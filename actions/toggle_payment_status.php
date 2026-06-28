<?php
// actions/toggle_payment_status.php - Handle toggle active status
session_start();
require_once '../config/database.php';
require_once '../includes/session_check.php';
require_once '../includes/payment_methods.php';

// Security check
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'penjual') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Akses tidak valid!']);
    exit;
}

// Ambil data penjual
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$stmt = $pdo->prepare("SELECT id FROM penjual WHERE user_id = ?");
$stmt->execute([$user_id]);
$penjual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$penjual) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Data toko tidak ditemukan!']);
    exit;
}

$penjual_id = $penjual['id'];

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

if ($id <= 0) {
    jsonResponse('error', 'ID tidak valid!');
}

try {
    $result = togglePaymentStatus($pdo, $id, $penjual_id);
    
    if ($result) {
        // Ambil status baru
        $stmt = $pdo->prepare("SELECT is_active FROM seller_payment_methods WHERE id = ?");
        $stmt->execute([$id]);
        $new_status = $stmt->fetchColumn();
        
        jsonResponse('success', 'Status berhasil diubah!', ['is_active' => $new_status]);
    } else {
        jsonResponse('error', 'Gagal mengubah status!');
    }
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>