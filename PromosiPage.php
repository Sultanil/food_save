<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// 🔐 Cek login
if (!isset($_SESSION['sudah_login'])) {
    header("Location: LoginPage.php?msg=login_required");
    exit;
}

// ==================== FILTER KATEGORI ====================
$filter_kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// ==================== STATS SECTION (Real Data) ====================
try {
    // Total makanan terselamatkan (jumlah transaksi)
    $stmt_stats = $pdo->query("SELECT COUNT(*) as total_transaksi FROM transaksi WHERE status != 'dibatalkan'");
    $total_transaksi = $stmt_stats->fetch()['total_transaksi'];

    // Total toko aktif
    $stmt_toko = $pdo->query("SELECT COUNT(*) as total_toko FROM penjual WHERE status_verifikasi = 'disetujui'");
    $total_toko = $stmt_toko->fetch()['total_toko'];

    // Total pembeli
    $stmt_pembeli = $pdo->query("SELECT COUNT(*) as total_pembeli FROM users WHERE role = 'pembeli'");
    $total_pembeli = $stmt_pembeli->fetch()['total_pembeli'];

    // Estimasi makanan terselamatkan (anggap rata-rata 0.5kg per transaksi)
    $makanan_terselamatkan = round($total_transaksi * 0.5, 1);
} catch (PDOException $e) {
    $total_transaksi = 0;
    $total_toko = 0;
    $total_pembeli = 0;
    $makanan_terselamatkan = 0;
}

// ==================== FEATURED PRODUCTS (Carousel) ====================
$featured_query = "SELECT p.*, pj.nama_toko, pj.kota, pj.foto_profil as toko_foto
                   FROM produk p
                   JOIN penjual pj ON p.penjual_id = pj.id
                   WHERE p.status = 'aktif' AND p.stok > 0 AND p.harga_diskon IS NOT NULL
                   ORDER BY (p.harga_asli - p.harga_diskon) DESC
                   LIMIT 10";
$featured_products = $pdo->query($featured_query)->fetchAll(PDO::FETCH_ASSOC);

// ==================== FLASH SALE (Diskon > 50%) ====================
$flash_sale_query = "SELECT p.*, pj.nama_toko, pj.kota, pj.foto_profil as toko_foto
                     FROM produk p
                     JOIN penjual pj ON p.penjual_id = pj.id
                     WHERE p.status = 'aktif' AND p.stok > 0 
                     AND p.harga_diskon IS NOT NULL 
                     AND p.harga_diskon < (p.harga_asli * 0.5)
                     ORDER BY RAND()
                     LIMIT 8";
$flash_sale_products = $pdo->query($flash_sale_query)->fetchAll(PDO::FETCH_ASSOC);

// ==================== DAFTAR TOKO ====================
$toko_list = [];
$query_toko = "SELECT DISTINCT 
                pj.id as penjual_id, 
                pj.nama_toko, 
                pj.kota,
                pj.foto_profil,
                COUNT(p.id) as total_produk 
               FROM penjual pj 
               JOIN produk p ON pj.id = p.penjual_id 
               WHERE p.status = 'aktif' AND p.stok > 0 
               GROUP BY pj.id 
               ORDER BY pj.nama_toko ASC";

$result_toko = $pdo->query($query_toko);
while ($row = $result_toko->fetch()) {
    $toko_list[] = [
        'penjual_id' => $row['penjual_id'],
        'nama_toko' => htmlspecialchars($row['nama_toko']),
        'kota' => htmlspecialchars($row['kota']),
        'total_produk' => $row['total_produk'],
        'foto_profil' => !empty($row['foto_profil']) && file_exists($row['foto_profil'])
            ? $row['foto_profil']
            : null,
    ];
}
$toko_json = json_encode($toko_list, JSON_UNESCAPED_UNICODE);

// ==================== PRODUK DENGAN FILTER KATEGORI ====================
$produk_list = [];

$query_produk = "SELECT 
                    p.id, p.penjual_id, p.nama_produk, p.kategori, p.deskripsi, 
                    p.harga_asli, p.harga_diskon, p.stok, p.satuan, p.gambar_url, 
                    pj.nama_toko, pj.kota, pj.foto_profil as toko_foto
                 FROM produk p 
                 JOIN penjual pj ON p.penjual_id = pj.id 
                 WHERE p.status = 'aktif' AND p.stok > 0";

$params = [];

if (!empty($filter_kategori)) {
    $query_produk .= " AND p.kategori = ?";
    $params[] = $filter_kategori;
}

$query_produk .= " ORDER BY pj.nama_toko, p.created_at DESC";

$stmt = $pdo->prepare($query_produk);
$stmt->execute($params);

while ($row = $stmt->fetch()) {
    $harga_tampil = !empty($row['harga_diskon']) && $row['harga_diskon'] < $row['harga_asli']
        ? $row['harga_diskon'] : $row['harga_asli'];

    $diskon_persen = 0;
    if (!empty($row['harga_diskon']) && $row['harga_asli'] > 0) {
        $diskon_persen = round((1 - $row['harga_diskon'] / $row['harga_asli']) * 100);
    }

    $produk_list[] = [
        'id' => $row['id'],
        'penjual_id' => $row['penjual_id'],
        'img' => !empty($row['gambar_url']) ? $row['gambar_url'] : 'https://via.placeholder.com/400x300?text=No+Image',
        'disc' => $diskon_persen > 0 ? "-{$diskon_persen}%" : '',
        'name' => htmlspecialchars($row['nama_produk']),
        'seller' => htmlspecialchars($row['nama_toko']),
        'city' => htmlspecialchars($row['kota']),
        'nw' => "Rp " . number_format($harga_tampil, 0, ',', '.'),
        'ol' => !empty($row['harga_diskon']) && $row['harga_diskon'] < $row['harga_asli']
            ? "Rp " . number_format($row['harga_asli'], 0, ',', '.') : '',
        'harga_raw' => $harga_tampil,
        'produk_id' => $row['id'],
        'stok' => $row['stok'],
        'satuan' => $row['satuan'],
        'kategori' => $row['kategori'] ?? 'Lainnya',
        'toko_foto' => !empty($row['toko_foto']) && file_exists($row['toko_foto'])
            ? $row['toko_foto']
            : null,
    ];
}
$produk_json = json_encode($produk_list, JSON_UNESCAPED_UNICODE);

// ==================== DAFTAR KATEGORI ====================
$list_kategori = ['Makanan Berat', 'Minuman', 'Kue & Roti', 'Snack', 'Buah & Sayur', 'Lainnya'];
$kategori_icons = [
    'Makanan Berat' => '🍱',
    'Minuman' => '🥤',
    'Kue & Roti' => '🧁',
    'Snack' => '🍿',
    'Buah & Sayur' => '🍎',
    'Lainnya' => '🍽️'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FoodSave – Selamatkan Makanan, Hemat Lebih Banyak</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .page {
            display: none;
            animation: fade .3s ease;
        }

        .page.active {
            display: block;
        }

        @keyframes fade {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .card {
            transition: transform .2s, box-shadow .2s;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        #grid-toko,
        #grid-produk {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        /* Carousel Styles */
        .carousel-container {
            position: relative;
            overflow: hidden;
        }

        .carousel-track {
            display: flex;
            transition: transform 0.5s ease;
        }

        .carousel-item {
            flex: 0 0 calc(33.333% - 16px);
            margin-right: 24px;
        }

        @media (max-width: 1024px) {
            .carousel-item {
                flex: 0 0 calc(50% - 12px);
            }
        }

        @media (max-width: 640px) {
            .carousel-item {
                flex: 0 0 100%;
                margin-right: 0;
            }
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
        }

        .carousel-btn:hover {
            background: #f3f4f6;
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-btn-prev {
            left: -24px;
        }

        .carousel-btn-next {
            right: -24px;
        }

        /* Flash Sale Animation */
        @keyframes pulse-red {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .flash-sale-badge {
            animation: pulse-red 2s infinite;
        }

        /* Stats Counter Animation */
        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-item {
            animation: countUp 0.6s ease-out forwards;
        }

        /* Filter Pills */
        .filter-pill {
            transition: all 0.3s ease;
        }

        .filter-pill:hover {
            transform: translateY(-2px);
        }

        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Store Photo Fallback */
        .store-avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
    </style>
</head>

<body class="bg-slate-50">

    <!-- ═══ NAVBAR ═══ -->
    <?php include 'includes/navbar_promosi.php'; ?>

    <!-- ═══ HALAMAN DAFTAR TOKO ═══ -->
    <div id="toko-page" class="page active">

        <!-- Hero Section -->
        <div class="text-center text-white py-16 px-7" style="background: linear-gradient(135deg,#0d7a3e,#22c55e)">
            <h1 class="text-4xl font-extrabold mb-3">Selamatkan Makanan, Hemat Lebih Banyak 🌿</h1>
            <p class="text-lg opacity-90 max-w-2xl mx-auto">Temukan makanan surplus berkualitas dari toko terpercaya dengan diskon hingga 70%</p>
        </div>

        <!-- ═══ STATS SECTION ═══ -->
        <div class="bg-white border-b">
            <div class="max-w-6xl mx-auto px-6 py-10">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    <!-- Stat 1: Makanan Terselamatkan -->
                    <div class="stat-item text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
                            🍽️
                        </div>
                        <div class="text-3xl font-extrabold text-gray-900 mb-1"><?= $makanan_terselamatkan ?>+ kg</div>
                        <div class="text-sm text-gray-600">Makanan Terselamatkan</div>
                    </div>

                    <!-- Stat 2: Toko Aktif -->
                    <div class="stat-item text-center" style="animation-delay: 0.1s">
                        <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
                            🏪
                        </div>
                        <div class="text-3xl font-extrabold text-gray-900 mb-1"><?= $total_toko ?>+</div>
                        <div class="text-sm text-gray-600">Toko Aktif</div>
                    </div>

                    <!-- Stat 3: Transaksi Sukses -->
                    <div class="stat-item text-center" style="animation-delay: 0.2s">
                        <div class="w-16 h-16 bg-yellow-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
                            ✅
                        </div>
                        <div class="text-3xl font-extrabold text-gray-900 mb-1"><?= $total_transaksi ?>+</div>
                        <div class="text-sm text-gray-600">Transaksi Sukses</div>
                    </div>

                    <!-- Stat 4: Pembeli Puas -->
                    <div class="stat-item text-center" style="animation-delay: 0.3s">
                        <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
                            😊
                        </div>
                        <div class="text-3xl font-extrabold text-gray-900 mb-1"><?= $total_pembeli ?>+</div>
                        <div class="text-sm text-gray-600">Pembeli Puas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ FEATURED PRODUCTS CAROUSEL ═══ -->
        <?php if (!empty($featured_products)): ?>
            <div class="max-w-6xl mx-auto px-6 py-10">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-extrabold text-gray-900 mb-1">⭐ Produk Terpopuler</h2>
                        <p class="text-sm text-gray-600">Makanan dengan diskon terbaik yang tidak boleh kamu lewatkan</p>
                    </div>
                </div>

                <div class="carousel-container relative">
                    <button class="carousel-btn carousel-btn-prev" onclick="moveCarousel('featured', -1)">
                        <i class="fas fa-chevron-left text-gray-700"></i>
                    </button>

                    <div class="overflow-hidden">
                        <div id="featured-carousel" class="carousel-track">
                            <?php foreach ($featured_products as $fp):
                                $discount = round((1 - $fp['harga_diskon'] / $fp['harga_asli']) * 100);
                            ?>
                                <div class="carousel-item">
                                    <div class="bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 cursor-pointer card"
                                        onclick="window.location.href='HalamanTransaksi.php?produk_id=<?= $fp['id'] ?>'">
                                        <!-- Image -->
                                        <div class="relative h-48 overflow-hidden">
                                            <?php if (!empty($fp['gambar_url'])): ?>
                                                <img src="<?= htmlspecialchars($fp['gambar_url']) ?>"
                                                    alt="<?= htmlspecialchars($fp['nama_produk']) ?>"
                                                    class="w-full h-full object-cover hover:scale-110 transition-transform duration-500">
                                            <?php else: ?>
                                                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-6xl">
                                                    🍽️
                                                </div>
                                            <?php endif; ?>

                                            <!-- Discount Badge -->
                                            <div class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-lg">
                                                -<?= $discount ?>%
                                            </div>
                                        </div>

                                        <!-- Content -->
                                        <div class="p-4">
                                            <!-- Store Info -->
                                            <div class="flex items-center gap-2 mb-2">
                                                <?php if (!empty($fp['toko_foto']) && file_exists($fp['toko_foto'])): ?>
                                                    <img src="<?= htmlspecialchars($fp['toko_foto']) ?>"
                                                        class="w-6 h-6 rounded-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                                        <?= strtoupper(substr($fp['nama_toko'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="text-xs text-gray-600 font-medium"><?= htmlspecialchars($fp['nama_toko']) ?></span>
                                            </div>

                                            <h3 class="font-bold text-gray-900 mb-2 line-clamp-2 hover:text-green-600 transition">
                                                <?= htmlspecialchars($fp['nama_produk']) ?>
                                            </h3>

                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="text-green-600 font-extrabold text-lg">Rp <?= number_format($fp['harga_diskon'], 0, ',', '.') ?></span>
                                                <span class="text-gray-400 line-through text-sm">Rp <?= number_format($fp['harga_asli'], 0, ',', '.') ?></span>
                                            </div>

                                            <div class="flex items-center justify-between text-xs text-gray-500">
                                                <span><i class="fas fa-box mr-1"></i> Stok: <?= $fp['stok'] ?></span>
                                                <span><i class="fas fa-map-marker-alt mr-1"></i> <?= htmlspecialchars($fp['kota']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button class="carousel-btn carousel-btn-next" onclick="moveCarousel('featured', 1)">
                        <i class="fas fa-chevron-right text-gray-700"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- ═══ FLASH SALE SECTION ═══ -->
        <?php if (!empty($flash_sale_products)): ?>
            <div class="bg-gradient-to-r from-red-500 to-pink-500 py-10">
                <div class="max-w-6xl mx-auto px-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="text-white">
                            <div class="flex items-center gap-3 mb-2">
                                <h2 class="text-3xl font-extrabold">⚡ FLASH SALE</h2>
                                <span class="flash-sale-badge bg-white text-red-500 text-xs font-bold px-3 py-1 rounded-full">HOT!</span>
                            </div>
                            <p class="text-sm opacity-90">Diskon gila-gilaan! Berakhir dalam <span id="countdown" class="font-bold text-yellow-300">00:00:00</span></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach (array_slice($flash_sale_products, 0, 4) as $fs):
                            $discount = round((1 - $fs['harga_diskon'] / $fs['harga_asli']) * 100);
                        ?>
                            <div class="bg-white rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 cursor-pointer card"
                                onclick="window.location.href='HalamanTransaksi.php?produk_id=<?= $fs['id'] ?>'">
                                <!-- Image -->
                                <div class="relative h-40 overflow-hidden">
                                    <?php if (!empty($fs['gambar_url'])): ?>
                                        <img src="<?= htmlspecialchars($fs['gambar_url']) ?>"
                                            alt="<?= htmlspecialchars($fs['nama_produk']) ?>"
                                            class="w-full h-full object-cover hover:scale-110 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-5xl">
                                            🍽️
                                        </div>
                                    <?php endif; ?>

                                    <!-- Discount Badge -->
                                    <div class="absolute top-2 left-2 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-lg shadow-lg">
                                        -<?= $discount ?>%
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="p-3">
                                    <h3 class="font-bold text-gray-900 text-sm mb-2 line-clamp-2">
                                        <?= htmlspecialchars($fs['nama_produk']) ?>
                                    </h3>

                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-red-600 font-extrabold text-base">Rp <?= number_format($fs['harga_diskon'], 0, ',', '.') ?></span>
                                    </div>
                                    <span class="text-gray-400 line-through text-xs">Rp <?= number_format($fs['harga_asli'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ═══ QUICK FILTER PILLS ═══ -->
        <div class="max-w-6xl mx-auto px-6 pt-8 pb-4">
            <div class="flex flex-wrap gap-3 justify-center sm:justify-start items-center">
                <span class="text-sm font-semibold text-gray-600 mr-2">🏷️ Filter:</span>

                <!-- Tombol "Semua" -->
                <a href="PromosiPage.php"
                    class="filter-pill px-5 py-2.5 rounded-full text-sm font-semibold transition <?= empty($filter_kategori) ? 'bg-green-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 hover:border-green-300' ?>">
                    Semua Produk
                </a>

                <!-- Tombol Kategori -->
                <?php foreach ($list_kategori as $kat):
                    $is_active = ($filter_kategori === $kat);
                    $url = "PromosiPage.php?kategori=" . urlencode($kat);
                    $icon = $kategori_icons[$kat] ?? '🍽️';
                ?>
                    <a href="<?= $url ?>"
                        class="filter-pill px-5 py-2.5 rounded-full text-sm font-semibold transition <?= $is_active ? 'bg-green-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 hover:border-green-300' ?>">
                        <?= $icon ?> <?= htmlspecialchars($kat) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ═══ BANNER FILTER KATEGORI ═══ -->
        <?php if (!empty($filter_kategori)): ?>
            <div class="max-w-6xl mx-auto px-6 mb-6">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6 flex flex-col sm:flex-row justify-between items-center gap-4 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center text-3xl">
                            🏷️
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Kategori: <?= htmlspecialchars($filter_kategori) ?></h2>
                            <p class="text-sm text-gray-600">Menampilkan <strong><?= count($produk_list) ?></strong> produk dalam kategori ini</p>
                        </div>
                    </div>
                    <a href="PromosiPage.php"
                        class="px-6 py-3 bg-white text-green-700 font-semibold rounded-xl hover:bg-gray-100 transition border-2 border-green-200 shadow-sm hover:shadow-md">
                        ✖ Reset Filter
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grid Toko -->
        <div id="grid-toko" class="max-w-6xl mx-auto px-6 py-6">
            <!-- Diisi oleh JavaScript -->
        </div>
        <!-- ═══ EDUKASI FOOD WASTE ═══ -->
        <div class="max-w-6xl mx-auto px-6 py-10">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-3xl p-8 shadow-sm">
                <div class="grid md:grid-cols-2 gap-8 items-center">
                    <!-- Left: Content -->
                    <div>
                        <div class="inline-flex items-center gap-2 bg-green-100 px-4 py-2 rounded-full text-sm font-semibold text-green-700 mb-4">
                            <i class="fas fa-leaf"></i>
                            Misi Kami
                        </div>
                        <h2 class="text-3xl font-extrabold text-gray-900 mb-4">
                            Bersama Mengurangi <span class="gradient-text">Food Waste</span>
                        </h2>
                        <p class="text-gray-700 mb-6 leading-relaxed">
                            Tahukah kamu? <strong>1/3 makanan di dunia terbuang sia-sia</strong> setiap tahun.
                            Dengan berbelanja di FoodSave, kamu tidak hanya menghemat uang, tapi juga
                            <strong>menyelamatkan makanan</strong> dan membantu lingkungan.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900">Ramah Lingkungan</div>
                                    <div class="text-xs text-gray-600">Kurangi emisi karbon</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white">
                                    <i class="fas fa-piggy-bank"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900">Hemat Uang</div>
                                    <div class="text-xs text-gray-600">Diskon hingga 70%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Visual -->
                    <div class="relative">
                        <div class="bg-white rounded-2xl p-6 shadow-lg">
                            <div class="text-center mb-4">
                                <div class="text-6xl mb-2">🌍</div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Dampak Positif Kamu</h3>
                                <p class="text-sm text-gray-600">Setiap pembelian membantu mengurangi food waste</p>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-green-50 rounded-xl">
                                    <span class="text-sm font-semibold text-gray-700">Makanan Diselamatkan</span>
                                    <span class="text-lg font-bold text-green-600"><?= $makanan_terselamatkan ?> kg</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-xl">
                                    <span class="text-sm font-semibold text-gray-700">CO₂ Berkurang</span>
                                    <span class="text-lg font-bold text-blue-600"><?= round($makanan_terselamatkan * 2.5, 1) ?> kg</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-xl">
                                    <span class="text-sm font-semibold text-gray-700">Uang Dihemat</span>
                                    <span class="text-lg font-bold text-yellow-600">Rp <?= number_format($makanan_terselamatkan * 25000, 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <?php if (empty($produk_list) && !empty($filter_kategori)): ?>
            <div class="max-w-6xl mx-auto px-6 py-16 text-center">
                <div class="text-6xl mb-4">😔</div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Belum Ada Produk di Kategori Ini</h3>
                <p class="text-gray-600 mb-6">Coba kategori lain atau lihat semua produk</p>
                <a href="PromosiPage.php" class="inline-block px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition">
                    Lihat Semua Produk
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══ HALAMAN PRODUK TOKO ═══ -->
    <div id="produk-page" class="page">
        <!-- Breadcrumb -->
        <div class="bg-white border-b px-7 py-4 sticky top-16 z-10">
            <div class="max-w-6xl mx-auto flex items-center gap-3">
                <button onclick="kembaliKeToko()" class="text-green-600 font-semibold text-sm hover:underline">
                    ← Kembali ke Daftar Toko
                </button>
                <span class="text-gray-300">/</span>
                <span id="toko-nama-display" class="font-bold text-gray-800"></span>
            </div>
        </div>

        <!-- Produk Grid -->
        <div id="grid-produk" class="max-w-6xl mx-auto px-6 py-10">
            <!-- Diisi oleh JavaScript -->
        </div>
    </div>

    <!-- ═══ HALAMAN DETAIL PRODUK ═══ -->
    <div id="detail-page" class="page">
        <div class="max-w-lg mx-auto my-16 bg-white rounded-2xl p-9 shadow-xl text-center">
            <img id="dImg" src="" alt="" class="w-full h-52 object-cover rounded-xl mb-5" />
            <h2 id="dName" class="text-2xl font-extrabold text-slate-800 mb-2"></h2>
            <p id="dSeller" class="text-slate-500 text-sm mb-4"></p>
            <span id="dPrice" class="block text-2xl font-extrabold text-green-600 mb-2"></span>
            <p id="dStok" class="text-sm text-gray-500 mb-6"></p>

            <div class="flex flex-col gap-3">
                <button type="button" onclick="kembaliKeProduk()"
                    class="px-6 py-2 border-2 border-gray-300 text-gray-600 font-bold rounded-xl hover:bg-gray-50">
                    ← Kembali
                </button>

                <button type="button" id="btnAddCart"
                    class="px-6 py-3 bg-yellow-400 text-gray-900 font-bold rounded-xl hover:bg-yellow-300 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    🛒 Tambah ke Keranjang
                </button>

                <form id="formBeli" method="GET" action="HalamanTransaksi.php" class="w-full">
                    <input type="hidden" name="produk" id="fProduk" />
                    <input type="hidden" name="harga" id="fHarga" />
                    <input type="hidden" name="seller" id="fSeller" />
                    <input type="hidden" name="produk_id" id="fProdukId" />
                    <input type="hidden" name="id_toko" id="fIdToko" />
                    <input type="hidden" name="qty" value="1" />

                    <button type="submit"
                        class="w-full px-6 py-3 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700">
                        ⚡ Beli Sekarang (Checkout Langsung)
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 text-slate-400 text-xs mt-5">
        © <?= date('Y') ?> <b class="text-green-600">FoodSave</b> — Selamatkan Makanan, Hemat Lebih Banyak 🌿
    </footer>

    <!-- ═══ JAVASCRIPT ═══ -->
    <script>
        // Pass data dari PHP ke JavaScript
        const TOKO = <?= $toko_json ?>;
        const PRODUK = <?= $produk_json ?>;

        // ═══ CAROUSEL LOGIC ═══
        let carouselPositions = {
            featured: 0
        };

        function moveCarousel(carouselId, direction) {
            const carousel = document.getElementById(`${carouselId}-carousel`);
            const items = carousel.querySelectorAll('.carousel-item');
            const itemWidth = items[0].offsetWidth + 24; // width + gap

            let currentPosition = carouselPositions[carouselId];
            const maxPosition = -(items.length * itemWidth - carousel.parentElement.offsetWidth);

            currentPosition += direction * -itemWidth;

            if (currentPosition > 0) currentPosition = 0;
            if (currentPosition < maxPosition) currentPosition = 0;

            carousel.style.transform = `translateX(${currentPosition}px)`;
            carouselPositions[carouselId] = currentPosition;
        }

        // Auto-rotate carousel every 5 seconds
        setInterval(() => {
            moveCarousel('featured', 1);
        }, 5000);

        // ═══ COUNTDOWN TIMER (Flash Sale) ═══
        function updateCountdown() {
            const now = new Date();
            const endOfDay = new Date();
            endOfDay.setHours(23, 59, 59, 999);

            const diff = endOfDay - now;

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            const countdownEl = document.getElementById('countdown');
            if (countdownEl) {
                countdownEl.textContent =
                    `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>

    <!-- Include external JS -->
    <script src="assets/js/promosi.js"></script>

</body>

</html>