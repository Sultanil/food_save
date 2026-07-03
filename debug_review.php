<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("Login dulu!");
}

$user_id = $_SESSION['user_id'];

echo "<h2>🔍 DEBUG REVIEW SYSTEM - User ID: $user_id</h2>";
echo "<hr>";

// 1. Cek semua transaksi user
echo "<h3>1. SEMUA TRANSAKSI USER</h3>";
$stmt = $pdo->prepare("
    SELECT t.id, t.produk_id, t.status, t.jumlah, t.total_harga, 
           t.tanggal_pesanan, p.nama_produk
    FROM transaksi t
    LEFT JOIN produk p ON t.produk_id = p.id
    WHERE t.user_id = ?
    ORDER BY t.id DESC
");
$stmt->execute([$user_id]);
$transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($transaksi)) {
    echo "<p style='color:red'>❌ TIDAK ADA TRANSAKSI!</p>";
} else {
    echo "<table border='1' cellpadding='8' style='width:100%'>";
    echo "<tr><th>ID</th><th>Produk</th><th>Status</th><th>Jumlah</th><th>Total</th><th>Tanggal</th></tr>";
    foreach ($transaksi as $t) {
        $color = $t['status'] === 'selesai' ? 'green' : 'orange';
        echo "<tr>";
        echo "<td>{$t['id']}</td>";
        echo "<td>{$t['nama_produk']} (ID: {$t['produk_id']})</td>";
        echo "<td style='color:$color;font-weight:bold'>{$t['status']}</td>";
        echo "<td>{$t['jumlah']}</td>";
        echo "<td>Rp " . number_format($t['total_harga']) . "</td>";
        echo "<td>{$t['tanggal_pesanan']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// 2. Cek ulasan yang sudah ada
echo "<h3>2. ULASAN YANG SUDAH ADA</h3>";
$stmt = $pdo->prepare("
    SELECT u.id, u.produk_id, u.transaksi_id, u.rating, u.komentar,
           p.nama_produk
    FROM ulasan u
    LEFT JOIN produk p ON u.produk_id = p.id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$ulasan = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($ulasan)) {
    echo "<p>✅ Belum ada ulasan (bagus!)</p>";
} else {
    echo "<p style='color:orange'>Sudah ada " . count($ulasan) . " ulasan:</p>";
    echo "<table border='1' cellpadding='8' style='width:100%'>";
    echo "<tr><th>ID</th><th>Produk</th><th>Transaksi ID</th><th>Rating</th><th>Komentar</th></tr>";
    foreach ($ulasan as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['nama_produk']} (ID: {$u['produk_id']})</td>";
        echo "<td>{$u['transaksi_id']}</td>";
        echo "<td>" . str_repeat('⭐', $u['rating']) . "</td>";
        echo "<td>" . substr($u['komentar'], 0, 50) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// 3. Test query getTransaksiBisaReview
echo "<h3>3. TEST QUERY getTransaksiBisaReview</h3>";
$sql = "SELECT 
            t.id AS transaksi_id,
            t.tanggal_pesanan,
            t.produk_id,
            t.jumlah,
            t.total_harga,
            p.nama_produk AS nama_produk,
            p.gambar_url,
            p.harga_asli,
            pj.nama_toko,
            t.checkout_batch_id,
            t.status
        FROM transaksi t
        JOIN produk p ON t.produk_id = p.id
        JOIN penjual pj ON t.penjual_id = pj.id
        WHERE t.user_id = :user_id
        AND t.status IN ('selesai', 'dibayar', 'completed', 'diantar')
        AND NOT EXISTS (
            SELECT 1 FROM ulasan u 
            WHERE u.user_id = t.user_id 
            AND u.produk_id = t.produk_id
            AND u.transaksi_id = t.id
        )
        ORDER BY t.tanggal_pesanan DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($result)) {
    echo "<p style='color:red;font-weight:bold;font-size:16px'>❌ QUERY TIDAK MENGEMBALIKAN HASIL!</p>";
    echo "<p><strong>Kemungkinan penyebab:</strong></p>";
    echo "<ul>";
    echo "<li>Status transaksi bukan 'selesai', 'dibayar', 'completed', atau 'diantar'</li>";
    echo "<li>Semua produk sudah direview</li>";
    echo "<li>Tidak ada transaksi untuk user ini</li>";
    echo "<li>Ada masalah dengan JOIN tabel</li>";
    echo "</ul>";
} else {
    echo "<p style='color:green;font-weight:bold;font-size:16px'>✅ QUERY BERHASIL! Ada " . count($result) . " produk yang bisa direview:</p>";
    echo "<table border='1' cellpadding='8' style='width:100%'>";
    echo "<tr><th>Transaksi ID</th><th>Produk</th><th>Status</th><th>Jumlah</th></tr>";
    foreach ($result as $r) {
        echo "<tr>";
        echo "<td>{$r['transaksi_id']}</td>";
        echo "<td>{$r['nama_produk']}</td>";
        echo "<td style='color:green'>{$r['status']}</td>";
        echo "<td>{$r['jumlah']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// 4. Tombol update status
echo "<h3>4. UPDATE STATUS TRANSAKSI</h3>";
echo "<form method='POST'>";
echo "<button type='submit' name='update_to_selesai' style='background:#22c55e;color:white;padding:12px 24px;border:none;border-radius:5px;cursor:pointer;font-size:16px;'>";
echo "🔄 Update Semua Transaksi ke Status 'selesai'";
echo "</button>";
echo "</form>";

if (isset($_POST['update_to_selesai'])) {
    $stmt = $pdo->prepare("
        UPDATE transaksi 
        SET status = 'selesai' 
        WHERE user_id = ? AND status IN ('pending', 'dibayar')
    ");
    $stmt->execute([$user_id]);
    $updated = $stmt->rowCount();
    
    echo "<p style='background:#22c55e;color:white;padding:10px;border-radius:5px;margin-top:10px;'>";
    echo "✅ Berhasil update <strong>$updated</strong> transaksi!";
    echo "</p>";
    echo "<meta http-equiv='refresh' content='1'>";
}

echo "<hr>";
echo "<a href='beri_ulasan.php' style='background:#22c55e;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;margin-top:10px;'>";
echo "📝 Coba Buka Halaman Ulasan";
echo "</a>";
?>