<?php
// checkout.php - Checkout dari keranjang (multi-item) - PDO + AJAX
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';
require_once 'includes/functions.php';
require_once 'includes/ongkir_calculator.php';

// 🔐 Security check
if (!isset($_SESSION['sudah_login']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: LoginPage.php");
    exit;
}

// ==================== DETEKSI AJAX REQUEST ====================
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Helper function untuk return JSON response
function jsonResponse($status, $message, $extra = [])
{
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));
    exit;
}

// ==================== AMBIL DATA USER ====================
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$kode_pos_pembeli = $_SESSION['kode_pos'] ?? '';

// ==================== AMBIL ALAMAT USER ====================
$stmt = $pdo->prepare("
    SELECT ua.*, kp.kecamatan, kp.kelurahan 
    FROM user_addresses ua
    LEFT JOIN kode_pos kp ON ua.kode_pos = kp.kode_pos
    WHERE ua.user_id = ?
    ORDER BY ua.is_default DESC, ua.created_at DESC
");
$stmt->execute([$user_id]);
$user_addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil alamat default
$default_address = array_filter($user_addresses, fn($a) => $a['is_default'] == 1);
$default_address = reset($default_address) ?: ($user_addresses[0] ?? null);

// Pre-fill data dari alamat default
if ($default_address) {
    $nama = $default_address['nama_penerima'];
    $telepon = $default_address['telepon'];
    $alamat = $default_address['alamat_lengkap'];
    $kode_pos_pembeli = $default_address['kode_pos'];
} else {
    $nama = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? '';
    $telepon = '';
    $alamat = '';
}

// ==================== AMBIL PAYLOAD DARI POST ====================
$cart_items_raw = $_POST['cart_items'] ?? '';
$cart_items = json_decode($cart_items_raw, true);
$subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0;

if (empty($cart_items)) {
    if ($is_ajax) {
        jsonResponse('error', 'Keranjang kosong!');
    } else {
        header("Location: keranjang.php");
        exit;
    }
}

// ==================== HITUNG ONGKIR KONSOLIDASI ====================
$seller_positions = array_unique(array_filter(array_column($cart_items, 'penjual_kode_pos')));
$hasil_konsolidasi = hitungBiayaKonsolidasi($pdo, $seller_positions, $kode_pos_pembeli);
$ongkir_konsolidasi = $hasil_konsolidasi['ongkir'];
$biaya_layanan = $hasil_konsolidasi['biaya_layanan'];

// ==================== DEFAULT VALUES ====================
$nama = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? '';
$telepon = '';
$alamat = '';
$kode_voucher = '';
$diskon = 0;
$pembayaran = 'Transfer Bank';
$pengiriman = 'foodsave';
$ongkir = $ongkir_konsolidasi;
$pesan = '';
$pesan_class = '';

// ==================== HANDLE FORM SUBMIT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $pembayaran = $_POST['pembayaran'] ?? 'Transfer Bank';
    $pengiriman = $_POST['pengiriman'] ?? 'foodsave';

    // Hitung ongkir berdasarkan pilihan pengiriman
    if ($pengiriman === 'pickup') {
        $ongkir = 0;
    } else {
        $ongkir = $ongkir_konsolidasi;
    }

    // Voucher logic
    $kode_voucher_input = strtoupper(trim($_POST['voucher'] ?? ''));
    if (!empty($kode_voucher_input)) {
        $voucher_result = hitungDiskonVoucher($kode_voucher_input, $subtotal);

        if ($voucher_result['valid']) {
            $diskon = $voucher_result['diskon'];
            $kode_voucher = $voucher_result['kode'];
        } else {
            $diskon = 0;
            $kode_voucher = '';
            if (!isset($_POST['bayar_sekarang'])) {
                $pesan = $voucher_result['pesan'];
                $pesan_class = 'error';
            }
        }
    } else {
        $diskon = 0;
        $kode_voucher = '';
    }

    // Hitung total
    $total_bayar = $subtotal + $biaya_layanan + $ongkir - $diskon;

    // ==================== HANDLE "TERAPKAN VOUCHER" (AJAX) ====================
    if (isset($_POST['apply_voucher'])) {
        if ($is_ajax) {
            if (!empty($kode_voucher)) {
                jsonResponse('success', '🎉 Voucher berhasil diterapkan!', [
                    'diskon' => $diskon,
                    'kode_voucher' => $kode_voucher,
                    'total_bayar' => $total_bayar,
                    'formatted_diskon' => 'Rp ' . number_format($diskon, 0, ',', '.'),
                    'formatted_total' => 'Rp ' . number_format($total_bayar, 0, ',', '.')
                ]);
            } else {
                jsonResponse('error', $voucher_result['pesan'] ?? 'Kode voucher tidak valid');
            }
        } else {
            if (!empty($kode_voucher)) {
                $pesan = '🎉 Voucher ' . $kode_voucher . ' berhasil diterapkan!';
                $pesan_class = 'success';
            }
        }
    }

    // ==================== HANDLE "BAYAR SEKARANG" (AJAX) ====================
    if (isset($_POST['bayar_sekarang'])) {
        // Validasi
        if (empty($nama) || empty($telepon) || empty($alamat)) {
            $error_msg = '⚠️ Mohon lengkapi Nama, Nomor WhatsApp, dan Alamat Pengiriman.';
            if ($is_ajax) {
                jsonResponse('error', $error_msg);
            } else {
                $pesan = $error_msg;
                $pesan_class = 'error';
            }
        } elseif ($pengiriman === 'foodsave' && empty($kode_pos_pembeli)) {
            $error_msg = '⚠️ Kode pos pembeli belum terdaftar. Silakan lengkapi profil Anda.';
            if ($is_ajax) {
                jsonResponse('error', $error_msg);
            } else {
                $pesan = $error_msg;
                $pesan_class = 'error';
            }
        } else {
            // Generate batch ID
            $batch_id = generateBatchId($user_id);

            // Mulai transaction
            $pdo->beginTransaction();

            try {
                // Simpan transaksi untuk SETIAP item
                foreach ($cart_items as $index => $item) {
                    $produk_id = (int)$item['produk_id'];
                    $penjual_id = (int)$item['penjual_id'];
                    $qty = (int)$item['qty'];
                    $harga = (float)$item['harga_satuan'];

                    // Distribusikan ongkir & biaya layanan hanya ke item pertama
                    $ongkir_item = ($index === 0) ? $ongkir : 0;
                    $layanan_item = ($index === 0) ? $biaya_layanan : 0;
                    $diskon_item = ($index === 0) ? $diskon : 0;
                    $total_item = ($harga * $qty) + $ongkir_item + $layanan_item - $diskon_item;

                    $stmt = $pdo->prepare("
                        INSERT INTO transaksi 
                        (user_id, penjual_id, produk_id, jumlah, total_harga, status, alamat_pengiriman, no_telepon, metode_pembayaran, ongkir, diskon, kode_voucher, checkout_batch_id, shipping_status, shipping_method) 
                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'diproses', ?)
                    ");

                    $stmt->execute([
                        $user_id,
                        $penjual_id,
                        $produk_id,
                        $qty,
                        $total_item,
                        $alamat,
                        $telepon,
                        $pembayaran,
                        $ongkir_item,
                        $diskon_item,
                        $kode_voucher,
                        $batch_id,
                        $pengiriman
                    ]);

                    // Kurangi stok produk
                    $stmt_update = $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                    $stmt_update->execute([$qty, $produk_id]);
                }

                // Kosongkan keranjang
                $stmt_clear = $pdo->prepare("DELETE FROM keranjang WHERE user_id = ?");
                $stmt_clear->execute([$user_id]);

                // Commit transaction
                $pdo->commit();

                // Response berdasarkan AJAX atau form biasa
                $redirect_url = "payment_summary.php?batch_id=$batch_id&total=$total_bayar&pembayaran=" . urlencode($pembayaran) . "&pengiriman=" . urlencode($pengiriman);

                if ($is_ajax) {
                    jsonResponse('success', '✅ Pesanan berhasil dibuat! Mengalihkan ke halaman pembayaran...', [
                        'redirect_url' => $redirect_url,
                        'batch_id' => $batch_id
                    ]);
                } else {
                    header("Location: $redirect_url");
                    exit;
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = '❌ Terjadi kesalahan saat memproses pesanan: ' . $e->getMessage();
                error_log("Checkout error: " . $e->getMessage());

                if ($is_ajax) {
                    jsonResponse('error', $error_msg);
                } else {
                    $pesan = $error_msg;
                    $pesan_class = 'error';
                }
            }
        }
    }
}

// Pastikan total_bayar selalu dihitung dengan benar
$ongkir = ($pengiriman === 'pickup') ? 0 : $ongkir_konsolidasi;
$total_bayar = max(0, $subtotal + $biaya_layanan + $ongkir - $diskon);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .input-focus {
            @apply focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition;
        }

        .radio-card {
            @apply p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-500 transition flex items-center gap-3;
        }

        .radio-card.selected {
            @apply border-green-500 bg-green-50/30;
        }

        .radio-card input {
            @apply w-4 h-4 text-green-600;
        }

        @keyframes highlight-section {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.2);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }

        .section-highlight {
            animation: highlight-section 1s ease-out;
        }

        /* Loading spinner */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            animation: spin 1s linear infinite;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white shadow-sm sticky top-0 z-50 border-b">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-green-600 flex items-center gap-2">🌿 FoodSave</a>
            <a href="keranjang.php" class="text-sm text-gray-600 hover:text-green-600 font-medium">← Kembali ke Keranjang</a>
        </div>
    </nav>

    <!-- MAIN -->
    <main class="max-w-5xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Checkout Pesanan</h1>
            <p class="text-gray-500 mt-2">Lengkapi data dan selesaikan pembayaran pesanan makanan surplus Anda</p>
        </div>

        <!-- Alert Message (untuk fallback non-AJAX) -->
        <?php if ($pesan): ?>
            <div id="fallbackMessage" class="mb-6 p-4 rounded-xl border-2 <?= $pesan_class === 'success'
                                                                                ? 'bg-green-50 border-green-200 text-green-700'
                                                                                : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <!-- AJAX Alert Message -->
        <div id="ajaxMessage" class="hidden mb-6 p-4 rounded-xl border-2"></div>

        <!-- Checkout Grid -->
        <div class="grid lg:grid-cols-3 gap-6">

            <!-- FORM KIRI (2/3) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Ringkasan Produk -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">📦</span>
                        Produk yang Dipesan
                    </h2>

                    <div class="space-y-3">
                        <?php foreach ($cart_items as $item):
                            $gambar_url = !empty($item['gambar_url']) && file_exists($item['gambar_url'])
                                ? $item['gambar_url']
                                : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"%3E%3Crect fill="%23f3f4f6" width="80" height="80"/%3E%3Ctext fill="%239ca3af" font-family="sans-serif" font-size="14" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3E📦%3C/text%3E%3C/svg%3E';
                        ?>
                            <div class="flex gap-4 p-3 bg-gray-50 rounded-lg items-center">
                                <img src="<?= htmlspecialchars($gambar_url) ?>"
                                    alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\' viewBox=\'0 0 80 80\'%3E%3Crect fill=\'%23f3f4f6\' width=\'80\' height=\'80\'/%3E%3Ctext fill=\'%239ca3af\' font-family=\'sans-serif\' font-size=\'14\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\'%3E📦%3C/text%3E%3C/svg%3E'"
                                    class="w-16 h-16 rounded-lg object-cover bg-white">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($item['nama_toko']) ?> • <?= htmlspecialchars($item['kota']) ?></p>
                                    <p class="text-xs text-gray-400 mt-0.5">Qty: <?= $item['qty'] ?> <?= htmlspecialchars($item['satuan']) ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="font-bold text-green-600 text-sm">Rp <?= number_format($item['harga_satuan'] * $item['qty'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Form Checkout -->
                <form method="POST" class="space-y-6" id="checkoutForm">
                    <input type="hidden" name="cart_items" value='<?= htmlspecialchars(json_encode($cart_items), ENT_QUOTES) ?>'>
                    <input type="hidden" name="subtotal" value="<?= $subtotal ?>">

                    <!-- Data Penerima -->
                    <div id="section-data" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">👤</span>
                            Data Penerima
                        </h2>

                        <!-- Pilih Alamat -->
                        <?php if (!empty($user_addresses)): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Alamat Pengiriman</label>
                                <select id="selectAlamat" onchange="pilihAlamat(this.value)"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none bg-white">
                                    <option value="">-- Pilih Alamat --</option>
                                    <?php foreach ($user_addresses as $addr): ?>
                                        <option value="<?= htmlspecialchars(json_encode($addr)) ?>" <?= ($default_address && $addr['id'] === $default_address['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($addr['nama_penerima']) ?> - <?= htmlspecialchars($addr['kelurahan'] ?? '') ?>, <?= htmlspecialchars($addr['kecamatan'] ?? '') ?>
                                            <?= $addr['is_default'] ? ' ⭐' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="new">+ Tambah Alamat Baru</option>
                                </select>
                                <a href="alamat_saya.php" class="text-sm text-green-600 hover:underline mt-2 inline-block">Kelola Alamat</a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="text-sm text-amber-800 mb-2">⚠️ Anda belum memiliki alamat pengiriman.</p>
                                <a href="alamat_saya.php" class="text-sm text-green-600 hover:underline font-medium">+ Tambah Alamat</a>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Penerima *</label>
                                <input type="text" name="nama" value="<?= htmlspecialchars($nama) ?>" required
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none transition"
                                    placeholder="Nama Penerima">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WhatsApp *</label>
                                <input type="tel" name="telepon" value="<?= htmlspecialchars($telepon) ?>" required pattern="08[0-9]{8,}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none transition"
                                    placeholder="08xxxxxxxxxx">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Pengiriman Lengkap *</label>
                                <textarea name="alamat" rows="2" required
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none resize-none transition"
                                    placeholder="Jl. Contoh No. 123, RT/RW, Kelurahan, Kecamatan"><?= htmlspecialchars($alamat) ?></textarea>
                                <?php if (!empty($kode_pos_pembeli)): ?>
                                    <p class="text-xs text-gray-400 mt-1">📮 Kode Pos: <strong><?= htmlspecialchars($kode_pos_pembeli) ?></strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Metode Pengiriman -->
                    <div id="section-shipping" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">🚚</span>
                            Metode Pengiriman
                        </h2>

                        <div class="space-y-4">
                            <!-- FoodSave Delivery -->
                            <label class="relative block p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 shipping-option
                      <?= $pengiriman === 'foodsave'
                            ? 'border-green-500 bg-green-50/40 shadow-md shadow-green-500/10'
                            : 'border-gray-200 bg-white hover:border-green-300 hover:shadow-sm' ?>">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <input type="radio" name="pengiriman" value="foodsave"
                                            class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500"
                                            <?= $pengiriman === 'foodsave' ? 'checked' : '' ?>>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-semibold text-gray-900">FoodSave Delivery</span>
                                        </div>
                                        <p class="text-sm text-gray-500 mb-2">Pengiriman teroptimasi via hub • Rute terdekat dari semua penjual</p>

                                        <?php if (isset($hasil_konsolidasi) && $pengiriman === 'foodsave'): ?>
                                            <div class="text-xs text-gray-400 space-y-0.5 mb-2">
                                                <p>📍 Total jarak: <strong class="text-gray-600"><?= $hasil_konsolidasi['total_jarak'] ?> km</strong></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (empty($kode_pos_pembeli)): ?>
                                            <p class="text-xs text-amber-600 bg-amber-50 inline-block px-2 py-1 rounded mt-1">
                                                ⚠️ Lengkapi kode pos di profil untuk hitung ongkir
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex-shrink-0 text-right">
                                        <span class="block text-lg font-bold text-green-600">
                                            <?= empty($kode_pos_pembeli) ? '-' : 'Rp ' . number_format($ongkir_konsolidasi, 0, ',', '.') ?>
                                        </span>
                                        <?php if (!empty($kode_pos_pembeli) && $ongkir_konsolidasi > 0): ?>
                                            <span class="text-xs text-gray-400">ongkir</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>

                            <!-- Pick Up -->
                            <label class="relative block p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 shipping-option
                      <?= $pengiriman === 'pickup'
                            ? 'border-blue-500 bg-blue-50/40 shadow-md shadow-blue-500/10'
                            : 'border-gray-200 bg-white hover:border-blue-300 hover:shadow-sm' ?>">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 mt-1">
                                        <input type="radio" name="pengiriman" value="pickup"
                                            class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500"
                                            <?= $pengiriman === 'pickup' ? 'checked' : '' ?>>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-semibold text-gray-900">Ambil Sendiri (Pick Up)</span>
                                        </div>
                                        <p class="text-sm text-gray-500 mb-2">Ambil langsung ke toko penjual • Gratis ongkir</p>
                                        <p class="text-xs text-gray-400 bg-gray-50 inline-block px-2 py-1 rounded">
                                            📍 Anda akan menerima lokasi masing-masing penjual setelah pembayaran
                                        </p>
                                    </div>

                                    <div class="flex-shrink-0 text-right">
                                        <span class="block text-lg font-bold text-green-600">Gratis</span>
                                        <span class="text-xs text-gray-400">ongkir</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Metode Pembayaran -->
                    <div id="section-payment" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">💳</span>
                            Metode Pembayaran
                        </h2>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="radio-card flex-col items-center text-center payment-option <?= $pembayaran === 'Transfer Bank' ? 'selected' : '' ?>">
                                <input type="radio" name="pembayaran" value="Transfer Bank" class="sr-only"
                                    <?= $pembayaran === 'Transfer Bank' ? 'checked' : '' ?>>
                                <div class="text-3xl mb-2">🏦</div>
                                <span class="font-semibold text-gray-900 text-sm">Transfer Bank</span>
                                <span class="text-xs text-gray-500 mt-1">BCA, Mandiri, BRI, BNI</span>
                            </label>

                            <label class="radio-card flex-col items-center text-center payment-option <?= $pembayaran === 'QRIS' ? 'selected' : '' ?>">
                                <input type="radio" name="pembayaran" value="QRIS" class="sr-only"
                                    <?= $pembayaran === 'QRIS' ? 'checked' : '' ?>>
                                <div class="text-3xl mb-2">📱</div>
                                <span class="font-semibold text-gray-900 text-sm">QRIS</span>
                                <span class="text-xs text-gray-500 mt-1">Scan & bayar dengan e-wallet</span>
                            </label>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800">
                            <p>💡 <strong>Catatan:</strong> Setelah checkout, Anda akan diarahkan ke halaman pembayaran dengan instruksi lengkap sesuai metode yang dipilih.</p>
                        </div>
                    </div>

                    <!-- Voucher -->
                    <div id="section-voucher" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-600">🎁</span>
                            Kode Voucher
                        </h2>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4 text-xs">
                            <p class="text-yellow-800">
                                <strong>Kode Voucher Tersedia:</strong><br>
                                <code class="bg-white px-1.5 py-0.5 rounded text-[11px] font-mono font-bold">FOODSAVE10</code> = Diskon 10% (Maks Rp 10.000)<br>
                                <code class="bg-white px-1.5 py-0.5 rounded text-[11px] font-mono font-bold">FOODSAVE20</code> = Diskon 20% (Maks Rp 20.000)
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <input type="text" name="voucher" id="voucherInput" value="<?= htmlspecialchars($kode_voucher) ?>" placeholder="Masukkan kode voucher"
                                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none uppercase text-sm font-mono"
                                maxlength="20">
                            <button type="button" id="btnApplyVoucher"
                                class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition text-sm cursor-pointer">
                                Terapkan
                            </button>
                        </div>

                        <div id="voucherStatus">
                            <?php if (!empty($kode_voucher) && $diskon > 0): ?>
                                <p class="text-xs text-green-600 mt-2 flex items-center gap-1">
                                    ✅ Voucher <strong><?= htmlspecialchars($kode_voucher) ?></strong> aktif: Potongan Rp <?= number_format($diskon, 0, ',', '.') ?>
                                </p>
                            <?php elseif (!empty($_POST['voucher']) && empty($kode_voucher)): ?>
                                <p class="text-xs text-red-500 mt-2">❌ Kode voucher tidak valid atau sudah kadaluarsa</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="bayar_sekarang" id="btnBayar"
                        class="w-full py-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition shadow-lg hover:shadow-xl text-lg cursor-pointer flex items-center justify-center gap-2">
                        <span>🔒</span>
                        <span id="btnBayarText">Bayar Sekarang • Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </button>

                    <p class="text-xs text-gray-400 text-center">
                        Dengan melanjutkan, Anda menyetujui <a href="#" class="text-green-600 hover:underline">Syarat & Ketentuan</a> FoodSave
                    </p>
                </form>
            </div>

            <!-- SUMMARY KANAN (1/3) - Sticky -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Pembayaran</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal Belanja</span>
                            <span class="font-medium">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Biaya Layanan</span>
                            <span class="font-medium">Rp <?= number_format($biaya_layanan, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Pengiriman</span>
                            <span class="font-medium" id="displayOngkir">
                                <?= $pengiriman === 'pickup' ? 'Gratis (Pick Up)' : 'Rp ' . number_format($ongkir, 0, ',', '.') ?>
                                <span class="text-xs text-gray-400 block">(<?= $pengiriman === 'pickup' ? 'Ambil Sendiri' : 'FoodSave Delivery' ?>)</span>
                            </span>
                        </div>
                        <div class="flex justify-between text-green-600 font-semibold" id="displayDiskonRow" style="<?= $diskon > 0 ? '' : 'display:none;' ?>">
                            <span>Potongan Voucher</span>
                            <span id="displayDiskon">- Rp <?= number_format($diskon, 0, ',', '.') ?></span>
                        </div>
                    </div>

                    <div class="flex justify-between text-lg font-bold text-gray-900 pt-4 mt-4 border-t-2 border-green-600">
                        <span>Total Bayar</span>
                        <span id="displayTotal">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>

                    <div class="mt-6 pt-4 border-t">
                        <div class="flex justify-between text-xs">
                            <span class="text-green-600 font-medium flex items-center gap-1">✓ Keranjang</span>
                            <span class="text-green-600 font-medium flex items-center gap-1">✓ Detail</span>
                            <span class="text-gray-400 flex items-center gap-1">○ Pembayaran</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t space-y-2">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="text-green-600">🔐</span> Pembayaran Aman & Terenkripsi
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="text-green-600">♻️</span> Mendukung Pengurangan Food Waste
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="text-green-600">💬</span> Bantuan 24/7 via WhatsApp
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

    <!-- jQuery untuk AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Load JavaScript dari file terpisah -->
    <script src="assets/js/checkout_cart.js"></script>

    <!-- AJAX Script untuk Checkout -->
    <script>
        $(document).ready(function() {
            // ==================== VARIABEL GLOBAL ====================
            const subtotal = <?= $subtotal ?>;
            const biayaLayanan = <?= $biaya_layanan ?>;
            let ongkir = <?= $ongkir_konsolidasi ?>;
            let diskon = <?= $diskon ?>;
            const ongkirPickup = 0;

            // ==================== HELPER FUNCTIONS ====================
            function formatRupiah(angka) {
                return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            function updateTotalDisplay() {
                const total = subtotal + biayaLayanan + ongkir - diskon;
                $('#displayTotal').text(formatRupiah(Math.max(0, total)));
                $('#btnBayarText').html('Bayar Sekarang • ' + formatRupiah(Math.max(0, total)));
            }

            function showMessage(type, message) {
                const alertClass = type === 'success' ?
                    'bg-green-50 border-green-200 text-green-700' :
                    'bg-red-50 border-red-200 text-red-700';

                $('#ajaxMessage')
                    .removeClass('hidden bg-green-50 border-green-200 text-green-700 bg-red-50 border-red-200 text-red-700')
                    .addClass(alertClass)
                    .text(message)
                    .show();

                // Scroll ke atas
                $('html, body').animate({
                    scrollTop: $('#ajaxMessage').offset().top - 100
                }, 500);

                // Auto-hide setelah 5 detik
                setTimeout(() => {
                    $('#ajaxMessage').fadeOut();
                }, 5000);
            }

            function setLoading(isLoading) {
                const btn = $('#btnBayar');
                if (isLoading) {
                    btn.prop('disabled', true)
                        .removeClass('bg-green-600 hover:bg-green-700')
                        .addClass('bg-gray-400 cursor-not-allowed');
                    $('#btnBayarText').html(`
                    <svg class="spinner w-5 h-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memproses pesanan...
                `);
                } else {
                    btn.prop('disabled', false)
                        .removeClass('bg-gray-400 cursor-not-allowed')
                        .addClass('bg-green-600 hover:bg-green-700');
                    updateTotalDisplay();
                }
            }

            // ==================== HANDLE FORM SUBMIT (AJAX) ====================
            $('#checkoutForm').on('submit', function(e) {
                e.preventDefault();

                // Validasi client-side
                const nama = $('input[name="nama"]').val().trim();
                const telepon = $('input[name="telepon"]').val().trim();
                const alamat = $('textarea[name="alamat"]').val().trim();

                if (!nama || !telepon || !alamat) {
                    showMessage('error', '⚠️ Mohon lengkapi Nama, Nomor WhatsApp, dan Alamat Pengiriman.');
                    return;
                }

                setLoading(true);
                $('#ajaxMessage').hide();

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: $(this).serialize() + '&bayar_sekarang=1',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            showMessage('success', response.message);

                            // Redirect ke halaman pembayaran
                            setTimeout(function() {
                                window.location.href = response.redirect_url;
                            }, 1500);
                        } else {
                            showMessage('error', response.message);
                            setLoading(false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Response:', xhr.responseText);

                        let errorMsg = '❌ Terjadi kesalahan sistem.';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) errorMsg = response.message;
                        } catch (e) {}

                        showMessage('error', errorMsg);
                        setLoading(false);
                    }
                });
            });

            // ==================== HANDLE APPLY VOUCHER (AJAX) ====================
            $('#btnApplyVoucher').on('click', function() {
                const btn = $(this);
                const voucherCode = $('#voucherInput').val().trim();

                if (!voucherCode) {
                    showMessage('error', 'Masukkan kode voucher terlebih dahulu!');
                    return;
                }

                const originalText = btn.text();
                btn.prop('disabled', true).html(`
                <svg class="spinner w-4 h-4 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memproses...
            `);

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        apply_voucher: '1',
                        voucher: voucherCode,
                        cart_items: $('input[name="cart_items"]').val(),
                        subtotal: $('input[name="subtotal"]').val(),
                        pengiriman: $('input[name="pengiriman"]:checked').val()
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            // Update diskon
                            diskon = response.diskon;

                            // Update tampilan
                            $('#displayDiskon').text('- ' + response.formatted_diskon);
                            $('#displayDiskonRow').show();
                            $('#displayTotal').text(response.formatted_total);

                            // Update status voucher
                            $('#voucherStatus').html(`
                            <p class="text-xs text-green-600 mt-2 flex items-center gap-1">
                                ✅ Voucher <strong>${response.kode_voucher}</strong> aktif: Potongan ${response.formatted_diskon}
                            </p>
                        `);

                            // Update tombol bayar
                            updateTotalDisplay();

                            showMessage('success', response.message);
                        } else {
                            showMessage('error', response.message);
                            $('#voucherStatus').html(`
                            <p class="text-xs text-red-500 mt-2">❌ ${response.message}</p>
                        `);
                        }

                        btn.prop('disabled', false).text(originalText);
                    },
                    error: function() {
                        showMessage('error', '❌ Terjadi kesalahan. Silakan coba lagi.');
                        btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // ==================== HANDLE PENGIRIMAN CHANGE ====================
            $('input[name="pengiriman"]').on('change', function() {
                const value = $(this).val();

                // Update style
                $('.shipping-option').removeClass('border-green-500 bg-green-50/40 shadow-md shadow-green-500/10 border-blue-500 bg-blue-50/40 shadow-md shadow-blue-500/10')
                    .addClass('border-gray-200 bg-white');

                if (value === 'foodsave') {
                    $(this).closest('.shipping-option')
                        .removeClass('border-gray-200 bg-white')
                        .addClass('border-green-500 bg-green-50/40 shadow-md shadow-green-500/10');
                    ongkir = <?= $ongkir_konsolidasi ?>;
                    $('#displayOngkir').html('Rp <?= number_format($ongkir_konsolidasi, 0, ',', '.') ?> <span class="text-xs text-gray-400 block">(FoodSave Delivery)</span>');
                } else if (value === 'pickup') {
                    $(this).closest('.shipping-option')
                        .removeClass('border-gray-200 bg-white')
                        .addClass('border-blue-500 bg-blue-50/40 shadow-md shadow-blue-500/10');
                    ongkir = ongkirPickup;
                    $('#displayOngkir').html('Gratis (Pick Up) <span class="text-xs text-gray-400 block">(Ambil Sendiri)</span>');
                }

                updateTotalDisplay();
            });

            // ==================== HANDLE PEMBAYARAN CHANGE ====================
            $('input[name="pembayaran"]').on('change', function() {
                $('.payment-option').removeClass('selected');
                $(this).closest('.payment-option').addClass('selected');
            });
        });
        // Fungsi pilih alamat dari dropdown
        function pilihAlamat(jsonData) {
            if (jsonData === 'new') {
                window.location.href = 'alamat_saya.php';
                return;
            }

            if (!jsonData) return;

            const alamat = JSON.parse(jsonData);

            // Fill form dengan data alamat
            document.querySelector('input[name="nama"]').value = alamat.nama_penerima;
            document.querySelector('input[name="telepon"]').value = alamat.telepon;
            document.querySelector('textarea[name="alamat"]').value = alamat.alamat_lengkap;

            // Update kode pos (jika ada elemennya)
            const kodePosElement = document.querySelector('p.text-xs.text-gray-400');
            if (kodePosElement && alamat.kode_pos) {
                kodePosElement.innerHTML = `📮 Kode Pos: <strong>${alamat.kode_pos}</strong>`;
            }
        }
    </script>

</body>

</html>