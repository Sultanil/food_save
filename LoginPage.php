<?php
session_start();
include 'koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        // ✅ QUERY: Tambah kode_pos di SELECT
        $stmt = $conn->prepare("SELECT id, username, nama_lengkap, email, password, role, kode_pos FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // ✅ SET SESSION - SEMUA LENGKAP
                $_SESSION['sudah_login'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama_lengkap'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['kode_pos'] = $user['kode_pos']; // ✅ Kode pos dari database

                // ✅ Redirect berdasarkan role
                switch ($user['role']) {
                    case 'admin': $dest = 'dashboardAdmin.php'; break;
                    case 'penjual': $dest = 'dashboardPenjual.php'; break;
                    default: $dest = 'Index.php';
                }
                header("Location: $dest");
                exit;
            } else {
                $error = 'Password salah.';
            }
        } else {
            $error = 'Email tidak terdaftar.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FoodSave</title>
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
            <h1 class="text-[#4CAF50] text-4xl font-bold mb-2">FoodSave</h1>
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

            <form method="POST" action="">
                <!-- Email Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Email</label>
                    <input type="email" name="email" placeholder="nama@email.com" required
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
            <p>&copy; 2026 FoodSave - Tugas Semester 2</p>
        </footer>
    </div>

</body>

</html>