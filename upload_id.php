<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_type = in_array($_POST['id_type'] ?? '', ['student','senior','pwd']) ? $_POST['id_type'] : '';

    if (empty($id_type)) {
        $error = 'Please select an ID type.';
    } elseif (!isset($_FILES['id_image']) || $_FILES['id_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select an image file to upload.';
    } else {
        $file     = $_FILES['id_image'];
        $allowed  = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed)) {
            $error = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must be under 5MB.';
        } else {
            // Create uploads directory if not exists
            $upload_dir = 'uploads/ids/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            // Generate unique filename
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'id_' . $user_id . '_' . time() . '.' . strtolower($ext);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old image if exists
                $old = $conn->query("SELECT id_image FROM users WHERE id=$user_id")->fetch_row()[0];
                if ($old && file_exists($old)) unlink($old);

                // Update user record
                $stmt = $conn->prepare("UPDATE users SET id_type=?, id_image=?, id_status='pending', id_verified=0, id_reject_reason=NULL WHERE id=?");
                $stmt->bind_param('ssi', $id_type, $filepath, $user_id);
                $stmt->execute();

                // Add to id_verifications for admin review
                $stmt2 = $conn->prepare("INSERT INTO id_verifications (user_id, id_type, status, id_image) VALUES (?,?,'pending',?)
                    ON DUPLICATE KEY UPDATE id_type=?, status='pending', id_image=?, updated_at=NOW()");
                $stmt2->bind_param('issss', $user_id, $id_type, $filepath, $id_type, $filepath);
                $stmt2->execute();

                $success = 'Your ID has been submitted successfully! Our team will review it within 24 hours and apply your discount.';
                // Refresh session user
                $updated = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
                $_SESSION['user'] = $updated;
            } else {
                $error = 'Upload failed. Please try again.';
            }
        }
    }
}

// Redirect back to dashboard profile with message
$_SESSION['id_upload_msg']   = $success ?: '';
$_SESSION['id_upload_error'] = $error   ?: '';
header('Location: dashboard.php?page=profile');
exit();
?>
