<?php
session_start();
include 'koneksi.php';

$error = '';
$success = '';

if (isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($conn, $_POST['nama']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    // Validasi
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Cek email sudah ada?
        $cek = "SELECT * FROM users WHERE Email = '$email'";
        $result = mysqli_query($conn, $cek);
        
        if (mysqli_num_rows($result) > 0) {
            $error = 'Email sudah terdaftar!';
        } else {
            // Hash password (enkripsi)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Simpan ke database (sesuai struktur tabel)
            $query = "INSERT INTO users (UserName, NamaLengkap, Email, Password, Role) 
                      VALUES ('$username', '$nama_lengkap', '$email', '$hashed_password', '$role')";
            
            if (mysqli_query($conn, $query)) {
                // Auto login setelah register
                $_SESSION['sudah_login'] = true;
                $_SESSION['nama'] = $nama_lengkap;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                
                header("Location: Index.php");
                exit();
                
            } else {
                $error = 'Registrasi gagal: ' . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        /* Custom gradient background */
        .bg-gradient-foodsave {
            background: linear-gradient(135deg, #f0f9f0 0%, #e8f5e9 100%);
        }
        /* Smooth transition untuk input focus */
        .input-transition {
            transition: all 0.3s ease;
        }
        /* Style untuk role card: hide radio button */
        .role-card input[type="radio"] {
            display: none;
        }
        /* Style saat role card di-hover */
        .role-card-wrapper:hover .role-card-inner {
            border-color: #4CAF50;
            background-color: #f0f9f0;
        }
        /* Style saat role card dipilih (checked) */
        .role-card-wrapper:has(input:checked) .role-card-inner {
            border-color: #4CAF50;
            background-color: #f0f9f0;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">Buat Akun Baru</h2>
            <p class="text-gray-500 text-sm mb-6">Daftar untuk mulai menggunakan FoodSave</p>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-5 text-center border-l-4 border-red-600">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Nama Lengkap Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Nama Anda" required 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm input-transition focus:outline-none focus:border-[#4CAF50]">
                </div>

                <!-- Email Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Email</label>
                    <input type="email" name="email" placeholder="nama@email.com" required 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm input-transition focus:outline-none focus:border-[#4CAF50]">
                </div>

                <!-- Password Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Password</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm input-transition focus:outline-none focus:border-[#4CAF50]">
                </div>

                <!-- Role Selector -->
                <div class="mb-6">
                    <label class="block mb-3 font-semibold text-gray-800 text-sm">Daftar Sebagai</label>
                    <div class="grid grid-cols-2 gap-3">
                        
                        <!-- Role: Pembeli -->
                        <label class="role-card-wrapper cursor-pointer">
                            <input type="radio" name="role" value="pembeli" checked>
                            <div class="role-card-inner border-2 border-gray-300 rounded-lg p-4 text-center input-transition">
                                <h3 class="font-semibold text-gray-800 mb-1">Pembeli</h3>
                                <p class="text-xs text-gray-500">Cari dan beli makanan surplus</p>
                            </div>
                        </label>

                        <!-- Role: Penjual -->
                        <label class="role-card-wrapper cursor-pointer">
                            <input type="radio" name="role" value="penjual">
                            <div class="role-card-inner border-2 border-gray-300 rounded-lg p-4 text-center input-transition">
                                <h3 class="font-semibold text-gray-800 mb-1">Penjual</h3>
                                <p class="text-xs text-gray-500">Jual makanan surplus</p>
                            </div>
                        </label>

                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="submit" 
                        class="w-full py-3.5 bg-[#4CAF50] hover:bg-[#43a047] text-white font-semibold rounded-lg cursor-pointer transition-colors duration-300 mb-5">
                    Daftar
                </button>
            </form>

            <!-- Login Link -->
            <p class="text-center text-sm text-gray-600">
                Sudah punya akun? 
                <a href="LoginPage.php" class="text-[#4CAF50] font-semibold hover:underline">Masuk di sini</a>
            </p>
        </div>

        <!-- Footer -->
        <footer class="text-center text-gray-600 text-sm py-5 w-full">
            <p>&copy; 2026 FoodSave - Tugas Semester 2</p>
        </footer>
    </div>

</body>
</html>