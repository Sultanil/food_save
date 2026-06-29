<?php
/**
 * Fungsi-fungsi untuk sistem ulasan
 */

/**
 * Cek apakah user sudah pernah review produk ini
 */
function sudahReview($pdo, $user_id, $produk_id) {
    $stmt = $pdo->prepare("SELECT id FROM ulasan WHERE user_id = ? AND produk_id = ?");
    $stmt->execute([$user_id, $produk_id]);
    return $stmt->rowCount() > 0;
}

/**
 * Tambah ulasan baru
 */
function tambahUlasan($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ulasan (user_id, produk_id, transaksi_id, rating, komentar)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['produk_id'],
            $data['transaksi_id'],
            $data['rating'],
            $data['komentar']
        ]);
        
        return ['success' => true, 'message' => 'Ulasan berhasil ditambahkan!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Gagal menambahkan ulasan: ' . $e->getMessage()];
    }
}

/**
 * Ambil semua ulasan untuk produk tertentu
 */
function getUlasanProduk($pdo, $produk_id, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT u.*, us.nama_lengkap, us.email
        FROM ulasan u
        JOIN users us ON u.user_id = us.id
        WHERE u.produk_id = ?
        ORDER BY u.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$produk_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Hitung rata-rata rating produk
 */
function getRataRatingProduk($pdo, $produk_id) {
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_ulasan
        FROM ulasan
        WHERE produk_id = ?
    ");
    $stmt->execute([$produk_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Cek apakah user berhak review (sudah beli dan transaksi selesai)
 */
function bisaReview($pdo, $user_id, $produk_id) {
    // Cek apakah user pernah beli produk ini dan status selesai
    $stmt = $pdo->prepare("
        SELECT id FROM transaksi
        WHERE user_id = ? 
        AND produk_id = ? 
        AND status = 'selesai'
        LIMIT 1
    ");
    $stmt->execute([$user_id, $produk_id]);
    return $stmt->rowCount() > 0;
}

/**
 * Ambil daftar transaksi yang bisa direview (status selesai & belum review)
 */
function getTransaksiBisaReview($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.nama_produk, pj.nama_toko
        FROM transaksi t
        JOIN produk p ON t.produk_id = p.id
        JOIN penjual pj ON p.penjual_id = pj.id
        LEFT JOIN ulasan u ON t.id = u.transaksi_id
        WHERE t.user_id = ? 
        AND t.status = 'selesai'
        AND u.id IS NULL
        ORDER BY t.tanggal_pesanan DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}