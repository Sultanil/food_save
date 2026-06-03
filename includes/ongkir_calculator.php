<?php
// includes/ongkir_calculator.php - Logika perhitungan ongkir & voucher

// ==================== FUNGSI HITUNG JARAK ====================
if (!function_exists('getJarak')) {
    function getJarak($conn, $pos_asal, $pos_tujuan) {
        if ($pos_asal === 'HUB' || $pos_asal === $pos_tujuan) return 0;
        
        $stmt = $conn->prepare("SELECT jarak FROM matriks_jarak WHERE pos_asal = ? AND pos_tujuan = ?");
        $stmt->bind_param("ss", $pos_asal, $pos_tujuan);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        
        return $res ? (float)$res['jarak'] : 5;
    }
}

// ==================== FUNGSI HITUNG ONGKIR (Single Item) ====================
if (!function_exists('hitungOngkirKonsolidasi')) {
    function hitungOngkirKonsolidasi($conn, $seller_positions, $kode_pos_pembeli) {
        if (empty($seller_positions)) return 12000;
        
        $placeholders = implode(',', array_fill(0, count($seller_positions), '?'));
        $types = str_repeat('s', count($seller_positions));
        
        $stmt = $conn->prepare("SELECT kode_pos, jarak_dari_hub FROM kode_pos WHERE kode_pos IN ($placeholders) ORDER BY jarak_dari_hub ASC");
        $stmt->bind_param($types, ...$seller_positions);
        $stmt->execute();
        $sellers_sorted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($sellers_sorted)) return 12000;
        
        $total_jarak = 0;
        $total_jarak += $sellers_sorted[0]['jarak_dari_hub'];
        
        for ($i = 0; $i < count($sellers_sorted) - 1; $i++) {
            $jarak = getJarak($conn, $sellers_sorted[$i]['kode_pos'], $sellers_sorted[$i+1]['kode_pos']);
            $total_jarak += $jarak;
        }
        
        $last_pos = end($sellers_sorted)['kode_pos'];
        $jarak_final = getJarak($conn, $last_pos, $kode_pos_pembeli);
        $total_jarak += $jarak_final;
        
        return $total_jarak * 2000;
    }
}

// ==================== FUNGSI HITUNG BIAYA KONSOLIDASI (Multi-Item) ====================
if (!function_exists('hitungBiayaKonsolidasi')) {
    /**
     * Hitung ongkir + biaya layanan berdasarkan rute teroptimasi
     * @return array ['ongkir' => int, 'biaya_layanan' => int, 'total_jarak' => float, 'detail' => string]
     */
    function hitungBiayaKonsolidasi($conn, $seller_positions, $kode_pos_pembeli) {
        if (empty($seller_positions)) {
            return [
                'ongkir' => 10000,
                'biaya_layanan' => 2000,
                'total_jarak' => 5,
                'jumlah_penjual' => 0,
                'detail' => 'Rute standar (fallback)'
            ];
        }

        $placeholders = implode(',', array_fill(0, count($seller_positions), '?'));
        $types = str_repeat('s', count($seller_positions));

        $stmt = $conn->prepare("SELECT kode_pos, jarak_dari_hub FROM kode_pos WHERE kode_pos IN ($placeholders) ORDER BY jarak_dari_hub ASC");
        $stmt->bind_param($types, ...$seller_positions);
        $stmt->execute();
        $sellers_sorted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($sellers_sorted)) {
            return [
                'ongkir' => 10000,
                'biaya_layanan' => 2000,
                'total_jarak' => 5,
                'jumlah_penjual' => 0,
                'detail' => 'Rute fallback'
            ];
        }

        $total_jarak = 0;
        $rute_detail = [];

        // 1. Hub → Penjual Terdekat
        $first_seller = $sellers_sorted[0]['kode_pos'];
        $jarak_hub_first = $sellers_sorted[0]['jarak_dari_hub'];
        $total_jarak += $jarak_hub_first;
        $rute_detail[] = "Hub → {$first_seller} ({$jarak_hub_first} km)";

        // 2. Penjual → Penjual
        for ($i = 0; $i < count($sellers_sorted) - 1; $i++) {
            $pos_a = $sellers_sorted[$i]['kode_pos'];
            $pos_b = $sellers_sorted[$i + 1]['kode_pos'];
            $jarak = getJarak($conn, $pos_a, $pos_b);
            $total_jarak += $jarak;
            $rute_detail[] = "{$pos_a} → {$pos_b} ({$jarak} km)";
        }

        // 3. Penjual Terakhir → Pembeli
        $last_pos = end($sellers_sorted)['kode_pos'];
        $jarak_final = getJarak($conn, $last_pos, $kode_pos_pembeli);
        $total_jarak += $jarak_final;
        $rute_detail[] = "{$last_pos} → {$kode_pos_pembeli} ({$jarak_final} km)";

        // Perhitungan biaya
        $tarif_per_km = 2000;
        $ongkir = $total_jarak * $tarif_per_km;

        $base_layanan = 1500;
        $fee_per_seller = 500;
        $fee_per_5km = 200;

        $jumlah_penjual = count($sellers_sorted);
        $biaya_layanan = $base_layanan
            + (($jumlah_penjual - 1) * $fee_per_seller)
            + (floor($total_jarak / 5) * $fee_per_5km);

        $biaya_layanan = min($biaya_layanan, 10000);

        return [
            'ongkir' => (int)round($ongkir),
            'biaya_layanan' => (int)round($biaya_layanan),
            'total_jarak' => round($total_jarak, 1),
            'jumlah_penjual' => $jumlah_penjual,
            'detail' => implode(' → ', $rute_detail)
        ];
    }
}

// ==================== FUNGSI HITUNG DISKON VOUCHER ====================
if (!function_exists('hitungDiskonVoucher')) {
    function hitungDiskonVoucher($kode_voucher, $harga_produk) {
        $kode_voucher = $kode_voucher ?? '';
        $harga_produk = $harga_produk ?? 0;
        
        $kode_voucher = strtoupper(trim($kode_voucher));
        
        $result = [
            'diskon' => 0,
            'pesan' => '',
            'valid' => false,
            'kode' => ''
        ];
        
        if (empty($kode_voucher)) {
            return $result;
        }
        
        if ($kode_voucher === 'FOODSAVE10') {
            return [
                'diskon' => min(10000, $harga_produk * 0.1),
                'pesan' => '🎉 Voucher FOODSAVE10 berhasil diterapkan! Diskon 10% (Maks Rp 10.000)',
                'valid' => true,
                'kode' => 'FOODSAVE10'
            ];
        }
        
        if ($kode_voucher === 'FOODSAVE20') {
            return [
                'diskon' => min(20000, $harga_produk * 0.2),
                'pesan' => '🎉 Voucher FOODSAVE20 berhasil diterapkan! Diskon 20% (Maks Rp 20.000)',
                'valid' => true,
                'kode' => 'FOODSAVE20'
            ];
        }
        
        return [
            'diskon' => 0,
            'pesan' => '❌ Kode voucher tidak valid!',
            'valid' => false,
            'kode' => ''
        ];
    }
}
?>