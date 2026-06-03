<?php
// keranjang.php
session_start();
require_once 'config/database.php';
require_once 'includes/session_check.php';

// Security check
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: LoginPage.php?msg=login_required&redirect=keranjang.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle update qty / hapus item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ HANDLE +/- BUTTONS & INPUT CHANGE
    if (isset($_POST['update_qty']) && isset($_POST['produk_id'])) {
        $produk_id = (int)$_POST['produk_id'];
        $current_qty = (int)$_POST['current_qty'];
        $action = $_POST['update_qty']; // 'plus', 'minus', atau 'set'

        // Ambil stok produk
        $stmt = $conn->prepare("SELECT stok FROM produk WHERE id = ? AND status = 'aktif'");
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();

        if ($produk) {
            $stok = $produk['stok'];
            $new_qty = $current_qty;

            // Hitung qty baru berdasarkan aksi
            if ($action === 'plus') {
                $new_qty = min($current_qty + 1, $stok);
            } elseif ($action === 'minus') {
                $new_qty = max($current_qty - 1, 1);
            } elseif ($action === 'set' && isset($_POST['qty_input'])) {
                $new_qty = max(1, min((int)$_POST['qty_input'], $stok));
            }

            // Update database jika qty berubah
            if ($new_qty !== $current_qty && $new_qty > 0) {
                $stmt = $conn->prepare("UPDATE keranjang SET qty = ? WHERE user_id = ? AND produk_id = ?");
                $stmt->bind_param("iii", $new_qty, $user_id, $produk_id);
                $stmt->execute();
            }
        }
        header("Location: keranjang.php");
        exit;
    }

    // Handle hapus item
    if (isset($_POST['hapus'])) {
        $produk_id = (int)$_POST['produk_id'];
        $stmt = $conn->prepare("DELETE FROM keranjang WHERE user_id = ? AND produk_id = ?");
        $stmt->bind_param("ii", $user_id, $produk_id);
        $stmt->execute();
        header("Location: keranjang.php");
        exit;
    }
}

// Ambil item keranjang + info produk & penjual
$query = "SELECT k.*, 
                 p.nama_produk, p.harga_asli, p.harga_diskon, p.satuan, p.gambar_url, p.stok,
                 pj.nama_toko, pj.kota, pj.kode_pos as penjual_kode_pos, pj.id as penjual_id
          FROM keranjang k
          JOIN produk p ON k.produk_id = p.id
          JOIN penjual pj ON p.penjual_id = pj.id
          WHERE k.user_id = ? AND p.status = 'aktif'
          ORDER BY pj.nama_toko, p.nama_produk";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

// Hitung subtotal
$subtotal = 0;
$cart_array = [];
while ($item = $cart_items->fetch_assoc()) {
    $harga = !empty($item['harga_diskon']) ? $item['harga_diskon'] : $item['harga_asli'];
    $item['harga_satuan'] = $harga;
    $item['subtotal'] = $harga * $item['qty'];
    $subtotal += $item['subtotal'];
    $cart_array[] = $item;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - FoodSave</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        /* Smooth transition untuk qty buttons */
        .qty-btn {
            transition: all 0.15s ease;
        }

        .qty-btn:active {
            transform: scale(0.95);
        }

        .qty-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Animasi untuk feedback visual */
        @keyframes pulse-green {

            0%,
            100% {
                background-color: #22c55e;
            }

            50% {
                background-color: #16a34a;
            }
        }

        .qty-updated {
            animation: pulse-green 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50 border-b">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-green-600 flex items-center gap-2">🌿 FoodSave</a>
            <div class="flex items-center gap-4">
                <a href="PromosiPage.php" class="text-sm text-gray-600 hover:text-green-600 font-medium transition">← Lanjut Belanja</a>
                <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($_SESSION['nama']) ?></span>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-gray-900">🛒 Keranjang Belanja</h1>

        <?php if (empty($cart_array)): ?>
            <div class="bg-white rounded-2xl p-10 text-center shadow-sm border border-gray-100">
                <div class="text-7xl mb-4">🛒</div>
                <p class="text-gray-500 text-lg mb-6">Keranjangmu masih kosong</p>
                <a href="PromosiPage.php" class="inline-block px-8 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition shadow-md">
                    Mulai Belanja →
                </a>
            </div>
        <?php else: ?>
            <div class="grid lg:grid-cols-3 gap-6">

                <!-- List Item Keranjang -->
                <div class="lg:col-span-2 space-y-4">
                    <?php foreach ($cart_array as $item):
                        $qty = $item['qty'];
                        $stok = $item['stok'];
                        $can_decrease = $qty > 1;
                        $can_increase = $qty < $stok;
                    ?>
                        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex gap-4 hover:shadow-md transition">
                            <!-- Gambar -->
                            <img src="<?= htmlspecialchars($item['gambar_url'] ?: 'https://via.placeholder.com/100') ?>"
                                alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                                class="w-24 h-24 rounded-lg object-cover bg-gray-100 flex-shrink-0">

                            <!-- Info Produk -->
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($item['nama_toko']) ?> • <?= htmlspecialchars($item['kota']) ?></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="font-bold text-green-600">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></span>
                                    <?php if ($item['harga_diskon'] && $item['harga_diskon'] < $item['harga_asli']): ?>
                                        <span class="text-sm text-gray-400 line-through">Rp <?= number_format($item['harga_asli'], 0, ',', '.') ?></span>
                                        <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-medium">
                                            -<?= round((1 - $item['harga_diskon'] / $item['harga_asli']) * 100) ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Stok tersedia: <?= $stok ?> <?= htmlspecialchars($item['satuan']) ?></p>

                                <!-- Qty Controls dengan +/- Buttons -->
                                <form method="POST" class="flex items-center gap-3 mt-3">
                                    <input type="hidden" name="produk_id" value="<?= $item['produk_id'] ?>">
                                    <input type="hidden" name="current_qty" value="<?= $qty ?>">

                                    <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
                                        <!-- Tombol MINUS -->
                                        <button type="submit" name="update_qty" value="minus"
                                            class="qty-btn px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-green-600 font-bold text-lg <?= !$can_decrease ? 'opacity-40 cursor-not-allowed' : '' ?>"
                                            <?= !$can_decrease ? 'disabled' : '' ?>
                                            title="Kurangi">
                                            −
                                        </button>

                                        <!-- Input Number (bisa diketik langsung) -->
                                        <input type="number" name="qty_input" value="<?= $qty ?>" min="1" max="<?= $stok ?>"
                                            class="w-14 text-center border-0 bg-transparent focus:ring-0 text-sm font-semibold text-gray-900 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            onchange="this.form.querySelector('[name=update_qty]').value='set'; this.form.submit()">

                                        <!-- Tombol PLUS -->
                                        <button type="submit" name="update_qty" value="plus"
                                            class="qty-btn px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-green-600 font-bold text-lg <?= !$can_increase ? 'opacity-40 cursor-not-allowed' : '' ?>"
                                            <?= !$can_increase ? 'disabled' : '' ?>
                                            title="Tambah">
                                            +
                                        </button>
                                    </div>

                                    <span class="text-sm text-gray-500 hidden sm:inline">=</span>
                                    <span class="font-bold text-gray-900">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                                </form>
                            </div>

                            <!-- Hapus Button -->
                            <form method="POST" onsubmit="return confirm('Hapus <?= htmlspecialchars($item['nama_produk']) ?> dari keranjang?')" class="self-start">
                                <input type="hidden" name="produk_id" value="<?= $item['produk_id'] ?>">
                                <button type="submit" name="hapus" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition" title="Hapus item">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Ringkasan & Checkout -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 sticky top-24">
                        <h3 class="font-semibold text-lg mb-4 text-gray-900">Ringkasan Pesanan</h3>

                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Subtotal (<?= array_sum(array_column($cart_array, 'qty')) ?> item)</span>
                                <span class="font-semibold text-gray-900">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Ongkos Kirim</span>
                                <span class="text-green-600 font-medium">Hitung di checkout</span>
                            </div>
                            <?php if ($subtotal > 100000): ?>
                                <div class="flex justify-between text-green-600 bg-green-50 px-3 py-2 rounded-lg">
                                    <span>🎉 Gratis Ongkir!</span>
                                    <span class="font-medium">Berlaku untuk order > Rp 100.000</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="border-t border-gray-100 pt-4 mt-4">
                            <div class="flex justify-between text-lg font-bold text-gray-900">
                                <span>Total Sementara</span>
                                <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <!-- Tombol Checkout -->
                        <form action="checkout.php" method="POST" class="mt-6">
                            <input type="hidden" name="cart_items" value='<?= htmlspecialchars(json_encode($cart_array), ENT_QUOTES) ?>'>
                            <input type="hidden" name="subtotal" value="<?= $subtotal ?>">

                            <button type="submit"
                                class="w-full py-3.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition shadow-md hover:shadow-lg flex items-center justify-center gap-2">
                                <i class="fa-solid fa-lock"></i>
                                Checkout Sekarang
                            </button>
                        </form>

                        <p class="text-xs text-gray-400 mt-4 text-center">
                            🔒 Pembayaran aman • Garansi uang kembali
                        </p>

                        <!-- Trust Badges -->
                        <div class="flex justify-center gap-4 mt-6 pt-4 border-t border-gray-100">
                            <div class="text-center">
                                <div class="text-xl">🚚</div>
                                <div class="text-xs text-gray-500">Pengiriman Cepat</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xl">🔐</div>
                                <div class="text-xs text-gray-500">Transaksi Aman</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xl">♻️</div>
                                <div class="text-xs text-gray-500">Ramah Lingkungan</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <!-- Optional: JavaScript untuk UX lebih smooth (tanpa reload) -->
    <script>
        // Jika ingin update tanpa reload halaman, bisa pakai AJAX seperti ini:
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            const qtyInput = form.querySelector('input[name="qty_input"]');
            const minusBtn = form.querySelector('button[value="minus"]');
            const plusBtn = form.querySelector('button[value="plus"]');

            // Update button states based on qty
            function updateButtonStates() {
                const qty = parseInt(qtyInput?.value) || 1;
                const max = parseInt(qtyInput?.max) || 99;
                if (minusBtn) minusBtn.disabled = qty <= 1;
                if (plusBtn) plusBtn.disabled = qty >= max;
            }

            if (qtyInput) {
                qtyInput.addEventListener('input', updateButtonStates);
                updateButtonStates();
            }

            // Visual feedback saat klik button
            [minusBtn, plusBtn].forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', function() {
                        this.classList.add('qty-updated');
                        setTimeout(() => this.classList.remove('qty-updated'), 300);
                    });
                }
            });
        });
    </script>

    <!-- Font Awesome untuk icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>

</html>