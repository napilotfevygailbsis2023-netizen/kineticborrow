<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email    = trim($conn->real_escape_string($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.'; $active_tab = 'login';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_blocked'] ?? 0) {
                    $error = 'Your account has been suspended. Please contact support.'; $active_tab = 'login';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user']    = $user;
                    header('Location: dashboard.php'); exit();
                }
            } else {
                $error = 'Invalid email or password.'; $active_tab = 'login';
            }
        }
    } elseif ($_POST['action'] === 'register') {
        $active_tab = 'register';
        $fname    = trim($conn->real_escape_string($_POST['first_name'] ?? ''));
        $lname    = trim($conn->real_escape_string($_POST['last_name']  ?? ''));
        $email    = trim($conn->real_escape_string($_POST['email']      ?? ''));
        $phone    = trim($conn->real_escape_string($_POST['phone']      ?? ''));
        $password = $_POST['password'] ?? '';
        $id_type  = 'regular';
        if (empty($fname)||empty($lname)||empty($email)||empty($phone)||empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param('s', $email); $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, id_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssss', $fname, $lname, $email, $phone, $hashed, $id_type);
                if ($stmt->execute()) { $success = 'Account created! You can now log in.'; $active_tab = 'login'; }
                else { $error = 'Something went wrong. Please try again.'; }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KineticBorrow — <?= $active_tab === 'register' ? 'Create Account' : 'Log In' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;--green:#2E8B57;--green-bg:#EAF6EE;--red:#C0392B;--red-bg:#FDECEA;--bg:#F7F5F2;--border:#E5E0D8;--muted:#8A8078;--text:#1C1916;--text2:#4A4540;}
    html,body{height:100%;}
    body{background:var(--bg);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}
    .split{display:flex;width:100%;min-height:100vh;}
    /* LEFT */
    .left{flex:1;background:linear-gradient(150deg,#FDF0DC,#F5DDB0);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:48px 40px;position:relative;overflow:hidden;}
    .left::before{content:'';position:absolute;width:380px;height:380px;border-radius:50%;background:rgba(196,127,43,.12);top:-100px;left:-80px;pointer-events:none;}
    .left::after{content:'';position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(196,127,43,.08);bottom:-60px;right:-50px;pointer-events:none;}
    .left-inner{position:relative;z-index:1;text-align:center;max-width:360px;}
    .brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:32px;text-decoration:none;}
    .brand-icon{font-size:28px;}
    .brand-name{font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);}
    .brand-name span{color:var(--gold);}
    .hero-emoji{font-size:76px;display:block;margin-bottom:18px;line-height:1;}
    .left-title{font-family:'Playfair Display',serif;font-size:30px;font-weight:800;color:var(--text);line-height:1.2;margin-bottom:10px;}
    .left-title span{color:var(--gold);}
    .left-sub{font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:26px;}
    .features{display:flex;flex-direction:column;gap:9px;text-align:left;}
    .feat{display:flex;align-items:center;gap:11px;background:rgba(255,255,255,.55);border:1px solid rgba(196,127,43,.18);border-radius:10px;padding:10px 14px;}
    .feat-icon{font-size:17px;flex-shrink:0;}
    .feat-text{font-size:13px;color:var(--text2);font-weight:500;line-height:1.3;}
    .feat-text small{display:block;font-size:11px;color:var(--muted);font-weight:400;margin-top:1px;}
    /* RIGHT */
    .right{width:460px;flex-shrink:0;background:#fff;display:flex;flex-direction:column;justify-content:center;padding:36px 44px;box-shadow:-4px 0 30px rgba(0,0,0,.07);overflow-y:auto;}
    .tab-row{display:flex;background:var(--bg);border-radius:10px;padding:4px;margin-bottom:22px;}
    .tab-btn{flex:1;background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;padding:9px;border-radius:8px;color:var(--muted);transition:all .2s;}
    .tab-btn.active{background:#fff;color:var(--text);font-weight:700;box-shadow:0 1px 6px rgba(0,0,0,.08);}
    .form-section{display:none;flex-direction:column;}
    .form-section.active{display:flex;}
    .form-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px;}
    .form-sub{font-size:13px;color:var(--muted);margin-bottom:18px;line-height:1.5;}
    .alert{padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px;font-weight:500;}
    .alert-error{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .input-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .input-group{margin-bottom:13px;}
    .input-label{display:block;font-size:11px;font-weight:700;color:var(--text2);letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:14px;color:#C0B8AE;pointer-events:none;}
    .form-input{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:10px 14px 10px 40px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .form-input::placeholder{color:#C8C0B8;}
    .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:15px;color:#C0B8AE;}
    .eye-btn:hover{color:var(--gold);}
    .strength-wrap{margin-top:5px;}
    .strength-bg{height:4px;background:var(--border);border-radius:4px;overflow:hidden;}
    .strength-bar{height:100%;border-radius:4px;transition:all .3s;width:0;}
    .strength-lbl{font-size:11px;margin-top:3px;}
    .forgot-row{display:flex;justify-content:flex-end;margin:-4px 0 12px;}
    .forgot-link{font-size:12px;color:var(--gold);font-weight:600;text-decoration:none;}
    .check-row{display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;}
    .custom-check{width:17px;height:17px;min-width:17px;border:1.5px solid var(--border);border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;background:#fff;margin-top:1px;}
    .custom-check.on{background:var(--gold);border-color:var(--gold);}
    .custom-check.on::after{content:'✓';font-size:10px;color:#fff;font-weight:700;}
    .check-lbl{font-size:12px;color:var(--muted);line-height:1.5;cursor:pointer;user-select:none;}
    .check-lbl a{color:var(--gold);font-weight:600;}
    .submit-btn{width:100%;background:var(--gold);color:#fff;border:none;padding:12px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;transition:all .2s;}
    .submit-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 6px 16px rgba(196,127,43,.28);}
    .divider{display:flex;align-items:center;gap:10px;margin:13px 0;color:var(--muted);font-size:12px;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .social-row{display:flex;gap:10px;}
    .social-btn{flex:1;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:9px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;color:var(--text2);display:flex;align-items:center;justify-content:center;gap:7px;transition:all .2s;}
    .social-btn:hover{border-color:var(--gold);background:var(--gold-bg);color:var(--gold);}
    .switch-txt{text-align:center;font-size:13px;color:var(--muted);margin-top:14px;}
    .switch-lnk{color:var(--gold);font-weight:700;cursor:pointer;}
    .footer-note{margin-top:18px;padding-top:14px;border-top:1px solid var(--border);text-align:center;font-size:11px;color:#C0B8AE;}
  </style>
</head>
<body>
<div class="split">
  <div class="left">
    <div class="left-inner">
      <a class="brand" href="index.php"><span class="brand-icon">🏋️</span><span class="brand-name">Kinetic<span>Borrow</span></span></a>
      <span class="hero-emoji">🏆</span>
      <h1 class="left-title">Rent. Play.<br><span>Return. Repeat.</span></h1>
      <p class="left-sub">Your one-stop platform for renting premium sports equipment. Explore gear, book in minutes, and hit the field.</p>
      <div class="features">
        <div class="feat"><span class="feat-icon">🚵</span><div class="feat-text">Wide Equipment Selection<small>Bikes, rackets, kayaks, and more</small></div></div>
        <div class="feat"><span class="feat-icon">🪪</span><div class="feat-text">ID-Verified Discounts<small>Student, Senior, and PWD rates applied</small></div></div>
        <div class="feat"><span class="feat-icon">⭐</span><div class="feat-text">Loyalty Rewards Program<small>Earn points on every rental</small></div></div>
        <div class="feat"><span class="feat-icon">🛡️</span><div class="feat-text">Equipment Safety Tracking<small>Verified renters protect our gear</small></div></div>
      </div>
    </div>
  </div>
  <div class="right">
    <div class="tab-row">
      <button class="tab-btn <?= $active_tab==='login'?'active':'' ?>" onclick="switchTab('login')">Log In</button>
      <button class="tab-btn <?= $active_tab==='register'?'active':'' ?>" onclick="switchTab('register')">Create Account</button>
    </div>
    <?php if($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- LOGIN -->
    <div class="form-section <?= $active_tab==='login'?'active':'' ?>" id="section-login">
      <h2 class="form-title">Welcome back!</h2>
      <p class="form-sub">Log in to your KineticBorrow account to manage your rentals.</p>
      <form method="POST" action="login.php">
        <input type="hidden" name="action" value="login"/>
        <div class="input-group">
          <label class="input-label">Email Address</label>
          <div class="input-wrap"><span class="input-icon">✉️</span><input class="form-input" type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/></div>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <div class="input-wrap"><span class="input-icon">🔒</span><input class="form-input" type="password" name="password" id="login-pass" placeholder="Enter your password" required/><button class="eye-btn" type="button" onclick="togglePass('login-pass',this)">👁️</button></div>
        </div>
        <div class="forgot-row"><a class="forgot-link" href="#">Forgot password?</a></div>
        <div class="check-row">
          <div class="custom-check" id="rem-chk" onclick="this.classList.toggle('on')"></div>
          <span class="check-lbl" onclick="document.getElementById('rem-chk').classList.toggle('on')">Remember me for 30 days</span>
        </div>
        <button type="submit" class="submit-btn">Log In →</button>
      </form>
      <div class="divider">or continue with</div>
      <div class="social-row">
        <button class="social-btn" type="button">🌐 Google</button>
        <button class="social-btn" type="button">📘 Facebook</button>
      </div>
      <p class="switch-txt">New here? <span class="switch-lnk" onclick="switchTab('register')">Create a free account</span></p>
    </div>

    <!-- REGISTER -->
    <div class="form-section <?= $active_tab==='register'?'active':'' ?>" id="section-register">
      <h2 class="form-title">Join KineticBorrow</h2>
      <p class="form-sub">Create your account and start renting sports equipment today.</p>
      <form method="POST" action="login.php?tab=register">
        <input type="hidden" name="action" value="register"/>
        <div class="input-row">
          <div class="input-group">
            <label class="input-label">First Name</label>
            <div class="input-wrap"><span class="input-icon">👤</span><input class="form-input" type="text" name="first_name" placeholder="Juan" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required/></div>
          </div>
          <div class="input-group">
            <label class="input-label">Last Name</label>
            <div class="input-wrap"><span class="input-icon">👤</span><input class="form-input" type="text" name="last_name" placeholder="Dela Cruz" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required/></div>
          </div>
        </div>
        <div class="input-group">
          <label class="input-label">Email Address</label>
          <div class="input-wrap"><span class="input-icon">✉️</span><input class="form-input" type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/></div>
        </div>
        <div class="input-group">
          <label class="input-label">Phone Number</label>
          <div class="input-wrap"><span class="input-icon">📱</span><input class="form-input" type="tel" name="phone" placeholder="09XX XXX XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required/></div>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <div class="input-wrap"><span class="input-icon">🔒</span><input class="form-input" type="password" name="password" id="reg-pass" placeholder="Create a strong password" oninput="checkStrength()" required/><button class="eye-btn" type="button" onclick="togglePass('reg-pass',this)">👁️</button></div>
          <div class="strength-wrap"><div class="strength-bg"><div class="strength-bar" id="sbar"></div></div><p class="strength-lbl" id="slbl"></p></div>
        </div>
        <div class="check-row">
          <div class="custom-check" id="terms-chk" onclick="this.classList.toggle('on')"></div>
          <p class="check-lbl" onclick="document.getElementById('terms-chk').classList.toggle('on')">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> of KineticBorrow.</p>
        </div>
        <button type="submit" class="submit-btn" onclick="return checkTerms()">Create Account →</button>
      </form>
      <p class="switch-txt">Already have an account? <span class="switch-lnk" onclick="switchTab('login')">Log in here</span></p>
    </div>

    <div class="footer-note">© <?= date('Y') ?> KineticBorrow · University of Caloocan City</div>
  </div>
</div>
<script>
function switchTab(tab){
  document.querySelectorAll('.tab-btn').forEach((b,i)=>b.classList.toggle('active',(tab==='login'&&i===0)||(tab==='register'&&i===1)));
  document.querySelectorAll('.form-section').forEach(s=>s.classList.remove('active'));
  document.getElementById('section-'+tab).classList.add('active');
}
function togglePass(id,btn){const el=document.getElementById(id);el.type=el.type==='password'?'text':'password';btn.textContent=el.type==='password'?'👁️':'🙈';}
function checkTerms(){if(!document.getElementById('terms-chk').classList.contains('on')){alert('Please accept the Terms of Service.');return false;}return true;}
function checkStrength(){
  const v=document.getElementById('reg-pass').value;
  const bar=document.getElementById('sbar'),lbl=document.getElementById('slbl');
  if(!v){bar.style.width='0';lbl.textContent='';return;}
  let s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const l=[{w:'20%',c:'#E05252',t:'😬 Too weak'},{w:'40%',c:'#E07C35',t:'🤔 Weak'},{w:'60%',c:'#D6A252',t:'😐 Fair'},{w:'80%',c:'#7EC87A',t:'😊 Good'},{w:'100%',c:'#2E8B57',t:'💪 Strong'}][Math.min(s-1,4)]||{w:'10%',c:'#E05252',t:'😬 Too weak'};
  bar.style.width=l.w;bar.style.background=l.c;lbl.textContent=l.t;lbl.style.color=l.c;
}
</script>
</body>
</html>
