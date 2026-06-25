<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// 🔐 SECURITY CHECK
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$msg = '';
$msg_class = '';

// 🔄 HANDLE ACTION POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. UPLOAD BUKTI BAYAR
    if (isset($_POST['upload_bukti'])) {
        $transaksi_id = (int)$_POST['transaksi_id'];
        
        // Cek kepemilikan transaksi
        $check = $pdo->prepare("SELECT id FROM transaksi WHERE id = ? AND user_id = ?");
        $check->execute([$transaksi_id, $user_id]);
        
        if ($check->rowCount() > 0) {
            if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION);
                $filename = 'bukti_' . $transaksi_id . '_' . time() . '.' . $ext;
                $target = 'uploads/' . $filename;
                
                // Buat folder uploads jika belum ada
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['bukti']['tmp_name'], $target)) {
                    try {
                        $upd = $pdo->prepare("UPDATE transaksi SET bukti_pembayaran = ?, status = 'dibayar' WHERE id = ?");
                        $upd->execute([$filename, $transaksi_id]);
                        $msg = '🎉 Bukti pembayaran berhasil diunggah! Status pesanan kini telah berubah.';
                        $msg_class = 'success';
                    } catch (PDOException $e) {
                        $msg = '❌ Gagal menyimpan data: ' . $e->getMessage();
                        $msg_class = 'error';
                    }
                } else {
                    $msg = '❌ Gagal mengunggah gambar. Coba lagi.';
                    $msg_class = 'error';
                }
            } else {
                $msg = '⚠️ Silakan pilih file gambar bukti bayar.';
                $msg_class = 'error';
            }
        }
    }
    
    // 2. BATALKAN PESANAN
    if (isset($_POST['batalkan_pesanan'])) {
        $transaksi_id = (int)$_POST['transaksi_id'];
        $alasan = trim($_POST['alasan_pembatalan'] ?? 'Dibatalkan oleh pembeli');
        
        // Hanya bisa batalkan jika status pending
        $check = $pdo->prepare("SELECT id FROM transaksi WHERE id = ? AND user_id = ? AND status = 'pending'");
        $check->execute([$transaksi_id, $user_id]);
        
        if ($check->rowCount() > 0) {
            try {
                $upd = $pdo->prepare("UPDATE transaksi SET status = 'dibatalkan', alasan_pembatalan = ? WHERE id = ?");
                $upd->execute([$alasan, $transaksi_id]);
                $msg = '🛑 Pesanan berhasil dibatalkan.';
                $msg_class = 'success';
            } catch (PDOException $e) {
                $msg = '❌ Gagal membatalkan pesanan: ' . $e->getMessage();
                $msg_class = 'error';
            }
        }
    }
    
    // 3. KONFIRMASI DITERIMA
    if (isset($_POST['konfirmasi_diterima'])) {
        $transaksi_id = (int)$_POST['transaksi_id'];
        
        // Hanya bisa konfirmasi jika status dikirim
        $check = $pdo->prepare("SELECT id FROM transaksi WHERE id = ? AND user_id = ? AND status = 'dikirim'");
        $check->execute([$transaksi_id, $user_id]);
        
        if ($check->rowCount() > 0) {
            try {
                $upd = $pdo->prepare("UPDATE transaksi SET status = 'selesai', shipping_status = 'selesai' WHERE id = ?");
                $upd->execute([$transaksi_id]);
                $msg = '🙌 Terima kasih! Pesanan telah selesai dikonfirmasi.';
                $msg_class = 'success';
            } catch (PDOException $e) {
                $msg = '❌ Gagal mengkonfirmasi pesanan: ' . $e->getMessage();
                $msg_class = 'error';
            }
        }
    }
}

// 📦 AMBIL RIWAYAT PEMBELIAN
$query = "SELECT t.id as transaksi_id, t.status, t.jumlah, t.total_harga, t.tanggal_pesanan,
                 t.alamat_pengiriman, t.no_telepon, t.metode_pembayaran,
                 t.bukti_pembayaran, t.no_resi, t.alasan_pembatalan, t.kode_voucher, t.diskon, t.ongkir,
                 p.nama_produk, p.gambar_url, p.satuan, p.harga_asli, p.harga_diskon,
                 pj.nama_toko, pj.kota
          FROM transaksi t
          JOIN produk p ON t.produk_id = p.id
          JOIN penjual pj ON p.penjual_id = pj.id
          WHERE t.user_id = ?
          ORDER BY t.tanggal_pesanan DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_colors = [
    'pending'    => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'dibayar'    => 'bg-blue-100 text-blue-800 border-blue-200',
    'dikirim'    => 'bg-purple-100 text-purple-800 border-purple-200',
    'selesai'    => 'bg-green-100 text-green-800 border-green-200',
    'dibatalkan' => 'bg-red-100 text-red-800 border-red-200',
];
$status_labels = [
    'pending'    => '⏳ Menunggu Pembayaran',
    'dibayar'    => '💳 Dibayar (Diproses)',
    'dikirim'    => '🚚 Sedang Dikirim',
    'selesai'    => '✅ Selesai Diterima',
    'dibatalkan' => '❌ Dibatalkan',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Belanja - FoodSave</title>
    <?php include 'includes/tailwind_config.php'; ?>
    <style>
        .modal-overlay { backdrop-filter: blur(4px); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-brand flex items-center gap-2">
                🌿 FoodSave
            </a>
            <div class="flex items-center gap-3">
                <a href="PromosiPage.php" class="text-sm text-gray-600 hover:text-brand font-medium">← Jelajahi Makanan Surplus</a>
            </div>
        </div>
    </nav>

    <!-- MAIN -->
    <main class="max-w-4xl mx-auto px-4 py-8">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-950">📦 Riwayat Belanja</h1>
                <p class="text-gray-500 text-sm mt-1">Lacak pengiriman dan riwayat pesanan surplus Anda</p>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $msg_class === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (count($purchases) === 0): ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-150 shadow-sm">
                <div class="text-6xl mb-4">🛒</div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Belum Ada Riwayat Belanja</h3>
                <p class="text-gray-500 mb-6 text-sm">Mari bantu selamatkan makanan surplus di sekitarmu sekarang juga!</p>
                <a href="PromosiPage.php" class="inline-block px-6 py-3 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl transition shadow-md">
                    Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($purchases as $p): ?>
                    <div class="bg-white rounded-2xl border border-gray-150 shadow-sm overflow-hidden transition hover:shadow-md">
                        <!-- Card Header -->
                        <div class="bg-gray-50 px-6 py-4 flex flex-wrap justify-between items-center border-b border-gray-100 gap-2">
                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                <span>📅 <?= date('d M Y, H:i', strtotime($p['tanggal_pesanan'])) ?></span>
                                <span class="text-gray-300">•</span>
                                <span>No. Transaksi: <strong class="text-gray-700 font-mono">#<?= str_pad($p['transaksi_id'], 6, '0', STR_PAD_LEFT) ?></strong></span>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold border <?= $status_colors[$p['status']] ?>">
                                <?= $status_labels[$p['status']] ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 flex flex-col md:flex-row gap-6">
                            <!-- Image -->
                            <div class="w-24 h-24 rounded-xl overflow-hidden bg-gray-100 flex-shrink-0">
                                <?php if ($p['gambar_url']): ?>
                                    <img src="<?= htmlspecialchars($p['gambar_url']) ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-3xl bg-gray-200">🍲</div>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 space-y-2">
                                <span class="text-xs bg-brand/10 text-brand font-bold px-2 py-0.5 rounded"><?= htmlspecialchars($p['nama_toko']) ?></span>
                                <h3 class="font-bold text-gray-900 text-lg leading-snug"><?= htmlspecialchars($p['nama_produk']) ?></h3>
                                <p class="text-sm text-gray-500"><?= $p['jumlah'] ?> <?= htmlspecialchars($p['satuan']) ?> x Rp <?= number_format($p['harga_diskon'] ?: $p['harga_asli'], 0, ',', '.') ?></p>
                                
                                <div class="pt-2 text-xs text-gray-500 space-y-1">
                                    <p>📍 Alamat Pengiriman: <strong><?= htmlspecialchars($p['alamat_pengiriman']) ?></strong></p>
                                    <p>📞 Kontak Pembeli: <strong><?= htmlspecialchars($p['no_telepon']) ?></strong></p>
                                    <p>💵 Pembayaran: <strong><?= htmlspecialchars($p['metode_pembayaran']) ?></strong></p>
                                    <?php if ($p['no_resi']): ?>
                                        <p class="text-purple-700">🚚 No. Resi Pengiriman: <strong class="bg-purple-50 px-2 py-0.5 rounded font-mono border border-purple-200"><?= htmlspecialchars($p['no_resi']) ?></strong></p>
                                    <?php endif; ?>
                                    <?php if ($p['alasan_pembatalan']): ?>
                                        <p class="text-red-600 bg-red-50 p-2 rounded border border-red-100 mt-2">❌ Alasan Batal: <strong><?= htmlspecialchars($p['alasan_pembatalan']) ?></strong></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pricing & Action -->
                            <div class="flex flex-col justify-between items-end border-t md:border-t-0 md:border-l border-gray-100 pt-4 md:pt-0 md:pl-6 gap-4 min-w-[200px]">
                                <div class="text-right w-full">
                                    <span class="text-xs text-gray-400 block">Total Transaksi</span>
                                    <span class="text-xl font-extrabold text-brand block mt-0.5">Rp <?= number_format($p['total_harga'], 0, ',', '.') ?></span>
                                    <span class="text-[10px] text-gray-400 block mt-1">(Termasuk biaya layanan, ongkir Rp <?= number_format($p['ongkir'], 0, ',', '.') ?>)</span>
                                </div>

                                <div class="w-full flex flex-col gap-2">
                                    <!-- Aksi 1: Upload Bukti Pembayaran -->
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <button onclick="openUploadModal(<?= $p['transaksi_id'] ?>)" class="w-full py-2.5 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl text-center text-sm shadow cursor-pointer transition">
                                            📤 Upload Bukti Bayar
                                        </button>
                                        <button onclick="openBatalModal(<?= $p['transaksi_id'] ?>)" class="w-full py-2 border border-red-200 text-red-600 hover:bg-red-50 font-medium rounded-xl text-center text-sm cursor-pointer transition">
                                            Batalkan Pesanan
                                        </button>
                                    <?php endif; ?>

                                    <!-- Aksi 2: Konfirmasi Barang Diterima -->
                                    <?php if ($p['status'] === 'dikirim'): ?>
                                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin pesanan sudah diterima dengan baik?')">
                                            <input type="hidden" name="transaksi_id" value="<?= $p['transaksi_id'] ?>">
                                            <button type="submit" name="konfirmasi_diterima" class="w-full py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl text-center text-sm shadow cursor-pointer transition">
                                                🤝 Konfirmasi Barang Diterima
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODAL UPLOAD BUKTI -->
    <div id="uploadModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 relative">
            <button onclick="closeUploadModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold">×</button>
            <h3 class="text-lg font-bold text-gray-900 mb-4">📤 Unggah Bukti Pembayaran</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="transaksi_id" id="upload_transaksi_id">
                
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center mb-4 hover:border-brand transition">
                    <input type="file" name="bukti" accept="image/*" required class="hidden" id="buktiFile" onchange="previewFile()">
                    <label for="buktiFile" class="cursor-pointer space-y-2 block">
                        <div class="text-4xl text-gray-400">📷</div>
                        <span class="text-sm font-medium text-brand hover:underline block">Pilih Foto Bukti Transfer</span>
                        <span class="text-xs text-gray-400 block">Mendukung format JPG, PNG, WEBP</span>
                    </label>
                    <div id="filePreviewName" class="mt-2 text-xs font-semibold text-green-600 hidden"></div>
                </div>
                
                <button type="submit" name="upload_bukti" class="w-full py-3 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl transition shadow cursor-pointer">
                    Simpan & Konfirmasi
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL BATAL -->
    <div id="batalModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 relative">
            <button onclick="closeBatalModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold">×</button>
            <h3 class="text-lg font-bold text-gray-900 mb-4">🛑 Batalkan Pesanan</h3>
            
            <form method="POST">
                <input type="hidden" name="transaksi_id" id="batal_transaksi_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Pembatalan *</label>
                    <textarea name="alasan_pembatalan" rows="3" required placeholder="Tuliskan alasan pembatalan Anda..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none resize-none transition text-sm"></textarea>
                </div>
                
                <button type="submit" name="batalkan_pesanan" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition shadow cursor-pointer">
                    Konfirmasi Pembatalan
                </button>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT MODAL -->
    <script>
        function openUploadModal(id) {
            document.getElementById('upload_transaksi_id').value = id;
            document.getElementById('uploadModal').classList.remove('hidden');
        }
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('buktiFile').value = '';
            document.getElementById('filePreviewName').classList.add('hidden');
        }
        
        function openBatalModal(id) {
            document.getElementById('batal_transaksi_id').value = id;
            document.getElementById('batalModal').classList.remove('hidden');
        }
        function closeBatalModal() {
            document.getElementById('batalModal').classList.add('hidden');
        }
        
        function previewFile() {
            const fileInput = document.getElementById('buktiFile');
            const preview = document.getElementById('filePreviewName');
            if (fileInput.files.length > 0) {
                preview.textContent = '✓ ' + fileInput.files[0].name;
                preview.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>