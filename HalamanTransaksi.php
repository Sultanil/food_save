<?php

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

$produk = isset($_GET['produk']) ? htmlspecialchars($_GET['produk']) : 'Produk belum dipilih';
$hargaSatuan = isset($_GET['harga']) ? (int)$_GET['harga'] : 0;
$jumlahProduk = isset($_GET['jumlah']) ? (int)$_GET['jumlah'] : 1;

$biayaLayanan = 2000;
$diskon = 0;
$kodeVoucher = '';
$pesan = '';
$pesanClass = '';

$nama = '';
$telepon = '';
$alamat = '';
$pembayaran = 'Transfer Bank';
$ongkir = 12000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk = isset($_POST['produk']) ? htmlspecialchars($_POST['produk']) : $produk;
    $hargaSatuan = isset($_POST['harga_satuan']) ? (int)$_POST['harga_satuan'] : $hargaSatuan;
    $jumlahProduk = isset($_POST['jumlah_produk']) ? (int)$_POST['jumlah_produk'] : $jumlahProduk;

    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kodeVoucher = strtoupper(trim($_POST['voucher'] ?? ''));
    $pembayaran = $_POST['pembayaran'] ?? 'Transfer Bank';
    $ongkir = isset($_POST['pengiriman']) ? (int)$_POST['pengiriman'] : 12000;

    $hargaProduk = $hargaSatuan * $jumlahProduk;

    if ($kodeVoucher === 'FOODSAVE10') {
        $diskon = 10000;
    } elseif ($kodeVoucher === 'FOODSAVE20') {
        $diskon = 20000;
    } else {
        $diskon = 0;
    }

    $totalBayar = $hargaProduk + $biayaLayanan + $ongkir - $diskon;

    if (empty($nama) || empty($telepon) || empty($alamat)) {
        $pesan = 'Mohon lengkapi data pembeli terlebih dahulu.';
        $pesanClass = 'error';
    } else {
        $namaPengiriman = 'Kurir Instan';
        if ($ongkir === 8000) $namaPengiriman = 'Same Day';
        elseif ($ongkir === 0) $namaPengiriman = 'Ambil di Toko';

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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-gradient-to-br from-[#f0f9f0] to-[#e8f5e9] m-0 p-0 text-[#333] min-h-screen">

    <!-- Header -->
    <section class="bg-white py-[30px] text-center shadow-[0_2px_5px_rgba(0,0,0,0.05)]">
        <div class="w-[90%] max-w-[1100px] mx-auto">
            <h1 class="mb-[10px] text-[#4CAF50]">Halaman Transaksi</h1>
            <p class="m-0 text-[#666]">Selesaikan pembelian makanan surplusmu dengan mudah</p>
        </div>
    </section>

    <div class="w-[90%] max-w-[1100px] mx-auto">

        <!-- Pesan Sukses/Error -->
        <?php if ($pesan !== ''): ?>
            <div class="p-[15px] rounded-lg my-5 mx-auto font-medium max-w-[1100px]
                <?php echo $pesanClass === 'success'
                    ? 'bg-[#e8f5e9] text-[#2e7d32] border-2 border-[#4CAF50]'
                    : 'bg-[#ffebee] text-[#c62828] border-2 border-[#F44336]'; ?>">
                <?php echo $pesan; ?>
            </div>
        <?php endif; ?>

        <!-- Checkout Grid -->
        <div class="grid grid-cols-1 md:grid-cols-[2fr_1fr] gap-5 my-[30px] mx-auto">

            <!-- Form Data Pembeli -->
            <div class="bg-white p-[25px] rounded-xl shadow-[0_4px_15px_rgba(0,0,0,0.1)]">
                <h2 class="mt-0 text-[#4CAF50] text-[18px] border-b-2 border-[#e8f5e9] pb-[10px] mb-5">Data Pembeli</h2>

                <form method="POST" action="">
                    <input type="hidden" name="produk" value="<?php echo htmlspecialchars($produk); ?>">
                    <input type="hidden" name="harga_satuan" value="<?php echo $hargaSatuan; ?>">
                    <input type="hidden" name="jumlah_produk" value="<?php echo $jumlahProduk; ?>">

                    <div class="mb-[15px]">
                        <label for="nama" class="block mb-[6px] font-semibold text-[#333]">Nama Lengkap *</label>
                        <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap"
                               value="<?php echo htmlspecialchars($nama); ?>" required
                               class="w-full p-3 border-2 border-[#e0e0e0] rounded-lg box-border text-sm transition-colors duration-300 focus:outline-none focus:border-[#4CAF50]">
                    </div>

                    <div class="mb-[15px]">
                        <label for="telepon" class="block mb-[6px] font-semibold text-[#333]">Nomor Telepon *</label>
                        <input type="tel" id="telepon" name="telepon" placeholder="08xxxxxxxxxx"
                               value="<?php echo htmlspecialchars($telepon); ?>" required
                               class="w-full p-3 border-2 border-[#e0e0e0] rounded-lg box-border text-sm transition-colors duration-300 focus:outline-none focus:border-[#4CAF50]">
                    </div>

                    <div class="mb-[15px]">
                        <label for="alamat" class="block mb-[6px] font-semibold text-[#333]">Alamat Lengkap *</label>
                        <textarea id="alamat" name="alamat" placeholder="Masukkan alamat lengkap" required
                                  class="w-full p-3 border-2 border-[#e0e0e0] rounded-lg box-border text-sm transition-colors duration-300 focus:outline-none focus:border-[#4CAF50] resize-y min-h-[80px]"><?php echo htmlspecialchars($alamat); ?></textarea>
                    </div>

                    <h2 class="mt-0 text-[#4CAF50] text-[18px] border-b-2 border-[#e8f5e9] pb-[10px] mb-5">Metode Pengiriman</h2>

                    <label class="border-2 border-[#e0e0e0] rounded-lg p-3 mb-[10px] cursor-pointer transition-all duration-300 hover:border-[#4CAF50] hover:bg-[#f0f9f0] block">
                        <input type="radio" name="pengiriman" value="12000" class="mr-[10px]" <?php echo $ongkir === 12000 ? 'checked' : ''; ?>>
                        <strong>Kurir Instan</strong> - Estimasi 1–3 jam
                        <br><small>Biaya: <?php echo formatRupiah(12000); ?></small>
                    </label>

                    <label class="border-2 border-[#e0e0e0] rounded-lg p-3 mb-[10px] cursor-pointer transition-all duration-300 hover:border-[#4CAF50] hover:bg-[#f0f9f0] block">
                        <input type="radio" name="pengiriman" value="8000" class="mr-[10px]" <?php echo $ongkir === 8000 ? 'checked' : ''; ?>>
                        <strong>Kirim Hari Ini</strong> - Tiba sebelum pukul 21.00
                        <br><small>Biaya: <?php echo formatRupiah(8000); ?></small>
                    </label>

                    <h2 class="mt-0 text-[#4CAF50] text-[18px] border-b-2 border-[#e8f5e9] pb-[10px] mb-5">Metode Pembayaran</h2>

                    <label class="border-2 border-[#e0e0e0] rounded-lg p-3 mb-[10px] cursor-pointer transition-all duration-300 hover:border-[#4CAF50] hover:bg-[#f0f9f0] block">
                        <input type="radio" name="pembayaran" value="Transfer Bank" class="mr-[10px]" <?php echo $pembayaran === 'Transfer Bank' ? 'checked' : ''; ?>>
                        <strong>Transfer Bank</strong>
                        <br><small>BCA, Mandiri, BNI, BRI</small>
                    </label>

                    <label class="border-2 border-[#e0e0e0] rounded-lg p-3 mb-[10px] cursor-pointer transition-all duration-300 hover:border-[#4CAF50] hover:bg-[#f0f9f0] block">
                        <input type="radio" name="pembayaran" value="E-Wallet" class="mr-[10px]" <?php echo $pembayaran === 'E-Wallet' ? 'checked' : ''; ?>>
                        <strong>E-Wallet</strong>
                        <br><small>GoPay, OVO, Dana, ShopeePay</small>
                    </label>

                    <h2 class="mt-0 text-[#4CAF50] text-[18px] border-b-2 border-[#e8f5e9] pb-[10px] mb-5">Kode Voucher</h2>

                    <div class="bg-[#fff9c4] p-[10px] rounded-lg text-[13px] text-[#333] mb-[15px]">
                        <strong class="text-[#4CAF50]">Voucher Tersedia:</strong><br>
                        FOODSAVE10 = Diskon <?php echo formatRupiah(10000); ?><br>
                        FOODSAVE20 = Diskon <?php echo formatRupiah(20000); ?>
                    </div>

                    <div class="mb-[15px]">
                        <input type="text" id="voucher" name="voucher"
                               placeholder="Masukkan kode voucher"
                               value="<?php echo htmlspecialchars($kodeVoucher); ?>"
                               class="w-full p-3 border-2 border-[#e0e0e0] rounded-lg box-border text-sm transition-colors duration-300 focus:outline-none focus:border-[#4CAF50]">
                    </div>

                    <button type="submit"
                            class="inline-block w-full py-[14px] border-2 border-[#4CAF50] rounded-lg bg-white text-[#4CAF50] text-base font-semibold cursor-pointer mt-[10px] transition-colors duration-300 hover:bg-[#f0f9f0] box-border">
                        Terapkan Voucher dan Konfirmasi
                    </button>
                </form>
            </div>

            <!-- Ringkasan Pesanan -->
            <div class="bg-white p-[25px] rounded-xl shadow-[0_4px_15px_rgba(0,0,0,0.1)]">
                <h2 class="mt-0 text-[#4CAF50] text-[18px] border-b-2 border-[#e8f5e9] pb-[10px] mb-5">Ringkasan Pesanan</h2>

                <p class="flex justify-between my-3 pb-3 border-b border-dashed border-[#e0e0e0]">
                    <span>Produk</span>
                    <span><?php echo htmlspecialchars($produk); ?></span>
                </p>
                <p class="flex justify-between my-3 pb-3 border-b border-dashed border-[#e0e0e0]">
                    <span>Jumlah</span>
                    <span><?php echo $jumlahProduk; ?> pcs</span>
                </p>
                <p class="flex justify-between my-3 pb-3 border-b border-dashed border-[#e0e0e0]">
                    <span>Harga Produk</span>
                    <span><?php echo formatRupiah($hargaProduk); ?></span>
                </p>
                <p class="flex justify-between my-3 pb-3 border-b border-dashed border-[#e0e0e0]">
                    <span>Biaya Layanan</span>
                    <span><?php echo formatRupiah($biayaLayanan); ?></span>
                </p>
                <p class="flex justify-between my-3 pb-3 border-b border-dashed border-[#e0e0e0]">
                    <span>Biaya Pengiriman</span>
                    <span id="ongkirDisplay"><?php echo $ongkir === 0 ? 'Gratis' : formatRupiah($ongkir); ?></span>
                </p>
                <p class="flex justify-between my-3 pb-3 border-b border-dashed border-[#e0e0e0] text-[#4CAF50]">
                    <span>Diskon</span>
                    <span>- <?php echo formatRupiah($diskon); ?></span>
                </p>
                <p class="flex justify-between text-[20px] font-bold text-[#4CAF50] border-t-2 border-[#4CAF50] pt-[15px] mt-[15px] border-b-0 my-3 pb-0">
                    <span>Total Bayar</span>
                    <span id="totalBayarDisplay"><?php echo formatRupiah($totalBayar); ?></span>
                </p>

                <p class="text-[13px] text-[#888] mt-[15px] text-center">
                    Data kamu aman dan terenkripsi
                </p>

                <button type="submit" form=""
                        onclick="alert('Silakan lengkapi form dan klik Terapkan Voucher dan Konfirmasi')"
                        class="inline-block w-full py-[14px] border-none rounded-lg bg-[#4CAF50] text-white text-base font-semibold cursor-pointer mt-[10px] transition-colors duration-300 hover:bg-[#43a047] box-border">
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