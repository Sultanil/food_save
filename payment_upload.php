<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// 🔐 SECURITY: Harus login
if (!isset($_SESSION['sudah_login']) || $_SESSION['sudah_login'] !== true) {
    header("Location: LoginPage.php?msg=login_required");
    exit;
}

// Format Rupiah helper
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Ambil data dari URL
$batch_id = $_GET['batch_id'] ?? '';
$total = (float)($_GET['total'] ?? 0);
$pembayaran = $_GET['pembayaran'] ?? 'Transfer Bank';

if (empty($batch_id) || $total <= 0) {
    header("Location: PromosiPage.php");
    exit;
}

// Ambil detail transaksi
$stmt = $conn->prepare("
    SELECT t.*, p.nama_produk, p.gambar_url, pj.nama_toko
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    JOIN penjual pj ON t.penjual_id = pj.id
    WHERE t.checkout_batch_id = ? AND t.user_id = ?
");
$stmt->bind_param("si", $batch_id, $_SESSION['user_id']);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();

if (!$transaksi) {
    die("Transaksi tidak ditemukan.");
}

$pesan = '';
$pesan_class = '';

// Handle upload bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['bukti_pembayaran'];
        
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $pesan = '❌ Format file tidak valid! Gunakan JPG, PNG, atau WEBP.';
            $pesan_class = 'error';
        } elseif ($file['size'] > $max_size) {
            $pesan = '❌ Ukuran file terlalu besar! Maksimal 5MB.';
            $pesan_class = 'error';
        } else {
            // Generate nama file unik
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nama_file = 'bukti_' . $batch_id . '_' . time() . '.' . $ext;
            $target_path = 'uploads/bukti/' . $nama_file;
            
            // Upload file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Update database
                $stmt_update = $conn->prepare("
                    UPDATE transaksi 
                    SET bukti_pembayaran = ?, status = 'pending', status = 'pending'
                    WHERE checkout_batch_id = ?
                ");
                $stmt_update->bind_param("ss", $nama_file, $batch_id);
                
                if ($stmt_update->execute()) {
                    $pesan = '✅ Bukti pembayaran berhasil diupload! Tim kami akan memverifikasi dalam 1x24 jam.';
                    $pesan_class = 'success';
                    
                    // Refresh data transaksi
                    $stmt->execute();
                    $transaksi = $stmt->get_result()->fetch_assoc();
                } else {
                    $pesan = '❌ Gagal menyimpan data: ' . $conn->error;
                    $pesan_class = 'error';
                }
            } else {
                $pesan = '❌ Gagal mengupload file. Silakan coba lagi.';
                $pesan_class = 'error';
            }
        }
    } else {
        $pesan = '❌ Mohon pilih file bukti pembayaran.';
        $pesan_class = 'error';
    }
}

// Data rekening (dummy - bisa diambil dari tabel settings)
$rekening = [
    'BCA' => ['no' => '1234567890', 'nama' => 'PT FoodSave Indonesia'],
    'Mandiri' => ['no' => '0987654321', 'nama' => 'PT FoodSave Indonesia'],
    'BRI' => ['no' => '1122334455', 'nama' => 'PT FoodSave Indonesia'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - FoodSave</title>
    <?php include 'includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        .input-focus { @apply focus:ring-2 focus:ring-brand focus:border-brand outline-none transition; }
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
                <a href="RiwayatPesanan.php" class="text-sm text-gray-600 hover:text-brand font-medium">Lihat Pesanan →</a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="max-w-4xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="text-6xl mb-3">💳</div>
            <h1 class="text-3xl font-bold text-gray-900">Pembayaran</h1>
            <p class="text-gray-500 mt-2">Selesaikan pembayaran pesananmu</p>
        </div>

        <!-- Alert Message -->
        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success' 
                ? 'bg-green-50 border-green-200 text-green-700' 
                : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= $pesan ?>
            </div>
        <?php endif; ?>

        <!-- Jika sudah upload bukti -->
        <?php if ($transaksi['status'] === 'menunggu_verifikasi'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="text-7xl mb-4">⏳</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Pembayaran Sedang Diverifikasi</h2>
                <p class="text-gray-600 mb-6">Terima kasih! Bukti pembayaranmu sudah kami terima dan akan diverifikasi dalam 1x24 jam.</p>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-6 inline-block">
                    <p class="text-sm text-gray-600 mb-1">Nomor Pesanan</p>
                    <p class="font-mono font-bold text-brand"><?= htmlspecialchars($batch_id) ?></p>
                </div>
                
                <div class="flex gap-3 justify-center">
                    <a href="RiwayatPesanan.php" class="px-6 py-3 bg-brand text-white rounded-lg hover:bg-brand-dark font-medium transition">
                        Lihat Riwayat Pesanan
                    </a>
                    <a href="PromosiPage.php" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition">
                        Kembali Belanja
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Form Upload Bukti -->
            <div class="grid lg:grid-cols-2 gap-6">
                
                <!-- Info Pembayaran -->
                <div class="space-y-6">
                    
                    <!-- Detail Pesanan -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">📦</span>
                            Detail Pesanan
                        </h2>
                        
                        <div class="space-y-3 text-sm">
                            <div class="flex gap-3 pb-3 border-b border-dashed">
                                <?php if ($transaksi['gambar_url']): ?>
                                    <img src="<?= htmlspecialchars($transaksi['gambar_url']) ?>" class="w-16 h-16 rounded-lg object-cover">
                                <?php else: ?>
                                    <div class="w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center text-2xl">📷</div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($transaksi['nama_produk']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($transaksi['nama_toko']) ?></p>
                                    <p class="text-xs text-gray-500">Jumlah: <?= $transaksi['jumlah'] ?></p>
                                </div>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium"><?= formatRupiah($transaksi['total_harga'] - $transaksi['ongkir'] - 2000 + $transaksi['diskon']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Biaya Layanan</span>
                                <span class="font-medium"><?= formatRupiah(2000) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Ongkir</span>
                                <span class="font-medium"><?= $transaksi['ongkir'] == 0 ? 'Gratis' : formatRupiah($transaksi['ongkir']) ?></span>
                            </div>
                            <?php if ($transaksi['diskon'] > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Diskon</span>
                                <span>- <?= formatRupiah($transaksi['diskon']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex justify-between text-xl font-bold text-gray-900 pt-4 mt-4 border-t-2 border-brand">
                            <span>Total Bayar</span>
                            <span class="text-brand"><?= formatRupiah($total) ?></span>
                        </div>
                    </div>

                    <!-- Info Pembayaran -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">
                                <?= $pembayaran === 'QRIS' ? '📱' : '🏦' ?>
                            </span>
                            Instruksi Pembayaran
                        </h2>
                        
                        <?php if ($pembayaran === 'Transfer Bank'): ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                <p class="text-sm text-blue-800 font-medium mb-2">Transfer ke salah satu rekening berikut:</p>
                                <?php foreach ($rekening as $bank => $data): ?>
                                    <div class="flex justify-between items-center py-2 border-b border-blue-100 last:border-0">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?= $bank ?></p>
                                            <p class="text-xs text-gray-600"><?= $data['nama'] ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-mono font-bold text-brand"><?= $data['no'] ?></p>
                                            <button onclick="copyToClipboard('<?= $data['no'] ?>')" 
                                                    class="text-xs text-blue-600 hover:underline">📋 Salin</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <p class="text-sm text-yellow-800">
                                    <strong>⚠️ Penting:</strong><br>
                                    • Transfer sesuai nominal: <strong><?= formatRupiah($total) ?></strong><br>
                                    • Gunakan kode unik untuk memudahkan verifikasi
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- QRIS -->
                            <div class="text-center">
                                <div class="bg-gray-100 rounded-xl p-6 mb-4 inline-block">
                                    <div class="w-48 h-48 bg-white border-2 border-gray-300 rounded-lg flex items-center justify-center text-6xl">
                                        📱
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">QR Code akan muncul di sini</p>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">Scan QR code di atas menggunakan aplikasi:</p>
                                <div class="flex justify-center gap-2 flex-wrap">
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded">GoPay</span>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded">OVO</span>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded">Dana</span>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded">ShopeePay</span>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded">Mobile Banking</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upload Bukti -->
                <div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-24">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">📤</span>
                            Upload Bukti Pembayaran
                        </h2>
                        
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Bukti Transfer / Screenshot QRIS *
                                </label>
                                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-brand transition cursor-pointer"
                                     onclick="document.getElementById('fileInput').click()">
                                    <div class="text-4xl mb-2">📸</div>
                                    <p class="text-sm text-gray-600 mb-1">Klik untuk upload atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, WEBP (Maks 5MB)</p>
                                    <input type="file" name="bukti_pembayaran" id="fileInput" accept="image/*" required
                                           class="hidden" onchange="previewFile(this)">
                                </div>
                                
                                <!-- Preview -->
                                <div id="preview" class="mt-4 hidden">
                                    <img id="previewImg" src="" alt="Preview" class="w-full rounded-lg border border-gray-200">
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-600">
                                    <strong>💡 Tips:</strong><br>
                                    • Pastikan bukti terlihat jelas<br>
                                    • Nominal transfer harus sesuai<br>
                                    • Tanggal & waktu harus terlihat
                                </p>
                            </div>
                            
                            <button type="submit" name="upload_bukti"
                                    class="w-full py-4 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl transition shadow-lg hover:shadow-xl text-lg cursor-pointer">
                                Konfirmasi Pembayaran
                            </button>
                            
                            <p class="text-center text-xs text-gray-500">
                                🔒 Data kamu aman dan terenkripsi
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER -->
    <footer class="py-6 px-4 bg-gray-900 text-gray-400 text-center mt-12">
        <p>© <?= date('Y') ?> FoodSave. All rights reserved.</p>
    </footer>

    <!-- JAVASCRIPT -->
    <script>
        function previewFile(input) {
            const preview = document.getElementById('preview');
            const img = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Nomor rekening berhasil disalin!');
            });
        }
    </script>

</body>
</html>