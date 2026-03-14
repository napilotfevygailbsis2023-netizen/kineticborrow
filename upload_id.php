<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

// ── GEMINI API KEY ────────────────────────────────────────────────
// Get a FREE key at: https://aistudio.google.com/app/apikey
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    }
}
$_gemini_key = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
define('GEMINI_API_KEY', $_gemini_key);

$user_id = $_SESSION['user_id']; // ← was missing, caused the fatal error

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

// ── SAVE FILE ────────────────────────────────────────────────────
$upload_dir = 'uploads/ids/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext === 'jpg') $ext = 'jpeg';
$filename = 'id_' . $user_id . '_' . time() . '.' . $ext;
$filepath = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    $_SESSION['id_upload_error'] = 'Upload failed. Please try again.';
    header('Location: dashboard.php?page=profile'); exit();
}

// ── GEMINI API CALL ───────────────────────────────────────────────
$ai_result          = null;
$fallback_to_manual = false;

if (!empty(GEMINI_API_KEY) && function_exists('curl_init')) {

    $image_data = base64_encode(file_get_contents($filepath));
    $mime_type  = ($file['type'] === 'image/jpg') ? 'image/jpeg' : $file['type'];

    $prompt = "You are an AI ID verification system for a sports equipment rental platform in the Philippines. Analyze this ID image and return ONLY a valid JSON object with no markdown, no explanation, no code blocks — just raw JSON:\n{\n  \"is_valid_id\": true or false,\n  \"id_category\": \"student\" or \"senior\" or \"pwd\" or \"regular\" or \"unknown\",\n  \"id_type_label\": \"human readable ID name\",\n  \"confidence\": number 0-100,\n  \"issues\": [],\n  \"auto_approve\": true or false,\n  \"reject_reason\": null or \"reason string\",\n  \"discount_eligible\": true or false\n}\nRules: student=school/university ID (20% discount), senior=Senior Citizen ID (20% discount), pwd=PWD ID (20% discount), regular=valid gov ID no discount. Set auto_approve=true only if is_valid_id=true and confidence>=70 and no serious issues. discount_eligible=true only for student/senior/pwd.";

    $payload = json_encode([
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mime_type, 'data' => $image_data]]
            ]
        ]],
        'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 1024]
    ]);

    $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];
    foreach ($models as $model) {
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
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
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $log = date('Y-m-d H:i:s') . " model=$model http=$http_code\nERROR: $curl_error\nRESPONSE: " . substr($api_response, 0, 500) . "\n---\n";
        file_put_contents('uploads/ai_debug.txt', $log, FILE_APPEND);

        if ($curl_error) continue;
        if ($http_code === 404) continue;
        if ($http_code === 429) continue;

        if ($api_response && $http_code === 200) {
            $data    = json_decode($api_response, true);
            $ai_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $ai_text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $ai_text));
            $parsed  = json_decode($ai_text, true);
            if ($parsed && isset($parsed['is_valid_id'])) {
                $ai_result = $parsed;
                break;
            }
        }

        if (in_array($http_code, [400, 401, 403])) break;
    }

    if (!$ai_result) $fallback_to_manual = true;

} else {
    $fallback_to_manual = true;
}

// ── DECIDE OUTCOME ────────────────────────────────────────────────
if ($fallback_to_manual || !$ai_result) {
    $id_type       = 'regular';
    $new_status    = 'pending';
    $reject_reason = null;
    $success_msg   = '✅ Your ID has been submitted. Our team will review it within 24 hours.';
} else {
    $id_category   = $ai_result['id_category'] ?? 'regular';
    $id_type       = in_array($id_category, ['student','senior','pwd','regular']) ? $id_category : 'regular';
    $auto_approve  = $ai_result['auto_approve']  ?? false;
    $is_valid      = $ai_result['is_valid_id']   ?? false;
    $confidence    = intval($ai_result['confidence'] ?? 0);
    $reject_reason = $ai_result['reject_reason'] ?? null;
    $id_type_label = $ai_result['id_type_label'] ?? ucfirst($id_type) . ' ID';
    $issues        = $ai_result['issues']        ?? [];

    if ($is_valid && $auto_approve && $confidence >= 70) {
        $new_status    = 'approved';
        $reject_reason = null;
        $disc_text     = ($ai_result['discount_eligible'] ?? false) ? ' Your 20% discount has been activated! 🎉' : '';
        $success_msg   = "🤖 AI verified your {$id_type_label} (confidence: {$confidence}%).{$disc_text}";
    } elseif ($is_valid && !$auto_approve) {
        $new_status    = 'pending';
        $reject_reason = null;
        $success_msg   = '✅ Your ID has been submitted for manual review. Usually verified within 24 hours.';
    } else {
        $new_status    = 'rejected';
        $reject_reason = $reject_reason ?: (count($issues) ? implode('; ', $issues) : 'Could not verify ID. Please resubmit a clearer photo.');
        $success_msg   = null;
    }
}

// ── DELETE OLD ID IMAGE ───────────────────────────────────────────
$old = $conn->query("SELECT id_image FROM users WHERE id=$user_id")->fetch_row()[0] ?? null;
if ($old && $old !== $filepath && file_exists($old)) @unlink($old);

// ── UPDATE DATABASE ───────────────────────────────────────────────
$stmt = $conn->prepare("UPDATE users SET id_type=?, id_image=?, id_status=?, id_verified=?, id_reject_reason=? WHERE id=?");
$verified = ($new_status === 'approved') ? 1 : 0;
$stmt->bind_param('sssisi', $id_type, $filepath, $new_status, $verified, $reject_reason, $user_id);
$stmt->execute();

$ai_notes = $ai_result
    ? 'AI: confidence=' . ($ai_result['confidence'] ?? '?') . ', category=' . ($ai_result['id_category'] ?? '?') . ', issues=' . json_encode($ai_result['issues'] ?? [])
    : ($fallback_to_manual ? 'Fallback: manual review' : 'AI parse failed');

$stmt2 = $conn->prepare(
    "INSERT INTO id_verifications (user_id, id_type, status, id_image, notes)
     VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE id_type=VALUES(id_type), status=VALUES(status), id_image=VALUES(id_image), notes=VALUES(notes), updated_at=NOW()"
);
$stmt2->bind_param('issss', $user_id, $id_type, $new_status, $filepath, $ai_notes);
$stmt2->execute();

$updated = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$_SESSION['user'] = $updated;

// ── RESPOND ───────────────────────────────────────────────────────
if ($new_status === 'rejected') {
    $_SESSION['id_upload_error'] = '❌ ' . htmlspecialchars($reject_reason ?? 'Please resubmit a clearer photo of your ID.');
    $_SESSION['id_upload_msg']   = '';
} else {
    $_SESSION['id_upload_msg']   = $success_msg;
    $_SESSION['id_upload_error'] = '';
}

header('Location: dashboard.php?page=profile');
exit();
