<?php
session_start();
include 'koneksi.php';

// 🔐 SECURITY: Harus login
if (!isset($_SESSION['sudah_login']) || $_SESSION['sudah_login'] !== true) {
    header("Location: LoginPage.php?msg=login_required");
    exit;
}

// Format Rupiah helper
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// 📦 Ambil data produk dari database
$produk_id = isset($_GET['produk_id']) ? (int)$_GET['produk_id'] : 0;
$penjual_id = isset($_GET['penjual_id']) ? (int)$_GET['penjual_id'] : 0;

if ($produk_id > 0 && $penjual_id > 0) {
    $stmt = $conn->prepare("
        SELECT p.id, p.nama_produk, p.deskripsi, p.harga_asli, p.harga_diskon, p.stok, p.satuan, p.gambar_url,
               pj.nama_toko, pj.kota, pj.user_id as penjual_user_id
        FROM produk p
        JOIN penjual pj ON p.penjual_id = pj.id
        WHERE p.id = ? AND p.penjual_id = ? AND p.status = 'aktif' AND p.stok > 0
    ");
    $stmt->bind_param("ii", $produk_id, $penjual_id);
    $stmt->execute();
    $produk = $stmt->get_result()->fetch_assoc();
    
    if (!$produk) {
        die("<div class='min-h-screen flex items-center justify-center bg-gray-50'>
                <div class='text-center p-8'>
                    <div class='text-6xl mb-4'>❌</div>
                    <h2 class='text-xl font-bold text-gray-900 mb-2'>Produk Tidak Ditemukan</h2>
                    <p class='text-gray-500 mb-4'>Produk mungkin sudah habis atau tidak tersedia.</p>
                    <a href='PromosiPage.php' class='inline-block px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-medium'>← Kembali ke Produk</a>
                </div>
            </div>");
    }
    
    // Harga yang dipakai (diskon jika ada)
    $harga_satuan = !empty($produk['harga_diskon']) && $produk['harga_diskon'] < $produk['harga_asli'] 
                  ? $produk['harga_diskon'] 
                  : $produk['harga_asli'];
} else {
    // Fallback jika tidak ada parameter
    header("Location: PromosiPage.php");
    exit;
}

// Default values
$jumlah_produk = 1;
$biaya_layanan = 2000;
$diskon = 0;
$kode_voucher = '';
$pesan = '';
$pesan_class = '';

// Data pembeli (dari session jika ada)
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nama_lengkap, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$nama = $user_data['nama_lengkap'] ?? '';
$telepon = '';
$alamat = '';
$pembayaran = 'Transfer Bank';
$ongkir = 12000;

// 🔄 HANDLE FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jumlah_produk = max(1, (int)($_POST['jumlah_produk'] ?? 1));
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kode_voucher = strtoupper(trim($_POST['voucher'] ?? ''));
    $pembayaran = $_POST['pembayaran'] ?? 'Transfer Bank';
    $ongkir = isset($_POST['pengiriman']) ? (int)$_POST['pengiriman'] : 12000;
    
    // Hitung total
    $harga_produk = $harga_satuan * $jumlah_produk;
    
    // Voucher logic
    if ($kode_voucher === 'FOODSAVE10') {
        $diskon = min(10000, $harga_produk * 0.1); // Max 10% atau 10k
    } elseif ($kode_voucher === 'FOODSAVE20') {
        $diskon = min(20000, $harga_produk * 0.2); // Max 20% atau 20k
    } else {
        $diskon = 0;
    }
    
    $total_bayar = $harga_produk + $biaya_layanan + $ongkir - $diskon;
    
    // Validasi
    if (empty($nama) || empty($telepon) || empty($alamat)) {
        $pesan = '⚠️ Mohon lengkapi data pembeli terlebih dahulu.';
        $pesan_class = 'error';
    } elseif ($jumlah_produk > $produk['stok']) {
        $pesan = "⚠️ Stok tidak mencukupi. Tersedia: {$produk['stok']} {$produk['satuan']}";
        $pesan_class = 'error';
    } else {
        // ✅ INSERT KE DATABASE
        $stmt = $conn->prepare("
            INSERT INTO transaksi 
            (user_id, penjual_id, produk_id, jumlah, total_harga, status, alamat_pengiriman, no_telepon, metode_pembayaran, ongkir, diskon, kode_voucher) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiidssssdds", 
            $user_id, 
            $penjual_id, 
            $produk_id, 
            $jumlah_produk, 
            $total_bayar,
            $alamat,
            $telepon,
            $pembayaran,
            $ongkir,
            $diskon,
            $kode_voucher
        );
        
        if ($stmt->execute()) {
            // Kurangi stok produk
            $stmt_update = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
            $stmt_update->bind_param("ii", $jumlah_produk, $produk_id);
            $stmt_update->execute();
            
            $pesan = '
                <div class="text-center">
                    <div class="text-5xl mb-3">✅</div>
                    <h3 class="text-lg font-bold text-green-700 mb-2">Pesanan Berhasil!</h3>
                    <p class="text-gray-600 text-sm mb-4">ID Pesanan: #'.str_pad($conn->insert_id, 6, '0', STR_PAD_LEFT).'</p>
                    <p class="text-gray-600 text-sm">Silakan lakukan pembayaran melalui <strong>'.htmlspecialchars($pembayaran).'</strong></p>
                    <p class="text-gray-600 text-sm mt-1">Total: <strong class="text-green-600">'.formatRupiah($total_bayar).'</strong></p>
                </div>
            ';
            $pesan_class = 'success';
            
            // Reset form setelah sukses
            $jumlah_produk = 1;
            $kode_voucher = '';
            $diskon = 0;
        } else {
            $pesan = '❌ Gagal memproses pesanan: ' . mysqli_error($conn);
            $pesan_class = 'error';
        }
    }
} else {
    // First load: hitung total default
    $harga_produk = $harga_satuan * $jumlah_produk;
    $total_bayar = $harga_produk + $biaya_layanan + $ongkir - $diskon;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .input-focus { @apply focus:ring-2 focus:ring-brand focus:border-brand outline-none transition; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- NAVBAR (Sama dengan index.php) -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-brand flex items-center gap-2">
                🌿 FoodSave
            </a>
            <div class="flex items-center gap-3">
                <a href="PromosiPage.php" class="text-sm text-gray-600 hover:text-brand font-medium">← Kembali Belanja</a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="max-w-5xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>
            <p class="text-gray-500 mt-2">Selesaikan pembelian makanan surplusmu dengan mudah</p>
        </div>

        <!-- Alert Message -->
        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success' 
                ? 'bg-green-50 border-green-200 text-green-700' 
                : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= $pesan ?>
            </div>
        <?php endif; ?>

        <!-- Checkout Grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- FORM KIRI (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Ringkasan Produk -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">📦</span>
                        Produk yang Dibeli
                    </h2>
                    
                    <div class="flex gap-4 p-4 bg-gray-50 rounded-lg">
                        <?php if ($produk['gambar_url']): ?>
                            <img src="<?= htmlspecialchars($produk['gambar_url']) ?>" 
                                 alt="<?= htmlspecialchars($produk['nama_produk']) ?>" 
                                 class="w-20 h-20 rounded-lg object-cover bg-white">
                        <?php else: ?>
                            <div class="w-20 h-20 rounded-lg bg-gray-200 flex items-center justify-center text-2xl">📷</div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($produk['nama_produk']) ?></h3>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($produk['nama_toko']) ?> • <?= htmlspecialchars($produk['kota']) ?></p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="font-bold text-brand"><?= formatRupiah($harga_satuan) ?></span>
                                <?php if ($produk['harga_diskon'] && $produk['harga_diskon'] < $produk['harga_asli']): ?>
                                    <span class="text-sm text-gray-400 line-through"><?= formatRupiah($produk['harga_asli']) ?></span>
                                    <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">
                                        -<?= round((1 - $produk['harga_diskon']/$produk['harga_asli'])*100) ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Qty Selector -->
                    <div class="mt-4 flex items-center gap-3">
                        <label class="text-sm font-medium text-gray-700">Jumlah:</label>
                        <div class="flex items-center border border-gray-300 rounded-lg">
                            <button type="button" onclick="updateQty(-1)" 
                                    class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-l-lg disabled:opacity-50"
                                    <?= $jumlah_produk <= 1 ? 'disabled' : '' ?>>−</button>
                            <input type="number" name="jumlah_produk" value="<?= $jumlah_produk ?>" min="1" max="<?= $produk['stok'] ?>"
                                   class="w-16 text-center border-0 focus:ring-0 text-sm font-medium" readonly>
                            <button type="button" onclick="updateQty(1)" 
                                    class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-r-lg disabled:opacity-50"
                                    <?= $jumlah_produk >= $produk['stok'] ? 'disabled' : '' ?>>+</button>
                        </div>
                        <span class="text-sm text-gray-500">Stok tersedia: <?= $produk['stok'] ?> <?= htmlspecialchars($produk['satuan']) ?></span>
                    </div>
                </div>

                <!-- Form Data -->
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="produk_id" value="<?= $produk_id ?>">
                    <input type="hidden" name="penjual_id" value="<?= $penjual_id ?>">
                    <input type="hidden" name="harga_satuan" value="<?= $harga_satuan ?>">
                    
                    <!-- Data Pembeli -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">👤</span>
                            Data Pembeli
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                                <input type="text" name="nama" value="<?= htmlspecialchars($nama) ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus"
                                       placeholder="Nama sesuai KTP">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WhatsApp *</label>
                                <input type="tel" name="telepon" value="<?= htmlspecialchars($telepon) ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus"
                                       placeholder="08xxxxxxxxxx">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Pengiriman *</label>
                                <textarea name="alamat" rows="3" required
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus resize-none"
                                          placeholder="Jl. Contoh No. 123, RT/RW, Kelurahan, Kecamatan, Kota"><?= htmlspecialchars($alamat) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Pengiriman -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">🚚</span>
                            Metode Pengiriman
                        </h2>
                        
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-brand transition <?= $ongkir === 12000 ? 'border-brand bg-brand/5' : '' ?>">
                                <input type="radio" name="pengiriman" value="12000" class="w-4 h-4 text-brand" <?= $ongkir === 12000 ? 'checked' : '' ?> onchange="hitungTotal()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">Kurir Instan</span>
                                    <p class="text-sm text-gray-500">Estimasi 1-3 jam • Area <?= htmlspecialchars($produk['kota']) ?></p>
                                </div>
                                <span class="font-semibold text-brand"><?= formatRupiah(12000) ?></span>
                            </label>
                            
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-brand transition <?= $ongkir === 8000 ? 'border-brand bg-brand/5' : '' ?>">
                                <input type="radio" name="pengiriman" value="8000" class="w-4 h-4 text-brand" <?= $ongkir === 8000 ? 'checked' : '' ?> onchange="hitungTotal()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">Same Day Delivery</span>
                                    <p class="text-sm text-gray-500">Tiba sebelum 21:00 • Area Jabodetabek</p>
                                </div>
                                <span class="font-semibold text-brand"><?= formatRupiah(8000) ?></span>
                            </label>
                            
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-brand transition <?= $ongkir === 0 ? 'border-brand bg-brand/5' : '' ?>">
                                <input type="radio" name="pengiriman" value="0" class="w-4 h-4 text-brand" <?= $ongkir === 0 ? 'checked' : '' ?> onchange="hitungTotal()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">Ambil Sendiri di Toko</span>
                                    <p class="text-sm text-gray-500">Gratis • <?= htmlspecialchars($produk['nama_toko']) ?></p>
                                </div>
                                <span class="font-semibold text-green-600">Gratis</span>
                            </label>
                        </div>
                    </div>

                    <!-- Pembayaran -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">💳</span>
                            Metode Pembayaran
                        </h2>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <?php $methods = ['Transfer Bank' => '🏦 BCA/Mandiri/BRI', 'E-Wallet' => '📱 GoPay/OVO/Dana', 'COD' => '💵 Bayar di Tempat']; ?>
                            <?php foreach ($methods as $val => $desc): ?>
                            <label class="p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-brand transition text-center <?= $pembayaran === $val ? 'border-brand bg-brand/5' : '' ?>">
                                <input type="radio" name="pembayaran" value="<?= $val ?>" class="hidden" <?= $pembayaran === $val ? 'checked' : '' ?>>
                                <div class="text-2xl mb-1"><?= explode(' ', $desc)[0] ?></div>
                                <div class="text-sm font-medium text-gray-900"><?= $val ?></div>
                                <div class="text-xs text-gray-500"><?= implode(' ', array_slice(explode(' ', $desc), 1)) ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Voucher -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">🎁</span>
                            Kode Voucher
                        </h2>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                            <p class="text-sm text-yellow-800">
                                <strong>Voucher tersedia:</strong><br>
                                <code class="bg-white px-2 py-0.5 rounded">FOODSAVE10</code> = Diskon <?= formatRupiah(10000) ?><br>
                                <code class="bg-white px-2 py-0.5 rounded">FOODSAVE20</code> = Diskon <?= formatRupiah(20000) ?>
                            </p>
                        </div>
                        
                        <div class="flex gap-2">
                            <input type="text" name="voucher" value="<?= htmlspecialchars($kode_voucher) ?>" placeholder="Masukkan kode voucher"
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg input-focus uppercase">
                            <button type="submit" name="apply_voucher"
                                    class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">
                                Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                            class="w-full py-4 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl transition shadow-lg hover:shadow-xl text-lg">
                        Bayar Sekarang • <span id="btnTotal"><?= formatRupiah($total_bayar) ?></span>
                    </button>
                    
                    <p class="text-center text-sm text-gray-500">
                        🔒 Data kamu aman dan terenkripsi
                    </p>
                </form>
            </div>

            <!-- SUMMARY KANAN (1/3) - Sticky -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Pesanan</h2>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Produk</span>
                            <span class="font-medium"><?= htmlspecialchars($produk['nama_produk']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Jumlah</span>
                            <span class="font-medium"><?= $jumlah_produk ?> <?= htmlspecialchars($produk['satuan']) ?></span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-dashed">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="font-medium" id="subtotalDisplay"><?= formatRupiah($harga_produk) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Biaya Layanan</span>
                            <span class="font-medium"><?= formatRupiah($biaya_layanan) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Pengiriman</span>
                            <span class="font-medium" id="ongkirDisplay"><?= $ongkir === 0 ? 'Gratis' : formatRupiah($ongkir) ?></span>
                        </div>
                        <div class="flex justify-between text-green-600">
                            <span>Diskon</span>
                            <span id="diskonDisplay">- <?= formatRupiah($diskon) ?></span>
                        </div>
                    </div>
                    
                    <div class="flex justify-between text-lg font-bold text-gray-900 pt-4 mt-4 border-t-2 border-brand">
                        <span>Total Bayar</span>
                        <span id="totalDisplay"><?= formatRupiah($total_bayar) ?></span>
                    </div>
                    
                    <!-- Progress Bar (opsional) -->
                    <div class="mt-6 pt-4 border-t">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>Proses Pesanan</span>
                            <span>3/3</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-brand w-full rounded-full"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span class="text-brand font-medium">✓ Keranjang</span>
                            <span class="text-brand font-medium">✓ Data</span>
                            <span class="text-brand font-medium">✓ Bayar</span>
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

    <!-- JAVASCRIPT -->
    <script>
        // Data dari PHP
        const hargaSatuan = <?= $harga_satuan ?>;
        const biayaLayanan = <?= $biaya_layanan ?>;
        let diskon = <?= $diskon ?>;
        const stokMaks = <?= $produk['stok'] ?>;
        
        // Update qty
        function updateQty(delta) {
            const input = document.querySelector('input[name="jumlah_produk"]');
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (val > stokMaks) val = stokMaks;
            input.value = val;
            hitungTotal();
            
            // Update button states
            document.querySelectorAll('button[onclick*="updateQty"]')[0].disabled = val <= 1;
            document.querySelectorAll('button[onclick*="updateQty"]')[1].disabled = val >= stokMaks;
        }
        
        // Hitung total realtime
        function hitungTotal() {
            const qty = parseInt(document.querySelector('input[name="jumlah_produk"]').value);
            const ongkir = parseInt(document.querySelector('input[name="pengiriman"]:checked').value);
            
            const subtotal = hargaSatuan * qty;
            const total = subtotal + biayaLayanan + ongkir - diskon;
            
            // Update display
            document.getElementById('subtotalDisplay').textContent = formatRupiah(subtotal);
            document.getElementById('ongkirDisplay').textContent = ongkir === 0 ? 'Gratis' : formatRupiah(ongkir);
            document.getElementById('diskonDisplay').textContent = '- ' + formatRupiah(diskon);
            document.getElementById('totalDisplay').textContent = formatRupiah(total);
            document.getElementById('btnTotal').textContent = formatRupiah(total);
        }
        
        // Format Rupiah
        function formatRupiah(angka) {
            return "Rp " + angka.toLocaleString("id-ID");
        }
        
        // Auto hitung saat load
        document.addEventListener('DOMContentLoaded', hitungTotal);
        
        // Auto select radio styling
        document.querySelectorAll('input[type="radio"][name="pengiriman"], input[type="radio"][name="pembayaran"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove active from siblings
                this.closest('.grid')?.querySelectorAll('label').forEach(l => l.classList.remove('border-brand', 'bg-brand/5'));
                this.closest('div.space-y-3')?.querySelectorAll('label').forEach(l => l.classList.remove('border-brand', 'bg-brand/5'));
                // Add active to selected
                this.closest('label').classList.add('border-brand', 'bg-brand/5');
                if (this.name === 'pengiriman') hitungTotal();
            });
        });
    </script>

</body>
</html>