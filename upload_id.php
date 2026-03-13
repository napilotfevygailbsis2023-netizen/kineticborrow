<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ── GEMINI API KEY (free at aistudio.google.com) ─────────────────
define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY']);

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?page=profile'); exit();
}

if (!isset($_FILES['id_image']) || $_FILES['id_image']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['id_upload_error'] = 'Please select an image file to upload.';
    header('Location: dashboard.php?page=profile'); exit();
}

$file     = $_FILES['id_image'];
$allowed  = ['image/jpeg','image/jpg','image/png','image/webp'];
$max_size = 5 * 1024 * 1024;

if (!in_array($file['type'], $allowed)) {
    $_SESSION['id_upload_error'] = 'Only JPG, PNG, or WEBP images are allowed.';
    header('Location: dashboard.php?page=profile'); exit();
}
if ($file['size'] > $max_size) {
    $_SESSION['id_upload_error'] = 'File size must be under 5MB.';
    header('Location: dashboard.php?page=profile'); exit();
}

// ── SAVE FILE FIRST ──────────────────────────────────────────────
$upload_dir = 'uploads/ids/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'id_' . $user_id . '_' . time() . '.' . $ext;
$filepath = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    $_SESSION['id_upload_error'] = 'Upload failed. Please try again.';
    header('Location: dashboard.php?page=profile'); exit();
}

// ── GEMINI API CALL ───────────────────────────────────────────────
$image_data = base64_encode(file_get_contents($filepath));
$mime_type  = ($file['type'] === 'image/jpg') ? 'image/jpeg' : $file['type'];

$prompt = 'You are an AI ID verification system for a sports equipment rental platform in the Philippines. Analyze this ID image and return ONLY a valid JSON object with no markdown, no explanation, no code blocks — just raw JSON:
{
  "is_valid_id": true or false,
  "id_category": "student" or "senior" or "pwd" or "regular" or "unknown",
  "id_type_label": "human readable ID name",
  "confidence": number 0-100,
  "issues": [],
  "auto_approve": true or false,
  "reject_reason": null or "reason string",
  "discount_eligible": true or false
}
Rules: student=school ID (20% discount), senior=Senior Citizen ID (20% discount), pwd=PWD ID (20% discount), regular=valid gov ID no discount. Set auto_approve=true only if is_valid_id=true and confidence>=70 and no serious issues. discount_eligible=true only for student/senior/pwd.';

$payload = json_encode([
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => $mime_type, 'data' => $image_data]]
        ]
    ]],
    'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 1024]
]);

$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 45,
]);
$api_response = curl_exec($ch);
$curl_error   = curl_error($ch);
curl_close($ch);

// DEBUG LOG — remove after confirming it works
file_put_contents('uploads/ai_debug.txt',
    date('Y-m-d H:i:s') . "\n" .
    "CURL ERROR: " . $curl_error . "\n" .
    "RESPONSE: " . $api_response . "\n---\n"
);

// ── PARSE GEMINI RESPONSE ─────────────────────────────────────────
$ai_result          = null;
$fallback_to_manual = false;

if ($curl_error || !$api_response) {
    $fallback_to_manual = true;
} else {
    $data     = json_decode($api_response, true);
    $ai_text  = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $ai_text  = trim(preg_replace('/^```json|^```|```$/m', '', $ai_text));
    $ai_result = json_decode($ai_text, true);
    if (!$ai_result || !isset($ai_result['is_valid_id'])) {
        $fallback_to_manual = true;
    }
}

// ── DECIDE OUTCOME ────────────────────────────────────────────────
if ($fallback_to_manual || !$ai_result) {
    $id_type      = 'regular';
    $new_status   = 'pending';
    $reject_reason = null;
    $success_msg  = 'Your ID has been submitted. Our team will review it within 24 hours.';
} else {
    $id_category   = $ai_result['id_category'] ?? 'regular';
    $id_type       = in_array($id_category, ['student','senior','pwd','regular']) ? $id_category : 'regular';
    $auto_approve  = $ai_result['auto_approve']  ?? false;
    $is_valid      = $ai_result['is_valid_id']   ?? false;
    $confidence    = $ai_result['confidence']    ?? 0;
    $reject_reason = $ai_result['reject_reason'] ?? null;
    $id_type_label = $ai_result['id_type_label'] ?? 'ID';
    $issues        = $ai_result['issues']        ?? [];

    if (!$is_valid || !$auto_approve) {
        $new_status    = 'rejected';
        $reject_reason = $reject_reason ?: (count($issues) ? implode('; ', $issues) : 'Unable to verify ID from submitted image.');
        $success_msg   = null;
    } else {
        $new_status    = 'approved';
        $reject_reason = null;
        $disc_text     = $ai_result['discount_eligible'] ? ' Your 20% discount has been activated!' : '';
        $success_msg   = '🤖 AI verified your ' . htmlspecialchars($id_type_label) . ' (confidence: ' . $confidence . '%).' . $disc_text;
    }
}

// ── DELETE OLD ID IMAGE ───────────────────────────────────────────
$old = $conn->query("SELECT id_image FROM users WHERE id=$user_id")->fetch_row()[0] ?? null;
if ($old && $old !== $filepath && file_exists($old)) unlink($old);

// ── UPDATE DATABASE ───────────────────────────────────────────────
$stmt = $conn->prepare(
    "UPDATE users SET id_type=?, id_image=?, id_status=?, id_verified=?, id_reject_reason=? WHERE id=?"
);
$verified = ($new_status === 'approved') ? 1 : 0;
$stmt->bind_param('sssisi', $id_type, $filepath, $new_status, $verified, $reject_reason, $user_id);
$stmt->execute();

$ai_notes = $ai_result
    ? 'Gemini AI: confidence=' . ($ai_result['confidence'] ?? '?') . ', issues=' . json_encode($ai_result['issues'] ?? [])
    : 'Fallback: manual review';

$stmt2 = $conn->prepare(
    "INSERT INTO id_verifications (user_id, id_type, status, id_image, notes)
     VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE id_type=VALUES(id_type), status=VALUES(status), id_image=VALUES(id_image), notes=VALUES(notes), updated_at=NOW()"
);
$stmt2->bind_param('issss', $user_id, $id_type, $new_status, $filepath, $ai_notes);
$stmt2->execute();

// Refresh session
$updated = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$_SESSION['user'] = $updated;

// ── RESPONSE ──────────────────────────────────────────────────────
if ($new_status === 'rejected') {
    $_SESSION['id_upload_error'] = '❌ AI could not verify your ID: ' . ($reject_reason ?? 'Please resubmit a clearer photo.');
    $_SESSION['id_upload_msg']   = '';
} else {
    $_SESSION['id_upload_msg']   = $success_msg;
    $_SESSION['id_upload_error'] = '';
}

header('Location: dashboard.php?page=profile');
exit();
