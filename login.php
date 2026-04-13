<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

// Safe Google OAuth include — won't crash if file is missing
$google_auth_url = '#';
if (file_exists(__DIR__ . '/includes/google_oauth.php')) {
    require_once 'includes/google_oauth.php';
    if (function_exists('getGoogleAuthURL')) {
        $google_auth_url = getGoogleAuthURL();
    }
}

if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';
$error   = '';
$success = '';

$google_errors = [
    'cancelled'    => 'Google sign-in was cancelled.',
    'token_failed' => 'Google sign-in failed. Please try again.',
    'no_email'     => 'Could not retrieve your Google email. Please try again.',
    'blocked'      => 'Your account has been suspended. Please contact support.',
    'no_code'      => 'Google sign-in failed. Please try again.',
];
if (!empty($_GET['google_error']) && isset($google_errors[$_GET['google_error']])) {
    $error = $google_errors[$_GET['google_error']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'login') {
        $active_tab = 'login';
        $email    = trim($conn->real_escape_string($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_blocked'] ?? 0) {
                    $error = 'Your account has been suspended. Please contact support.';
                } elseif (!($user['email_verified'] ?? 0)) {
                    $code = generateAndSaveCode($conn, $user['id']);
                    sendVerificationCode($user['email'], $user['first_name'], $code);
                    $_SESSION['verify_user_id'] = $user['id'];
                    $_SESSION['verify_email']   = $user['email'];
                    $_SESSION['verify_name']    = $user['first_name'];
                    header('Location: email_verify.php'); exit();
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user']    = $user;
                    header('Location: dashboard.php'); exit();
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }

    } elseif ($_POST['action'] === 'register') {
        $active_tab = 'register';
        $fname    = trim($conn->real_escape_string($_POST['first_name']  ?? ''));
        $mname    = trim($conn->real_escape_string($_POST['middle_name'] ?? ''));
        $lname    = trim($conn->real_escape_string($_POST['last_name']   ?? ''));
        $suffix   = trim($conn->real_escape_string($_POST['suffix']      ?? ''));
        $email    = trim($conn->real_escape_string($_POST['email']       ?? ''));
        $phone    = trim($conn->real_escape_string($_POST['phone']       ?? ''));
        $street   = trim($conn->real_escape_string($_POST['street']      ?? ''));
        $city     = trim($conn->real_escape_string($_POST['city']        ?? ''));
        $zip      = trim($conn->real_escape_string($_POST['zip']         ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param('s', $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, id_type) VALUES (?, ?, ?, ?, ?, 'regular')");
                $stmt->bind_param('sssss', $fname, $lname, $email, $phone, $hashed);
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $code   = generateAndSaveCode($conn, $new_id);
                    sendVerificationCode($email, $fname, $code);
                    $_SESSION['verify_user_id'] = $new_id;
                    $_SESSION['verify_email']   = $email;
                    $_SESSION['verify_name']    = $fname;
                    header('Location: email_verify.php'); exit();
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

// Carry POST values back for register tab on error
$pv = function($k) { return htmlspecialchars($_POST[$k] ?? ''); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KineticBorrow — <?= $active_tab === 'register' ? 'Register' : 'Log In' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --gold: #C47F2B; --gold-lt: #D9952E; --gold-bg: #FDF3E3;
      --green: #2E8B57; --green-bg: #EAF6EE;
      --red: #C0392B; --red-bg: #FDECEA;
      --bg: #F7F5F2; --border: #E5E0D8;
      --muted: #8A8078; --text: #1C1916; --text2: #4A4540;
    }
    html, body { height: 100%; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); display: flex; min-height: 100vh; }

    /* ── LAYOUT ── */
    .split { display: flex; width: 100%; min-height: 100vh; }

    /* LEFT */
    .left {
      flex: 0 0 48%;
      background: linear-gradient(150deg, #FDF0DC, #F5DDB0);
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      padding: 48px 40px; position: relative; overflow: hidden;
    }
    .left::before { content:''; position:absolute; width:380px; height:380px; border-radius:50%; background:rgba(196,127,43,.12); top:-100px; left:-80px; }
    .left::after  { content:''; position:absolute; width:260px; height:260px; border-radius:50%; background:rgba(196,127,43,.08); bottom:-60px; right:-50px; }
    .left-inner { position:relative; z-index:1; text-align:center; max-width:380px; }
    .brand { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:28px; text-decoration:none; }
    .brand-name { font-family:'Playfair Display',serif; font-size:26px; font-weight:800; color:var(--text); }
    .brand-name span { color:var(--gold); }
    .hero-emoji { font-size:76px; display:block; margin-bottom:16px; line-height:1; }
    .left-title { font-family:'Playfair Display',serif; font-size:30px; font-weight:800; color:var(--text); line-height:1.2; margin-bottom:10px; }
    .left-title span { color:var(--gold); }
    .left-sub { font-size:13px; color:var(--muted); line-height:1.7; margin-bottom:26px; }
    .features { display:flex; flex-direction:column; gap:9px; text-align:left; }
    .feat { display:flex; align-items:center; gap:11px; background:rgba(255,255,255,.55); border:1px solid rgba(196,127,43,.18); border-radius:10px; padding:10px 14px; }
    .feat-icon { font-size:18px; flex-shrink:0; }
    .feat-text { font-size:13px; color:var(--text2); font-weight:500; line-height:1.3; }
    .feat-text small { display:block; font-size:11px; color:var(--muted); font-weight:400; margin-top:1px; }

    /* RIGHT */
    .right {
      flex: 1;
      background: #fff;
      display: flex; flex-direction: column; justify-content: center;
      padding: 40px 52px;
      box-shadow: -4px 0 30px rgba(0,0,0,.07);
      overflow-y: auto;
    }
    .right-inner { max-width: 500px; width: 100%; margin: 0 auto; }

    /* TABS */
    .tab-row { display:flex; background:var(--bg); border-radius:10px; padding:4px; margin-bottom:28px; }
    .tab-btn {
      flex:1; background:none; border:none; cursor:pointer;
      font-family:'DM Sans',sans-serif; font-size:14px; font-weight:500;
      padding:10px; border-radius:8px; color:var(--muted); transition:all .2s;
    }
    .tab-btn.active { background:#fff; color:var(--text); font-weight:700; box-shadow:0 1px 6px rgba(0,0,0,.08); }

    /* SECTIONS — key fix: use visibility + height trick so PHP class drives it, JS toggles class */
    .form-section { display:none; }
    .form-section.active { display:block; }

    /* FORM ELEMENTS */
    .form-title { font-family:'Playfair Display',serif; font-size:24px; font-weight:800; color:var(--text); margin-bottom:4px; }
    .form-sub { font-size:13px; color:var(--muted); margin-bottom:20px; line-height:1.5; }
    .alert { padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px; font-weight:500; }
    .alert-error  { background:var(--red-bg);  color:var(--red);   border:1px solid #F5C6C2; }
    .alert-success{ background:var(--green-bg); color:var(--green); border:1px solid #C0E0CC; }
    .input-group { margin-bottom:13px; }
    .input-label { display:block; font-size:11px; font-weight:700; color:var(--text2); letter-spacing:.05em; text-transform:uppercase; margin-bottom:5px; }
    .input-wrap { position:relative; display:flex; align-items:center; }
    .input-icon { position:absolute; left:13px; font-size:14px; color:#C0B8AE; pointer-events:none; z-index:1; }
    .form-input {
      width:100%; background:var(--bg); border:1.5px solid var(--border);
      border-radius:10px; padding:11px 14px 11px 40px;
      color:var(--text); font-family:'DM Sans',sans-serif; font-size:14px;
      outline:none; transition:all .2s;
    }
    .form-input.no-icon { padding-left:14px; }
    .form-input:focus { border-color:var(--gold); background:#fff; box-shadow:0 0 0 3px rgba(196,127,43,.1); }
    .form-input::placeholder { color:#C8C0B8; }
    select.form-input { cursor:pointer; }
    .eye-btn { position:absolute; right:12px; background:none; border:none; cursor:pointer; font-size:15px; color:#C0B8AE; padding:0; line-height:1; }
    .eye-btn:hover { color:var(--gold); }

    /* GRIDS */
    .g4 { display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:10px; margin-bottom:13px; }
    .g2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:13px; }
    .g3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:13px; }
    .g4 .input-group, .g2 .input-group, .g3 .input-group { margin-bottom:0; }

    /* PHONE */
    .phone-wrap { display:flex; gap:6px; }
    .phone-prefix { background:var(--bg); border:1.5px solid var(--border); border-radius:10px; padding:11px 12px; font-size:14px; font-weight:600; color:var(--gold); white-space:nowrap; display:flex; align-items:center; flex-shrink:0; }

    /* PASSWORD STRENGTH */
    .strength-wrap { margin-top:5px; }
    .strength-bg { height:4px; background:var(--border); border-radius:4px; overflow:hidden; }
    .strength-bar { height:100%; border-radius:4px; transition:all .3s; width:0; }
    .strength-lbl { font-size:11px; margin-top:3px; color:var(--muted); }

    /* MISC */
    .forgot-row { display:flex; justify-content:flex-end; margin:-4px 0 14px; }
    .forgot-link { font-size:12px; color:var(--gold); font-weight:600; text-decoration:none; }
    .forgot-link:hover { text-decoration:underline; }
    .check-row { display:flex; align-items:flex-start; gap:8px; margin-bottom:14px; }
    .custom-check { width:17px; height:17px; min-width:17px; border:1.5px solid var(--border); border-radius:5px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; background:#fff; margin-top:2px; flex-shrink:0; }
    .custom-check.on { background:var(--gold); border-color:var(--gold); }
    .custom-check.on::after { content:'✓'; font-size:10px; color:#fff; font-weight:700; }
    .check-lbl { font-size:12px; color:var(--muted); line-height:1.5; cursor:pointer; user-select:none; }
    .check-lbl a { color:var(--gold); font-weight:600; text-decoration:underline; }
    .submit-btn { width:100%; background:var(--gold); color:#fff; border:none; padding:13px; border-radius:10px; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:15px; font-weight:700; transition:all .2s; margin-bottom:14px; }
    .submit-btn:hover { background:var(--gold-lt); transform:translateY(-1px); box-shadow:0 6px 16px rgba(196,127,43,.28); }
    .divider { display:flex; align-items:center; gap:10px; margin:4px 0 12px; color:var(--muted); font-size:12px; }
    .divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--border); }
    .social-btn { width:100%; background:var(--bg); border:1.5px solid var(--border); border-radius:10px; padding:10px; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:500; color:var(--text2); display:flex; align-items:center; justify-content:center; gap:8px; transition:all .2s; text-decoration:none; margin-bottom:12px; }
    .social-btn:hover { border-color:var(--gold); background:var(--gold-bg); color:var(--gold); }
    .switch-txt { text-align:center; font-size:13px; color:var(--muted); margin-bottom:4px; }
    .switch-lnk { color:var(--gold); font-weight:700; cursor:pointer; }
    .switch-lnk:hover { text-decoration:underline; }
    .footer-note { margin-top:18px; padding-top:14px; border-top:1px solid var(--border); text-align:center; font-size:11px; color:#C0B8AE; }
  </style>
</head>
<body>
<div class="split">

  <!-- ═══ LEFT ═══ -->
  <div class="left">
    <div class="left-inner">
      <a class="brand" href="index.php">
        <span style="font-size:28px">🏋️</span>
        <span class="brand-name">Kinetic<span>Borrow</span></span>
      </a>
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

  <!-- ═══ RIGHT ═══ -->
  <div class="right">
    <div class="right-inner">

      <!-- TAB BUTTONS — clicking navigates via URL so it works even without JS -->
      <div class="tab-row">
        <button type="button"
          class="tab-btn <?= $active_tab==='login'?'active':'' ?>"
          onclick="switchTab('login')">Log In</button>
        <button type="button"
          class="tab-btn <?= $active_tab==='register'?'active':'' ?>"
          onclick="switchTab('register')">Register</button>
      </div>

      <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

      <!-- ══ LOGIN SECTION ══ -->
      <div class="form-section <?= $active_tab==='login'?'active':'' ?>" id="section-login">
        <h2 class="form-title">Welcome back!</h2>
        <p class="form-sub">Log in to your KineticBorrow account to manage your rentals.</p>
        <form method="POST" action="login.php">
          <input type="hidden" name="action" value="login"/>
          <div class="input-group">
            <label class="input-label">Email Address</label>
            <div class="input-wrap">
              <span class="input-icon">✉️</span>
              <input class="form-input" type="email" name="email"
                placeholder="you@email.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
            </div>
          </div>
          <div class="input-group">
            <label class="input-label">Password</label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input class="form-input" type="password" name="password"
                id="login-pass" placeholder="Enter your password" required/>
              <button class="eye-btn" type="button" onclick="togglePass('login-pass',this)">👁️</button>
            </div>
          </div>
          <div class="forgot-row"><a class="forgot-link" href="#">Forgot password?</a></div>
          <div class="check-row">
            <div class="custom-check" id="rem-chk" onclick="this.classList.toggle('on')"></div>
            <span class="check-lbl" onclick="document.getElementById('rem-chk').classList.toggle('on')">Remember me for 30 days</span>
          </div>
          <button type="submit" class="submit-btn">Log In →</button>
        </form>
        <div class="divider">or continue with</div>
        <a class="social-btn" href="<?= htmlspecialchars($google_auth_url) ?>">
          <svg width="16" height="16" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.2l6.7-6.7C35.8 2.5 30.2 0 24 0 14.6 0 6.6 5.4 2.6 13.3l7.8 6C12.3 13 17.7 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.1z"/>
            <path fill="#FBBC05" d="M10.4 28.7A14.6 14.6 0 0 1 9.5 24c0-1.6.3-3.2.9-4.7l-7.8-6A23.9 23.9 0 0 0 0 24c0 3.9.9 7.5 2.6 10.7l7.8-6z"/>
            <path fill="#34A853" d="M24 48c6.2 0 11.4-2 15.2-5.5l-7.6-5.9c-2 1.4-4.7 2.2-7.6 2.2-6.3 0-11.7-4.3-13.6-10l-7.8 6C6.6 42.6 14.6 48 24 48z"/>
          </svg>
          Continue with Google
        </a>
        <p class="switch-txt">New here? <span class="switch-lnk" onclick="switchTab('register')">Create a free account</span></p>
        <div class="footer-note">© <?= date('Y') ?> KineticBorrow · University of Caloocan City</div>
      </div><!-- /section-login -->

      <!-- ══ REGISTER SECTION ══ -->
      <div class="form-section <?= $active_tab==='register'?'active':'' ?>" id="section-register">
        <h2 class="form-title">Register to KineticBorrow</h2>
        <p class="form-sub">Fill in your information to create a secure account.</p>
        <form method="POST" action="login.php?tab=register">
          <input type="hidden" name="action" value="register"/>

          <!-- Name row -->
          <div class="g4">
            <div class="input-group">
              <label class="input-label">First Name *</label>
              <div class="input-wrap">
                <input class="form-input no-icon" type="text" name="first_name"
                  placeholder="Enter first name" value="<?= $pv('first_name') ?>" required/>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">Middle Name</label>
              <div class="input-wrap">
                <input class="form-input no-icon" type="text" name="middle_name"
                  placeholder="Enter middle name" value="<?= $pv('middle_name') ?>"/>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">Last Name *</label>
              <div class="input-wrap">
                <input class="form-input no-icon" type="text" name="last_name"
                  placeholder="Enter last name" value="<?= $pv('last_name') ?>" required/>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">Suffix</label>
              <select class="form-input no-icon" name="suffix" style="width:80px">
                <option value="">—</option>
                <option value="Jr." <?= $pv('suffix')==='Jr.'?'selected':'' ?>>Jr.</option>
                <option value="Sr." <?= $pv('suffix')==='Sr.'?'selected':'' ?>>Sr.</option>
                <option value="II"  <?= $pv('suffix')==='II' ?'selected':'' ?>>II</option>
                <option value="III" <?= $pv('suffix')==='III'?'selected':'' ?>>III</option>
              </select>
            </div>
          </div>

          <!-- Email + Phone -->
          <div class="g2">
            <div class="input-group">
              <label class="input-label">Email Address *</label>
              <div class="input-wrap">
                <span class="input-icon">✉️</span>
                <input class="form-input" type="email" name="email"
                  placeholder="Enter email address" value="<?= $pv('email') ?>" required/>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">Phone Number *</label>
              <div class="phone-wrap">
                <span class="phone-prefix">+63</span>
                <input class="form-input no-icon" type="tel" name="phone"
                  placeholder="XXX-XXX-XXXX" value="<?= $pv('phone') ?>" required style="flex:1"/>
              </div>
            </div>
          </div>

          <!-- Address -->
          <div class="g3">
            <div class="input-group">
              <label class="input-label">Street</label>
              <div class="input-wrap">
                <input class="form-input no-icon" type="text" name="street"
                  placeholder="Enter street" value="<?= $pv('street') ?>"/>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">City</label>
              <div class="input-wrap">
                <input class="form-input no-icon" type="text" name="city"
                  placeholder="Enter city" value="<?= $pv('city') ?>"/>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">Zip Code</label>
              <div class="input-wrap">
                <input class="form-input no-icon" type="text" name="zip"
                  placeholder="0000" value="<?= $pv('zip') ?>"/>
              </div>
            </div>
          </div>

          <!-- Password -->
          <div class="g2">
            <div class="input-group">
              <label class="input-label">Password *</label>
              <div class="input-wrap">
                <span class="input-icon">🔒</span>
                <input class="form-input" type="password" name="password"
                  id="reg-pass" placeholder="Enter password" oninput="checkStrength()" required/>
                <button class="eye-btn" type="button" onclick="togglePass('reg-pass',this)">👁️</button>
              </div>
              <div class="strength-wrap">
                <div class="strength-bg"><div class="strength-bar" id="sbar"></div></div>
                <p class="strength-lbl" id="slbl">Use 8+ characters</p>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">Confirm Password *</label>
              <div class="input-wrap">
                <span class="input-icon">🔒</span>
                <input class="form-input" type="password" name="password_confirm"
                  id="reg-pass2" placeholder="Enter confirm password" required/>
                <button class="eye-btn" type="button" onclick="togglePass('reg-pass2',this)">👁️</button>
              </div>
            </div>
          </div>

          <!-- Terms -->
          <div class="check-row">
            <div class="custom-check" id="terms-chk" onclick="this.classList.toggle('on')"></div>
            <p class="check-lbl" onclick="document.getElementById('terms-chk').classList.toggle('on')">
              By signing up, you agree to KineticBorrow's
              <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a>.
            </p>
          </div>

          <button type="submit" class="submit-btn" onclick="return checkTerms()">Create an Account →</button>
        </form>
        <p class="switch-txt">Already have an account? <span class="switch-lnk" onclick="switchTab('login')">Login</span></p>
        <div class="footer-note">© <?= date('Y') ?> KineticBorrow · University of Caloocan City</div>
      </div><!-- /section-register -->

    </div><!-- /right-inner -->
  </div><!-- /right -->

</div><!-- /split -->

<script>
  function switchTab(tab) {
    // Update button styles
    var btns = document.querySelectorAll('.tab-btn');
    btns[0].classList.toggle('active', tab === 'login');
    btns[1].classList.toggle('active', tab === 'register');
    // Show correct section
    document.getElementById('section-login').classList.toggle('active', tab === 'login');
    document.getElementById('section-register').classList.toggle('active', tab === 'register');
    // Update browser URL without reload
    history.replaceState(null, '', 'login.php' + (tab === 'register' ? '?tab=register' : ''));
  }

  function togglePass(id, btn) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
    btn.textContent = el.type === 'password' ? '👁️' : '🙈';
  }

  function checkTerms() {
    if (!document.getElementById('terms-chk').classList.contains('on')) {
      alert('Please accept the Terms and Conditions to continue.');
      return false;
    }
    return true;
  }

  function checkStrength() {
    var v = document.getElementById('reg-pass').value;
    var bar = document.getElementById('sbar'), lbl = document.getElementById('slbl');
    if (!v) { bar.style.width = '0'; lbl.textContent = 'Use 8+ characters'; lbl.style.color = ''; return; }
    var s = 0;
    if (v.length >= 6)  s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    var lvl = [
      {w:'20%', c:'#E05252', t:'😬 Too weak'},
      {w:'40%', c:'#E07C35', t:'🤔 Weak'},
      {w:'60%', c:'#D6A252', t:'😐 Fair'},
      {w:'80%', c:'#7EC87A', t:'😊 Good'},
      {w:'100%',c:'#2E8B57', t:'💪 Strong'}
    ][Math.min(s - 1, 4)] || {w:'10%', c:'#E05252', t:'😬 Too weak'};
    bar.style.width = lvl.w; bar.style.background = lvl.c;
    lbl.textContent = lvl.t; lbl.style.color = lvl.c;
  }
</script>
</body>
</html>
