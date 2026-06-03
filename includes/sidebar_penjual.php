<?php
// includes/sidebar_penjual.php
// Pastikan $penjual['nama_toko'] sudah di-set sebelum include

// Set default jika belum ada
$penjual_nama_toko = $penjual['nama_toko'] ?? 'Toko Saya';
$current_page = basename($_SERVER['PHP_SELF']); // Untuk highlight menu aktif
?>

<aside class="w-64 bg-white shadow-lg hidden md:block">
    <div class="p-6 border-b border-gray-200">
        <a href="dashboardPenjual.php" class="flex items-center gap-2">
            <span class="text-2xl">🌿</span>
            <h1 class="text-2xl font-bold text-green-600">FoodSave</h1>
        </a>
        <p class="text-xs text-gray-500 mt-2 truncate" title="<?= htmlspecialchars($penjual_nama_toko) ?>">
            <?= htmlspecialchars($penjual_nama_toko) ?>
        </p>
    </div>

    <nav class="p-4 space-y-2">
        <a href="dashboardPenjual.php" 
           class="flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition <?= $current_page === 'dashboardPenjual.php' ? 'bg-green-500 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            <span>📊</span> Dashboard
        </a>
        <a href="pesanan.php" 
           class="flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition <?= $current_page === 'pesanan.php' ? 'bg-green-500 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            <span>🛒</span> Pesanan Masuk
        </a>
        <a href="profil_toko.php" 
           class="flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition <?= $current_page === 'profil_toko.php' ? 'bg-green-500 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            <span>🏪</span> Profil Toko
        </a>
        <hr class="my-3 border-gray-200">
        <a href="index.php" 
           class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition">
            <span>🏠</span> Ke Beranda
        </a>
        <a href="logout.php" 
           class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
            <span>🚪</span> Logout
        </a>
    </nav>
</aside>

<!-- Mobile Sidebar Toggle (untuk responsive) -->
<div class="md:hidden fixed bottom-4 right-4 z-50">
    <a href="dashboardPenjual.php" class="block w-12 h-12 bg-green-500 text-white rounded-full flex items-center justify-center shadow-lg">
        📊
    </a>
</div>