<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user = getUser();
$user_id = $_SESSION['user_id'];

// Refresh user from DB
$u = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$u->bind_param('i', $user_id);
$u->execute();
$user = $u->get_result()->fetch_assoc();
$_SESSION['user'] = $user;

// Fetch equipment
$cat_filter = isset($_GET['cat']) ? $conn->real_escape_string($_GET['cat']) : '';
$search     = isset($_GET['q'])   ? $conn->real_escape_string($_GET['q'])   : '';
$page       = $_GET['page'] ?? 'browse';

$eq_sql = "SELECT * FROM equipment WHERE is_active=1";
if ($cat_filter) $eq_sql .= " AND category='$cat_filter'";
if ($search)     $eq_sql .= " AND (name LIKE '%$search%' OR category LIKE '%$search%')";
$eq_sql .= " ORDER BY review_count DESC";
$equipment = $conn->query($eq_sql)->fetch_all(MYSQLI_ASSOC);

// Fetch cart from session
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add_cart') {
        $eid = intval($_POST['eq_id']);
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] === $eid) { $item['days']++; $found = true; break; }
        }
        if (!$found) {
            $eq = $conn->query("SELECT * FROM equipment WHERE id=$eid LIMIT 1")->fetch_assoc();
            if ($eq) $_SESSION['cart'][] = ['id'=>$eq['id'],'name'=>$eq['name'],'icon'=>$eq['icon'],'price'=>$eq['price_per_day'],'days'=>1];
        }
        header("Location: dashboard.php?page=cart");
        exit();
    }
    if ($act === 'remove_cart') {
        $eid = intval($_POST['eq_id']);
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($i) => $i['id'] !== $eid));
        header("Location: dashboard.php?page=cart");
        exit();
    }
    if ($act === 'update_days') {
        $eid  = intval($_POST['eq_id']);
        $days = max(1, intval($_POST['days']));
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] === $eid) { $item['days'] = $days; break; }
        }
        header("Location: dashboard.php?page=cart");
        exit();
    }
    if ($act === 'confirm_booking') {
        // Create rental records
        foreach ($_SESSION['cart'] as $item) {
            $disc = in_array($user['id_type'], ['student','senior','pwd']) ? 20 : 0;
            $total = $item['price'] * $item['days'] * (1 - $disc/100);
            $code  = 'KB-' . strtoupper(substr(uniqid(), -4));
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime("+{$item['days']} days"));
            $stmt  = $conn->prepare("INSERT INTO rentals (order_code,user_id,equipment_id,days,price_per_day,discount_pct,total_amount,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('siiiidiss', $code, $user_id, $item['id'], $item['days'], $item['price'], $disc, $total, $start, $end);
            $stmt->execute();
            // Add loyalty points (1pt per ₱10)
            $pts = intval($total / 10);
            $conn->query("UPDATE users SET loyalty_pts = loyalty_pts + $pts WHERE id = $user_id");
        }
        $_SESSION['cart'] = [];
        header("Location: dashboard.php?page=history&booked=1");
        exit();
    }
}

// Fetch rental history
$rentals = $conn->query("
    SELECT r.*, e.name as eq_name, e.icon as eq_icon
    FROM rentals r
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.user_id = $user_id
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Discount % for cart
$disc_pct = in_array($user['id_type'], ['student','senior','pwd']) ? 20 : 0;

// Cart totals
$subtotal = array_reduce($_SESSION['cart'], fn($s, $i) => $s + $i['price'] * $i['days'], 0);
$discount = round($subtotal * $disc_pct / 100);
$total    = $subtotal - $discount;

$id_labels = ['student'=>'Student ID','senior'=>'Senior Citizen ID','pwd'=>'PWD ID','regular'=>'Regular'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KineticBorrow — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,800;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;
      --green:#2E8B57;--green-bg:#EAF6EE;
      --red:#C0392B;--red-bg:#FDECEA;
      --blue:#2563EB;--blue-bg:#EEF3FD;
      --bg:#F7F5F2;--surface:#fff;--border:#E5E0D8;--border2:#D0C8BC;
      --muted:#8A8078;--text:#1C1916;--text2:#4A4540;
    }
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;}

    /* NAV */
    nav{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(0,0,0,.06);}
    .brand{display:flex;align-items:center;gap:8px;text-decoration:none;}
    .brand-name{font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:var(--text);}
    .brand-name span{color:var(--gold);}
    .nav-links{display:flex;gap:4px;}
    .nav-btn{background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;padding:8px 16px;border-radius:6px;color:var(--text2);transition:all .2s;text-decoration:none;display:inline-block;}
    .nav-btn:hover,.nav-btn.active{background:var(--gold-bg);color:var(--gold);}
    .nav-btn.active{font-weight:600;}
    .cart-badge{background:var(--gold);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;margin-left:4px;}
    .nav-user{display:flex;align-items:center;gap:10px;}
    .avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;}
    .nav-user span{font-size:13px;color:var(--muted);}
    .logout-btn{background:none;border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:6px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12px;margin-left:6px;transition:all .18s;text-decoration:none;display:inline-block;}
    .logout-btn:hover{border-color:var(--red);color:var(--red);}

    .container{max-width:1100px;margin:0 auto;padding:32px 24px;}
    .page-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;margin-bottom:6px;}
    .page-sub{font-size:13px;color:var(--muted);margin-bottom:24px;}

    /* HERO */
    .hero{background:linear-gradient(120deg,#FDF0DC 0%,#FFF9F0 60%);border:1px solid #EDD8B0;border-radius:18px;padding:28px 36px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;}
    .hero-label{font-size:11px;color:var(--gold);letter-spacing:.14em;text-transform:uppercase;margin-bottom:6px;font-weight:600;}
    .hero-title{font-family:'Playfair Display',serif;font-size:30px;font-weight:800;line-height:1.1;margin-bottom:10px;}
    .hero-title span{color:var(--gold);}
    .hero-sub{font-size:13px;color:var(--muted);max-width:320px;line-height:1.6;}

    /* FILTERS */
    .filter-row{display:flex;gap:12px;align-items:center;margin-bottom:22px;flex-wrap:wrap;}
    .search-wrap{position:relative;flex:1;min-width:200px;}
    .search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;color:#B0A898;pointer-events:none;}
    .search-input{background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px 16px 10px 42px;color:var(--text);outline:none;width:100%;font-family:'DM Sans',sans-serif;font-size:14px;transition:all .2s;box-shadow:0 1px 4px rgba(0,0,0,.04);}
    .search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .search-input::placeholder{color:#C0B8AE;}
    .pills{display:flex;gap:8px;flex-wrap:wrap;}
    .pill{background:#fff;border:1px solid var(--border);border-radius:20px;padding:6px 16px;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;cursor:pointer;transition:all .18s;color:var(--text2);text-decoration:none;display:inline-block;}
    .pill:hover,.pill.active{background:var(--gold);border-color:var(--gold);color:#fff;}

    /* CARDS */
    .eq-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;}
    .card{background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:all .25s;box-shadow:0 2px 8px rgba(0,0,0,.05);}
    .card:hover{border-color:var(--gold);transform:translateY(-4px);box-shadow:0 12px 32px rgba(196,127,43,.14);}
    .card-img{background:var(--gold-bg);padding:28px 0;text-align:center;font-size:52px;border-bottom:1px solid #EDD8B0;}
    .card-body{padding:16px;}
    .card-name{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;margin-bottom:3px;}
    .card-cat{font-size:12px;color:var(--muted);margin-bottom:10px;}
    .card-foot{display:flex;align-items:center;justify-content:space-between;}
    .card-price{font-family:'Playfair Display',serif;font-size:22px;color:var(--gold);font-weight:700;}
    .card-price span{font-family:'DM Sans',sans-serif;font-size:12px;color:var(--muted);}
    .card-rating{font-size:12px;color:var(--muted);}
    .tag{font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;padding:3px 8px;border-radius:4px;}
    .tag-pop{background:var(--gold-bg);color:var(--gold);border:1px solid #EDD8B0;}
    .tag-std{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .tag-lmt{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .tag-wkd{background:var(--blue-bg);color:var(--blue);border:1px solid #C0D4F8;}
    .gold-btn{background:var(--gold);color:#fff;border:none;padding:11px 24px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;transition:all .2s;width:100%;margin-top:12px;}
    .gold-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 4px 12px rgba(196,127,43,.3);}
    .ghost-btn{background:transparent;color:var(--text2);border:1px solid var(--border);padding:10px 22px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;transition:all .2s;width:100%;margin-top:8px;}
    .ghost-btn:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-bg);}

    /* CART */
    .cart-layout{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}
    .cart-items{display:flex;flex-direction:column;gap:12px;}
    .cart-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .cart-emoji{font-size:34px;background:var(--gold-bg);border-radius:12px;width:60px;height:60px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .cart-info{flex:1;}
    .cart-name{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:2px;}
    .cart-rate{font-size:12px;color:var(--muted);}
    .qty-row{display:flex;align-items:center;gap:10px;}
    .qty-label{font-size:12px;color:var(--muted);}
    .qty-ctrl{display:flex;align-items:center;gap:6px;}
    .qty-btn{background:var(--bg);border:1px solid var(--border);color:var(--text);width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:all .15s;}
    .qty-btn:hover{background:var(--gold);border-color:var(--gold);color:#fff;}
    .qty-val{font-size:14px;font-weight:600;min-width:20px;text-align:center;}
    .cart-sub{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:var(--gold);min-width:80px;text-align:right;}
    .remove-form{display:inline;}
    .remove-btn{background:none;border:none;color:#C0B8AE;cursor:pointer;font-size:20px;margin-left:4px;transition:color .15s;}
    .remove-btn:hover{color:var(--red);}
    .summary-box{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.06);}
    .summary-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;margin-bottom:18px;}
    .summary-rows{display:flex;flex-direction:column;gap:10px;margin-bottom:18px;}
    .summary-row{display:flex;justify-content:space-between;font-size:13px;}
    .summary-row .lbl{color:var(--muted);}
    .summary-row .val{color:var(--text);font-weight:500;}
    .summary-row.disc .lbl,.summary-row.disc .val{color:var(--green);}
    .summary-divider{border-top:1px solid var(--border);padding-top:12px;}
    .summary-total-lbl{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;}
    .summary-total-val{font-family:'Playfair Display',serif;font-size:21px;font-weight:800;color:var(--gold);}
    .summary-note{font-size:11px;color:var(--muted);text-align:center;margin-top:14px;line-height:1.5;}
    .empty-state{text-align:center;padding:60px 0;color:var(--muted);}
    .empty-icon{font-size:48px;margin-bottom:12px;}

    /* ID BADGE */
    .id-box{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,.04);margin-top:14px;}
    .id-title{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:6px;}
    .id-sub{font-size:12px;color:var(--muted);margin-bottom:0;line-height:1.5;}
    .verified-pill{display:inline-flex;align-items:center;gap:6px;background:var(--green-bg);border:1px solid #A8D8B8;border-radius:20px;padding:5px 14px;font-size:12px;color:var(--green);font-weight:500;margin-top:10px;}

    /* HISTORY */
    .history-list{display:flex;flex-direction:column;gap:12px;}
    .history-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px 22px;display:flex;align-items:center;gap:20px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .history-card.active-order{border-left:4px solid var(--green);border-color:#A8D8B8;}
    .order-id-lbl{font-size:10px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px;}
    .order-id-val{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:var(--gold);}
    .order-info{flex:1;}
    .order-name{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:2px;}
    .order-date{font-size:12px;color:var(--muted);}
    .status-badge{padding:4px 12px;border-radius:20px;font-size:12px;font-weight:500;}
    .status-active{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .status-returned{background:#F2F0EE;color:var(--muted);border:1px solid var(--border);}
    .order-total{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;min-width:80px;text-align:right;}
    .active-total{color:var(--gold);}
    .done-total{color:var(--muted);}
    .loyalty-banner{margin-top:22px;background:linear-gradient(120deg,#FDF0DC,#FFF9F2);border:1px solid #EDD8B0;border-radius:14px;padding:22px 26px;display:flex;align-items:center;justify-content:space-between;}
    .loyalty-lbl{font-size:11px;color:var(--gold);text-transform:uppercase;letter-spacing:.12em;margin-bottom:4px;font-weight:600;}
    .loyalty-pts{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;}
    .loyalty-pts span{font-size:14px;color:var(--muted);font-weight:400;}
    .loyalty-sub{font-size:12px;color:var(--muted);margin-top:2px;}
    .tier-badge{background:#fff;border:1px solid var(--border);border-radius:8px;padding:6px 14px;font-size:12px;color:var(--text2);display:inline-block;margin-bottom:8px;}
    .tier-badge span{color:var(--gold);font-weight:600;}

    /* PROFILE */
    .profile-wrap{max-width:620px;margin:0 auto;}
    .profile-card{background:linear-gradient(135deg,#FDF0DC,#FFF9F2);border:1px solid #EDD8B0;border-radius:18px;padding:30px;margin-bottom:16px;text-align:center;}
    .profile-avatar{width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;margin:0 auto 14px;box-shadow:0 4px 16px rgba(196,127,43,.3);}
    .profile-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;margin-bottom:2px;}
    .profile-email{font-size:13px;color:var(--muted);margin-bottom:14px;}
    .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
    .stat-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .stat-icon{font-size:24px;margin-bottom:6px;}
    .stat-val{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--gold);}
    .stat-lbl{font-size:12px;color:var(--muted);margin-top:2px;}
    .settings-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px 22px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .settings-title{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:16px;}
    .settings-item{display:flex;align-items:center;justify-content:space-between;padding:13px 0;border-bottom:1px solid #F0EDE8;cursor:pointer;transition:padding .15s;}
    .settings-item:last-child{border-bottom:none;}
    .settings-item:hover{padding-left:6px;}
    .settings-item-lbl{font-size:14px;color:var(--text2);}
    .settings-item-lbl.danger{color:var(--red);}
    .settings-arrow{color:#C0B8AE;font-size:16px;}

    /* ALERT */
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
  </style>
</head>
<body>

<nav>
  <a class="brand" href="dashboard.php">
    <span style="font-size:22px">🏋️</span>
    <span class="brand-name">Kinetic<span>Borrow</span></span>
  </a>
  <div class="nav-links">
    <a class="nav-btn <?= $page==='browse'  ? 'active':'' ?>" href="dashboard.php?page=browse">Browse</a>
    <a class="nav-btn <?= $page==='cart'    ? 'active':'' ?>" href="dashboard.php?page=cart">
      Cart <?php if(count($_SESSION['cart'])>0): ?><span class="cart-badge"><?= count($_SESSION['cart']) ?></span><?php endif; ?>
    </a>
    <a class="nav-btn <?= $page==='history' ? 'active':'' ?>" href="dashboard.php?page=history">History</a>
    <a class="nav-btn <?= $page==='profile' ? 'active':'' ?>" href="dashboard.php?page=profile">Profile</a>
  </div>
  <div class="nav-user">
    <div class="avatar"><?= strtoupper(substr($user['first_name'],0,1)) ?></div>
    <span><?= htmlspecialchars($user['first_name'].' '.substr($user['last_name'],0,1)) ?>.</span>
    <a class="logout-btn" href="logout.php">Log Out</a>
  </div>
</nav>

<div class="container">

<?php if (isset($_GET['booked'])): ?>
<div class="alert alert-success">✅ Booking confirmed! Your equipment is ready for pick-up. Loyalty points have been added to your account.</div>
<?php endif; ?>

<!-- ══ BROWSE ══ -->
<?php if ($page === 'browse'): ?>
  <div class="hero">
    <div>
      <p class="hero-label">Sports Equipment Rental</p>
      <h1 class="hero-title">Gear Up. <span>Play Hard.</span></h1>
      <p class="hero-sub">Browse, reserve, and rent premium sports equipment — ready for pick-up at your convenience.</p>
    </div>
    <div style="font-size:72px">🏆</div>
  </div>

  <form method="GET" action="dashboard.php" style="display:contents">
    <input type="hidden" name="page" value="browse"/>
    <div class="filter-row">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input class="search-input" name="q" placeholder="Search equipment..." value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div class="pills">
        <a class="pill <?= !$cat_filter?'active':'' ?>" href="dashboard.php?page=browse">All</a>
        <?php foreach(['Cycling','Racket Sports','Water Sports','Combat Sports','Team Sports'] as $c): ?>
        <a class="pill <?= $cat_filter===$c?'active':'' ?>" href="dashboard.php?page=browse&cat=<?= urlencode($c) ?>"><?= $c ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </form>

  <div class="eq-grid">
    <?php foreach($equipment as $eq): ?>
    <?php
      $tl = $eq['tag'] ?? '';
      $tc = $tl==='Popular'?'tag-pop': ($tl==='Student Deal'?'tag-std': ($tl==='Limited'?'tag-lmt': ($tl?'tag-wkd':'')));
    ?>
    <div class="card">
      <div class="card-img"><?= $eq['icon'] ?></div>
      <div class="card-body">
        <?php if($tl): ?><span class="tag <?= $tc ?>" style="margin-bottom:8px;display:inline-block"><?= htmlspecialchars($tl) ?></span><?php endif; ?>
        <p class="card-name"><?= htmlspecialchars($eq['name']) ?></p>
        <p class="card-cat"><?= htmlspecialchars($eq['category']) ?></p>
        <div class="card-foot">
          <span class="card-price">₱<?= number_format($eq['price_per_day'],0) ?><span>/day</span></span>
          <span class="card-rating">⭐ <?= $eq['rating'] ?> (<?= $eq['review_count'] ?>)</span>
        </div>
        <form method="POST" action="dashboard.php?page=cart">
          <input type="hidden" name="act" value="add_cart"/>
          <input type="hidden" name="eq_id" value="<?= $eq['id'] ?>"/>
          <button type="submit" class="gold-btn">+ Add to Cart</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($equipment)): ?>
    <div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">🔍</div><p>No equipment found.</p></div>
    <?php endif; ?>
  </div>

<!-- ══ CART ══ -->
<?php elseif($page==='cart'): ?>
  <h2 class="page-title">Your Cart</h2>
  <p class="page-sub"><?= count($_SESSION['cart']) ?> item<?= count($_SESSION['cart'])!==1?'s':'' ?> reserved</p>

  <?php if(empty($_SESSION['cart'])): ?>
  <div class="empty-state"><div class="empty-icon">🛒</div><p>Your cart is empty</p><a class="gold-btn" href="dashboard.php?page=browse" style="display:inline-block;width:auto;padding:11px 24px;text-decoration:none;margin-top:16px">Browse Equipment</a></div>
  <?php else: ?>
  <div class="cart-layout">
    <div>
      <div class="cart-items">
        <?php foreach($_SESSION['cart'] as $item): ?>
        <div class="cart-card">
          <div class="cart-emoji"><?= $item['icon'] ?></div>
          <div class="cart-info">
            <p class="cart-name"><?= htmlspecialchars($item['name']) ?></p>
            <p class="cart-rate">₱<?= number_format($item['price'],0) ?>/day</p>
          </div>
          <div class="qty-row">
            <span class="qty-label">Days:</span>
            <div class="qty-ctrl">
              <form method="POST" action="dashboard.php?page=cart" style="display:inline">
                <input type="hidden" name="act" value="update_days"/>
                <input type="hidden" name="eq_id" value="<?= $item['id'] ?>"/>
                <input type="hidden" name="days" value="<?= max(1,$item['days']-1) ?>"/>
                <button type="submit" class="qty-btn">−</button>
              </form>
              <span class="qty-val"><?= $item['days'] ?></span>
              <form method="POST" action="dashboard.php?page=cart" style="display:inline">
                <input type="hidden" name="act" value="update_days"/>
                <input type="hidden" name="eq_id" value="<?= $item['id'] ?>"/>
                <input type="hidden" name="days" value="<?= $item['days']+1 ?>"/>
                <button type="submit" class="qty-btn">+</button>
              </form>
            </div>
          </div>
          <span class="cart-sub">₱<?= number_format($item['price']*$item['days'],0) ?></span>
          <form class="remove-form" method="POST" action="dashboard.php?page=cart">
            <input type="hidden" name="act" value="remove_cart"/>
            <input type="hidden" name="eq_id" value="<?= $item['id'] ?>"/>
            <button type="submit" class="remove-btn">×</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ID Box -->
      <div class="id-box">
        <p class="id-title">🪪 Your ID Discount</p>
        <?php if($disc_pct > 0): ?>
        <p class="id-sub">Your <?= $id_labels[$user['id_type']] ?> entitles you to a <?= $disc_pct ?>% discount on all rentals.</p>
        <span class="verified-pill">✅ <?= $id_labels[$user['id_type']] ?> Verified — <?= $disc_pct ?>% off applied</span>
        <?php else: ?>
        <p class="id-sub">No eligible ID on file. <a href="dashboard.php?page=profile" style="color:var(--gold);font-weight:600">Update your profile</a> to unlock discounts.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Summary -->
    <div class="summary-box">
      <p class="summary-title">Order Summary</p>
      <div class="summary-rows">
        <div class="summary-row"><span class="lbl">Subtotal</span><span class="val">₱<?= number_format($subtotal,0) ?></span></div>
        <?php if($discount > 0): ?>
        <div class="summary-row disc"><span class="lbl"><?= ucfirst($user['id_type']) ?> Discount (<?= $disc_pct ?>%)</span><span class="val">−₱<?= number_format($discount,0) ?></span></div>
        <?php endif; ?>
        <div class="summary-row summary-divider">
          <span class="summary-total-lbl">Total</span>
          <span class="summary-total-val">₱<?= number_format($total,0) ?></span>
        </div>
      </div>
      <form method="POST" action="dashboard.php?page=cart">
        <input type="hidden" name="act" value="confirm_booking"/>
        <button type="submit" class="gold-btn">Confirm Booking →</button>
      </form>
      <a href="dashboard.php?page=browse" class="ghost-btn" style="display:block;text-align:center;text-decoration:none">Continue Browsing</a>
      <p class="summary-note">Equipment will be prepared for pick-up once booking is confirmed.</p>
    </div>
  </div>
  <?php endif; ?>

<!-- ══ HISTORY ══ -->
<?php elseif($page==='history'): ?>
  <h2 class="page-title">Rental History</h2>
  <p class="page-sub">All your past and active rentals</p>

  <?php if(empty($rentals)): ?>
  <div class="empty-state"><div class="empty-icon">📋</div><p>No rentals yet. <a href="dashboard.php?page=browse" style="color:var(--gold)">Browse equipment</a> to get started!</p></div>
  <?php else: ?>
  <div class="history-list">
    <?php foreach($rentals as $r): ?>
    <div class="history-card <?= $r['status']==='active'?'active-order':'' ?>">
      <div><p class="order-id-lbl">Order ID</p><p class="order-id-val"><?= htmlspecialchars($r['order_code']) ?></p></div>
      <div class="order-info">
        <p class="order-name"><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></p>
        <p class="order-date"><?= date('M j', strtotime($r['start_date'])) ?> – <?= date('M j, Y', strtotime($r['end_date'])) ?></p>
      </div>
      <?php if($r['status']==='active'): ?>
      <span class="status-badge status-active">🟢 Active</span>
      <?php else: ?>
      <span class="status-badge status-returned">✓ Returned</span>
      <?php endif; ?>
      <span class="order-total <?= $r['status']==='active'?'active-total':'done-total' ?>">₱<?= number_format($r['total_amount'],0) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="loyalty-banner">
    <div>
      <p class="loyalty-lbl">Loyalty Points</p>
      <p class="loyalty-pts"><?= number_format($user['loyalty_pts']) ?> <span>pts</span></p>
      <p class="loyalty-sub">Earn 1 point for every ₱10 spent</p>
    </div>
    <div style="text-align:right">
      <div class="tier-badge">Current tier: <span><?= $user['loyalty_pts']>=5000?'Gold 🥇':($user['loyalty_pts']>=2000?'Silver 🥈':'Bronze 🥉') ?></span></div><br>
      <button class="gold-btn" style="width:auto;padding:9px 20px;font-size:12px;margin-top:4px">Redeem Points</button>
    </div>
  </div>

<!-- ══ PROFILE ══ -->
<?php elseif($page==='profile'):
  // Flash messages from upload
  $upload_msg   = $_SESSION['id_upload_msg']   ?? ''; unset($_SESSION['id_upload_msg']);
  $upload_error = $_SESSION['id_upload_error'] ?? ''; unset($_SESSION['id_upload_error']);
  // Refresh user from DB to get latest id_status
  $user = $conn->query("SELECT * FROM users WHERE id={$user['id']}")->fetch_assoc();
  $_SESSION['user'] = $user;
  $id_status = $user['id_status'] ?? 'none';
?>
  <div class="profile-wrap">

    <!-- PROFILE CARD -->
    <div class="profile-card">
      <div class="profile-avatar"><?= strtoupper(substr($user['first_name'],0,1)) ?></div>
      <p class="profile-name"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></p>
      <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
      <?php if($id_status === 'approved' && $user['id_type'] !== 'regular'): ?>
        <div class="verified-pill">✅ <?= $id_labels[$user['id_type']] ?> — 20% Discount Active</div>
      <?php elseif($id_status === 'pending'): ?>
        <div class="verified-pill" style="background:#FEF3E2;color:#E07C35;border-color:#FADDB8">⏳ ID Under Review</div>
      <?php elseif($id_status === 'rejected'): ?>
        <div class="verified-pill" style="background:#FDECEA;color:#C0392B;border-color:#F5C6C2">❌ ID Rejected — Please resubmit</div>
      <?php else: ?>
        <div class="verified-pill" style="background:#F2F0EE;color:#8A8078;border-color:#E5E0D8">🪪 No ID submitted yet</div>
      <?php endif; ?>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card"><p class="stat-icon">🏅</p><p class="stat-val"><?= count($rentals) ?></p><p class="stat-lbl">Total Rentals</p></div>
      <div class="stat-card"><p class="stat-icon">⭐</p><p class="stat-val"><?= number_format($user['loyalty_pts']) ?></p><p class="stat-lbl">Loyalty Points</p></div>
      <div class="stat-card"><p class="stat-icon">📦</p><p class="stat-val"><?= count(array_filter($rentals,fn($r)=>$r['status']==='active')) ?></p><p class="stat-lbl">Active Bookings</p></div>
      <div class="stat-card"><p class="stat-icon">💰</p><p class="stat-val">₱<?= number_format(array_sum(array_map(fn($r)=>$r['total_amount']*($r['discount_pct']/100)/(1-$r['discount_pct']/100),array_filter($rentals,fn($r)=>$r['discount_pct']>0))),0) ?></p><p class="stat-lbl">Total Saved</p></div>
    </div>

    <!-- ID UPLOAD CARD -->
    <div class="settings-card" style="margin-bottom:16px">
      <p class="settings-title">🪪 Upload ID for Discount</p>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.6">
        Upload a clear photo of your valid ID to receive a <strong style="color:var(--gold)">20% discount</strong> on all rentals.
        Eligible IDs: Student ID, Senior Citizen ID, or PWD ID.
      </p>

      <?php if($upload_msg): ?>
        <div style="background:#EAF6EE;color:#2E8B57;border:1px solid #C0E0CC;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;margin-bottom:16px">✅ <?= htmlspecialchars($upload_msg) ?></div>
      <?php endif; ?>
      <?php if($upload_error): ?>
        <div style="background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;margin-bottom:16px">⚠️ <?= htmlspecialchars($upload_error) ?></div>
      <?php endif; ?>
      <?php if($id_status === 'rejected' && $user['id_reject_reason']): ?>
        <div style="background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:16px">
          <strong>Rejection reason:</strong> <?= htmlspecialchars($user['id_reject_reason']) ?>
        </div>
      <?php endif; ?>

      <?php if($id_status === 'approved'): ?>
        <!-- Already approved -->
        <div style="background:#EAF6EE;border:1px solid #C0E0CC;border-radius:12px;padding:16px;text-align:center">
          <p style="font-size:22px;margin-bottom:6px">✅</p>
          <p style="font-weight:600;color:#2E8B57;font-size:14px">Your <?= ucfirst($user['id_type']) ?> ID has been verified!</p>
          <p style="font-size:12px;color:#2E8B57;margin-top:4px">20% discount is automatically applied to all your rentals.</p>
          <?php if($user['id_image']): ?>
          <div style="margin-top:14px">
            <p style="font-size:11px;color:#2E8B57;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em">Submitted ID</p>
            <img src="<?= htmlspecialchars($user['id_image']) ?>" style="max-width:100%;max-height:180px;border-radius:10px;border:2px solid #C0E0CC;object-fit:cover"/>
          </div>
          <?php endif; ?>
          <p style="font-size:12px;color:#2E8B57;margin-top:12px">Need to update your ID? <a href="#" onclick="document.getElementById('reupload-form').style.display='block';this.style.display='none'" style="color:var(--gold);font-weight:600">Resubmit here</a></p>
          <div id="reupload-form" style="display:none;margin-top:14px">
            <?php include_once 'includes/id_upload_form.php'; ?>
          </div>
        </div>

      <?php elseif($id_status === 'pending'): ?>
        <!-- Pending review -->
        <div style="background:#FEF3E2;border:1px solid #FADDB8;border-radius:12px;padding:16px;text-align:center">
          <p style="font-size:28px;margin-bottom:8px">⏳</p>
          <p style="font-weight:600;color:#E07C35;font-size:14px">Your ID is being reviewed</p>
          <p style="font-size:12px;color:#E07C35;margin-top:4px">Our team will verify your ID within 24 hours. You'll receive your 20% discount once approved.</p>
          <?php if($user['id_image']): ?>
          <div style="margin-top:14px">
            <p style="font-size:11px;color:#E07C35;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em">Submitted ID</p>
            <img src="<?= htmlspecialchars($user['id_image']) ?>" style="max-width:100%;max-height:180px;border-radius:10px;border:2px solid #FADDB8;object-fit:cover"/>
          </div>
          <?php endif; ?>
          <p style="font-size:12px;color:#E07C35;margin-top:12px">Wrong file? <a href="#" onclick="document.getElementById('reupload-form').style.display='block';this.style.display='none'" style="color:var(--gold);font-weight:600">Resubmit here</a></p>
          <div id="reupload-form" style="display:none;margin-top:14px">
            <?php include_once 'includes/id_upload_form.php'; ?>
          </div>
        </div>

      <?php else: ?>
        <!-- No ID or rejected — show upload form -->
        <?php include_once 'includes/id_upload_form.php'; ?>
      <?php endif; ?>
    </div>

    <!-- ACCOUNT SETTINGS -->
    <div class="settings-card">
      <p class="settings-title">Account Settings</p>
      <div class="settings-item"><span class="settings-item-lbl">Edit Profile</span><span class="settings-arrow">›</span></div>
      <div class="settings-item"><span class="settings-item-lbl">Change Password</span><span class="settings-arrow">›</span></div>
      <div class="settings-item"><span class="settings-item-lbl">Notification Preferences</span><span class="settings-arrow">›</span></div>
      <a href="logout.php" class="settings-item" style="text-decoration:none">
        <span class="settings-item-lbl danger">Log Out</span><span class="settings-arrow">›</span>
      </a>
    </div>
  </div>

<?php endif; ?>

</div>
</body>
</html>
