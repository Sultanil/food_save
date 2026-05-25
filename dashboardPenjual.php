<?php
session_start();
include 'koneksi.php';

// 🔐 SECURITY CHECK
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

// Ambil user_id dari session
$user_id = $_SESSION['user_id'] ?? 0;


// Ambil data penjual
$stmt = $conn->prepare("SELECT id, nama_toko FROM penjual WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_penjual = $stmt->get_result();

if ($result_penjual->num_rows === 0) {
    header("Location: lengkapi_toko.php");
    exit;
}

$penjual = $result_penjual->fetch_assoc();
$penjual_id = $penjual['id'];

// 📦 QUERY PRODUK MILIK PENJUAL INI
$stmt = $conn->prepare("SELECT id, nama_produk, harga_asli, harga_diskon, stok, satuan, gambar_url, status FROM produk WHERE penjual_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $penjual_id);
$stmt->execute();
$produk_list = $stmt->get_result();

// Hitung statistik
$stmt_stats = $conn->prepare("SELECT COUNT(*) as total, SUM(stok) as total_stok FROM produk WHERE penjual_id = ?");
$stmt_stats->bind_param("i", $penjual_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($penjual['nama_toko']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-50 text-gray-800 font-sans">

    <div class="flex min-h-screen">

        <!-- SIDEBAR -->
        <aside class="w-64 bg-white shadow-lg hidden md:block">
            <div class="p-6 border-b border-gray-200">
                <h1 class="text-2xl font-bold text-green-600">🌿 FoodSave</h1>
            </div>

            <nav class="p-4 space-y-2">
                <a href="dashboardPenjual.php" class="flex items-center gap-3 px-4 py-3 bg-green-500 text-white rounded-lg font-medium">
                    <span>📊</span> Dashboard
                </a>
                <a href="pesanan.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg font-medium transition">
                    <span>🛒</span> Pesanan
                </a>
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 p-6 md:p-8">

            <!-- HEADER -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Dashboard <?= htmlspecialchars($penjual['nama_toko']) ?> 👋</h2>
                <p class="text-gray-500 mt-1">Kelola produk dan pantau penjualan Anda</p>
            </div>

            <!-- STATS CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Produk</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['total'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-2xl">📦</div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Stok</p>
                            <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['total_stok'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-2xl">📊</div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Status Toko</p>
                            <p class="text-lg font-semibold text-green-600 mt-2">✅ Aktif</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center text-2xl">✓</div>
                    </div>
                </div>
            </div>

            <!-- PRODUK SECTION -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h3 class="font-semibold text-lg text-gray-900">Produk Saya</h3>
                    <a href="tambah_produk.php" class="inline-flex items-center justify-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm font-medium transition">
                        <span class="mr-2">+</span> Tambah Produk
                    </a>
                </div>

                <?php if ($produk_list->num_rows === 0): ?>
                    <!-- EMPTY STATE -->
                    <div class="p-12 text-center">
                        <div class="text-6xl mb-4">📦</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Produk</h3>
                        <p class="text-gray-500 mb-6">Yuk tambah produk pertama Anda dan mulai berjualan!</p>
                        <a href="tambah_produk.php" class="inline-block px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-medium transition">
                            Tambah Produk Pertama
                        </a>
                    </div>
                <?php else: ?>
                    <!-- PRODUK TABLE -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php while ($p = $produk_list->fetch_assoc()): ?>
        
                                        <!-- ... kode tabel ... -->
                                    </tr>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <?php if (!empty($p['gambar_url'])): ?>
                                                    <img src="<?= htmlspecialchars($p['gambar_url']) ?>"
                                                        alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                                                        class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center text-2xl">📷</div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($p['nama_produk']) ?></p>
                                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($p['satuan'] ?? 'pcs') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($p['harga_diskon']) && $p['harga_diskon'] < $p['harga_asli']): ?>
                                                <p class="font-semibold text-green-600">Rp <?= number_format($p['harga_diskon'], 0, ',', '.') ?></p>
                                                <p class="text-sm text-gray-400 line-through">Rp <?= number_format($p['harga_asli'], 0, ',', '.') ?></p>
                                            <?php else: ?>
                                                <p class="font-semibold text-gray-900">Rp <?= number_format($p['harga_asli'], 0, ',', '.') ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="<?= $p['stok'] < 10 ? 'text-red-600 font-semibold' : 'text-gray-900' ?>">
                                                <?= $p['stok'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $status_colors = [
                                                'aktif' => 'bg-green-100 text-green-700',
                                                'habis' => 'bg-red-100 text-red-700',
                                                'nonaktif' => 'bg-gray-100 text-gray-700'
                                            ];
                                            $status = $p['status'] ?? 'aktif';
                                            ?>
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?= $status_colors[$status] ?? 'bg-gray-100' ?>">
                                                <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right space-x-2">
                                            <a href="kelola_produk.php?id=<?= $p['id'] ?>&action=edit"
                                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                ✏️ Edit
                                            </a>
                                            <a href="kelola_produk.php?id=<?= $p['id'] ?>&action=delete"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                🗑️ Hapus
                                            </a>
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