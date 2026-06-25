<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Cek login
if (!isset($_SESSION['user_id']) && !isset($_SESSION['sudah_login'])) {
    echo json_encode(["status" => "error", "message" => "Silakan login terlebih dahulu!"]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'];
$produk_id = isset($_POST['produk_id']) ? (int)$_POST['produk_id'] : 0;
$id_toko = isset($_POST['id_toko']) ? (int)$_POST['id_toko'] : 0;

if ($produk_id <= 0 || $id_toko <= 0) {
    echo json_encode(["status" => "error", "message" => "Data produk tidak valid!"]);
    exit;
}

try {
    // 1. Verifikasi produk ada dan milik toko tersebut
    $stmt = $pdo->prepare("SELECT id, nama_produk FROM produk WHERE id = ? AND penjual_id = ?");
    $stmt->execute([$produk_id, $id_toko]);
    $produk = $stmt->fetch();
    
    if (!$produk) {
        echo json_encode(["status" => "error", "message" => "Produk tidak ditemukan!"]);
        exit;
    }
    
    // 2. Cek keranjang user saat ini
    $stmt = $pdo->prepare("SELECT id_toko FROM keranjang WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $keranjang_lama = $stmt->fetch();
    
    $keranjang_dihapus = false;
    $pesan_tambahan = "";
    
    // 3. LOGIKA PINDAH TOKO (GoFood Style)
    if ($keranjang_lama && $keranjang_lama['id_toko'] != $id_toko) {
        // Hapus semua isi keranjang lama!
        $stmt = $pdo->prepare("DELETE FROM keranjang WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $keranjang_dihapus = true;
        $pesan_tambahan = " Keranjang dari toko sebelumnya telah dihapus karena Anda berpindah toko.";
    }
    
    // 4. Cek apakah produk sudah ada di keranjang
    $stmt = $pdo->prepare("SELECT id, qty FROM keranjang WHERE user_id = ? AND produk_id = ?");
    $stmt->execute([$user_id, $produk_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Kalau sudah ada, tambah qty
        $qty_baru = $existing['qty'] + 1;
        $stmt = $pdo->prepare("UPDATE keranjang SET qty = ? WHERE id = ?");
        $stmt->execute([$qty_baru, $existing['id']]);
        $aksi = "updated";
    } else {
        // Kalau belum ada, insert baru
        $stmt = $pdo->prepare("INSERT INTO keranjang (user_id, produk_id, id_toko, qty) VALUES (?, ?, ?, 1)");
        $stmt->execute([$user_id, $produk_id, $id_toko]);
        $aksi = "added";
    }
    
    // 5. Hitung total item di keranjang
    $stmt = $pdo->prepare("SELECT SUM(qty) as total FROM keranjang WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_items = $stmt->fetch()['total'] ?? 0;
    
    echo json_encode([
        "status" => "success",
        "message" => "Produk berhasil ditambahkan ke keranjang!" . $pesan_tambahan,
        "cart_cleared" => $keranjang_dihapus,
        "total_items" => $total_items,
        "action" => $aksi
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error database: " . $e->getMessage()]);
}

exit;
?>