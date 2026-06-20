<?php
session_start();

// 1. WAJIB: Set header JSON untuk AJAX
header('Content-Type: application/json');

// 2. Include database
include 'database.php';

// 3. Keamanan: Cegah akses langsung via browser
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Akses tidak valid!"]);
    exit;
}

// 4. Validasi: Cek session ada atau tidak
if (!isset($_SESSION['pending_user_id'])) {
    echo json_encode([
        "status" => "error", 
        "message" => "Sesi pendaftaran tidak ditemukan. Silakan daftar ulang."
    ]);
    exit;
}

// 5. Ambil input dengan aman
$id = $_SESSION['pending_user_id'];
$otp_input = trim($_POST['kode_otp'] ?? '');

// 6. Validasi input OTP
if (empty($otp_input)) {
    echo json_encode([
        "status" => "error", 
        "message" => "Kode OTP harus diisi!"
    ]);
    exit;
}

try {
    // 7. Ambil data OTP dari database
    $stmt = $pdo->prepare("SELECT kode_otp, expired_otp FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    // 8. Cek apakah OTP ada
    if (!$user || !$user['kode_otp']) {
        echo json_encode([
            "status" => "error", 
            "message" => "Kode OTP tidak ditemukan. Silakan daftar ulang."
        ]);
        exit;
    }

    // 9. Cek apakah sudah expired
    if (strtotime(date('Y-m-d H:i:s')) > strtotime($user['expired_otp'])) {
        echo json_encode([
            "status" => "error", 
            "message" => "Kode OTP sudah kedaluwarsa. Silakan minta kode baru."
        ]);
        exit;
    }

    // 10. Cek apakah kode cocok
    if ($otp_input == $user['kode_otp']) {
        // SUKSES! Update status jadi terverifikasi dan hapus kode OTP
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, kode_otp = NULL, expired_otp = NULL WHERE id = ?");
        $update->execute([$id]);
        
        // Hapus session pending, buat session login
        unset($_SESSION['pending_user_id']);
        $_SESSION['id_user'] = $id;
        $_SESSION['username'] = $user['username'] ?? ''; // Optional: simpan username
        $_SESSION['login'] = true;
        
        echo json_encode([
            "status" => "success", 
            "message" => "Verifikasi berhasil! Selamat datang."
        ]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Kode OTP salah! Silakan coba lagi."
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error", 
        "message" => "Terjadi kesalahan database: " . $e->getMessage()
    ]);
}

// 11. WAJIB: Hentikan script
exit;
?>