<?php
// LoginPage.php - Halaman login (PUBLIK)
require_once 'config/database.php';

// Mulai session jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah sudah login, kalau iya redirect
if (isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true) {
    // Redirect berdasarkan role
    switch ($_SESSION['role'] ?? 'pembeli') {
        case 'admin':
            header("Location: dashboardAdmin.php");
            break;
        case 'penjual':
            header("Location: dashboardPenjual.php");
            break;
        default:
            header("Location: Index.php");
    }
    exit;
}

// Set variabel untuk halaman
$site_name = "FoodSave";
$page_title = "Login";
$description = "Masuk ke akun FoodSave Anda";

// ✅ Ambil error dari session (jika ada)
$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Hapus setelah ditampilkan
}

// ✅ Ambil old email untuk mengisi form kembali
$old_email = $_SESSION['login_old_email'] ?? '';
unset($_SESSION['login_old_email']);

// Ambil pesan dari URL (jika ada)
$pesan = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'session_expired':
            $pesan = '⏰ Session kamu telah habis karena tidak aktif selama 5 menit. Silakan login kembali.';
            break;
        case 'login_required':
            $pesan = '🔒 Kamu harus login terlebih dahulu.';
            break;
        case 'logged_out':
            $pesan = '👋 Kamu telah berhasil logout.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($site_name) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .bg-gradient-foodsave {
            background: linear-gradient(135deg, #f0f9f0 0%, #e8f5e9 100%);
        }

        .input-focus {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body class="bg-gradient-foodsave min-h-screen flex flex-col justify-center items-center p-4">

    <div class="w-full max-w-md mb-5">
        <!-- Brand -->
        <div class="text-center mb-8">
            <h1 class="text-[#4CAF50] text-4xl font-bold mb-2"><?= htmlspecialchars($site_name) ?></h1>
            <p class="text-gray-600 text-sm">Platform jual beli makanan surplus yang berkelanjutan</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white p-8 rounded-2xl shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">Masuk ke Akun Anda</h2>
            <p class="text-gray-500 text-sm mb-6">Masukkan email dan password untuk melanjutkan</p>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-5 text-center border-l-4 border-red-600">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Pesan dari URL (session expired, dll) -->
            <?php if ($pesan): ?>
                <div class="bg-blue-50 text-blue-700 p-3 rounded-lg mb-5 text-center border-l-4 border-blue-600">
                    <?php echo htmlspecialchars($pesan); ?>
                </div>
            <?php endif; ?>

            <!-- Form action diarahkan ke actions/proses_login.php -->
            <form method="POST" action="actions/proses_login.php">
                <!-- Email Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($old_email) ?>" placeholder="nama@email.com" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm input-focus focus:outline-none focus:border-[#4CAF50]">
                </div>

                <!-- Password Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Password</label>
                    <input type="password" name="password" placeholder="Masukkan password" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm input-focus focus:outline-none focus:border-[#4CAF50]">
                </div>

                <!-- Submit Button -->
                <button type="submit" name="submit"
                    class="w-full py-3.5 bg-[#4CAF50] hover:bg-[#43a047] text-white font-semibold rounded-lg cursor-pointer transition-colors duration-300 mb-5">
                    Masuk
                </button>
            </form>

            <!-- Signup Link -->
            <p class="text-center text-sm text-gray-600 mb-4">
                Belum punya akun?
                <a href="RegisterPage.php" class="text-[#4CAF50] font-semibold hover:underline">Daftar di sini</a>
            </p>

            <!-- Demo Text -->
            <p class="text-center text-xs text-gray-500 pt-4 border-t border-gray-200">
                Data tersimpan di database MySQL
            </p>
        </div>

        <!-- Footer -->
        <footer class="text-center text-gray-600 text-sm py-5 w-full">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?> - Tugas Semester 2</p>
        </footer>
    </div>

</body>

</html>