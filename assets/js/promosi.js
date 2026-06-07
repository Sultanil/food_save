// js/promosi.js
// Data akan di-pass dari PHP via global variables

let currentTokoId = null;

// Render daftar toko
function renderToko() {
    const grid = document.getElementById('grid-toko');
    if (!TOKO || TOKO.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-20">
                <div class="text-6xl mb-4">😔</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Toko Tersedia</h3>
                <p class="text-gray-500">Yuk cek lagi nanti!</p>
            </div>`;
        return;
    }

    grid.innerHTML = TOKO.map(toko => `
        <div class="card bg-white rounded-2xl shadow-md p-6 cursor-pointer border-2 border-transparent hover:border-green-500"
             onclick="pilihToko(${toko.penjual_id})">
            <div class="flex items-center justify-between mb-4">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-3xl">
                    🏪
                </div>
                <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">
                    ${toko.total_produk} Produk
                </span>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">${toko.nama_toko}</h3>
            <p class="text-gray-500 text-sm mb-4">📍 ${toko.kota}</p>
            <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm">
                Lihat Produk →
            </button>
        </div>
    `).join('');
}

// Pilih toko & tampilkan produk
function pilihToko(penjualId) {
    currentTokoId = penjualId;
    const toko = TOKO.find(t => t.penjual_id === penjualId);
    const produkToko = PRODUK.filter(p => p.penjual_id === penjualId);

    // Update breadcrumb
    const namaDisplay = document.getElementById('toko-nama-display');
    if (namaDisplay) {
        namaDisplay.textContent = toko.nama_toko;
    }

    // Render produk
    const grid = document.getElementById('grid-produk');
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
                            onclick='openDetail(${JSON.stringify(p).replace(/'/g, "\\'")})'>
                            🛒 Beli Sekarang
                        </button>
                    </div>
                </div>
            `).join('')}
        `;
    }

    // Ganti halaman
    document.getElementById('toko-page').classList.remove('active');
    document.getElementById('produk-page').classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Kembali ke daftar toko
function kembaliKeToko() {
    currentTokoId = null;
    document.getElementById('produk-page').classList.remove('active');
    document.getElementById('toko-page').classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Buka detail produk
function openDetail(p) {
    // Simpan ke localStorage atau langsung redirect
    localStorage.setItem('selectedProduct', JSON.stringify(p));
    window.location.href = `HalamanTransaksi.php?produk_id=${p.produk_id}`;
}

// Init saat DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    renderToko();
});