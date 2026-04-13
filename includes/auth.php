<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireGuest() {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit();
    }
}

function getUser() {
    return $_SESSION['user'] ?? null;
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}
?>
