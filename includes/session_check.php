<?php
// session_check.php - Auto logout setelah 5 menit tidak aktif
// Include di semua halaman yang butuh login

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ️ TIMEOUT: 5 menit = 300 detik
$timeout_duration = 15;

// Cek apakah sudah login
if (isset($_SESSION['sudah_login']) && $_SESSION['sudah_login'] === true) {
    
    // Cek apakah ada last activity timestamp
    if (isset($_SESSION['last_activity'])) {
        
        // Hitung selisih waktu
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        // Jika lebih dari 5 menit → logout
        if ($elapsed_time > $timeout_duration) {
            
            // Hancurkan semua session
            session_unset();
            session_destroy();
            
            // Redirect ke login dengan pesan
            header("Location: LoginPage.php?msg=session_expired");
            exit;
        }
    }
    
    // Update last activity setiap kali ada request
    $_SESSION['last_activity'] = time();
}
?>