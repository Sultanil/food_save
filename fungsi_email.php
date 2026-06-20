<?php
// fungsi_email.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function kirimEmailVerifikasi($email_tujuan, $nama_tujuan, $kode_otp) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ptsawittberkahh@gmail.com'; // Ganti dengan gmailmu
        $mail->Password   = 'vehugvffcnageeih';            // Ganti dengan APP PASSWORD (16 karakter tanpa spasi)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('ptsawittberkahh@gmail.com', 'Admin Toko Soloraya');
        $mail->addAddress($email_tujuan, $nama_tujuan);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi Akun Anda';
        
        // Isi Email (Bisa di-Html-kan biar cantik)
        $mail->Body    = "
            <h2>Halo, $nama_tujuan!</h2>
            <p>Terima kasih telah mendaftar. Gunakan kode berikut untuk memverifikasi email Anda:</p>
            <h1 style='color: #ff5722; letter-spacing: 5px;'>$kode_otp</h1>
            <p>Kode ini akan kedaluwarsa dalam 10 menit.</p>
            <br>
            <p><i>Jangan bagikan kode ini kepada siapa pun.</i></p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Jika error, kembalikan pesan errornya
        return "Gagal mengirim email. Mailer Error: {$mail->ErrorInfo}";
    }
}