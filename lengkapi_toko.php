<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// Cek login & role penjual
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'penjual') {
    header("Location: LoginPage.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$error = '';
$success = '';

// Ambil list kode pos untuk dropdown (PDO)
$kode_pos_list = $pdo->query("SELECT kode_pos, kecamatan, kelurahan FROM kode_pos ORDER BY kecamatan, kelurahan");

// Cek apakah sudah punya data toko
$cek = $pdo->prepare("SELECT * FROM penjual WHERE user_id = ?");
$cek->execute([$user_id]);
$toko = $cek->fetch(PDO::FETCH_ASSOC);

if ($toko) {
    // Jika sudah disetujui atau sedang pending, jangan izinkan edit
    if ($toko['status_verifikasi'] === 'disetujui' || $toko['status_verifikasi'] === 'pending') {
        header("Location: dashboardPenjual.php");
        exit;
    }
    // Jika 'ditolak', biarkan penjual berada di halaman ini untuk resubmission
}

// ==================== PROSES FORM SUBMIT ====================
if (isset($_POST['submit'])) {
    // Ambil input
    $nama_toko = trim($_POST['nama_toko'] ?? '');
    $kota = trim($_POST['kota'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $kode_pos = trim($_POST['kode_pos'] ?? '');

    // ==================== UPLOAD FOTO KTP ====================
    $foto_ktp = $toko['foto_ktp'] ?? '';
    if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/ktp/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["foto_ktp"]["name"], PATHINFO_EXTENSION);
        $new_filename = "ktp_" . $user_id . "_" . time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["foto_ktp"]["tmp_name"], $target_file)) {
            // Hapus file lama jika ada
            if (!empty($toko['foto_ktp']) && file_exists($toko['foto_ktp'])) {
                unlink($toko['foto_ktp']);
            }
            $foto_ktp = $target_file;
        }
    }

    // ==================== UPLOAD FOTO PROFIL TOKO ====================
    $foto_profil = $toko['foto_profil'] ?? '';
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_profil'];
        
        // Validasi tipe file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = "Format foto tidak valid! Gunakan JPG, PNG, atau WEBP.";
        }
        
        // Validasi ukuran (Maks 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = "Ukuran foto terlalu besar! Maksimal 2MB.";
        }
        
        // Jika validasi lolos, upload file
        if (empty($error)) {
            $target_dir = "uploads/profil_toko/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = "toko_" . $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Hapus file lama jika ada
                if (!empty($toko['foto_profil']) && file_exists($toko['foto_profil'])) {
                    unlink($toko['foto_profil']);
                }
                $foto_profil = $target_file;
            } else {
                $error = "Gagal mengupload foto profil.";
            }
        }
    }

    // ==================== VALIDASI INPUT ====================
    if (empty($error)) {
        if (empty($nama_toko) || empty($kota) || empty($nik) || empty($kode_pos)) {
            $error = "Nama toko, kota, NIK, dan kode pos wajib diisi!";
        } elseif (strlen($nik) !== 16 || !is_numeric($nik)) {
            $error = "NIK harus terdiri dari 16 digit angka!";
        } elseif (empty($foto_ktp)) {
            $error = "Foto KTP wajib diunggah!";
        }
    }

    // ==================== SIMPAN KE DATABASE ====================
    if (empty($error)) {
        try {
            if ($toko) {
                // UPDATE (Resubmit setelah ditolak)
                $stmt = $pdo->prepare("
                    UPDATE penjual 
                    SET nama_toko = ?, foto_profil = ?, kota = ?, alamat = ?, no_telp = ?, nik = ?, foto_ktp = ?, kode_pos = ?, status_verifikasi = 'pending', alasan_penolakan = NULL 
                    WHERE user_id = ?
                ");
                $stmt->execute([$nama_toko, $foto_profil, $kota, $alamat, $no_telp, $nik, $foto_ktp, $kode_pos, $user_id]);
            } else {
                // INSERT (Pendaftaran baru)
                $stmt = $pdo->prepare("
                    INSERT INTO penjual (user_id, nama_toko, foto_profil, kota, kode_pos, alamat, no_telp, nik, foto_ktp, status_verifikasi) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$user_id, $nama_toko, $foto_profil, $kota, $kode_pos, $alamat, $no_telp, $nik, $foto_ktp]);
            }
            
            $_SESSION['toko_step_1_done'] = true;
            header("Location: setup_payment.php");
            exit;
            
        } catch (PDOException $e) {
            $error = "Gagal menyimpan data toko: " . $e->getMessage();
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-8 rounded-2xl shadow-lg max-w-md w-full">
        <div class="text-center mb-6">
            <div class="text-5xl mb-3">🏪</div>
            <h1 class="text-2xl font-bold text-gray-900">Lengkapi Profil Toko</h1>
            <p class="text-gray-500 text-sm mt-2">Isi data toko Anda untuk mulai mengajukan verifikasi</p>
        </div>

        <!-- Alert Status Ditolak -->
        <?php if ($toko && $toko['status_verifikasi'] === 'ditolak'): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200 mb-5 text-sm">
                <h4 class="font-bold mb-1">❌ Pendaftaran Sebelumnya Ditolak</h4>
                <p><strong>Alasan:</strong> <?= htmlspecialchars($toko['alasan_penolakan']) ?></p>
                <p class="mt-2 text-xs text-red-500">Silakan koreksi data Anda di bawah ini dan kirim ulang.</p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm border-l-4 border-red-500">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            
            <!-- Upload Foto Profil Toko -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Foto Profil Toko / Logo</label>
                <div class="flex items-center gap-6">
                    <!-- Preview Area -->
                    <div class="w-32 h-32 rounded-2xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden relative group">
                        <?php if (!empty($toko['foto_profil']) && file_exists($toko['foto_profil'])): ?>
                            <img id="previewFotoToko" src="<?= htmlspecialchars($toko['foto_profil']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <img id="previewFotoToko" src="" class="w-full h-full object-cover hidden">
                            <div id="placeholderFotoToko" class="text-center p-2">
                                <div class="text-3xl mb-1">🏪</div>
                                <p class="text-[10px] text-gray-500 font-medium">Belum ada foto</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Upload Button -->
                    <div class="flex-1">
                        <input type="file" name="foto_profil" id="foto_profil" accept="image/*" class="hidden" onchange="previewImage(this)">
                        <label for="foto_profil" class="inline-block px-5 py-2.5 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl cursor-pointer transition shadow-sm">
                            📷 Pilih Foto
                        </label>
                        <p class="text-xs text-gray-500 mt-2">
                            JPG, PNG, atau WEBP. Maksimal 2MB.<br>
                            <span class="text-gray-400">Disarankan ukuran persegi (1:1) agar terlihat rapi.</span>
                        </p>
                        <?php if (!empty($toko['foto_profil'])): ?>
                            <p class="text-xs text-gray-400 mt-1">✓ Foto sudah terunggah (Pilih file baru jika ingin mengganti)</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Nama Toko <span class="text-red-500">*</span>
                </label>
                <input type="text" name="nama_toko" required
                    value="<?= htmlspecialchars($toko['nama_toko'] ?? '') ?>"
                    placeholder="Contoh: Warung Berkah"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Kota <span class="text-red-500">*</span>
                </label>
                <input type="text" name="kota" required
                    value="<?= htmlspecialchars($toko['kota'] ?? '') ?>"
                    placeholder="Contoh: Surakarta"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Kode Pos Toko <span class="text-red-500">*</span>
                </label>
                <select name="kode_pos" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none bg-white transition">
                    <option value="">Pilih Kecamatan & Kelurahan</option>
                    <?php 
                    // Reset pointer untuk loop kedua
                    $kode_pos_list->execute();
                    while ($kp = $kode_pos_list->fetch(PDO::FETCH_ASSOC)): 
                    ?>
                        <option value="<?= htmlspecialchars($kp['kode_pos']) ?>" <?= (isset($toko['kode_pos']) && $toko['kode_pos'] == $kp['kode_pos']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kp['kecamatan']) ?> - <?= htmlspecialchars($kp['kelurahan']) ?> (<?= $kp['kode_pos'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Alamat Lengkap Toko
                </label>
                <textarea name="alamat" rows="2"
                    placeholder="Jl. Slamet Riyadi No. 123, Laweyan"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none resize-none transition"><?= htmlspecialchars($toko['alamat'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Nomor Telepon/WhatsApp
                </label>
                <input type="tel" name="no_telp"
                    value="<?= htmlspecialchars($toko['no_telp'] ?? '') ?>"
                    placeholder="081234567890"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
            </div>

            <div class="border-t pt-4 mt-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3">🛡️ Dokumen Identitas Penjual</h3>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        NIK Penjual (16 Digit) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nik" required maxlength="16" minlength="16"
                        value="<?= htmlspecialchars($toko['nik'] ?? '') ?>"
                        placeholder="337201xxxxxxxxxx"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Unggah Foto KTP <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="foto_ktp" accept="image/*" <?= empty($toko['foto_ktp']) ? 'required' : '' ?>
                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 transition">
                    <?php if (!empty($toko['foto_ktp'])): ?>
                        <p class="text-xs text-gray-400 mt-1">✓ KTP Sudah Terunggah (Pilih file baru jika ingin mengganti)</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" name="submit"
                    class="w-full py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:shadow transition cursor-pointer">
                    Kirim Pengajuan Toko
                </button>
            </div>
        </form>

        <div class="mt-5 text-center">
            <a href="logout.php" class="text-sm text-red-500 hover:underline">Logout</a>
        </div>
    </div>

    <!-- Script untuk Preview Gambar (di bawah, bukan di tengah form) -->
    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('previewFotoToko');
                const placeholder = document.getElementById('placeholderFotoToko');
                
                img.src = e.target.result;
                img.classList.remove('hidden');
                if (placeholder) {
                    placeholder.classList.add('hidden');
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

</body>

</html>