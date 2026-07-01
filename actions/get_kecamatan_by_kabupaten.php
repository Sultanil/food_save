<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$kabupaten = $_GET['kabupaten'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT DISTINCT kecamatan FROM kode_pos WHERE kabupaten = ? ORDER BY kecamatan");
    $stmt->execute([$kabupaten]);
    $kecamatans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $kecamatans
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>