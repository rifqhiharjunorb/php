<?php
require_once "guest.php";
// Include file konfigurasi
require_once "config.php";

// Inisialisasi variabel
$username = $password = "";
$username_err = $password_err = "";
$login_err = "";

// Proses form ketika di-submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validasi username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Silakan masukkan username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Validasi password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Silakan masukkan password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validasi kredensial
    if (empty($username_err) && empty($password_err)) {
        // Siapkan statement select
        $sql = "SELECT user_id, username, password, role, nama_lengkap, jenis_pengguna FROM users WHERE username = :username";
        
        if($stmt = $pdo->prepare($sql)) {
            // Bind variabel ke statement sebagai parameter
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Set parameter
            $param_username = trim($_POST["username"]);
            
            // Mencoba eksekusi statement
            if($stmt->execute()) {
                // Periksa jika username ada
                if($stmt->rowCount() == 1) {
                    if($row = $stmt->fetch()) {
                        $id = $row["user_id"];
                        $username = $row["username"];
                        $hashed_password = $row["password"];
                        $role = $row["role"];
                        $nama_lengkap = $row["nama_lengkap"];
                        $jenis_pengguna = $row["jenis_pengguna"];
                        
                        if($password == $hashed_password) { // Menggunakan perbandingan langsung karena password tidak di-hash
                            // Password benar, mulai sesi baru
                            session_start();
                            
                            // Simpan data di sesi
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            $_SESSION["nama_lengkap"] = $nama_lengkap;
                            $_SESSION["jenis_pengguna"] = $jenis_pengguna;
                            
                            // Redirect berdasarkan role
                            if($role == "admin") {
                                header("location: dashboard_admin.php");
                            } else {
                                header("location: dashboard.php");
                            }
                        } else {
                            // Password salah
                            $login_err = "Username atau password tidak valid.";
                        }
                    }
                } else {
                    // Username tidak ditemukan
                    $login_err = "Username atau password tidak valid.";
                }
            } else {
                $login_err = "Oops! Terjadi kesalahan. Silakan coba lagi nanti.";
            }

            // Tutup statement
            unset($stmt);
        }
    }
    
    // Tutup koneksi
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            padding: 0;
            background: #f2f2f2;
        }
        .container {
            max-width: 900px;
            width: 90vw;
            margin: 48px auto;
            background: #fff;
            display: flex;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            flex-direction: row;
            box-sizing: border-box;
        }
        @media (max-width: 900px) {
            .container { max-width: 98vw; width: 98vw; }
        }
        @media (max-width: 700px) {
            .container { flex-direction: column; }
            .login, .register, .register-form, .promo { padding: 24px 16px; }
        }
        @media (max-width: 600px) {
            .container { margin: 10px auto; border-radius: 8px; }
            .login, .register, .register-form, .promo { padding: 12px 4px; }
            .register h2, .login h2, .promo h2, .register-form h2 { font-size: 1.1rem; }
        }
        .login, .register, .register-form, .promo {
            flex: 1;
            padding: 48px 40px;
            box-sizing: border-box;
        }
        .register {
            background: linear-gradient(to right, #0284c7, #38bdf8);
            color: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .register h2 {
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: bold;
        }
        .register p {
            margin-bottom: 30px;
        }
        .register a, .register button {
            text-decoration: none;
            background: white;
            color: #0284c7;
            padding: 12px 40px;
            border-radius: 30px;
            font-weight: bold;
            border: 2px solid #0284c7;
            font-size: 1rem;
            transition: background 0.2s, color 0.2s;
            cursor: pointer;
        }
        .register a:hover, .register button:hover {
            background: #0284c7;
            color: white;
        }
        .login h2 {
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: bold;
        }
        .social-buttons {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            gap: 16px;
        }
        .social-buttons button {
            border: 1px solid #ccc;
            background: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 1.2rem;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        form input, form button, .register-btn, .login-btn {
            width: 100%;
            box-sizing: border-box;
        }
        .forgot {
            font-size: 0.95em;
            margin-bottom: 20px;
            color: #888;
        }
        .login-btn {
            background: #0284c7;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background: #0284c7;
        }
        .error {
            color: red;
            font-size: 0.95em;
            margin-bottom: 10px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body>
<div class="container">
    <div class="login">
        <h2>Sign in</h2>
        <p> gunakan akun anda</p>
        <?php if(!empty($login_err)): ?>
            <div class="error"><?php echo $login_err; ?></div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:flex;flex-direction:column;gap:15px;">
            <input type="text" name="username" placeholder="Username" value="<?php echo $username; ?>" required style="padding:12px;border:none;background:#eee;border-radius:6px;font-size:1rem;height:44px;" />
            <div style="position:relative;display:flex;align-items:center;height:44px;">
                <input type="password" name="password" id="password" placeholder="Password" required style="width:100%;padding:12px 40px 12px 12px;border:none;background:#eee;border-radius:6px;font-size:1rem;height:44px;" />
                <span id="togglePassword" style="position:absolute;right:12px;top:0;bottom:0;display:flex;align-items:center;cursor:pointer;font-size:1.2em;color:#888;height:44px;">
                    <i class="fa fa-eye" id="icon-eye"></i>
                </span>
            </div>
            <button class="login-btn" type="submit">SIGN IN</button>
        </form>
    </div>
    <div class="register">
        <h2>Halo, Teman!</h2>
        <p>Daftarkan diri anda dan mulai gunakan layanan kami segera</p>
        <a href="register.php">SIGN UP</a>
    </div>
</div>
<script>
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
const iconEye = document.getElementById('icon-eye');
togglePassword.addEventListener('click', function () {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    iconEye.classList.toggle('fa-eye');
    iconEye.classList.toggle('fa-eye-slash');
});
</script>
</body>
</html>