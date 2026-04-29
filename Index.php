<?php

$site_name   = "Food Save";
$tagline     = "Reduce Waste. Feed More. Sustain Better.";
$description = "Dengan teknologi dan ekonomi sirkular, sisa makanan dimanfaatkan kembali menjadi nilai ekonomi yang berkelanjutan.";

// kalo udah ada database bisa diubah
$stats = [
    "67 ton makanan sudah diselamatkan",
    "1000+ pengguna aktif",
    "500+ mitra",
];

$langkah = [
    "Temukan Produk",
    "Pilih Produk",
    "Bayar",
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="LandingPageStyle2.css">
</head>
<body>

    <!-- NAVBAR / LOGIN -->
    <a href="RegisterPage.php">Login</a>

    <!-- HERO -->
    <h1><?= htmlspecialchars($tagline) ?></h1>
    <p><?= htmlspecialchars($description) ?></p>
    <a href="PromosiPage.php">Lihat Produk</a>
    <a href="RegisterPage.php">Beli</a>

    <!-- KATEGORI -->
    <section>
        <div>
            <h3>Cari Kategori</h3>
            <a href="PromosiPage.php">Lihat Semua</a>
        </div>
    </section>

    <!-- CARA KERJA -->
    <section>
        <h2>Mudah Hanya 3 Langkah</h2>
        <p>Food Save dirancang agar ramah untuk semua kalangan</p>
        <ol>
            <?php foreach ($langkah as $item): ?>
            <li><?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <!-- STATISTIK -->
    <section>
        <?php foreach ($stats as $stat): ?>
        <h4><?= htmlspecialchars($stat) ?></h4>
        <?php endforeach; ?>
    </section>

    <!-- FOOTER -->
    <section>
        <p>© <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.</p>
    </section>

</body>
</html>
