<?php
// actions/get_kelurahan_by_kecamatan.php
// Endpoint AJAX untuk mengambil daftar kelurahan berdasarkan kecamatan

require_once __DIR__ . '/../config/database.php';

// Set response sebagai JSON
header('Content-Type: application/json');

// Ambil parameter kecamatan dari GET
$kecamatan = isset($_GET['kecamatan']) ? trim($_GET['kecamatan']) : '';

// Validasi: kecamatan tidak boleh kosong
if (empty($kecamatan)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Kecamatan tidak boleh kosong',
        'data' => []
    ]);
    exit;
}

try {
    // Query kelurahan berdasarkan kecamatan (pakai prepared statement biar aman dari SQL injection)
    $stmt = $pdo->prepare("SELECT kode_pos, kelurahan FROM kode_pos WHERE kecamatan = :kecamatan ORDER BY kelurahan ASC");
    $stmt->execute([':kecamatan' => $kecamatan]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return hasil
    echo json_encode([
        'status' => 'success',
        'message' => 'Data berhasil dimuat',
        'data' => $results
    ]);
} catch (PDOException $e) {
    // Tangani error database
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data: ' . $e->getMessage(),
        'data' => []
    ]);
}