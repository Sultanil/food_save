<?php
// tambah_ke_keranjang.php
session_start();
include 'koneksi.php';
header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Silakan login sebagai pembeli']);
    exit;
}

$user_id = $_SESSION['user_id'];
$produk_id = isset($_POST['produk_id']) ? (int)$_POST['produk_id'] : 0;
$qty = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;

if (!$produk_id) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak valid']);
    exit;
}

// Cek stok & status produk
$stmt = $conn->prepare("SELECT stok, status FROM produk WHERE id = ?");
$stmt->bind_param("i", $produk_id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();

if (!$produk || $produk['status'] !== 'aktif' || $produk['stok'] < $qty) {
    echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi atau produk tidak tersedia']);
    exit;
}

// Insert atau update keranjang (ON DUPLICATE KEY UPDATE)
$query = "INSERT INTO keranjang (user_id, produk_id, qty) VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE qty = qty + ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $user_id, $produk_id, $qty, $qty);

if ($stmt->execute()) {
    // Hitung total item di keranjang untuk badge
    $total_item = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT SUM(qty) as total FROM keranjang WHERE user_id = $user_id"))['total'];
    
    echo json_encode(['success' => true, 'message' => 'Produk ditambahkan ke keranjang!', 'total_item' => $total_item]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menambahkan ke keranjang']);
}
?>