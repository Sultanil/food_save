<?php
session_start();
include 'koneksi.php';

// Cek login & role penjual
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Cek apakah sudah punya data toko
$cek = $conn->prepare("SELECT id FROM penjual WHERE user_id = ?");
$cek->bind_param("i", $user_id);
$cek->execute();
if ($cek->get_result()->num_rows > 0) {
    header("Location: dashboardPenjual.php");
    exit;
}

// Proses form submit
if (isset($_POST['submit'])) {
    $nama_toko = mysqli_real_escape_string($conn, $_POST['nama_toko']);
    $kota = mysqli_real_escape_string($conn, $_POST['kota']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);
    
    if (empty($nama_toko) || empty($kota)) {
        $error = "Nama toko dan kota wajib diisi!";
    } else {
        $stmt = $conn->prepare("INSERT INTO penjual (user_id, nama_toko, kota, alamat, no_telp) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $nama_toko, $kota, $alamat, $no_telp);
        
        if ($stmt->execute()) {
            // Redirect ke dashboard
            header("Location: dashboardPenjual.php?success=1");
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Profil Toko - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

<div class="bg-white p-8 rounded-2xl shadow-lg max-w-md w-full">
    <div class="text-center mb-6">
        <div class="text-5xl mb-3">🏪</div>
        <h1 class="text-2xl font-bold text-gray-900">Lengkapi Profil Toko</h1>
        <p class="text-gray-500 text-sm mt-2">Isi data toko Anda untuk mulai berjualan</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-4 text-sm">
            Profil toko berhasil dibuat!
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Nama Toko <span class="text-red-500">*</span>
            </label>
            <input type="text" name="nama_toko" required 
                   placeholder="Contoh: Warung Berkah"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Kota <span class="text-red-500">*</span>
            </label>
            <input type="text" name="kota" required 
                   placeholder="Contoh: Jakarta Selatan"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Alamat Lengkap
            </label>
            <textarea name="alamat" rows="3" 
                      placeholder="Jl. Contoh No. 123, RT/RW 001/002"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Nomor Telepon/WhatsApp
            </label>
            <input type="text" name="no_telp" 
                   placeholder="081234567890"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>

        <div class="pt-2">
            <button type="submit" name="submit" 
                    class="w-full py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg transition">
                Simpan Profil Toko
            </button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-700">Logout</a>
    </div>
</div>

</body>
</html>