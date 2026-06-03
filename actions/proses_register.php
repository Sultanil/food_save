<?php
// actions/proses_register.php - Logika register

// 1. Mulai session DI PALING AWAL
session_start();

// 2. Include database
require_once '../config/database.php';
global $conn;

// 3. Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    // Jika bukan POST, redirect ke register page
    header("Location: ../RegisterPage.php");
    exit;
}

// 4. Ambil & sanitasi input
$username = trim($_POST['nama'] ?? '');
$nama_lengkap = trim($_POST['nama'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'pembeli';
$kode_pos = trim($_POST['kode_pos'] ?? '');

$error = '';

// 5. Validasi
if (empty($username) || empty($email) || empty($password)) {
    $error = 'Semua field harus diisi!';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Format email tidak valid!';
} elseif (strlen($password) < 6) {
    $error = 'Password minimal 6 karakter!';
} elseif (empty($kode_pos)) {
    $error = 'Pilih kode pos terlebih dahulu!';
} elseif (!in_array($role, ['pembeli', 'penjual'])) {
    $error = 'Role tidak valid!';
} else {
    // 6. Cek email sudah ada?
    $stmt_cek = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_cek->bind_param("s", $email);
    $stmt_cek->execute();
    $result = $stmt_cek->get_result();

    if ($result->num_rows > 0) {
        $error = 'Email sudah terdaftar!';
    } else {
        // 7. Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 8. Insert ke database dengan prepared statement
        $stmt_insert = $conn->prepare("
            INSERT INTO users (username, nama_lengkap, email, password, role, kode_pos) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->bind_param("ssssss", $username, $nama_lengkap, $email, $hashed_password, $role, $kode_pos);

        if ($stmt_insert->execute()) {
            // 9. Auto login setelah register berhasil
            $user_id = $conn->insert_id;
            
            $_SESSION['sudah_login'] = true;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['nama'] = $nama_lengkap;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['kode_pos'] = $kode_pos;
            $_SESSION['last_activity'] = time();

            // 10. Redirect berdasarkan role
            switch ($role) {
                case 'penjual':
                    $dest = '../dashboardPenjual.php';
                    break;
                case 'admin':
                    $dest = '../dashboardAdmin.php';
                    break;
                default: // pembeli
                    $dest = '../Index.php';
            }

            header("Location: $dest");
            exit;
        } else {
            $error = "Gagal mendaftar: " . $conn->error;
        }
    }
}

// 11. Jika ada error, simpan di session dan redirect kembali
if (!empty($error)) {
    $_SESSION['register_error'] = $error;
    $_SESSION['register_old_input'] = [
        'nama' => $username,
        'email' => $email,
        'role' => $role,
        'kode_pos' => $kode_pos
    ];
}

// 12. Redirect kembali ke register page
header("Location: ../RegisterPage.php");
exit;
?>