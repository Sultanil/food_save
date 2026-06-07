<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// 🔐 Cek login
if (!isset($_SESSION['sudah_login'])) {
    header("Location: LoginPage.php?msg=login_required");
    exit;
}

//  AMBIL DAFTAR TOKO
$toko_list = [];
$query_toko = "SELECT DISTINCT 
                  pj.id as penjual_id,
                  pj.nama_toko,
                  pj.kota,
                  COUNT(p.id) as total_produk
                FROM penjual pj
                JOIN produk p ON pj.id = p.penjual_id
                WHERE p.status = 'aktif' AND p.stok > 0
                GROUP BY pj.id
                ORDER BY pj.nama_toko ASC";

$result_toko = $conn->query($query_toko);
if ($result_toko && $result_toko->num_rows > 0) {
    while ($row = $result_toko->fetch_assoc()) {
        $toko_list[] = [
            'penjual_id' => $row['penjual_id'],
            'nama_toko' => htmlspecialchars($row['nama_toko']),
            'kota' => htmlspecialchars($row['kota']),
            'total_produk' => $row['total_produk'],
        ];
    }
}
$toko_json = json_encode($toko_list, JSON_UNESCAPED_UNICODE);

// 📦 AMBIL SEMUA PRODUK
$produk_list = [];
$query_produk = "SELECT 
                    p.id, p.penjual_id, p.nama_produk, p.deskripsi, 
                    p.harga_asli, p.harga_diskon, p.stok, p.satuan, p.gambar_url,
                    pj.nama_toko, pj.kota
                  FROM produk p
                  JOIN penjual pj ON p.penjual_id = pj.id
                  WHERE p.status = 'aktif' AND p.stok > 0
                  ORDER BY pj.nama_toko, p.created_at DESC";

$result_produk = $conn->query($query_produk);
if ($result_produk && $result_produk->num_rows > 0) {
    while ($row = $result_produk->fetch_assoc()) {
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
            'seller' => htmlspecialchars($row['nama_toko']),
            'city' => htmlspecialchars($row['kota']),
            'nw' => "Rp " . number_format($harga_tampil, 0, ',', '.'),
            'ol' => !empty($row['harga_diskon']) && $row['harga_diskon'] < $row['harga_asli']
                ? "Rp " . number_format($row['harga_asli'], 0, ',', '.') : '',
            'harga_raw' => $harga_tampil,
            'produk_id' => $row['id'],
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
    <title>FoodSave – Pilih Toko</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .page { display: none; animation: fade .3s ease; }
        .page.active { display: block; }
        @keyframes fade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .card { transition: transform .2s, box-shadow .2s; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        #grid-toko, #grid-produk { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
    </style>
</head>
<body class="bg-slate-50">

    <!-- ═══ NAVBAR (Include) ═══ -->
    <?php include 'includes/navbar_promosi.php'; ?>

    <!-- ═══ HALAMAN DAFTAR TOKO ═══ -->
    <div id="toko-page" class="page active">
        <!-- Hero -->
        <div class="text-center text-white py-12 px-7" style="background: linear-gradient(135deg,#0d7a3e,#22c55e)">
            <h1 class="text-3xl font-extrabold mb-2">Pilih Toko Favoritmu 🏪</h1>
            <p class="opacity-90">Temukan makanan surplus dari berbagai toko di sekitarmu</p>
        </div>

        <!-- Grid Toko -->
        <div id="grid-toko" class="max-w-6xl mx-auto px-6 py-10">
            <!-- Diisi oleh JavaScript -->
        </div>
    </div>

    <!-- ═══ HALAMAN PRODUK TOKO ═══ -->
    <div id="produk-page" class="page">
        <!-- Breadcrumb -->
        <div class="bg-white border-b px-7 py-4 sticky top-16 z-10">
            <div class="max-w-6xl mx-auto flex items-center gap-3">
                <button onclick="kembaliKeToko()" class="text-green-600 font-semibold text-sm hover:underline">
                    ← Kembali ke Daftar Toko
                </button>
                <span class="text-gray-300">/</span>
                <span id="toko-nama-display" class="font-bold text-gray-800"></span>
            </div>
        </div>

        <!-- Produk Grid -->
        <div id="grid-produk" class="max-w-6xl mx-auto px-6 py-10">
            <!-- Diisi oleh JavaScript -->
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 text-slate-400 text-xs mt-5">
        © <?= date('Y') ?> <b class="text-green-600">FoodSave</b> — Selamatkan Makanan, Hemat Lebih Banyak 🌿
    </footer>

    <!-- ═══ JAVASCRIPT ═══ -->
    <script>
        // Pass data dari PHP ke JavaScript
        const TOKO = <?= $toko_json ?>;
        const PRODUK = <?= $produk_json ?>;
    </script>
    
    <!-- Include external JS -->
    <script src="js/promosi.js"></script>

</body>
</html>