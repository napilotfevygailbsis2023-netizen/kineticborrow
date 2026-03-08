<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';

if (isAdminLoggedIn()) { header('Location: admin_dashboard.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin']    = $admin;
            header('Location: admin_dashboard.php');
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KineticBorrow — Admin Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;--red:#C0392B;--red-bg:#FDECEA;--bg:#F7F5F2;--border:#E5E0D8;--muted:#8A8078;--text:#1C1916;}
    body{background:var(--bg);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
    .login-wrap{display:flex;width:900px;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.1);}
    .login-left{flex:1;background:linear-gradient(160deg,#1C1916,#2E2420);padding:52px 44px;display:flex;flex-direction:column;justify-content:center;}
    .login-left .brand-name{font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:#fff;margin-bottom:4px;}
    .login-left .brand-name span{color:var(--gold);}
    .login-left .brand-sub{font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.1em;margin-bottom:48px;}
    .left-title{font-family:'Playfair Display',serif;font-size:32px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:14px;}
    .left-title span{color:var(--gold);}
    .left-sub{font-size:13px;color:#888;line-height:1.7;margin-bottom:36px;}
    .feat-item{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
    .feat-icon{font-size:16px;}
    .feat-text{font-size:13px;color:#AAA;}
    .login-right{width:400px;padding:52px 44px;display:flex;flex-direction:column;justify-content:center;}
    .form-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .form-sub{font-size:13px;color:var(--muted);margin-bottom:28px;}
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;font-weight:500;background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .input-group{margin-bottom:18px;}
    .input-label{display:block;font-size:12px;font-weight:600;color:#4A4540;letter-spacing:.04em;text-transform:uppercase;margin-bottom:7px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:15px;color:#C0B8AE;pointer-events:none;}
    .form-input{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:11px 16px 11px 42px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .form-input::placeholder{color:#C0B8AE;}
    .eye-btn{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#C0B8AE;}
    .submit-btn{width:100%;background:var(--gold);color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;transition:all .2s;margin-top:4px;}
    .submit-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 6px 16px rgba(196,127,43,.3);}
    .back-link{text-align:center;margin-top:20px;font-size:12px;color:var(--muted);}
    .back-link a{color:var(--gold);font-weight:500;text-decoration:none;}
    .back-link a:hover{text-decoration:underline;}
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-left">
    <div class="brand-name">Kinetic<span>Borrow</span></div>
    <div class="brand-sub">Admin Panel</div>
    <h1 class="left-title">Manage Your<br><span>Rental Business</span></h1>
    <p class="left-sub">Full control over equipment, customers, rentals, and promotions — all in one place.</p>
    <div class="feat-item"><span class="feat-icon">📊</span><span class="feat-text">Real-time dashboard & reports</span></div>
    <div class="feat-item"><span class="feat-icon">🏋️</span><span class="feat-text">Equipment & inventory management</span></div>
    <div class="feat-item"><span class="feat-icon">👥</span><span class="feat-text">Customer & order management</span></div>
    <div class="feat-item"><span class="feat-icon">🪪</span><span class="feat-text">AI ID verification control</span></div>
    <div class="feat-item"><span class="feat-icon">🎁</span><span class="feat-text">Promotions & loyalty programs</span></div>
  </div>
  <div class="login-right">
    <h2 class="form-title">Admin Login</h2>
    <p class="form-sub">Sign in to access the KineticBorrow admin panel.</p>
    <?php if ($error): ?><div class="alert">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="admin_login.php">
      <div class="input-group">
        <label class="input-label">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input class="form-input" type="email" name="email" placeholder="admin@kineticborrow.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
        </div>
      </div>
      <div class="input-group">
        <label class="input-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input class="form-input" type="password" name="password" id="pass" placeholder="Enter admin password" required/>
          <button class="eye-btn" type="button" onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password';this.textContent=this.previousElementSibling.type==='password'?'👁️':'🙈'">👁️</button>
        </div>
      </div>
      <button type="submit" class="submit-btn">Sign In to Admin Panel →</button>
    </form>
    <div class="back-link">← <a href="index.php">Back to main site</a></div>
  </div>
</div>
</body>
</html>
