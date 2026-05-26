<?php
session_start();
include 'koneksi.php';

// Security check
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

// Ambil penjual_id & status
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, status_verifikasi FROM penjual WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$penjual = $stmt->get_result()->fetch_assoc();

if (!$penjual || $penjual['status_verifikasi'] !== 'disetujui') {
    header("Location: dashboardPenjual.php");
    exit;
}
$penjual_id = $penjual['id'];

$error = '';
$success = '';

if (isset($_POST['submit'])) {
    $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $harga_asli = $_POST['harga_asli'];
    $harga_diskon = $_POST['harga_diskon'] ?: null;
    $stok = $_POST['stok'];
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    
    // Upload gambar (opsional)
    $gambar_url = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
        $target_dir = "uploads/produk/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION);
        $new_filename = "produk_" . time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["gambar"]["tmp_name"], $target_file)) {
            $gambar_url = $target_file;
        }
    }
    
    $query = "INSERT INTO produk (penjual_id, nama_produk, deskripsi, harga_asli, harga_diskon, stok, satuan, gambar_url) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issdsiss", $penjual_id, $nama_produk, $deskripsi, $harga_asli, $harga_diskon, $stok, $satuan, $gambar_url);
    
    if ($stmt->execute()) {
        header("Location: dashboardPenjual.php?success=1");
        exit;
    } else {
        $error = "Gagal menambah produk: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk - Food Save</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50">

<div class="max-w-2xl mx-auto p-8">
    <div class="bg-white rounded-xl shadow-lg p-8">
        <h2 class="text-2xl font-bold mb-6">Tambah Produk Baru</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block mb-2 font-semibold">Nama Produk</label>
                <input type="text" name="nama_produk" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            
            <div>
                <label class="block mb-2 font-semibold">Deskripsi</label>
                <textarea name="deskripsi" rows="3" 
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block mb-2 font-semibold">Harga Asli (Rp)</label>
                    <input type="number" name="harga_asli" required min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block mb-2 font-semibold">Harga Diskon (Rp) - Opsional</label>
                    <input type="number" name="harga_diskon" min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block mb-2 font-semibold">Stok</label>
                    <input type="number" name="stok" required min="0"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block mb-2 font-semibold">Satuan</label>
                    <select name="satuan" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="pcs">Pcs</option>
                        <option value="kg">Kg</option>
                        <option value="gram">Gram</option>
                        <option value="liter">Liter</option>
                        <option value="paket">Paket</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block mb-2 font-semibold">Foto Produk</label>
                <input type="file" name="gambar" accept="image/*"
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" name="submit" 
                        class="flex-1 bg-green-500 text-white py-3 rounded-lg hover:bg-green-600 font-semibold">
                    Simpan Produk
                </button>
                <a href="dashboardPenjual.php" 
                   class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-center">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>