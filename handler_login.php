<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';

if (isHandlerLoggedIn()) { header('Location: handler_dashboard.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM handlers WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $handler = $stmt->get_result()->fetch_assoc();

        if ($handler && password_verify($password, $handler['password'])) {
            $_SESSION['handler_id'] = $handler['id'];
            $_SESSION['handler']    = $handler;
            header('Location: handler_dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>KineticBorrow — Handler Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--gold:#C47F2B;--gold-lt:#D9952E;--teal:#0E7C86;--teal-dk:#095E66;--teal-bg:#E8F6F7;--red:#C0392B;--red-bg:#FDECEA;--bg:#F2F7F7;--border:#D0E4E6;--text:#0D2B2D;}
    body{background:var(--bg);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
    .login-wrap{display:flex;width:900px;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.1);}
    .login-left{flex:1;background:linear-gradient(160deg,#0D2B2D,#0E5C66);padding:52px 44px;display:flex;flex-direction:column;justify-content:center;}
    .brand-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:2px;}
    .brand-name span{color:var(--gold);}
    .brand-sub{font-size:11px;color:#5AABB2;text-transform:uppercase;letter-spacing:.12em;margin-bottom:44px;}
    .left-title{font-family:'Playfair Display',serif;font-size:30px;font-weight:800;color:#fff;line-height:1.25;margin-bottom:12px;}
    .left-title span{color:#5AEBB2;}
    .left-sub{font-size:13px;color:#7ABFC5;line-height:1.7;margin-bottom:34px;}
    .feat-item{display:flex;align-items:center;gap:10px;margin-bottom:11px;}
    .feat-icon{font-size:15px;width:28px;height:28px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .feat-text{font-size:13px;color:#9ACFD5;}
    .login-right{width:420px;padding:52px 44px;display:flex;flex-direction:column;justify-content:center;}
    .role-badge{display:inline-flex;align-items:center;gap:6px;background:var(--teal-bg);color:var(--teal);border:1px solid #B0D8DB;border-radius:20px;padding:5px 13px;font-size:12px;font-weight:600;margin-bottom:18px;}
    .form-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .form-sub{font-size:13px;color:#6A8E90;margin-bottom:26px;}
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;font-weight:500;background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .input-group{margin-bottom:18px;}
    .input-label{display:block;font-size:11px;font-weight:700;color:#4A7A7C;letter-spacing:.06em;text-transform:uppercase;margin-bottom:7px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;color:#A0C4C6;pointer-events:none;}
    .form-input{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:11px 16px 11px 42px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(14,124,134,.1);}
    .form-input::placeholder{color:#A0C4C6;}
    .eye-btn{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#A0C4C6;}
    .submit-btn{width:100%;background:linear-gradient(135deg,var(--teal),var(--teal-dk));color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;transition:all .2s;margin-top:4px;}
    .submit-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(14,124,134,.3);}
    .back-link{text-align:center;margin-top:20px;font-size:12px;color:#6A8E90;}
    .back-link a{color:var(--teal);font-weight:500;text-decoration:none;}
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-left">
    <div class="brand-name">Kinetic<span>Borrow</span></div>
    <div class="brand-sub">Equipment Handler Portal</div>
    <h1 class="left-title">Handle Equipment.<br><span>Keep Things Moving.</span></h1>
    <p class="left-sub">Your operations hub for check-outs, returns, condition logging, and incident reporting.</p>
    <div class="feat-item"><span class="feat-icon">📋</span><span class="feat-text">View daily reservation queue</span></div>
    <div class="feat-item"><span class="feat-icon">✅</span><span class="feat-text">Process check-out & confirm release</span></div>
    <div class="feat-item"><span class="feat-icon">📦</span><span class="feat-text">Receive returns & update availability</span></div>
    <div class="feat-item"><span class="feat-icon">🔍</span><span class="feat-text">Log equipment condition</span></div>
    <div class="feat-item"><span class="feat-icon">🚨</span><span class="feat-text">Report damage, loss, or incidents</span></div>
    <div class="feat-item"><span class="feat-icon">🚫</span><span class="feat-text">Check blocklist before releasing</span></div>
  </div>
  <div class="login-right">
    <div class="role-badge">🔧 Equipment Handler</div>
    <h2 class="form-title">Handler Login</h2>
    <p class="form-sub">Sign in to access your equipment operations portal.</p>
    <?php if ($error): ?><div class="alert">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="handler_login.php">
      <div class="input-group">
        <label class="input-label">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input class="form-input" type="email" name="email" placeholder="handler@kineticborrow.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
        </div>
      </div>
      <div class="input-group">
        <label class="input-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input class="form-input" type="password" name="password" id="pass" placeholder="Enter your password" required/>
          <button class="eye-btn" type="button" onclick="const i=document.getElementById('pass');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁️':'🙈'">👁️</button>
        </div>
      </div>
      <button type="submit" class="submit-btn">Sign In to Handler Portal →</button>
    </form>
    <div class="back-link">← <a href="index.php">Back to main site</a></div>
  </div>
</div>
</body>
</html>
