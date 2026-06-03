// assets/js/checkout.js - JavaScript untuk halaman checkout

// Data dari PHP (akan di-inject)
let hargaSatuan = 0;
let biayaLayanan = 2000;
let diskon = 0;
let stokMaks = 1;

// Update qty
function updateQty(delta) {
    const input = document.querySelector('input[name="jumlah_produk"]');
    let val = parseInt(input.value) + delta;
    
    if (val < 1) val = 1;
    if (val > stokMaks) val = stokMaks;
    
    input.value = val;
    
    // Set hidden field value
    const hiddenInput = document.getElementById('hidden_jumlah_produk');
    if (hiddenInput) hiddenInput.value = val;
    
    hitungTotal();
    
    // Update button states
    const buttons = document.querySelectorAll('button[onclick*="updateQty"]');
    if (buttons[0]) buttons[0].disabled = val <= 1;
    if (buttons[1]) buttons[1].disabled = val >= stokMaks;
}

// Hitung total realtime
function hitungTotal() {
    const qty = parseInt(document.querySelector('input[name="jumlah_produk"]').value);
    const ongkirElement = document.querySelector('input[name="pengiriman"]:checked');
    const ongkir = ongkirElement ? parseInt(ongkirElement.value) : 0;
    
    const subtotal = hargaSatuan * qty;
    const total = subtotal + biayaLayanan + ongkir - diskon;
    
    // Update display
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const ongkirDisplay = document.getElementById('ongkirDisplay');
    const diskonDisplay = document.getElementById('diskonDisplay');
    const totalDisplay = document.getElementById('totalDisplay');
    const btnTotal = document.getElementById('btnTotal');
    
    if (subtotalDisplay) subtotalDisplay.textContent = formatRupiah(subtotal);
    if (ongkirDisplay) ongkirDisplay.textContent = ongkir === 0 ? 'Gratis' : formatRupiah(ongkir);
    if (diskonDisplay) diskonDisplay.textContent = '- ' + formatRupiah(diskon);
    if (totalDisplay) totalDisplay.textContent = formatRupiah(total);
    if (btnTotal) btnTotal.textContent = formatRupiah(total);
}

// Format Rupiah
function formatRupiah(angka) {
    return "Rp " + angka.toLocaleString("id-ID");
}

// Auto select radio styling
function initRadioStyling() {
    document.querySelectorAll('input[type="radio"][name="pengiriman"], input[type="radio"][name="pembayaran"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove active from siblings
            const grid = this.closest('.grid');
            if (grid) {
                grid.querySelectorAll('label').forEach(l => {
                    l.classList.remove('border-brand', 'bg-brand/5');
                });
            }
            
            const spaceY = this.closest('div.space-y-3');
            if (spaceY) {
                spaceY.querySelectorAll('label').forEach(l => {
                    l.classList.remove('border-brand', 'bg-brand/5');
                });
            }
            
            // Add active to selected
            this.closest('label').classList.add('border-brand', 'bg-brand/5');
            
            if (this.name === 'pengiriman') hitungTotal();
        });
    });
}

// Init saat DOM ready
document.addEventListener('DOMContentLoaded', function() {
    hitungTotal();
    initRadioStyling();
});