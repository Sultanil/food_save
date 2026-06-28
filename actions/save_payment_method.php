<?php
// actions/save_payment_method.php - Handle tambah/edit payment method
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

// Cek apakah AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Helper untuk return JSON
function jsonResponse($status, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// ==================== HANDLE POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $payment_type = $_POST['payment_type'] ?? '';
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_holder = trim($_POST['account_holder'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    // Validasi payment_type
    if (!in_array($payment_type, ['bank_transfer', 'qris'])) {
        jsonResponse('error', 'Tipe pembayaran tidak valid!');
    }

    // Validasi berdasarkan tipe
    if ($payment_type === 'bank_transfer') {
        if (empty($bank_name) || empty($account_number) || empty($account_holder)) {
            jsonResponse('error', 'Bank, nomor rekening, dan atas nama wajib diisi!');
        }
    } elseif ($payment_type === 'qris') {
        // QRIS wajib upload gambar (kecuali edit tanpa ganti gambar)
        if ($id === 0 && (!isset($_FILES['qris_image']) || $_FILES['qris_image']['error'] === UPLOAD_ERR_NO_FILE)) {
            jsonResponse('error', 'QRIS code wajib diupload!');
        }
    }

    // Handle upload QRIS image
    $qris_image = null;
    if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qris_image'];
        
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
        $target_dir = '../uploads/qris/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'qris_' . $penjual_id . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $qris_image = 'uploads/qris/' . $filename;
        } else {
            jsonResponse('error', 'Gagal mengupload gambar QRIS!');
        }
    }

    try {
        if ($id > 0) {
            // UPDATE
            $data = [
                'payment_type' => $payment_type,
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'account_holder' => $account_holder,
                'is_default' => $is_default
            ];
            
            if ($qris_image) {
                $data['qris_image'] = $qris_image;
            }
            
            $result = updatePaymentMethod($pdo, $id, $penjual_id, $data);
            
            if ($result) {
                jsonResponse('success', 'Metode pembayaran berhasil diperbarui!');
            } else {
                jsonResponse('error', 'Gagal memperbarui metode pembayaran!');
            }
        } else {
            // INSERT
            $data = [
                'payment_type' => $payment_type,
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'account_holder' => $account_holder,
                'qris_image' => $qris_image,
                'is_default' => $is_default
            ];
            
            $new_id = addPaymentMethod($pdo, $penjual_id, $data);
            
            if ($new_id) {
                jsonResponse('success', 'Metode pembayaran berhasil ditambahkan!', ['id' => $new_id]);
            } else {
                jsonResponse('error', 'Gagal menambahkan metode pembayaran!');
            }
        }
    } catch (PDOException $e) {
        jsonResponse('error', 'Terjadi kesalahan database: ' . $e->getMessage());
    }
}

// Jika bukan POST, redirect
header('Location: ../setup_payment.php');
exit;
?>