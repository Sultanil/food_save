<?php
session_start(); // Wajib untuk session login
include 'koneksi.php'; // Koneksi database jika diperlukan

$cart_items = [];
$subtotal = 0;
$site_name   = "Food Save";
$tagline     = "Reduce Waste. Feed More. Sustain Better.";
$description = "Dengan teknologi dan ekonomi sirkular, sisa makanan dimanfaatkan kembali menjadi nilai ekonomi yang berkelanjutan.";

$stats = [
    "67 ton makanan sudah diselamatkan",
    "1000+ pengguna aktif",
    "500+ mitra",
];

$langkah = [
    "Temukan Produk",
    "Pilih Produk",
    "Bayar",
];

// Cek login state
$is_logged_in = isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true;
$username = $is_logged_in ? htmlspecialchars($_SESSION['nama'] ?? 'Pengguna') : '';

if ($is_logged_in && $_SESSION['role'] === 'pembeli') {
    $user_id = $_SESSION['user_id'];

    // Handle POST (update qty / hapus)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produk_id'])) {
        if (isset($_POST['update_qty'])) {
            $prod_id = (int)$_POST['produk_id'];
            $qty = max(1, (int)$_POST['qty']);
            $stmt = $conn->prepare("UPDATE keranjang SET qty = ? WHERE user_id = ? AND produk_id = ?");
            $stmt->bind_param("iii", $qty, $user_id, $prod_id);
            $stmt->execute();
        } elseif (isset($_POST['hapus_item'])) {
            $prod_id = (int)$_POST['produk_id'];
            $stmt = $conn->prepare("DELETE FROM keranjang WHERE user_id = ? AND produk_id = ?");
            $stmt->bind_param("ii", $user_id, $prod_id);
            $stmt->execute();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Ambil data keranjang
    $stmt = $conn->prepare("
        SELECT k.produk_id, k.qty, p.nama_produk, p.harga_asli, p.harga_diskon, p.satuan, p.gambar_url
        FROM keranjang k
        JOIN produk p ON k.produk_id = p.id
        WHERE k.user_id = ? AND p.status = 'aktif'
        ORDER BY k.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();

    while ($row = $cart_result->fetch_assoc()) {
        $harga = !empty($row['harga_diskon']) ? $row['harga_diskon'] : $row['harga_asli'];
        $row['harga_satuan'] = $harga;
        $row['subtotal'] = $harga * $row['qty'];
        $subtotal += $row['subtotal'];
        $cart_items[] = $row;
    }
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @theme {
            --color-brand: #22c55e;
            --color-brand-dark: #16a34a;
            --font-primary: "Poppins", sans-serif;
        }
        body { font-family: var(--font-primary); }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="index.php" class="font-bold text-xl text-brand"> 🌿 <?= htmlspecialchars($site_name) ?></a>

            <div class="flex items-center gap-3">
                <?php if ($is_logged_in): ?>
                    <!-- Dropdown Profil -->
                    <div class="relative group">
                        <button class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition">
                            <div class="w-8 h-8 rounded-full bg-brand/10 flex items-center justify-center text-brand font-bold text-sm">
                                <?= strtoupper(substr($username, 0, 1)) ?>
                            </div>
                            <span class="text-sm font-medium hidden sm:block"><?= $username ?></span>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <!-- Menu Dropdown -->
                        <div class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border py-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition z-50">
                            <a href="profil.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">👤 Profil</a>
                            <a href="pesanan.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">📦 Pesanan</a>
                            <form method="POST" action="logout.php">
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="RegisterPage.php" class="px-4 py-2 text-sm font-semibold text-brand bg-white border-2 border-brand rounded-lg hover:bg-green-50 transition">
                        Daftar
                    </a>
                    <a href="LoginPage.php" class="px-4 py-2 text-sm font-semibold text-white bg-brand rounded-lg hover:bg-brand-dark transition">
                        Masuk
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="py-16 px-4 text-center bg-gradient-to-b from-green-50 to-white flex-grow">
        <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($tagline) ?></h1>
        <p class="text-gray-600 max-w-2xl mx-auto mb-8"><?= htmlspecialchars($description) ?></p>


        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <?php if ($is_logged_in): ?>
                <a href="PromosiPage.php" class="px-6 py-3 font-semibold text-white bg-brand rounded-lg hover:bg-brand-dark transition shadow">
                    Lihat Produk
                </a>
            <?php else: ?>
                <a href="LoginPage.php?redirect=PromosiPage.php" class="px-6 py-3 font-semibold text-white bg-brand rounded-lg hover:bg-brand-dark transition shadow">
                    Lihat Produk
                </a>
                <a href="LoginPage.php?redirect=RegisterPage.php" class="px-6 py-3 font-semibold text-brand bg-white border-2 border-brand rounded-lg hover:bg-green-50 transition">
                    Beli
                </a>
            <?php endif; ?>
        </div>

    </section>
    <?php if ($is_logged_in && $_SESSION['role'] === 'pembeli'): ?>
        <section class="px-4 pb-8">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        🛒 Keranjang Belanja
                    </h2>

                    <?php if (empty($cart_items)): ?>
                        <div class="text-center py-10 text-gray-500">
                            <p class="text-lg">Keranjang masih kosong</p>
                            <a href="PromosiPage.php" class="inline-block mt-4 px-5 py-2 bg-brand text-white rounded-lg hover:bg-brand-dark transition">
                                Mulai Belanja →
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="space-y-3">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 p-4 border rounded-lg bg-gray-50">
                                        <img src="<?= htmlspecialchars($item['gambar_url'] ?: 'https://via.placeholder.com/80') ?>"
                                            class="w-20 h-20 rounded-lg object-cover bg-white shadow-sm" alt="produk">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($item['nama_produk']) ?></h4>
                                            <p class="text-sm text-gray-500 mt-1">
                                                Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?> / <?= htmlspecialchars($item['satuan']) ?>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-3 w-full sm:w-auto">
                                            <input type="hidden" name="produk_id" value="<?= $item['produk_id'] ?>">
                                            <input type="number" name="qty" value="<?= $item['qty'] ?>" min="1" max="99"
                                                class="w-14 text-center text-sm border rounded py-2"
                                                onchange="this.form.submit()">
                                            <button type="submit" name="hapus_item"
                                                class="text-red-500 hover:text-red-700 text-sm font-medium px-3 py-2 rounded-lg hover:bg-red-50 transition">
                                                🗑️ Hapus
                                            </button>
                                        </div>
                                        <div class="text-right min-w-[90px]">
                                            <p class="font-bold text-brand text-lg">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-6 pt-5 border-t flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="text-lg">
                                    <span class="text-gray-600">Total Belanja:</span>
                                    <span class="font-bold text-gray-900 ml-2">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                                </div>
                                <a href="checkout.php"
                                    class="px-8 py-3 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl shadow transition">
                                    Checkout Sekarang →
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- KATEGORI / GAMBAR PRODUK -->
    <section class="py-12 px-4 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Produk Pilihan</h3>
                <a href="PromosiPage.php" class="text-brand font-medium hover:underline">Lihat Semua →</a>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <?php
                $images = [
                    "https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=400&auto=format&fit=crop",
                    "https://images.unsplash.com/photo-1569127959161-2b1297b2d9a6?w=400&auto=format&fit=crop",
                    "https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=400&auto=format&fit=crop"
                ];

                foreach ($images as $img): ?>
                    <a href="PromosiPage.php" class="group block aspect-square rounded-2xl overflow-hidden bg-gray-100 hover:ring-4 hover:ring-brand/30 transition-all duration-300">
                        <img src="<?= $img ?>"
                            alt="Produk surplus"
                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CARA KERJA -->
    <section class="py-12 px-4 bg-gray-50">
        <div class="max-w-7xl mx-auto text-center">
            <h2 class="text-2xl font-bold mb-2">Mudah Hanya 3 Langkah</h2>
            <p class="text-gray-600 mb-8">Food Save dirancang agar ramah untuk semua kalangan</p>
            <ol class="grid md:grid-cols-3 gap-6">
                <?php foreach ($langkah as $index => $item): ?>
                    <li class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="w-10 h-10 mx-auto mb-3 bg-brand text-white rounded-full flex items-center justify-center font-bold"><?= $index + 1 ?></div>
                        <h4 class="font-semibold mb-2"><?= htmlspecialchars($item) ?></h4>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- STATISTIK -->
    <section class="py-10 px-4 bg-brand text-white">
        <div class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            <?php foreach ($stats as $stat): ?>
                <h4 class="text-lg font-bold"><?= htmlspecialchars($stat) ?></h4>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-6 px-4 bg-gray-900 text-gray-400 text-center mt-auto">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.</p>
    </footer>

</body>

</html>