<?php
session_start();
include 'koneksi.php';

// 🔐 SECURITY CHECK
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, nama_toko, status_verifikasi FROM penjual WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$penjual = $stmt->get_result()->fetch_assoc();

if (!$penjual) { header("Location: lengkapi_toko.php"); exit; }
$penjual_id = $penjual['id'];

$msg = '';
$msg_type = '';

// 🔄 HANDLE UPDATE STATUS + RESI / ALASAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $transaksi_id = (int)$_POST['transaksi_id'];
    $new_status   = $_POST['new_status'];
    $valid_status = ['pending', 'dibayar', 'dikirim', 'selesai', 'dibatalkan'];

    if (in_array($new_status, $valid_status)) {
        $check = $conn->prepare("SELECT id FROM transaksi WHERE id = ? AND penjual_id = ?");
        $check->bind_param("ii", $transaksi_id, $penjual_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            if ($new_status === 'dikirim') {
                $no_resi = trim($_POST['no_resi'] ?? '');
                $upd = $conn->prepare("UPDATE transaksi SET status = 'dikirim', no_resi = ? WHERE id = ?");
                $upd->bind_param("si", $no_resi, $transaksi_id);
            } elseif ($new_status === 'dibatalkan') {
                $alasan = trim($_POST['alasan_pembatalan'] ?? '');
                $upd = $conn->prepare("UPDATE transaksi SET status = 'dibatalkan', alasan_pembatalan = ? WHERE id = ?");
                $upd->bind_param("si", $alasan, $transaksi_id);
            } else {
                $upd = $conn->prepare("UPDATE transaksi SET status = ? WHERE id = ?");
                $upd->bind_param("si", $new_status, $transaksi_id);
            }
            $upd->execute();
            $msg = 'Status pesanan berhasil diperbarui!';
            $msg_type = 'success';
        }
    }
}

// 📦 AMBIL DATA PESANAN
$query = "SELECT t.id as transaksi_id, t.status, t.jumlah, t.total_harga, t.tanggal_pesanan,
                 t.alamat_pengiriman, t.no_telepon, t.metode_pembayaran,
                 t.bukti_pembayaran, t.no_resi, t.alasan_pembatalan,
                 u.nama_lengkap as nama_pembeli, u.email as email_pembeli,
                 p.nama_produk, p.gambar_url, p.satuan
          FROM transaksi t
          JOIN users u ON t.user_id = u.id
          JOIN produk p ON t.produk_id = p.id
          WHERE t.penjual_id = ?
          ORDER BY t.tanggal_pesanan DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $penjual_id);
$stmt->execute();
$orders = $stmt->get_result();

$status_colors = [
    'pending'    => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'dibayar'    => 'bg-blue-100 text-blue-700 border-blue-200',
    'dikirim'    => 'bg-purple-100 text-purple-700 border-purple-200',
    'selesai'    => 'bg-green-100 text-green-700 border-green-200',
    'dibatalkan' => 'bg-red-100 text-red-700 border-red-200',
];
$status_labels = [
    'pending'    => '⏳ Pending',
    'dibayar'    => '💳 Dibayar',
    'dikirim'    => '🚚 Dikirim',
    'selesai'    => '✅ Selesai',
    'dibatalkan' => '❌ Dibatalkan',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Masuk - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .modal-overlay { backdrop-filter: blur(4px); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white shadow-lg hidden md:flex flex-col">
        <div class="p-6 border-b border-gray-100">
            <a href="Index.php" class="text-2xl font-extrabold text-green-600">🌿 FoodSave</a>
            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($penjual['nama_toko']) ?></p>
        </div>
        <nav class="p-4 space-y-1 flex-1">
            <a href="dashboardPenjual.php" class="flex items-center gap-3 px-4 py-2.5 text-gray-600 hover:bg-gray-100 rounded-xl font-medium transition">📊 Dashboard</a>
            <a href="tambah_produk.php" class="flex items-center gap-3 px-4 py-2.5 text-gray-600 hover:bg-gray-100 rounded-xl font-medium transition">➕ Tambah Produk</a>
            <a href="pesanan.php" class="flex items-center gap-3 px-4 py-2.5 bg-green-500 text-white rounded-xl font-semibold shadow-sm">📬 Pesanan Masuk</a>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 text-red-600 hover:bg-red-50 rounded-xl font-medium transition">🚪 Logout</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-6 md:p-8 overflow-x-hidden">

        <!-- HEADER -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📬 Pesanan Masuk</h1>
                <p class="text-gray-500 text-sm">Kelola & perbarui status pesanan pembeli</p>
            </div>
            <?php if ($msg): ?>
                <div class="<?= $msg_type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?> px-4 py-2 rounded-xl text-sm font-medium border">
                    <?= $msg_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($orders->num_rows === 0): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                <div class="text-6xl mb-4">📭</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Pesanan</h3>
                <p class="text-gray-500 max-w-sm mx-auto text-sm">Pesanan dari pembeli akan otomatis muncul di sini setelah checkout berhasil.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
            <?php while ($o = $orders->fetch_assoc()): ?>
                <?php $color = $status_colors[$o['status']] ?? 'bg-gray-100 text-gray-700 border-gray-200'; ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                    <!-- Order Header -->
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-sm font-bold text-gray-800 bg-white px-3 py-1 rounded-lg border">
                                #<?= str_pad($o['transaksi_id'], 4, '0', STR_PAD_LEFT) ?>
                            </span>
                            <span class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($o['tanggal_pesanan'])) ?></span>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?= $color ?>">
                            <?= $status_labels[$o['status']] ?? ucfirst($o['status']) ?>
                        </span>
                    </div>

                    <div class="p-6 grid md:grid-cols-3 gap-6">

                        <!-- Kolom 1: Produk + Pembeli -->
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Produk Dipesan</p>
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($o['gambar_url'] ?: 'https://via.placeholder.com/60') ?>" 
                                         class="w-14 h-14 rounded-xl object-cover bg-gray-100 flex-shrink-0" alt="">
                                    <div>
                                        <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($o['nama_produk']) ?></p>
                                        <p class="text-xs text-gray-500"><?= $o['jumlah'] ?> <?= htmlspecialchars($o['satuan']) ?></p>
                                        <p class="text-sm font-bold text-green-600 mt-0.5">Rp <?= number_format($o['total_harga'], 0, ',', '.') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Data Pembeli</p>
                                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($o['nama_pembeli']) ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($o['email_pembeli']) ?></p>
                                <?php if ($o['no_telepon']): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $o['no_telepon']) ?>" target="_blank"
                                       class="inline-flex items-center gap-1 mt-1 text-xs text-green-600 font-semibold hover:underline">
                                        📱 <?= htmlspecialchars($o['no_telepon']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($o['alamat_pengiriman']): ?>
                                    <p class="text-xs text-gray-500 mt-1">📍 <?= htmlspecialchars($o['alamat_pengiriman']) ?></p>
                                <?php endif; ?>
                                <?php if ($o['metode_pembayaran']): ?>
                                    <p class="text-xs text-gray-400 mt-1">💳 <?= htmlspecialchars($o['metode_pembayaran']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Kolom 2: Bukti Bayar + Resi/Alasan -->
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Bukti Pembayaran</p>
                                <?php if (!empty($o['bukti_pembayaran'])): ?>
                                    <a href="<?= htmlspecialchars($o['bukti_pembayaran']) ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($o['bukti_pembayaran']) ?>" 
                                             class="w-full max-w-[180px] h-28 object-cover rounded-xl border-2 border-green-200 hover:border-green-500 transition cursor-zoom-in"
                                             alt="Bukti Pembayaran">
                                        <p class="text-xs text-green-600 font-semibold mt-1">🔍 Klik untuk perbesar</p>
                                    </a>
                                <?php else: ?>
                                    <div class="w-full max-w-[180px] h-28 bg-gray-100 rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center">
                                        <span class="text-2xl">🧾</span>
                                        <p class="text-xs text-gray-400 mt-1">Belum ada bukti</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($o['no_resi']): ?>
                                <div>
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">No. Resi Pengiriman</p>
                                    <span class="inline-block bg-purple-50 text-purple-700 border border-purple-200 font-mono text-sm px-3 py-1.5 rounded-lg font-bold">
                                        <?= htmlspecialchars($o['no_resi']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($o['alasan_pembatalan']): ?>
                                <div>
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Alasan Pembatalan</p>
                                    <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"><?= htmlspecialchars($o['alasan_pembatalan']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Kolom 3: Aksi Update Status -->
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Ubah Status Pesanan</p>

                            <?php if ($o['status'] === 'dibatalkan' || $o['status'] === 'selesai'): ?>
                                <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-200">
                                    <p class="text-sm text-gray-500">Pesanan sudah <?= $o['status'] === 'selesai' ? 'selesai' : 'dibatalkan' ?>.</p>
                                    <p class="text-xs text-gray-400 mt-1">Status tidak dapat diubah lagi.</p>
                                </div>

                            <?php elseif ($o['status'] === 'dikirim'): ?>
                                <!-- Hanya bisa menyelesaikan -->
                                <form method="POST">
                                    <input type="hidden" name="transaksi_id" value="<?= $o['transaksi_id'] ?>">
                                    <input type="hidden" name="new_status" value="selesai">
                                    <button type="submit" name="update_status"
                                            class="w-full py-2.5 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl transition shadow-sm text-sm cursor-pointer">
                                        ✅ Tandai Selesai
                                    </button>
                                </form>

                            <?php else: ?>
                                <div class="space-y-2">
                                    <!-- Konfirmasi Pembayaran (pending → dibayar) -->
                                    <?php if ($o['status'] === 'pending' && !empty($o['bukti_pembayaran'])): ?>
                                        <form method="POST">
                                            <input type="hidden" name="transaksi_id" value="<?= $o['transaksi_id'] ?>">
                                            <input type="hidden" name="new_status" value="dibayar">
                                            <button type="submit" name="update_status"
                                                    class="w-full py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-xl transition text-sm cursor-pointer">
                                                💳 Konfirmasi Pembayaran
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Kirim Produk (dibayar → dikirim dengan resi) -->
                                    <?php if ($o['status'] === 'dibayar'): ?>
                                        <button type="button"
                                                onclick="document.getElementById('modal-kirim-<?= $o['transaksi_id'] ?>').classList.remove('hidden')"
                                                class="w-full py-2 bg-purple-500 hover:bg-purple-600 text-white font-semibold rounded-xl transition text-sm cursor-pointer">
                                            🚚 Input Resi & Kirim
                                        </button>
                                    <?php endif; ?>

                                    <!-- Batalkan Pesanan -->
                                    <button type="button"
                                            onclick="document.getElementById('modal-batal-<?= $o['transaksi_id'] ?>').classList.remove('hidden')"
                                            class="w-full py-2 bg-red-50 hover:bg-red-100 text-red-600 font-semibold rounded-xl transition text-sm border border-red-200 cursor-pointer">
                                        ❌ Batalkan Pesanan
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- MODAL: Input Resi & Kirim -->
                <div id="modal-kirim-<?= $o['transaksi_id'] ?>" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 modal-overlay">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">🚚 Input Nomor Resi</h3>
                        <p class="text-sm text-gray-500 mb-4">Masukkan nomor resi pengiriman untuk pesanan <strong>#<?= str_pad($o['transaksi_id'], 4, '0', STR_PAD_LEFT) ?></strong>.</p>
                        <form method="POST">
                            <input type="hidden" name="transaksi_id" value="<?= $o['transaksi_id'] ?>">
                            <input type="hidden" name="new_status" value="dikirim">
                            <input type="text" name="no_resi" required placeholder="Contoh: JNE123456789ID"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 outline-none mb-4 text-sm font-mono">
                            <div class="flex gap-2">
                                <button type="button" onclick="document.getElementById('modal-kirim-<?= $o['transaksi_id'] ?>').classList.add('hidden')"
                                        class="flex-1 py-2 border border-gray-300 rounded-xl text-sm text-gray-600 hover:bg-gray-50 cursor-pointer">Batal</button>
                                <button type="submit" name="update_status"
                                        class="flex-1 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-xl text-sm font-bold cursor-pointer">Kirim!</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- MODAL: Alasan Pembatalan -->
                <div id="modal-batal-<?= $o['transaksi_id'] ?>" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 modal-overlay">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">❌ Batalkan Pesanan</h3>
                        <p class="text-sm text-gray-500 mb-4">Berikan alasan pembatalan untuk pesanan <strong>#<?= str_pad($o['transaksi_id'], 4, '0', STR_PAD_LEFT) ?></strong>. Alasan ini akan dilihat oleh pembeli.</p>
                        <form method="POST">
                            <input type="hidden" name="transaksi_id" value="<?= $o['transaksi_id'] ?>">
                            <input type="hidden" name="new_status" value="dibatalkan">
                            <textarea name="alasan_pembatalan" rows="3" required placeholder="Contoh: Stok habis mendadak, mohon maaf atas ketidaknyamanannya."
                                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-400 outline-none resize-none mb-4 text-sm"></textarea>
                            <div class="flex gap-2">
                                <button type="button" onclick="document.getElementById('modal-batal-<?= $o['transaksi_id'] ?>').classList.add('hidden')"
                                        class="flex-1 py-2 border border-gray-300 rounded-xl text-sm text-gray-600 hover:bg-gray-50 cursor-pointer">Batal</button>
                                <button type="submit" name="update_status"
                                        class="flex-1 py-2 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-bold cursor-pointer">Konfirmasi Batal</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>