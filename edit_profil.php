<?php
// edit_profil.php - Halaman edit profil user
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// Security check
if (!isset($_SESSION['sudah_login'])) {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: Index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- NAVBAR -->
    <?php include 'includes/navbar.php'; ?>

    <main class="max-w-4xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Edit Profil</h1>
            <p class="text-gray-600">Perbarui informasi profil Anda</p>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer" class="mb-6"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- FORM EDIT PROFIL -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Informasi Dasar</h2>

                <form id="profilForm" enctype="multipart/form-data" class="space-y-4">

                    <!-- Foto Profil -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Foto Profil</label>
                        <div class="flex items-center gap-4">
                            <div class="w-24 h-24 rounded-full bg-gray-200 overflow-hidden flex items-center justify-center text-4xl">
                                <?php if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])): ?>
                                    <img src="<?= htmlspecialchars($user['foto_profil']) ?>" alt="Foto Profil" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span>👤</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="foto_profil" id="foto_profil" accept="image/*" class="hidden" onchange="previewFoto(this)">
                                <label for="foto_profil" class="inline-block px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg cursor-pointer transition text-sm">
                                    📷 Ganti Foto
                                </label>
                                <p class="text-xs text-gray-500 mt-1">JPG, PNG, WEBP (Maks 2MB)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                               placeholder="Nama lengkap Anda">
                    </div>

                    <!-- Email (Read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly disabled
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                        <p class="text-xs text-gray-400 mt-1">Email tidak dapat diubah</p>
                    </div>

                    <!-- Username (Read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly disabled
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                        <p class="text-xs text-gray-400 mt-1">Username tidak dapat diubah</p>
                    </div>

                    <!-- Role (Read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <input type="text" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" readonly disabled
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                    </div>

                    <button type="submit" class="w-full py-3 bg-green-500 hover:bg-green-600 text-white font-bold rounded-lg transition shadow">
                         Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- MENU ALAMAT -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Alamat Pengiriman</h2>

                <div class="space-y-3">
                    <a href="alamat_saya.php" class="block p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition group">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl group-hover:scale-110 transition-transform">📍</span>
                            <div>
                                <h3 class="font-bold text-gray-900">Kelola Alamat</h3>
                                <p class="text-sm text-gray-600">Tambah, edit, atau hapus alamat pengiriman</p>
                            </div>
                            <span class="ml-auto text-gray-400 group-hover:translate-x-1 transition-transform">→</span>
                        </div>
                    </a>

                    <a href="ganti_password.php" class="block p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition group">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl group-hover:scale-110 transition-transform">🔐</span>
                            <div>
                                <h3 class="font-bold text-gray-900">Ganti Password</h3>
                                <p class="text-sm text-gray-600">Ubah password akun Anda</p>
                            </div>
                            <span class="ml-auto text-gray-400 group-hover:translate-x-1 transition-transform">→</span>
                        </div>
                    </a>

                    <a href="riwayat_pembelian.php" class="block p-4 bg-green-50 hover:bg-green-100 rounded-xl transition group">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl group-hover:scale-110 transition-transform">📦</span>
                            <div>
                                <h3 class="font-bold text-gray-900">Riwayat Pembelian</h3>
                                <p class="text-sm text-gray-600">Lihat semua pembelian Anda</p>
                            </div>
                            <span class="ml-auto text-gray-400 group-hover:translate-x-1 transition-transform">→</span>
                        </div>
                    </a>
                </div>
            </div>

        </div>

    </main>

    <!-- FOOTER -->
    <?php include 'includes/footer.php'; ?>

    <script>
        // Preview foto profil
        function previewFoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = input.closest('.flex').querySelector('img');
                    if (img) {
                        img.src = e.target.result;
                    } else {
                        const placeholder = input.closest('.flex').querySelector('span');
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.className = 'w-full h-full object-cover';
                        placeholder.parentNode.replaceChild(newImg, placeholder);
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Alert helper
        function showAlert(type, message) {
            const alertClass = type === 'success' 
                ? 'bg-green-50 border-green-200 text-green-700' 
                : 'bg-red-50 border-red-200 text-red-700';
            
            const icon = type === 'success' ? '✅' : '❌';
            
            const alertHTML = `
                <div class="p-4 ${alertClass} rounded-xl border-2">
                    ${icon} ${message}
                </div>
            `;
            
            document.getElementById('alertContainer').innerHTML = alertHTML;
            
            setTimeout(() => {
                document.getElementById('alertContainer').innerHTML = '';
            }, 5000);
        }

        // Handle form submit
        document.getElementById('profilForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Menyimpan...';
            
            fetch('actions/update_profil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                } else {
                    showAlert('error', data.message);
                }
                
                btn.disabled = false;
                btn.innerHTML = originalText;
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Terjadi kesalahan sistem!');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>

</body>
</html>