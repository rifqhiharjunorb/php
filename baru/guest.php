<?php
session_start();
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin') {
        header("Location: dashboard_admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
} 