<?php
// actions/save_alamat.php - Handle tambah/edit alamat
session_start();
require_once '../config/database.php';
require_once '../includes/session_check.php';

// Security check
if (!isset($_SESSION['sudah_login']) || !in_array($_SESSION['role'] ?? '', ['pembeli', 'penjual'])) {
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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nama_penerima = trim($_POST['nama_penerima'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$alamat_lengkap = trim($_POST['alamat_lengkap'] ?? '');
$kode_pos = trim($_POST['kode_pos'] ?? '');
$is_default = isset($_POST['is_default']) ? 1 : 0;

// Validasi
if (empty($nama_penerima) || empty($telepon) || empty($alamat_lengkap) || empty($kode_pos)) {
    jsonResponse('error', 'Semua field wajib diisi!');
}

try {
    // Jika set default, reset semua default yang lain
    if ($is_default) {
        $reset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $reset->execute([$user_id]);
    }

    if ($id > 0) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE user_addresses 
            SET nama_penerima = ?, telepon = ?, alamat_lengkap = ?, kode_pos = ?, is_default = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$nama_penerima, $telepon, $alamat_lengkap, $kode_pos, $is_default, $id, $user_id]);
        jsonResponse('success', 'Alamat berhasil diperbarui!');
    } else {
        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO user_addresses 
            (user_id, nama_penerima, telepon, alamat_lengkap, kode_pos, is_default)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $nama_penerima, $telepon, $alamat_lengkap, $kode_pos, $is_default]);
        jsonResponse('success', 'Alamat berhasil ditambahkan!', ['id' => $pdo->lastInsertId()]);
    }
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>