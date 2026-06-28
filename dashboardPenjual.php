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

// ==================== AMBIL PRODUK PENJUAL ====================
try {
    $stmt = $pdo->prepare("SELECT id, nama_produk, harga_asli, harga_diskon, stok, satuan, gambar_url, status FROM produk WHERE penjual_id = ? ORDER BY created_at DESC");
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