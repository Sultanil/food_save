<?php
// includes/header.php

// Pastikan session sudah aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default values untuk mencegah error "undefined variable"
$site_name = $site_name ?? 'Food Save';
$page_title = $page_title ?? $site_name;
$description = $description ?? 'Platform Food Waste Terdepan di Indonesia';
$is_logged_in = $is_logged_in ?? (isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true);
$username = $username ?? ($is_logged_in ? htmlspecialchars($_SESSION['nama'] ?? 'Pengguna') : '');
$cart_badge_count = $cart_badge_count ?? 0;
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <title><?= htmlspecialchars($page_title) ?> - Selamatkan Makanan, Wujudkan Masa Depan Berkelanjutan</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style type="text/tailwindcss">
        @theme {
            --color-brand: #22c55e;
            --color-brand-light: #4ade80;
            --color-brand-dark: #16a34a;
            --color-brand-darker: #15803d;
            --font-primary: "Poppins", sans-serif;
        }
        body { font-family: var(--font-primary); }
    </style>
    
    <!-- Custom Animations -->
    <style>
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .bg-animated-gradient {
            background: linear-gradient(-45deg, #f0fdf4, #dcfce7, #bbf7d0, #86efac, #f0fdf4);
            background-size: 400% 400%;
            animation: gradient-shift 15s ease infinite;
        }
        
        @keyframes float-slow {
            0%, 100% { transform: translateY(0) translateX(0) scale(1); }
            33% { transform: translateY(-20px) translateX(10px) scale(1.05); }
            66% { transform: translateY(10px) translateX(-15px) scale(0.95); }
        }
        @keyframes float-medium {
            0%, 100% { transform: translateY(0) translateX(0) rotate(0deg); }
            50% { transform: translateY(-30px) translateX(20px) rotate(5deg); }
        }
        @keyframes float-fast {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-15px) translateX(-10px); }
        }
        .blob-float-slow { animation: float-slow 20s ease-in-out infinite; }
        .blob-float-medium { animation: float-medium 12s ease-in-out infinite; }
        .blob-float-fast { animation: float-fast 8s ease-in-out infinite; }
        
        .bg-pattern-dots {
            background-image: radial-gradient(circle, rgba(34,197,94,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
        }
        .bg-pattern-grid {
            background-image: 
                linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        
        .wave-divider { position: relative; }
        .wave-divider::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 60px;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 1200 120' xmlns='http://www.w3.org/2000/svg' preserveAspectRatio='none'%3E%3Cpath d='M0,0V46.29c47.79,22.2,103.59,32.17,158.59,32.17,55,0,110.8-9.97,158.59-32.17V0h-317.18Z' fill='%23ffffff'/%3E%3C/svg%3E") no-repeat;
            background-size: cover;
        }
        .wave-divider-up::before {
            content: "";
            position: absolute;
            top: -1px;
            left: 0;
            right: 0;
            height: 60px;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 1200 120' xmlns='http://www.w3.org/2000/svg' preserveAspectRatio='none'%3E%3Cpath d='M1200,0V73.71C1152.21,95.9,1096.41,105.87,1041.41,105.87C986.41,105.87,930.61,95.9,882.82,73.71V0H1200Z' fill='%23ffffff'/%3E%3C/svg%3E") no-repeat;
            background-size: cover;
        }
        
        .bg-noise { position: relative; }
        .bg-noise::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.03;
            pointer-events: none;
            z-index: 1;
        }
        .bg-noise > * { position: relative; z-index: 2; }
        
        @keyframes glow-pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
        .glow-pulse { animation: glow-pulse 4s ease-in-out infinite; }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <!-- ==================== NAVBAR ==================== -->
    <nav class="bg-white/70 backdrop-blur-xl shadow-sm sticky top-0 z-50 border-b border-white/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-2.5 group">
                    <span class="text-2xl transition-transform group-hover:scale-110">🌿</span>
                    <span class="font-bold text-xl text-gray-900 group-hover:text-brand transition-colors">
                        <?= htmlspecialchars($site_name) ?>
                    </span>
                </a>

                <!-- Right Section -->
                <div class="flex items-center gap-1 sm:gap-2">
                    
                    <!-- Cart Icon -->
                    <?php if ($is_logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'pembeli'): ?>
                        <a href="keranjang.php" 
                           class="relative p-2.5 text-gray-600 hover:text-brand hover:bg-green-50 rounded-xl transition-all duration-200 group" 
                           title="Keranjang Belanja">
                            <i class="fa-solid fa-cart-shopping text-lg group-hover:scale-110 transition-transform"></i>
                            <?php if (!empty($cart_badge_count) && $cart_badge_count > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center animate-pulse shadow-lg border-2 border-white">
                                    <?= $cart_badge_count > 99 ? '99+' : $cart_badge_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <!-- Auth Section -->
                    <?php if ($is_logged_in): ?>
                        <div class="relative group">
                            <button class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-100/50 transition-all duration-200 cursor-pointer">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand to-brand-dark flex items-center justify-center text-white font-bold text-sm shadow-md">
                                    <?= strtoupper(substr($username, 0, 1)) ?>
                                </div>
                                <span class="text-sm font-medium text-gray-700 hidden sm:block"><?= $username ?></span>
                                <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform group-hover:rotate-180"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-52 bg-white/90 backdrop-blur-md rounded-2xl shadow-xl border border-white/20 py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                                <a href="profil.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition">
                                    <i class="fa-regular fa-user"></i> Profil
                                </a>
                                <?php if (isset($_SESSION['role'])): ?>
                                    <?php if ($_SESSION['role'] === 'penjual'): ?>
                                        <a href="pesanan.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition"><i class="fa-solid fa-box-open"></i> Pesanan Masuk</a>
                                        <a href="dashboardPenjual.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition"><i class="fa-solid fa-store"></i> Dashboard Toko</a>
                                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                        <a href="dashboardAdmin.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition"><i class="fa-solid fa-crown"></i> Dashboard Admin</a>
                                    <?php else: ?>
                                        <a href="riwayat_pembelian.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Belanja</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <hr class="my-2 border-gray-100">
                                <form method="POST" action="logout.php">
                                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition rounded-b-2xl">
                                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="hidden sm:flex items-center gap-2">
                            <div class="relative group">
                                <button class="px-4 py-2 text-sm font-semibold text-brand bg-white/80 backdrop-blur-sm border-2 border-brand rounded-xl hover:bg-green-50 transition flex items-center gap-1">
                                    Daftar <i class="fa-solid fa-chevron-down text-xs transition-transform group-hover:rotate-180"></i>
                                </button>
                                <div class="absolute right-0 mt-2 w-48 bg-white/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                                    <a href="RegisterPage.php?role=pembeli" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition"><i class="fa-solid fa-cart-shopping mr-2"></i>Sebagai Pembeli</a>
                                    <a href="RegisterPage.php?role=penjual" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-brand transition"><i class="fa-solid fa-store mr-2"></i>Sebagai Penjual</a>
                                </div>
                            </div>
                            <a href="LoginPage.php" class="px-5 py-2 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-dark transition shadow-md hover:shadow-lg">Masuk</a>
                        </div>
                        <div class="sm:hidden">
                            <a href="LoginPage.php" class="p-2 text-brand hover:bg-green-50 rounded-xl transition"><i class="fa-regular fa-user text-lg"></i></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>