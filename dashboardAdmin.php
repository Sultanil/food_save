<?php
session_start();
include 'koneksi.php';

// Security: Hanya admin yang bisa akses
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Proses soft delete
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Jangan biarkan admin menghapus dirinya sendiri
    if ($id != $_SESSION['user_id']) {
        mysqli_query($conn, "UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = $id");
        header("Location: admin_dashboard.php?msg=deleted");
        exit;
    }
}

// Proses restore (aktifkan kembali)
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    mysqli_query($conn, "UPDATE users SET is_deleted = 0, deleted_at = NULL WHERE id = $id");
    header("Location: admin_dashboard.php?msg=restored");
    exit;
}

// Ambil statistik
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE is_deleted = 0"))['total'];
$total_penjual = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'penjual' AND is_deleted = 0"))['total'];
$total_pembeli = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'pembeli' AND is_deleted = 0"))['total'];

// Ambil list users (aktif)
$users = mysqli_query($conn, "SELECT * FROM users WHERE is_deleted = 0 ORDER BY created_at DESC");

// Ambil list users (deleted) untuk referensi
$deleted_users = mysqli_query($conn, "SELECT * FROM users WHERE is_deleted = 1 ORDER BY deleted_at DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold text-green-600">🌿 Admin FoodSave</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">Halo, <?= htmlspecialchars($_SESSION['nama']) ?></span>
                <a href="logout.php" class="text-sm text-red-600 hover:underline">Logout</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border">
                <div class="text-sm text-gray-500">Total User Aktif</div>
                <div class="text-3xl font-bold text-gray-900 mt-1"><?= $total_users ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border">
                <div class="text-sm text-gray-500">Total Penjual</div>
                <div class="text-3xl font-bold text-green-600 mt-1"><?= $total_penjual ?></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border">
                <div class="text-sm text-gray-500">Total Pembeli</div>
                <div class="text-3xl font-bold text-blue-600 mt-1"><?= $total_pembeli ?></div>
            </div>
        </div>

        <!-- Pesan Notifikasi -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'deleted'): ?>
                <div class="bg-yellow-50 text-yellow-700 p-3 rounded mb-4 border border-yellow-200">
                    ✅ User berhasil dinonaktifkan (soft delete)
                </div>
            <?php elseif ($_GET['msg'] == 'restored'): ?>
                <div class="bg-green-50 text-green-700 p-3 rounded mb-4 border border-green-200">
                    ✅ User berhasil diaktifkan kembali
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Tabel User Aktif -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="font-semibold text-lg">👥 User Aktif</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Kode Pos</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Terdaftar</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while($u = mysqli_fetch_assoc($users)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm"><?= $u['id'] ?></td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                                <div class="text-sm text-gray-500">@<?= htmlspecialchars($u['username']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full <?= $u['role'] == 'admin' ? 'bg-purple-100 text-purple-700' : ($u['role'] == 'penjual' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700') ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm"><?= $u['kode_pos'] ?? '-' ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?hapus=<?= $u['id'] ?>" 
                                       onclick="return confirm('Yakin ingin menonaktifkan user ini?\n\nUser akan di-soft delete (tidak hilang permanen)')"
                                       class="text-red-600 hover:text-red-800 text-sm font-medium">
                                       🗑️ Nonaktifkan
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabel User Nonaktif (Optional) -->
        <?php if (mysqli_num_rows($deleted_users) > 0): ?>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="font-semibold text-lg">🚫 User Nonaktif (Soft Deleted)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dihapus</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while($u = mysqli_fetch_assoc($deleted_users)): ?>
                        <tr class="hover:bg-gray-50 opacity-75">
                            <td class="px-6 py-4 text-sm"><?= $u['id'] ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="px-6 py-4 text-sm"><?= ucfirst($u['role']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($u['deleted_at'])) ?></td>
                            <td class="px-6 py-4 text-right">
                                <a href="?restore=<?= $u['id'] ?>" 
                                   class="text-green-600 hover:text-green-800 text-sm font-medium">
                                   ↩️ Aktifkan
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>