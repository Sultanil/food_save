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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl p-8 shadow-lg max-w-md w-full text-center">
        <div class="text-6xl mb-4">✅</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Pesanan Diterima!</h1>
        <p class="text-gray-500 mb-6">ID Batch: <code class="bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($batch_id) ?></code></p>
        
        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
            <div class="flex justify-between mb-2">
                <span class="text-gray-500">Total Belanja</span>
                <span class="font-medium">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
            </div>
            <div class="flex justify-between text-sm text-gray-500">
                <span>Termasuk ongkir konsolidasi</span>
                <span>✓</span>
            </div>
        </div>
        
        <div class="space-y-3">
            <button class="w-full py-3 bg-brand hover:bg-brand-dark text-white font-semibold rounded-lg">
                💳 Bayar dengan Midtrans (Demo)
            </button>
            <a href="pesanan.php" class="block py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Lihat Riwayat Pesanan
            </a>
            <a href="Index.php" class="block text-sm text-brand hover:underline">
                ← Kembali ke Beranda
            </a>
        </div>
        
        <p class="text-xs text-gray-400 mt-6">
            🚚 Pesanan akan diproses dengan rute teroptimasi dari Hub FoodSave
        </p>
    </div>
</body>
</html>