<?php
// checkout.php
session_start();
include 'koneksi.php';

// Security check
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli' || empty($_POST['cart_items'])) {
    header("Location: keranjang.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$kode_pos_pembeli = $_SESSION['kode_pos'];
$cart_items = json_decode($_POST['cart_items'], true);
$subtotal = (float)$_POST['subtotal'];

// ========== FUNGSI HITUNG JARAK ==========
function getJarak($conn, $pos_asal, $pos_tujuan) {
    if ($pos_asal === 'HUB') return 0;
    $stmt = $conn->prepare("SELECT jarak FROM matriks_jarak WHERE pos_asal = ? AND pos_tujuan = ?");
    $stmt->bind_param("ss", $pos_asal, $pos_tujuan);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? (float)$res['jarak'] : 5; // fallback 5km
}

function hitungOngkirKonsolidasi($conn, $seller_positions, $kode_pos_pembeli) {
    // Urutkan penjual berdasarkan jarak dari Hub (terdekat dulu)
    $placeholders = implode(',', array_fill(0, count($seller_positions), '?'));
    $stmt = $conn->prepare("SELECT kode_pos, jarak_dari_hub FROM kode_pos WHERE kode_pos IN ($placeholders) ORDER BY jarak_dari_hub ASC");
    $stmt->bind_param(str_repeat('s', count($seller_positions)), ...$seller_positions);
    $stmt->execute();
    $sellers_sorted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($sellers_sorted)) return 12000; // fallback
    
    $total_jarak = 0;
    
    // 1. Hub → Penjual Terdekat
    $total_jarak += $sellers_sorted[0]['jarak_dari_hub'];
    
    // 2. Penjual → Penjual (loop)
    for ($i = 0; $i < count($sellers_sorted) - 1; $i++) {
        $jarak = getJarak($conn, $sellers_sorted[$i]['kode_pos'], $sellers_sorted[$i+1]['kode_pos']);
        $total_jarak += $jarak;
    }
    
    // 3. Penjual Terakhir → Pembeli
    $last_pos = end($sellers_sorted)['kode_pos'];
    $jarak_final = getJarak($conn, $last_pos, $kode_pos_pembeli);
    $total_jarak += $jarak_final;
    
    return $total_jarak * 2000; // Tarif Rp 2.000/km
}
// ========== END FUNGSI ==========

// Ambil data unik penjual dari cart
$seller_positions = array_unique(array_column($cart_items, 'penjual_kode_pos'));
$ongkir_total = hitungOngkirKonsolidasi($conn, $seller_positions, $kode_pos_pembeli);
$biaya_layanan = 2000;
$total_bayar = $subtotal + $biaya_layanan + $ongkir_total;

// Generate batch ID untuk grouping transaksi
$batch_id = 'BATCH_' . date('YmdHis') . '_' . $user_id;

// Proses: Insert ke tabel transaksi untuk SETIAP item
foreach ($cart_items as $item) {
    $produk_id = (int)$item['produk_id'];
    $penjual_id = (int)$item['penjual_id'];
    $qty = (int)$item['qty'];
    $harga = (float)$item['harga'];
    
    // Ongkir hanya dikenakan sekali (di item pertama)
    $ongkir_item = ($item === $cart_items[0]) ? $ongkir_total : 0;
    $total_item = ($harga * $qty) + ($ongkir_item > 0 ? $ongkir_item + $biaya_layanan : 0);
    
    $stmt = $conn->prepare("
        INSERT INTO transaksi 
        (user_id, penjual_id, produk_id, jumlah, total_harga, status, checkout_batch_id, shipping_status) 
        VALUES (?, ?, ?, ?, ?, 'pending', ?, 'diproses')
    ");
    $stmt->bind_param("iiidss", $user_id, $penjual_id, $produk_id, $qty, $total_item, $batch_id);
    $stmt->execute();
    
    // Kurangi stok (opsional: bisa dipindah ke setelah pembayaran)
    $stmt = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
    $stmt->bind_param("ii", $qty, $produk_id);
    $stmt->execute();
}

// Kosongkan keranjang user ini
$stmt = $conn->prepare("DELETE FROM keranjang WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Redirect ke halaman pembayaran/summary
header("Location: payment_summary.php?batch_id=$batch_id&total=$total_bayar");
exit;
?>