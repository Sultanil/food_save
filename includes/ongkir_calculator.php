<?php

/**
 * Kalkulator Checkout FoodSave
 * Berisi logika Ongkir, Voucher, dan Total Pembayaran
 */

// ==================== KONSTANTA ====================
define('ONGKIR_PER_KM', 750);
define('ONGKIR_MINIMUM', 3000);
define('BIAYA_LAYANAN_DEFAULT', 5000);

// ==================== FUNGSI ONGKIR ====================

/**
 * Hitung ongkir berdasarkan kode pos pembeli
 * Rumus: Jarak (km) × Rp 750 (Min. Rp 3.000)
 */
function hitungOngkir($pdo, $kode_pos_pembeli)
{
    if (empty($kode_pos_pembeli)) {
        return ONGKIR_MINIMUM;
    }

    try {
        // ✅ PERBAIKAN: Pakai tabel 'kode_pos' dan kolom 'jarak_dari_hub'
        $stmt = $pdo->prepare("
            SELECT jarak_dari_hub 
            FROM kode_pos 
            WHERE kode_pos = ?
            LIMIT 1
        ");
        $stmt->execute([$kode_pos_pembeli]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['jarak_dari_hub'])) {
            $jarak_km = (float)$result['jarak_dari_hub'];
            $ongkir = $jarak_km * ONGKIR_PER_KM;

            // Terapkan minimum ongkir
            return max(ONGKIR_MINIMUM, (int)$ongkir);
        } else {
            // Jika kode pos tidak ditemukan, gunakan default 5 km
            return max(ONGKIR_MINIMUM, 5 * ONGKIR_PER_KM);
        }
    } catch (PDOException $e) {
        error_log("Error hitung ongkir: " . $e->getMessage());
        return ONGKIR_MINIMUM;
    }
}

// ==================== FUNGSI VOUCHER ====================

/**
 * Hitung diskon voucher
 * FOODSAVE10 = 10% (Maks 10.000)
 * FOODSAVE20 = 20% (Maks 20.000)
 */
function hitungDiskonVoucher($kode_voucher, $subtotal)
{
    $kode_voucher = strtoupper(trim($kode_voucher));
    $result = ['valid' => false, 'diskon' => 0, 'pesan' => '', 'kode' => ''];

    if (empty($kode_voucher)) {
        return $result;
    }

    // Database voucher (bisa dipindah ke tabel database nanti jika mau)
    $vouchers = [
        'FOODSAVE10' => ['persen' => 10, 'maks' => 10000],
        'FOODSAVE20' => ['persen' => 20, 'maks' => 20000],
    ];

    if (isset($vouchers[$kode_voucher])) {
        $v = $vouchers[$kode_voucher];
        $diskon = ($subtotal * $v['persen']) / 100;

        // Batasi maksimal diskon
        if ($diskon > $v['maks']) {
            $diskon = $v['maks'];
        }

        // Diskon tidak boleh lebih besar dari subtotal
        if ($diskon > $subtotal) {
            $diskon = $subtotal;
        }

        $result['valid'] = true;
        $result['diskon'] = (int)$diskon;
        $result['pesan'] = "🎉 Voucher {$kode_voucher} berhasil diterapkan! Hemat Rp " . number_format($diskon, 0, ',', '.');
        $result['kode'] = $kode_voucher;
    } else {
        $result['pesan'] = "❌ Kode voucher tidak valid atau sudah kadaluarsa.";
    }

    return $result;
}

// ==================== FUNGSI MASTER TOTAL (SANGAT DISARANKAN) ====================

/**
 * Hitung semua komponen checkout dalam 1 kali panggil
 * Mengembalikan array lengkap untuk digunakan di frontend/backend
 */
function hitungTotalCheckout($pdo, $subtotal, $kode_pos_pembeli, $kode_voucher = '', $biaya_layanan = BIAYA_LAYANAN_DEFAULT)
{
    // 1. Hitung Ongkir
    $ongkir = hitungOngkir($pdo, $kode_pos_pembeli);

    // 2. Hitung Voucher
    $voucher_result = hitungDiskonVoucher($kode_voucher, $subtotal);
    $diskon = $voucher_result['diskon'];

    // 3. Hitung Total Akhir (Pastikan tidak minus)
    $total_bayar = $subtotal + $biaya_layanan + $ongkir - $diskon;
    $total_bayar = max(0, $total_bayar);

    return [
        'ongkir' => $ongkir,
        'biaya_layanan' => $biaya_layanan,
        'diskon' => $diskon,
        'total_bayar' => $total_bayar,
        'voucher_valid' => $voucher_result['valid'],
        'voucher_pesan' => $voucher_result['pesan'],
        'voucher_kode' => $voucher_result['kode']
    ];
}
