<?php
// setup_payment.php - Halaman setup payment methods (Versi Simpel & Jelas)
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/payment_methods.php';

// 🔐 Security check
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

// Ambil data penjual
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$stmt = $pdo->prepare("SELECT id, nama_toko, status_verifikasi FROM penjual WHERE user_id = ?");
$stmt->execute([$user_id]);
$penjual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$penjual) {
    header("Location: lengkapi_toko.php");
    exit;
}

$penjual_id = $penjual['id'];

// Fetch semua payment methods
$payment_methods = getSellerPaymentMethods($pdo, $penjual_id);
$bank_accounts = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'bank_transfer');
$qris_list = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'qris');
$has_bank_account = count($bank_accounts) > 0;

// Pesan notifikasi
$pesan = $_GET['msg'] ?? '';
$pesan_type = $_GET['type'] ?? 'success';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Payment Methods - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm border-b sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="text-2xl font-extrabold text-green-600">🌿 FoodSave</a>
            <div class="text-sm text-gray-600">Setup Pembayaran: <strong><?= htmlspecialchars($penjual['nama_toko']) ?></strong></div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">

        <!-- Progress Steps -->
        <div class="flex items-center justify-between mb-8 text-sm">
            <div class="flex items-center gap-2 text-green-600 font-bold"><span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center">✓</span> Data Toko</div>
            <div class="flex-1 h-1 bg-green-500 mx-2"></div>
            <div class="flex items-center gap-2 text-green-600 font-bold"><span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center">2</span> Payment Methods</div>
            <div class="flex-1 h-1 bg-gray-300 mx-2"></div>
            <div class="flex items-center gap-2 text-gray-400"><span class="w-6 h-6 bg-gray-300 text-white rounded-full flex items-center justify-center">3</span> Review</div>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-2">💳 Setup Metode Pembayaran</h1>
        <p class="text-gray-600 mb-6">Isi form di bawah untuk menambahkan rekening bank dan upload gambar QRIS toko Anda.</p>

        <!-- Notifikasi -->
        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <!-- WARNING -->
        <?php if (!$has_bank_account): ?>
            <div class="mb-6 p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-800 rounded-r-xl">
                ⚠️ <strong>Wajib:</strong> Anda harus menambahkan minimal 1 Rekening Bank sebelum bisa lanjut ke tahap Review.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- ==================== FORM TAMBAH REKENING BANK ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">🏦 Tambah Rekening Bank</h2>
                
                <form action="actions/save_payment_method.php" method="POST" class="space-y-4">
                    <input type="hidden" name="payment_type" value="bank_transfer">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bank *</label>
                        <select name="bank_name" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                            <option value="">-- Pilih Bank --</option>
                            <option value="BCA">BCA (Bank Central Asia)</option>
                            <option value="Mandiri">Bank Mandiri</option>
                            <option value="BRI">BRI (Bank Rakyat Indonesia)</option>
                            <option value="BNI">BNI (Bank Negara Indonesia)</option>
                            <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Rekening *</label>
                        <input type="text" name="account_number" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none font-mono" placeholder="Contoh: 1234567890">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Atas Nama Pemilik Rekening *</label>
                        <input type="text" name="account_holder" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none" placeholder="Nama sesuai buku tabungan">
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" name="is_default" value="1" id="bank_default" class="w-4 h-4 text-green-600 rounded">
                        <label for="bank_default" class="text-sm text-gray-700">Jadikan sebagai rekening utama (default)</label>
                    </div>

                    <button type="submit" class="w-full py-3 bg-green-500 hover:bg-green-600 text-white font-bold rounded-lg transition shadow cursor-pointer">
                        💾 Simpan Rekening Bank
                    </button>
                </form>
            </div>

            <!-- ==================== FORM UPLOAD QRIS ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">📱 Upload QRIS Code</h2>
                
                <form action="actions/save_payment_method.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="payment_type" value="qris">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Gambar QRIS *</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-green-500 transition bg-gray-50">
                            <input type="file" name="qris_image" accept="image/*" required class="hidden" id="qris_file" onchange="previewFile()">
                            <label for="qris_file" class="cursor-pointer block">
                                <div class="text-4xl mb-2">📷</div>
                                <span class="text-sm font-medium text-green-600 hover:underline">Klik untuk pilih gambar QRIS</span>
                                <p class="text-xs text-gray-400 mt-1">Format: JPG, PNG, WEBP (Maks 2MB)</p>
                            </label>
                            <img id="preview_img" class="hidden max-h-40 mx-auto mt-4 rounded-lg border border-gray-200" alt="Preview QRIS">
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" name="is_default" value="1" id="qris_default" class="w-4 h-4 text-green-600 rounded">
                        <label for="qris_default" class="text-sm text-gray-700">Jadikan sebagai metode utama (default)</label>
                    </div>

                    <button type="submit" class="w-full py-3 bg-purple-500 hover:bg-purple-600 text-white font-bold rounded-lg transition shadow cursor-pointer">
                         Upload QRIS
                    </button>
                </form>
            </div>

        </div>

        <!-- ==================== DAFTAR PAYMENT METHODS YANG SUDAH ADA ==================== -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">📋 Metode Pembayaran Aktif Anda</h2>
            
            <?php if (empty($payment_methods)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-gray-500 border border-gray-100">
                    <div class="text-4xl mb-2">📭</div>
                    <p>Belum ada metode pembayaran. Silakan isi form di atas.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($payment_methods as $pm): ?>
                        <div class="bg-white rounded-xl border-2 <?= $pm['is_default'] ? 'border-green-500 bg-green-50/30' : 'border-gray-200' ?> p-4 relative">
                            <?php if ($pm['is_default']): ?>
                                <span class="absolute top-2 right-2 bg-green-500 text-white text-[10px] font-bold px-2 py-1 rounded-full">⭐ DEFAULT</span>
                            <?php endif; ?>
                            
                            <?php if ($pm['payment_type'] === 'bank_transfer'): ?>
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-2xl"></span>
                                    <h3 class="font-bold text-gray-900"><?= htmlspecialchars($pm['bank_name']) ?></h3>
                                </div>
                                <p class="text-sm text-gray-600">No. Rek: <span class="font-mono font-bold"><?= htmlspecialchars($pm['account_number']) ?></span></p>
                                <p class="text-sm text-gray-600">A/N: <?= htmlspecialchars($pm['account_holder']) ?></p>
                            <?php else: ?>
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-2xl">📱</span>
                                    <h3 class="font-bold text-gray-900">QRIS Code</h3>
                                </div>
                                <?php if ($pm['qris_image']): ?>
                                    <img src="<?= htmlspecialchars($pm['qris_image']) ?>" class="w-32 h-32 object-cover rounded-lg border border-gray-200 mt-2">
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                                <?php if (!$pm['is_default']): ?>
                                    <a href="actions/set_default_payment.php?id=<?= $pm['id'] ?>&type=<?= $pm['payment_type'] ?>" class="flex-1 text-center py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">⭐ Jadikan Default</a>
                                <?php endif; ?>
                                <a href="actions/delete_payment_method.php?id=<?= $pm['id'] ?>" onclick="return confirm('Yakin hapus metode ini?')" class="flex-1 text-center py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold rounded-lg transition">️ Hapus</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- NAVIGATION -->
        <div class="mt-8 flex justify-between">
            <a href="lengkapi_toko.php" class="px-6 py-3 border-2 border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition">← Kembali</a>
            <a href="review_toko.php" class="px-6 py-3 <?= $has_bank_account ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed' ?> font-medium rounded-xl transition">Lanjut Review →</a>
        </div>

    </main>

    <script>
        // Script sederhana untuk preview gambar QRIS sebelum upload
        function previewFile() {
            const preview = document.getElementById('preview_img');
            const file = document.getElementById('qris_file').files[0];
            const reader = new FileReader();

            reader.addEventListener("load", function () {
                preview.src = reader.result;
                preview.classList.remove('hidden');
            }, false);

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>