<?php
session_start();
require_once 'config/database.php';
require_once 'includes/payment_methods.php';

// Set session manual untuk test (hapus setelah test)
$_SESSION['sudah_login'] = true;
$_SESSION['role'] = 'penjual';
$_SESSION['user_id'] = 14; // Ganti dengan user_id penjual yang ada

// Ambil penjual_id
$stmt = $pdo->prepare("SELECT id FROM penjual WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$penjual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$penjual) {
    die("Penjual tidak ditemukan!");
}

$penjual_id = $penjual['id'];

echo "<h2>Test Payment Methods</h2>";

// Test 1: Tambah Bank Account
echo "<h3>1. Tambah Bank Account</h3>";
$data = [
    'payment_type' => 'bank_transfer',
    'bank_name' => 'BCA',
    'account_number' => '1234567890',
    'account_holder' => 'PT FoodSave Indonesia',
    'is_default' => 1
];

$id = addPaymentMethod($pdo, $penjual_id, $data);
echo "Bank account ditambahkan dengan ID: $id<br>";

// Test 2: Tambah QRIS
echo "<h3>2. Tambah QRIS</h3>";
$data = [
    'payment_type' => 'qris',
    'qris_image' => null, // Nanti upload manual
    'is_default' => 0
];

$id = addPaymentMethod($pdo, $penjual_id, $data);
echo "QRIS ditambahkan dengan ID: $id<br>";

// Test 3: Fetch semua payment methods
echo "<h3>3. Fetch Semua Payment Methods</h3>";
$methods = getSellerPaymentMethods($pdo, $penjual_id);
echo "<pre>";
print_r($methods);
echo "</pre>";

// Test 4: Set Default
echo "<h3>4. Set Default</h3>";
$result = setDefaultPayment($pdo, $penjual_id, $methods[1]['id'], 'bank_transfer');
echo "Set default: " . ($result ? 'Sukses' : 'Gagal') . "<br>";

// Test 5: Delete
echo "<h3>5. Delete</h3>";
$result = deletePaymentMethod($pdo, $methods[1]['id'], $penjual_id);
echo "Delete: " . ($result ? 'Sukses' : 'Gagal') . "<br>";
?>