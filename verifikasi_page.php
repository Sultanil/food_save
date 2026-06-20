<?php
session_start();

// Redirect jika tidak ada sesi pending
if (!isset($_SESSION['pending_user_id'])) {
    header("Location: register_page.php");
    exit;
}

$site_name = "FoodSave";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - <?= $site_name ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .bg-gradient-foodsave {
            background: linear-gradient(135deg, #f0f9f0 0%, #e8f5e9 100%);
        }
    </style>
</head>
<body class="bg-gradient-foodsave min-h-screen flex flex-col justify-center items-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-[#4CAF50] text-4xl font-bold mb-2"><?= $site_name ?></h1>
            <p class="text-gray-600 text-sm">Verifikasi email Anda untuk melanjutkan</p>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-2">Cek Email Anda</h2>
                <p class="text-gray-500 text-sm">Kami telah mengirim kode verifikasi 6 digit ke email Anda</p>
            </div>

            <div id="message" class="hidden p-3 rounded-lg mb-4 text-center text-sm"></div>

            <form id="verifyForm">
                <div class="mb-6">
                    <label class="block mb-2 font-semibold text-gray-800 text-sm text-center">Masukkan Kode Verifikasi</label>
                    <input type="text" name="kode_otp" id="otpInput" maxlength="6" pattern="[0-9]{6}" required
                        placeholder="000000"
                        class="w-full px-4 py-4 border-2 border-gray-300 rounded-lg text-2xl text-center tracking-[1em] font-bold focus:outline-none focus:border-[#4CAF50]">
                </div>

                <button type="submit" id="btnVerify"
                    class="w-full py-3.5 bg-[#4CAF50] hover:bg-[#43a047] text-white font-semibold rounded-lg cursor-pointer transition-colors duration-300 mb-4">
                    Verifikasi
                </button>
            </form>

            <div class="text-center">
                <p class="text-sm text-gray-500 mb-2">Tidak menerima kode?</p>
                <button onclick="resendOTP()" class="text-[#4CAF50] font-semibold text-sm hover:underline cursor-pointer">
                    Kirim Ulang Kode
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $('#verifyForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#btnVerify');
        const message = $('#message');
        const otp = $('#otpInput').val();
        
        if (otp.length !== 6) {
            message.removeClass('hidden bg-green-50 text-green-700')
                   .addClass('bg-red-50 text-red-700')
                   .text('Kode OTP harus 6 digit!')
                   .show();
            return;
        }
        
        btn.prop('disabled', true)
           .removeClass('bg-[#4CAF50] hover:bg-[#43a047]')
           .addClass('bg-gray-400 cursor-not-allowed')
           .text('Memverifikasi...');
        message.hide().removeClass('bg-red-50 text-red-700 bg-green-50 text-green-700');
        
        $.ajax({
            url: 'actions/proses_verifikasi.php',
            type: 'POST',
            data: { kode_otp: otp },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    message.removeClass('hidden bg-red-50 text-red-700')
                           .addClass('bg-green-50 text-green-700')
                           .text(response.message)
                           .show();
                    
                    setTimeout(function() {
                        window.location.href = 'Index.php';
                    }, 2000);
                } else {
                    message.removeClass('hidden bg-green-50 text-green-700')
                           .addClass('bg-red-50 text-red-700')
                           .text(response.message)
                           .show();
                    
                    btn.prop('disabled', false)
                       .removeClass('bg-gray-400 cursor-not-allowed')
                       .addClass('bg-[#4CAF50] hover:bg-[#43a047]')
                       .text('Verifikasi');
                }
            },
            error: function() {
                message.removeClass('hidden bg-green-50 text-green-700')
                       .addClass('bg-red-50 text-red-700')
                       .text('Terjadi kesalahan. Silakan coba lagi.')
                       .show();
                
                btn.prop('disabled', false)
                   .removeClass('bg-gray-400 cursor-not-allowed')
                   .addClass('bg-[#4CAF50] hover:bg-[#43a047]')
                   .text('Verifikasi');
            }
        });
    });

    function resendOTP() {
        alert('Fitur kirim ulang akan segera hadir!');
    }
    </script>
</body>
</html>