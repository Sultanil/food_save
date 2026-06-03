<?php
// includes/functions.php - Helper functions yang bisa dipakai di semua halaman

// Format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Format tanggal Indonesia
function formatTanggal($tanggal, $format = 'd F Y') {
    $bulan = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    
    $formatted = date($format, strtotime($tanggal));
    return str_replace(array_keys($bulan), array_values($bulan), $formatted);
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Generate batch ID
function generateBatchId($user_id) {
    return 'BATCH_SINGLE_' . date('YmdHis') . '_' . $user_id;
}
?>