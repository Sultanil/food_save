<?php
// actions/set_default_alamat.php - Handle set alamat default
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

// ==================== HANDLE POST ====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Method tidak valid!');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    jsonResponse('error', 'ID tidak valid!');
}

try {
    // Validasi kepemilikan
    $stmt = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    if (!$stmt->fetch()) {
        jsonResponse('error', 'Alamat tidak ditemukan!');
    }
    
    // Reset semua default
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Set yang ini sebagai default
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    jsonResponse('success', 'Alamat default berhasil diubah!');
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>