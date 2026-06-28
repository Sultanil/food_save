<?php
// actions/delete_alamat.php - Handle hapus alamat
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
function jsonResponse($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ==================== HANDLE POST/GET ====================
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id <= 0) {
    jsonResponse('error', 'ID tidak valid!');
}

try {
    // Cek apakah alamat ini default
    $stmt = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $alamat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alamat) {
        jsonResponse('error', 'Alamat tidak ditemukan!');
    }
    
    // Hapus alamat
    $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    // Jika yang dihapus adalah default, set alamat lain sebagai default
    if ($alamat['is_default']) {
        $stmt = $pdo->prepare("
            UPDATE user_addresses 
            SET is_default = 1 
            WHERE user_id = ? 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
    }
    
    jsonResponse('success', 'Alamat berhasil dihapus!');
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>