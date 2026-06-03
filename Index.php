<?php
require_once 'config/database.php';
require_once 'includes/session_check.php';

// ==================== LOGIKA PHP (DIPISAH DARI HTML) ====================
$site_name   = "Food Save";
$tagline     = "Reduce Waste. Feed More. Sustain Better.";
$description = "Dengan teknologi dan ekonomi sirkular, sisa makanan dimanfaatkan kembali menjadi nilai ekonomi yang berkelanjutan.";
$page_title  = $site_name; // Untuk <title>

$stats = [
    ["icon" => "🌾", "value" => "67 Ton", "label" => "Makanan Diselamatkan"],
    ["icon" => "👥", "value" => "1000+", "label" => "Pengguna Aktif"],
    ["icon" => "🤝", "value" => "500+", "label" => "Mitra Terpercaya"],
];

$langkah = [
    ["icon" => "🔍", "title" => "Temukan Produk", "desc" => "Jelajahi produk surplus berkualitas dari mitra terpercaya"],
    ["icon" => "🛒", "title" => "Pilih Produk", "desc" => "Tambahkan ke keranjang dan atur quantity sesuai kebutuhan"],
    ["icon" => "💳", "title" => "Bayar & Nikmati", "desc" => "Selesaikan pembayaran dan dapatkan produk langsung"],
];

$is_logged_in = isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true;
$username = $is_logged_in ? htmlspecialchars($_SESSION['nama'] ?? 'Pengguna') : '';
$cart_badge_count = 0;

// Handle POST untuk keranjang
if ($is_logged_in && $_SESSION['role'] === 'pembeli') {
    $user_id = $_SESSION['user_id'];

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

    // Hitung badge keranjang
    $stmt = $conn->prepare("SELECT SUM(qty) as total FROM keranjang WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $cart_badge_count = $result['total'] ?? 0;
}

// Data produk featured (bisa dipindah ke database nanti)
$products = [
    ["name" => "Sayuran Segar", "price" => "15.000", "old" => "45.000", "img" => "https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=400&auto=format&fit=crop", "badge" => "-67%"],
    ["name" => "Buah Organik", "price" => "25.000", "old" => "60.000", "img" => "https://images.unsplash.com/photo-1569127959161-2b1297b2d9a6?w=400&auto=format&fit=crop", "badge" => "-58%"],
    ["name" => "Roti & Bakery", "price" => "12.000", "old" => "35.000", "img" => "https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=400&auto=format&fit=crop", "badge" => "-65%"],
];
?>

<?php include 'includes/header.php'; ?>

    <!-- ==================== HERO SECTION ==================== -->
    <section class="relative py-24 sm:py-32 px-4 text-center bg-animated-gradient overflow-hidden">
        
        <!-- Background Decorative Elements -->
        <div class="absolute inset-0 bg-pattern-dots opacity-50"></div>
        
        <!-- Floating Blobs -->
        <div class="absolute top-20 left-10 w-32 h-32 sm:w-48 sm:h-48 bg-brand-light/20 rounded-full blur-3xl blob-float-slow"></div>
        <div class="absolute bottom-32 right-10 sm:right-20 w-40 h-40 sm:w-64 sm:h-64 bg-emerald-300/20 rounded-full blur-3xl blob-float-medium"></div>
        <div class="absolute top-1/2 left-1/4 w-24 h-24 sm:w-36 sm:h-36 bg-teal-300/20 rounded-full blur-2xl blob-float-fast"></div>
        <div class="absolute bottom-10 left-1/3 w-20 h-20 bg-green-200/30 rounded-full blur-xl blob-float-slow"></div>
        
        <!-- Glow Orbs -->
        <div class="absolute top-1/4 right-1/4 w-16 h-16 bg-brand/30 rounded-full blur-2xl glow-pulse"></div>
        <div class="absolute bottom-1/4 left-1/5 w-12 h-12 bg-emerald-400/30 rounded-full blur-xl glow-pulse" style="animation-delay: -2s"></div>
        
        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-white/80"></div>
        
        <!-- Content -->
        <div class="relative z-10 max-w-4xl mx-auto">
            <!-- Badge -->
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-white/60 backdrop-blur-md border border-green-200/60 rounded-full text-sm font-medium text-brand-dark shadow-lg mb-6 hover:shadow-xl transition-shadow">
                <i class="fa-solid fa-leaf animate-pulse"></i> Platform Food Waste Terdepan di Indonesia
            </span>
            
            <!-- Headline -->
            <h1 class="text-4xl sm:text-5xl lg:text-7xl font-extrabold text-gray-900 leading-tight mb-6">
                <?= htmlspecialchars($tagline) ?>
            </h1>
            
            <!-- Description -->
            <p class="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto mb-10 leading-relaxed">
                <?= htmlspecialchars($description) ?>
            </p>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <?php if ($is_logged_in): ?>
                    <a href="PromosiPage.php" class="group px-8 py-4 font-semibold text-white bg-brand rounded-2xl hover:bg-brand-dark transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Lihat Produk
                        <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </a>
                <?php else: ?>
                    <a href="RegisterPage.php?role=pembeli" class="group px-8 py-4 font-semibold text-white bg-brand rounded-2xl hover:bg-brand-dark transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 flex items-center gap-2">
                        <i class="fa-solid fa-cart-shopping"></i> Daftar Sebagai Pembeli
                    </a>
                    <a href="RegisterPage.php?role=penjual" class="px-8 py-4 font-semibold text-brand bg-white/80 backdrop-blur-sm border-2 border-brand rounded-2xl hover:bg-green-50 transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-1">
                        Daftar Sebagai Penjual
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Trust Badges -->
            <div class="mt-14 flex flex-wrap justify-center items-center gap-6 text-sm text-gray-600">
                <div class="flex items-center gap-2 px-4 py-2 bg-white/60 backdrop-blur-sm rounded-full shadow-sm">
                    <i class="fa-solid fa-shield-halved text-brand"></i>
                    <span>Transaksi Aman</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white/60 backdrop-blur-sm rounded-full shadow-sm">
                    <i class="fa-solid fa-truck-fast text-brand"></i>
                    <span>Pengiriman Cepat</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white/60 backdrop-blur-sm rounded-full shadow-sm">
                    <i class="fa-solid fa-leaf text-brand"></i>
                    <span>Ramah Lingkungan</span>
                </div>
            </div>
        </div>
        
        <!-- Bottom Wave -->
        <div class="absolute bottom-0 left-0 right-0">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none" class="w-full h-16 sm:h-24 text-white fill-current">
                <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158.59,32.17,55,0,110.8-9.97,158.59-32.17V0h-317.18Z"></path>
            </svg>
        </div>
    </section>

    <!-- ==================== STATS SECTION ==================== -->
    <section class="relative py-10 bg-white/90 backdrop-blur-sm border-y border-gray-100">
        <div class="absolute inset-0 bg-gradient-to-r from-brand/5 via-transparent to-emerald-50/30"></div>
        <div class="relative max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <?php foreach ($stats as $stat): ?>
                    <div class="flex items-center justify-center sm:justify-start gap-4 p-5 rounded-2xl bg-gradient-to-br from-white to-gray-50 border border-gray-100 hover:border-brand/30 hover:shadow-lg transition-all duration-300 group">
                        <span class="text-4xl group-hover:scale-110 transition-transform"><?= $stat['icon'] ?></span>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?= $stat['value'] ?></p>
                            <p class="text-sm text-gray-500"><?= $stat['label'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ==================== FEATURED PRODUCTS ==================== -->
    <section class="relative py-20 px-4 bg-white wave-divider-up">
        <div class="absolute inset-0 bg-pattern-grid opacity-30"></div>
        <div class="relative max-w-7xl mx-auto">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10">
                <div>
                    <span class="inline-block px-3 py-1 bg-brand/10 text-brand-dark text-xs font-semibold rounded-full mb-3">
                        🔥 Promo Hari Ini
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Produk Pilihan</h2>
                    <p class="text-gray-500 mt-1">Hemat hingga 70% untuk produk berkualitas</p>
                </div>
                <a href="PromosiPage.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:underline group">
                    Lihat Semua Produk
                    <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($products as $p): ?>
                    <a href="PromosiPage.php" class="group block bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-2xl hover:border-brand/40 transition-all duration-300">
                        <div class="relative aspect-square overflow-hidden bg-gradient-to-br from-gray-100 to-gray-50">
                            <span class="absolute top-3 left-3 z-10 px-3 py-1.5 bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold rounded-xl shadow-lg">
                                <?= $p['badge'] ?>
                            </span>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity z-10"></div>
                            <img src="<?= $p['img'] ?>" alt="<?= $p['name'] ?>" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                            <button class="absolute bottom-3 right-3 z-20 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center text-brand shadow-lg opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300 hover:bg-brand hover:text-white">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold text-gray-900 group-hover:text-brand transition-colors"><?= $p['name'] ?></h3>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span class="text-xl font-bold text-brand">Rp <?= $p['price'] ?></span>
                                <span class="text-sm text-gray-400 line-through">Rp <?= $p['old'] ?></span>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 flex items-center gap-1">
                                <i class="fa-solid fa-circle-exclamation text-amber-500"></i>
                                Stok terbatas
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ==================== HOW IT WORKS ==================== -->
    <section class="relative py-20 px-4 bg-gradient-to-b from-gray-50 via-white to-emerald-50/30">
        <div class="absolute inset-0 bg-noise"></div>
        <div class="relative max-w-7xl mx-auto">
            <div class="text-center mb-14">
                <span class="inline-block px-4 py-2 bg-brand/10 text-brand-dark text-sm font-semibold rounded-full mb-4">
                    ✨ Cara Kerja
                </span>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Mudah Hanya 3 Langkah</h2>
                <p class="text-gray-600 mt-2 max-w-xl mx-auto">Food Save dirancang agar ramah dan mudah digunakan untuk semua kalangan</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($langkah as $index => $item): ?>
                    <div class="relative group">
                        <?php if ($index < 2): ?>
                            <div class="hidden md:block absolute top-12 left-full w-full h-0.5 bg-gradient-to-r from-brand/40 to-transparent -z-10"></div>
                        <?php endif; ?>
                        
                        <div class="relative bg-white/80 backdrop-blur-md p-7 rounded-3xl shadow-lg border border-gray-100/50 hover:shadow-2xl hover:border-brand/30 transition-all duration-300 text-center group-hover:-translate-y-2">
                            <!-- Step Number -->
                            <div class="absolute -top-3 -right-3 w-8 h-8 bg-gradient-to-br from-brand to-brand-dark text-white text-sm font-bold rounded-full flex items-center justify-center shadow-lg border-4 border-white">
                                <?= $index + 1 ?>
                            </div>
                            
                            <!-- Icon -->
                            <div class="w-16 h-16 mx-auto mb-5 bg-gradient-to-br from-brand/20 to-emerald-200/30 text-brand-dark rounded-2xl flex items-center justify-center text-3xl shadow-inner group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <?= $item['icon'] ?>
                            </div>
                            
                            <h4 class="font-bold text-gray-900 mb-3 text-lg"><?= $item['title'] ?></h4>
                            <p class="text-sm text-gray-500 leading-relaxed"><?= $item['desc'] ?></p>
                            
                            <!-- Hover Glow -->
                            <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-brand/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity -z-10"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ==================== CTA SECTION ==================== -->
    <section class="relative py-20 px-4 overflow-hidden">
        <!-- Animated Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-brand via-brand-dark to-emerald-800"></div>
        <div class="absolute inset-0 bg-pattern-dots opacity-20"></div>
        
        <!-- Floating Elements -->
        <div class="absolute top-10 right-10 w-24 h-24 bg-white/10 rounded-full blur-2xl blob-float-slow"></div>
        <div class="absolute bottom-20 left-20 w-32 h-32 bg-white/10 rounded-full blur-3xl blob-float-medium"></div>
        
        <div class="relative max-w-4xl mx-auto text-center text-white">
            <span class="inline-block px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full text-sm font-medium mb-6">
                🌍 Bergabung Sekarang
            </span>
            <h2 class="text-2xl sm:text-4xl font-bold mb-5">Siap Berkontribusi untuk Bumi?</h2>
            <p class="text-white/90 mb-10 max-w-xl mx-auto text-lg">
                Bergabunglah dengan ribuan pengguna yang sudah menyelamatkan makanan dan mengurangi limbah setiap hari.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="RegisterPage.php?role=pembeli" class="px-8 py-4 bg-white text-brand font-semibold rounded-2xl hover:bg-gray-100 transition shadow-xl hover:shadow-2xl hover:-translate-y-1">
                    Mulai Belanja Sekarang
                </a>
                <a href="#cara-kerja" class="px-8 py-4 bg-white/20 backdrop-blur-sm text-white font-semibold rounded-2xl hover:bg-white/30 transition border border-white/30 hover:-translate-y-1">
                    Pelajari Lebih Lanjut
                </a>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>