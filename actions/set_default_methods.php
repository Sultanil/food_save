<?php
// actions/set_default_payment.php - Handle set default payment method
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

// ==================== HANDLE POST ====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Method tidak valid!');
}

$payment_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$payment_type = $_POST['payment_type'] ?? '';

if ($payment_id <= 0 || !in_array($payment_type, ['bank_transfer', 'qris'])) {
    jsonResponse('error', 'Data tidak valid!');
}

try {
    $result = setDefaultPayment($pdo, $penjual_id, $payment_id, $payment_type);
    
    if ($result) {
        jsonResponse('success', 'Metode pembayaran default berhasil diubah!');
    } else {
        jsonResponse('error', 'Gagal mengubah metode pembayaran default!');
    }
} catch (PDOException $e) {
    jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
}
?>