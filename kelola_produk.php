<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// 🔐 SECURITY CHECK
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

// Ambil data penjual & status
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, status_verifikasi FROM penjual WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$penjual = $stmt->get_result()->fetch_assoc();

if (!$penjual) {
    header("Location: lengkapi_toko.php");
    exit;
}

if ($penjual['status_verifikasi'] !== 'disetujui') {
    header("Location: dashboardPenjual.php");
    exit;
}
$penjual_id = $penjual['id'];

// Ambil parameter URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? 'edit';

if (!in_array($action, ['edit', 'delete'])) {
    header("Location: dashboardPenjual.php");
    exit;
}

// 🔍 Verifikasi produk milik penjual ini
$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ? AND penjual_id = ?");
$stmt->bind_param("ii", $id, $penjual_id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();

// Ambil parameter URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? 'edit';

// Cek apakah produk ada (tanpa filter penjual_id)
$check_all = $conn->prepare("SELECT id, penjual_id, nama_produk FROM produk WHERE id = ?");
$check_all->bind_param("i", $id);
$check_all->execute();
$result_all = $check_all->get_result();

if (!$produk) {
    die("⚠️ Produk tidak ditemukan atau Anda tidak memiliki akses.");
}

$success = '';
$error = '';

// ⚡ HANDLE POST REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($action === 'delete') {
        // Selalu lakukan SOFT DELETE (nonaktifkan saja, jangan pernah hapus permanen)
        $stmt = $conn->prepare("UPDATE produk SET status = 'nonaktif' WHERE id = ? AND penjual_id = ?");
        $stmt->bind_param("ii", $id, $penjual_id);
        
        if ($stmt->execute()) {
            header("Location: dashboardPenjual.php?msg=deactivated");
            exit;
        } else {
            $error = "Gagal menonaktifkan produk.";
        }
    }

    } elseif ($action === 'edit') {
        // UPDATE PRODUK
        $nama_produk = trim($_POST['nama_produk']);
        $deskripsi = trim($_POST['deskripsi']);
        $harga_asli = (float)$_POST['harga_asli'];
        $harga_diskon = !empty($_POST['harga_diskon']) ? (float)$_POST['harga_diskon'] : null;
        $stok = (int)$_POST['stok'];
        $satuan = trim($_POST['satuan']);
        
        // Handle gambar (jika diupload baru)
        $gambar_url = $produk['gambar_url'];
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
            $target_dir = "uploads/produk/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $ext = pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION);
            $new_name = "prod_" . time() . "_" . uniqid() . "." . $ext;
            $target_file = $target_dir . $new_name;
            
            if (move_uploaded_file($_FILES["gambar"]["tmp_name"], $target_file)) {
                if (!empty($produk['gambar_url']) && file_exists($produk['gambar_url'])) {
                    unlink($produk['gambar_url']);
                }
                $gambar_url = $target_file;
            }
        }

        $stmt = $conn->prepare("UPDATE produk SET nama_produk=?, deskripsi=?, harga_asli=?, harga_diskon=?, stok=?, satuan=?, gambar_url=? WHERE id=? AND penjual_id=?");
        $stmt->bind_param("ssdissiis", $nama_produk, $deskripsi, $harga_asli, $harga_diskon, $stok, $satuan, $gambar_url, $id, $penjual_id);
        
        if ($stmt->execute()) {
            header("Location: dashboardPenjual.php?msg=updated");
            exit;
        } else {
            $error = "Gagal memperbarui: " . $stmt->error;
        }
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'edit' ? 'Edit' : 'Hapus' ?> Produk - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

<div class="bg-white rounded-2xl shadow-lg w-full max-w-lg p-6 md:p-8">
    
    <!-- Header -->
    <div class="mb-6 text-center">
        <div class="text-4xl mb-3"><?= $action === 'edit' ? '✏️' : '🗑️' ?></div>
        <h1 class="text-2xl font-bold text-gray-900">
            <?= $action === 'edit' ? 'Edit Produk' : 'Konfirmasi Hapus' ?>
        </h1>
        <p class="text-gray-500 text-sm mt-1">
            <?= htmlspecialchars($produk['nama_produk']) ?>
        </p>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        
        <?php if ($action === 'edit'): ?>
            <!-- FORM EDIT -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Produk</label>
                <input type="text" name="nama_produk" value="<?= htmlspecialchars($produk['nama_produk']) ?>" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                <textarea name="deskripsi" rows="2" 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"><?= htmlspecialchars($produk['deskripsi']) ?></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Harga Asli (Rp)</label>
                    <input type="number" name="harga_asli" value="<?= $produk['harga_asli'] ?>" required min="0" step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Harga Diskon (Opsional)</label>
                    <input type="number" name="harga_diskon" value="<?= $produk['harga_diskon'] ?? '' ?>" min="0" step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Stok</label>
                    <input type="number" name="stok" value="<?= $produk['stok'] ?>" required min="0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Satuan</label>
                    <select name="satuan" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                        <option value="pcs" <?= $produk['satuan'] == 'pcs' ? 'selected' : '' ?>>Pcs</option>
                        <option value="kg" <?= $produk['satuan'] == 'kg' ? 'selected' : '' ?>>Kg</option>
                        <option value="gram" <?= $produk['satuan'] == 'gram' ? 'selected' : '' ?>>Gram</option>
                        <option value="liter" <?= $produk['satuan'] == 'liter' ? 'selected' : '' ?>>Liter</option>
                        <option value="paket" <?= $produk['satuan'] == 'paket' ? 'selected' : '' ?>>Paket</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Ganti Foto Produk (Opsional)</label>
                <input type="file" name="gambar" accept="image/*" 
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg transition">
                     Simpan Perubahan
                </button>
                <a href="dashboardPenjual.php" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-center font-medium text-gray-700">
                    Batal
                </a>
            </div>

        <?php else: ?>
            <!-- FORM HAPUS (KONFIRMASI) -->
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center mb-4">
                <p class="text-red-800 font-medium mb-1">Yakin ingin menghapus produk ini?</p>
                <p class="text-red-600 text-sm">Tindakan ini tidak dapat dibatalkan. Data produk akan hilang permanen.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg transition">
                    🗑️ Ya, Hapus Produk
                </button>
                <a href="dashboardPenjual.php" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-center font-medium text-gray-700">
                    Batal
                </a>
            </div>
        <?php endif; ?>

    </form>
</div>

</body>
</html>