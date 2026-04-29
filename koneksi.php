<?php
$conn = mysqli_connect("localhost", "root", "", "login",);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
} else {
    echo "Koneksi berhasil";
}
?>