<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$produk_id = isset($_GET['produk_id']) ? (int)$_GET['produk_id'] : 0;

if ($produk_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID produk tidak valid'
    ]);
    exit;
}

try {
    // Hitung rata-rata rating
    $stmt_avg = $pdo->prepare("
        SELECT 
            COALESCE(AVG(rating), 0) as avg_rating,
            COUNT(*) as total_ulasan
        FROM ulasan
        WHERE produk_id = ?
    ");
    $stmt_avg->execute([$produk_id]);
    $stats = $stmt_avg->fetch(PDO::FETCH_ASSOC);

    // Ambil daftar ulasan
    $stmt_list = $pdo->prepare("
        SELECT 
            u.id,
            u.rating,
            u.komentar,
            u.created_at,
            us.nama_lengkap
        FROM ulasan u
        JOIN users us ON u.user_id = us.id
        WHERE u.produk_id = ?
        ORDER BY u.created_at DESC
        LIMIT 50
    ");
    $stmt_list->execute([$produk_id]);
    $ulasan_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'produk_id' => $produk_id,
        'avg_rating' => (float)$stats['avg_rating'],
        'total_ulasan' => (int)$stats['total_ulasan'],
        'ulasan' => $ulasan_list
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}