<?php
// actions/proses_login.php - Logika login

// 1. Mulai session DI PALING AWAL
session_start();
global $conn;

// 2. Include database
require_once '../config/database.php';

// 3. Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../LoginPage.php");
    exit;
}

// 4. Ambil & sanitasi input
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$error = '';

// 5. Validasi
if (empty($email) || empty($password)) {
    $error = 'Email dan password wajib diisi.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Format email tidak valid.';
} else {
    // 6. Query ke database dengan prepared statement
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap, email, password, role, kode_pos FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // 7. Cek apakah user ditemukan
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 8. Verifikasi password
        if (password_verify($password, $user['password'])) {
            // 9. Set session - SEMUA LENGKAP
            $_SESSION['sudah_login'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['kode_pos'] = $user['kode_pos'];
            $_SESSION['last_activity'] = time(); // Penting untuk auto-logout

            // 10. Redirect berdasarkan role
            switch ($user['role']) {
                case 'admin':
                    $dest = '../dashboardAdmin.php';
                    break;
                case 'penjual':
                    $dest = '../dashboardPenjual.php';
                    break;
                default: // pembeli
                    $dest = '../Index.php';
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

// 11. Jika ada error, simpan di session dan redirect kembali
if (!empty($error)) {
    $_SESSION['login_error'] = $error;
    $_SESSION['login_old_email'] = $email; // Simpan email agar tidak hilang
}

// 12. Redirect kembali ke login page
header("Location: ../LoginPage.php");
exit;
?>