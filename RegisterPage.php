<?php
// RegisterPage.php - Halaman register (PUBLIK, tidak butuh login)
require_once 'config/database.php';

// Mulai session jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah sudah login, kalau iya redirect
if (isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true) {
    header("Location: Index.php");
    exit;
}

// Set variabel untuk header
$site_name = "FoodSave";
$page_title = "Daftar";
$description = "Daftar akun FoodSave untuk mulai bertransaksi makanan surplus";
$is_logged_in = false; // Halaman publik
$username = '';
$cart_badge_count = 0;

// Ambil error dari session jika ada
$error = $_SESSION['register_error'] ?? '';
if (isset($_GET['error'])) {
    unset($_SESSION['register_error']); // Hapus setelah ditampilkan
}

// Ambil list kode pos untuk dropdown
$kode_pos_list = mysqli_query($conn, "SELECT kode_pos, kecamatan, kelurahan FROM kode_pos ORDER BY kecamatan, kelurahan");

// Cek error query
if (!$kode_pos_list) {
    die("Error query kode_pos: " . mysqli_error($conn));
}

// Role yang dipilih (default: pembeli)
$selected_role = isset($_GET['role']) && $_GET['role'] === 'penjual' ? 'penjual' : 'pembeli';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($site_name) ?></title>
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
            <h1 class="text-[#4CAF50] text-4xl font-bold mb-2"><?= htmlspecialchars($site_name) ?></h1>
            <p class="text-gray-600 text-sm">Platform jual beli makanan surplus yang berkelanjutan</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white p-8 rounded-2xl shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">Buat Akun Baru</h2>
            <p class="text-gray-500 text-sm mb-6">Daftar untuk mulai menggunakan <?= htmlspecialchars($site_name) ?></p>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-5 text-center border-l-4 border-red-600">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Form action diarahkan ke actions/proses_register.php -->
            <form method="POST" action="actions/proses_register.php">
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

                <!-- Kode Pos Dropdown -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Kode Pos</label>
                    <select name="kode_pos" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#4CAF50]">
                        <option value="">Pilih Kecamatan & Kelurahan</option>
                        <?php while ($kp = mysqli_fetch_assoc($kode_pos_list)): ?>
                            <option value="<?= htmlspecialchars($kp['kode_pos']) ?>">
                                <?= htmlspecialchars($kp['kecamatan']) ?> - <?= htmlspecialchars($kp['kelurahan']) ?> (<?= $kp['kode_pos'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
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
                            <input type="radio" name="role" value="pembeli" <?= $selected_role === 'pembeli' ? 'checked' : '' ?>>
                            <div class="role-card-inner border-2 border-gray-300 rounded-lg p-4 text-center input-transition">
                                <h3 class="font-semibold text-gray-800 mb-1">Pembeli</h3>
                                <p class="text-xs text-gray-500">Cari dan beli makanan surplus</p>
                            </div>
                        </label>

                        <!-- Role: Penjual -->
                        <label class="role-card-wrapper cursor-pointer">
                            <input type="radio" name="role" value="penjual" <?= $selected_role === 'penjual' ? 'checked' : '' ?>>
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
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?> - Tugas Semester 2</p>
        </footer>
    </div>

</body>

</html>