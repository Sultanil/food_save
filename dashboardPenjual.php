<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/payment_methods.php';

// 🔐 SECURITY CHECK
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

// Ambil user_id dari session
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;

// ==================== AMBIL DATA PENJUAL ====================
try {
    $stmt = $pdo->prepare("SELECT id, nama_toko, status_verifikasi, alasan_penolakan FROM penjual WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $penjual = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (!$penjual) {
    header("Location: lengkapi_toko.php");
    exit;
}

$penjual_id = $penjual['id'];
$status_verifikasi = $penjual['status_verifikasi'] ?? 'pending';
$alasan_penolakan = $penjual['alasan_penolakan'] ?? '';

// ==================== HANDLE UPDATE STATUS PESANAN ====================
$pesan_update = '';
$pesan_update_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $transaksi_id = (int)$_POST['transaksi_id'];
    $new_shipping_status = $_POST['shipping_status'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    // Validasi sesuai ENUM di database
    $allowed_shipping_status = ['diproses', 'dikirim', 'diterima'];
    $allowed_status = ['pending', 'dibayar', 'selesai', 'dibatalkan'];
    
    if (!in_array($new_shipping_status, $allowed_shipping_status)) {
        $pesan_update = "❌ Status pengiriman tidak valid!";
        $pesan_update_class = 'error';
    } elseif (!in_array($new_status, $allowed_status)) {
        $pesan_update = "❌ Status transaksi tidak valid!";
        $pesan_update_class = 'error';
    } else {
        try {
            $stmt_update = $pdo->prepare("
                UPDATE transaksi 
                SET shipping_status = ?, status = ?
                WHERE id = ? AND penjual_id = ?
            ");
            $stmt_update->execute([$new_shipping_status, $new_status, $transaksi_id, $penjual_id]);
            
            $pesan_update = "✅ Status pesanan berhasil diupdate!";
            $pesan_update_class = 'success';
            
            // Refresh halaman
            header("Location: dashboardPenjual.php?msg=status_updated");
            exit;
        } catch (PDOException $e) {
            $pesan_update = "❌ Gagal update status: " . $e->getMessage();
            $pesan_update_class = 'error';
        }
    }
}

// ==================== AMBIL PESANAN MASUK DENGAN BUKTI PEMBAYARAN ====================
$stmt_pesanan = $pdo->prepare("
    SELECT 
        t.*,
        p.nama_produk,
        p.harga_asli,
        p.harga_diskon,
        p.satuan,
        u.nama_lengkap as nama_pembeli,
        u.email as email_pembeli
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    JOIN users u ON t.user_id = u.id
    WHERE t.penjual_id = ?
    ORDER BY 
        CASE 
            WHEN t.status = 'pending' AND t.bukti_pembayaran IS NOT NULL THEN 1
            WHEN t.status = 'pending' THEN 2
            WHEN t.status = 'dibayar' THEN 3
            WHEN t.shipping_status = 'diproses' THEN 4
            WHEN t.shipping_status = 'dikirim' THEN 5
            WHEN t.shipping_status = 'diterima' THEN 6
            ELSE 7
        END,
        t.tanggal_pesanan DESC
");
$stmt_pesanan->execute([$penjual_id]);
$pesanan_list = $stmt_pesanan->fetchAll(PDO::FETCH_ASSOC);

// Group by status
$pesanan_perlu_verifikasi = array_filter($pesanan_list, fn($p) => $p['status'] === 'pending' && !empty($p['bukti_pembayaran']));
$pesanan_dibayar = array_filter($pesanan_list, fn($p) => $p['status'] === 'dibayar');
$pesanan_diproses = array_filter($pesanan_list, fn($p) => $p['shipping_status'] === 'diproses');
$pesanan_dikirim = array_filter($pesanan_list, fn($p) => $p['shipping_status'] === 'dikirim');
$pesanan_selesai = array_filter($pesanan_list, fn($p) => $p['shipping_status'] === 'diterima');

// ==================== AMBIL PRODUK PENJUAL ====================
try {
    $stmt = $pdo->prepare("SELECT id, nama_produk, harga_asli, harga_diskon, stok, satuan, gambar_url, status FROM produk WHERE penjual_id = ? ORDER BY id DESC");
    $stmt->execute([$penjual_id]);
    $produk_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produk_list = [];
}

// ==================== HITUNG STATISTIK ====================
try {
    $stmt_stats = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(stok), 0) as total_stok FROM produk WHERE penjual_id = ?");
    $stmt_stats->execute([$penjual_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'total_stok' => 0];
}

// ==================== HITUNG PESANAN PENDING ====================
try {
    $stmt_orders = $pdo->prepare("
        SELECT COUNT(*) as total_pending 
        FROM transaksi 
        WHERE penjual_id = ? AND status IN ('pending', 'dibayar')
    ");
    $stmt_orders->execute([$penjual_id]);
    $orders_stats = $stmt_orders->fetch(PDO::FETCH_ASSOC);
    $total_pending_orders = $orders_stats['total_pending'] ?? 0;
} catch (PDOException $e) {
    $total_pending_orders = 0;
}

// ==================== CEK PAYMENT METHODS ====================
$payment_methods_count = 0;
if ($status_verifikasi === 'disetujui') {
    $payment_methods_count = countSellerPaymentMethods($pdo, $penjual_id);
}

// ==================== PESAN DARI REDIRECT ====================
$success_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'verifikasi_submitted') {
        $success_msg = "✅ Verifikasi toko berhasil disubmit! Tim admin akan meninjau dalam 1x24 jam.";
    } elseif ($_GET['msg'] === 'updated') {
        $success_msg = "✅ Produk berhasil diperbarui!";
    } elseif ($_GET['msg'] === 'deactivated') {
        $success_msg = "✅ Produk berhasil dinonaktifkan!";
    } elseif ($_GET['msg'] === 'deleted') {
        $success_msg = "✅ Metode pembayaran berhasil dihapus!";
    } elseif ($_GET['msg'] === 'status_updated') {
        $success_msg = "✅ Status pesanan berhasil diperbarui!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($penjual['nama_toko']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-50 text-gray-800 font-sans">

    <div class="flex min-h-screen">

        <!-- SIDEBAR (dari include) -->
        <?php include 'includes/sidebar_penjual.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="flex-1 p-6 md:p-8">

            <!-- HEADER -->
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Dashboard <?= htmlspecialchars($penjual['nama_toko']) ?> 👋</h2>
                    <p class="text-gray-500 mt-1">Kelola produk dan pantau penjualan Anda</p>
                </div>
                <?php if ($total_pending_orders > 0 && $status_verifikasi === 'disetujui'): ?>
                    <a href="pesanan.php" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg transition shadow animate-pulse">
                        🔔 <?= $total_pending_orders ?> Pesanan Baru
                    </a>
                <?php endif; ?>
            </div>

            <!-- SUCCESS MESSAGE -->
            <?php if ($success_msg): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-xl shadow-sm mb-6 flex items-start gap-3">
                    <span class="text-2xl">✅</span>
                    <div>
                        <p class="text-sm"><?= $success_msg ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- STATUS VERIFIKASI BANNER -->
            <?php if ($status_verifikasi === 'pending'): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-xl shadow-sm mb-6 flex items-start gap-3">
                    <span class="text-2xl">⏳</span>
                    <div>
                        <h4 class="font-bold text-yellow-900">Pendaftaran Toko Sedang Ditinjau</h4>
                        <p class="text-sm mt-1">Profil toko Anda sedang ditinjau oleh Admin. Anda akan dapat menambahkan produk dan mulai menerima pesanan setelah toko disetujui.</p>
                    </div>
                </div>
            <?php elseif ($status_verifikasi === 'ditolak'): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-xl shadow-sm mb-6 flex items-start gap-3">
                    <span class="text-2xl">❌</span>
                    <div>
                        <h4 class="font-bold text-red-900">Pendaftaran Toko Ditolak</h4>
                        <p class="text-sm mt-1">Maaf, pendaftaran toko Anda ditolak oleh Admin.</p>
                        <p class="text-sm font-semibold mt-1">Alasan Penolakan: <?= htmlspecialchars($alasan_penolakan) ?></p>
                        <a href="lengkapi_toko.php" class="inline-block mt-3 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition shadow">
                            ✏️ Perbaiki Profil Toko
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- WARNING: BELUM SETUP PAYMENT METHODS -->
            <?php if ($status_verifikasi === 'disetujui' && $payment_methods_count === 0): ?>
                <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-800 p-4 rounded-xl shadow-sm mb-6 flex items-start gap-3">
                    <span class="text-2xl">⚠️</span>
                    <div class="flex-1">
                        <h4 class="font-bold text-amber-900">Belum Ada Metode Pembayaran</h4>
                        <p class="text-sm mt-1">Anda perlu menambahkan minimal 1 rekening bank agar pembeli bisa melakukan pembayaran.</p>
                        <a href="setup_payment.php" class="inline-block mt-3 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition shadow">
                            💳 Setup Payment Methods
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- STATS CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Produk</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['total'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-2xl">📦</div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Stok</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['total_stok'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-2xl">📊</div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Status Toko</p>
                            <?php
                            $status_label = [
                                'disetujui' => ['✅ Disetujui', 'text-green-600'],
                                'pending' => ['⏳ Menunggu', 'text-yellow-600'],
                                'ditolak' => ['❌ Ditolak', 'text-red-600']
                            ];
                            $s = $status_label[$status_verifikasi] ?? ['❓ Tidak Diketahui', 'text-gray-600'];
                            ?>
                            <p class="text-lg font-semibold <?= $s[1] ?> mt-2"><?= $s[0] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center text-2xl">🏪</div>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS (HANYA UNTUK TOKO DISETUJUI) -->
            <?php if ($status_verifikasi === 'disetujui'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
                    <h3 class="font-semibold text-lg text-gray-900 mb-4">⚡ Aksi Cepat</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="tambah_produk.php" class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition group">
                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">➕</span>
                            <span class="text-sm font-medium text-gray-700">Tambah Produk</span>
                        </a>
                        <a href="pesanan.php" class="flex flex-col items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-xl transition group relative">
                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">📬</span>
                            <span class="text-sm font-medium text-gray-700">Pesanan Masuk</span>
                            <?php if ($total_pending_orders > 0): ?>
                                <span class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?= $total_pending_orders ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="setup_payment.php" class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition group">
                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">💳</span>
                            <span class="text-sm font-medium text-gray-700">Payment Methods</span>
                        </a>
                        <a href="riwayat_penjualan.php" class="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition group">
                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">📈</span>
                            <span class="text-sm font-medium text-gray-700">Riwayat Penjualan</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ==================== SECTION VERIFIKASI PEMBAYARAN ==================== -->
            <?php if ($status_verifikasi === 'disetujui' && count($pesanan_perlu_verifikasi) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm border-2 border-yellow-200 overflow-hidden mb-8">
                <div class="p-6 border-b border-yellow-100 bg-yellow-50 flex items-center justify-between">
                    <h3 class="font-semibold text-lg text-gray-900 flex items-center gap-2">
                        <span class="text-2xl">💳</span>
                        Verifikasi Pembayaran
                        <span class="px-3 py-1 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse">
                            <?= count($pesanan_perlu_verifikasi) ?>
                        </span>
                    </h3>
                    <a href="pesanan.php" class="text-sm text-yellow-700 hover:text-yellow-900 font-medium">
                        Lihat Semua →
                    </a>
                </div>

                <div class="p-6 space-y-6">
                    <?php foreach (array_slice($pesanan_perlu_verifikasi, 0, 3) as $pesanan): ?>
                        <div class="border-2 border-gray-200 rounded-xl p-5 hover:border-yellow-300 transition">
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-4 pb-3 border-b border-gray-100">
                                <div>
                                    <h4 class="font-bold text-gray-900">
                                        Pesanan #<?= substr($pesanan['checkout_batch_id'], -6) ?>
                                    </h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])) ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-full">
                                    Menunggu Konfirmasi
                                </span>
                            </div>

                            <div class="grid md:grid-cols-2 gap-5">
                                <!-- Info Pembeli & Produk -->
                                <div class="space-y-3">
                                    <!-- Data Pembeli -->
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-xs font-semibold text-gray-500 mb-2">👤 PEMBELI</p>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($pesanan['nama_pembeli']) ?></p>
                                        <p class="text-xs text-gray-600">📞 <?= htmlspecialchars($pesanan['no_telepon']) ?></p>
                                        <p class="text-xs text-gray-600 mt-1">📍 <?= htmlspecialchars(substr($pesanan['alamat_pengiriman'], 0, 50)) ?>...</p>
                                    </div>

                                    <!-- Produk -->
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-xs font-semibold text-gray-500 mb-2">📦 PRODUK</p>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($pesanan['nama_produk']) ?></p>
                                        <p class="text-xs text-gray-600">Qty: <?= $pesanan['jumlah'] ?> <?= htmlspecialchars($pesanan['satuan'] ?? '') ?></p>
                                        <p class="text-sm font-bold text-green-600 mt-1">Rp <?= number_format($pesanan['total_harga'], 0, ',', '.') ?></p>
                                    </div>

                                    <!-- Metode Pembayaran -->
                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <p class="text-xs font-semibold text-blue-700 mb-1">💳 METODE PEMBAYARAN</p>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($pesanan['metode_pembayaran']) ?></p>
                                    </div>
                                </div>

                                <!-- Bukti Pembayaran -->
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 mb-2">📸 BUKTI PEMBAYARAN</p>
                                    <?php if (!empty($pesanan['bukti_pembayaran']) && file_exists($pesanan['bukti_pembayaran'])): ?>
                                        <a href="<?= htmlspecialchars($pesanan['bukti_pembayaran']) ?>" target="_blank" class="block mb-3">
                                            <img src="<?= htmlspecialchars($pesanan['bukti_pembayaran']) ?>" 
                                                 alt="Bukti Pembayaran" 
                                                 class="w-full h-48 object-cover rounded-lg border-2 border-gray-200 hover:border-yellow-400 transition cursor-pointer shadow-sm">
                                            <p class="text-xs text-center text-blue-600 mt-2 hover:underline">
                                                🔍 Klik untuk memperbesar
                                            </p>
                                        </a>
                                    <?php else: ?>
                                        <div class="bg-gray-100 rounded-lg p-6 text-center text-gray-400 mb-3">
                                            <div class="text-3xl mb-2">📷</div>
                                            <p class="text-xs">Belum ada bukti</p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Form Update Status -->
                                    <form method="POST" class="space-y-2">
                                        <input type="hidden" name="transaksi_id" value="<?= $pesanan['id'] ?>">
                                        <input type="hidden" name="status" value="dibayar">
                                        
                                        <select name="shipping_status" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none">
                                            <option value="diproses">🔨 Sedang Diproses</option>
                                            <option value="dikirim">📦 Sudah Dikirim</option>
                                            <option value="diterima">✅ Pesanan Diterima</option>
                                        </select>

                                        <button type="submit" name="update_status"
                                            class="w-full py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition cursor-pointer text-sm">
                                            ✅ Konfirmasi Pembayaran
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($pesanan_perlu_verifikasi) > 3): ?>
                        <div class="text-center pt-4 border-t border-gray-100">
                            <a href="pesanan.php" class="text-sm text-yellow-700 hover:text-yellow-900 font-medium">
                                Lihat <?= count($pesanan_perlu_verifikasi) - 3 ?> pesanan lainnya →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ==================== SECTION PESANAN DIPROSES ==================== -->
            <?php if ($status_verifikasi === 'disetujui' && count($pesanan_diproses) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-lg text-gray-900 flex items-center gap-2">
                        <span class="text-2xl">🔨</span>
                        Sedang Diproses
                        <?php if (count($pesanan_diproses) > 0): ?>
                            <span class="px-3 py-1 bg-blue-500 text-white text-xs font-bold rounded-full">
                                <?= count($pesanan_diproses) ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <a href="pesanan.php" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                        Lihat Semua →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Pembeli</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Produk</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Total</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">Update Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($pesanan_diproses as $pesanan): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-xs">#<?= substr($pesanan['checkout_batch_id'], -6) ?></td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($pesanan['nama_pembeli']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($pesanan['no_telepon']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($pesanan['nama_produk']) ?></p>
                                        <p class="text-xs text-gray-500">Qty: <?= $pesanan['jumlah'] ?></p>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-green-600">
                                        Rp <?= number_format($pesanan['total_harga'], 0, ',', '.') ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" class="inline-flex items-center gap-2">
                                            <input type="hidden" name="transaksi_id" value="<?= $pesanan['id'] ?>">
                                            <input type="hidden" name="status" value="dibayar">
                                            <select name="shipping_status" onchange="this.form.submit()" 
                                                class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                                                <option value="diproses" <?= $pesanan['shipping_status'] === 'diproses' ? 'selected' : '' ?>>🔨 Diproses</option>
                                                <option value="dikirim" <?= $pesanan['shipping_status'] === 'dikirim' ? 'selected' : '' ?>>📦 Dikirim</option>
                                                <option value="diterima" <?= $pesanan['shipping_status'] === 'diterima' ? 'selected' : '' ?>>✅ Diterima</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- PRODUK SECTION -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h3 class="font-semibold text-lg text-gray-900">Produk Saya</h3>
                    <?php if ($status_verifikasi === 'disetujui'): ?>
                        <a href="tambah_produk.php" class="inline-flex items-center justify-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm font-medium transition cursor-pointer">
                            <span class="mr-2">+</span> Tambah Produk
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (count($produk_list) === 0): ?>
                    <!-- EMPTY STATE -->
                    <div class="p-12 text-center">
                        <div class="text-6xl mb-4">📦</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Produk</h3>
                        <p class="text-gray-500 mb-6">Yuk tambah produk pertama Anda dan mulai berjualan!</p>
                        <?php if ($status_verifikasi === 'disetujui'): ?>
                            <a href="tambah_produk.php" class="inline-block px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-medium transition">
                                Tambah Produk Pertama
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- PRODUK TABLE -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($produk_list as $p): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <?php if (!empty($p['gambar_url'])): ?>
                                                    <img src="<?= htmlspecialchars($p['gambar_url']) ?>"
                                                        alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                                                        class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center text-2xl">📷</div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($p['nama_produk']) ?></p>
                                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($p['satuan'] ?? 'pcs') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($p['harga_diskon']) && $p['harga_diskon'] < $p['harga_asli']): ?>
                                                <p class="font-semibold text-green-600">Rp <?= number_format($p['harga_diskon'], 0, ',', '.') ?></p>
                                                <p class="text-sm text-gray-400 line-through">Rp <?= number_format($p['harga_asli'], 0, ',', '.') ?></p>
                                            <?php else: ?>
                                                <p class="font-semibold text-gray-900">Rp <?= number_format($p['harga_asli'], 0, ',', '.') ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="<?= $p['stok'] < 10 ? 'text-red-600 font-semibold' : 'text-gray-900' ?>">
                                                <?= $p['stok'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $status_colors = [
                                                'aktif' => 'bg-green-100 text-green-700',
                                                'habis' => 'bg-red-100 text-red-700',
                                                'nonaktif' => 'bg-gray-100 text-gray-700'
                                            ];
                                            $status = $p['status'] ?? 'aktif';
                                            ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?= $status_colors[$status] ?? 'bg-gray-100' ?>">
                                                <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right space-x-2">
                                            <a href="kelola_produk.php?id=<?= $p['id'] ?>&action=edit"
                                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                ✏️ Edit
                                            </a>
                                            <a href="kelola_produk.php?id=<?= $p['id'] ?>&action=delete"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium"
                                                onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                                🗑️ Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

</body>

</html>