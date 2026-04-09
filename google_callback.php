<?php
// google_callback.php
// Handles the redirect back from Google after user approves sign-in

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/google_oauth.php';

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Google returned an error
if (isset($_GET['error'])) {
    header('Location: login.php?google_error=cancelled');
    exit();
}

// No code returned
if (empty($_GET['code'])) {
    header('Location: login.php?google_error=no_code');
    exit();
}

// Exchange code for access token
$token_data = getGoogleToken($_GET['code']);

if (empty($token_data['access_token'])) {
    header('Location: login.php?google_error=token_failed');
    exit();
}

// Get user info from Google
$guser = getGoogleUserInfo($token_data['access_token']);

if (empty($guser['email'])) {
    header('Location: login.php?google_error=no_email');
    exit();
}

$google_id = $conn->real_escape_string($guser['sub']);
$email     = $conn->real_escape_string($guser['email']);
$fname     = $conn->real_escape_string($guser['given_name']  ?? '');
$lname     = $conn->real_escape_string($guser['family_name'] ?? '');
$avatar    = $conn->real_escape_string($guser['picture']     ?? '');

// Check if user already exists by google_id OR email
$existing = $conn->query("SELECT * FROM users WHERE google_id='$google_id' OR email='$email' LIMIT 1")->fetch_assoc();

if ($existing) {
    // Block check
    if ($existing['is_blocked'] ?? 0) {
        header('Location: login.php?google_error=blocked');
        exit();
    }

    // Update google_id and avatar if logging in via Google for the first time on existing email account
    $conn->query("UPDATE users SET
        google_id='$google_id',
        auth_provider='google',
        avatar='$avatar'
        WHERE id={$existing['id']}");

    // Refresh user from DB
    $user = $conn->query("SELECT * FROM users WHERE id={$existing['id']} LIMIT 1")->fetch_assoc();

} else {
    // New user — register them automatically
    // Generate a random unusable password (they'll use Google to log in)
    $random_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users
        (first_name, last_name, email, phone, password, id_type, google_id, auth_provider, avatar)
        VALUES (?, ?, ?, '', ?, 'regular', ?, 'google', ?)");
    $stmt->bind_param('ssssss', $fname, $lname, $email, $random_pass, $google_id, $avatar);
    $stmt->execute();
    $new_id = $conn->insert_id;

    $user = $conn->query("SELECT * FROM users WHERE id=$new_id LIMIT 1")->fetch_assoc();
}

// Log them in
$_SESSION['user_id'] = $user['id'];
$_SESSION['user']    = $user;

header('Location: dashboard.php');
exit();
