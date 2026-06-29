// js/promosi.js

let currentTokoId = null;
let currentProduct = null;

// ═══ RENDER DAFTAR TOKO ═══
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

    const colors = ['bg-green-500', 'bg-blue-500', 'bg-purple-500', 'bg-yellow-500', 'bg-pink-500', 'bg-indigo-500'];
    let html = '';

    TOKO.forEach((toko, index) => {
        const initial = toko.nama_toko.charAt(0).toUpperCase();
        const colorClass = colors[toko.penjual_id % colors.length];
        const hasPhoto = toko.foto_profil && toko.foto_profil.toString().trim() !== '';

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

// ═══ PILIH TOKO & TAMPILKAN PRODUK ═══
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
        <!-- ✅ ONCLICK ADA DI CARD (bukan cuma di gambar) -->
        <div class="card bg-white rounded-2xl shadow-md overflow-hidden cursor-pointer hover:shadow-xl transition-all"
             onclick="openDetailByProduct(${p.produk_id})">
            
            <!-- Gambar juga bisa diklik -->
            <img src="${p.img}" 
                 alt="${p.name}" 
                 class="w-full h-48 object-cover hover:scale-105 transition-transform cursor-pointer"
                 onclick="openDetailByProduct(${p.produk_id})"/>
            
            <div class="p-4">
                ${p.disc ? `<span class="text-xs font-extrabold bg-red-500 text-white px-2 py-1 rounded inline-block mb-2">${p.disc}</span>` : ''}
                <div class="font-bold text-gray-800 text-sm mb-2 line-clamp-2">${p.name}</div>
                
                <!-- Rating (jika ada) -->
                ${p.avg_rating > 0 ? `
                    <div class="flex items-center gap-1 mb-2">
                        <div class="text-yellow-400 text-sm">
                            ${'★'.repeat(Math.round(p.avg_rating))}${'☆'.repeat(5 - Math.round(p.avg_rating))}
                        </div>
                        <span class="text-xs text-gray-500">(${p.total_ulasan})</span>
                    </div>
                ` : ''}
                
                <div class="flex gap-2 items-center mb-3">
                    <span class="font-extrabold text-green-600 text-lg">${p.nw}</span>
                    ${p.ol ? `<span class="text-gray-400 line-through text-xs">${p.ol}</span>` : ''}
                </div>
                <p class="text-xs text-gray-500 mb-3">Stok: ${p.stok} ${p.satuan}</p>
                
                <!-- Tombol dengan event.stopPropagation() -->
                <div class="flex gap-2" onclick="event.stopPropagation()">
                    <button class="flex-1 py-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-lg text-sm"
                        onclick="event.stopPropagation(); tambahKeKeranjang(${p.produk_id}, ${p.penjual_id}, this)">
                        🛒 Keranjang
                    </button>
                    <button class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm"
                        onclick="event.stopPropagation(); window.location.href='HalamanTransaksi.php?produk_id=${p.produk_id}&penjual_id=${p.penjual_id}'">
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

// ═══ KEMBALI KE DAFTAR TOKO ═══
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

// ═══ BUKA DETAIL PRODUK BY ID ═══
function openDetailByProduct(produk_id) {
    console.log('🔍 [OPEN DETAIL] Mencari produk ID:', produk_id);
    console.log('📦 Total produk di array:', PRODUK.length);

    const produk = PRODUK.find(p => (p.produk_id || p.id) === produk_id);

    if (!produk) {
        console.error('❌ Produk tidak ditemukan:', produk_id);
        console.log('📋 ID produk yang tersedia:', PRODUK.map(p => p.produk_id));
        alert('Produk tidak ditemukan! Cek console untuk detail.');
        return;
    }

    console.log('✅ [OPEN DETAIL] Produk ditemukan:', produk.name);
    openDetail(produk);
}

// ═══ BUKA DETAIL PRODUK ═══
function openDetail(p) {
    currentProduct = p;
    console.log('📦 [OPEN DETAIL] Membuka detail:', p.name);

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

    // ✅ LOAD ULASAN
    loadUlasan(p.produk_id || p.id);

    document.getElementById('produk-page').classList.remove('active');
    document.getElementById('detail-page').classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ═══ KEMBALI KE DAFTAR PRODUK TOKO ═══
function kembaliKeProduk() {
    currentProduct = null;
    document.getElementById('detail-page').classList.remove('active');
    document.getElementById('produk-page').classList.add('active');
}

// ═══ LOAD ULASAN DARI SERVER ═══
async function loadUlasan(produk_id) {
    console.log('📝 [LOAD ULASAN] Memuat ulasan untuk produk ID:', produk_id);

    const avgRatingEl = document.getElementById('ulasan-avg-rating');
    const starsEl = document.getElementById('ulasan-stars');
    const totalEl = document.getElementById('ulasan-total');
    const listEl = document.getElementById('ulasan-list');

    // Show loading
    if (listEl) {
        listEl.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Memuat ulasan...</p>
            </div>
        `;
    }

    try {
        const response = await fetch(`api/get_ulasan.php?produk_id=${produk_id}`);
        const data = await response.json();

        console.log('📝 [LOAD ULASAN] Response:', data);

        if (data.success) {
            // Update rating summary
            if (avgRatingEl) {
                avgRatingEl.textContent = data.avg_rating.toFixed(1);
            }

            if (starsEl) {
                const rating = Math.round(data.avg_rating);
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    stars += i <= rating ? '★' : '☆';
                }
                starsEl.textContent = stars;
            }

            if (totalEl) {
                totalEl.textContent = data.total_ulasan;
            }

            // Render daftar ulasan
            if (listEl) {
                if (data.ulasan.length === 0) {
                    listEl.innerHTML = `
                        <div class="bg-white rounded-xl p-8 text-center shadow-sm">
                            <div class="text-6xl mb-4">💬</div>
                            <p class="text-gray-500">Belum ada ulasan untuk produk ini.</p>
                            <p class="text-sm text-gray-400 mt-2">Jadilah yang pertama memberikan ulasan!</p>
                        </div>
                    `;
                } else {
                    listEl.innerHTML = data.ulasan.map(ulasan => {
                        let stars = '';
                        for (let i = 1; i <= 5; i++) {
                            stars += i <= ulasan.rating ? '★' : '☆';
                        }

                        const tanggal = new Date(ulasan.created_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        });

                        return `
                            <div class="bg-white rounded-xl p-6 shadow-sm">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-semibold text-gray-900">${ulasan.nama_lengkap}</h4>
                                        <p class="text-xs text-gray-400">${tanggal}</p>
                                    </div>
                                    <div class="text-yellow-400 text-lg">
                                        ${stars}
                                    </div>
                                </div>
                                <p class="text-gray-700 leading-relaxed">${ulasan.komentar.replace(/\n/g, '<br>')}</p>
                            </div>
                        `;
                    }).join('');
                }
            }

            console.log('✅ [LOAD ULASAN] Berhasil dimuat!');
        } else {
            console.error('❌ [LOAD ULASAN] Error:', data.message);
            if (listEl) {
                listEl.innerHTML = `
                    <div class="bg-red-50 rounded-xl p-6 text-center">
                        <p class="text-red-600">Gagal memuat ulasan</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('❌ [LOAD ULASAN] Exception:', error);
        if (listEl) {
            listEl.innerHTML = `
                <div class="bg-red-50 rounded-xl p-6 text-center">
                    <p class="text-red-600">Terjadi kesalahan saat memuat ulasan</p>
                </div>
            `;
        }
    }
}

// ═══ FUNGSI TAMBAH KE KERANJANG ═══
function tambahKeKeranjang(produk_id, id_toko, btnElement) {
    const btn = btnElement;
    const originalHTML = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';

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
                const badge = document.querySelector('.cart-badge-count');
                if (badge) {
                    badge.textContent = data.total_items;
                    badge.classList.add('animate-bounce');
                    setTimeout(() => badge.classList.remove('animate-bounce'), 1000);
                }

                if (data.cart_cleared) {
                    alert('⚠️ ' + data.message);
                } else {
                    alert('✅ ' + data.message);
                }

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

// ═══ EVENT LISTENER ═══
document.addEventListener('DOMContentLoaded', function () {
    renderToko();

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