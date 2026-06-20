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
$kode_pos_list = $pdo->query("SELECT kode_pos, kecamatan, kelurahan FROM kode_pos ORDER BY kecamatan, kelurahan");

// Cek error query
if (!$kode_pos_list) {
    die("Error query kode_pos: " . $pdo->errorInfo()[2]);
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
            <form method="POST" id="registerForm">
                <!-- Nama Lengkap Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" placeholder="Nama Lengkap Anda" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-sm input-transition focus:outline-none focus:border-[#4CAF50]">
                </div>

                <!-- Username Input -->
                <div class="mb-5">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm">Username</label>
                    <input type="text" name="nama" placeholder="Pilih username" required
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
                        <?php while ($kp = $kode_pos_list->fetch()): ?>
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

                <!-- Success/Error Message Container -->
                <div id="message" class="hidden p-3 rounded-lg mb-4 text-center text-sm"></div>

                <!-- Submit Button -->
                <button type="submit" id="btnSubmit" name="submit"
                    class="w-full py-3.5 bg-[#4CAF50] hover:bg-[#43a047] text-white font-semibold rounded-lg cursor-pointer transition-colors duration-300 mb-5">
                    Daftar
                </button>
            </form>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                $('#registerForm').on('submit', function(e) {
                    e.preventDefault(); // Cegah form submit biasa

                    const btn = $('#btnSubmit');
                    const message = $('#message');

                    // Disable button & show loading
                    btn.prop('disabled', true)
                        .removeClass('bg-[#4CAF50] hover:bg-[#43a047]')
                        .addClass('bg-gray-400 cursor-not-allowed')
                        .html('<svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...');
                    message.hide().removeClass('bg-red-50 text-red-700 bg-green-50 text-green-700');

                    $.ajax({
                        url: 'actions/proses_register.php', // Path ke file proses
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                // Tampilkan pesan sukses
                                message.removeClass('hidden bg-red-50 text-red-700')
                                    .addClass('bg-green-50 text-green-700')
                                    .text(response.message)
                                    .show();

                                // Redirect ke halaman verifikasi setelah 2 detik
                                setTimeout(function() {
                                    window.location.href = 'verifikasi_page.php';
                                }, 2000);
                            } else {
                                // Tampilkan error
                                message.removeClass('hidden bg-green-50 text-green-700')
                                    .addClass('bg-red-50 text-red-700')
                                    .text(response.message)
                                    .show();

                                // Enable button kembali
                                btn.prop('disabled', false)
                                    .removeClass('bg-gray-400 cursor-not-allowed')
                                    .addClass('bg-[#4CAF50] hover:bg-[#43a047]')
                                    .text('Daftar');
                            }
                        },
                        error: function(xhr, status, error) {
                            message.removeClass('hidden bg-green-50 text-green-700')
                                .addClass('bg-red-50 text-red-700')
                                .text('Terjadi kesalahan sistem. Silakan coba lagi.')
                                .show();
                            console.log('Error:', xhr.responseText);

                            // Enable button kembali
                            btn.prop('disabled', false)
                                .removeClass('bg-gray-400 cursor-not-allowed')
                                .addClass('bg-[#4CAF50] hover:bg-[#43a047]')
                                .text('Daftar');
                        }
                    });
                });
            </script>

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