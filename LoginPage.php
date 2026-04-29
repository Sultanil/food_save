<?php
session_start();
include 'koneksi.php';


$error = '';

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Cek email di database
    $query = "SELECT * FROM users WHERE email = '$email'"; //bisa dipake kalo udah ada database
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['sudah_login'] = true;
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: Index.php");
            exit();
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Email tidak terdaftar!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FoodSave</title>
    <link rel="stylesheet" href="loginstyle.css">
</head>
<body>
    <div class="login-container">
        <div class="brand">
            <h1>FoodSave</h1>
            <p>Platform jual beli makanan surplus yang berkelanjutan</p>
        </div>

        <div class="form-card">
            <h2>Masuk ke Akun Anda</h2>
            <p class="subtitle">Masukkan email dan password untuk melanjutkan</p>

            <?php if ($error): ?>
                <div class="error-msg"> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="nama@email.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Masukkan password" required>
                </div>

                <button type="submit" name="submit" class="btn-submit">Masuk</button>
            </form>

            <p class="signup-link">
                Belum punya akun? <a href="RegisterPage.php">Daftar di sini</a>
            </p>

            <p class="demo-text">Data tersimpan di database MySQL</p>
        </div>

        <footer class="footer">
            <p>&copy; 2026 FoodSave - Tugas Semester 2</p>
        </footer>
    </div>
</body>
</html>