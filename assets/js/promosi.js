// js/promosi.js

let currentTokoId = null;

// Render daftar toko
function renderToko() {
    const grid = document.getElementById('grid-toko');
    if (!grid) return;

    // Debug: Cek apakah data sudah ada
    console.log('📦 Data TOKO:', TOKO);
    console.log('📦 Data PRODUK:', PRODUK);

    if (!TOKO || TOKO.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-20">
                <div class="text-6xl mb-4"></div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Toko Tersedia</h3>
                <p class="text-gray-500">Yuk cek lagi nanti!</p>
            </div>`;
        return;
    }

    grid.innerHTML = TOKO.map(toko => `
        <div class="card bg-white rounded-2xl shadow-md p-6 cursor-pointer border-2 border-transparent hover:border-green-500"
             onclick="pilihToko('${toko.penjual_id}')">
            <div class="flex items-center justify-between mb-4">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-3xl">
                    🏪
                </div>
                <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">
                    ${toko.total_produk} Produk
                </span>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">${toko.nama_toko}</h3>
            <p class="text-gray-500 text-sm mb-4"> ${toko.kota}</p>
            <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm">
                Lihat Produk →
            </button>
        </div>
    `).join('');
}

// Pilih toko & tampilkan produk
function pilihToko(penjualId) {
    // ✅ Konversi ke string untuk pencocokan yang konsisten
    penjualId = String(penjualId);
    currentTokoId = penjualId;

    // ✅ Cari toko dengan type coercion
    const toko = TOKO.find(t => String(t.penjual_id) === penjualId);

    // ✅ Null check - jika toko tidak ditemukan
    if (!toko) {
        console.error('❌ Toko tidak ditemukan untuk ID:', penjualId);
        console.log('📋 Daftar toko yang tersedia:', TOKO.map(t => t.penjual_id));
        alert('Toko tidak ditemukan! Silakan refresh halaman.');
        return;
    }

    // ✅ Filter produk dengan type coercion
    const produkToko = PRODUK.filter(p => String(p.penjual_id) === penjualId);

    // Update breadcrumb
    const namaDisplay = document.getElementById('toko-nama-display');
    if (namaDisplay) {
        namaDisplay.textContent = toko.nama_toko;
    }

    // Render produk
    const grid = document.getElementById('grid-produk');
    if (!grid) return;

    if (!produkToko || produkToko.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-20">
                <div class="text-6xl mb-4">📦</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Produk</h3>
                <p class="text-gray-500">Toko ini belum memiliki produk tersedia</p>
            </div>`;
    } else {
        grid.innerHTML = `
            <div class="col-span-full mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Produk dari ${toko.nama_toko}</h2>
                <p class="text-gray-500">${produkToko.length} produk tersedia</p>
            </div>
            ${produkToko.map(p => `
                <div class="card bg-white rounded-2xl shadow-md overflow-hidden">
                    <img src="${p.img}" alt="${p.name}" class="w-full h-48 object-cover"/>
                    <div class="p-4">
                        ${p.disc ? `<span class="text-xs font-extrabold bg-red-500 text-white px-2 py-1 rounded inline-block mb-2">${p.disc}</span>` : ''}
                        <div class="font-bold text-gray-800 text-sm mb-2 line-clamp-2">${p.name}</div>
                        <div class="flex gap-2 items-center mb-3">
                            <span class="font-extrabold text-green-600 text-lg">${p.nw}</span>
                            ${p.ol ? `<span class="text-gray-400 line-through text-xs">${p.ol}</span>` : ''}
                        </div>
                        <p class="text-xs text-gray-500 mb-3">Stok: ${p.stok} ${p.satuan}</p>
                        <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm"
                            onclick='window.location.href="HalamanTransaksi.php?produk_id=${p.produk_id}"'>
                            🛒 Beli Sekarang
                        </button>
                    </div>
                </div>
            `).join('')}
        `;
    }

    // Ganti halaman
    const tokoPage = document.getElementById('toko-page');
    const produkPage = document.getElementById('produk-page');
    if (tokoPage && produkPage) {
        tokoPage.classList.remove('active');
        produkPage.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Kembali ke daftar toko
function kembaliKeToko() {
    currentTokoId = null;
    const tokoPage = document.getElementById('toko-page');
    const produkPage = document.getElementById('produk-page');
    if (tokoPage && produkPage) {
        produkPage.classList.remove('active');
        tokoPage.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Buka detail produk
function openDetail(p) {
    currentProduct = p;

    // Isi data ke halaman detail
    document.getElementById('dImg').src = p.img;
    document.getElementById('dName').textContent = p.name;
    document.getElementById('dSeller').textContent = p.seller;
    document.getElementById('dPrice').textContent = p.nw;
    document.getElementById('dStok').textContent = `Stok: ${p.stok} ${p.satuan}`;

    // Update link "Tambah ke Keranjang"
    const btnCart = document.getElementById('btnAddCart');
    if (btnCart) {
        btnCart.href = `tambah_ke_keranjang.php?produk_id=${p.produk_id}&qty=1`;
    }

    // Update form "Beli Sekarang"
    const formBeli = document.getElementById('formBeli');
    if (formBeli) {
        document.getElementById('fProduk').value = p.name;
        document.getElementById('fHarga').value = p.harga_raw;
        document.getElementById('fSeller').value = p.seller;
        document.getElementById('fProdukId').value = p.produk_id;
        document.getElementById('fPenjualId').value = p.penjual_id;
    }

    // Pindah ke halaman detail
    document.getElementById('produk-page').classList.remove('active');
    document.getElementById('detail-page').classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Kembali ke daftar produk toko
function kembaliKeProduk() {
    currentProduct = null;
    document.getElementById('detail-page').classList.remove('active');
    document.getElementById('produk-page').classList.add('active');
}

// Init saat DOM loaded
document.addEventListener('DOMContentLoaded', function () {
    renderToko();
});