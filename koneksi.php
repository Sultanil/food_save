<?php
$conn = mysqli_connect("localhost", "root", "root", "food_save",);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
} else {
    echo "Koneksi berhasil";
}
?>