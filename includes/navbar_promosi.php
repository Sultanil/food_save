<?php
// includes/navbar.php
// Pastikan session sudah started di file yang include ini!
?>
<nav class="bg-white px-7 py-3 flex items-center gap-5 shadow-sm sticky top-0 z-10">
    <a href="Index.php" class="font-extrabold text-green-600 text-lg mr-auto no-underline">🌿 FoodSave</a>
    <a href="Index.php" class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Beranda</a>
    <a href="PromosiPage.php" class="text-green-600 font-semibold text-sm no-underline">Promo</a>
    
    <?php if (isset($_SESSION['sudah_login'])): ?>
        <?php if ($_SESSION['role'] === 'penjual'): ?>
            <a href="dashboardPenjual.php" class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🏪 Dashboard</a>
        <?php else: ?>
            <a href="keranjang.php" class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🛒 Keranjang</a>
        <?php endif; ?>
        <a href="logout.php" class="bg-red-500 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-red-600">🚪 Keluar</a>
    <?php else: ?>
        <a href="LoginPage.php" class="bg-green-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-700">👤 Masuk</a>
    <?php endif; ?>
</nav>