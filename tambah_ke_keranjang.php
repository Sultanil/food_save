<?php
// tambah_ke_keranjang.php
session_start();
include 'koneksi.php';
header('Content-Type: application/json');

// Allow CORS if needed (for development)
// header('Access-Control-Allow-Origin: *');

// Cek login
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login sebagai pembeli']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Terima dari GET atau POST
$produk_id = isset($_REQUEST['produk_id']) ? (int)$_REQUEST['produk_id'] : 0;
$qty = isset($_REQUEST['qty']) ? max(1, (int)$_REQUEST['qty']) : 1;

if (!$produk_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produk tidak valid']);
    exit;
}

// Cek stok & status produk
$stmt = $conn->prepare("SELECT id, nama_produk, stok, status, gambar_url FROM produk WHERE id = ?");
$stmt->bind_param("i", $produk_id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();

if (!$produk) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    exit;
}

if ($produk['status'] !== 'aktif') {
    echo json_encode(['success' => false, 'message' => 'Produk tidak tersedia']);
    exit;
}

if ($produk['stok'] < $qty) {
    echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi', 'stok_tersisa' => $produk['stok']]);
    exit;
}

// Insert atau update keranjang
$query = "INSERT INTO keranjang (user_id, produk_id, qty) VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE qty = qty + ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $user_id, $produk_id, $qty, $qty);

if ($stmt->execute()) {
    // Hitung total item di keranjang untuk badge
    $stmt_total = $conn->prepare("SELECT SUM(qty) as total FROM keranjang WHERE user_id = ?");
    $stmt_total->bind_param("i", $user_id);
    $stmt_total->execute();
    $total_item = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Produk ditambahkan ke keranjang!', 
        'total_item' => (int)$total_item,
        'produk_nama' => $produk['nama_produk'],
        'produk_img' => $produk['gambar_url']
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menambahkan ke keranjang']);
}
?>