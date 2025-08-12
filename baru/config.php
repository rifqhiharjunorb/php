<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');     // Sesuaikan dengan username database Anda
define('DB_PASSWORD', '');         // Sesuaikan dengan password database Anda
define('DB_NAME', 'pjr_rifqhi_harjuno');    // Mengubah nama database sesuai dengan database Anda

// Mencoba koneksi ke database
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Set mode error PDO ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Tidak bisa terkoneksi. " . $e->getMessage());
}
?> 