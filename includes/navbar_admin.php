<?php
// includes/navbar_admin.php
$admin_nama = $_SESSION['nama'] ?? 'Admin';
?>

<nav class="bg-white shadow-sm border-b sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
        <h1 class="text-xl font-bold text-green-600">🌿 Admin FoodSave</h1>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600">Halo, <?= htmlspecialchars($admin_nama) ?></span>
            <a href="logout.php" class="text-sm text-red-600 hover:underline">Logout</a>
        </div>
    </div>
</nav>