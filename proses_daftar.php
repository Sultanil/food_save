<?php
session_start();

// 1. WAJIB: Set header JSON untuk AJAX
header('Content-Type: application/json');

// 2. Include file
include 'database.php'; 
include 'fungsi_email.php'; 

// 3. Keamanan: Cegah akses langsung via browser
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Akses tidak valid!"]);
    exit;
}

// 4. Ambil input
$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$username = trim($_POST['nama'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// 5. Validasi: Pastikan tidak ada kolom kosong
if (empty($nama_lengkap) || empty($username) || empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Semua kolom (Nama Lengkap, Username, Email, Password) harus diisi!"]);
    exit;
}

// 6. Validasi format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Format email tidak valid!"]);
    exit;
}

try {
    // 7. Cek apakah email sudah terdaftar dan SUDAH terverifikasi
    $cek = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
    $cek->execute([$email]);
    $akun = $cek->fetch();

    if ($akun && $akun['is_verified'] == 1) {
        echo json_encode(["status" => "error", "message" => "Email sudah terdaftar dan terverifikasi! Silakan login."]);
        exit;
    }

    // 8. Generate Kode OTP & Hash Password (DILAKUKAN DULU sebelum query)
    $kode_otp = random_int(100000, 999999); 
    $expired = date('Y-m-d H:i:s', strtotime('+10 minutes')); 
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // 9. Simpan / Update ke Database
    if ($akun) {
        // Jika email pernah daftar tapi belum verifikasi, update datanya
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, username=?, password=?, kode_otp=?, expired_otp=? WHERE email=?");
        $stmt->execute([$nama_lengkap, $username, $password_hash, $kode_otp, $expired, $email]);
        $id_user = $akun['id'];
    } else {
        // Jika benar-benar baru, INSERT
        $stmt = $pdo->prepare("INSERT INTO users (nama_lengkap, username, email, password, kode_otp, expired_otp, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$nama_lengkap, $username, $email, $password_hash, $kode_otp, $expired]);
        $id_user = $pdo->lastInsertId();
    }

    // 10. Kirim Email
    $kirim = kirimEmailVerifikasi($email, $username, $kode_otp);

    // 11. Kirim Respons ke AJAX
    if ($kirim === true) {
        $_SESSION['pending_user_id'] = $id_user;
        echo json_encode([
            "status" => "success", 
            "message" => "Pendaftaran berhasil! Silakan cek email $email untuk kode OTP."
        ]);
    } else {
        // Jika gagal kirim email, hapus user dari DB
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id_user]);
        echo json_encode([
            "status" => "error", 
            "message" => "Gagal mengirim email verifikasi. Error: " . $kirim
        ]);
    }

} catch (PDOException $e) {
    // Tangani jika ada error database
    echo json_encode([
        "status" => "error", 
        "message" => "Terjadi kesalahan database: " . $e->getMessage()
    ]);
}

// 12. WAJIB: Hentikan script setelah mengirim JSON
exit; 
?>