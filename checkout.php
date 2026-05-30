<?php
// checkout.php
session_start();
include 'koneksi.php';

// Security check
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$kode_pos_pembeli = $_SESSION['kode_pos'];

// Ambil payload dari POST (bisa dari keranjang.php atau submit_self dari form di halaman ini)
$cart_items_raw = $_POST['cart_items'] ?? '';
$cart_items = json_decode($cart_items_raw, true);
$subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0;

if (empty($cart_items)) {
    header("Location: keranjang.php");
    exit;
}

// ========== FUNGSI HITUNG JARAK & ONGKIR ==========
function getJarak($conn, $pos_asal, $pos_tujuan) {
    if ($pos_asal === 'HUB' || $pos_asal === $pos_tujuan) return 0;
    $stmt = $conn->prepare("SELECT jarak FROM matriks_jarak WHERE pos_asal = ? AND pos_tujuan = ?");
    $stmt->bind_param("ss", $pos_asal, $pos_tujuan);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? (float)$res['jarak'] : 5; // fallback 5km
}

function hitungOngkirKonsolidasi($conn, $seller_positions, $kode_pos_pembeli) {
    if (empty($seller_positions)) return 12000;
    
    // Urutkan penjual berdasarkan jarak dari Hub (terdekat dulu)
    $placeholders = implode(',', array_fill(0, count($seller_positions), '?'));
    $types = str_repeat('s', count($seller_positions));
    
    $stmt = $conn->prepare("SELECT kode_pos, jarak_dari_hub FROM kode_pos WHERE kode_pos IN ($placeholders) ORDER BY jarak_dari_hub ASC");
    $stmt->bind_param($types, ...$seller_positions);
    $stmt->execute();
    $sellers_sorted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($sellers_sorted)) return 12000; // fallback
    
    $total_jarak = 0;
    
    // 1. Hub → Penjual Terdekat
    $total_jarak += $sellers_sorted[0]['jarak_dari_hub'];
    
    // 2. Penjual → Penjual (loop)
    for ($i = 0; $i < count($sellers_sorted) - 1; $i++) {
        $jarak = getJarak($conn, $sellers_sorted[$i]['kode_pos'], $sellers_sorted[$i+1]['kode_pos']);
        $total_jarak += $jarak;
    }
    
    // 3. Penjual Terakhir → Pembeli
    $last_pos = end($sellers_sorted)['kode_pos'];
    $jarak_final = getJarak($conn, $last_pos, $kode_pos_pembeli);
    $total_jarak += $jarak_final;
    
    return $total_jarak * 2000; // Tarif Rp 2.000/km
}
// ========== END FUNGSI ==========

// Hitung ongkir
$seller_positions = array_unique(array_filter(array_column($cart_items, 'penjual_kode_pos')));
$ongkir_konsolidasi = hitungOngkirKonsolidasi($conn, $seller_positions, $kode_pos_pembeli);

// Default states
$biaya_layanan = 2000;
$nama = $_SESSION['nama'] ?? '';
$telepon = '';
$alamat = '';
$kode_voucher = '';
$diskon = 0;
$pembayaran = 'Transfer Bank';
$ongkir = 12000; // Default kurir instan
$pesan = '';
$pesan_class = '';

// Jika form disubmit untuk BAYAR SEKARANG
if (isset($_POST['bayar_sekarang'])) {
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kode_voucher = strtoupper(trim($_POST['voucher'] ?? ''));
    $pembayaran = $_POST['pembayaran'] ?? 'Transfer Bank';
    $pengiriman_opsi = $_POST['pengiriman'] ?? '12000';
    
    // Tentukan ongkir berdasarkan opsi yang dipilih
    if ($pengiriman_opsi === '0') {
        $ongkir = 0; // Ambil sendiri
    } elseif ($pengiriman_opsi === '8000') {
        $ongkir = 8000; // Same Day
    } else {
        $ongkir = $ongkir_konsolidasi; // Kurir instan teroptimasi
    }
    
    // Hitung Voucher
    if ($kode_voucher === 'FOODSAVE10') {
        $diskon = min(10000, $subtotal * 0.1);
    } elseif ($kode_voucher === 'FOODSAVE20') {
        $diskon = min(20000, $subtotal * 0.2);
    }
    
    $total_bayar = $subtotal + $biaya_layanan + $ongkir - $diskon;
    
    if (empty($nama) || empty($telepon) || empty($alamat)) {
        $pesan = '⚠️ Mohon lengkapi data pembeli terlebih dahulu.';
        $pesan_class = 'error';
    } else {
        // Generate batch ID untuk pengelompokan transaksi
        $batch_id = 'BATCH_' . date('YmdHis') . '_' . $user_id;
        
        // Simpan transaksi untuk SETIAP item keranjang
        foreach ($cart_items as $index => $item) {
            $produk_id = (int)$item['produk_id'];
            $penjual_id = (int)$item['penjual_id'];
            $qty = (int)$item['qty'];
            $harga = (float)$item['harga_satuan'];
            
            // Distribusikan ongkir & biaya layanan hanya pada item pertama
            $ongkir_item = ($index === 0) ? $ongkir : 0;
            $layanan_item = ($index === 0) ? $biaya_layanan : 0;
            $diskon_item = ($index === 0) ? $diskon : 0;
            $total_item = ($harga * $qty) + $ongkir_item + $layanan_item - $diskon_item;
            
            $stmt = $conn->prepare("
                INSERT INTO transaksi 
                (user_id, penjual_id, produk_id, jumlah, total_harga, status, alamat_pengiriman, no_telepon, metode_pembayaran, ongkir, diskon, kode_voucher, checkout_batch_id, shipping_status) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'diproses')
            ");
            
            $stmt->bind_param("iiidssssddss", 
                $user_id, 
                $penjual_id, 
                $produk_id, 
                $qty, 
                $total_item,
                $alamat,
                $telepon,
                $pembayaran,
                $ongkir_item,
                $diskon_item,
                $kode_voucher,
                $batch_id
            );
            
            $stmt->execute();
            
            // Kurangi stok produk
            $stmt_update = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
            $stmt_update->bind_param("ii", $qty, $produk_id);
            $stmt_update->execute();
        }
        
        // Kosongkan keranjang belanja
        $stmt_clear = $conn->prepare("DELETE FROM keranjang WHERE user_id = ?");
        $stmt_clear->bind_param("i", $user_id);
        $stmt_clear->execute();
        
        // Redirect ke halaman rangkuman pembayaran
        header("Location: payment_summary.php?batch_id=$batch_id&total=$total_bayar&pembayaran=" . urlencode($pembayaran));
        exit;
    }
}

// Jika tombol voucher ditekan (tanpa membuat pesanan)
if (isset($_POST['apply_voucher'])) {
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kode_voucher = strtoupper(trim($_POST['voucher'] ?? ''));
    $pembayaran = $_POST['pembayaran'] ?? 'Transfer Bank';
    $pengiriman_opsi = $_POST['pengiriman'] ?? '12000';
    
    if ($pengiriman_opsi === '0') {
        $ongkir = 0;
    } elseif ($pengiriman_opsi === '8000') {
        $ongkir = 8000;
    } else {
        $ongkir = $ongkir_konsolidasi;
    }
    
    if ($kode_voucher === 'FOODSAVE10') {
        $diskon = min(10000, $subtotal * 0.1);
        $pesan = '🎉 Voucher FOODSAVE10 berhasil diterapkan! Diskon 10% (Maks Rp 10.000)';
        $pesan_class = 'success';
    } elseif ($kode_voucher === 'FOODSAVE20') {
        $diskon = min(20000, $subtotal * 0.2);
        $pesan = '🎉 Voucher FOODSAVE20 berhasil diterapkan! Diskon 20% (Maks Rp 20.000)';
        $pesan_class = 'success';
    } else {
        $diskon = 0;
        $pesan = '❌ Kode voucher tidak valid!';
        $pesan_class = 'error';
    }
}

$ongkir = ($ongkir === 0) ? 0 : (($ongkir == 8000) ? 8000 : $ongkir_konsolidasi);
$total_bayar = $subtotal + $biaya_layanan + $ongkir - $diskon;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Keranjang - FoodSave</title>
    <?php include 'includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        .input-focus { @apply focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-green-600 flex items-center gap-2">
                🌿 FoodSave
            </a>
            <div class="flex items-center gap-3">
                <a href="keranjang.php" class="text-sm text-gray-600 hover:text-green-600 font-medium">← Kembali ke Keranjang</a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="max-w-5xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Checkout Keranjang Belanja</h1>
            <p class="text-gray-500 mt-2">Selesaikan pemesanan makanan surplus Anda dengan kurir rute teroptimasi</p>
        </div>

        <!-- Alert Message -->
        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success' 
                ? 'bg-green-50 border-green-200 text-green-700' 
                : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <!-- Checkout Grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- FORM KIRI (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Ringkasan Produk-produk Keranjang -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">📦</span>
                        Daftar Produk Belanjaan
                    </h2>
                    
                    <div class="space-y-3">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex gap-4 p-3 bg-gray-50 rounded-lg items-center">
                                <img src="<?= htmlspecialchars($item['gambar_url'] ?: 'https://via.placeholder.com/80') ?>" 
                                     alt="<?= htmlspecialchars($item['nama_produk']) ?>" 
                                     class="w-16 h-16 rounded-lg object-cover bg-white">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($item['nama_toko']) ?> • <?= htmlspecialchars($item['kota']) ?></p>
                                    <p class="text-xs text-gray-400 mt-0.5">Jumlah: <?= $item['qty'] ?> <?= htmlspecialchars($item['satuan']) ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-green-600 text-sm">Rp <?= number_format($item['harga_satuan'] * $item['qty'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Form Data Pengiriman & Pembayaran -->
                <form method="POST" class="space-y-6">
                    <!-- Hidden Payload untuk diteruskan saat submit ulang -->
                    <input type="hidden" name="cart_items" value='<?= htmlspecialchars(json_encode($cart_items)) ?>'>
                    <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
                    
                    <!-- Data Pembeli -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">👤</span>
                            Data Penerima
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                                <input type="text" name="nama" value="<?= htmlspecialchars($nama) ?>"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                                       placeholder="Nama Penerima">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WhatsApp *</label>
                                <input type="tel" name="telepon" value="<?= htmlspecialchars($telepon) ?>"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                                       placeholder="08xxxxxxxxxx">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Pengiriman Lengkap *</label>
                                <textarea name="alamat" rows="2"
                                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none resize-none transition"
                                          placeholder="Jl. Slamet Riyadi No. 456, RT/RW, Kelurahan, Kecamatan"><?= htmlspecialchars($alamat) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Pengiriman -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">🚚</span>
                            Metode Pengiriman
                        </h2>
                        
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-500 transition <?= $pengiriman_opsi === '12000' || !isset($_POST['pengiriman']) ? 'border-green-500 bg-green-50/20' : '' ?>">
                                <input type="radio" name="pengiriman" value="12000" class="w-4 h-4 text-green-600" <?= $pengiriman_opsi === '12000' || !isset($_POST['pengiriman']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">Kurir Instan Rute Teroptimasi</span>
                                    <p class="text-xs text-gray-500">Dikirim via hub optimal dari para penjual • Jarak terdekat</p>
                                </div>
                                <span class="font-semibold text-green-600">Rp <?= number_format($ongkir_konsolidasi, 0, ',', '.') ?></span>
                            </label>
                            
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-500 transition <?= $pengiriman_opsi === '8000' ? 'border-green-500 bg-green-50/20' : '' ?>">
                                <input type="radio" name="pengiriman" value="8000" class="w-4 h-4 text-green-600" <?= $pengiriman_opsi === '8000' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">Same Day Delivery</span>
                                    <p class="text-xs text-gray-500">Estimasi sampai malam ini</p>
                                </div>
                                <span class="font-semibold text-green-600">Rp 8.000</span>
                            </label>
                            
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-500 transition <?= $pengiriman_opsi === '0' ? 'border-green-500 bg-green-50/20' : '' ?>">
                                <input type="radio" name="pengiriman" value="0" class="w-4 h-4 text-green-600" <?= $pengiriman_opsi === '0' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">Ambil Sendiri di Toko Penjual</span>
                                    <p class="text-xs text-gray-500">Gratis ongkir • Ambil sendiri ke toko masing-masing</p>
                                </div>
                                <span class="font-semibold text-green-600">Gratis</span>
                            </label>
                        </div>
                    </div>

                    <!-- Pembayaran -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">💳</span>
                            Metode Pembayaran
                        </h2>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <?php $methods = ['Transfer Bank' => '🏦 Transfer Bank', 'E-Wallet' => '📱 E-Wallet', 'COD' => '💵 COD']; ?>
                            <?php foreach ($methods as $val => $desc): ?>
                            <label class="p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-500 transition text-center flex flex-col items-center justify-center <?= $pembayaran === $val ? 'border-green-500 bg-green-50/20' : '' ?>">
                                <input type="radio" name="pembayaran" value="<?= $val ?>" class="sr-only" <?= $pembayaran === $val ? 'checked' : '' ?> onchange="this.form.submit()">
                                <div class="text-xs font-semibold text-gray-900 mt-1"><?= $desc ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Voucher -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">🎁</span>
                            Kode Voucher
                        </h2>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4 text-xs">
                            <p class="text-yellow-800">
                                <strong>Pilihan Kode Voucher:</strong><br>
                                <code class="bg-white px-1.5 py-0.5 rounded text-[11px] font-bold">FOODSAVE10</code> = Diskon 10% (Maks Rp 10.000)<br>
                                <code class="bg-white px-1.5 py-0.5 rounded text-[11px] font-bold">FOODSAVE20</code> = Diskon 20% (Maks Rp 20.000)
                            </p>
                        </div>
                        
                        <div class="flex gap-2">
                            <input type="text" name="voucher" value="<?= htmlspecialchars($kode_voucher) ?>" placeholder="Masukkan kode voucher"
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none uppercase text-sm">
                            <button type="submit" name="apply_voucher"
                                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition text-sm cursor-pointer">
                                Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="bayar_sekarang"
                            class="w-full py-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition shadow-lg hover:shadow-xl text-lg cursor-pointer">
                        Bayar Sekarang • <span>Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </button>
                </form>
            </div>

            <!-- SUMMARY KANAN (1/3) - Sticky -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Pembayaran</h2>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal Belanja</span>
                            <span class="font-medium">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Biaya Layanan</span>
                            <span class="font-medium">Rp <?= number_format($biaya_layanan, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Pengiriman</span>
                            <span class="font-medium"><?= $ongkir === 0 ? 'Gratis' : 'Rp ' . number_format($ongkir, 0, ',', '.') ?></span>
                        </div>
                        <?php if ($diskon > 0): ?>
                            <div class="flex justify-between text-green-600 font-semibold">
                                <span>Potongan Voucher</span>
                                <span>- Rp <?= number_format($diskon, 0, ',', '.') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex justify-between text-lg font-bold text-gray-900 pt-4 mt-4 border-t-2 border-green-600">
                        <span>Total Bayar</span>
                        <span>Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t">
                        <div class="flex justify-between text-xs text-gray-400">
                            <span class="text-green-600 font-medium">✓ Keranjang</span>
                            <span class="text-green-600 font-medium">✓ Detail Alamat</span>
                            <span class="text-green-600 font-medium">✓ Pilih Pembayaran</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="py-6 px-4 bg-gray-900 text-gray-400 text-center mt-12">
        <p>© <?= date('Y') ?> FoodSave. All rights reserved.</p>
    </footer>

</body>
</html>