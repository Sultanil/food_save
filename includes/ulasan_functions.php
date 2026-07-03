<?php
// ============================================
// FUNGSI: Cek apakah user bisa review produk
// ============================================
function bisaReview($pdo, $user_id, $produk_id, $transaksi_id = null) {
    try {
        $sql = "SELECT COUNT(*) FROM transaksi
                WHERE user_id = :user_id 
                AND produk_id = :produk_id
                AND status IN ('selesai', 'dibayar')";
        
        $params = [
            'user_id' => $user_id,
            'produk_id' => $produk_id
        ];
        
        if ($transaksi_id) {
            $sql .= " AND id = :transaksi_id";
            $params['transaksi_id'] = $transaksi_id;
        }
        
        $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM ulasan 
                    WHERE ulasan.user_id = transaksi.user_id 
                    AND ulasan.produk_id = transaksi.produk_id
                    AND ulasan.transaksi_id = transaksi.id
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error bisaReview: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNGSI: Ambil daftar transaksi yang bisa direview
// ============================================
function getTransaksiBisaReview($pdo, $user_id) {
    try {
        $sql = "SELECT 
                    t.id AS transaksi_id,
                    t.tanggal_pesanan,
                    t.produk_id,
                    t.jumlah,
                    t.total_harga,
                    p.nama_produk,
                    p.gambar_url,
                    p.harga_asli,
                    pj.nama_toko,
                    t.checkout_batch_id
                FROM transaksi t
                JOIN produk p ON t.produk_id = p.id
                JOIN penjual pj ON t.penjual_id = pj.id
                WHERE t.user_id = :user_id
                AND t.status IN ('selesai', 'dibayar')
                AND NOT EXISTS (
                    SELECT 1 FROM ulasan u 
                    WHERE u.user_id = t.user_id 
                    AND u.produk_id = t.produk_id
                    AND u.transaksi_id = t.id
                )
                ORDER BY t.tanggal_pesanan DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getTransaksiBisaReview: " . $e->getMessage());
        return [];
    }
}

// ============================================
// FUNGSI: Tambah ulasan
// ============================================
function tambahUlasan($pdo, $data) {
    try {
        // Validasi: pastikan user memang punya transaksi ini
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM transaksi
            WHERE id = :transaksi_id 
            AND user_id = :user_id
            AND produk_id = :produk_id
            AND status IN ('selesai', 'dibayar')
        ");
        $check->execute([
            'transaksi_id' => $data['transaksi_id'],
            'user_id' => $data['user_id'],
            'produk_id' => $data['produk_id']
        ]);
        
        if ($check->fetchColumn() == 0) {
            return ['success' => false, 'message' => 'Data transaksi tidak valid atau belum selesai!'];
        }
        
        // Cek apakah sudah pernah review
        $checkReview = $pdo->prepare("
            SELECT COUNT(*) FROM ulasan 
            WHERE user_id = :user_id 
            AND produk_id = :produk_id 
            AND transaksi_id = :transaksi_id
        ");
        $checkReview->execute([
            'user_id' => $data['user_id'],
            'produk_id' => $data['produk_id'],
            'transaksi_id' => $data['transaksi_id']
        ]);
        
        if ($checkReview->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Anda sudah memberikan ulasan untuk produk ini!'];
        }

        // Insert ulasan - TANPA kolom timestamp (auto-generated)
        $sql = "INSERT INTO ulasan (user_id, produk_id, transaksi_id, rating, komentar) 
                VALUES (:user_id, :produk_id, :transaksi_id, :rating, :komentar)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'produk_id' => $data['produk_id'],
            'transaksi_id' => $data['transaksi_id'],
            'rating' => $data['rating'],
            'komentar' => $data['komentar']
        ]);
        
        // Update rata-rata rating produk
        $updateRating = $pdo->prepare("
            UPDATE produk SET rating = (
                SELECT AVG(rating) FROM ulasan WHERE produk_id = :produk_id
            ) WHERE id = :produk_id
        ");
        $updateRating->execute(['produk_id' => $data['produk_id']]);
        
        return ['success' => true, 'message' => 'Ulasan berhasil dikirim! Terima kasih.'];
    } catch (PDOException $e) {
        error_log("Error tambahUlasan: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal menyimpan ulasan: ' . $e->getMessage()];
    }
}

// ============================================
// FUNGSI: Hitung jumlah produk yang perlu direview
// ============================================
function hitungBelumReview($pdo, $user_id) {
    try {
        $sql = "SELECT COUNT(*) FROM transaksi t
                WHERE t.user_id = :user_id
                AND t.status IN ('selesai', 'dibayar')
                AND NOT EXISTS (
                    SELECT 1 FROM ulasan u 
                    WHERE u.user_id = t.user_id 
                    AND u.produk_id = t.produk_id
                    AND u.transaksi_id = t.id
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}