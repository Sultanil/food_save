<?php
// actions/delete_payment_method.php - Handle hapus payment method
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
    $result = deletePaymentMethod($pdo, $id, $penjual_id);
    
    if ($result) {
        // Cek apakah AJAX
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($is_ajax) {
            jsonResponse('success', 'Metode pembayaran berhasil dihapus!');
        } else {
            header('Location: ../setup_payment.php?msg=deleted');
            exit;
        }
    } else {
        jsonResponse('error', 'Gagal menghapus metode pembayaran!');
    }
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>