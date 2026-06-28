<?php
// actions/update_profil.php - Handle update profil (nama + foto)
session_start();
require_once '../config/database.php';
require_once '../includes/session_check.php';

// Security check
if (!isset($_SESSION['sudah_login'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Akses tidak valid!']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;

// Helper untuk return JSON
function jsonResponse($status, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// ==================== HANDLE POST ====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Method tidak valid!');
}

$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');

// Validasi
if (empty($nama_lengkap)) {
    jsonResponse('error', 'Nama lengkap wajib diisi!');
}

try {
    // Ambil data user saat ini
    $stmt = $pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        jsonResponse('error', 'User tidak ditemukan!');
    }
    
    $foto_profil = $user['foto_profil'];
    
    // Handle upload foto profil
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_profil'];
        
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            jsonResponse('error', 'Format file tidak valid! Gunakan JPG, PNG, atau WEBP.');
        }
        
        if ($file['size'] > $max_size) {
            jsonResponse('error', 'Ukuran file terlalu besar! Maksimal 2MB.');
        }
        
        // Upload
        $target_dir = 'uploads/profil/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profil_' . $user_id . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Hapus foto lama jika ada
            if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])) {
                unlink($user['foto_profil']);
            }
            $foto_profil = $target_file;
        } else {
            jsonResponse('error', 'Gagal mengupload foto profil!');
        }
    }
    
    // Update database
    $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, foto_profil = ? WHERE id = ?");
    $stmt->execute([$nama_lengkap, $foto_profil, $user_id]);
    
    // Update session
    $_SESSION['nama'] = $nama_lengkap;
    $_SESSION['nama_lengkap'] = $nama_lengkap;
    
    jsonResponse('success', 'Profil berhasil diperbarui!', ['foto_profil' => $foto_profil]);
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>