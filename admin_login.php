<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['sudah_login']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboardAdmin.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, username, nama_lengkap, email, password, role FROM users WHERE email = ? AND role = 'admin' AND is_deleted = 0");
    $stmt->execute([$email]);
    $result = $stmt->fetch();

    if ($result) {
        $user = $result;

        if (password_verify($password, $user['password'])) {
            $_SESSION['sudah_login'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            header("Location: dashboardAdmin.php");
            exit;
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Email admin tidak terdaftar!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-2xl font-bold mb-2 text-center text-green-600">🔐 Admin FoodSave</h2>
        <p class="text-gray-500 text-center mb-6 text-sm">Silakan login untuk mengelola sistem</p>
        
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email Admin</label>
                <input type="email" name="email" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="adminfoodsave@gmail.com">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                <input type="password" name="password" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="••••••••">
            </div>

            <button type="submit" 
                    class="w-full py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 transition">
                Login
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500">
            <a href="Index.php" class="text-green-600 hover:underline">← Kembali ke Beranda</a>
        </p>
    </div>
</body>
</html>