<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Fetch equipment from DB for display
$eq_result = $conn->query("SELECT * FROM equipment WHERE is_active = 1 ORDER BY review_count DESC LIMIT 6");
$equipment = $eq_result ? $eq_result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch categories
$cat_result = $conn->query("SELECT category, COUNT(*) as cnt FROM equipment WHERE is_active=1 GROUP BY category");
$categories = $cat_result ? $cat_result->fetch_all(MYSQLI_ASSOC) : [];

$cat_icons = [
    'Cycling'       => '🚵',
    'Water Sports'  => '🏄',
    'Racket Sports' => '🏸',
    'Team Sports'   => '⚽',
    'Combat Sports' => '🥊',
    'Outdoor'       => '🏔️',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KineticBorrow — Sports Equipment Rental</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,800;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;
      --green:#2E8B57;--red:#C0392B;
      --bg:#FAFAF8;--surface:#fff;--border:#E8E3DA;--muted:#8A8078;
      --text:#1C1916;--text2:#4A4540;
    }
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;}

    /* TOPBAR */
    .topbar{background:var(--gold-bg);border-bottom:1px solid #EDD8B0;text-align:center;padding:9px 20px;font-size:13px;color:var(--text2);}
    .topbar strong{color:var(--gold);font-weight:700;}

    /* NAV */
    nav{background:#fff;border-bottom:1px solid var(--border);padding:0 48px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(0,0,0,.06);}
    .nav-left{display:flex;align-items:center;gap:40px;}
    .brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .brand-logo{width:38px;height:38px;background:var(--gold);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;}
    .brand-name{font-family:'Playfair Display',serif;font-size:20px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
    .brand-name span{color:var(--gold);}
    .nav-links{display:flex;gap:4px;}
    .nav-link{background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;color:var(--text2);padding:8px 14px;border-radius:6px;transition:all .18s;text-decoration:none;}
    .nav-link:hover,.nav-link.active{background:var(--gold-bg);color:var(--gold);}
    .nav-link.active{font-weight:600;}
    .nav-right{display:flex;align-items:center;gap:10px;}
    .btn-login{background:none;border:1.5px solid var(--border);color:var(--text);padding:8px 20px;border-radius:8px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;transition:all .18s;text-decoration:none;display:inline-block;}
    .btn-login:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-bg);}
    .btn-signup{background:var(--gold);color:#fff;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;transition:all .2s;text-decoration:none;display:inline-block;}
    .btn-signup:hover{background:var(--gold-lt);box-shadow:0 4px 14px rgba(196,127,43,.3);transform:translateY(-1px);}

    /* HERO */
    .hero{background:linear-gradient(135deg,#FDF5E8 0%,#FFF0D8 35%,#FDE8CE 60%,#F8E0D8 100%);padding:70px 80px;display:flex;align-items:center;justify-content:space-between;min-height:460px;position:relative;overflow:visible;}
    .hero::after{content:'';position:absolute;right:-60px;top:50%;transform:translateY(-50%);width:420px;height:420px;border-radius:50%;background:rgba(196,127,43,.1);}
    .hero-content{max-width:560px;position:relative;z-index:1;}
    .hero-dots{display:flex;gap:7px;margin-bottom:20px;}
    .hero-dot{width:10px;height:10px;border-radius:50%;}
    .hero-title{font-family:'Playfair Display',serif;font-size:52px;font-weight:800;line-height:1.1;color:var(--text);margin-bottom:16px;}
    .hero-title .hl-gold{color:var(--gold);}
    .hero-title .hl-red{color:var(--red);}
    .hero-sub{font-size:15px;color:var(--text2);line-height:1.7;margin-bottom:30px;max-width:440px;}
    /* SEARCH BAR + DROPDOWN */
    .hero-search-wrap{position:relative;max-width:540px;}
    .hero-search{display:flex;background:#fff;border-radius:12px;border:1.5px solid var(--border);box-shadow:0 4px 20px rgba(0,0,0,.08);position:relative;z-index:202;overflow:hidden;}
    .hero-search-icon{padding:0 16px;font-size:18px;display:flex;align-items:center;color:#C0B8AE;flex-shrink:0;}
    .hero-search input{flex:1;border:none;outline:none;padding:14px 8px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--text);background:transparent;min-width:0;}
    .hero-search input::placeholder{color:#C0B8AE;}
    .hero-search-btn{background:var(--gold);color:#fff;border:none;padding:0 28px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:background .18s;flex-shrink:0;}
    .hero-search-btn:hover{background:var(--gold-lt);}
    .search-dropdown{display:none;position:absolute;top:calc(100% + 10px);left:0;right:0;background:#fff;border-radius:16px;box-shadow:0 16px 56px rgba(0,0,0,.16);border:1px solid var(--border);z-index:201;max-height:420px;overflow-y:auto;}
    .search-dropdown.show{display:block;}
    .sd-section{padding:14px 16px 10px;}
    .sd-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;}
    .sd-chips{display:flex;flex-wrap:wrap;gap:7px;}
    .sd-chip{background:var(--bg);border:1.5px solid var(--border);border-radius:20px;padding:5px 13px;font-size:12px;font-weight:500;color:var(--text2);cursor:pointer;white-space:nowrap;transition:all .15s;}
    .sd-chip:hover{border-color:var(--gold);background:var(--gold-bg);color:var(--gold);}
    .sd-hr{height:1px;background:var(--border);}
    .sd-cols{display:grid;grid-template-columns:1fr 1fr;}
    .sd-col{padding:12px 16px 14px;}
    .sd-col+.sd-col{border-left:1px solid var(--border);}
    .sd-row{display:flex;align-items:center;gap:9px;padding:7px 8px;border-radius:10px;cursor:pointer;transition:background .14s;}
    .sd-row:hover{background:var(--bg);}
    .sd-badge{width:20px;height:20px;border-radius:6px;background:var(--gold);color:#fff;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .sd-icon{font-size:22px;flex-shrink:0;}
    .sd-cat-box{width:36px;height:36px;background:var(--gold-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .sd-info{flex:1;min-width:0;}
    .sd-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .sd-sub{font-size:11px;color:var(--muted);margin-top:1px;}
    .sd-price{font-size:12px;font-weight:700;color:var(--gold);flex-shrink:0;margin-left:4px;}
    .sd-results-wrap{padding:6px 8px 8px;}
    .sd-no{padding:20px;text-align:center;color:var(--muted);font-size:13px;}
    .hero-circle{width:320px;height:320px;border-radius:50%;background:rgba(196,127,43,.12);display:flex;align-items:center;justify-content:center;font-size:120px;position:relative;z-index:1;}

    /* SECTIONS */
    .section{padding:52px 80px;}
    .section-title{font-family:'Playfair Display',serif;font-size:26px;font-weight:800;text-align:center;color:var(--text);margin-bottom:32px;}

    /* EQUIPMENT GRID */
    .eq-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}
    .eq-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:all .22s;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.04);}
    .eq-card:hover{border-color:var(--gold);transform:translateY(-4px);box-shadow:0 10px 28px rgba(196,127,43,.14);}
    .eq-card-img{background:var(--gold-bg);padding:22px 0;text-align:center;font-size:44px;border-bottom:1px solid #EDD8B0;overflow:hidden;min-height:100px;display:flex;align-items:center;justify-content:center;}
    .eq-card-img img{width:100%;height:160px;object-fit:cover;display:block;}
    .eq-card-body{padding:14px;}
    .eq-card-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:2px;}
    .eq-card-cat{font-size:11px;color:var(--muted);margin-bottom:8px;}
    .eq-card-foot{display:flex;align-items:center;justify-content:space-between;}
    .eq-card-price{font-family:'Playfair Display',serif;font-size:18px;color:var(--gold);font-weight:700;}
    .eq-card-price span{font-family:'DM Sans',sans-serif;font-size:11px;color:var(--muted);}
    .eq-tag{font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;}
    .tag-pop{background:var(--gold-bg);color:var(--gold);border:1px solid #EDD8B0;}
    .tag-hot{background:#FDECEA;color:var(--red);border:1px solid #F5C6C2;}
    .tag-ltd{background:#EEF3FD;color:#2563EB;border:1px solid #C0D4F8;}
    .tag-wknd{background:#EAF6EE;color:var(--green);border:1px solid #C0E0CC;}
    .rent-btn{display:block;width:100%;margin-top:10px;background:var(--gold);color:#fff;border:none;border-radius:8px;padding:9px;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .18s;}
    .rent-btn:hover{background:var(--gold-lt);}

    /* HOW IT WORKS */
    .how-section{background:var(--gold-bg);border-top:1px solid #EDD8B0;border-bottom:1px solid #EDD8B0;}
    .steps-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;}
    .step-card{text-align:center;padding:8px;}
    .step-num{width:44px;height:44px;border-radius:50%;background:var(--gold);color:#fff;font-family:'Playfair Display',serif;font-size:20px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;}
    .step-icon{font-size:32px;margin-bottom:10px;}
    .step-title{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:6px;}
    .step-desc{font-size:12px;color:var(--muted);line-height:1.6;}

    /* DISCOUNT BANNER */
    .discount-banner{background:linear-gradient(120deg,#1C1916 0%,#2E2420 100%);border-radius:16px;padding:36px 48px;display:flex;align-items:center;justify-content:space-between;margin:0 80px 52px;}
    .discount-left h2{font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:#fff;margin-bottom:8px;}
    .discount-left h2 span{color:var(--gold);}
    .discount-left p{font-size:13px;color:#8A8078;line-height:1.6;max-width:380px;}
    .discount-badges{display:flex;gap:10px;margin-top:18px;}
    .d-badge{background:rgba(196,127,43,.15);border:1px solid rgba(196,127,43,.3);border-radius:8px;padding:8px 16px;text-align:center;}
    .d-badge .d-icon{font-size:20px;margin-bottom:3px;}
    .d-badge .d-label{font-size:11px;color:var(--gold);font-weight:600;}
    .d-badge .d-pct{font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:#fff;}
    .discount-right{text-align:center;}
    .big-emoji{font-size:72px;margin-bottom:12px;display:block;}
    .discount-cta{background:var(--gold);color:#fff;border:none;padding:12px 28px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;transition:all .2s;text-decoration:none;display:inline-block;}
    .discount-cta:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 6px 16px rgba(196,127,43,.4);}

    /* CATEGORIES */
    .cat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
    .cat-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:24px 20px;display:flex;align-items:center;gap:16px;cursor:pointer;transition:all .2s;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .cat-card:hover{border-color:var(--gold);background:var(--gold-bg);transform:translateY(-2px);}
    .cat-icon{font-size:32px;}
    .cat-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:2px;}
    .cat-count{font-size:12px;color:var(--muted);}

    /* FOOTER */
    footer{background:#1C1916;color:#8A8078;padding:40px 80px;text-align:center;}
    .footer-brand{font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:6px;}
    .footer-brand span{color:var(--gold);}
    .footer-sub{font-size:12px;margin-bottom:20px;}
    .footer-links{display:flex;justify-content:center;gap:24px;margin-bottom:16px;}
    .footer-link{font-size:12px;color:#666;cursor:pointer;}
    .footer-link:hover{color:var(--gold);}
    .footer-copy{font-size:11px;color:#444;}

    /* MODAL */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;}
    .modal-overlay.show{opacity:1;pointer-events:all;}
    .modal{background:#fff;border-radius:20px;padding:40px 36px;width:400px;text-align:center;transform:translateY(20px);transition:transform .2s;box-shadow:0 20px 60px rgba(0,0,0,.15);position:relative;}
    .modal-overlay.show .modal{transform:translateY(0);}
    .modal-icon{font-size:44px;margin-bottom:14px;}
    .modal-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;margin-bottom:8px;color:var(--text);}
    .modal-sub{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:24px;}
    .modal-btns{display:flex;gap:10px;}
    .modal-login{flex:1;background:none;border:1.5px solid var(--border);padding:11px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;color:var(--text);transition:all .18s;text-decoration:none;display:inline-block;text-align:center;}
    .modal-login:hover{border-color:var(--gold);color:var(--gold);}
    .modal-signup{flex:1;background:var(--gold);color:#fff;border:none;padding:11px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;transition:all .18s;text-decoration:none;display:inline-block;text-align:center;}
    .modal-signup:hover{background:var(--gold-lt);}
    .modal-close{position:absolute;top:16px;right:20px;background:none;border:none;font-size:20px;cursor:pointer;color:#C0B8AE;}
  </style>
</head>
<body>

<div class="topbar">
  🇵🇭 Welcome to <strong>KineticBorrow</strong> — Your trusted sports equipment rental platform in the Philippines
</div>

<nav>
  <div class="nav-left">
    <a class="brand" href="index.php">
      <div class="brand-logo">🏋️</div>
      <span class="brand-name">Kinetic<span>Borrow</span></span>
    </a>
    <div class="nav-links">
      <a class="nav-link active" onclick="scrollToTop()">Home</a>
      <a class="nav-link" onclick="scrollToEquipment()">Equipment</a>
      <a class="nav-link" onclick="scrollToPromos()">Promotions</a>
      <a class="nav-link" onclick="scrollToAbout()">About</a>
    </div>
  </div>
  <div class="nav-right">
    <a class="btn-login"  href="login.php">Log In</a>
    <a class="btn-signup" href="login.php?tab=register">Sign Up</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="section-home">
  <div class="hero-content">
    <div class="hero-dots">
      <div class="hero-dot" style="background:#2563EB"></div>
      <div class="hero-dot" style="background:#C47F2B"></div>
      <div class="hero-dot" style="background:#C0392B"></div>
    </div>
    <h1 class="hero-title">
      Rent the <span class="hl-gold">Best</span> Sports<br>
      Gear in <span class="hl-red">the Philippines</span>
    </h1>
    <p class="hero-sub">Your all-in-one platform for renting sports equipment — bikes, rackets, kayaks, and more. Browse, book, and play.</p>
    <div class="hero-search-wrap" id="search-wrap">
      <div class="hero-search">
        <div class="hero-search-icon">🔍</div>
        <input type="text" id="hero-search" placeholder="Search equipment, categories, or brands..."
          autocomplete="off"
          onfocus="openDropdown()"
          oninput="filterDropdown(this.value)"/>
        <button class="hero-search-btn" onclick="doSearch()">Search</button>
      </div>
      <!-- SEARCH DROPDOWN -->
      <div class="search-dropdown" id="search-dropdown">
        <div id="sd-default">
          <div class="sd-section">
            <div class="sd-label">Popular searches</div>
            <div class="sd-chips" id="sd-chips"></div>
          </div>
          <div class="sd-hr"></div>
          <div class="sd-cols">
            <div class="sd-col">
              <div class="sd-label">🏆 Top Equipment</div>
              <div id="sd-top-eq"></div>
            </div>
            <div class="sd-col">
              <div class="sd-label">📂 Browse by Category</div>
              <div id="sd-categories"></div>
            </div>
          </div>
        </div>
        <div id="sd-results" style="display:none">
          <div class="sd-results-wrap" id="sd-results-list"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="hero-circle">🏆</div>
</section>

<!-- TOP EQUIPMENT -->
<section class="section" id="section-equipment">
  <h2 class="section-title">Top Equipment to Rent</h2>
  <div id="eq-no-results" style="display:none;text-align:center;padding:40px 20px;background:#fff;border-radius:16px;border:1px dashed var(--border);margin-bottom:20px">
    <div style="font-size:40px;margin-bottom:12px">🔍</div>
    <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px">No equipment found</div>
    <div style="font-size:13px;color:var(--muted)">Try a different search term or browse all categories</div>
    <button onclick="document.getElementById('hero-search').value='';document.querySelectorAll('.eq-card').forEach(c=>c.style.display='');this.parentElement.style.display='none'" style="margin-top:16px;background:var(--gold);color:#fff;border:none;border-radius:8px;padding:10px 24px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer">Show All Equipment</button>
  </div>
  <div class="eq-grid">
    <?php foreach ($equipment as $eq): ?>
    <?php
      $tag_class = '';
      $tag_label = $eq['tag'] ?? '';
      if ($tag_label === 'Popular')        $tag_class = 'tag-pop';
      elseif ($tag_label === 'Student Deal') $tag_class = 'tag-wknd';
      elseif ($tag_label === 'Limited')    $tag_class = 'tag-hot';
      elseif ($tag_label === 'Weekend Special') $tag_class = 'tag-ltd';
    ?>
    <div class="eq-card">
      <div class="eq-card-img" style="<?= !empty($eq['image']) ? 'padding:0;' : '' ?>">
        <?php if(!empty($eq['image'])): ?>
          <img src="<?= htmlspecialchars($eq['image']) ?>" alt="<?= htmlspecialchars($eq['name']) ?>"
               onerror="this.parentElement.style.padding='22px 0';this.outerHTML='<span style=\'font-size:44px\'>🏅</span>'"/>
        <?php else: ?>
          <span style="font-size:44px"><?= !empty($eq['icon']) ? htmlspecialchars($eq['icon']) : '🏅' ?></span>
        <?php endif; ?>
      </div>
      <div class="eq-card-body">
        <p class="eq-card-name"><?= htmlspecialchars($eq['name']) ?></p>
        <p class="eq-card-cat"><?= htmlspecialchars($eq['category']) ?></p>
        <div class="eq-card-foot">
          <span class="eq-card-price">₱<?= number_format($eq['price_per_day'], 0) ?><span>/day</span></span>
          <?php if ($tag_label): ?>
          <span class="eq-tag <?= $tag_class ?>"><?= htmlspecialchars($tag_label) ?></span>
          <?php endif; ?>
        </div>
        <button class="rent-btn" onclick="promptLogin()">Rent Now</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section how-section">
  <h2 class="section-title">How KineticBorrow Works</h2>
  <div class="steps-grid">
    <div class="step-card">
      <div class="step-icon">🔍</div>
      <div class="step-num">1</div>
      <p class="step-title">Browse Equipment</p>
      <p class="step-desc">Search and explore our wide selection of sports gear available for rent.</p>
    </div>
    <div class="step-card">
      <div class="step-icon">🪪</div>
      <div class="step-num">2</div>
      <p class="step-title">Verify Your ID</p>
      <p class="step-desc">Upload your Student, Senior, or PWD ID — our AI auto-detects your discount tier.</p>
    </div>
    <div class="step-card">
      <div class="step-icon">🛒</div>
      <div class="step-num">3</div>
      <p class="step-title">Book &amp; Pay</p>
      <p class="step-desc">Select your rental dates, confirm your booking, and complete payment securely.</p>
    </div>
    <div class="step-card">
      <div class="step-icon">🏃</div>
      <div class="step-num">4</div>
      <p class="step-title">Pick Up &amp; Play</p>
      <p class="step-desc">Pick up your gear at our store and get out there. Return when done!</p>
    </div>
  </div>
</section>

<!-- DISCOUNT BANNER -->
<div class="discount-banner" id="section-promos">
  <div class="discount-left">
    <h2>Exclusive <span>Discounts</span> for You</h2>
    <p>KineticBorrow's AI-powered ID verification automatically applies the right discount — no manual processing needed.</p>
    <div class="discount-badges">
      <div class="d-badge"><div class="d-icon">🎓</div><div class="d-pct">20%</div><div class="d-label">Student</div></div>
      <div class="d-badge"><div class="d-icon">👴</div><div class="d-pct">20%</div><div class="d-label">Senior</div></div>
      <div class="d-badge"><div class="d-icon">♿</div><div class="d-pct">20%</div><div class="d-label">PWD</div></div>
    </div>
  </div>
  <div class="discount-right">
    <span class="big-emoji">🪪</span>
    <br>
    <a class="discount-cta" href="login.php?tab=register">Claim Your Discount →</a>
  </div>
</div>

<!-- CATEGORIES -->
<section class="section" style="padding-top:0">
  <h2 class="section-title">Browse by Category</h2>
  <div class="cat-grid">
    <?php foreach ($categories as $cat): ?>
    <div class="cat-card" onclick="scrollToEquipment()">
      <span class="cat-icon"><?= $cat_icons[$cat['category']] ?? '🏋️' ?></span>
      <div>
        <p class="cat-name"><?= htmlspecialchars($cat['category']) ?></p>
        <p class="cat-count"><?= $cat['cnt'] ?> item<?= $cat['cnt'] > 1 ? 's' : '' ?> available</p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FOOTER -->
<footer id="section-about">
  <p class="footer-brand">Kinetic<span>Borrow</span></p>
  <p class="footer-sub">Your trusted sports equipment rental platform</p>
  <div class="footer-links">
    <span class="footer-link">About Us</span>
    <span class="footer-link">Contact</span>
    <span class="footer-link">Privacy Policy</span>
    <span class="footer-link">Terms of Service</span>
    <span class="footer-link">FAQ</span>
  </div>
  <p class="footer-copy">© <?= date('Y') ?> KineticBorrow · University of Caloocan City · Computer Studies Department</p>
</footer>

<!-- MODAL -->
<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
  <div class="modal">
    <button class="modal-close" onclick="closeModalDirect()">×</button>
    <div class="modal-icon">🔐</div>
    <h2 class="modal-title">Sign In to Continue</h2>
    <p class="modal-sub">Log in or create a free account to book equipment, track your rentals, and unlock exclusive discounts.</p>
    <div class="modal-btns">
      <a class="modal-login"  href="login.php">Log In</a>
      <a class="modal-signup" href="login.php?tab=register">Create Account</a>
    </div>
  </div>
</div>

<script>
  function scrollToTop()       { window.scrollTo({top:0,behavior:'smooth'}); setActive(0); }
  function scrollToEquipment() { document.getElementById('section-equipment').scrollIntoView({behavior:'smooth'}); setActive(1); }
  function scrollToPromos()    { document.getElementById('section-promos').scrollIntoView({behavior:'smooth'}); setActive(2); }
  function scrollToAbout()     { document.getElementById('section-about').scrollIntoView({behavior:'smooth'}); setActive(3); }
  function setActive(i) { document.querySelectorAll('.nav-link').forEach((l,j) => l.classList.toggle('active', i===j)); }
  function promptLogin() { document.getElementById('modal-overlay').classList.add('show'); }
  function closeModal(e) { if(e.target===document.getElementById('modal-overlay')) document.getElementById('modal-overlay').classList.remove('show'); }
  function closeModalDirect() { document.getElementById('modal-overlay').classList.remove('show'); }
  // ── SMART SEARCH DROPDOWN ──────────────────────────────────────
  const allEquipment = <?php
    $eq_js = array_map(fn($e) => [
      'name'     => $e['name'],
      'category' => $e['category'],
      'price'    => (float)$e['price_per_day'],
      'icon'     => $e['icon'] ?? '🏅',
      'image'    => $e['image'] ?? '',
      'rating'   => (float)$e['rating'],
    ], $equipment);
    echo json_encode($eq_js);
  ?>;

  const popularChips = ['Mountain Bike','Surfboard','Badminton','Kayak','Football','Boxing Gloves','Tennis','Volleyball'];
  const catIcons = {'Cycling':'🚵','Racket Sports':'🏸','Water Sports':'🏄','Combat Sports':'🥊','Team Sports':'⚽','Outdoor':'🏕️'};

  function buildDefault() {
    // Chips
    document.getElementById('sd-chips').innerHTML = popularChips.slice(0,6).map(c =>
      `<span class="sd-chip" onclick="setSearch('${c}')">${c}</span>`
    ).join('');

    // Top equipment sorted by rating
    const top = [...allEquipment].sort((a,b)=>b.rating-a.rating).slice(0,4);
    document.getElementById('sd-top-eq').innerHTML = top.map((e,i) => {
      const thumb = e.image
        ? `<img src="${e.image}" style="width:36px;height:36px;object-fit:cover;border-radius:8px;flex-shrink:0" onerror="this.outerHTML='<span style=\'font-size:22px\'>${e.icon||'🏅'}</span>'">`
        : `<span class="sd-icon">${e.icon||'🏅'}</span>`;
      return `<div class="sd-row" onclick="setSearch('${e.name}')">
        <div class="sd-badge">${i+1}</div>
        ${thumb}
        <div class="sd-info">
          <div class="sd-name">${e.name}</div>
          <div class="sd-sub">${e.category}</div>
        </div>
        <span class="sd-price">₱${e.price}/day</span>
      </div>`;
    }).join('');

    // Categories
    const cats = [...new Set(allEquipment.map(e=>e.category))];
    document.getElementById('sd-categories').innerHTML = cats.slice(0,5).map(cat => {
      const count = allEquipment.filter(e=>e.category===cat).length;
      return `<div class="sd-row" onclick="setSearch('${cat}')">
        <div class="sd-cat-box">${catIcons[cat]||'🏋️'}</div>
        <div class="sd-info">
          <div class="sd-name">${cat}</div>
          <div class="sd-sub">${count} item${count!==1?'s':''}</div>
        </div>
      </div>`;
    }).join('');
  }

  function openDropdown() {
    buildDefault();
    document.getElementById('sd-default').style.display = 'block';
    document.getElementById('sd-results').style.display  = 'none';
    document.getElementById('search-dropdown').classList.add('show');
  }

  function filterDropdown(q) {
    document.getElementById('search-dropdown').classList.add('show');
    if (!q.trim()) { openDropdown(); return; }
    document.getElementById('sd-default').style.display = 'none';
    document.getElementById('sd-results').style.display  = 'block';
    const matches = allEquipment.filter(e=>
      e.name.toLowerCase().includes(q.toLowerCase()) ||
      e.category.toLowerCase().includes(q.toLowerCase())
    ).slice(0,6);
    const list = document.getElementById('sd-results-list');
    if (!matches.length) {
      list.innerHTML = `<div class="sd-no">😕 No equipment found for "<strong>${q}</strong>"</div>`;
    } else {
      list.innerHTML = matches.map(e => {
        const thumb = e.image
          ? `<img src="${e.image}" style="width:36px;height:36px;object-fit:cover;border-radius:8px;flex-shrink:0" onerror="this.outerHTML='<span style=\'font-size:22px\'>${e.icon||'🏅'}</span>'">`
          : `<span class="sd-icon">${e.icon||'🏅'}</span>`;
        return `<div class="sd-row" onclick="setSearch('${e.name}')">
          ${thumb}
          <div class="sd-info">
            <div class="sd-name">${e.name}</div>
            <div class="sd-sub">${e.category} · ⭐ ${e.rating}</div>
          </div>
          <span class="sd-price">₱${e.price}/day</span>
        </div>`;
      }).join('');
    }
  }

  function setSearch(val) {
    document.getElementById('hero-search').value = val;
    closeDropdown(); doSearch();
  }
  function closeDropdown() { document.getElementById('search-dropdown').classList.remove('show'); }
  document.addEventListener('click', e => { if(!document.getElementById('search-wrap').contains(e.target)) closeDropdown(); });

  function doSearch() {
    const q = document.getElementById('hero-search').value.trim();
    closeDropdown();
    if (q) {
      // Filter equipment cards on the page
      let anyVisible = false;
      document.querySelectorAll('.eq-card').forEach(card => {
        const name = card.querySelector('.eq-name')?.textContent.toLowerCase()||'';
        const cat  = card.querySelector('.eq-cat')?.textContent.toLowerCase()||'';
        const match = name.includes(q.toLowerCase()) || cat.includes(q.toLowerCase());
        card.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
      });
      // Show "no results" message if nothing matches
      const noRes = document.getElementById('eq-no-results');
      if (noRes) noRes.style.display = anyVisible ? 'none' : 'block';
      scrollToEquipment();
    } else {
      // Show all if query cleared
      document.querySelectorAll('.eq-card').forEach(card => card.style.display = '');
      const noRes = document.getElementById('eq-no-results');
      if (noRes) noRes.style.display = 'none';
    }
  }

  // Clear search when input is cleared
  document.getElementById('hero-search').addEventListener('input', function() {
    if (!this.value.trim()) {
      document.querySelectorAll('.eq-card').forEach(card => card.style.display = '');
      const noRes = document.getElementById('eq-no-results');
      if (noRes) noRes.style.display = 'none';
    }
  });
</script>
</body>
</html>
