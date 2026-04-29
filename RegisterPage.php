<?php
session_start();
include 'koneksi.php';

$error = '';
$success = '';

if (isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($conn, $_POST['nama']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    // Validasi
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Cek email sudah ada?
        $cek = "SELECT * FROM users WHERE Email = '$email'";
        $result = mysqli_query($conn, $cek);
        
        if (mysqli_num_rows($result) > 0) {
            $error = 'Email sudah terdaftar!';
        } else {
            // Hash password (enkripsi)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Simpan ke database (sesuai struktur tabel)
            $query = "INSERT INTO users (UserName, NamaLengkap, Email, Password, Role) 
                      VALUES ('$username', '$nama_lengkap', '$email', '$hashed_password', '$role')";
            
            if (mysqli_query($conn, $query)) {
                // Auto login setelah register
                $_SESSION['sudah_login'] = true;
                $_SESSION['nama'] = $nama_lengkap;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                
                header("Location: Index.php");
                exit();
                
            } else {
                $error = 'Registrasi gagal: ' . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - FoodSave</title>
    <link rel="stylesheet" href="loginstyle.css">
</head>
<body>
    <div class="login-container">
        <div class="brand">
            <h1>FoodSave</h1>
            <p>Platform jual beli makanan surplus yang berkelanjutan</p>
        </div>

        <div class="form-card">
            <h2>Buat Akun Baru</h2>
            <p class="subtitle">Daftar untuk mulai menggunakan FoodSave</p>

            <?php if ($error): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Nama Anda" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="nama@email.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
                </div>

                <div class="form-group">
                    <label>Daftar Sebagai</label>
                    <div class="role-selector">
                        <label class="role-card">
                            <input type="radio" name="role" value="pembeli" checked>
                            <div class="role-content">
                                <h3>Pembeli</h3>
                                <p>Cari dan beli makanan surplus</p>
                            </div>
                        </label>
                        <label class="role-card">
                            <input type="radio" name="role" value="penjual">
                            <div class="role-content">
                                <h3>Penjual</h3>
                                <p>Jual makanan surplus</p>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-submit">Daftar</button>
            </form>

            <p class="signup-link">
                Sudah punya akun? <a href="LoginPage.php">Masuk di sini</a>
            </p>
        </div>

        <footer class="footer">
            <p>&copy; 2026 FoodSave - Tugas Semester 2</p>
        </footer>
    </div>
</body>
</html>