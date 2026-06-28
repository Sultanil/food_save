// js/promosi.js

let currentTokoId = null;
let currentProduct = null;

// Render daftar toko
// Render daftar toko
function renderToko() {
    const grid = document.getElementById('grid-toko');
    if (!grid) return;

    console.log('📦 Data TOKO:', TOKO);

    if (!TOKO || TOKO.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-20">
                <div class="text-6xl mb-4">🏪</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum Ada Toko Tersedia</h3>
                <p class="text-gray-500">Yuk cek lagi nanti!</p>
            </div>`;
        return;
    }

    // Warna untuk fallback
    const colors = ['bg-green-500', 'bg-blue-500', 'bg-purple-500', 'bg-yellow-500', 'bg-pink-500', 'bg-indigo-500'];

    let html = '';

    TOKO.forEach((toko, index) => {
        console.log(`\n🏪 Toko #${index}: ${toko.nama_toko}`);
        console.log('   foto_profil:', toko.foto_profil);

        // Generate inisial
        const initial = toko.nama_toko.charAt(0).toUpperCase();
        const colorClass = colors[toko.penjual_id % colors.length];

        // Cek apakah ada foto
        const hasPhoto = toko.foto_profil && toko.foto_profil.toString().trim() !== '';

        // Buat HTML untuk avatar
        let avatarHtml = '';
        if (hasPhoto) {
            avatarHtml = `
                <div class="w-16 h-16 rounded-2xl overflow-hidden flex-shrink-0 bg-white border border-gray-200">
                    <img src="${toko.foto_profil}" 
                         alt="${toko.nama_toko}" 
                         class="w-full h-full object-cover"
                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-full h-full ${colorClass} flex items-center justify-center text-white text-2xl font-bold\\'>${initial}</div>';">
                </div>`;
        } else {
            avatarHtml = `
                <div class="w-16 h-16 rounded-2xl flex-shrink-0 ${colorClass} flex items-center justify-center text-white text-2xl font-bold">
                    ${initial}
                </div>`;
        }

        html += `
        <div class="card bg-white rounded-2xl shadow-md p-6 cursor-pointer border-2 border-transparent hover:border-green-500 transition-all duration-300"
             onclick="pilihToko('${toko.penjual_id}')">
            <div class="flex items-start justify-between mb-4">
                ${avatarHtml}
                <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">
                    ${toko.total_produk} Produk
                </span>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">${toko.nama_toko}</h3>
            <p class="text-gray-500 text-sm mb-4 flex items-center gap-1">
                <i class="fas fa-map-marker-alt text-red-400"></i>
                ${toko.kota}
            </p>
            <button class="w-full py-2.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl text-sm transition shadow-md hover:shadow-lg">
                Lihat Produk <i class="fas fa-arrow-right ml-1"></i>
            </button>
        </div>
        `;
    });

    grid.innerHTML = html;
    console.log('✅ Grid toko berhasil di-render dengan', TOKO.length, 'toko');
}

// Pilih toko & tampilkan produk
function pilihToko(penjualId) {
    penjualId = String(penjualId);
    currentTokoId = penjualId;

    const toko = TOKO.find(t => String(t.penjual_id) === penjualId);

    if (!toko) {
        console.error('❌ Toko tidak ditemukan untuk ID:', penjualId);
        alert('Toko tidak ditemukan! Silakan refresh halaman.');
        return;
    }

    const produkToko = PRODUK.filter(p => String(p.penjual_id) === penjualId);

    const namaDisplay = document.getElementById('toko-nama-display');
    if (namaDisplay) {
        namaDisplay.textContent = toko.nama_toko;
    }

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
                        
                        <!-- ✅ DUA TOMBOL: Keranjang + Beli Langsung -->
                        <div class="flex gap-2">
                            <button class="flex-1 py-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-lg text-sm"
                                onclick="tambahKeKeranjang(${p.produk_id}, ${p.penjual_id}, this)">
                                🛒 Keranjang
                            </button>
                            <button class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm"
                                onclick="window.location.href='HalamanTransaksi.php?produk_id=${p.produk_id}&penjual_id=${p.penjual_id}'">
                                ⚡ Beli
                            </button>
                        </div>
                    </div>
                </div>
            `).join('')}
        `;
    }

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

    document.getElementById('dImg').src = p.img;
    document.getElementById('dName').textContent = p.name;
    document.getElementById('dSeller').textContent = p.seller;
    document.getElementById('dPrice').textContent = p.nw;
    document.getElementById('dStok').textContent = `Stok: ${p.stok} ${p.satuan}`;

    // Update hidden inputs
    const formBeli = document.getElementById('formBeli');
    if (formBeli) {
        document.getElementById('fProduk').value = p.name;
        document.getElementById('fHarga').value = p.harga_raw;
        document.getElementById('fSeller').value = p.seller;
        document.getElementById('fProdukId').value = p.produk_id || p.id;
        document.getElementById('fIdToko').value = p.penjual_id;
    }

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

// ═══ FUNGSI TAMBAH KE KERANJANG (GLOBAL) ═══
function tambahKeKeranjang(produk_id, id_toko, btnElement) {
    const btn = btnElement;
    const originalHTML = btn.innerHTML;

    // Disable button & show loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';

    // Kirim AJAX
    fetch('actions/tambah_ke_keranjang.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `produk_id=${produk_id}&id_toko=${id_toko}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update badge keranjang di navbar
                const badge = document.querySelector('.cart-badge-count');
                if (badge) {
                    badge.textContent = data.total_items;
                    badge.classList.add('animate-bounce');
                    setTimeout(() => badge.classList.remove('animate-bounce'), 1000);
                }

                // Tampilkan notifikasi
                if (data.cart_cleared) {
                    alert('⚠️ ' + data.message);
                } else {
                    alert('✅ ' + data.message);
                }

                // Update tombol
                btn.innerHTML = '✅';
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 1500);

            } else {
                alert('❌ Error: ' + data.message);
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Terjadi kesalahan sistem.');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
}

// ═══ EVENT LISTENER untuk tombol di detail-page ═══
document.addEventListener('DOMContentLoaded', function () {
    renderToko();

    // Event listener untuk tombol di detail-page (kalau ada)
    const btnAddCart = document.getElementById('btnAddCart');
    if (btnAddCart) {
        btnAddCart.addEventListener('click', function (e) {
            e.preventDefault();

            const produk_id = document.getElementById('fProdukId')?.value;
            const id_toko = document.getElementById('fIdToko')?.value;

            if (!produk_id || !id_toko) {
                alert('❌ Data produk tidak valid!');
                return;
            }

            tambahKeKeranjang(produk_id, id_toko, this);
        });
    }
});