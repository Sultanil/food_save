<?php
session_start();
include 'koneksi.php';

// 🔐 Cek login untuk akses tertentu (opsional)
// if (!isset($_SESSION['sudah_login'])) {
//   header("Location: LoginPage.php?msg=login_required");
//   exit;
// }

// 📦 AMBIL PRODUK DARI DATABASE
$produk_list = [];

$query = "SELECT 
            p.id, p.penjual_id, p.nama_produk, p.deskripsi, p.harga_asli, p.harga_diskon, 
            p.stok, p.satuan, p.gambar_url, pj.nama_toko, pj.kota, pj.kode_pos as penjual_kode_pos
          FROM produk p
          JOIN penjual pj ON p.penjual_id = pj.id
          WHERE p.status = 'aktif' AND p.stok > 0
          ORDER BY p.created_at DESC";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $harga_tampil = !empty($row['harga_diskon']) && $row['harga_diskon'] < $row['harga_asli']
      ? $row['harga_diskon'] : $row['harga_asli'];
    
    $diskon_persen = 0;
    if (!empty($row['harga_diskon']) && $row['harga_asli'] > 0) {
      $diskon_persen = round((1 - $row['harga_diskon'] / $row['harga_asli']) * 100);
    }

    $produk_list[] = [
      'id' => $row['id'],
      'penjual_id' => $row['penjual_id'],
      'img' => !empty($row['gambar_url']) ? $row['gambar_url'] : 'https://via.placeholder.com/400x300?text=No+Image',
      'disc' => $diskon_persen > 0 ? "-{$diskon_persen}%" : '',
      'name' => htmlspecialchars($row['nama_produk']),
      'seller' => "📍 " . htmlspecialchars($row['nama_toko']) . " – " . htmlspecialchars($row['kota']),
      'nw' => "Rp " . number_format($harga_tampil, 0, ',', '.'),
      'ol' => !empty($row['harga_diskon']) && $row['harga_diskon'] < $row['harga_asli']
        ? "Rp " . number_format($row['harga_asli'], 0, ',', '.') : '',
      'harga_raw' => $harga_tampil,
      'produk_id' => $row['id'],
      'penjual_id' => $row['penjual_id'],
      'penjual_kode_pos' => $row['penjual_kode_pos'],
      'stok' => $row['stok'],
      'satuan' => $row['satuan'],
    ];
  }
}

$produk_json = json_encode($produk_list, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>FoodSave – Promo</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .page { display: none; animation: fade .3s ease; }
    .page.active { display: block; }
    @keyframes fade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    #grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; max-width: 1100px; margin: 30px auto; padding: 0 24px; }
    .card { transition: transform .2s; }
    .card:hover { transform: translateY(-4px); }
  </style>
</head>
<body class="bg-slate-50">

  <!-- ═══ NAVBAR ═══ -->
  <nav class="bg-white px-7 py-3 flex items-center gap-5 shadow-sm sticky top-0 z-10">
    <a href="Index.php" class="font-extrabold text-green-600 text-lg mr-auto no-underline">🌿 FoodSave</a>
    <a href="Index.php" class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Beranda</a>
    <a href="#" class="text-green-600 font-semibold text-sm no-underline">Promo</a>
    <a href="PromosiPage.php" class="text-slate-700 font-semibold text-sm no-underline hover:text-green-600">Cari Makanan</a>

    <?php if (isset($_SESSION['sudah_login'])): ?>
      <?php if ($_SESSION['role'] === 'penjual'): ?>
        <a href="dashboardPenjual.php" class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🏪 Dashboard</a>
      <?php else: ?>
        <a href="keranjang.php" class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🛒 Keranjang</a>
      <?php endif; ?>
      <a href="logout.php" class="bg-red-500 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-red-600">🚪 Keluar</a>
    <?php else: ?>
      <a href="LoginPage.php" class="bg-green-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-700">👤 Masuk</a>
    <?php endif; ?>
  </nav>

  <!-- ═══ HOME PAGE ═══ -->
  <div id="home" class="page active">
    <!-- Hero -->
    <div class="text-center text-white py-12 px-7" style="background: linear-gradient(135deg,#0d7a3e,#22c55e)">
      <h1 class="text-3xl font-extrabold mb-2">Hemat Lebih Banyak, <span class="text-yellow-300">Selamatkan</span> Lebih Banyak!</h1>
      <p class="opacity-90">Diskon hingga 70% untuk makanan surplus berkualitas di sekitarmu 🌍</p>
    </div>

    <!-- Empty State -->
    <?php if (empty($produk_list)): ?>
      <div class="text-center py-20">
        <div class="text-6xl mb-4">🔍</div>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Produk Tersedia</h3>
        <p class="text-gray-500">Yuk cek lagi nanti, penjual sedang menyiapkan produk surplus!</p>
      </div>
    <?php else: ?>
      <div id="grid"></div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="text-center py-4 text-slate-400 text-xs mt-5">
      © <?= date('Y') ?> <b class="text-green-600">FoodSave</b> — Selamatkan Makanan, Hemat Lebih Banyak 🌿
    </footer>
  </div>

  <!-- ═══ DETAIL PAGE ═══ -->
  <div id="detail" class="page">
    <div class="max-w-lg mx-auto my-16 bg-white rounded-2xl p-9 shadow-xl text-center">
      <img id="dImg" src="" alt="" class="w-full h-52 object-cover rounded-xl mb-5" />
      <h2 id="dName" class="text-2xl font-extrabold text-slate-800 mb-2"></h2>
      <p id="dSeller" class="text-slate-500 text-sm mb-4"></p>
      <span id="dPrice" class="block text-2xl font-extrabold text-green-600 mb-6"></span>
      <p id="dStok" class="text-sm text-gray-500 mb-6"></p>

      <!-- ✅ DUA TOMBOL TERPISAH (TANPA NESTED FORM) -->
      <div class="flex flex-col sm:flex-row justify-center gap-3">
        
        <!-- Tombol Kembali -->
        <button type="button" onclick="goHome()"
          class="px-6 py-2 border-2 border-green-600 text-green-600 font-bold rounded-xl hover:bg-green-50">
          ← Kembali
        </button>

        <!-- ✅ Tombol: Tambah ke Keranjang (LINK GET, BUKAN FORM) -->
        <a id="btnAddCart" href="#" 
           class="px-6 py-2 bg-yellow-400 text-gray-900 font-bold rounded-xl hover:bg-yellow-300 inline-flex items-center justify-center gap-2">
          🛒 Tambah ke Keranjang
        </a>

        <!-- ✅ Tombol: Beli Sekarang (Form Terpisah) -->
        <form id="formBeli" method="GET" action="HalamanTransaksi.php" class="w-full sm:w-auto">
          <input type="hidden" name="produk" id="fProduk" />
          <input type="hidden" name="harga" id="fHarga" />
          <input type="hidden" name="seller" id="fSeller" />
          <input type="hidden" name="produk_id" id="fProdukId" />
          <input type="hidden" name="penjual_id" id="fPenjualId" />
          
          <button type="submit"
            class="w-full sm:w-auto px-6 py-2 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700">
            ⚡ Beli Sekarang
          </button>
        </form>

      </div>
    </div>
  </div>

  <!-- ═══ JAVASCRIPT ═══ -->
  <script>
    // ✅ DATA DARI DATABASE
    const P = <?= $produk_json ?>;
    let currentProduct = null;

    // Render kartu produk
    const g = document.getElementById('grid');
    if (P.length > 0) {
      P.forEach(p => {
        g.innerHTML += `
          <div class="card bg-white rounded-2xl shadow-md overflow-hidden">
            <img src="${p.img}" alt="${p.name}" class="w-full h-40 object-cover"/>
            <div class="p-3">
              ${p.disc ? `<span class="text-xs font-extrabold bg-red-500 text-white px-2 py-0.5 rounded inline-block mb-1">${p.disc}</span>` : ''}
              <div class="font-bold text-slate-800 text-sm mb-1">${p.name}</div>
              <div class="flex gap-2 items-center mb-2">
                <span class="font-extrabold text-green-600">${p.nw}</span>
                ${p.ol ? `<span class="text-slate-400 line-through text-xs">${p.ol}</span>` : ''}
              </div>
              <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm"
                onclick='openDetail(${JSON.stringify(p).replace(/'/g, "\\'")})'>🛒 Beli Sekarang</button>
            </div>
          </div>`;
      });
    }

    // Buka halaman detail
    function openDetail(p) {
      currentProduct = p;
      
      document.getElementById('dImg').src = p.img;
      document.getElementById('dName').textContent = p.name;
      document.getElementById('dSeller').textContent = p.seller;
      document.getElementById('dPrice').textContent = p.nw;
      document.getElementById('dStok').textContent = `Stok: ${p.stok} ${p.satuan}`;

      // Isi hidden fields untuk form "Beli Sekarang"
      document.getElementById('fProduk').value = p.name;
      document.getElementById('fHarga').value = p.harga_raw;
      document.getElementById('fSeller').value = p.seller;
      document.getElementById('fProdukId').value = p.produk_id;
      document.getElementById('fPenjualId').value = p.penjual_id;

      // Update link "Tambah ke Keranjang"
      const cartLink = document.getElementById('btnAddCart');
      cartLink.href = `tambah_ke_keranjang.php?produk_id=${p.produk_id}&qty=1`;
      
      // Tambah event listener untuk show alert setelah add to cart
      cartLink.onclick = function(e) {
        // Biarkan link jalan, tapi kita bisa tambah feedback
        setTimeout(() => {
          // Opsional: reload halaman untuk update badge keranjang
          // window.location.reload();
        }, 500);
      };

      document.getElementById('home').classList.remove('active');
      document.getElementById('detail').classList.add('active');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Kembali ke home
    function goHome() {
      document.getElementById('detail').classList.remove('active');
      document.getElementById('home').classList.add('active');
      currentProduct = null;
    }
  </script>

</body>
</html>