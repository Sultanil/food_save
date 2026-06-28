<?php
// alamat_saya.php - Halaman kelola alamat user
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// Security check
if (!isset($_SESSION['sudah_login'])) {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;

// Ambil semua alamat user
$stmt = $pdo->prepare("
    SELECT ua.*, kp.kecamatan, kp.kelurahan 
    FROM user_addresses ua
    LEFT JOIN kode_pos kp ON ua.kode_pos = kp.kode_pos
    WHERE ua.user_id = ?
    ORDER BY ua.is_default DESC, ua.created_at DESC
");
$stmt->execute([$user_id]);
$alamat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil list kode pos untuk dropdown
$kode_pos_list = $pdo->query("SELECT kode_pos, kecamatan, kelurahan FROM kode_pos ORDER BY kecamatan, kelurahan");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alamat Saya - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- NAVBAR -->
    <?php include 'includes/navbar.php'; ?>

    <main class="max-w-4xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">📍 Alamat Saya</h1>
                <p class="text-gray-600">Kelola alamat pengiriman Anda</p>
            </div>
            <button onclick="openAlamatModal()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg transition">
                + Tambah Alamat
            </button>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer" class="mb-6"></div>

        <!-- List Alamat -->
        <?php if (empty($alamat_list)): ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100">
                <div class="text-6xl mb-4">📭</div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Belum Ada Alamat</h3>
                <p class="text-gray-500 mb-6">Tambahkan alamat pengiriman pertama Anda</p>
                <button onclick="openAlamatModal()" class="px-6 py-3 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg transition">
                    + Tambah Alamat
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($alamat_list as $alamat): ?>
                    <div class="bg-white rounded-xl border-2 <?= $alamat['is_default'] ? 'border-green-500' : 'border-gray-200' ?> p-6 relative">
                        <?php if ($alamat['is_default']): ?>
                            <span class="absolute top-4 right-4 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full">⭐ Default</span>
                        <?php endif; ?>

                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($alamat['nama_penerima']) ?></h3>
                                <p class="text-gray-600"><?= htmlspecialchars($alamat['telepon']) ?></p>
                            </div>
                        </div>

                        <p class="text-gray-700 mb-2"><?= nl2br(htmlspecialchars($alamat['alamat_lengkap'])) ?></p>
                        <p class="text-sm text-gray-500">
                            <?= htmlspecialchars($alamat['kelurahan'] ?? '') ?>, 
                            <?= htmlspecialchars($alamat['kecamatan'] ?? '') ?>, 
                            <?= htmlspecialchars($alamat['kode_pos']) ?>
                        </p>

                        <div class="mt-4 pt-4 border-t border-gray-100 flex gap-2">
                            <?php if (!$alamat['is_default']): ?>
                                <button onclick="setDefault(<?= $alamat['id'] ?>)" 
                                        class="flex-1 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition text-sm">
                                    ⭐ Jadikan Default
                                </button>
                            <?php endif; ?>
                            <button onclick="editAlamat(<?= htmlspecialchars(json_encode($alamat)) ?>)" 
                                    class="flex-1 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium rounded-lg transition text-sm">
                                ✏️ Edit
                            </button>
                            <button onclick="deleteAlamat(<?= $alamat['id'] ?>)" 
                                    class="flex-1 py-2 bg-red-50 hover:bg-red-100 text-red-600 font-medium rounded-lg transition text-sm">
                                🗑️ Hapus
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="mt-8">
            <a href="edit_profil.php" class="inline-block px-6 py-3 border-2 border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition">
                ← Kembali ke Edit Profil
            </a>
        </div>

    </main>

    <!-- MODAL: Tambah/Edit Alamat -->
    <div id="alamatModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900" id="alamatModalTitle">Tambah Alamat</h3>
                <button onclick="closeAlamatModal()" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">×</button>
            </div>

            <form id="alamatForm" class="space-y-4">
                <input type="hidden" name="id" id="alamat_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Penerima *</label>
                    <input type="text" name="nama_penerima" id="alamat_nama_penerima" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                           placeholder="Nama penerima">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon *</label>
                    <input type="tel" name="telepon" id="alamat_telepon" required pattern="08[0-9]{8,}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none"
                           placeholder="08xxxxxxxxxx">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap *</label>
                    <textarea name="alamat_lengkap" id="alamat_alamat_lengkap" rows="3" required
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none resize-none"
                              placeholder="Jl. Contoh No. 123, RT/RW, Kelurahan, Kecamatan"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode Pos *</label>
                    <select name="kode_pos" id="alamat_kode_pos" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none bg-white">
                        <option value="">Pilih Kecamatan & Kelurahan</option>
                        <?php while ($kp = $kode_pos_list->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= htmlspecialchars($kp['kode_pos']) ?>">
                                <?= htmlspecialchars($kp['kecamatan']) ?> - <?= htmlspecialchars($kp['kelurahan']) ?> (<?= $kp['kode_pos'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_default" id="alamat_is_default" value="1" class="w-4 h-4 text-green-600 rounded">
                    <label for="alamat_is_default" class="text-sm text-gray-700">Jadikan sebagai alamat default</label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeAlamatModal()" class="flex-1 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 py-2.5 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg cursor-pointer">
                        💾 Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Alert helper
        function showAlert(type, message) {
            const alertClass = type === 'success' 
                ? 'bg-green-50 border-green-200 text-green-700' 
                : 'bg-red-50 border-red-200 text-red-700';
            
            const icon = type === 'success' ? '✅' : '❌';
            
            const alertHTML = `
                <div class="p-4 ${alertClass} rounded-xl border-2">
                    ${icon} ${message}
                </div>
            `;
            
            document.getElementById('alertContainer').innerHTML = alertHTML;
            
            setTimeout(() => {
                document.getElementById('alertContainer').innerHTML = '';
            }, 5000);
        }

        // Modal functions
        function openAlamatModal() {
            document.getElementById('alamatModalTitle').textContent = 'Tambah Alamat';
            document.getElementById('alamatForm').reset();
            document.getElementById('alamat_id').value = '';
            document.getElementById('alamatModal').classList.remove('hidden');
        }

        function closeAlamatModal() {
            document.getElementById('alamatModal').classList.add('hidden');
        }

        function editAlamat(alamat) {
            document.getElementById('alamatModalTitle').textContent = 'Edit Alamat';
            document.getElementById('alamat_id').value = alamat.id;
            document.getElementById('alamat_nama_penerima').value = alamat.nama_penerima;
            document.getElementById('alamat_telepon').value = alamat.telepon;
            document.getElementById('alamat_alamat_lengkap').value = alamat.alamat_lengkap;
            document.getElementById('alamat_kode_pos').value = alamat.kode_pos;
            document.getElementById('alamat_is_default').checked = alamat.is_default == 1;
            document.getElementById('alamatModal').classList.remove('hidden');
        }

        // Handle form submit
        document.getElementById('alamatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = 'Menyimpan...';
            
            fetch('actions/save_alamat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    closeAlamatModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', data.message);
                }
                
                btn.disabled = false;
                btn.innerHTML = originalText;
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Terjadi kesalahan sistem!');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        // Set default
        function setDefault(id) {
            if (!confirm('Yakin ingin menjadikan ini sebagai alamat default?')) return;
            
            const formData = new FormData();
            formData.append('id', id);
            
            fetch('actions/set_default_alamat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Terjadi kesalahan sistem!');
            });
        }

        // Delete
        function deleteAlamat(id) {
            if (!confirm('Yakin ingin menghapus alamat ini?')) return;
            
            fetch(`actions/delete_alamat.php?id=${id}`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Terjadi kesalahan sistem!');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('alamatModal');
            if (event.target === modal) {
                closeAlamatModal();
            }
        }
    </script>

</body>
</html>