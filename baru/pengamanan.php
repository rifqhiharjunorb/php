<?php
function hanya_user() {
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'user') {
        http_response_code(403);
        echo '<div style="color:red;font-weight:bold;text-align:center;margin-top:40px;font-size:1.5em;">Akses ditolak! Anda tidak berhak mengakses halaman ini.<br>Jangan pernah berubah seperti dia<br><br><button onclick="window.history.back()" style="margin-top:20px;padding:10px 30px;font-size:1em;background:#22c55e;color:white;border:none;border-radius:8px;cursor:pointer;">Kembali</button></div>';
        exit;
    }
}

function hanya_admin() {
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
        http_response_code(403);
        echo '<div style="color:red;font-weight:bold;text-align:center;margin-top:40px;font-size:1.5em;">Akses ditolak! Anda tidak berhak mengakses halaman ini.<br>Jangan pernah berubah seperti dia<br><br><button onclick="window.history.back()" style="margin-top:20px;padding:10px 30px;font-size:1em;background:#22c55e;color:white;border:none;border-radius:8px;cursor:pointer;">Kembali</button></div>';
        exit;
    }
} 