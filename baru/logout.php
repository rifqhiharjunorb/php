<?php
// Inisialisasi sesi
session_start();
 
// Hapus semua variabel sesi
$_SESSION = array();
 
// Hapus sesi di server
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
 
// Redirect ke halaman login
header("location: login.php");
exit;
?> 