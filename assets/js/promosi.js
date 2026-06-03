// assets/js/promosi.js - Semua JavaScript untuk halaman promo

// ✅ DATA DARI DATABASE (akan di-inject dari PHP)
let currentProduct = null;

// ✅ GLOBAL ELEMENT REFERENCES
let btnAddCart, cartIcon, cartBadge, cartIconLink, cartToast, toastProductName;

// ✅ 1. CREATE FLYING ITEM ANIMATION
function createFlyingItem(imgSrc, btnElement, onComplete) {
    const flyingItem = document.createElement('img');
    flyingItem.src = imgSrc && imgSrc.trim() !== '' ?
        imgSrc :
        'https://via.placeholder.com/50/22c55e/ffffff?text=🛒';
    flyingItem.className = 'flying-item';
    flyingItem.alt = 'product';
    flyingItem.style.border = '2px solid white';

    const btnRect = btnElement.getBoundingClientRect();
    const cartRect = cartIconLink ? cartIconLink.getBoundingClientRect() : {
        left: window.innerWidth - 60,
        top: 20,
        width: 40,
        height: 40
    };

    flyingItem.style.left = (btnRect.left + btnRect.width / 2 - 25) + 'px';
    flyingItem.style.top = (btnRect.top + window.scrollY + btnRect.height / 2 - 25) + 'px';

    document.body.appendChild(flyingItem);

    requestAnimationFrame(() => {
        flyingItem.style.left = (cartRect.left + cartRect.width / 2 - 12) + 'px';
        flyingItem.style.top = (cartRect.top + window.scrollY + cartRect.height / 2 - 12) + 'px';
        flyingItem.style.transform = 'scale(0.25)';
        flyingItem.style.opacity = '0.6';
    });

    setTimeout(() => {
        if (flyingItem.parentNode) flyingItem.remove();
        if (onComplete) onComplete();
    }, 750);
}

// ✅ 2. AJAX ADD TO CART
function addToCartAJAX(produkId) {
    const btnText = btnAddCart ? btnAddCart.querySelector('span') : null;
    const originalText = btnText ? btnText.textContent : 'Tambah ke Keranjang';

    if (btnText && btnAddCart) {
        btnAddCart.classList.add('loading');
        btnText.textContent = 'Menambahkan...';
    }

    const formData = new FormData();
    formData.append('produk_id', produkId);
    formData.append('qty', '1');

    fetch('tambah_ke_keranjang.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (btnText && btnAddCart) {
                btnAddCart.classList.remove('loading');
                btnText.textContent = originalText;
            }
            if (data.success) {
                if (btnAddCart) createRipple(btnAddCart);
                if (data.total_item !== undefined && cartBadge) {
                    const count = data.total_item > 99 ? '99+' : data.total_item;
                    cartBadge.textContent = count;
                    cartBadge.classList.remove('hidden');
                }
            } else {
                showToastError(data.message || 'Gagal menambahkan produk');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (btnText && btnAddCart) {
                btnAddCart.classList.remove('loading');
                btnText.textContent = originalText;
            }
            showToastError('Terjadi kesalahan. Silakan coba lagi.');
        });
}

// ✅ 3. UPDATE BADGE WITH BOUNCE
function updateCartBadge() {
    if (!cartBadge) return;
    cartBadge.classList.remove('cart-badge-bounce');
    void cartBadge.offsetWidth;
    cartBadge.classList.add('cart-badge-bounce');
}

// ✅ 4. SHOW SUCCESS TOAST
function showToast(productName) {
    if (!cartToast || !toastProductName) return;
    toastProductName.textContent = productName.length > 20 ?
        productName.substring(0, 17) + '...' :
        productName;
    cartToast.classList.add('show');
    setTimeout(() => {
        cartToast.classList.remove('show');
    }, 3000);
}

// ✅ 5. SHOW ERROR TOAST
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

// ✅ 6. ANIMATE CART ICON
function animateCartIcon() {
    if (!cartIcon || !cartIconLink) return;
    cartIcon.classList.add('cart-shake');
    setTimeout(() => cartIcon.classList.remove('cart-shake'), 500);
    cartIconLink.classList.add('cart-glow');
    setTimeout(() => cartIconLink.classList.remove('cart-glow'), 1000);
}

// ✅ 7. CREATE RIPPLE EFFECT
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
    setTimeout(() => {
        if (ripple.parentNode) ripple.remove();
    }, 600);
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

    // Handler: Tombol "Tambah ke Keranjang" di detail page
    if (btnAddCart) {
        btnAddCart.addEventListener('click', function(e) {
            e.preventDefault();
            const produkId = this.dataset.produkId;
            const produkName = this.dataset.produkName;
            const produkImg = this.dataset.produkImg;

            if (!produkId) {
                showToastError('Produk tidak valid');
                return;
            }

            createFlyingItem(produkImg, this, function() {
                updateCartBadge();
                showToast(produkName);
                animateCartIcon();
            });

            addToCartAJAX(produkId);
        });
    }

    // Handler: Toast click
    if (cartToast) {
        cartToast.addEventListener('click', function() {
            this.classList.remove('show');
        });
    }
});

// ✅ Render kartu produk
function renderProductCards() {
    const g = document.getElementById('grid');
    if (g && P.length > 0) {
        P.forEach(p => {
            const productData = JSON.stringify(p).replace(/'/g, "\\'").replace(/"/g, '&quot;');
            g.innerHTML += `
                <div class="card bg-white rounded-2xl shadow-md overflow-hidden cursor-pointer" onclick='openDetail(${productData})'>
                    <img src="${p.img}" alt="${p.name}" class="w-full h-40 object-cover"/>
                    <div class="p-3">
                        ${p.disc ? `<span class="text-xs font-extrabold bg-red-500 text-white px-2 py-0.5 rounded inline-block mb-1">${p.disc}</span>` : ''}
                        <div class="font-bold text-slate-800 text-sm mb-1">${p.name}</div>
                        <div class="flex gap-2 items-center mb-2">
                            <span class="font-extrabold text-green-600">${p.nw}</span>
                            ${p.ol ? `<span class="text-slate-400 line-through text-xs">${p.ol}</span>` : ''}
                        </div>
                        <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm"
                            onclick='openDetail(${productData}); event.stopPropagation();'>
                            🛒 Beli Sekarang
                        </button>
                    </div>
                </div>`;
        });
    }
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

    if (btnAddCart) {
        btnAddCart.dataset.produkId = p.produk_id;
        btnAddCart.dataset.produkName = p.name;
        btnAddCart.dataset.produkImg = p.img;
    }

    document.getElementById('home').classList.remove('active');
    document.getElementById('detail').classList.add('active');
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// ✅ Fungsi goHome
function goHome() {
    document.getElementById('detail').classList.remove('active');
    document.getElementById('home').classList.add('active');
    currentProduct = null;
}