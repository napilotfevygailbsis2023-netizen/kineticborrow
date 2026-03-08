<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin_login.php');
        exit();
    }
}

function getAdmin() {
    return $_SESSION['admin'] ?? null;
}

function adminLogout() {
    unset($_SESSION['admin_id'], $_SESSION['admin']);
    header('Location: admin_login.php');
    exit();
}
?>
