<?php
// payment_summary.php
session_start();
include 'koneksi.php';

if (!isset($_GET['batch_id']) || !isset($_SESSION['sudah_login'])) {
    header("Location: Index.php");
    exit;
}

$batch_id = $_GET['batch_id'];
$total_bayar = (float)$_GET['total'];
$pembayaran = $_GET['pembayaran'] ?? 'Transfer Bank';

// Generate dynamic unique 6-digit order ID based on the batch ID / timestamp
$display_id = substr(preg_replace('/[^0-9]/', '', $batch_id), -6);
if (strlen($display_id) < 6) {
    $display_id = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - FoodSave</title>
    <?php include 'includes/tailwind_config.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-8 shadow-xl max-w-md w-full border border-gray-150">
        
        <!-- Premium Green Box Success Alert (Matches Picture 5) -->
        <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 text-center mb-6">
            <div class="w-12 h-12 bg-green-500 text-white rounded-full flex items-center justify-center text-2xl mx-auto mb-3 shadow-md">
                ✓
            </div>
            <h1 class="text-xl font-extrabold text-green-700 mb-1">Pesanan Berhasil!</h1>
            <p class="text-xs text-gray-500 font-mono mb-3">ID Pesanan: <strong class="text-gray-800 font-bold">#<?= htmlspecialchars($display_id) ?></strong></p>
            
            <div class="border-t border-dashed border-green-200 my-3 pt-3">
                <p class="text-sm text-gray-600">
                    Silakan lakukan pembayaran melalui <br>
                    <strong class="text-gray-900 text-base"><?= htmlspecialchars($pembayaran) ?></strong>
                </p>
                <p class="text-xs text-gray-400 mt-1">Total tagihan yang harus dibayar:</p>
                <p class="text-2xl font-black text-green-600 mt-1">Rp <?= number_format($total_bayar, 0, ',', '.') ?></p>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="space-y-3">
            <button class="w-full py-3.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer flex items-center justify-center gap-2">
                💳 Bayar Sekarang (Midtrans Demo)
            </button>
            
            <a href="riwayat_pembelian.php" class="block w-full py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-center transition">
                Lihat Riwayat Pesanan
            </a>
            
            <a href="Index.php" class="block text-center text-sm text-green-600 hover:text-green-700 font-semibold hover:underline">
                ← Kembali ke Beranda
            </a>
        </div>
        
        <p class="text-[10px] text-gray-400 mt-6 text-center">
            🚚 Pesanan akan diproses dengan rute teroptimasi dari Hub FoodSave. Terima kasih telah menyelamatkan makanan surplus!
        </p>
    </div>
</body>
</html>