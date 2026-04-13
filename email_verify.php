<?php
// email_verify.php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

// Must have a pending verification session
if (empty($_SESSION['verify_user_id'])) {
    header('Location: login.php'); exit();
}

$uid    = intval($_SESSION['verify_user_id']);
$email  = $_SESSION['verify_email']  ?? '';
$name   = $_SESSION['verify_name']   ?? '';
$error  = '';
$resent = false;

// Handle code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'verify') {
        $entered = trim($_POST['code'] ?? '');

        // ✅ FIX: Validate that the code is exactly 6 numeric digits before querying the DB.
        // This prevents empty string or non-numeric input from being compared against DB values.
        if (!preg_match('/^\d{6}$/', $entered)) {
            $error = 'Please enter a valid 6-digit code.';
        } else {
            $row = $conn->query("SELECT verify_code, verify_code_expires, email_verified FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();

            if (!$row) {
                $error = 'Account not found.';
            } elseif ($row['verify_code'] !== $entered) {
                $error = 'Incorrect code. Please try again.';
            } elseif (strtotime($row['verify_code_expires']) < time()) {
                $error = 'Code has expired. Please request a new one.';
            } else {
                // Mark verified, clear code
                $conn->query("UPDATE users SET email_verified=1, verify_code=NULL, verify_code_expires=NULL WHERE id=$uid");
                $user = $conn->query("SELECT * FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
                $_SESSION['user_id'] = $uid;
                $_SESSION['user']    = $user;
                unset($_SESSION['verify_user_id'], $_SESSION['verify_email'], $_SESSION['verify_name']);
                header('Location: dashboard.php'); exit();
            }
        }
    }

    if ($act === 'resend') {
        $user = $conn->query("SELECT * FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
        if ($user) {
            $code = generateAndSaveCode($conn, $uid);
            $sent = sendVerificationCode($user['email'], $user['first_name'], $code);
            $resent = $sent;
            if (!$sent) $error = 'Failed to send email. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>KineticBorrow — Verify Your Email</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;--green:#2E8B57;--green-bg:#EAF6EE;--red:#C0392B;--red-bg:#FDECEA;--bg:#F7F5F2;--border:#E5E0D8;--text:#1C1916;--muted:#8A8078;}
    body{background:var(--bg);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .card{background:#fff;border-radius:20px;padding:44px 40px;width:460px;max-width:100%;box-shadow:0 8px 40px rgba(0,0,0,.1);text-align:center;}
    .brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:28px;text-decoration:none;}
    .brand-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--text);}
    .brand-name span{color:var(--gold);}
    .icon{font-size:52px;margin-bottom:16px;}
    h1{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--text);margin-bottom:8px;}
    .sub{font-size:14px;color:var(--muted);line-height:1.6;margin-bottom:28px;}
    .email-highlight{color:var(--gold);font-weight:700;}
    .code-wrap{display:flex;gap:10px;justify-content:center;margin-bottom:22px;}
    .code-input{width:52px;height:62px;border:2px solid var(--border);border-radius:12px;font-size:26px;font-weight:800;text-align:center;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .code-input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.12);}
    .alert-error{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;}
    .btn{width:100%;background:var(--gold);color:#fff;border:none;padding:14px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;margin-bottom:14px;}
    .btn:hover{background:var(--gold-lt);transform:translateY(-1px);}
    .resend-wrap{font-size:13px;color:var(--muted);}
    .resend-btn{background:none;border:none;color:var(--gold);font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;padding:0;}
    .resend-btn:hover{text-decoration:underline;}
    #timer{font-weight:600;color:var(--gold);}
  </style>
</head>
<body>
<div class="card">
  <a class="brand" href="index.php">
    <span style="font-size:24px">🏋️</span>
    <span class="brand-name">Kinetic<span>Borrow</span></span>
  </a>

  <div class="icon">📧</div>
  <h1>Verify Your Email</h1>
  <p class="sub">We sent a 6-digit code to<br><span class="email-highlight"><?= htmlspecialchars($email) ?></span><br>Enter it below to continue.</p>

  <?php if($error): ?>
  <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if($resent): ?>
  <div class="alert-success">✅ New code sent! Check your inbox.</div>
  <?php endif; ?>

  <form method="POST" id="verify-form">
    <input type="hidden" name="act" value="verify"/>
    <input type="hidden" name="code" id="code-hidden"/>
    <div class="code-wrap">
      <?php for($i=1;$i<=6;$i++): ?>
      <input class="code-input" type="text" maxlength="1" id="ci<?=$i?>" inputmode="numeric" pattern="[0-9]" autocomplete="off"/>
      <?php endfor; ?>
    </div>
    <button type="submit" class="btn" onclick="return collectCode()">Verify Email →</button>
  </form>

  <div class="resend-wrap">
    Didn't receive it?
    <form method="POST" style="display:inline">
      <input type="hidden" name="act" value="resend"/>
      <button class="resend-btn" id="resend-btn" type="submit">Resend Code</button>
    </form>
    <span id="timer-wrap" style="display:none"> in <span id="timer">60</span>s</span>
  </div>

  <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
    <a href="login.php" style="font-size:12px;color:var(--muted);text-decoration:none;">← Back to Login</a>
  </div>
</div>

<script>
// Auto-focus next input
const inputs = document.querySelectorAll('.code-input');
inputs.forEach((inp, i) => {
  inp.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g,'');
    if (e.target.value && i < 5) inputs[i+1].focus();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !e.target.value && i > 0) inputs[i-1].focus();
  });
  inp.addEventListener('paste', e => {
    e.preventDefault();
    const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
    paste.split('').forEach((ch, j) => { if(inputs[j]) inputs[j].value = ch; });
    if(inputs[Math.min(paste.length, 5)]) inputs[Math.min(paste.length, 5)].focus();
  });
});

function collectCode() {
  const code = Array.from(inputs).map(i => i.value).join('');
  if (code.length < 6) { alert('Please enter all 6 digits.'); return false; }
  document.getElementById('code-hidden').value = code;
  return true;
}

// Resend cooldown timer
<?php if($resent): ?>
startTimer();
<?php endif; ?>

function startTimer() {
  const btn  = document.getElementById('resend-btn');
  const wrap = document.getElementById('timer-wrap');
  const span = document.getElementById('timer');
  btn.disabled = true; btn.style.opacity = '.4';
  wrap.style.display = 'inline';
  let t = 60;
  const iv = setInterval(() => {
    span.textContent = --t;
    if (t <= 0) {
      clearInterval(iv);
      btn.disabled = false; btn.style.opacity = '1';
      wrap.style.display = 'none';
    }
  }, 1000);
}
</script>
</body>
</html>
