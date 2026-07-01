<?php
// actions/update_profil.php - Handle update profil (nama + foto)
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session_check.php';

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
        
        // ===== VALIDASI FILE (Lebih Aman) =====
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_ext)) {
            jsonResponse('error', 'Format file tidak valid! Gunakan JPG, PNG, atau WEBP.');
        }
        
        if ($file['size'] > $max_size) {
            jsonResponse('error', 'Ukuran file terlalu besar! Maksimal 2MB.');
        }
        
        // Validasi tambahan: pastikan file benar-benar gambar
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed_mime)) {
            jsonResponse('error', 'File bukan gambar yang valid!');
        }
        
        // ===== PATH UPLOAD (DIPERBAIKI!) =====
        // Path absolut untuk proses upload (di root project, BUKAN di actions/)
        $upload_dir_abs = __DIR__ . '/../uploads/profil/';
        
        // Buat folder kalau belum ada
        if (!file_exists($upload_dir_abs)) {
            if (!mkdir($upload_dir_abs, 0755, true)) {
                jsonResponse('error', 'Gagal membuat folder upload! Cek permission.');
            }
        }
        
        // Generate nama file unik
        $filename = 'profil_' . $user_id . '_' . time() . '.' . $ext;
        $target_file_abs = $upload_dir_abs . $filename;
        
        // Path RELATIVE untuk disimpan di database (dari root project)
        $foto_profil_db = 'uploads/profil/' . $filename;
        
        // Pindahkan file
        if (move_uploaded_file($file['tmp_name'], $target_file_abs)) {
            // Hapus foto lama jika ada (pakai path absolut!)
            if (!empty($user['foto_profil'])) {
                $old_file_abs = __DIR__ . '/../' . $user['foto_profil'];
                if (file_exists($old_file_abs)) {
                    @unlink($old_file_abs);
                }
            }
            // Update variabel untuk disimpan ke DB
            $foto_profil = $foto_profil_db;
        } else {
            jsonResponse('error', 'Gagal mengupload foto profil! Error code: ' . $file['error']);
        }
    }
    
    // Update database
    $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, foto_profil = ? WHERE id = ?");
    $stmt->execute([$nama_lengkap, $foto_profil, $user_id]);
    
    // Update session
    $_SESSION['nama'] = $nama_lengkap;
    $_SESSION['nama_lengkap'] = $nama_lengkap;
    if ($foto_profil !== $user['foto_profil']) {
        $_SESSION['foto_profil'] = $foto_profil;
    }
    
    jsonResponse('success', 'Profil berhasil diperbarui!', ['foto_profil' => $foto_profil]);
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>