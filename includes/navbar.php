<?php
// includes/navbar.php - Navbar standar untuk user yang sudah login
if (!isset($_SESSION['sudah_login'])) {
    header("Location: LoginPage.php");
    exit;
}

$nama_user = $_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? 'User';
$role = $_SESSION['role'] ?? 'pembeli';
$foto = $_SESSION['foto_profil'] ?? ''; 
?>
<nav class="bg-white shadow-sm border-b sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
        <!-- Logo -->
        <a href="Index.php" class="text-2xl font-extrabold text-green-600 flex items-center gap-2">
            🌿 FoodSave
        </a>
        
        <!-- Menu Kanan -->
        <div class="flex items-center gap-6">
            <a href="Index.php" class="text-sm text-gray-600 hover:text-green-600 font-medium transition">Beranda</a>
            
            <?php if ($role === 'pembeli'): ?>
                <a href="riwayat_pesanan.php" class="text-sm text-gray-600 hover:text-green-600 font-medium transition">Pesanan Saya</a>
            <?php elseif ($role === 'penjual'): ?>
                <a href="dashboardPenjual.php" class="text-sm text-gray-600 hover:text-green-600 font-medium transition">Dashboard Toko</a>
            <?php endif; ?>
            
            <!-- Profile Dropdown -->
            <div class="relative" id="profileDropdown">
                <!-- Klik avatar/nama → Redirect ke edit_profil.php -->
                <a href="edit_profil.php" class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-green-600 transition">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 overflow-hidden border border-gray-300">
                        <?php if (!empty($foto) && file_exists($foto)): ?>
                            <img src="<?= htmlspecialchars($foto) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            👤
                        <?php endif; ?>
                    </div>
                    <span class="hidden sm:block"><?= htmlspecialchars($nama_user) ?></span>
                </a>
                
                <!-- Tombol Toggle Dropdown (untuk mobile) -->
                <button onclick="toggleDropdown()" class="sm:hidden ml-2 text-gray-600 hover:text-green-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <!-- Dropdown Menu -->
                <div id="dropdownMenu" class="hidden sm:group-hover:block absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">
                    <div class="px-4 py-2 border-b border-gray-100 mb-1">
                        <p class="text-xs text-gray-400">Masuk sebagai</p>
                        <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($nama_user) ?></p>
                        <p class="text-[10px] text-green-600 font-semibold uppercase"><?= htmlspecialchars($role) ?></p>
                    </div>
                    
                    <a href="edit_profil.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                        <span>✏️</span> Edit Profil
                    </a>
                    <a href="alamat_saya.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                        <span>📍</span> Alamat Saya
                    </a>
                    
                    <div class="border-t border-gray-100 my-1"></div>
                    
                    <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                        <span>🚪</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- JavaScript untuk toggle dropdown di mobile -->
<script>
function toggleDropdown() {
    const dropdown = document.getElementById('dropdownMenu');
    dropdown.classList.toggle('hidden');
}

// Tutup dropdown kalau klik di luar
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('profileDropdown');
    const dropdownMenu = document.getElementById('dropdownMenu');
    
    if (dropdown && !dropdown.contains(event.target)) {
        dropdownMenu.classList.add('hidden');
    }
});
</script>