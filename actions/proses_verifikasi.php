<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Akses tidak valid!"]);
    exit;
}

if (!isset($_SESSION['pending_user_id'])) {
    echo json_encode(["status" => "error", "message" => "Sesi tidak ditemukan. Silakan daftar ulang."]);
    exit;
}

$id = $_SESSION['pending_user_id'];
$otp_input = trim($_POST['kode_otp'] ?? '');

if (empty($otp_input)) {
    echo json_encode(["status" => "error", "message" => "Kode OTP harus diisi!"]);
    exit;
}

try {
    // Ambil data OTP + data user lengkap
    $stmt = $pdo->prepare("SELECT id, username, email, role, kode_otp, expired_otp FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user || !$user['kode_otp']) {
        echo json_encode(["status" => "error", "message" => "Kode OTP tidak ditemukan."]);
        exit;
    }

    if (strtotime(date('Y-m-d H:i:s')) > strtotime($user['expired_otp'])) {
        echo json_encode(["status" => "error", "message" => "Kode OTP sudah kedaluwarsa. Silakan daftar ulang."]);
        exit;
    }

    if ($otp_input == $user['kode_otp']) {
        // Update status verifikasi
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, kode_otp = NULL, expired_otp = NULL WHERE id = ?");
        $update->execute([$id]);
        
        // Hapus session pending
        unset($_SESSION['pending_user_id']);
        
        // Simpan semua data user ke session
        $_SESSION['id_user'] = $id;
        $_SESSION['user_id'] = $id; // Untuk kompatibilitas dengan Index.php
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama'] = $user['username']; // Untuk kompatibilitas dengan Index.php
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role']; // INI YANG PENTING!
        $_SESSION['sudah_login'] = true;
        
        echo json_encode(["status" => "success", "message" => "Verifikasi berhasil! Selamat datang di FoodSave."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Kode OTP salah!"]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error database: " . $e->getMessage()]);
}

exit;
?>