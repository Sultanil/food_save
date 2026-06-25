<?php
// actions/admin_actions.php - Semua proses backend admin

session_start();
require_once '../config/database.php';

// Security: Hanya admin yang bisa akses
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// ==================== PROSES SOFT DELETE ====================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Jangan biarkan admin menghapus dirinya sendiri
    if ($id != ($_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0)) {
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: ../dashboardAdmin.php?msg=deleted");
        exit;
    }
}

// ==================== PROSES RESTORE ====================
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    
    $stmt = $pdo->prepare("UPDATE users SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: ../dashboardAdmin.php?msg=restored");
    exit;
}

// ==================== PROSES SETUJUI PENJUAL ====================
if (isset($_GET['setujui_penjual'])) {
    $id = (int)$_GET['setujui_penjual'];
    
    $stmt = $pdo->prepare("UPDATE penjual SET status_verifikasi = 'disetujui', alasan_penolakan = NULL WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: ../dashboardAdmin.php?msg=approved");
    exit;
}

// ==================== PROSES TOLAK PENJUAL ====================
if (isset($_POST['tolak_penjual'])) {
    $id = (int)$_POST['penjual_id'];
    $alasan = trim($_POST['alasan_penolakan']);
    
    $stmt = $pdo->prepare("UPDATE penjual SET status_verifikasi = 'ditolak', alasan_penolakan = ? WHERE id = ?");
    $stmt->execute([$alasan, $id]);
    
    header("Location: ../dashboardAdmin.php?msg=rejected");
    exit;
}

// Jika tidak ada action, redirect ke dashboard
header("Location: ../dashboardAdmin.php");
exit;
?>