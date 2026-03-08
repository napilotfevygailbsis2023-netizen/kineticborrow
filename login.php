<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';
$error   = '';
$success = '';

// ── HANDLE LOGIN ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'login') {
        $email    = trim($conn->real_escape_string($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
            $active_tab = 'login';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user']      = $user;
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or password. Please try again.';
                $active_tab = 'login';
            }
        }
    }

    // ── HANDLE REGISTER ────────────────────────────────────────────
    elseif ($_POST['action'] === 'register') {
        $active_tab = 'register';
        $fname    = trim($conn->real_escape_string($_POST['first_name'] ?? ''));
        $lname    = trim($conn->real_escape_string($_POST['last_name']  ?? ''));
        $email    = trim($conn->real_escape_string($_POST['email']      ?? ''));
        $phone    = trim($conn->real_escape_string($_POST['phone']      ?? ''));
        $password = $_POST['password'] ?? '';
        $id_type  = in_array($_POST['id_type'] ?? '', ['student','senior','pwd','regular']) ? $_POST['id_type'] : 'regular';

        if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param('s', $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, id_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssss', $fname, $lname, $email, $phone, $hashed, $id_type);
                if ($stmt->execute()) {
                    $success    = 'Account created! You can now log in.';
                    $active_tab = 'login';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,800;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;
      --green:#2E8B57;--green-bg:#EAF6EE;
      --red:#C0392B;--red-bg:#FDECEA;
      --bg:#F7F5F2;--border:#E5E0D8;--border2:#D0C8BC;
      --muted:#8A8078;--text:#1C1916;--text2:#4A4540;
    }
    body{background:var(--bg);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;flex-direction:column;}
    .split{display:flex;min-height:100vh;}

    /* LEFT */
    .left-panel{flex:1;background:linear-gradient(160deg,#FDF0DC 0%,#FCEBD0 40%,#F5DDB0 100%);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:60px 48px;position:relative;overflow:hidden;}
    .left-panel::before{content:'';position:absolute;width:420px;height:420px;border-radius:50%;background:rgba(196,127,43,.1);top:-100px;left:-100px;}
    .left-panel::after{content:'';position:absolute;width:280px;height:280px;border-radius:50%;background:rgba(196,127,43,.08);bottom:-60px;right:-60px;}
    .left-content{position:relative;z-index:1;text-align:center;max-width:380px;}
    .brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:40px;text-decoration:none;}
    .brand-icon{font-size:32px;}
    .brand-name{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;letter-spacing:-.02em;color:#1C1916;}
    .brand-name span{color:var(--gold);}
    .hero-emoji{font-size:88px;margin-bottom:24px;display:block;line-height:1;}
    .left-title{font-family:'Playfair Display',serif;font-size:32px;font-weight:800;line-height:1.15;color:#1C1916;margin-bottom:14px;}
    .left-title span{color:var(--gold);}
    .left-sub{font-size:14px;color:var(--muted);line-height:1.7;margin-bottom:36px;}
    .features{display:flex;flex-direction:column;gap:12px;text-align:left;}
    .feature-item{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.6);border:1px solid rgba(196,127,43,.2);border-radius:10px;padding:12px 16px;}
    .feature-icon{font-size:20px;flex-shrink:0;}
    .feature-text{font-size:13px;color:var(--text2);font-weight:500;}
    .feature-text span{display:block;font-size:11px;color:var(--muted);font-weight:400;margin-top:1px;}

    /* RIGHT */
    .right-panel{width:460px;flex-shrink:0;background:#fff;display:flex;flex-direction:column;justify-content:center;padding:48px;box-shadow:-4px 0 30px rgba(0,0,0,.06);overflow-y:auto;}
    .tab-row{display:flex;background:var(--bg);border-radius:10px;padding:4px;margin-bottom:28px;}
    .tab-btn{flex:1;background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;padding:10px;border-radius:8px;color:var(--muted);transition:all .2s;}
    .tab-btn.active{background:#fff;color:#1C1916;font-weight:600;box-shadow:0 1px 6px rgba(0,0,0,.08);}
    .form-section{display:none;}
    .form-section.active{display:block;}
    .form-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:#1C1916;margin-bottom:6px;}
    .form-sub{font-size:13px;color:var(--muted);margin-bottom:24px;line-height:1.5;}

    /* ALERTS */
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;font-weight:500;}
    .alert-error{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}

    /* INPUTS */
    .input-group{margin-bottom:16px;}
    .input-label{display:block;font-size:12px;font-weight:600;color:var(--text2);letter-spacing:.04em;text-transform:uppercase;margin-bottom:7px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:15px;color:#C0B8AE;pointer-events:none;}
    .form-input{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:11px 16px 11px 42px;color:#1C1916;font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .form-input::placeholder{color:#C0B8AE;}
    .eye-btn{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:#C0B8AE;}
    .eye-btn:hover{color:var(--gold);}
    .input-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .forgot-row{display:flex;justify-content:flex-end;margin-top:-8px;margin-bottom:16px;}
    .forgot-link{font-size:12px;color:var(--gold);font-weight:500;cursor:pointer;text-decoration:none;}
    .forgot-link:hover{text-decoration:underline;}
    .remember-row{display:flex;align-items:center;gap:8px;margin-bottom:20px;}
    .custom-check{width:18px;height:18px;border:1.5px solid var(--border2);border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;background:#fff;}
    .custom-check.checked{background:var(--gold);border-color:var(--gold);}
    .custom-check.checked::after{content:'✓';font-size:11px;color:#fff;font-weight:700;}
    .remember-label{font-size:13px;color:var(--text2);cursor:pointer;user-select:none;}
    .submit-btn{width:100%;background:var(--gold);color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;transition:all .2s;}
    .submit-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 6px 16px rgba(196,127,43,.3);}
    .or-divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:var(--muted);font-size:12px;}
    .or-divider::before,.or-divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .social-row{display:flex;gap:10px;}
    .social-btn{flex:1;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;color:var(--text2);display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;}
    .social-btn:hover{border-color:var(--gold);background:var(--gold-bg);color:var(--gold);}
    .switch-text{text-align:center;font-size:13px;color:var(--muted);margin-top:20px;}
    .switch-link{color:var(--gold);font-weight:600;cursor:pointer;text-decoration:none;}
    .switch-link:hover{text-decoration:underline;}
    .id-type-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;}
    .id-type-btn{background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:10px 8px;cursor:pointer;text-align:center;transition:all .2s;}
    .id-type-btn:hover,.id-type-btn.active{border-color:var(--gold);background:var(--gold-bg);}
    .id-type-btn.active .id-label{color:var(--gold);}
    .id-emoji{font-size:20px;margin-bottom:3px;}
    .id-label{font-size:11px;font-weight:600;color:var(--text2);}
    .terms-row{display:flex;align-items:flex-start;gap:8px;margin-bottom:18px;}
    .terms-text{font-size:12px;color:var(--muted);line-height:1.5;}
    .terms-text a{color:var(--gold);font-weight:500;}
    .strength-row{margin-top:6px;}
    .strength-bar-bg{height:4px;background:var(--border);border-radius:4px;overflow:hidden;}
    .strength-bar{height:100%;border-radius:4px;transition:all .3s;width:0;}
    .strength-label{font-size:11px;margin-top:4px;}
    .right-footer{margin-top:24px;padding-top:18px;border-top:1px solid var(--border);text-align:center;}
    .right-footer p{font-size:11px;color:#C0B8AE;}
  </style>
</head>
<body>
<div class="split">

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div class="left-content">
      <a class="brand" href="index.php">
        <span class="brand-icon">🏋️</span>
        <span class="brand-name">Kinetic<span>Borrow</span></span>
      </a>
      <span class="hero-emoji">🏆</span>
      <h1 class="left-title">Rent. Play.<br><span>Return. Repeat.</span></h1>
      <p class="left-sub">Your one-stop platform for renting premium sports equipment. Explore gear, book in minutes, and hit the field.</p>
      <div class="features">
        <div class="feature-item">
          <span class="feature-icon">🚵</span>
          <div class="feature-text">Wide Equipment Selection<span>Bikes, rackets, kayaks, and more</span></div>
        </div>
        <div class="feature-item">
          <span class="feature-icon">🪪</span>
          <div class="feature-text">AI-Powered ID Discounts<span>Student, Senior, and PWD rates auto-applied</span></div>
        </div>
        <div class="feature-item">
          <span class="feature-icon">⭐</span>
          <div class="feature-text">Loyalty Rewards Program<span>Earn points on every rental</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="tab-row">
      <button class="tab-btn <?= $active_tab === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Log In</button>
      <button class="tab-btn <?= $active_tab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Create Account</button>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <div class="form-section <?= $active_tab === 'login' ? 'active' : '' ?>" id="section-login">
      <h2 class="form-title">Welcome back!</h2>
      <p class="form-sub">Log in to your KineticBorrow account to manage your rentals.</p>

      <form method="POST" action="login.php">
        <input type="hidden" name="action" value="login"/>

        <div class="input-group">
          <label class="input-label">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input class="form-input" type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input class="form-input" type="password" name="password" id="login-pass" placeholder="Enter your password" required/>
            <button class="eye-btn" type="button" onclick="togglePass('login-pass',this)">👁️</button>
          </div>
        </div>

        <div class="forgot-row"><a class="forgot-link" href="#">Forgot password?</a></div>

        <div class="remember-row">
          <div class="custom-check" id="remember-check" onclick="toggleCheck('remember-check')"></div>
          <span class="remember-label" onclick="toggleCheck('remember-check')">Remember me for 30 days</span>
        </div>

        <button type="submit" class="submit-btn">Log In →</button>
      </form>

      <div class="or-divider">or continue with</div>
      <div class="social-row">
        <button class="social-btn" type="button">🌐 Google</button>
        <button class="social-btn" type="button">📘 Facebook</button>
      </div>
      <p class="switch-text">New here? <a class="switch-link" onclick="switchTab('register')">Create a free account</a></p>
    </div>

    <!-- REGISTER FORM -->
    <div class="form-section <?= $active_tab === 'register' ? 'active' : '' ?>" id="section-register">
      <h2 class="form-title">Join KineticBorrow</h2>
      <p class="form-sub">Create your account and start renting sports equipment today.</p>

      <form method="POST" action="login.php?tab=register">
        <input type="hidden" name="action" value="register"/>

        <div class="input-row">
          <div class="input-group">
            <label class="input-label">First Name</label>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input class="form-input" type="text" name="first_name" placeholder="Juan" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required/>
            </div>
          </div>
          <div class="input-group">
            <label class="input-label">Last Name</label>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input class="form-input" type="text" name="last_name" placeholder="Dela Cruz" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required/>
            </div>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input class="form-input" type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Phone Number</label>
          <div class="input-wrap">
            <span class="input-icon">📱</span>
            <input class="form-input" type="tel" name="phone" placeholder="09XX XXX XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input class="form-input" type="password" name="password" id="reg-pass" placeholder="Create a strong password" oninput="checkStrength()" required/>
            <button class="eye-btn" type="button" onclick="togglePass('reg-pass',this)">👁️</button>
          </div>
          <div class="strength-row" id="strength-row">
            <div class="strength-bar-bg"><div class="strength-bar" id="strength-bar"></div></div>
            <p class="strength-label" id="strength-label"></p>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">ID Type (for discount)</label>
          <div class="id-type-grid">
            <div class="id-type-btn <?= ($_POST['id_type'] ?? '') === 'student' ? 'active' : '' ?>" onclick="selectID(this,'student')">
              <div class="id-emoji">🎓</div><div class="id-label">Student ID</div>
            </div>
            <div class="id-type-btn <?= ($_POST['id_type'] ?? '') === 'senior' ? 'active' : '' ?>" onclick="selectID(this,'senior')">
              <div class="id-emoji">👴</div><div class="id-label">Senior Citizen</div>
            </div>
            <div class="id-type-btn <?= ($_POST['id_type'] ?? '') === 'pwd' ? 'active' : '' ?>" onclick="selectID(this,'pwd')">
              <div class="id-emoji">♿</div><div class="id-label">PWD ID</div>
            </div>
            <div class="id-type-btn <?= ($_POST['id_type'] ?? 'regular') === 'regular' ? 'active' : '' ?>" onclick="selectID(this,'none')">
              <div class="id-emoji">🪪</div><div class="id-label">Regular / None</div>
            </div>
          </div>
          <input type="hidden" name="id_type" id="id-type-val" value="<?= htmlspecialchars($_POST['id_type'] ?? 'regular') ?>"/>
        </div>

        <div class="terms-row">
          <div class="custom-check" id="terms-check" onclick="toggleCheck('terms-check')"></div>
          <p class="terms-text">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> of KineticBorrow.</p>
        </div>

        <button type="submit" class="submit-btn" onclick="return checkTerms()">Create Account →</button>
      </form>

      <p class="switch-text">Already have an account? <a class="switch-link" onclick="switchTab('login')">Log in here</a></p>
    </div>

    <div class="right-footer">
      <p>© <?= date('Y') ?> KineticBorrow · University of Caloocan City</p>
    </div>
  </div>
</div>

<script>
  // Auto-open correct tab based on PHP
  const defaultTab = '<?= $active_tab ?>';

  function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
    document.getElementById('section-' + tab).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => {
      if (b.textContent.toLowerCase().includes(tab === 'login' ? 'log' : 'create')) b.classList.add('active');
    });
  }

  function togglePass(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁️' : '🙈';
  }

  function toggleCheck(id) {
    document.getElementById(id).classList.toggle('checked');
  }

  function checkTerms() {
    if (!document.getElementById('terms-check').classList.contains('checked')) {
      alert('Please accept the Terms of Service to continue.');
      return false;
    }
    return true;
  }

  function selectID(el, type) {
    document.querySelectorAll('.id-type-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('id-type-val').value = type === 'none' ? 'regular' : type;
  }

  function checkStrength() {
    const val = document.getElementById('reg-pass').value;
    const bar = document.getElementById('strength-bar');
    const lbl = document.getElementById('strength-label');
    if (!val) { bar.style.width='0'; lbl.textContent=''; return; }
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
      {w:'20%',color:'#E05252',label:'😬 Too weak'},
      {w:'40%',color:'#E07C35',label:'🤔 Weak'},
      {w:'60%',color:'#D6A252',label:'😐 Fair'},
      {w:'80%',color:'#7EC87A',label:'😊 Good'},
      {w:'100%',color:'#2E8B57',label:'💪 Strong'},
    ];
    const lvl = levels[Math.min(score-1,4)] || levels[0];
    bar.style.width=lvl.w; bar.style.background=lvl.color;
    lbl.textContent=lvl.label; lbl.style.color=lvl.color;
  }
</script>
</body>
</html>
