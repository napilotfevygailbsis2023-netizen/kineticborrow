<?php
// db.php — supports both local XAMPP and Railway deployment
// On Railway, set these as environment variables in the Variables tab

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'kineticborrow');

// API Keys — set as environment variables on Railway
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: 'YOUR_KEY_HERE');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');
?>
