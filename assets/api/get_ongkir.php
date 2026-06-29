<?php
// assets/api/get_ongkir.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/ongkir_calculator.php';

// Ambil kode_pos dari request
$kode_pos = $_GET['kode_pos'] ?? '';

if (empty($kode_pos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Kode pos tidak boleh kosong'
    ]);
    exit;
}

// Hitung ongkir
$ongkir = hitungOngkir($pdo, $kode_pos);

// ✅ PERBAIKAN: Ambil detail jarak dari database
$stmt = $pdo->prepare("
    SELECT kecamatan, kelurahan, jarak_dari_hub 
    FROM kode_pos 
    WHERE kode_pos = ?
");
$stmt->execute([$kode_pos]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode([
        'success' => true,
        'kode_pos' => $kode_pos,
        'kecamatan' => $data['kecamatan'],
        'kelurahan' => $data['kelurahan'],
        'jarak_km' => (float)$data['jarak_dari_hub'],
        'ongkir' => $ongkir,
        'formatted_ongkir' => 'Rp ' . number_format($ongkir, 0, ',', '.')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Kode pos tidak ditemukan'
    ]);
}