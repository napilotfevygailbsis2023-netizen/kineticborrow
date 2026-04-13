<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isHandlerLoggedIn() {
    return isset($_SESSION['handler_id']);
}

function requireHandler() {
    if (!isHandlerLoggedIn()) {
        header('Location: handler_login.php');
        exit();
    }
}

function getHandler() {
    return $_SESSION['handler'] ?? null;
}

function handlerLogout() {
    unset($_SESSION['handler_id'], $_SESSION['handler']);
    header('Location: handler_login.php');
    exit();
}
?>
