<?php
// koneksi.php
$host = "localhost";
$dbname = "foodsave"; // Ganti dengan nama DBmu
$username = "root";             // Ganti dengan username DBmu (biasanya root)
$password = "";                 // Ganti dengan password DBmu (biasanya kosong di XAMPP)

try {
    // Membuat koneksi menggunakan PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode ke exception agar mudah tracking error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>