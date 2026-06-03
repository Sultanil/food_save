<?php
// includes/navbar_promosi.php

// Pastikan session sudah aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default values jika variabel belum ada
$cart_badge_count = $cart_badge_count ?? 0;
?>

<nav class="bg-white px-7 py-3 flex items-center gap-5 shadow-sm sticky top-0 z-10">
    <a href="Index.php" class="font-extrabold text-green-600 text-lg mr-auto no-underline">🌿 FoodSave</a>
    <a href="Index.php" class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Beranda</a>
    <a href="#" class="text-green-600 font-semibold text-sm no-underline">Promo</a>
    <a href="PromosiPage.php" class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Cari Makanan</a>
    
    <?php if (isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true): ?>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'pembeli'): ?>
            <a href="keranjang.php" id="cart-icon-link" class="relative p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-xl transition group" title="Keranjang Belanja">
                <i id="cart-icon" class="fa-solid fa-cart-shopping text-lg"></i>
                <?php if (!empty($cart_badge_count) && $cart_badge_count > 0): ?>
                    <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-lg border-2 border-white">
                        <?= $cart_badge_count > 99 ? '99+' : $cart_badge_count ?>
                    </span>
                <?php else: ?>
                    <span id="cart-badge" class="hidden"></span>
                <?php endif; ?>
            </a>
        <?php else: ?>
            <a href="dashboardPenjual.php" class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🏪 Dashboard</a>
        <?php endif; ?>
        <a href="logout.php" class="bg-red-500 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-red-600">🚪 Keluar</a>
    <?php else: ?>
        <a href="LoginPage.php" class="bg-green-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-700">👤 Masuk</a>
    <?php endif; ?>
</nav>