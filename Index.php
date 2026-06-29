<?php
session_start();
require_once 'config/database.php';

// Ambil kategori produk
$categories = $pdo->query("
    SELECT DISTINCT kategori 
    FROM produk 
    WHERE status = 'aktif' AND stok > 0 
    LIMIT 6
")->fetchAll(PDO::FETCH_COLUMN);

// Ambil produk populer (dengan diskon)
$popular_products = $pdo->query("
    SELECT p.*, pj.nama_toko, pj.kota
    FROM produk p
    JOIN penjual pj ON p.penjual_id = pj.id
    WHERE p.status = 'aktif' AND p.stok > 0 AND p.harga_diskon IS NOT NULL
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil toko teratas
$top_stores = $pdo->query("
    SELECT pj.*, COUNT(p.id) as total_produk
    FROM penjual pj
    LEFT JOIN produk p ON pj.id = p.penjual_id AND p.status = 'aktif'
    WHERE pj.status_verifikasi = 'disetujui'
    GROUP BY pj.id
    ORDER BY total_produk DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodSave - Selamatkan Makanan, Hemat Lebih Banyak</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            /* Background body hijau lembut tapi tidak pucat */
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #1f2937;
        }

        /* Class khusus untuk Hero Section (Hijau Tua) */
        .hero-bg {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .text-primary {
            color: #059669;
        }

        .bg-primary {
            background-color: #059669;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(5, 150, 105, 0.15);
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white/95 backdrop-blur-md shadow-sm sticky top-0 z-50 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <a href="Index.php" class="flex items-center gap-2 group">
                    <div class="w-10 h-10 gradient-primary rounded-xl flex items-center justify-center text-white text-xl group-hover:scale-110 transition-transform">
                        🌿
                    </div>
                    <span class="text-2xl font-extrabold text-gray-900">Food<span class="text-primary">Save</span></span>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#beranda" class="text-gray-700 hover:text-primary font-medium transition">Beranda</a>
                    <a href="#cara-kerja" class="text-gray-700 hover:text-primary font-medium transition">Cara Kerja</a>
                    <a href="#tentang" class="text-gray-700 hover:text-primary font-medium transition">Tentang</a>
                    <a href="PromosiPage.php" class="text-gray-700 hover:text-primary font-medium transition">Jelajah</a>
                </div>

                <!-- Auth Buttons -->
                <div class="flex items-center gap-3">
                    <?php if (isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true): ?>

                        <?php if ($_SESSION['role'] === 'pembeli'): ?>
                            <a href="keranjang.php" class="relative p-2 text-gray-600 hover:text-primary transition">
                                <i class="fas fa-shopping-cart text-xl"></i>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">0</span>
                            </a>
                        <?php endif; ?>

                        <!-- ✅ DROPDOWN PROFIL -->
                        <div class="relative" id="profileDropdown">
                            <button onclick="toggleProfileDropdown()" class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-primary transition bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-xl border border-gray-200">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white text-xs font-bold overflow-hidden">
                                    <?php
                                    $nama_user = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'U';
                                    $initial = strtoupper(substr($nama_user, 0, 1));
                                    echo $initial;
                                    ?>
                                </div>
                                <span class="hidden sm:block max-w-[100px] truncate"><?= htmlspecialchars($nama_user) ?></span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="profileDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">

                                <!-- Menu Items -->
                                <a href="edit_profil.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                    <span class="text-lg">✏️</span>
                                    <span>Edit Profil</span>
                                </a>
                                <a href="alamat_saya.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                    <span class="text-lg">📍</span>
                                    <span>Alamat Saya</span>
                                </a>

                                <?php if ($_SESSION['role'] === 'pembeli'): ?>
                                    <a href="riwayat_pembelian.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                        <span class="text-lg">📦</span>
                                        <span>Riwayat Pembelian</span>
                                    </a>
                                <?php elseif ($_SESSION['role'] === 'penjual'): ?>
                                    <a href="dashboardPenjual.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                        <span class="text-lg">🏪</span>
                                        <span>Dashboard Toko</span>
                                    </a>
                                <?php endif; ?>

                                <div class="border-t border-gray-100 my-1"></div>

                                <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
                                    <span class="text-lg">🚪</span>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <a href="LoginPage.php" class="px-5 py-2.5 text-gray-700 font-semibold hover:text-primary transition">
                            Masuk
                        </a>
                        <a href="RegisterPage.php" class="px-5 py-2.5 text-gray-700 font-semibold hover:text-primary transition">
                            Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section id="beranda" class="hero-bg relative overflow-hidden text-white">
        <!-- Pattern Overlay -->
        <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 relative z-10">
            <div class="grid md:grid-cols-2 gap-12 items-center">

                <!-- Kiri: Teks -->
                <div class="space-y-6 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full text-sm font-medium border border-white/30">
                        <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                        Selamatkan Makanan, Selamatkan Bumi
                    </div>

                    <h1 class="text-4xl md:text-6xl font-extrabold leading-tight">
                        Makanan Berkualitas, <br>
                        <span class="text-yellow-300">Harga Hemat</span>
                    </h1>

                    <p class="text-lg md:text-xl text-green-50 max-w-lg mx-auto md:mx-0">
                        Temukan makanan surplus dari toko terdekat dengan diskon hingga 70%. Belanja bijak, ramah lingkungan.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 pt-4 justify-center md:justify-start">
                        <a href="PromosiPage.php" class="px-8 py-4 bg-white text-green-700 font-bold rounded-xl hover:bg-gray-100 transition shadow-xl text-center">
                            🛒 Mulai Belanja
                        </a>
                        <a href="#cara-kerja" class="px-8 py-4 bg-green-800/50 backdrop-blur-sm text-white font-bold rounded-xl hover:bg-green-800/70 transition text-center border border-white/30">
                            📖 Cara Kerja
                        </a>
                    </div>
                </div>

                <!-- Kanan: Ilustrasi Stats (Pengganti Mockup Produk) -->
                <div class="hidden md:grid grid-cols-2 gap-4">
                    <!-- Card 1 -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20 text-center transform hover:scale-105 transition duration-300">
                        <div class="text-4xl mb-2">🍽️</div>
                        <div class="text-3xl font-bold text-white mb-1">5,000+</div>
                        <div class="text-green-100 text-sm">Makanan Terselamatkan</div>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20 text-center transform hover:scale-105 transition duration-300 mt-8">
                        <div class="text-4xl mb-2">🏪</div>
                        <div class="text-3xl font-bold text-white mb-1">200+</div>
                        <div class="text-green-100 text-sm">Mitra Toko</div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20 text-center transform hover:scale-105 transition duration-300">
                        <div class="text-4xl mb-2">😊</div>
                        <div class="text-3xl font-bold text-white mb-1">3,000+</div>
                        <div class="text-green-100 text-sm">Pengguna Puas</div>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20 text-center transform hover:scale-105 transition duration-300 mt-8">
                        <div class="text-4xl mb-2">🌍</div>
                        <div class="text-3xl font-bold text-white mb-1">2,500 kg</div>
                        <div class="text-green-100 text-sm">CO₂ Berkurang</div>
                    </div>
                </div>

            </div>
        </div>
    </section>
    <!-- CATEGORIES SECTION -->
    <section class="py-16 bg-gradient-to-b from-white to-green-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Kategori Makanan</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Temukan berbagai kategori makanan surplus yang bisa kamu selamatkan dengan harga terjangkau</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php
                // Define semua kategori dengan emoji yang sesuai
                $all_categories = [
                    'Makanan Berat' => '🍛',
                    'Minuman' => '🥤',
                    'Kue & Roti' => '🧁',
                    'Snack' => '🍿',
                    'Buah & Sayur' => '🥗',
                    'Lainnya' => '🔍'
                ];

                foreach ($all_categories as $category => $emoji):
                ?>
                    <a href="PromosiPage.php?kategori=<?= urlencode($category) ?>"
                        class="category-card bg-white hover:bg-gradient-to-br hover:from-green-500 hover:to-emerald-600 hover:text-white rounded-2xl p-6 text-center group cursor-pointer shadow-md hover:shadow-xl transition-all duration-300 border border-gray-100">
                        <div class="text-5xl mb-3 group-hover:scale-110 transition-transform inline-block">
                            <?= $emoji ?>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-white text-sm">
                            <?= htmlspecialchars($category) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- POPULAR PRODUCTS SECTION -->
    <section class="py-16 bg-gradient-to-b from-green-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">Promo Hari Ini 🔥</h2>
                    <p class="text-gray-600">Makanan dengan diskon terbaik yang tidak boleh kamu lewatkan</p>
                </div>
                <a href="PromosiPage.php" class="hidden md:inline-flex items-center gap-2 text-primary font-semibold hover:text-primary-dark transition">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($popular_products as $product):
                    $discount = round((1 - $product['harga_diskon'] / $product['harga_asli']) * 100);
                ?>
                    <div class="card-hover bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 group cursor-pointer" onclick="window.location.href='HalamanTransaksi.php?produk_id=<?= $product['id'] ?>'">
                        <!-- Image -->
                        <div class="relative h-48 overflow-hidden">
                            <?php if (!empty($product['gambar_url'])): ?>
                                <img src="<?= htmlspecialchars($product['gambar_url']) ?>"
                                    alt="<?= htmlspecialchars($product['nama_produk']) ?>"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-6xl">
                                    🍽️
                                </div>
                            <?php endif; ?>

                            <!-- Discount Badge -->
                            <div class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-3 py-1.5 rounded-full">
                                Diskon <?= $discount ?>%
                            </div>

                            <!-- Wishlist Button -->
                            <button class="absolute top-3 right-3 w-8 h-8 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 transition shadow-lg">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <div class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                <i class="fas fa-store text-primary"></i>
                                <?= htmlspecialchars($product['nama_toko']) ?>
                            </div>
                            <h3 class="font-bold text-gray-900 mb-2 line-clamp-2 group-hover:text-primary transition">
                                <?= htmlspecialchars($product['nama_produk']) ?>
                            </h3>

                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-primary font-bold text-lg">Rp <?= number_format($product['harga_diskon'], 0, ',', '.') ?></span>
                                <span class="text-gray-400 line-through text-sm">Rp <?= number_format($product['harga_asli'], 0, ',', '.') ?></span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span><i class="fas fa-box mr-1"></i> Stok: <?= $product['stok'] ?></span>
                                <span><i class="fas fa-map-marker-alt mr-1"></i> <?= htmlspecialchars($product['kota']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 text-center md:hidden">
                <a href="PromosiPage.php" class="inline-flex items-center gap-2 text-primary font-semibold hover:text-primary-dark transition">
                    Lihat Semua Promo <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS SECTION -->
    <section id="cara-kerja" class="py-20 bg-gradient-to-b from-white to-green-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Cara Kerja FoodSave</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Hemat hingga 70% dengan 3 langkah mudah</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="relative">
                    <div class="bg-white rounded-2xl p-8 text-center shadow-lg border border-gray-100 h-full">
                        <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center text-4xl mx-auto mb-6 shadow-xl">
                            🔍
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">1. Cari Makanan</h3>
                        <p class="text-gray-600">Jelajahi berbagai makanan surplus dari toko terdekat dengan diskon menarik</p>
                    </div>
                    <!-- Connector Line -->
                    <div class="hidden md:block absolute top-1/2 -right-4 w-8 h-0.5 bg-green-500"></div>
                </div>

                <!-- Step 2 -->
                <div class="relative">
                    <div class="bg-white rounded-2xl p-8 text-center shadow-lg border border-gray-100 h-full">
                        <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center text-4xl mx-auto mb-6 shadow-xl">
                            🛒
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">2. Pesan & Bayar</h3>
                        <p class="text-gray-600">Pilih makanan favoritmu dan selesaikan pembayaran dengan mudah dan aman</p>
                    </div>
                    <!-- Connector Line -->
                    <div class="hidden md:block absolute top-1/2 -right-4 w-8 h-0.5 bg-green-500"></div>
                </div>

                <!-- Step 3 -->
                <div>
                    <div class="bg-white rounded-2xl p-8 text-center shadow-lg border border-gray-100 h-full">
                        <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center text-4xl mx-auto mb-6 shadow-xl">
                            🍽️
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">3. Nikmati Makanan</h3>
                        <p class="text-gray-600">Ambil pesananmu di toko atau terima di rumah, lalu nikmati makanan lezat!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION (Background Hijau Tua) -->
    <section class="py-20 relative overflow-hidden" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
        <!-- Pattern Overlay -->
        <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <h2 class="text-3xl md:text-5xl font-bold text-white mb-6">
                Siap Selamatkan Makanan & Hemat Uang?
            </h2>
            <p class="text-xl text-green-100 mb-10 max-w-2xl mx-auto">
                Bergabunglah dengan ribuan pengguna FoodSave yang telah membantu mengurangi food waste dan menghemat pengeluaran
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="RegisterPage.php" class="px-10 py-5 bg-white text-green-700 font-bold rounded-xl hover:bg-gray-100 transition shadow-xl text-lg">
                    🚀 Daftar Sekarang - Gratis!
                </a>
                <a href="PromosiPage.php" class="px-10 py-5 bg-green-800/50 backdrop-blur-sm text-white font-bold rounded-xl hover:bg-green-800/70 transition border-2 border-white/30 text-lg">
                    🛒 Lihat Promo
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <!-- Brand -->
                <div class="col-span-1 md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 gradient-primary rounded-xl flex items-center justify-center text-white text-xl">
                            🌿
                        </div>
                        <span class="text-2xl font-extrabold text-white">FoodSave</span>
                    </div>
                    <p class="text-gray-400 text-sm mb-4">
                        Selamatkan makanan, hemat uang, dan bantu kurangi food waste di Indonesia.
                    </p>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary transition">
                            <i class="fab fa-facebook"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-white font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="Index.php" class="hover:text-primary transition">Beranda</a></li>
                        <li><a href="PromosiPage.php" class="hover:text-primary transition">Jelajah</a></li>
                        <li><a href="#cara-kerja" class="hover:text-primary transition">Cara Kerja</a></li>
                        <li><a href="#tentang" class="hover:text-primary transition">Tentang Kami</a></li>
                    </ul>
                </div>

                <!-- For Sellers -->
                <div>
                    <h4 class="text-white font-bold mb-4">Untuk Penjual</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="RegisterPage.php?role=penjual" class="hover:text-primary transition">Daftar sebagai Penjual</a></li>
                        <li><a href="#" class="hover:text-primary transition">Panduan Penjual</a></li>
                        <li><a href="#" class="hover:text-primary transition">FAQ Penjual</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="text-white font-bold mb-4">Kontak Kami</h4>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            <i class="fas fa-envelope text-primary"></i>
                            <span>hello@foodsave.id</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-phone text-primary"></i>
                            <span>0800-123-4567</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Surakarta, Jawa Tengah</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 pt-8 text-center text-sm text-gray-400">
                <p>&copy; <?= date('Y') ?> FoodSave. All rights reserved. Made with ❤️ in Indonesia</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('shadow-md');
            } else {
                navbar.classList.remove('shadow-md');
            }
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#') {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });

        // Toggle Profile Dropdown
        function toggleProfileDropdown() {
            const menu = document.getElementById('profileDropdownMenu');
            menu.classList.toggle('hidden');
        }

        // Tutup dropdown kalau klik di luar
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const menu = document.getElementById('profileDropdownMenu');

            if (dropdown && menu && !dropdown.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>

</body>

</html>