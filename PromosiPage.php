<?php
// ── SIMPAN PESANAN JIKA ADA DATA POST ────────────────────────
$pesanSimpan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk  = htmlspecialchars($_POST['produk']  ?? '-');
    $harga   = htmlspecialchars($_POST['harga']   ?? '-');
    $seller  = htmlspecialchars($_POST['seller']  ?? '-');
    $waktu   = date('d-m-Y H:i:s');

    $baris  = "============================\n";
    $baris .= "Waktu    : $waktu\n";
    $baris .= "Produk   : $produk\n";
    $baris .= "Penjual  : $seller\n";
    $baris .= "Harga    : Rp $harga\n";
    $baris .= "============================\n\n";

    $hasil = file_put_contents('orders.txt', $baris, FILE_APPEND | LOCK_EX);
    $pesanSimpan = $hasil !== false ? 'berhasil' : 'gagal';

    // Redirect ke HalamanTransaksi.php setelah disimpan
    header('Location: HalamanTransaksi.php?produk=' . urlencode($produk) .
           '&harga=' . urlencode($harga) . '&jumlah=1&status=' . $pesanSimpan);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FoodSave – Promo</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="PromosiPage.css"/>
</head>
<body>

<nav>
  <a class="logo" href="#"> FoodSave</a>
  <a href="FoodSave.php">Beranda</a>
  <a href="#" style="color:#16a34a">Promo</a>
  <a href="FoodSave.php">Cari Makanan</a>
  <a href="FoodSave.php" class="btn o">🛒 Beli</a>
  <a href="FoodSave.php" class="btn o">🗑️ Jual Surplus</a>
  <a href="loginPage.php" class="btn s">👤 Masuk</a>
</nav>

<!-- HOME PAGE -->
<div id="home" class="page active">
  <div class="hero">
    <h1>Hemat Lebih Banyak, <span>Selamatkan</span> Lebih Banyak!</h1>
    <p>Diskon hingga 70% untuk makanan surplus berkualitas di sekitarmu 🌍</p>
  </div>
  <div class="grid" id="grid"></div>
  <footer>© 2024 <b style="color:#16a34a">FoodSave</b> — Selamatkan Makanan, Hemat Lebih Banyak 🌿</footer>
</div>

<!-- DETAIL PAGE -->
<div id="detail" class="page">
  <div class="detail">
    <img id="dImg" src="" alt=""/>
    <h2 id="dName"></h2>
    <p id="dSeller"></p>
    <span class="nw" id="dPrice"></span>

    <!-- Form POST ke PromosiPage.php (dirinya sendiri) -->
    <form id="formBeli" method="POST" action="PromosiPage.php">
      <input type="hidden" name="produk"  id="fProduk"/>
      <input type="hidden" name="harga"   id="fHarga"/>
      <input type="hidden" name="seller"  id="fSeller"/>
      <div>
        <button type="button" class="back" onclick="goHome()">← Kembali</button>
        <button type="submit" class="buy">Beli Sekarang</button>
      </div>
    </form>
  </div>
</div>

<script>
const P = [
  {img:"https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=400&auto=format&fit=crop",disc:"-30%",name:"Daging Ayam Segar Surplus 1kg",seller:"📍 Jaya Ternak – Jombang",nw:"Rp 28.000",ol:"Rp 40.000"},
  {img:"https://images.unsplash.com/photo-1569127959161-2b1297b2d9a6?w=400&auto=format&fit=crop",disc:"-70%",name:"Telur Ayam Surplus 1 kg",seller:"📍 Peternakan Sukses – Semarang",nw:"Rp 15.000",ol:"Rp 50.000"},
  {img:"https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=400&auto=format&fit=crop",disc:"-25%",name:"Apel Malang Surplus 1kg",seller:"📍 Petani Apel – Batu, Malang",nw:"Rp 22.000",ol:"Rp 29.000"},
  {img:"https://images.unsplash.com/photo-1550583724-b2692b85b150?w=400&auto=format&fit=crop",disc:"-30%",name:"Susu Sapi Segar Surplus 1 Liter",seller:"📍 Peternakan Sejuk – Pacitan",nw:"Rp 8.000",ol:"Rp 12.000"},
  {img:"https://images.unsplash.com/photo-1526346698789-22fd84314424?w=400&auto=format&fit=crop",disc:"-40%",name:"Cabai Merah Keriting Surplus Segar 250g",seller:"📍 Kebun Segar – Ngawi",nw:"Rp 12.000",ol:"Rp 20.000"},
  {img:"https://images.unsplash.com/photo-1587049352851-8d4e89133924?w=400&auto=format&fit=crop",disc:"-50%",name:"Madu Surplus 150g",seller:"📍 Tresno Madu – Madiun",nw:"Rp 14.000",ol:"Rp 28.000"},
];

// Render kartu produk
const g = document.getElementById('grid');
P.forEach(p => {
  g.innerHTML += `
    <div class="card">
      <img src="${p.img}" alt="${p.name}"/>
      <div class="cb">
        <span class="disc">${p.disc}</span>
        <div class="name">${p.name}</div>
        <div class="prices">
          <span class="nw">${p.nw}</span>
          <span class="ol">${p.ol}</span>
        </div>
        <button class="add" onclick='openDetail(${JSON.stringify(p)})'>🛒 Beli Sekarang</button>
      </div>
    </div>`;
});

// Buka halaman detail & isi hidden input form
function openDetail(p) {
  document.getElementById('dImg').src              = p.img;
  document.getElementById('dName').textContent     = p.name;
  document.getElementById('dSeller').textContent   = p.seller;
  document.getElementById('dPrice').textContent    = p.nw;

  // Isi hidden field agar dikirim ke PHP saat form disubmit
  document.getElementById('fProduk').value  = p.name;
  document.getElementById('fHarga').value   = p.nw.replace(/[^0-9]/g, '');
  document.getElementById('fSeller').value  = p.seller;

  document.getElementById('home').classList.remove('active');
  document.getElementById('detail').classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Kembali ke home
function goHome() {
  document.getElementById('detail').classList.remove('active');
  document.getElementById('home').classList.add('active');
}
</script>

</body>
</html>