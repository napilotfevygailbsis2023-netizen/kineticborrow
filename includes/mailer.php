<?php
// includes/mailer.php
// Sends a 6-digit verification code to the user's email

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationCode($to_email, $to_name, $code) {
    // Load vendor autoload
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) return false;
    require_once $autoload;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kineticborrow@gmail.com';
        $mail->Password   = 'tlqvkwqxdyfzkxex'; // App password (no spaces)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('kineticborrow@gmail.com', 'KineticBorrow');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your KineticBorrow Verification Code: ' . $code;
        $mail->Body    = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#F7F5F2;font-family:DM Sans,sans-serif;">
  <div style="max-width:480px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
    <div style="background:linear-gradient(135deg,#1C1916,#2E2420);padding:32px;text-align:center;">
      <div style="font-size:32px;margin-bottom:8px;">🏋️</div>
      <div style="font-family:serif;font-size:22px;font-weight:800;color:#fff;">Kinetic<span style="color:#C47F2B;">Borrow</span></div>
    </div>
    <div style="padding:36px 40px;text-align:center;">
      <p style="font-size:16px;color:#1C1916;font-weight:700;margin-bottom:6px;">Hi ' . htmlspecialchars($to_name) . ',</p>
      <p style="font-size:14px;color:#8A8078;margin-bottom:28px;line-height:1.6;">Use the code below to verify your email address. This code expires in <strong>10 minutes</strong>.</p>
      <div style="background:#FDF3E3;border:2px dashed #C47F2B;border-radius:12px;padding:24px;margin-bottom:28px;">
        <div style="font-size:42px;font-weight:800;letter-spacing:12px;color:#C47F2B;font-family:monospace;">' . $code . '</div>
      </div>
      <p style="font-size:12px;color:#8A8078;line-height:1.6;">If you did not create a KineticBorrow account, you can safely ignore this email.</p>
    </div>
    <div style="background:#F7F5F2;padding:16px;text-align:center;">
      <p style="font-size:11px;color:#8A8078;">© ' . date('Y') . ' KineticBorrow · University of Caloocan City</p>
    </div>
  </div>
</body>
</html>';
        $mail->AltBody = "Your KineticBorrow verification code is: $code\n\nThis code expires in 10 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

function generateAndSaveCode($conn, $user_id) {
    $code    = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $conn->query("UPDATE users SET verify_code='$code', verify_code_expires='$expires' WHERE id=$user_id");
    return $code;
}
?>
