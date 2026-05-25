<?php
session_start();
echo "<!DOCTYPE html><html><head><style>body{font-family:monospace;background:#1e1e1e;color:#0f0;padding:20px}pre{background:#2d2d2d;padding:15px;border-radius:5px;overflow-x:auto}.ok{color:#4ade80}.err{color:#f87171}</style></head><body>";
echo "<h1>🔍 Debug Session</h1>";

echo "<h3>📦 Isi \$_SESSION:</h3><pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>✅ Status Cek:</h3><ul>";
echo "<li class='" . (isset($_SESSION['sudah_login']) ? 'ok' : 'err') . "'>sudah_login: " . (isset($_SESSION['sudah_login']) ? '✓ SET' : '✗ NOT SET') . "</li>";
echo "<li class='" . (isset($_SESSION['user_id']) ? 'ok' : 'err') . "'>user_id: " . (isset($_SESSION['user_id']) ? '✓ ' . $_SESSION['user_id'] : '✗ NOT SET') . "</li>";
echo "<li class='" . (isset($_SESSION['role']) ? 'ok' : 'err') . "'>role: " . ($_SESSION['role'] ?? 'NOT SET') . "</li>";
echo "<li class='" . (isset($_SESSION['email']) ? 'ok' : 'err') . "'>email: " . ($_SESSION['email'] ?? 'NOT SET') . "</li>";
echo "</ul>";

echo "<div style='margin-top:20px'><a href='LoginPage.php' style='color:#4ade80'>← Login Ulang</a> | <a href='logout.php' style='color:#f87171'>Logout</a> | <a href='dashboardPenjual.php' style='color:#60a5fa'>Coba Dashboard</a></div>";
echo "</body></html>";
?>