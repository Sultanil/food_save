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
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { jakarta: ["'Plus Jakarta Sans'", 'sans-serif'] },
          colors: { brand: '#16a34a', branddark: '#0d7a3e' }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }

    /* Page transition */
    .page { display: none; animation: fade .3s ease; }
    .page.active { display: block; }
    @keyframes fade {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: none; }
    }

    /* Product grid responsive */
    #grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      gap: 16px;
      max-width: 1100px;
      margin: 30px auto;
      padding: 0 24px;
    }

    /* Card hover */
    .card { transition: transform .2s; }
    .card:hover { transform: translateY(-4px); }
  </style>
</head>
<body class="bg-slate-50">

<!-- ═══ NAVBAR ═══ -->
<nav class="bg-white px-7 py-3 flex items-center gap-5 shadow-sm sticky top-0 z-10">
  <a href="#" class="font-extrabold text-green-600 text-lg mr-auto no-underline">🌿 FoodSave</a>
  <a href="Index.php"  class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Beranda</a>
  <a href="#"             class="text-green-600 font-semibold text-sm no-underline">Promo</a>
  <a href="PromosiPage.php"  class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Cari Makanan</a>
  <a href="PromosiPage.php"  class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🛒 Beli</a>
  <a href="Index.php"  class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🗑️ Jual Surplus</a>
  <a href="LoginPage.php" class="bg-green-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-700">👤 Masuk</a>
</nav>

<!-- ═══ HOME PAGE ═══ -->
<div id="home" class="page active">

  <!-- Hero -->
  <div class="text-center text-white py-12 px-7" style="background: linear-gradient(135deg,#0d7a3e,#22c55e)">
    <h1 class="text-3xl font-extrabold mb-2">
      Hemat Lebih Banyak, <span class="text-yellow-300">Selamatkan</span> Lebih Banyak!
    </h1>
    <p class="opacity-90">Diskon hingga 70% untuk makanan surplus berkualitas di sekitarmu 🌍</p>
  </div>

  <!-- Grid produk (diisi JS) -->
  <div id="grid"></div>

  <!-- Footer -->
  <footer class="text-center py-4 text-slate-400 text-xs mt-5">
    © 2024 <b class="text-green-600">FoodSave</b> — Selamatkan Makanan, Hemat Lebih Banyak 🌿
  </footer>
</div>

<!-- ═══ DETAIL PAGE ═══ -->
<div id="detail" class="page">
  <div class="max-w-lg mx-auto my-16 bg-white rounded-2xl p-9 shadow-xl text-center">
    <img id="dImg" src="" alt="" class="w-full h-52 object-cover rounded-xl mb-5"/>
    <h2 id="dName"   class="text-2xl font-extrabold text-slate-800 mb-2"></h2>
    <p  id="dSeller" class="text-slate-500 text-sm mb-4"></p>
    <span id="dPrice" class="block text-2xl font-extrabold text-green-600 mb-6"></span>

    <!-- Form POST ke diri sendiri -->
    <form id="formBeli" method="POST" action="PromosiPage.php">
      <input type="hidden" name="produk" id="fProduk"/>
      <input type="hidden" name="harga"  id="fHarga"/>
      <input type="hidden" name="seller" id="fSeller"/>
      <div class="flex justify-center gap-3">
        <button type="button" onclick="goHome()"
          class="px-6 py-2 border-2 border-green-600 text-green-600 font-bold rounded-xl hover:bg-green-50 cursor-pointer">
          ← Kembali
        </button>
        <button type="submit"
          class="px-6 py-2 bg-yellow-400 text-gray-900 font-extrabold rounded-xl hover:bg-yellow-300 cursor-pointer">
          Beli Sekarang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ JAVASCRIPT ═══ -->
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
    <div class="card bg-white rounded-2xl shadow-md overflow-hidden">
      <img src="${p.img}" alt="${p.name}" class="w-full h-40 object-cover"/>
      <div class="p-3">
        <span class="text-xs font-extrabold bg-red-500 text-white px-2 py-0.5 rounded inline-block mb-1">${p.disc}</span>
        <div class="font-bold text-slate-800 text-sm mb-1">${p.name}</div>
        <div class="flex gap-2 items-center mb-2">
          <span class="font-extrabold text-green-600">${p.nw}</span>
          <span class="text-slate-400 line-through text-xs">${p.ol}</span>
        </div>
        <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm cursor-pointer"
          onclick='openDetail(${JSON.stringify(p)})'>🛒 Beli Sekarang</button>
      </div>
    </div>`;
});

// Buka halaman detail
function openDetail(p) {
  document.getElementById('dImg').src            = p.img;
  document.getElementById('dName').textContent   = p.name;
  document.getElementById('dSeller').textContent = p.seller;
  document.getElementById('dPrice').textContent  = p.nw;

  document.getElementById('fProduk').value = p.name;
  document.getElementById('fHarga').value  = p.nw.replace(/[^0-9]/g, '');
  document.getElementById('fSeller').value = p.seller;

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