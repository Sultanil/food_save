<?php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/functions.php';
require_once 'includes/ongkir_calculator.php';

// 🔐 SECURITY: Harus login
if (!isset($_SESSION['sudah_login']) || $_SESSION['sudah_login'] !== true) {
    header("Location: LoginPage.php?msg=login_required");
    exit;
}

// ==================== AMBIL DATA PRODUK ====================
$produk_id = isset($_GET['produk_id']) ? (int)$_GET['produk_id'] : 0;
$penjual_id = isset($_GET['penjual_id']) ? (int)$_GET['penjual_id'] : 0;

if ($produk_id > 0 && $penjual_id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.nama_produk, p.deskripsi, p.harga_asli, p.harga_diskon, p.stok, p.satuan, p.gambar_url,
               pj.nama_toko, pj.kota, pj.user_id as penjual_user_id, pj.kode_pos as penjual_kode_pos
        FROM produk p
        JOIN penjual pj ON p.penjual_id = pj.id
        WHERE p.id = ? AND p.penjual_id = ? AND p.status = 'aktif' AND p.stok > 0
    ");
    $stmt->execute([$produk_id, $penjual_id]);
    $produk = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produk) {
        die("<div class='min-h-screen flex items-center justify-center bg-gray-50'>
                <div class='text-center p-8'>
                    <div class='text-6xl mb-4'>❌</div>
                    <h2 class='text-xl font-bold text-gray-900 mb-2'>Produk Tidak Ditemukan</h2>
                    <p class='text-gray-500 mb-4'>Produk mungkin sudah habis atau tidak tersedia.</p>
                    <a href='PromosiPage.php' class='inline-block px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-medium'>← Kembali ke Produk</a>
                </div>
            </div>");
    }

    // Harga yang dipakai (diskon jika ada)
    $harga_satuan = !empty($produk['harga_diskon']) && $produk['harga_diskon'] < $produk['harga_asli']
        ? $produk['harga_diskon']
        : $produk['harga_asli'];
} else {
    header("Location: PromosiPage.php");
    exit;
}

// ==================== AMBIL PAYMENT METHODS PENJUAL ====================
$stmt_payment = $pdo->prepare("
    SELECT * FROM seller_payment_methods 
    WHERE penjual_id = ? AND is_active = 1 
    ORDER BY is_default DESC, created_at ASC
");
$stmt_payment->execute([$penjual_id]);
$payment_methods = $stmt_payment->fetchAll(PDO::FETCH_ASSOC);

// Group by type
$bank_accounts = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'bank_transfer');
$qris_list = array_filter($payment_methods, fn($m) => $m['payment_type'] === 'qris');

// Set default payment value untuk form
$default_payment = '';
if (!empty($payment_methods)) {
    foreach ($payment_methods as $pm) {
        if ($pm['is_default']) {
            $default_payment = $pm['payment_type'] === 'bank_transfer'
                ? 'Transfer Bank - ' . $pm['bank_name']
                : 'QRIS';
            break;
        }
    }
    if (empty($default_payment)) {
        $first = reset($payment_methods);
        $default_payment = $first['payment_type'] === 'bank_transfer'
            ? 'Transfer Bank - ' . $first['bank_name']
            : 'QRIS';
    }
}

// ==================== DEFAULT VALUES ====================
$jumlah_produk = 1;
$biaya_layanan = BIAYA_LAYANAN_DEFAULT; // Rp 5.000 dari konstanta
$diskon = 0;
$kode_voucher = '';
$pesan = '';
$pesan_class = '';

// ==================== DATA PEMBELI ====================
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$stmt = $pdo->prepare("SELECT nama_lengkap, email, kode_pos FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// ==================== AMBIL DAFTAR ALAMAT TERSIMPAN ====================
$stmt_alamat = $pdo->prepare("
    SELECT ua.*, kp.kecamatan, kp.kelurahan 
    FROM user_addresses ua
    LEFT JOIN kode_pos kp ON ua.kode_pos = kp.kode_pos
    WHERE ua.user_id = ?
    ORDER BY ua.is_default DESC, ua.created_at DESC
");
$stmt_alamat->execute([$user_id]);
$user_addresses = $stmt_alamat->fetchAll(PDO::FETCH_ASSOC);

// Ambil alamat default
$default_address = null;
foreach ($user_addresses as $addr) {
    if ($addr['is_default'] == 1) {
        $default_address = $addr;
        break;
    }
}
if (!$default_address && !empty($user_addresses)) {
    $default_address = $user_addresses[0];
}

// Pre-fill data dari alamat default atau dari user
if ($default_address) {
    $nama = $default_address['nama_penerima'];
    $telepon = $default_address['telepon'];
    $alamat = $default_address['alamat_lengkap'];
    $kode_pos_pembeli = $default_address['kode_pos'];
} else {
    $nama = $user_data['nama_lengkap'] ?? '';
    $telepon = '';
    $alamat = '';
    $kode_pos_pembeli = $user_data['kode_pos'] ?? $_SESSION['kode_pos'] ?? '';
}

$pembayaran = $default_payment ?: 'Transfer Bank';

// ==================== HITUNG ONGKIR (LOGIKA BARU) ====================
$ongkir_foodsave = hitungOngkir($pdo, $kode_pos_pembeli);
$ongkir = $ongkir_foodsave;
$is_pickup = false; // Default ke delivery
$pengiriman = 'foodsave'; // Default ke delivery

// ==================== HANDLE FORM SUBMIT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jumlah_produk = max(1, (int)($_POST['jumlah_produk'] ?? 1));
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kode_voucher = strtoupper(trim($_POST['voucher'] ?? ''));
    $pembayaran = $_POST['pembayaran'] ?? $default_payment ?: 'Transfer Bank';

    // Hitung subtotal produk
    $harga_produk = $harga_satuan * $jumlah_produk;

    // Tentukan apakah pickup atau delivery
    $is_pickup = isset($_POST['pengiriman']) && (int)$_POST['pengiriman'] === 0;
    $pengiriman = $is_pickup ? 'pickup' : 'foodsave';

    // Hitung semua komponen (ongkir, voucher, total)
    $hasil_hitung = hitungTotalCheckout($pdo, $harga_produk, $kode_pos_pembeli, $kode_voucher, $biaya_layanan);

    // Jika pickup, ongkir jadi 0
    $ongkir = $is_pickup ? 0 : $hasil_hitung['ongkir'];
    $diskon = $hasil_hitung['diskon'];
    $voucher_result = [
        'valid' => $hasil_hitung['voucher_valid'],
        'pesan' => $hasil_hitung['voucher_pesan'],
        'kode' => $hasil_hitung['voucher_kode']
    ];

    // Hitung ulang total dengan ongkir yang sesuai (pickup = 0)
    $total_bayar = max(0, $harga_produk + $biaya_layanan + $ongkir - $diskon);

    if (isset($_POST['apply_voucher'])) {
        if ($voucher_result['valid']) {
            $pesan = $voucher_result['pesan'];
            $pesan_class = 'success';
        } elseif (!empty($kode_voucher)) {
            $pesan = $voucher_result['pesan'];
            $pesan_class = 'error';
        }
    } else {
        // Validasi
        if (empty($nama) || empty($telepon) || empty($alamat)) {
            $pesan = '⚠️ Mohon lengkapi data pembeli terlebih dahulu.';
            $pesan_class = 'error';
        } elseif ($jumlah_produk > $produk['stok']) {
            $pesan = "⚠️ Stok tidak mencukupi. Tersedia: {$produk['stok']} {$produk['satuan']}";
            $pesan_class = 'error';
        } else {
            // ✅ INSERT KE DATABASE
            try {
                $batch_id = generateBatchId($user_id);

                $stmt = $pdo->prepare("
                    INSERT INTO transaksi 
                    (user_id, penjual_id, produk_id, jumlah, total_harga, status, alamat_pengiriman, no_telepon, metode_pembayaran, ongkir, diskon, kode_voucher, checkout_batch_id, shipping_status, shipping_method) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'diproses', ?)
                ");

                $stmt->execute([
                    $user_id,
                    $penjual_id,
                    $produk_id,
                    $jumlah_produk,
                    $total_bayar,
                    $alamat,
                    $telepon,
                    $pembayaran,
                    $ongkir,
                    $diskon,
                    $kode_voucher,
                    $batch_id,
                    $pengiriman
                ]);

                // Kurangi stok produk
                $stmt_update = $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                $stmt_update->execute([$jumlah_produk, $produk_id]);

                // Redirect ke payment_summary.php (halaman upload bukti)
                header("Location: payment_summary.php?batch_id=$batch_id&total=$total_bayar&pembayaran=" . urlencode($pembayaran));
                exit;
            } catch (PDOException $e) {
                $pesan = '❌ Gagal memproses pesanan: ' . $e->getMessage();
                $pesan_class = 'error';
            }
        }
    }
} else {
    // First load: hitung total default
    $harga_produk = $harga_satuan * $jumlah_produk;
    $total_bayar = $harga_produk + $biaya_layanan + $ongkir - $diskon;
}
$is_pickup = ($pengiriman === 'pickup');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FoodSave</title>
    <?php include 'includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        .input-focus { @apply focus:ring-2 focus:ring-brand focus:border-brand outline-none transition; }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-brand flex items-center gap-2">
                🌿 FoodSave
            </a>
            <div class="flex items-center gap-3">
                <a href="PromosiPage.php" class="text-sm text-gray-600 hover:text-brand font-medium">← Kembali Belanja</a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="max-w-5xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>
            <p class="text-gray-500 mt-2">Selesaikan pembelian makanan surplusmu dengan mudah</p>
        </div>

        <!-- Alert Message -->
        <?php if ($pesan): ?>
            <div class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success'
                                                            ? 'bg-green-50 border-green-200 text-green-700'
                                                            : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= $pesan ?>
            </div>
        <?php endif; ?>

        <!-- Checkout Grid -->
        <div class="grid lg:grid-cols-3 gap-6">

            <!-- FORM KIRI (2/3) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Ringkasan Produk -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">📦</span>
                        Produk yang Dibeli
                    </h2>

                    <div class="flex gap-4 p-4 bg-gray-50 rounded-lg">
                        <?php if ($produk['gambar_url']): ?>
                            <img src="<?= htmlspecialchars($produk['gambar_url']) ?>"
                                alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                                class="w-20 h-20 rounded-lg object-cover bg-white">
                        <?php else: ?>
                            <div class="w-20 h-20 rounded-lg bg-gray-200 flex items-center justify-center text-2xl">📷</div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($produk['nama_produk']) ?></h3>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($produk['nama_toko']) ?> • <?= htmlspecialchars($produk['kota']) ?></p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="font-bold text-brand"><?= formatRupiah($harga_satuan) ?></span>
                                <?php if ($produk['harga_diskon'] && $produk['harga_diskon'] < $produk['harga_asli']): ?>
                                    <span class="text-sm text-gray-400 line-through"><?= formatRupiah($produk['harga_asli']) ?></span>
                                    <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">
                                        -<?= round((1 - $produk['harga_diskon'] / $produk['harga_asli']) * 100) ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Qty Selector -->
                    <div class="mt-4 flex items-center gap-3">
                        <label class="text-sm font-medium text-gray-700">Jumlah:</label>
                        <div class="flex items-center border border-gray-300 rounded-lg">
                            <button type="button" onclick="updateQty(-1)"
                                class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-l-lg disabled:opacity-50"
                                <?= $jumlah_produk <= 1 ? 'disabled' : '' ?>>−</button>
                            <input type="number" name="jumlah_produk" value="<?= $jumlah_produk ?>" min="1" max="<?= $produk['stok'] ?>"
                                class="w-16 text-center border-0 focus:ring-0 text-sm font-medium" readonly>
                            <button type="button" onclick="updateQty(1)"
                                class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-r-lg disabled:opacity-50"
                                <?= $jumlah_produk >= $produk['stok'] ? 'disabled' : '' ?>>+</button>
                        </div>
                        <span class="text-sm text-gray-500">Stok tersedia: <?= $produk['stok'] ?> <?= htmlspecialchars($produk['satuan']) ?></span>
                    </div>
                </div>

                <!-- Form Data -->
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="produk_id" value="<?= $produk_id ?>">
                    <input type="hidden" name="penjual_id" value="<?= $penjual_id ?>">
                    <input type="hidden" name="harga_satuan" value="<?= $harga_satuan ?>">
                    <input type="hidden" name="jumlah_produk" id="hidden_jumlah_produk" value="<?= $jumlah_produk ?>">

                    <!-- Data Pembeli -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">👤</span>
                            Data Pembeli
                        </h2>

                        <!-- Pilih Alamat Tersimpan -->
                        <?php if (!empty($user_addresses)): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">📍 Pilih Alamat Pengiriman</label>
                                <select id="selectAlamat" onchange="pilihAlamat(this.value)"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand outline-none bg-white">
                                    <option value="">-- Pilih Alamat --</option>
                                    <?php foreach ($user_addresses as $addr): ?>
                                        <option value="<?= htmlspecialchars(json_encode($addr)) ?>"
                                            <?= ($default_address && $addr['id'] === $default_address['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($addr['nama_penerima']) ?> -
                                            <?= htmlspecialchars($addr['kelurahan'] ?? '') ?>, <?= htmlspecialchars($addr['kecamatan'] ?? '') ?>
                                            <?= $addr['is_default'] ? ' ⭐' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="new">+ Tambah Alamat Baru</option>
                                </select>
                                <a href="alamat_saya.php" class="text-sm text-brand hover:underline mt-2 inline-block">
                                    🔧 Kelola Alamat
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="text-sm text-amber-800 mb-2">⚠️ Anda belum memiliki alamat tersimpan.</p>
                                <a href="alamat_saya.php" class="text-sm text-brand hover:underline font-medium">
                                    + Tambah Alamat Sekarang
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Penerima *</label>
                                <input type="text" name="nama" id="inputNama" value="<?= htmlspecialchars($nama) ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus"
                                    placeholder="Nama Penerima">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WhatsApp *</label>
                                <input type="tel" name="telepon" id="inputTelepon" value="<?= htmlspecialchars($telepon) ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus"
                                    placeholder="08xxxxxxxxxx">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Pengiriman Lengkap *</label>
                                <textarea name="alamat" id="inputAlamat" rows="3"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus resize-none"
                                    placeholder="Jl. Contoh No. 123, RT/RW, Kelurahan, Kecamatan, Kota"><?= htmlspecialchars($alamat) ?></textarea>
                                <?php if (!empty($kode_pos_pembeli)): ?>
                                    <p class="text-xs text-gray-400 mt-1" id="kodePosDisplay">
                                        📮 Kode Pos: <strong><?= htmlspecialchars($kode_pos_pembeli) ?></strong>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pengiriman -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">🚚</span>
                            Metode Pengiriman
                        </h2>

                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-brand transition <?= !$is_pickup ? 'border-brand bg-brand/5' : '' ?>">
                                <input type="radio" name="pengiriman" value="<?= $ongkir_foodsave ?>" class="w-4 h-4 text-brand" <?= !$is_pickup ? 'checked' : '' ?> onchange="hitungTotal()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">🌿 FoodSave Delivery</span>
                                    <p class="text-sm text-gray-500">Estimasi 1-3 jam • Ongkir dari Hub: Rp 750/km</p>
                                </div>
                                <span class="font-semibold text-brand"><?= formatRupiah($ongkir_foodsave) ?></span>
                            </label>

                            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-brand transition <?= $is_pickup ? 'border-brand bg-brand/5' : '' ?>">
                                <input type="radio" name="pengiriman" value="0" class="w-4 h-4 text-brand" <?= $is_pickup ? 'checked' : '' ?> onchange="hitungTotal()">
                                <div class="flex-1">
                                    <span class="font-medium text-gray-900">🏪 Ambil Sendiri di Toko</span>
                                    <p class="text-sm text-gray-500">Gratis • <?= htmlspecialchars($produk['nama_toko']) ?></p>
                                </div>
                                <span class="font-semibold text-green-600">Gratis</span>
                            </label>
                        </div>
                    </div>

                    <!-- Pembayaran -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">💳</span>
                            Metode Pembayaran
                        </h2>

                        <?php if (empty($payment_methods)): ?>
                            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
                                ⚠️ Penjual belum menambahkan metode pembayaran. Silakan hubungi penjual untuk konfirmasi.
                            </div>
                            <input type="hidden" name="pembayaran" value="Transfer Manual">
                        <?php else: ?>

                            <!-- BANK TRANSFER OPTIONS -->
                            <?php if (!empty($bank_accounts)): ?>
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                        🏦 Transfer Bank
                                    </h3>
                                    <div class="space-y-3">
                                        <?php foreach ($bank_accounts as $bank):
                                            $value = 'Transfer Bank - ' . $bank['bank_name'];
                                            $is_selected = ($pembayaran === $value);
                                        ?>
                                            <label class="flex items-start gap-3 p-4 border-2 <?= $is_selected ? 'border-brand bg-brand/5' : 'border-gray-200' ?> rounded-xl cursor-pointer hover:border-brand transition">
                                                <input type="radio"
                                                    name="pembayaran"
                                                    value="<?= htmlspecialchars($value) ?>"
                                                    class="mt-1 w-4 h-4 text-brand"
                                                    <?= $is_selected ? 'checked' : '' ?>>
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="text-xl">🏦</span>
                                                        <span class="font-semibold text-gray-900"><?= htmlspecialchars($bank['bank_name']) ?></span>
                                                        <?php if ($bank['is_default']): ?>
                                                            <span class="px-2 py-0.5 bg-brand/10 text-brand text-xs font-bold rounded-full">⭐ Default</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ml-8 space-y-1 text-sm">
                                                        <p class="text-gray-700">
                                                            <span class="text-gray-500">No. Rek:</span>
                                                            <strong class="font-mono text-base"><?= htmlspecialchars($bank['account_number']) ?></strong>
                                                        </p>
                                                        <p class="text-gray-700">
                                                            <span class="text-gray-500">A/N:</span>
                                                            <?= htmlspecialchars($bank['account_holder']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- QRIS OPTIONS -->
                            <?php if (!empty($qris_list)): ?>
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                        📱 QRIS
                                    </h3>
                                    <div class="space-y-3">
                                        <?php foreach ($qris_list as $qris):
                                            $value = 'QRIS';
                                            $is_selected = ($pembayaran === $value);
                                        ?>
                                            <label class="flex items-start gap-3 p-4 border-2 <?= $is_selected ? 'border-brand bg-brand/5' : 'border-gray-200' ?> rounded-xl cursor-pointer hover:border-brand transition">
                                                <input type="radio"
                                                    name="pembayaran"
                                                    value="<?= htmlspecialchars($value) ?>"
                                                    class="mt-1 w-4 h-4 text-brand"
                                                    <?= $is_selected ? 'checked' : '' ?>>
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="text-xl">📱</span>
                                                        <span class="font-semibold text-gray-900">QRIS Code</span>
                                                        <?php if ($qris['is_default']): ?>
                                                            <span class="px-2 py-0.5 bg-brand/10 text-brand text-xs font-bold rounded-full">⭐ Default</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-sm text-gray-600 ml-8">Scan kode QR untuk pembayaran instan</p>

                                                    <?php if (!empty($qris['qris_image']) && $is_selected): ?>
                                                        <div class="mt-3 ml-8">
                                                            <img src="<?= htmlspecialchars($qris['qris_image']) ?>"
                                                                alt="QRIS"
                                                                class="w-40 h-40 object-cover rounded-lg border border-gray-200 shadow-sm">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-xs text-blue-800">
                                    ℹ️ <strong>Cara Bayar:</strong> Pilih metode di atas, lalu klik "Bayar Sekarang". Anda akan diarahkan ke halaman upload bukti pembayaran.
                                </p>
                            </div>

                        <?php endif; ?>
                    </div>

                    <!-- Voucher -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center text-brand">🎁</span>
                            Kode Voucher
                        </h2>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                            <p class="text-sm text-yellow-800">
                                <strong>Voucher tersedia:</strong><br>
                                <code class="bg-white px-2 py-0.5 rounded">FOODSAVE10</code> = Diskon 10% (Maks <?= formatRupiah(10000) ?>)<br>
                                <code class="bg-white px-2 py-0.5 rounded">FOODSAVE20</code> = Diskon 20% (Maks <?= formatRupiah(20000) ?>)
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <input type="text" name="voucher" value="<?= htmlspecialchars($kode_voucher) ?>" placeholder="Masukkan kode voucher"
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg input-focus uppercase">
                            <button type="submit" name="apply_voucher"
                                class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">
                                Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="bayar_sekarang"
                        class="w-full py-4 bg-brand hover:bg-brand-dark text-white font-semibold rounded-xl transition shadow-lg hover:shadow-xl text-lg cursor-pointer">
                        Bayar Sekarang • <span id="btnTotal"><?= formatRupiah($total_bayar) ?></span>
                    </button>

                    <p class="text-center text-sm text-gray-500">
                        🔒 Data kamu aman dan terenkripsi
                    </p>
                </form>
            </div>

            <!-- SUMMARY KANAN (1/3) - Sticky -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Pesanan</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Produk</span>
                            <span class="font-medium"><?= htmlspecialchars($produk['nama_produk']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Jumlah</span>
                            <span class="font-medium"><?= $jumlah_produk ?> <?= htmlspecialchars($produk['satuan']) ?></span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-dashed">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="font-medium" id="subtotalDisplay"><?= formatRupiah($harga_produk) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Biaya Layanan</span>
                            <span class="font-medium"><?= formatRupiah($biaya_layanan) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Pengiriman</span>
                            <span class="font-medium" id="ongkirDisplay"><?= $ongkir === 0 ? 'Gratis' : formatRupiah($ongkir) ?></span>
                        </div>
                        <div class="flex justify-between text-green-600">
                            <span>Diskon</span>
                            <span id="diskonDisplay">- <?= formatRupiah($diskon) ?></span>
                        </div>
                    </div>

                    <div class="flex justify-between text-lg font-bold text-gray-900 pt-4 mt-4 border-t-2 border-brand">
                        <span>Total Bayar</span>
                        <span id="totalDisplay"><?= formatRupiah($total_bayar) ?></span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-6 pt-4 border-t">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>Proses Pesanan</span>
                            <span>3/3</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-brand w-full rounded-full"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                            <span class="text-brand font-medium">✓ Keranjang</span>
                            <span class="text-brand font-medium">✓ Data</span>
                            <span class="text-brand font-medium">✓ Bayar</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="py-6 px-4 bg-gray-900 text-gray-400 text-center mt-12">
        <p>© <?= date('Y') ?> FoodSave. All rights reserved.</p>
    </footer>

    <!-- Inject data PHP ke JavaScript -->
    <script>
        const hargaSatuan = <?= $harga_satuan ?>;
        const biayaLayanan = <?= $biaya_layanan ?>;
        let diskon = <?= $diskon ?>;
        const stokMaks = <?= $produk['stok'] ?>;
        const ongkirFoodsave = <?= $ongkir_foodsave ?>;

        // Fungsi hitung total
        function hitungTotal() {
            console.log('💰 [HITUNG TOTAL] Mulai...');

            // 1. Ambil jumlah produk
            const jumlahInput = document.querySelector('input[name="jumlah_produk"]');
            const jumlah = parseInt(jumlahInput?.value) || 1;
            console.log('   - Jumlah:', jumlah);

            // 2. Ambil pengiriman yang dipilih
            const pengirimanRadio = document.querySelector('input[name="pengiriman"]:checked');
            const pengirimanValue = pengirimanRadio ? parseInt(pengirimanRadio.value) : 0;
            console.log('   - Pengiriman value:', pengirimanValue);

            // 3. Tentukan ongkir
            let ongkir = 0;
            if (pengirimanValue === 0) {
                ongkir = 0; // Pickup
                console.log('   - Pickup dipilih, ongkir = 0');
            } else {
                // Delivery - pakai dari window atau fallback
                ongkir = window.ongkirFoodsave || <?= $ongkir_foodsave ?>;
                console.log('   - Delivery dipilih, ongkir =', ongkir);
            }

            // 4. Hitung subtotal
            const subtotal = hargaSatuan * jumlah;
            console.log('   - Subtotal:', formatRupiah(subtotal));

            // 5. Hitung total
            const total = Math.max(0, subtotal + biayaLayanan + ongkir - diskon);
            console.log('   - Total:', formatRupiah(total));
            console.log('     (Subtotal:', formatRupiah(subtotal),
                '+ Layanan:', formatRupiah(biayaLayanan),
                '+ Ongkir:', formatRupiah(ongkir),
                '- Diskon:', formatRupiah(diskon) + ')');

            // 6. Update semua elemen yang menampilkan total
            const elementsToUpdate = [{
                    id: 'subtotalDisplay',
                    value: formatRupiah(subtotal)
                },
                {
                    id: 'ongkirDisplay',
                    value: ongkir === 0 ? 'Gratis' : formatRupiah(ongkir)
                },
                {
                    id: 'diskonDisplay',
                    value: '- ' + formatRupiah(diskon)
                },
                {
                    id: 'totalDisplay',
                    value: formatRupiah(total)
                },
                {
                    id: 'btnTotal',
                    value: formatRupiah(total)
                }
            ];

            elementsToUpdate.forEach(item => {
                const el = document.getElementById(item.id);
                if (el) {
                    el.textContent = item.value;
                    console.log(`   - Updated #${item.id}:`, item.value);
                } else {
                    console.error(`   - Element #${item.id} TIDAK ditemukan!`);
                }
            });

            // 7. Update hidden input
            const hiddenInput = document.getElementById('hidden_jumlah_produk');
            if (hiddenInput) {
                hiddenInput.value = jumlah;
            }

            console.log('💰 [HITUNG TOTAL] SELESAI!');
        }

        function formatRupiah(angka) {
            return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateQty(change) {
            const input = document.querySelector('input[name="jumlah_produk"]');
            let currentQty = parseInt(input.value) || 1;
            let newQty = currentQty + change;

            if (newQty < 1) newQty = 1;
            if (newQty > stokMaks) newQty = stokMaks;

            input.value = newQty;

            const buttons = document.querySelectorAll('button[onclick^="updateQty"]');
            buttons[0].disabled = (newQty <= 1);
            buttons[1].disabled = (newQty >= stokMaks);

            hitungTotal();
        }

        // Fungsi pilih alamat dari dropdown
        async function pilihAlamat(jsonData) {
            console.log('🎯 pilihAlamat called!');
            console.log('   Data:', jsonData);

            if (jsonData === 'new') {
                window.location.href = 'alamat_saya.php';
                return;
            }

            if (!jsonData) {
                console.log('   ⚠️ jsonData kosong!');
                return;
            }

            try {
                const alamat = JSON.parse(jsonData);
                console.log('   ✅ Parsed alamat:', alamat);

                // Fill form
                document.getElementById('inputNama').value = alamat.nama_penerima || '';
                document.getElementById('inputTelepon').value = alamat.telepon || '';
                document.getElementById('inputAlamat').value = alamat.alamat_lengkap || '';

                const kodePosElement = document.getElementById('kodePosDisplay');
                if (kodePosElement && alamat.kode_pos) {
                    kodePosElement.innerHTML = `📮 Kode Pos: <strong>${alamat.kode_pos}</strong>`;
                    console.log('   📮 Kode pos:', alamat.kode_pos);

                    await updateOngkir(alamat.kode_pos);
                } else {
                    console.log('   ⚠️ kodePosElement atau alamat.kode_pos tidak ada');
                }
            } catch (e) {
                console.error('   ❌ Error parsing alamat:', e);
            }
        }

        // Fungsi update ongkir via AJAX
        async function updateOngkir(kodePos) {
            console.log('🔄 [UPDATE ONGKIR] Mulai untuk kode pos:', kodePos);

            try {
                const response = await fetch(`assets/api/get_ongkir.php?kode_pos=${kodePos}`);
                const data = await response.json();

                console.log('📡 [UPDATE ONGKIR] Response:', data);

                if (data.success) {
                    console.log('✅ [UPDATE ONGKIR] API berhasil!');
                    console.log('   - Jarak:', data.jarak_km, 'km');
                    console.log('   - Ongkir baru:', data.formatted_ongkir);

                    // 1. Update variabel global
                    window.ongkirFoodsave = data.ongkir;
                    console.log('   - window.ongkirFoodsave =', window.ongkirFoodsave);

                    // 2. Update radio button delivery (yang bukan pickup)
                    const allRadios = document.querySelectorAll('input[name="pengiriman"]');
                    console.log('📻 [UPDATE ONGKIR] Total radio buttons:', allRadios.length);

                    allRadios.forEach((radio, index) => {
                        console.log(`   Radio ${index}: value="${radio.value}", checked=${radio.checked}`);
                    });

                    // Cari radio delivery (value bukan "0")
                    const radioDelivery = document.querySelector('input[name="pengiriman"]:not([value="0"])');

                    if (radioDelivery) {
                        console.log('✅ [UPDATE ONGKIR] Radio delivery ditemukan!');

                        // Update VALUE radio button
                        radioDelivery.value = data.ongkir;
                        console.log('   - Updated value to:', radioDelivery.value);

                        // Cari dan update label
                        const label = radioDelivery.closest('label');
                        if (label) {
                            console.log('   - Label ditemukan');

                            // Update semua elemen yang mungkin menampilkan harga
                            const priceElements = label.querySelectorAll('.font-bold, [class*="font-bold"]');
                            console.log('   - Jumlah elemen harga ditemukan:', priceElements.length);

                            priceElements.forEach(el => {
                                el.textContent = data.formatted_ongkir;
                                console.log('   - Updated element:', el.textContent);
                            });

                            // Fallback: cari elemen dengan class text-brand atau text-green-600
                            const priceByColor = label.querySelector('.text-brand, .text-green-600');
                            if (priceByColor) {
                                priceByColor.textContent = data.formatted_ongkir;
                                console.log('   - Updated by color class:', priceByColor.textContent);
                            }
                        } else {
                            console.error('   ❌ Label tidak ditemukan!');
                        }
                    } else {
                        console.error('   ❌ Radio delivery TIDAK ditemukan!');
                        console.error('   Mungkin semua radio memiliki value="0"?');
                    }

                    // 3. Update summary di kanan (element #ongkirDisplay)
                    const ongkirDisplay = document.getElementById('ongkirDisplay');
                    if (ongkirDisplay) {
                        console.log('✅ [UPDATE ONGKIR] Element #ongkirDisplay ditemukan');

                        // Cek apakah pickup yang dipilih
                        const checkedRadio = document.querySelector('input[name="pengiriman"]:checked');
                        const isCheckedPickup = checkedRadio && checkedRadio.value === '0';

                        if (isCheckedPickup) {
                            ongkirDisplay.innerHTML = 'Gratis (Pick Up) <span class="text-xs text-gray-400 block">(Ambil Sendiri)</span>';
                            console.log('   - Pickup dipilih, ongkir = Gratis');
                        } else {
                            ongkirDisplay.innerHTML = `${data.formatted_ongkir} <span class="text-xs text-gray-400 block">(FoodSave Delivery)</span>`;
                            console.log('   - Delivery dipilih, ongkir =', data.formatted_ongkir);
                        }
                    } else {
                        console.error('   ❌ Element #ongkirDisplay TIDAK ditemukan!');
                    }

                    // 4. Update total bayar
                    console.log('🧮 [UPDATE ONGKIR] Memanggil hitungTotal()...');
                    hitungTotal();

                    console.log('✅ [UPDATE ONGKIR] SELESAI!');

                    // 5. Force re-render dengan toggle class
                    const deliveryLabel = document.querySelector('input[name="pengiriman"]:not([value="0"])')?.closest('label');
                    if (deliveryLabel) {
                        // Toggle class untuk trigger re-render
                        deliveryLabel.classList.remove('border-green-500');
                        setTimeout(() => {
                            deliveryLabel.classList.add('border-green-500');
                        }, 10);

                        console.log('🎨 [UPDATE ONGKIR] Force re-render label');
                    }

                    // 6. Scroll sedikit ke bawah dan atas untuk trigger repaint
                    window.scrollTo(0, window.scrollY + 1);
                    setTimeout(() => {
                        window.scrollTo(0, window.scrollY - 1);
                    }, 50);

                    console.log('✅ [UPDATE ONGKIR] SELESAI TOTAL!');

                    // 7. Alert untuk konfirmasi visual (bisa dihapus nanti)
                    // alert(`✅ Ongkir berhasil diupdate!\n\nTotal Bayar: ${formatRupiah(total)}`);

                } else {
                    console.error('❌ [UPDATE ONGKIR] API return error:', data.message);
                }
            } catch (error) {
                console.error('❌ [UPDATE ONGKIR] Exception:', error);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            hitungTotal();
        });
    </script>

</body>

</html>