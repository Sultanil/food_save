// assets/js/checkout_cart.js - JavaScript untuk checkout dari keranjang

console.log('✅ Checkout page loaded');

// ✅ Fungsi submit form dengan hash URL (HANYA untuk radio button)
function submitWithHash(element, hash) {
    console.log('🔄 submitWithHash called:', hash);
    try {
        sessionStorage.setItem('checkout_scroll_to', hash);
        history.replaceState(null, null, hash);
        element.closest('form').submit();
    } catch (error) {
        console.error('❌ Error in submitWithHash:', error);
        element.closest('form').submit();
    }
}

// ✅ Monitor form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkoutForm');
    const submitBtn = document.querySelector('button[name="bayar_sekarang"]');

    if (form) {
        console.log('✅ Form found');

        form.addEventListener('submit', function(e) {
            console.log('📤 Form submit event triggered!');

            // Validasi manual
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            let emptyFields = [];

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    emptyFields.push(field.name);
                    field.style.borderColor = 'red';
                    field.style.backgroundColor = '#fef2f2';
                } else {
                    field.style.borderColor = '';
                    field.style.backgroundColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                console.error('❌ Validation failed. Empty fields:', emptyFields);
                alert('⚠️ Mohon lengkapi field berikut:\n- ' + emptyFields.join('\n- '));

                // Scroll ke field pertama yang kosong
                const firstEmpty = document.querySelector('[name="' + emptyFields[0] + '"]');
                if (firstEmpty) {
                    firstEmpty.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstEmpty.focus();
                }
                return false;
            }

            console.log('✅ Validation passed!');

            // Show loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Memproses...';
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
            }
        });
    }

    // Restore scroll position
    const scrollTarget = sessionStorage.getItem('checkout_scroll_to');
    if (scrollTarget) {
        console.log('📍 Restoring scroll to:', scrollTarget);
        const element = document.querySelector(scrollTarget);
        if (element) {
            setTimeout(() => {
                element.classList.add('section-highlight');
                const navbarHeight = 80;
                const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
                const offsetPosition = elementPosition - navbarHeight;
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
                setTimeout(() => element.classList.remove('section-highlight'), 1000);
            }, 100);
        }
        sessionStorage.removeItem('checkout_scroll_to');
    }

    // Radio button visual feedback
    document.querySelectorAll('input[name="pengiriman"], input[name="pembayaran"]').forEach(input => {
        input.addEventListener('change', function() {
            document.querySelectorAll('.radio-card').forEach(card => card.classList.remove('selected'));
            this.closest('.radio-card')?.classList.add('selected');
        });
    });
});

// Format phone number
document.querySelector('input[name="telepon"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.startsWith('08') && value.length > 2) {
        e.target.value = value;
    }
});