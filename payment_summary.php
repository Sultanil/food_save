<?php
// payment_summary.php - Upload Bukti Pembayaran
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

if (!isset($_GET['batch_id']) || !isset($_SESSION['sudah_login'])) {
    header("Location: Index.php");
    exit;
}

$batch_id = $_GET['batch_id'];
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;

// ==================== AMBIL DATA TRANSAKSI ====================
$stmt = $pdo->prepare("
    SELECT t.*, p.nama_produk, p.gambar_url, pj.nama_toko, pj.id as penjual_id
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    JOIN penjual pj ON t.penjual_id = pj.id
    WHERE t.checkout_batch_id = ? AND t.user_id = ?
    ORDER BY t.id ASC
");
$stmt->execute([$batch_id, $user_id]);
$transaksi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($transaksi_list)) {
    die("<div class='min-h-screen flex items-center justify-center bg-gray-50'>
            <div class='text-center p-8'>
                <div class='text-6xl mb-4'>❌</div>
                <h2 class='text-xl font-bold text-gray-900 mb-2'>Transaksi Tidak Ditemukan</h2>
                <a href='Index.php' class='inline-block px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-medium'>← Kembali ke Beranda</a>
            </div>
        </div>");
}

// Ambil data pertama untuk info umum
$first_transaksi = $transaksi_list[0];
$total_bayar = (float)($_GET['total'] ?? $first_transaksi['total_harga']);
$pembayaran = $_GET['pembayaran'] ?? $first_transaksi['metode_pembayaran'];
$penjual_id = $first_transaksi['penjual_id'];

// ==================== AMBIL PAYMENT METHODS PENJUAL ====================
$stmt_pm = $pdo->prepare("
    SELECT * FROM seller_payment_methods 
    WHERE penjual_id = ? AND is_active = 1 
    ORDER BY is_default DESC, created_at ASC
");
$stmt_pm->execute([$penjual_id]);
$payment_methods = $stmt_pm->fetchAll(PDO::FETCH_ASSOC);

$bank_accounts = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'bank_transfer');
$qris_list = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'qris');

// ==================== HANDLE UPLOAD BUKTI PEMBAYARAN ====================
$pesan = '';
$pesan_class = '';
$upload_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['bukti_pembayaran'];

        // Validasi tipe file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            $pesan = "❌ Format file tidak valid! Gunakan JPG, PNG, atau WEBP.";
            $pesan_class = 'error';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $pesan = "❌ Ukuran file terlalu besar! Maksimal 2MB.";
            $pesan_class = 'error';
        } else {
            // Upload file
            $target_dir = "uploads/bukti_pembayaran/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = "bukti_" . $batch_id . "_" . time() . "." . $ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Update semua transaksi dalam batch dengan bukti pembayaran
                try {
                    $stmt_update = $pdo->prepare("
    UPDATE transaksi 
    SET bukti_pembayaran = ?, status = 'pending', shipping_status = 'diproses'
    WHERE checkout_batch_id = ? AND user_id = ?
");
                    $stmt_update->execute([$target_file, $batch_id, $user_id]);

                    $pesan = "✅ Bukti pembayaran berhasil diupload! Pesanan Anda sedang menunggu konfirmasi penjual.";
                    $pesan_class = 'success';
                    $upload_success = true;
                } catch (PDOException $e) {
                    $pesan = "❌ Gagal menyimpan bukti: " . $e->getMessage();
                    $pesan_class = 'error';
                }
            } else {
                $pesan = "❌ Gagal mengupload file.";
                $pesan_class = 'error';
            }
        }
    } else {
        $pesan = "❌ Pilih file bukti pembayaran terlebih dahulu.";
        $pesan_class = 'error';
    }
}

// ==================== CEK STATUS UPLOAD ====================
// Cek apakah sudah ada bukti pembayaran
$stmt_check = $pdo->prepare("SELECT bukti_pembayaran, status FROM transaksi WHERE checkout_batch_id = ? LIMIT 1");
$stmt_check->execute([$batch_id]);
$check_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
$sudah_upload = !empty($check_data['bukti_pembayaran']);
$bukti_path = $check_data['bukti_pembayaran'] ?? '';

// Generate display ID
$display_id = substr(preg_replace('/[^0-9]/', '', $batch_id), -6);
if (strlen($display_id) < 6) {
    $display_id = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - FoodSave</title>
    <?php include 'includes/tailwind_config.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen py-8 px-4">
    <div class="max-w-2xl mx-auto">

        <!-- Header Success -->
        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100 mb-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-500 text-white rounded-full flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg">
                    ✓
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Pesanan Berhasil Dibuat!</h1>
                <p class="text-sm text-gray-500">ID Pesanan: <strong class="text-gray-800 font-mono">#<?= htmlspecialchars($display_id) ?></strong></p>
            </div>

            <!-- Alert Message -->
            <?php if ($pesan): ?>
                <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
                    <?= htmlspecialchars($pesan) ?>
                </div>
            <?php endif; ?>

            <!-- Info Pembayaran -->
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    💳 Total Pembayaran
                </h2>
                <div class="text-center mb-4">
                    <p class="text-sm text-gray-600 mb-1">Total yang harus dibayar:</p>
                    <p class="text-4xl font-black text-green-600">Rp <?= number_format($total_bayar, 0, ',', '.') ?></p>
                </div>
                <div class="border-t border-dashed border-green-200 pt-4">
                    <p class="text-sm text-gray-600 text-center">
                        Metode pembayaran: <strong class="text-gray-900"><?= htmlspecialchars($pembayaran) ?></strong>
                    </p>
                </div>
            </div>

            <!-- Info Rekening/QRIS Penjual -->
            <?php if (!empty($payment_methods)): ?>
                <div class="bg-white border-2 border-gray-200 rounded-2xl p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        🏦 Transfer ke Rekening Penjual
                    </h2>

                    <!-- Bank Transfer -->
                    <?php if (!empty($bank_accounts)): ?>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Transfer Bank</h3>
                            <div class="space-y-3">
                                <?php foreach ($bank_accounts as $bank): ?>
                                    <div class="border-2 <?= $bank['is_default'] ? 'border-green-500 bg-green-50/30' : 'border-gray-200' ?> rounded-xl p-4">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-xl">🏦</span>
                                            <span class="font-bold text-gray-900"><?= htmlspecialchars($bank['bank_name']) ?></span>
                                            <?php if ($bank['is_default']): ?>
                                                <span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">⭐ Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-8 space-y-1 text-sm">
                                            <p class="text-gray-700">
                                                <span class="text-gray-500">No. Rekening:</span>
                                                <strong class="font-mono text-base"><?= htmlspecialchars($bank['account_number']) ?></strong>
                                            </p>
                                            <p class="text-gray-700">
                                                <span class="text-gray-500">Atas Nama:</span>
                                                <?= htmlspecialchars($bank['account_holder']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- QRIS -->
                    <?php if (!empty($qris_list)): ?>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Scan QRIS</h3>
                            <?php foreach ($qris_list as $qris): ?>
                                <div class="border-2 <?= $qris['is_default'] ? 'border-purple-500 bg-purple-50/30' : 'border-gray-200' ?> rounded-xl p-4 text-center">
                                    <div class="flex items-center justify-center gap-2 mb-3">
                                        <span class="text-xl">📱</span>
                                        <span class="font-bold text-gray-900">QRIS Code</span>
                                    </div>
                                    <?php if (!empty($qris['qris_image'])): ?>
                                        <img src="<?= htmlspecialchars($qris['qris_image']) ?>"
                                            alt="QRIS"
                                            class="w-48 h-48 object-cover mx-auto rounded-lg border border-gray-200 shadow-sm">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Instruksi -->
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800">
                            <strong>📋 Instruksi:</strong><br>
                            1. Transfer sesuai total pembayaran<br>
                            2. Gunakan ID pesanan <strong>#<?= htmlspecialchars($display_id) ?></strong> sebagai keterangan<br>
                            3. Screenshot bukti transfer<br>
                            4. Upload bukti di bawah ini
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-6 mb-6">
                    <p class="text-sm text-amber-800">
                        ⚠️ Penjual belum menambahkan metode pembayaran. Silakan hubungi penjual untuk konfirmasi pembayaran.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Upload Bukti Pembayaran -->
            <?php if (!$sudah_upload): ?>
                <div class="bg-white border-2 border-gray-200 rounded-2xl p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        📤 Upload Bukti Pembayaran
                    </h2>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Pilih File Bukti Transfer *
                            </label>
                            <input type="file"
                                name="bukti_pembayaran"
                                accept="image/*"
                                required
                                onchange="previewImage(this)"
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 cursor-pointer">
                            <p class="text-xs text-gray-500 mt-2">
                                Format: JPG, PNG, atau WEBP. Maksimal 2MB.
                            </p>
                        </div>

                        <!-- Preview Image -->
                        <div id="imagePreview" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Preview:</label>
                            <div class="border-2 border-gray-200 rounded-xl p-4 bg-gray-50">
                                <img id="previewImg" src="" alt="Preview" class="max-w-full h-auto mx-auto rounded-lg shadow-sm">
                            </div>
                        </div>

                        <button type="submit"
                            name="upload_bukti"
                            class="w-full py-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2 cursor-pointer">
                            <span>📤</span>
                            <span>Upload Bukti Pembayaran</span>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Bukti Sudah Diupload -->
                <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 mb-6">
                    <h2 class="text-lg font-bold text-green-700 mb-4 flex items-center gap-2">
                        ✅ Bukti Pembayaran Sudah Diupload
                    </h2>
                    <div class="bg-white rounded-xl p-4 border border-green-200">
                        <img src="<?= htmlspecialchars($bukti_path) ?>"
                            alt="Bukti Pembayaran"
                            class="max-w-full h-auto mx-auto rounded-lg shadow-sm">
                    </div>
                    <p class="text-sm text-green-700 mt-4 text-center">
                        Pesanan Anda sedang menunggu konfirmasi dari penjual.
                    </p>
                </div>
            <?php endif; ?>

            <!-- List Produk yang Dipesan -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">📦 Detail Pesanan</h2>
                <div class="space-y-3">
                    <?php foreach ($transaksi_list as $trx): ?>
                        <div class="flex gap-3 p-3 bg-gray-50 rounded-lg">
                            <?php if (!empty($trx['gambar_url'])): ?>
                                <img src="<?= htmlspecialchars($trx['gambar_url']) ?>"
                                    alt="<?= htmlspecialchars($trx['nama_produk']) ?>"
                                    class="w-16 h-16 rounded-lg object-cover">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center text-2xl">📦</div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($trx['nama_produk']) ?></h3>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($trx['nama_toko']) ?></p>
                                <p class="text-xs text-gray-400">Qty: <?= $trx['jumlah'] ?> <?= htmlspecialchars($trx['satuan'] ?? '') ?></p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-green-600 text-sm">Rp <?= number_format($trx['total_harga'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="riwayat_pembelian.php" class="block w-full py-3 border-2 border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-center transition">
                    📋 Lihat Riwayat Pesanan
                </a>

                <a href="Index.php" class="block text-center text-sm text-green-600 hover:text-green-700 font-semibold hover:underline">
                    ← Kembali ke Beranda
                </a>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="text-center">
            <p class="text-xs text-gray-400">
                🚚 Pesanan akan diproses setelah penjual mengkonfirmasi pembayaran. Terima kasih telah menyelamatkan makanan surplus!
            </p>
        </div>
    </div>

    <!-- Script Preview Image -->
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    const img = document.getElementById('previewImg');

                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>