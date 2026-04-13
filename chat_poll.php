<?php
// chat_poll.php — lightweight polling endpoint for new messages
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/handler_auth.php';

header('Content-Type: application/json');

$role    = $_GET['role']    ?? '';
$last_id = intval($_GET['last_id'] ?? 0);

if ($role === 'admin' && isAdminLoggedIn()) {
    $admin_id   = $_SESSION['admin_id'];
    $handler_id = intval($_GET['handler_id'] ?? 0);
    if (!$handler_id) { echo json_encode(['messages' => []]); exit(); }

    $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$admin_id AND handler_id=$handler_id LIMIT 1")->fetch_assoc();
    if (!$thread) { echo json_encode(['messages' => []]); exit(); }

    $msgs = $conn->query("SELECT id FROM chat_messages WHERE thread_id={$thread['id']} AND id > $last_id AND sender_type='handler'")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['messages' => $msgs]);

} elseif ($role === 'handler' && isHandlerLoggedIn()) {
    $hid      = $_SESSION['handler_id'];
    $admin_id = intval($_GET['admin_id'] ?? 0);
    if (!$admin_id) { echo json_encode(['messages' => []]); exit(); }

    $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$admin_id AND handler_id=$hid LIMIT 1")->fetch_assoc();
    if (!$thread) { echo json_encode(['messages' => []]); exit(); }

    $msgs = $conn->query("SELECT id FROM chat_messages WHERE thread_id={$thread['id']} AND id > $last_id AND sender_type='admin'")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['messages' => $msgs]);

} else {
    echo json_encode(['messages' => []]);
}
