<?php
// keranjang.php
session_start();
include 'koneksi.php';

// Security check
if (!isset($_SESSION['sudah_login']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: LoginPage.php?msg=login_required&redirect=keranjang.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle update qty / hapus item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'])) {
        $produk_id = (int)$_POST['produk_id'];
        $qty = max(1, (int)$_POST['qty']);
        
        // Cek stok
        $stmt = $conn->prepare("SELECT stok FROM produk WHERE id = ?");
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $stok = $stmt->get_result()->fetch_assoc()['stok'];
        
        if ($qty <= $stok) {
            $stmt = $conn->prepare("UPDATE keranjang SET qty = ? WHERE user_id = ? AND produk_id = ?");
            $stmt->bind_param("iii", $qty, $user_id, $produk_id);
            $stmt->execute();
        }
        header("Location: keranjang.php");
        exit;
    }
    
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
while($item = $cart_items->fetch_assoc()) {
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
    <?php include 'includes/tailwind_config.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="Index.php" class="font-bold text-xl text-brand flex items-center gap-2">🌿 FoodSave</a>
            <div class="flex items-center gap-4">
                <a href="PromosiPage.php" class="text-sm text-gray-600 hover:text-brand">← Lanjut Belanja</a>
                <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['nama']) ?></span>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6">🛒 Keranjang Belanja</h1>

        <?php if (empty($cart_array)): ?>
            <div class="bg-white rounded-xl p-8 text-center shadow-sm">
                <div class="text-6xl mb-4">🛒</div>
                <p class="text-gray-500 mb-4">Keranjangmu masih kosong</p>
                <a href="PromosiPage.php" class="inline-block px-6 py-3 bg-brand text-white rounded-lg hover:bg-brand-dark">
                    Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            <div class="grid lg:grid-cols-3 gap-6">
                
                <!-- List Item Keranjang -->
                <div class="lg:col-span-2 space-y-4">
                    <?php foreach($cart_array as $item): ?>
                    <div class="bg-white rounded-xl p-4 shadow-sm border flex gap-4">
                        <!-- Gambar -->
                        <img src="<?= htmlspecialchars($item['gambar_url'] ?: 'https://via.placeholder.com/100') ?>" 
                             alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                             class="w-24 h-24 rounded-lg object-cover bg-gray-100">
                        
                        <!-- Info Produk -->
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($item['nama_toko']) ?> • <?= htmlspecialchars($item['kota']) ?></p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="font-bold text-brand">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></span>
                                <?php if($item['harga_diskon'] && $item['harga_diskon'] < $item['harga_asli']): ?>
                                    <span class="text-sm text-gray-400 line-through">Rp <?= number_format($item['harga_asli'], 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Qty Controls -->
                            <form method="POST" class="flex items-center gap-2 mt-3">
                                <input type="hidden" name="produk_id" value="<?= $item['produk_id'] ?>">
                                <div class="flex items-center border rounded-lg">
                                    <button type="submit" name="update_qty" value="1" 
                                            class="px-3 py-1 text-gray-600 hover:bg-gray-100" 
                                            style="display:none;">+</button>
                                    <input type="number" name="qty" value="<?= $item['qty'] ?>" min="1" max="<?= $item['stok'] ?>"
                                           class="w-12 text-center border-0 focus:ring-0 text-sm" onchange="this.form.submit()">
                                </div>
                                <span class="text-sm text-gray-500">x</span>
                                <span class="font-medium">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                            </form>
                        </div>
                        
                        <!-- Hapus Button -->
                        <form method="POST" onsubmit="return confirm('Hapus item ini?')">
                            <input type="hidden" name="produk_id" value="<?= $item['produk_id'] ?>">
                            <button type="submit" name="hapus" class="text-red-500 hover:text-red-700 text-sm">🗑️</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Ringkasan & Checkout -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl p-6 shadow-sm border sticky top-24">
                        <h3 class="font-semibold text-lg mb-4">Ringkasan Pesanan</h3>
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Subtotal (<?= count($cart_array) ?> item)</span>
                                <span class="font-medium">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Ongkir</span>
                                <span>Hitung di checkout</span>
                            </div>
                        </div>
                        
                        <div class="border-t pt-4 mt-4">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total Sementara</span>
                                <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                        </div>
                        
                        <!-- Tombol Checkout -->
                        <form action="checkout.php" method="POST" class="mt-6">
                            <input type="hidden" name="cart_items" value='<?= htmlspecialchars(json_encode($cart_array)) ?>'>
                            <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
                            
                            <button type="submit" 
                                    class="w-full py-3 bg-brand hover:bg-brand-dark text-black font-semibold rounded-lg transition cursor-pointer">
                                Checkout Keranjang
                            </button>
                        </form>
                        
                        <p class="text-xs text-gray-500 mt-3 text-center">
                            🔒 Ongkir akan dihitung berdasarkan rute teroptimasi
                        </p>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </main>

</body>
</html>