<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/ulasan_functions.php';

if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pesan = '';
$pesan_class = '';

// Pesan dari checkout
if (isset($_SESSION['checkout_success']) && $_SESSION['checkout_success']) {
    $pesan = '✅ Pesanan berhasil dibuat! Silakan berikan ulasan untuk produk Anda.';
    $pesan_class = 'success';
    unset($_SESSION['checkout_success']);
}

// Handle POST (submit ulasan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaksi_id = filter_input(INPUT_POST, 'transaksi_id', FILTER_VALIDATE_INT);
    $produk_id = filter_input(INPUT_POST, 'produk_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $komentar = trim($_POST['komentar'] ?? '');
    
    if (!$transaksi_id || !$produk_id) {
        $pesan = 'Data transaksi tidak valid!';
        $pesan_class = 'error';
    } elseif (!$rating || $rating < 1 || $rating > 5) {
        $pesan = 'Rating harus antara 1-5!';
        $pesan_class = 'error';
    } elseif (empty($komentar) || strlen($komentar) < 10) {
        $pesan = 'Komentar minimal 10 karakter!';
        $pesan_class = 'error';
    } elseif (!bisaReview($pdo, $user_id, $produk_id, $transaksi_id)) {
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
            $_SESSION['ulasan_success'] = $result['message'];
            header("Location: beri_ulasan.php");
            exit;
        } else {
            $pesan = $result['message'];
            $pesan_class = 'error';
        }
    }
}

// Flash message
if (isset($_SESSION['ulasan_success'])) {
    $pesan = $_SESSION['ulasan_success'];
    $pesan_class = 'success';
    unset($_SESSION['ulasan_success']);
}

$transaksi_list = getTransaksiBisaReview($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Ulasan - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 2rem;
            color: #d1d5db;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #facc15;
        }
        .star-rating {
            direction: rtl;
            display: inline-flex;
            gap: 4px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="Index.php" class="text-xl font-bold text-green-600">🌿 FoodSave</a>
            <a href="riwayat_pembelian.php" class="text-sm text-gray-600 hover:text-green-600">← Kembali</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Beri Ulasan Pesanan</h1>
        <p class="text-gray-500 mb-6">Bagikan pengalaman Anda tentang produk yang sudah diterima</p>

        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($transaksi_list)): ?>
            <div class="bg-white rounded-xl shadow-sm p-8 text-center">
                <div class="text-6xl mb-4">✅</div>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Tidak Ada Pesanan yang Perlu Direview</h2>
                <p class="text-gray-500 mb-4">Semua pesanan sudah direview atau belum ada pesanan yang selesai.</p>
                <a href="Index.php" class="inline-block px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Belanja Sekarang</a>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <p class="text-yellow-800">
                    <strong>📝 Ada <?= count($transaksi_list) ?> produk</strong> yang perlu diberi ulasan
                </p>
            </div>

            <div class="space-y-6">
                <?php foreach ($transaksi_list as $transaksi): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex gap-4 mb-4">
                            <?php if (!empty($transaksi['gambar_url'])): ?>
                                <img src="<?= htmlspecialchars($transaksi['gambar_url']) ?>" 
                                     alt="<?= htmlspecialchars($transaksi['nama_produk']) ?>"
                                     class="w-20 h-20 object-cover rounded-lg border">
                            <?php else: ?>
                                <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center text-3xl">🍽️</div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <h3 class="font-semibold text-lg text-gray-900"><?= htmlspecialchars($transaksi['nama_produk']) ?></h3>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($transaksi['nama_toko']) ?></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Tanggal: <?= date('d M Y', strtotime($transaksi['tanggal_pesanan'])) ?>
                                    • <?= (int)$transaksi['jumlah'] ?> item
                                </p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="transaksi_id" value="<?= $transaksi['transaksi_id'] ?>">
                            <input type="hidden" name="produk_id" value="<?= $transaksi['produk_id'] ?>">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rating *</label>
                                <div class="star-rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?= $i ?>-<?= $transaksi['produk_id'] ?>" 
                                               name="rating" value="<?= $i ?>" required>
                                        <label for="star<?= $i ?>-<?= $transaksi['produk_id'] ?>" title="<?= $i ?> bintang">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Komentar *</label>
                                <textarea name="komentar" rows="3" required minlength="10"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none resize-none"
                                    placeholder="Bagaimana kualitas produk ini? (min. 10 karakter)"></textarea>
                            </div>

                            <button type="submit" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700">
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