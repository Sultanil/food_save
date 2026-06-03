<?php
include 'includes/session_check.php';
$host = "localhost";
$user = "root";
$pass = "";
$db   = "food_save";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

?>