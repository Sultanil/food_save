<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../fungsi_email.php';

// Keamanan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Akses tidak valid!"]);
    exit;
}
// Ambil input
$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$username = trim($_POST['nama'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$kode_pos = $_POST['kode_pos'] ?? '';
$role = $_POST['role'] ?? 'pembeli';

// Validasi - Tampilkan field mana yang kosong
$missing_fields = [];
if (empty($nama_lengkap)) $missing_fields[] = 'Nama Lengkap';
if (empty($username)) $missing_fields[] = 'Username';
if (empty($email)) $missing_fields[] = 'Email';
if (empty($password)) $missing_fields[] = 'Password';
if (empty($kode_pos)) $missing_fields[] = 'Kode Pos';

// ... sisa kode sama seperti sebelumnya ...

// Validasi
if (empty($nama_lengkap) || empty($username) || empty($email) || empty($password) || empty($kode_pos)) {
    echo json_encode(["status" => "error", "message" => "Semua kolom harus diisi!"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Format email tidak valid!"]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(["status" => "error", "message" => "Password minimal 6 karakter!"]);
    exit;
}

try {
    // Cek email sudah terdaftar & terverifikasi
    $cek = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
    $cek->execute([$email]);
    $akun = $cek->fetch();

    if ($akun && $akun['is_verified'] == 1) {
        echo json_encode(["status" => "error", "message" => "Email sudah terdaftar! Silakan login."]);
        exit;
    }

    // Generate OTP
    $kode_otp = random_int(100000, 999999);
    $expired = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Simpan ke database
    if ($akun) {
        // Update jika pernah daftar tapi belum verifikasi
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, username=?, password=?, kode_pos=?, role=?, kode_otp=?, expired_otp=? WHERE email=?");
        $stmt->execute([$nama_lengkap, $username, $password_hash, $kode_pos, $role, $kode_otp, $expired, $email]);
        $id_user = $akun['id'];
    } else {
        // Insert user baru
        $stmt = $pdo->prepare("INSERT INTO users (nama_lengkap, username, email, password, kode_pos, role, kode_otp, expired_otp, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$nama_lengkap, $username, $email, $password_hash, $kode_pos, $role, $kode_otp, $expired]);
        $id_user = $pdo->lastInsertId();
    }

    // Kirim email verifikasi
    $kirim = kirimEmailVerifikasi($email, $nama_lengkap, $kode_otp);

    if ($kirim === true) {
        $_SESSION['pending_user_id'] = $id_user;
        echo json_encode([
            "status" => "success",
            "message" => "Pendaftaran berhasil! Kode verifikasi telah dikirim ke email Anda."
        ]);
    } else {
        // Hapus user jika gagal kirim email
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id_user]);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal mengirim email verifikasi. Silakan coba lagi."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Error database: " . $e->getMessage()
    ]);
}

exit;
