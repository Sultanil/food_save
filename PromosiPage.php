<?php
session_start();
include 'koneksi.php';

// 📦 AMBIL PRODUK DARI DATABASE
$produk_list = [];
$query = "SELECT 
            p.id, p.penjual_id, p.nama_produk, p.deskripsi, p.harga_asli, p.harga_diskon, 
            p.stok, p.satuan, p.gambar_url, pj.nama_toko, pj.kota, pj.kode_pos as penjual_kode_pos
          FROM produk p
          JOIN penjual pj ON p.penjual_id = pj.id
          WHERE p.status = 'aktif' AND p.stok > 0 AND pj.status_verifikasi = 'disetujui'
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
            'penjual_kode_pos' => $row['penjual_kode_pos'],
            'stok' => $row['stok'],
            'satuan' => $row['satuan'],
        ];
    }
}
$produk_json = json_encode($produk_list, JSON_UNESCAPED_UNICODE);

// 🔢 Hitung cart badge untuk pembeli
$cart_badge_count = 0;
if (isset($_SESSION['sudah_login']) && $_SESSION['role'] === 'pembeli' && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(qty) as total FROM keranjang WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $cart_badge_count = $res['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FoodSave – Promo</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        /* ==================== CART ANIMATIONS ==================== */
        .cart-toast { position: fixed; top: 80px; right: 20px; background: white; border-radius: 16px; padding: 12px 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 0 0 1px rgba(34,197,94,0.2); display: flex; align-items: center; gap: 10px; z-index: 9999; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); max-width: 300px; }
        .cart-toast.show { transform: translateX(0); }
        .cart-toast .toast-icon { width: 36px; height: 36px; background: linear-gradient(135deg, #22c55e, #16a34a); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; flex-shrink: 0; animation: toast-icon-bounce 0.5s ease; }
        @keyframes toast-icon-bounce { 0% { transform: scale(0); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
        .cart-toast .toast-text { font-size: 14px; color: #1f2937; font-weight: 500; line-height: 1.3; }
        .cart-toast .toast-text span { color: #22c55e; font-weight: 600; }
        .flying-item { position: fixed; z-index: 9999; pointer-events: none; width: 50px; height: 50px; border-radius: 12px; object-fit: cover; box-shadow: 0 8px 25px rgba(0,0,0,0.25); transition: all 0.75s cubic-bezier(0.25, 0.46, 0.45, 0.94); will-change: transform, opacity; }
        .cart-badge-bounce { animation: badge-bounce 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); }
        @keyframes badge-bounce { 0% { transform: scale(1); } 50% { transform: scale(1.5); } 100% { transform: scale(1); } }
        .cart-shake { animation: cart-shake 0.5s ease; }
        @keyframes cart-shake { 0%, 100% { transform: rotate(0deg); } 20% { transform: rotate(-12deg); } 40% { transform: rotate(12deg); } 60% { transform: rotate(-6deg); } 80% { transform: rotate(6deg); } }
        .cart-glow { animation: cart-glow 1s ease; }
        @keyframes cart-glow { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); } 70% { box-shadow: 0 0 0 12px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        .btn-add-cart { position: relative; overflow: hidden; transition: all 0.3s ease; }
        .btn-add-cart.loading { pointer-events: none; opacity: 0.85; }
        .btn-add-cart.loading::after { content: ""; position: absolute; top: 50%; left: 50%; width: 18px; height: 18px; margin: -9px 0 0 -9px; border: 2px solid transparent; border-top-color: currentColor; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.5); transform: scale(0); animation: ripple 0.6s ease-out; pointer-events: none; }
        @keyframes ripple { to { transform: scale(4); opacity: 0; } }
        @media (max-width: 640px) { .cart-toast { top: auto; bottom: 20px; right: 12px; left: 12px; max-width: none; } }
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
            <?php if ($_SESSION['role'] === 'pembeli'): ?>
                <a href="keranjang.php" id="cart-icon-link" class="relative p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-xl transition group" title="Keranjang Belanja">
                    <i id="cart-icon" class="fa-solid fa-cart-shopping text-lg"></i>
                    <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-lg border-2 border-white <?= $cart_badge_count > 0 ? '' : 'hidden' ?>">
                        <?= $cart_badge_count > 99 ? '99+' : ($cart_badge_count > 0 ? $cart_badge_count : '') ?>
                    </span>
                </a>
            <?php else: ?>
                <a href="dashboardPenjual.php" class="border border-green-600 text-green-600 font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-50">🏪 Dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="bg-red-500 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-red-600">🚪 Keluar</a>
        <?php else: ?>
            <a href="LoginPage.php" class="bg-green-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg no-underline hover:bg-green-700">👤 Masuk</a>
        <?php endif; ?>
    </nav>

    <!-- ✅ Toast Notification -->
    <div id="cart-toast" class="cart-toast" role="alert" aria-live="polite">
        <div class="toast-icon"><i class="fa-solid fa-check"></i></div>
        <div class="toast-text"><span id="toast-product-name">Produk</span> ditambahkan!</div>
    </div>

    <!-- ═══ HOME PAGE ═══ -->
    <div id="home" class="page active">
        <div class="text-center text-white py-12 px-7" style="background: linear-gradient(135deg,#0d7a3e,#22c55e)">
            <h1 class="text-3xl font-extrabold mb-2">Hemat Lebih Banyak, <span class="text-yellow-300">Selamatkan</span> Lebih Banyak!</h1>
            <p class="opacity-90">Diskon hingga 70% untuk makanan surplus berkualitas di sekitarmu 🌍</p>
        </div>
        <?php if (empty($produk_list)): ?>
            <div class="text-center py-20">
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Produk Tersedia</h3>
                <p class="text-gray-500">Yuk cek lagi nanti, penjual sedang menyiapkan produk surplus!</p>
            </div>
        <?php else: ?>
            <div id="grid"></div>
        <?php endif; ?>
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
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                <button type="button" onclick="goHome()" class="px-6 py-2 border-2 border-green-600 text-green-600 font-bold rounded-xl hover:bg-green-50">← Kembali</button>
                <button type="button" id="btn-add-cart" class="btn-add-cart group px-6 py-3 font-semibold text-gray-900 bg-gradient-to-r from-yellow-400 to-yellow-500 rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-0.5 flex items-center justify-center gap-2" data-produk-id="" data-produk-name="" data-produk-img="">
                    <i class="fa-solid fa-cart-plus group-hover:scale-110 transition-transform"></i>
                    <span>Tambah ke Keranjang</span>
                </button>
                <form id="formBeli" method="GET" action="HalamanTransaksi.php" class="w-full sm:w-auto">
                    <input type="hidden" name="produk" id="fProduk" />
                    <input type="hidden" name="harga" id="fHarga" />
                    <input type="hidden" name="seller" id="fSeller" />
                    <input type="hidden" name="produk_id" id="fProdukId" />
                    <input type="hidden" name="penjual_id" id="fPenjualId" />
                    <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700">⚡ Beli Sekarang</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ JAVASCRIPT (Sebelum </body>) ═══ -->
    <script>
    // ✅ DATA DARI DATABASE
    const P = <?= $produk_json ?>;
    let currentProduct = null;

    // ✅ GLOBAL ELEMENT REFERENCES (agar bisa dipanggil di semua fungsi)
    let btnAddCart, cartIcon, cartBadge, cartIconLink, cartToast, toastProductName;

    // ✅ ANIMATION FUNCTIONS (Global Scope)
    function createFlyingItem(imgSrc, btnElement, onComplete) {
        const flyingItem = document.createElement('img');
        flyingItem.src = imgSrc && imgSrc.trim() !== '' ? imgSrc : 'https://via.placeholder.com/50/22c55e/ffffff?text=🛒';
        flyingItem.className = 'flying-item';
        flyingItem.alt = 'product';
        flyingItem.style.border = '2px solid white';
        const btnRect = btnElement.getBoundingClientRect();
        const cartRect = cartIconLink ? cartIconLink.getBoundingClientRect() : { left: window.innerWidth - 60, top: 20, width: 40, height: 40 };
        flyingItem.style.left = (btnRect.left + btnRect.width / 2 - 25) + 'px';
        flyingItem.style.top = (btnRect.top + window.scrollY + btnRect.height / 2 - 25) + 'px';
        document.body.appendChild(flyingItem);
        requestAnimationFrame(() => {
            flyingItem.style.left = (cartRect.left + cartRect.width / 2 - 12) + 'px';
            flyingItem.style.top = (cartRect.top + window.scrollY + cartRect.height / 2 - 12) + 'px';
            flyingItem.style.transform = 'scale(0.25)';
            flyingItem.style.opacity = '0.6';
        });
        setTimeout(() => { if (flyingItem.parentNode) flyingItem.remove(); if (onComplete) onComplete(); }, 750);
    }

    function addToCartAJAX(produkId) {
        const btnText = btnAddCart ? btnAddCart.querySelector('span') : null;
        const originalText = btnText ? btnText.textContent : 'Tambah ke Keranjang';
        if (btnText && btnAddCart) { btnAddCart.classList.add('loading'); btnText.textContent = 'Menambahkan...'; }
        const formData = new FormData();
        formData.append('produk_id', produkId);
        formData.append('qty', '1');
        fetch('tambah_ke_keranjang.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(response => { if (!response.ok) throw new Error('Network error'); return response.json(); })
        .then(data => {
            if (btnText && btnAddCart) { btnAddCart.classList.remove('loading'); btnText.textContent = originalText; }
            if (data.success) {
                if (btnAddCart) createRipple(btnAddCart);
                if (data.total_item !== undefined && cartBadge) {
                    const count = data.total_item > 99 ? '99+' : data.total_item;
                    cartBadge.textContent = count;
                    cartBadge.classList.remove('hidden');
                }
            } else { showToastError(data.message || 'Gagal menambahkan produk'); }
        })
        .catch(error => {
            console.error('Error:', error);
            if (btnText && btnAddCart) { btnAddCart.classList.remove('loading'); btnText.textContent = originalText; }
            showToastError('Terjadi kesalahan. Silakan coba lagi.');
        });
    }

    function updateCartBadge() {
        if (!cartBadge) return;
        cartBadge.classList.remove('cart-badge-bounce');
        void cartBadge.offsetWidth;
        cartBadge.classList.add('cart-badge-bounce');
    }

    function showToast(productName) {
        if (!cartToast || !toastProductName) return;
        toastProductName.textContent = productName.length > 20 ? productName.substring(0, 17) + '...' : productName;
        cartToast.classList.add('show');
        setTimeout(() => { cartToast.classList.remove('show'); }, 3000);
    }

    function showToastError(message) {
        if (!cartToast || !toastProductName) return;
        const toastIcon = cartToast.querySelector('.toast-icon');
        if (toastIcon) {
            toastIcon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            toastIcon.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        }
        toastProductName.textContent = message;
        cartToast.classList.add('show');
        setTimeout(() => {
            cartToast.classList.remove('show');
            setTimeout(() => {
                if (toastIcon) {
                    toastIcon.innerHTML = '<i class="fa-solid fa-check"></i>';
                    toastIcon.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
                }
            }, 400);
        }, 4000);
    }

    function animateCartIcon() {
        if (!cartIcon || !cartIconLink) return;
        cartIcon.classList.add('cart-shake');
        setTimeout(() => cartIcon.classList.remove('cart-shake'), 500);
        cartIconLink.classList.add('cart-glow');
        setTimeout(() => cartIconLink.classList.remove('cart-glow'), 1000);
    }

    function createRipple(element) {
        if (!element) return;
        const ripple = document.createElement('span');
        ripple.className = 'btn-ripple';
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = rect.width / 2 - size / 2;
        const y = rect.height / 2 - size / 2;
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        element.appendChild(ripple);
        setTimeout(() => { if (ripple.parentNode) ripple.remove(); }, 600);
    }

    // ✅ INIT setelah DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Cache elements
        btnAddCart = document.getElementById('btn-add-cart');
        cartIcon = document.getElementById('cart-icon');
        cartBadge = document.getElementById('cart-badge');
        cartIconLink = document.getElementById('cart-icon-link');
        cartToast = document.getElementById('cart-toast');
        toastProductName = document.getElementById('toast-product-name');

        // Handler: Tombol di detail page
        if (btnAddCart) {
            btnAddCart.addEventListener('click', function(e) {
                e.preventDefault();
                const produkId = this.dataset.produkId;
                const produkName = this.dataset.produkName;
                const produkImg = this.dataset.produkImg;
                if (!produkId) return;
                createFlyingItem(produkImg, this, function() { updateCartBadge(); showToast(produkName); animateCartIcon(); });
                addToCartAJAX(produkId);
            });
        }

        // Handler: Toast click
        if (cartToast) {
            cartToast.addEventListener('click', function() { this.classList.remove('show'); });
        }

        // Handler: Event delegation untuk grid buttons
        const grid = document.getElementById('grid');
        if (grid) {
            grid.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-buy-now');
                if (btn) {
                    e.preventDefault();
                    const produkId = btn.dataset.produkId;
                    const produk = P.find(p => p.produk_id == produkId);
                    if (produk) {
                        createFlyingItem(produk.img, btn, function() { updateCartBadge(); showToast(produk.name); animateCartIcon(); });
                        addToCartAJAX(produkId);
                    }
                }
            });
        }
    });

    // ✅ Render kartu produk
    const g = document.getElementById('grid');
    if (g && P.length > 0) {
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
                        <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm btn-buy-now"
                            data-produk-id="${p.produk_id}"
                            data-produk-name="${p.name.replace(/"/g, '&quot;')}"
                            data-produk-img="${p.img}">
                            🛒 Beli Sekarang
                        </button>
                    </div>
                </div>`;
        });
    }

    // ✅ Fungsi openDetail
    function openDetail(p) {
        currentProduct = p;
        document.getElementById('dImg').src = p.img;
        document.getElementById('dName').textContent = p.name;
        document.getElementById('dSeller').textContent = p.seller;
        document.getElementById('dPrice').textContent = p.nw;
        document.getElementById('dStok').textContent = `Stok: ${p.stok} ${p.satuan}`;
        document.getElementById('fProduk').value = p.name;
        document.getElementById('fHarga').value = p.harga_raw;
        document.getElementById('fSeller').value = p.seller;
        document.getElementById('fProdukId').value = p.produk_id;
        document.getElementById('fPenjualId').value = p.penjual_id;
        // Isi data attributes untuk animasi
        if (btnAddCart) {
            btnAddCart.dataset.produkId = p.produk_id;
            btnAddCart.dataset.produkName = p.name;
            btnAddCart.dataset.produkImg = p.img;
        }
        document.getElementById('home').classList.remove('active');
        document.getElementById('detail').classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ✅ Fungsi goHome
    function goHome() {
        document.getElementById('detail').classList.remove('active');
        document.getElementById('home').classList.add('active');
        currentProduct = null;
    }
    </script>
</body>
</html>