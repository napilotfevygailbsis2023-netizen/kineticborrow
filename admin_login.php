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
        $stmt->bind_param('s', $email); $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin']    = $admin;
            header('Location: admin_dashboard.php'); exit();
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
  <title>KineticBorrow — Admin Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--gold:#C47F2B;--gold-lt:#D9952E;--red:#C0392B;--red-bg:#FDECEA;--bg:#F7F5F2;--border:#E5E0D8;--text:#1C1916;}
    html,body{height:100%;}
    body{background:var(--bg);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}
    .split{display:flex;width:100%;min-height:100vh;}
    /* LEFT */
    .left{flex:1;background:linear-gradient(150deg,#1C1916 0%,#2E2420 60%,#3A2E28 100%);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:48px 44px;position:relative;overflow:hidden;}
    .left::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;background:rgba(196,127,43,.08);top:-80px;left:-80px;}
    .left::after{content:'';position:absolute;width:220px;height:220px;border-radius:50%;background:rgba(196,127,43,.06);bottom:-50px;right:-40px;}
    .left-inner{position:relative;z-index:1;text-align:center;max-width:340px;}
    .brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:32px;text-decoration:none;}
    .brand-icon{font-size:26px;}
    .brand-name{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:#fff;}
    .brand-name span{color:var(--gold);}
    .brand-sub{display:block;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.12em;margin-top:2px;}
    .hero-emoji{font-size:72px;display:block;margin-bottom:16px;line-height:1;}
    .left-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:10px;}
    .left-title span{color:var(--gold);}
    .left-sub{font-size:13px;color:#888;line-height:1.7;margin-bottom:26px;}
    .features{display:flex;flex-direction:column;gap:9px;text-align:left;}
    .feat{display:flex;align-items:center;gap:11px;background:rgba(255,255,255,.05);border:1px solid rgba(196,127,43,.15);border-radius:10px;padding:10px 14px;}
    .feat-icon{font-size:16px;flex-shrink:0;}
    .feat-text{font-size:13px;color:#AAA;font-weight:500;}
    /* RIGHT */
    .right{width:460px;flex-shrink:0;background:#fff;display:flex;flex-direction:column;justify-content:center;padding:48px 44px;box-shadow:-4px 0 30px rgba(0,0,0,.12);overflow-y:auto;}
    .role-badge{display:inline-flex;align-items:center;gap:6px;background:#FDF3E3;color:var(--gold);border:1px solid #EDD8B0;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;margin-bottom:16px;}
    .form-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--text);margin-bottom:4px;}
    .form-sub{font-size:13px;color:#8A8078;margin-bottom:22px;line-height:1.5;}
    .alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;font-weight:500;background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .input-group{margin-bottom:15px;}
    .input-label{display:block;font-size:11px;font-weight:700;color:#4A4540;letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:14px;color:#C0B8AE;pointer-events:none;}
    .form-input{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:11px 14px 11px 40px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .form-input::placeholder{color:#C8C0B8;}
    .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:15px;color:#C0B8AE;}
    .eye-btn:hover{color:var(--gold);}
    .submit-btn{width:100%;background:var(--gold);color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;transition:all .2s;margin-top:6px;}
    .submit-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 6px 18px rgba(196,127,43,.3);}
    .back-link{text-align:center;margin-top:18px;font-size:12px;color:#8A8078;}
    .back-link a{color:var(--gold);font-weight:600;text-decoration:none;}
    .back-link a:hover{text-decoration:underline;}
  </style>
</head>
<body>
<div class="split">
  <div class="left">
    <div class="left-inner">
      <a class="brand" href="index.php">
        <span class="brand-icon">🏋️</span>
        <div><span class="brand-name">Kinetic<span>Borrow</span></span><span class="brand-sub">Admin Panel</span></div>
      </a>
      <span class="hero-emoji">⚙️</span>
      <h1 class="left-title">Manage Your<br><span>Rental Business</span></h1>
      <p class="left-sub">Full control over equipment, customers, rentals, and promotions — all in one place.</p>
      <div class="features">
        <div class="feat"><span class="feat-icon">📊</span><span class="feat-text">Real-time dashboard & reports</span></div>
        <div class="feat"><span class="feat-icon">🏋️</span><span class="feat-text">Equipment & inventory management</span></div>
        <div class="feat"><span class="feat-icon">👥</span><span class="feat-text">Customer & order management</span></div>
        <div class="feat"><span class="feat-icon">🪪</span><span class="feat-text">AI ID verification control</span></div>
        <div class="feat"><span class="feat-icon">🎁</span><span class="feat-text">Promotions & loyalty programs</span></div>
        <div class="feat"><span class="feat-icon">🚨</span><span class="feat-text">Incident reports & blocklist</span></div>
      </div>
    </div>
  </div>
  <div class="right">
    <div class="role-badge">⚙️ Administrator</div>
    <h2 class="form-title">Admin Login</h2>
    <p class="form-sub">Sign in to access the KineticBorrow admin panel.</p>
    <?php if($error): ?><div class="alert">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="admin_login.php">
      <div class="input-group">
        <label class="input-label">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input class="form-input" type="email" name="email" placeholder="you@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
        </div>
      </div>
      <div class="input-group">
        <label class="input-label">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input class="form-input" type="password" name="password" id="pass" placeholder="Enter admin password" required/>
          <button class="eye-btn" type="button" onclick="const i=document.getElementById('pass');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁️':'🙈'">👁️</button>
        </div>
      </div>
      <button type="submit" class="submit-btn">Sign In to Admin Panel →</button>
    </form>
    <div class="back-link">← <a href="index.php">Back to main site</a></div>
  </div>
</div>
</body>
</html>
