<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/notifikasi_admin.php';

// Security: Hanya admin yang bisa akses
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// ==================== AMBIL STATISTIK ====================
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'penjual' AND is_deleted = 0");
$total_penjual = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'pembeli' AND is_deleted = 0");
$total_pembeli = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// ==================== AMBIL DATA ====================
// List penjual pending
$stmt = $pdo->query("
    SELECT p.*, u.nama_lengkap, u.email 
    FROM penjual p
    JOIN users u ON p.user_id = u.id
    WHERE p.status_verifikasi = 'pending' 
    ORDER BY p.id DESC
");
$pending_sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// List users aktif
$stmt = $pdo->query("SELECT * FROM users WHERE is_deleted = 0 ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// List users deleted
$stmt = $pdo->query("SELECT * FROM users WHERE is_deleted = 1 ORDER BY deleted_at DESC");
$deleted_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">

    <!-- Navbar (dari include) -->
    <?php include 'includes/navbar_admin.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Notifikasi (dari helper) -->
        <?php tampilkanNotifikasi(); ?>

        <!-- ==================== STATISTIK ==================== -->
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

        <!-- ==================== 1. VERIFIKASI PENJUAL PENDING ==================== -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="font-semibold text-lg text-gray-900 flex items-center gap-2">🏪 Verifikasi Pendaftaran Toko (Pending)</h2>
                <span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2.5 py-1 rounded-full"><?= count($pending_sellers) ?> Perlu Tindakan</span>
            </div>
            <div class="overflow-x-auto">
                <?php if (count($pending_sellers) === 0): ?>
                    <div class="p-12 text-center text-gray-500">
                        <span class="text-4xl">☕</span>
                        <p class="mt-2 text-sm">Tidak ada pendaftaran toko penjual baru yang memerlukan verifikasi.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Toko & Pemilik</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Alamat & Telp</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">KTP & NIK</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach($pending_sellers as $s): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars($s['nama_toko']) ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($s['nama_lengkap']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($s['email']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div>📍 <?= htmlspecialchars($s['alamat']) ?>, <?= htmlspecialchars($s['kota']) ?> (<?= $s['kode_pos'] ?>)</div>
                                    <div class="text-gray-500 mt-1">📞 <?= htmlspecialchars($s['no_telp'] ?? '-') ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="font-mono bg-gray-100 px-2.5 py-1 rounded w-max text-gray-700 font-bold text-xs"><?= htmlspecialchars($s['nik']) ?></div>
                                    <?php if (!empty($s['foto_ktp'])): ?>
                                        <a href="<?= htmlspecialchars($s['foto_ktp']) ?>" target="_blank" class="text-green-600 hover:text-green-800 hover:underline text-xs font-bold block mt-2 flex items-center gap-1">
                                            🖼️ Lihat KTP Asli →
                                        </a>
                                    <?php else: ?>
                                        <span class="text-red-500 text-xs block mt-1">Tidak ada foto KTP</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Tombol Setujui -->
                                        <a href="actions/admin_actions.php?setujui_penjual=<?= $s['id'] ?>" 
                                           onclick="return confirm('Setujui toko <?= htmlspecialchars($s['nama_toko']) ?> untuk mulai berjualan?')"
                                           class="px-3.5 py-2 bg-green-500 hover:bg-green-600 text-white text-xs font-bold rounded-lg transition shadow-sm cursor-pointer">
                                            ✓ Setujui
                                        </a>
                                        <!-- Form Tolak -->
                                        <button type="button" onclick="document.getElementById('modal-tolak-<?= $s['id'] ?>').classList.remove('hidden')" 
                                                class="px-3.5 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition shadow-sm cursor-pointer">
                                            ✗ Tolak
                                        </button>
                                    </div>
                                    
                                    <!-- Modal Tolak Penjual -->
                                    <div id="modal-tolak-<?= $s['id'] ?>" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                                        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 text-left">
                                            <h3 class="text-lg font-bold text-gray-900 mb-1">Tolak Pendaftaran Toko</h3>
                                            <p class="text-sm text-gray-500 mb-4">Berikan alasan penolakan untuk toko <strong><?= htmlspecialchars($s['nama_toko']) ?></strong>. Alasan ini akan ditampilkan ke penjual.</p>
                                            <form method="POST" action="actions/admin_actions.php">
                                                <input type="hidden" name="penjual_id" value="<?= $s['id'] ?>">
                                                <textarea name="alasan_penolakan" rows="3" required placeholder="Contoh: Foto KTP buram dan NIK tidak sesuai KTP." 
                                                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none resize-none mb-4 text-sm"></textarea>
                                                <div class="flex gap-2 justify-end">
                                                    <button type="button" onclick="document.getElementById('modal-tolak-<?= $s['id'] ?>').classList.add('hidden')" 
                                                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                                        Batal
                                                    </button>
                                                    <button type="submit" name="tolak_penjual" 
                                                            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-bold shadow cursor-pointer">
                                                        Kirim Penolakan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== 2. USER AKTIF ==================== -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="font-semibold text-lg text-gray-900">👥 User Aktif</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
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
                        <?php foreach($users as $u): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-mono"><?= $u['id'] ?></td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                                <div class="text-xs text-gray-500">@<?= htmlspecialchars($u['username']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full <?= $u['role'] == 'admin' ? 'bg-purple-100 text-purple-700' : ($u['role'] == 'penjual' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700') ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-gray-600"><?= $u['kode_pos'] ?? '-' ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($u['id'] != ($_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0)): ?>
                                    <a href="actions/admin_actions.php?hapus=<?= $u['id'] ?>" 
                                       onclick="return confirm('Yakin ingin menonaktifkan user ini?\n\nUser akan di-soft delete (tidak hilang permanen)')"
                                       class="text-red-600 hover:text-red-800 text-sm font-medium transition cursor-pointer">
                                       🗑️ Nonaktifkan
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ==================== 3. USER NONAKTIF ==================== -->
        <?php if (count($deleted_users) > 0): ?>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="font-semibold text-lg text-gray-900">🚫 User Nonaktif (Soft Deleted)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
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
                        <?php foreach($deleted_users as $u): ?>
                        <tr class="hover:bg-gray-50 opacity-75 transition-colors">
                            <td class="px-6 py-4 text-sm font-mono"><?= $u['id'] ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= ucfirst($u['role']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-400"><?= date('d/m/Y H:i', strtotime($u['deleted_at'])) ?></td>
                            <td class="px-6 py-4 text-right">
                                <a href="actions/admin_actions.php?restore=<?= $u['id'] ?>" 
                                   class="text-green-600 hover:text-green-800 text-sm font-medium transition cursor-pointer">
                                   ↩️ Aktifkan Kembali
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>