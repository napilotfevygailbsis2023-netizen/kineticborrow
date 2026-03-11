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
        $stmt->bind_param('s', $email); $stmt->execute();
        $handler = $stmt->get_result()->fetch_assoc();
        if ($handler && password_verify($password, $handler['password'])) {
            $_SESSION['handler_id'] = $handler['id'];
            $_SESSION['handler']    = $handler;
            header('Location: handler_dashboard.php'); exit();
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
    :root{--teal:#0E7C86;--teal-dk:#095E66;--teal-lt:#5AABB2;--teal-bg:#E8F6F7;--gold:#C47F2B;--red:#C0392B;--red-bg:#FDECEA;--bg:#F2F7F7;--border:#C8DFE1;--text:#0D2B2D;}
    html,body{height:100%;}
    body{background:var(--bg);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}
    .split{display:flex;width:100%;min-height:100vh;}
    /* LEFT */
    .left{flex:1;background:linear-gradient(150deg,#0D2B2D 0%,#0E4A52 60%,#0E5C66 100%);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:48px 44px;position:relative;overflow:hidden;}
    .left::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;background:rgba(90,171,178,.08);top:-80px;left:-80px;}
    .left::after{content:'';position:absolute;width:220px;height:220px;border-radius:50%;background:rgba(90,171,178,.06);bottom:-50px;right:-40px;}
    .left-inner{position:relative;z-index:1;text-align:center;max-width:340px;}
    .brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:32px;text-decoration:none;}
    .brand-icon{font-size:26px;}
    .brand-name{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:#fff;}
    .brand-name span{color:var(--gold);}
    .brand-sub{display:block;font-size:10px;color:var(--teal-lt);text-transform:uppercase;letter-spacing:.12em;margin-top:2px;}
    .hero-emoji{font-size:72px;display:block;margin-bottom:16px;line-height:1;}
    .left-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:10px;}
    .left-title span{color:var(--teal-lt);}
    .left-sub{font-size:13px;color:#7ABFC5;line-height:1.7;margin-bottom:26px;}
    .features{display:flex;flex-direction:column;gap:9px;text-align:left;}
    .feat{display:flex;align-items:center;gap:11px;background:rgba(255,255,255,.05);border:1px solid rgba(90,171,178,.2);border-radius:10px;padding:10px 14px;}
    .feat-icon{font-size:16px;flex-shrink:0;}
    .feat-text{font-size:13px;color:#9ACFD5;font-weight:500;}
    /* RIGHT */
    .right{width:460px;flex-shrink:0;background:#fff;display:flex;flex-direction:column;justify-content:center;padding:48px 44px;box-shadow:-4px 0 30px rgba(0,0,0,.1);overflow-y:auto;}
    .role-badge{display:inline-flex;align-items:center;gap:6px;background:var(--teal-bg);color:var(--teal);border:1px solid #B0D8DB;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;margin-bottom:16px;}
    .form-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--text);margin-bottom:4px;}
    .form-sub{font-size:13px;color:#6A8E90;margin-bottom:22px;line-height:1.5;}
    .alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;font-weight:500;background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .input-group{margin-bottom:15px;}
    .input-label{display:block;font-size:11px;font-weight:700;color:#2A5A5C;letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:14px;color:#A0C4C6;pointer-events:none;}
    .form-input{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:11px 14px 11px 40px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(14,124,134,.1);}
    .form-input::placeholder{color:#A8C8CA;}
    .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:15px;color:#A0C4C6;}
    .eye-btn:hover{color:var(--teal);}
    .submit-btn{width:100%;background:linear-gradient(135deg,var(--teal),var(--teal-dk));color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;transition:all .2s;margin-top:6px;}
    .submit-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(14,124,134,.3);}
    .back-link{text-align:center;margin-top:18px;font-size:12px;color:#6A8E90;}
    .back-link a{color:var(--teal);font-weight:600;text-decoration:none;}
    .back-link a:hover{text-decoration:underline;}
  </style>
</head>
<body>
<div class="split">
  <div class="left">
    <div class="left-inner">
      <a class="brand" href="index.php">
        <span class="brand-icon">🏋️</span>
        <div><span class="brand-name">Kinetic<span>Borrow</span></span><span class="brand-sub">Handler Portal</span></div>
      </a>
      <span class="hero-emoji">📦</span>
      <h1 class="left-title">Handle Equipment.<br><span>Keep Things Moving.</span></h1>
      <p class="left-sub">Your operations hub for check-outs, returns, condition logging, and incident reporting.</p>
      <div class="features">
        <div class="feat"><span class="feat-icon">📋</span><span class="feat-text">View daily reservation queue</span></div>
        <div class="feat"><span class="feat-icon">✅</span><span class="feat-text">Process check-out & confirm release</span></div>
        <div class="feat"><span class="feat-icon">📦</span><span class="feat-text">Receive returns & update availability</span></div>
        <div class="feat"><span class="feat-icon">🔍</span><span class="feat-text">Log equipment condition on check-in/out</span></div>
        <div class="feat"><span class="feat-icon">🚨</span><span class="feat-text">Report damage, loss, or incidents</span></div>
        <div class="feat"><span class="feat-icon">🚫</span><span class="feat-text">Check blocklist before releasing</span></div>
      </div>
    </div>
  </div>
  <div class="right">
    <div class="role-badge">🔧 Equipment Handler</div>
    <h2 class="form-title">Handler Login</h2>
    <p class="form-sub">Sign in to access your equipment operations portal.</p>
    <?php if($error): ?><div class="alert">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
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
