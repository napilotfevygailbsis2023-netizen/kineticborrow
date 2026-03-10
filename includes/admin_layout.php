<?php
// includes/admin_layout.php
// Usage: include this after requireAdmin() in every admin page
// $active_menu must be set before including (e.g. $active_menu = 'dashboard')
$admin = getAdmin();
$menu_label = $active_menu ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KineticBorrow Admin — <?= ucfirst($menu_label) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;
      --green:#2E8B57;--green-bg:#EAF6EE;
      --red:#C0392B;--red-bg:#FDECEA;
      --blue:#2563EB;--blue-bg:#EEF3FD;
      --orange:#E07C35;--orange-bg:#FEF3E2;
      --sidebar:#1C1916;--sidebar2:#2A2420;--sidebar3:#3A3028;
      --bg:#F7F5F2;--surface:#fff;--border:#E5E0D8;
      --muted:#8A8078;--text:#1C1916;--text2:#4A4540;
    }
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}

    /* ── SIDEBAR ── */
    .sidebar{width:240px;background:var(--sidebar);flex-shrink:0;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;}
    .sidebar-brand{padding:22px 20px 18px;border-bottom:1px solid var(--sidebar3);}
    .sidebar-brand .brand-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:#fff;}
    .sidebar-brand .brand-name span{color:var(--gold);}
    .sidebar-brand .brand-sub{font-size:11px;color:#666;margin-top:2px;letter-spacing:.06em;text-transform:uppercase;}
    .sidebar-admin{padding:14px 20px;border-bottom:1px solid var(--sidebar3);display:flex;align-items:center;gap:10px;}
    .admin-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
    .admin-info .admin-name{font-size:13px;color:#fff;font-weight:600;}
    .admin-info .admin-role{font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.06em;}
    .sidebar-nav{flex:1;padding:14px 0;}
    .nav-section{padding:8px 20px 4px;font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.1em;font-weight:600;}
    .nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:#AAA;font-size:13px;font-weight:500;cursor:pointer;transition:all .18s;text-decoration:none;border-left:3px solid transparent;}
    .nav-item:hover{background:var(--sidebar2);color:#fff;border-left-color:var(--sidebar3);}
    .nav-item.active{background:var(--sidebar2);color:#fff;border-left-color:var(--gold);}
    .nav-item .nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0;}
    .sidebar-footer{padding:16px 20px;border-top:1px solid var(--sidebar3);}
    .logout-link{display:flex;align-items:center;gap:8px;color:#888;font-size:13px;cursor:pointer;text-decoration:none;transition:color .18s;}
    .logout-link:hover{color:var(--red);}

    /* ── MAIN ── */
    .main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
    .topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:0 1px 6px rgba(0,0,0,.05);}
    .topbar-title{font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:var(--text);}
    .topbar-right{display:flex;align-items:center;gap:14px;}
    .topbar-date{font-size:12px;color:var(--muted);}

    /* ── CONTENT AREA ── */
    .content{padding:28px 32px;flex:1;}

    /* ── STAT CARDS ── */
    .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
    .stat-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px 22px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .stat-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
    .stat-card-icon{font-size:24px;}
    .stat-card-badge{font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;}
    .badge-green{background:var(--green-bg);color:var(--green);}
    .badge-red{background:var(--red-bg);color:var(--red);}
    .badge-blue{background:var(--blue-bg);color:var(--blue);}
    .badge-gold{background:var(--gold-bg);color:var(--gold);}
    .stat-val{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;color:var(--text);margin-bottom:4px;}
    .stat-lbl{font-size:12px;color:var(--muted);}

    /* ── TABLES ── */
    .table-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.04);margin-bottom:20px;}
    .table-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .table-title{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;}
    table{width:100%;border-collapse:collapse;}
    th{background:#FAFAF8;padding:11px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);}
    td{padding:13px 16px;font-size:13px;border-bottom:1px solid #F5F2EE;vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:#FDFCFA;}

    /* ── BADGES ── */
    .status-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
    .s-active{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .s-returned{background:#F2F0EE;color:var(--muted);border:1px solid var(--border);}
    .s-cancelled{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .s-paid{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .s-pending{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}
    .s-refunded{background:var(--blue-bg);color:var(--blue);border:1px solid #C0D4F8;}
    .s-flagged{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}
    .s-suspended{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .s-approved{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .s-rejected{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .s-escalated{background:var(--blue-bg);color:var(--blue);border:1px solid #C0D4F8;}
    .s-on{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .s-off{background:#F2F0EE;color:var(--muted);border:1px solid var(--border);}

    /* ── BUTTONS ── */
    .btn{padding:7px 16px;border-radius:8px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;border:none;transition:all .18s;text-decoration:none;display:inline-block;}
    .btn-gold{background:var(--gold);color:#fff;}
    .btn-gold:hover{background:var(--gold-lt);}
    .btn-outline{background:none;border:1px solid var(--border);color:var(--text2);}
    .btn-outline:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-bg);}
    .btn-red{background:var(--red);color:#fff;}
    .btn-red:hover{background:#A93226;}
    .btn-green{background:var(--green);color:#fff;}
    .btn-green:hover{background:#256B45;}
    .btn-blue{background:var(--blue);color:#fff;}
    .btn-blue:hover{background:#1E50C8;}
    .btn-sm{padding:5px 12px;font-size:11px;}
    .btn-group{display:flex;gap:6px;}

    /* ── FORMS ── */
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-group{margin-bottom:16px;}
    .form-label{display:block;font-size:12px;font-weight:600;color:var(--text2);letter-spacing:.04em;text-transform:uppercase;margin-bottom:7px;}
    .form-control{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .form-control::placeholder{color:#C0B8AE;}
    select.form-control{cursor:pointer;}

    /* ── MODAL ── */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;}
    .modal-overlay.show{opacity:1;pointer-events:all;}
    .modal{background:#fff;border-radius:18px;padding:28px 30px;width:520px;max-height:90vh;overflow-y:auto;transform:translateY(16px);transition:transform .2s;box-shadow:0 20px 60px rgba(0,0,0,.15);}
    .modal-overlay.show .modal{transform:translateY(0);}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
    .modal-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:800;}
    .modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:#C0B8AE;line-height:1;}
    .modal-close:hover{color:var(--red);}
    .modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:22px;padding-top:18px;border-top:1px solid var(--border);}

    /* ── ALERTS ── */
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .alert-error{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}

    /* ── SEARCH BAR ── */
    .search-bar{display:flex;gap:10px;align-items:center;margin-bottom:20px;}
    .search-wrap{position:relative;flex:1;max-width:320px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;color:#C0B8AE;pointer-events:none;}
    .search-input{background:#fff;border:1px solid var(--border);border-radius:10px;padding:9px 14px 9px 38px;color:var(--text);outline:none;width:100%;font-family:'DM Sans',sans-serif;font-size:13px;transition:all .2s;}
    .search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .search-input::placeholder{color:#C0B8AE;}

    /* ── MISC ── */
    .page-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
    .page-head-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;}
    .page-head-sub{font-size:13px;color:var(--muted);margin-top:2px;}
    .empty-row td{text-align:center;padding:32px;color:var(--muted);font-size:13px;}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;}

    ::-webkit-scrollbar{width:5px;}
    ::-webkit-scrollbar-track{background:var(--bg);}
    ::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name">Kinetic<span>Borrow</span></div>
    <div class="brand-sub">Admin Panel</div>
  </div>
  <div class="sidebar-admin">
    <div class="admin-avatar"><?= strtoupper(substr($admin['name'],0,1)) ?></div>
    <div class="admin-info">
      <div class="admin-name"><?= htmlspecialchars($admin['name']) ?></div>
      <div class="admin-role"><?= htmlspecialchars($admin['role']) ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a class="nav-item <?= $menu_label==='dashboard'?'active':'' ?>" href="admin_dashboard.php">
      <span class="nav-icon">📊</span> Dashboard
    </a>

    <div class="nav-section">Management</div>
    <a class="nav-item <?= $menu_label==='equipment'?'active':'' ?>" href="admin_equipment.php">
      <span class="nav-icon">🏋️</span> Equipment
    </a>
    <a class="nav-item <?= $menu_label==='customers'?'active':'' ?>" href="admin_customers.php">
      <span class="nav-icon">👥</span> Customers
    </a>
    <a class="nav-item <?= $menu_label==='rentals'?'active':'' ?>" href="admin_rentals.php">
      <span class="nav-icon">📦</span> Rentals & Orders
    </a>
    <a class="nav-item <?= $menu_label==='id_verify'?'active':'' ?>" href="admin_id_verify.php">
      <span class="nav-icon">🪪</span> ID Verification
    </a>

    <div class="nav-section">Operations</div>
    <a class="nav-item <?= $menu_label==='blocklist'?'active':'' ?>" href="admin_blocklist.php">
      <span class="nav-icon">🚫</span> Blocklist
    </a>
    <a class="nav-item <?= $menu_label==='promotions'?'active':'' ?>" href="admin_promotions.php">
      <span class="nav-icon">🎁</span> Promotions
    </a>
    <a class="nav-item <?= $menu_label==='incidents'?'active':'' ?>" href="admin_incidents.php">
      <span class="nav-icon">🚨</span> Incidents
    </a>
    <a class="nav-item <?= $menu_label==='reports'?'active':'' ?>" href="admin_reports.php">
      <span class="nav-icon">📈</span> Reports
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-link" href="admin_logout.php">
      <span>🚪</span> Log Out
    </a>
  </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <span class="topbar-title"><?= ucfirst(str_replace('_',' ',$menu_label)) ?></span>
    <div class="topbar-right">
      <span class="topbar-date"><?= date('l, F j, Y') ?></span>
      <a href="index.php" target="_blank" style="font-size:12px;color:var(--muted);text-decoration:none;">🔗 View Site</a>
    </div>
  </div>
  <div class="content">
