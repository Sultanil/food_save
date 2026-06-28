<?php
// includes/payment_methods.php - Helper functions untuk payment methods

// ==================== FETCH PAYMENT METHODS ====================
if (!function_exists('getSellerPaymentMethods')) {
    function getSellerPaymentMethods($pdo, $penjual_id) {
        $stmt = $pdo->prepare("
            SELECT * FROM seller_payment_methods 
            WHERE penjual_id = ? AND is_active = 1
            ORDER BY is_default DESC, created_at ASC
        ");
        $stmt->execute([$penjual_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ==================== FETCH SINGLE PAYMENT METHOD ====================
if (!function_exists('getPaymentMethod')) {
    function getPaymentMethod($pdo, $id, $penjual_id) {
        $stmt = $pdo->prepare("
            SELECT * FROM seller_payment_methods 
            WHERE id = ? AND penjual_id = ?
        ");
        $stmt->execute([$id, $penjual_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// ==================== ADD PAYMENT METHOD ====================
if (!function_exists('addPaymentMethod')) {
    function addPaymentMethod($pdo, $penjual_id, $data) {
        $payment_type = $data['payment_type'];
        $bank_name = $data['bank_name'] ?? null;
        $account_number = $data['account_number'] ?? null;
        $account_holder = $data['account_holder'] ?? null;
        $qris_image = $data['qris_image'] ?? null;
        $is_default = $data['is_default'] ?? 0;

        // Jika set default, reset semua default yang lain
        if ($is_default) {
            $reset = $pdo->prepare("
                UPDATE seller_payment_methods 
                SET is_default = 0 
                WHERE penjual_id = ? AND payment_type = ?
            ");
            $reset->execute([$penjual_id, $payment_type]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO seller_payment_methods 
            (penjual_id, payment_type, bank_name, account_number, account_holder, qris_image, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $penjual_id,
            $payment_type,
            $bank_name,
            $account_number,
            $account_holder,
            $qris_image,
            $is_default
        ]);

        return $pdo->lastInsertId();
    }
}

// ==================== UPDATE PAYMENT METHOD ====================
if (!function_exists('updatePaymentMethod')) {
    function updatePaymentMethod($pdo, $id, $penjual_id, $data) {
        // Validasi kepemilikan
        $existing = getPaymentMethod($pdo, $id, $penjual_id);
        if (!$existing) {
            return false;
        }

        $payment_type = $data['payment_type'] ?? $existing['payment_type'];
        $bank_name = $data['bank_name'] ?? $existing['bank_name'];
        $account_number = $data['account_number'] ?? $existing['account_number'];
        $account_holder = $data['account_holder'] ?? $existing['account_holder'];
        $qris_image = $data['qris_image'] ?? $existing['qris_image'];
        $is_default = $data['is_default'] ?? 0;

        // Jika set default, reset semua default yang lain
        if ($is_default) {
            $reset = $pdo->prepare("
                UPDATE seller_payment_methods 
                SET is_default = 0 
                WHERE penjual_id = ? AND payment_type = ? AND id != ?
            ");
            $reset->execute([$penjual_id, $payment_type, $id]);
        }

        $stmt = $pdo->prepare("
            UPDATE seller_payment_methods 
            SET payment_type = ?, bank_name = ?, account_number = ?, 
                account_holder = ?, qris_image = ?, is_default = ?
            WHERE id = ? AND penjual_id = ?
        ");

        return $stmt->execute([
            $payment_type,
            $bank_name,
            $account_number,
            $account_holder,
            $qris_image,
            $is_default,
            $id,
            $penjual_id
        ]);
    }
}

// ==================== DELETE PAYMENT METHOD ====================
if (!function_exists('deletePaymentMethod')) {
    function deletePaymentMethod($pdo, $id, $penjual_id) {
        // Validasi kepemilikan
        $existing = getPaymentMethod($pdo, $id, $penjual_id);
        if (!$existing) {
            return false;
        }

        // Hapus file gambar jika ada
        if (!empty($existing['qris_image']) && file_exists($existing['qris_image'])) {
            unlink($existing['qris_image']);
        }

        $stmt = $pdo->prepare("DELETE FROM seller_payment_methods WHERE id = ? AND penjual_id = ?");
        return $stmt->execute([$id, $penjual_id]);
    }
}

// ==================== SET DEFAULT PAYMENT ====================
if (!function_exists('setDefaultPayment')) {
    function setDefaultPayment($pdo, $penjual_id, $payment_id, $payment_type) {
        // Validasi kepemilikan
        $existing = getPaymentMethod($pdo, $payment_id, $penjual_id);
        if (!$existing) {
            return false;
        }

        // Reset semua default untuk tipe yang sama
        $reset = $pdo->prepare("
            UPDATE seller_payment_methods 
            SET is_default = 0 
            WHERE penjual_id = ? AND payment_type = ?
        ");
        $reset->execute([$penjual_id, $payment_type]);

        // Set yang ini sebagai default
        $stmt = $pdo->prepare("
            UPDATE seller_payment_methods 
            SET is_default = 1 
            WHERE id = ? AND penjual_id = ?
        ");
        return $stmt->execute([$payment_id, $penjual_id]);
    }
}

// ==================== TOGGLE ACTIVE STATUS ====================
if (!function_exists('togglePaymentStatus')) {
    function togglePaymentStatus($pdo, $id, $penjual_id) {
        $existing = getPaymentMethod($pdo, $id, $penjual_id);
        if (!$existing) {
            return false;
        }

        $new_status = $existing['is_active'] ? 0 : 1;

        $stmt = $pdo->prepare("
            UPDATE seller_payment_methods 
            SET is_active = ? 
            WHERE id = ? AND penjual_id = ?
        ");
        return $stmt->execute([$new_status, $id, $penjual_id]);
    }
}

// ==================== COUNT PAYMENT METHODS ====================
if (!function_exists('countSellerPaymentMethods')) {
    function countSellerPaymentMethods($pdo, $penjual_id, $payment_type = null) {
        if ($payment_type) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM seller_payment_methods 
                WHERE penjual_id = ? AND payment_type = ? AND is_active = 1
            ");
            $stmt->execute([$penjual_id, $payment_type]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM seller_payment_methods 
                WHERE penjual_id = ? AND is_active = 1
            ");
            $stmt->execute([$penjual_id]);
        }
        return $stmt->fetchColumn();
    }
}
?>