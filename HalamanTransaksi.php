<?php

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}


$produk = isset($_GET['produk']) ? htmlspecialchars($_GET['produk']) : 'Produk belum dipilih';
$hargaSatuan = isset($_GET['harga']) ? (int)$_GET['harga'] : 0;
$jumlahProduk = isset($_GET['jumlah']) ? (int)$_GET['jumlah'] : 1;

// Set default values
$biayaLayanan = 2000;
$diskon = 0;
$kodeVoucher = '';
$pesan = '';
$pesanClass = '';

// Data form (default)
$nama = '';
$telepon = '';
$alamat = '';
$pembayaran = 'Transfer Bank';
$ongkir = 12000;

// Proses form saat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari POST
    $produk = isset($_POST['produk']) ? htmlspecialchars($_POST['produk']) : $produk;
    $hargaSatuan = isset($_POST['harga_satuan']) ? (int)$_POST['harga_satuan'] : $hargaSatuan;
    $jumlahProduk = isset($_POST['jumlah_produk']) ? (int)$_POST['jumlah_produk'] : $jumlahProduk;
    
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kodeVoucher = strtoupper(trim($_POST['voucher'] ?? ''));
    $pembayaran = $_POST['pembayaran'] ?? 'Transfer Bank';
    $ongkir = isset($_POST['pengiriman']) ? (int)$_POST['pengiriman'] : 12000;
    
    // Hitung harga produk
    $hargaProduk = $hargaSatuan * $jumlahProduk;
    
    // Logic voucher
    if ($kodeVoucher === 'FOODSAVE10') {
        $diskon = 10000;
    } elseif ($kodeVoucher === 'FOODSAVE20') {
        $diskon = 20000;
    } elseif (!empty($kodeVoucher)) {
        $diskon = 0; // Voucher tidak valid
    } else {
        $diskon = 0; // Tidak ada voucher
    }
    
    // Hitung total
    $totalBayar = $hargaProduk + $biayaLayanan + $ongkir - $diskon;
    
    // Validasi form
    if (empty($nama) || empty($telepon) || empty($alamat)) {
        $pesan = 'Mohon lengkapi data pembeli terlebih dahulu.';
        $pesanClass = 'error';
    } else {
        // Tentukan nama pengiriman
        $namaPengiriman = 'Kurir Instan';
        if ($ongkir === 8000) {
            $namaPengiriman = 'Same Day';
        } elseif ($ongkir === 0) {
            $namaPengiriman = 'Ambil di Toko';
        }
        
        // Format nomor telepon
        $teleponFormat = preg_replace('/[^0-9]/', '', $telepon);
        if (substr($teleponFormat, 0, 1) === '0') {
            $teleponFormat = '+62' . substr($teleponFormat, 1);
        }
        
        $pesan = 
            "Transaksi Berhasil!<br><br>" .
            "<strong>Produk:</strong> " . $produk . "<br>" .
            "<strong>Jumlah:</strong> " . $jumlahProduk . " pcs<br>" .
            "<strong>Nama:</strong> " . $nama . "<br>" .
            "<strong>Telepon:</strong> " . $teleponFormat . "<br>" .
            "<strong>Pembayaran:</strong> " . $pembayaran . "<br>" .
            "<strong>Pengiriman:</strong> " . $namaPengiriman . "<br>" .
            "<strong>Total Bayar:</strong> " . formatRupiah($totalBayar);
        $pesanClass = 'success';
    }
} else {
    // Hitung default saat pertama load (GET)
    $hargaProduk = $hargaSatuan * $jumlahProduk;
    $totalBayar = $hargaProduk + $biayaLayanan + $ongkir - $diskon;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi - FoodSave</title>
    <link rel="stylesheet" href="../assets/css/loginstyle.css">
    <link rel="stylesheet" href="HalamanTransaksi.css">
</head>
<body>

    <!-- Header -->
    <section class="hero">
        <div class="container">
            <h1>Halaman Transaksi</h1>
            <p>Selesaikan pembelian makanan surplusmu dengan mudah</p>
        </div>
    </section>

    <div class="container">
        <!-- Pesan Sukses/Error -->
        <?php if ($pesan !== ''): ?>
            <div class="message <?php echo $pesanClass; ?>">
                <?php echo $pesan; ?>
            </div>
        <?php endif; ?>

        <div class="checkout">
            <!-- Form Data Pembeli -->
            <div class="card">
                <h2>Data Pembeli</h2>

                <form method="POST" action="">
                    <!-- Hidden Fields -->
                    <input type="hidden" name="produk" value="<?php echo htmlspecialchars($produk); ?>">
                    <input type="hidden" name="harga_satuan" value="<?php echo $hargaSatuan; ?>">
                    <input type="hidden" name="jumlah_produk" value="<?php echo $jumlahProduk; ?>">

                    <div class="form-group">
                        <label for="nama">Nama Lengkap *</label>
                        <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" 
                               value="<?php echo htmlspecialchars($nama); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="telepon">Nomor Telepon *</label>
                        <input type="tel" id="telepon" name="telepon" placeholder="08xxxxxxxxxx" 
                               value="<?php echo htmlspecialchars($telepon); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="alamat">Alamat Lengkap *</label>
                        <textarea id="alamat" name="alamat" placeholder="Masukkan alamat lengkap" 
                                  required><?php echo htmlspecialchars($alamat); ?></textarea>
                    </div>

                    <h2>Metode Pengiriman</h2>
                    <label class="option-box">
                        <input type="radio" name="pengiriman" value="12000" <?php echo $ongkir === 12000 ? 'checked' : ''; ?>>
                        <strong>Kurir Instan</strong> - Sampai hari ini
                        <br><small>Biaya: <?php echo formatRupiah(12000); ?></small>
                    </label>
                    
                    <label class="option-box">
                        <input type="radio" name="pengiriman" value="8000" <?php echo $ongkir === 8000 ? 'checked' : ''; ?>>
                        <strong>Same Day</strong> - Sampai sore ini
                        <br><small>Biaya: <?php echo formatRupiah(8000); ?></small>
                    </label>
                    


                    <h2>Metode Pembayaran</h2>
                    <label class="option-box">
                        <input type="radio" name="pembayaran" value="Transfer Bank" 
                               <?php echo $pembayaran === 'Transfer Bank' ? 'checked' : ''; ?>>
                        <strong>Transfer Bank</strong>
                        <br><small>BCA, Mandiri, BNI, BRI</small>
                    </label>
                    
                    <label class="option-box">
                        <input type="radio" name="pembayaran" value="E-Wallet" 
                               <?php echo $pembayaran === 'E-Wallet' ? 'checked' : ''; ?>>
                        <strong>E-Wallet</strong>
                        <br><small>GoPay, OVO, Dana, ShopeePay</small>
                    </label>

                    <h2>Kode Voucher</h2>
                    <div class="voucher-info">
                        <strong>Voucher Tersedia:</strong><br>
                        FOODSAVE10 = Diskon <?php echo formatRupiah(10000); ?><br>
                        FOODSAVE20 = Diskon <?php echo formatRupiah(20000); ?>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" id="voucher" name="voucher" 
                               placeholder="Masukkan kode voucher" 
                               value="<?php echo htmlspecialchars($kodeVoucher); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">
                        Terapkan Voucher dan Konfirmasi
                    </button>
                </form>
            </div>

            <!-- Ringkasan Pesanan -->
            <div class="card summary">
                <h2>Ringkasan Pesanan</h2>
                
                <p>
                    <span>Produk</span>
                    <span><?php echo htmlspecialchars($produk); ?></span>
                </p>
                
                <p>
                    <span>Jumlah</span>
                    <span><?php echo $jumlahProduk; ?> pcs</span>
                </p>
                
                <p>
                    <span>Harga Produk</span>
                    <span><?php echo formatRupiah($hargaProduk); ?></span>
                </p>
                
                <p>
                    <span>Biaya Layanan</span>
                    <span><?php echo formatRupiah($biayaLayanan); ?></span>
                </p>
                
                <p>
                    <span>Biaya Pengiriman</span>
                    <span id="ongkirDisplay"><?php echo $ongkir === 0 ? 'Gratis' : formatRupiah($ongkir); ?></span>
                </p>
                
                <p class="diskon-row">
                    <span>Diskon</span>
                    <span>- <?php echo formatRupiah($diskon); ?></span>
                </p>
                
                <p class="total">
                    <span>Total Bayar</span>
                    <span id="totalBayarDisplay"><?php echo formatRupiah($totalBayar); ?></span>
                </p>

                <p class="note">
                    Data kamu aman dan terenkripsi
                </p>
                
                <button type="submit" form="" class="btn" onclick="alert('Silakan lengkapi form dan klik Terapkan Voucher dan Konfirmasi')">
                    Bayar Sekarang
                </button>
            </div>
        </div>
    </div>

    <script>
        const hargaProduk  = <?php echo $hargaProduk; ?>;
        const biayaLayanan = <?php echo $biayaLayanan; ?>;
        const diskon       = <?php echo $diskon; ?>;

        function formatRupiah(angka) {
            return "Rp " + angka.toLocaleString("id-ID");
        }

        function hitungTotal() {
            const ongkir = parseInt(document.querySelector('input[name="pengiriman"]:checked').value);
            const total  = hargaProduk + biayaLayanan + ongkir - diskon;

            document.getElementById("ongkirDisplay").textContent     = formatRupiah(ongkir);
            document.getElementById("totalBayarDisplay").textContent = formatRupiah(total);
        }

        document.querySelectorAll('input[name="pengiriman"]').forEach(function(el) {
            el.addEventListener("change", hitungTotal);
        });
    </script>

</body>
</html>
