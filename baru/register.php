<?php
require_once "guest.php";

// Include file konfigurasi database
require_once "config.php";

// Mendefinisikan variabel dengan nilai kosong
$id_card = $username = $password = $confirm_password = $role = $jenis_pengguna = $nama_lengkap = "";
$id_card_err = $username_err = $password_err = $confirm_password_err = $role_err = $jenis_pengguna_err = $nama_lengkap_err = "";

// Set default role dan jenis_pengguna
$role = 'user';
$jenis_pengguna = 'internal';

// Memproses data form ketika form di-submit
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validasi ID Card
    if(empty(trim($_POST["id_card"]))){
        $id_card_err = "Masukkan nomor ID Card.";
    } else {
        $id_card = trim($_POST["id_card"]);
    }
    
    // Validasi username
    if(empty(trim($_POST["username"]))){
        $username_err = "Masukkan username.";
    } else {
        // Menyiapkan statement select
        $sql = "SELECT user_id FROM users WHERE username = :username";
        
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $param_username = trim($_POST["username"]);
            
            if($stmt->execute()){
                if($stmt->rowCount() == 1){
                    $username_err = "Username ini sudah terdaftar.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Terjadi kesalahan. Silakan coba lagi nanti.";
            }

            unset($stmt);
        }
    }
    
    // Validasi password
    if(empty(trim($_POST["password"]))){
        $password_err = "Masukkan password.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password harus memiliki minimal 6 karakter.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validasi konfirmasi password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Konfirmasi password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password tidak cocok.";
        }
    }

    // Validasi nama lengkap
    if(empty(trim($_POST["nama_lengkap"]))){
        $nama_lengkap_err = "Masukkan nama lengkap.";
    } else {
        $nama_lengkap = trim($_POST["nama_lengkap"]);
    }
    
    // Cek error sebelum insert ke database
    if(empty($id_card_err) && empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($nama_lengkap_err)){
        
        // Menyiapkan statement insert
        $sql = "INSERT INTO users (id_card, username, password, role, jenis_pengguna, nama_lengkap) VALUES (:id_card, :username, :password, :role, :jenis_pengguna, :nama_lengkap)";
         
        if($stmt = $pdo->prepare($sql)){
            // Bind parameter
            $stmt->bindParam(":id_card", $param_id_card, PDO::PARAM_STR);
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":role", $param_role, PDO::PARAM_STR);
            $stmt->bindParam(":jenis_pengguna", $param_jenis_pengguna, PDO::PARAM_STR);
            $stmt->bindParam(":nama_lengkap", $param_nama_lengkap, PDO::PARAM_STR);
            
            // Set parameter
            $param_id_card = $id_card;
            $param_username = $username;
            $param_password = $password; // Simpan password apa adanya (tidak di-hash)
            $param_role = $role;
            $param_jenis_pengguna = $jenis_pengguna;
            $param_nama_lengkap = $nama_lengkap;
            
            // Mencoba eksekusi statement
            if($stmt->execute()){
                // Redirect ke halaman login dengan parameter sukses
                header("location: login.php?registered=true");
            } else{
                echo "Oops! Terjadi kesalahan. Silakan coba lagi nanti.";
            }

            unset($stmt);
        }
    }
    
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi</title>
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
        .promo {
            background: linear-gradient(to right, #0284c7, #38bdf8);
            color: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .promo h2 {
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: bold;
        }
        .promo p {
            margin-bottom: 30px;
        }
        .promo a, .promo button {
            text-decoration: none;
            background: white;
            color: #0284c7;
            padding: 12px 40px;
            border-radius: 30px;
            font-weight: bold;
            border: 2px solid white;
            font-size: 1rem;
            transition: background 0.2s, color 0.2s;
            cursor: pointer;
        }
        .promo a:hover, .promo button:hover {
            background: #0284c7;
            color: white;
        }
        .register-form h2 {
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: bold;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        form input, form select {
            margin-bottom: 15px;
            padding: 12px;
            border: none;
            background: #eee;
            border-radius: 6px;
            font-size: 1rem;
        }
        .register-btn {
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
        .register-btn:hover {
            background: #0284c7;
        }
        .error {
            color: red;
            font-size: 0.95em;
            margin-bottom: 10px;
        }
        form input, form button, .register-btn, .login-btn {
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
<a href="login.php" style="position:absolute;left:30px;top:30px;z-index:10;background:#0284c7;color:white;padding:8px 22px;border-radius:8px;text-decoration:none;font-weight:bold;box-shadow:0 2px 8px rgba(0,0,0,0.08);">&larr; Kembali ke Login</a>
<div class="container">
    <div class="register-form">
        <h2>Buat Akun Baru</h2>
        <?php if(!empty($id_card_err)) echo '<div class="error">'.$id_card_err.'</div>'; ?>
        <?php if(!empty($username_err)) echo '<div class="error">'.$username_err.'</div>'; ?>
        <?php if(!empty($password_err)) echo '<div class="error">'.$password_err.'</div>'; ?>
        <?php if(!empty($confirm_password_err)) echo '<div class="error">'.$confirm_password_err.'</div>'; ?>
        <?php if(!empty($nama_lengkap_err)) echo '<div class="error">'.$nama_lengkap_err.'</div>'; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" name="id_card" placeholder="ID Card" value="<?php echo $id_card; ?>" required />
            <input type="text" name="username" placeholder="Username" value="<?php echo $username; ?>" required />
            <input type="password" name="password" placeholder="Password" required style="padding:12px;border:none;background:#eee;border-radius:6px;font-size:1rem;" />
            <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required style="padding:12px;border:none;background:#eee;border-radius:6px;font-size:1rem;" />
            <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" value="<?php echo $nama_lengkap; ?>" required />
            <button class="register-btn" type="submit">DAFTAR</button>
        </form>
    </div>
    <div class="promo">
        <h2>Halo, Teman!</h2>
        <p>Daftarkan diri anda dan mulai gunakan layanan kami segera</p>
    </div>
</div>
</body>
</html> 