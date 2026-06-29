<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/ulasan_functions.php';

// Cek login
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pesan = '';
$pesan_class = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaksi_id = (int)$_POST['transaksi_id'];
    $produk_id = (int)$_POST['produk_id'];
    $rating = (int)$_POST['rating'];
    $komentar = trim($_POST['komentar']);
    
    // Validasi
    if ($rating < 1 || $rating > 5) {
        $pesan = 'Rating harus antara 1-5!';
        $pesan_class = 'error';
    } elseif (empty($komentar)) {
        $pesan = 'Komentar tidak boleh kosong!';
        $pesan_class = 'error';
    } elseif (!bisaReview($pdo, $user_id, $produk_id)) {
        $pesan = 'Anda tidak berhak memberikan ulasan untuk produk ini!';
        $pesan_class = 'error';
    } else {
        $result = tambahUlasan($pdo, [
            'user_id' => $user_id,
            'produk_id' => $produk_id,
            'transaksi_id' => $transaksi_id,
            'rating' => $rating,
            'komentar' => $komentar
        ]);
        
        if ($result['success']) {
            $pesan = $result['message'];
            $pesan_class = 'success';
        } else {
            $pesan = $result['message'];
            $pesan_class = 'error';
        }
    }
}

// Ambil daftar transaksi yang bisa direview
$transaksi_list = getTransaksiBisaReview($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Ulasan - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <a href="Index.php" class="text-xl font-bold text-green-600">🌿 FoodSave</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Beri Ulasan Pesanan</h1>

        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($transaksi_list)): ?>
            <div class="bg-white rounded-xl shadow-sm p-8 text-center">
                <div class="text-6xl mb-4">✅</div>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Semua Pesanan Sudah Direview!</h2>
                <p class="text-gray-500">Terima kasih telah memberikan ulasan untuk semua pesanan Anda.</p>
                <a href="riwayat_pembelian.php" class="inline-block mt-4 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Lihat Riwayat Pesanan
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($transaksi_list as $transaksi): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex gap-4 mb-4">
                            <div class="flex-1">
                                <h3 class="font-semibold text-lg text-gray-900"><?= htmlspecialchars($transaksi['nama_produk']) ?></h3>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($transaksi['nama_toko']) ?></p>
                                <p class="text-xs text-gray-400 mt-1">Tanggal: <?= date('d M Y', strtotime($transaksi['tanggal_pesanan'])) ?></p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="transaksi_id" value="<?= $transaksi['id'] ?>">
                            <input type="hidden" name="produk_id" value="<?= $transaksi['produk_id'] ?>">

                            <!-- Rating Stars -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rating *</label>
                                <div class="flex gap-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="rating" value="<?= $i ?>" class="sr-only peer" required>
                                            <span class="text-3xl text-gray-300 peer-checked:text-yellow-400 hover:text-yellow-400 transition">★</span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Komentar -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Komentar/Ulasan *</label>
                                <textarea name="komentar" rows="3" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none resize-none"
                                    placeholder="Bagaimana kualitas produk ini? Ceritakan pengalaman Anda..."></textarea>
                            </div>

                            <button type="submit" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition">
                                Kirim Ulasan
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>