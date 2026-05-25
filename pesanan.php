<?php
session_start();
include 'koneksi.php';

// 🔐 SECURITY CHECK
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

// Ambil data penjual
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM penjual WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$penjual = $stmt->get_result()->fetch_assoc();

if (!$penjual) {
    header("Location: lengkapi_toko.php");
    exit;
}
$penjual_id = $penjual['id'];

// 🔄 HANDLE UPDATE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $transaksi_id = (int)$_POST['transaksi_id'];
    $new_status = $_POST['new_status'];
    $valid_status = ['pending', 'dibayar', 'selesai', 'dibatalkan'];

    if (in_array($new_status, $valid_status)) {
        // Pastikan transaksi milik penjual ini
        $check = $conn->prepare("SELECT id FROM transaksi WHERE id = ? AND penjual_id = ?");
        $check->bind_param("ii", $transaksi_id, $penjual_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $update = $conn->prepare("UPDATE transaksi SET status = ? WHERE id = ?");
            $update->bind_param("si", $new_status, $transaksi_id);
            $update->execute();
            header("Location: pesanan.php?updated=1");
            exit;
        }
    }
}

// 📦 AMBIL DATA PESANAN
// JOIN transaksi dengan users (pembeli) dan produk
$query = "SELECT t.id as transaksi_id, t.status, t.jumlah, t.total_harga, t.tanggal_pesanan,
                 u.nama_lengkap as nama_pembeli, u.email as email_pembeli,
                 p.nama_produk, p.gambar_url, p.harga_asli
          FROM transaksi t
          JOIN users u ON t.user_id = u.id
          JOIN produk p ON t.produk_id = p.id
          WHERE t.penjual_id = ?
          ORDER BY t.tanggal_pesanan DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $penjual_id);
$stmt->execute();
$orders = $stmt->get_result();

// Warna status
$status_colors = [
    'pending' => 'bg-yellow-100 text-yellow-700',
    'dibayar' => 'bg-blue-100 text-blue-700',
    'selesai' => 'bg-green-100 text-green-700',
    'dibatalkan' => 'bg-red-100 text-red-700'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Masuk - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<div class="flex min-h-screen">
    
    <!-- SIDEBAR (Sama dengan dashboard) -->
    <aside class="w-64 bg-white shadow-lg hidden md:block">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-green-600">🌿 FoodSave</h1>
        </div>
        <nav class="p-4 space-y-2">
            <a href="dashboardPenjual.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition">
                <span>📊</span> Dashboard
            </a>
            <a href="tambah_produk.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition">
                <span>➕</span> Tambah Produk
            </a>
            <a href="pesanan.php" class="flex items-center gap-3 px-4 py-3 bg-green-500 text-white rounded-lg font-medium shadow-sm">
                <span></span> Pesanan
            </a>
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                <span></span> Logout
            </a>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-6 md:p-8">
        
        <!-- HEADER -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900"> Pesanan Masuk</h1>
                <p class="text-gray-500 text-sm">Kelola pesanan dari pembeli secara real-time</p>
            </div>
            <?php if (isset($_GET['updated'])): ?>
                <div class="bg-green-50 text-green-700 px-4 py-2 rounded-lg text-sm font-medium border border-green-200">
                    ✅ Status berhasil diperbarui!
                </div>
            <?php endif; ?>
        </div>

        <!-- TABEL PESANAN -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <?php if ($orders->num_rows === 0): ?>
                <!-- EMPTY STATE -->
                <div class="p-16 text-center">
                    <div class="text-6xl mb-4">📭</div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Pesanan</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Pesanan dari pembeli akan otomatis muncul di sini setelah checkout berhasil.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID Pesanan</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pembeli & Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Detail</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php while ($o = $orders->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-medium text-gray-900 bg-gray-100 px-2 py-1 rounded">
                                        #<?= str_pad($o['transaksi_id'], 4, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <p class="text-xs text-gray-400 mt-1"><?= date('d M Y, H:i', strtotime($o['tanggal_pesanan'])) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($o['nama_pembeli']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($o['nama_produk']) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-gray-900"><?= $o['jumlah'] ?>x</p>
                                    <p class="text-sm text-green-600 font-medium">Rp <?= number_format($o['total_harga'], 0, ',', '.') ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?= $status_colors[$o['status']] ?? 'bg-gray-100' ?>">
                                        <?= ucfirst($o['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <!-- Form Update Status -->
                                    <form method="POST" class="inline-flex items-center gap-2">
                                        <input type="hidden" name="transaksi_id" value="<?= $o['transaksi_id'] ?>">
                                        <select name="new_status" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none bg-white">
                                            <option value="pending" <?= $o['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="dibayar" <?= $o['status'] == 'dibayar' ? 'selected' : '' ?>>Dibayar</option>
                                            <option value="selesai" <?= $o['status'] == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                            <option value="dibatalkan" <?= $o['status'] == 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                        </select>
                                        <button type="submit" name="update_status" class="px-3 py-1.5 bg-green-500 text-white text-sm font-medium rounded-lg hover:bg-green-600 transition shadow-sm">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

</body>
</html>