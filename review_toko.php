<?php
// review_toko.php - Review data toko & payment methods sebelum submit verifikasi (Step 3 dari 3)
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
$stmt = $pdo->prepare("SELECT * FROM penjual WHERE user_id = ?");
$stmt->execute([$user_id]);
$penjual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$penjual) {
    header("Location: lengkapi_toko.php");
    exit;
}

// Jika toko sudah disetujui atau pending, tidak perlu review lagi
if (in_array($penjual['status_verifikasi'], ['disetujui', 'pending'])) {
    header("Location: dashboardPenjual.php");
    exit;
}

$penjual_id = $penjual['id'];

// Fetch semua payment methods
$payment_methods = getSellerPaymentMethods($pdo, $penjual_id);
$bank_accounts = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'bank_transfer');
$qris_list = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'qris');

// Validasi: minimal 1 bank account
$has_bank_account = count($bank_accounts) > 0;

// Pesan dari redirect
$pesan = $_GET['msg'] ?? '';
$pesan_type = $_GET['type'] ?? 'success';

// Handle POST submit verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verifikasi'])) {
    if (!$has_bank_account) {
        header("Location: review_toko.php?msg=error&type=error&error_msg=Minimal 1 rekening bank wajib ditambahkan!");
        exit;
    }

    try {
        // Update status verifikasi jadi pending
        $stmt = $pdo->prepare("UPDATE penjual SET status_verifikasi = 'pending', alasan_penolakan = NULL WHERE id = ?");
        $stmt->execute([$penjual_id]);

        // Hapus flag step
        unset($_SESSION['toko_step_1_done']);

        header("Location: dashboardPenjual.php?msg=verifikasi_submitted");
        exit;
    } catch (PDOException $e) {
        header("Location: review_toko.php?msg=error&type=error&error_msg=Terjadi kesalahan: " . urlencode($e->getMessage()));
        exit;
    }
}

$error_msg = $_GET['error_msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review & Submit Verifikasi - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm border-b sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="text-2xl font-extrabold text-green-600">🌿 FoodSave</a>
            <div class="text-sm text-gray-600">Review Data: <strong><?= htmlspecialchars($penjual['nama_toko']) ?></strong></div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">

        <!-- Progress Steps -->
        <div class="flex items-center justify-between mb-8 text-sm">
            <div class="flex items-center gap-2 text-green-600 font-bold">
                <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center">✓</span> Data Toko
            </div>
            <div class="flex-1 h-1 bg-green-500 mx-2"></div>
            <div class="flex items-center gap-2 text-green-600 font-bold">
                <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center">✓</span> Payment Methods
            </div>
            <div class="flex-1 h-1 bg-green-500 mx-2"></div>
            <div class="flex items-center gap-2 text-green-600 font-bold">
                <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center">3</span> Review
            </div>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-2">📋 Review Data Toko</h1>
        <p class="text-gray-600 mb-6">Periksa kembali data toko dan metode pembayaran Anda. Setelah disubmit, data akan ditinjau oleh admin dalam 1x24 jam.</p>

        <!-- Notifikasi -->
        <?php if ($pesan === 'error' && $error_msg): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-xl">
                ❌ <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!$has_bank_account): ?>
            <div class="mb-6 p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-800 rounded-r-xl">
                ⚠️ <strong>Peringatan:</strong> Anda belum menambahkan rekening bank. Silakan kembali ke Step 2 untuk menambahkan minimal 1 rekening bank.
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">

            <!-- ==================== DATA TOKO ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">🏪 Data Toko</h2>
                    <a href="lengkapi_toko.php" class="text-sm text-green-600 hover:underline font-medium">✏️ Edit</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Nama Toko</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($penjual['nama_toko']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Kota</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($penjual['kota']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Kode Pos</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($penjual['kode_pos']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Nomor Telepon</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($penjual['no_telp'] ?? '-') ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs text-gray-500 mb-1">Alamat Lengkap</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($penjual['alamat'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">NIK Penjual</p>
                        <p class="font-semibold text-gray-900 font-mono"><?= htmlspecialchars($penjual['nik']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Foto KTP</p>
                        <?php if (!empty($penjual['foto_ktp'])): ?>
                            <a href="<?= htmlspecialchars($penjual['foto_ktp']) ?>" target="_blank" class="inline-flex items-center gap-2 text-green-600 hover:underline font-medium text-sm">
                                🖼️ Lihat Foto KTP
                            </a>
                        <?php else: ?>
                            <p class="text-red-500 text-sm">Belum diupload</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ==================== PAYMENT METHODS ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">💳 Metode Pembayaran</h2>
                    <a href="setup_payment.php" class="text-sm text-green-600 hover:underline font-medium">✏️ Edit</a>
                </div>

                <?php if (empty($payment_methods)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>Belum ada metode pembayaran. <a href="setup_payment.php" class="text-green-600 hover:underline font-medium">Tambahkan di sini</a></p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($payment_methods as $pm): ?>
                            <div class="border-2 <?= $pm['is_default'] ? 'border-green-500 bg-green-50/30' : 'border-gray-200' ?> rounded-xl p-4 relative">
                                <?php if ($pm['is_default']): ?>
                                    <span class="absolute top-2 right-2 bg-green-500 text-white text-[10px] font-bold px-2 py-1 rounded-full">⭐ DEFAULT</span>
                                <?php endif; ?>
                                
                                <?php if ($pm['payment_type'] === 'bank_transfer'): ?>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-2xl">🏦</span>
                                        <h3 class="font-bold text-gray-900"><?= htmlspecialchars($pm['bank_name']) ?></h3>
                                    </div>
                                    <p class="text-sm text-gray-600">No. Rek: <span class="font-mono font-bold"><?= htmlspecialchars($pm['account_number']) ?></span></p>
                                    <p class="text-sm text-gray-600">A/N: <?= htmlspecialchars($pm['account_holder']) ?></p>
                                <?php else: ?>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-2xl">📱</span>
                                        <h3 class="font-bold text-gray-900">QRIS Code</h3>
                                    </div>
                                    <?php if (!empty($pm['qris_image'])): ?>
                                        <img src="<?= htmlspecialchars($pm['qris_image']) ?>" class="w-32 h-32 object-cover rounded-lg border border-gray-200 mt-2">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==================== PERSETUJUAN ==================== -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6">
                <div class="flex items-start gap-3">
                    <span class="text-2xl"></span>
                    <div>
                        <h3 class="font-bold text-blue-900 mb-2">Persetujuan & Konfirmasi</h3>
                        <p class="text-sm text-blue-800 mb-3">
                            Dengan mengklik tombol "Submit Verifikasi" di bawah, Anda menyatakan bahwa:
                        </p>
                        <ul class="text-sm text-blue-800 space-y-1 ml-4 list-disc">
                            <li>Semua data yang diisi adalah benar dan valid</li>
                            <li>Foto KTP yang diupload adalah milik Anda sendiri</li>
                            <li>Rekening bank yang didaftarkan atas nama Anda atau toko Anda</li>
                            <li>Anda bersedia mematuhi Syarat & Ketentuan FoodSave</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ==================== TOMBOL SUBMIT ==================== -->
            <div class="flex justify-between gap-4">
                <a href="setup_payment.php" class="px-6 py-3 border-2 border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition text-center">
                    ← Kembali ke Payment Methods
                </a>
                <button type="submit" name="submit_verifikasi" 
                        <?= !$has_bank_account ? 'disabled' : '' ?>
                        class="px-8 py-3 <?= $has_bank_account ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed' ?> font-bold rounded-xl transition shadow-lg text-center">
                    ✓ Submit Verifikasi Toko
                </button>
            </div>

        </form>

    </main>

    <!-- FOOTER -->
    <footer class="py-6 px-4 bg-gray-900 text-gray-400 text-center mt-12">
        <p>© <?= date('Y') ?> FoodSave. All rights reserved.</p>
    </footer>

</body>
</html>