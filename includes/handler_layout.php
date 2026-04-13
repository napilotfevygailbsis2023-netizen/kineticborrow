<?php
// includes/handler_layout.php
// $active_menu must be set before including
$handler = getHandler();
$menu_label = $active_menu ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>KineticBorrow Handler — <?= ucfirst(str_replace('_',' ',$menu_label)) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --teal:#0E7C86;--teal-dk:#095E66;--teal-lt:#5AABB2;--teal-bg:#E8F6F7;
      --gold:#C47F2B;--gold-bg:#FDF3E3;
      --green:#2E8B57;--green-bg:#EAF6EE;
      --red:#C0392B;--red-bg:#FDECEA;
      --orange:#E07C35;--orange-bg:#FEF3E2;
      --blue:#2563EB;--blue-bg:#EEF3FD;
      --purple:#7C3AED;--purple-bg:#F3EFFD;
      --sidebar:#0D2B2D;--sidebar2:#0E3E42;--sidebar3:#155A60;
      --bg:#F2F7F7;--surface:#fff;--border:#D0E4E6;
      --muted:#6A8E90;--text:#0D2B2D;--text2:#2A5A5C;
    }
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}

    /* SIDEBAR */
    .sidebar{width:240px;background:var(--sidebar);flex-shrink:0;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;}
    .sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid var(--sidebar3);}
    .brand-name{font-family:'Playfair Display',serif;font-size:17px;font-weight:800;color:#fff;}
    .brand-name span{color:var(--gold);}
    .brand-sub{font-size:10px;color:var(--teal-lt);margin-top:2px;letter-spacing:.08em;text-transform:uppercase;}
    .sidebar-handler{display:block;}
    .handler-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--teal-dk));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
    .handler-name{font-size:13px;color:#fff;font-weight:600;}
    .handler-role{font-size:10px;color:var(--teal-lt);text-transform:uppercase;letter-spacing:.06em;}
    .sidebar-nav{flex:1;padding:12px 0;}
    .nav-section{padding:8px 20px 4px;font-size:10px;color:#3A7A80;text-transform:uppercase;letter-spacing:.1em;font-weight:600;}
    .nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:#7ABFC5;font-size:13px;font-weight:500;cursor:pointer;transition:all .18s;text-decoration:none;border-left:3px solid transparent;}
    .nav-item:hover{background:var(--sidebar2);color:#fff;border-left-color:var(--sidebar3);}
    .nav-item.active{background:var(--sidebar2);color:#fff;border-left-color:var(--teal-lt);}
    .nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0;}
    .sidebar-footer{padding:14px 20px;border-top:1px solid var(--sidebar3);}
    .logout-link{display:flex;align-items:center;gap:8px;color:#5A9A9E;font-size:13px;text-decoration:none;transition:color .18s;}
    .logout-link:hover{color:var(--red);}

    /* MAIN */
    .main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
    .topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 28px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .topbar-title{font-family:'Playfair Display',serif;font-size:19px;font-weight:800;color:var(--text);}
    .topbar-right{display:flex;align-items:center;gap:14px;}
    .topbar-date{font-size:12px;color:var(--muted);}
    .topbar-badge{background:var(--teal-bg);color:var(--teal);border:1px solid #B0D8DB;border-radius:20px;padding:4px 12px;font-size:11px;font-weight:600;}
    .content{padding:24px 28px;flex:1;}

    /* CARDS */
    .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
    .stat-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .stat-icon{font-size:22px;}
    .stat-badge{font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;}
    .badge-teal{background:var(--teal-bg);color:var(--teal);}
    .badge-green{background:var(--green-bg);color:var(--green);}
    .badge-red{background:var(--red-bg);color:var(--red);}
    .badge-orange{background:var(--orange-bg);color:var(--orange);}
    .badge-gold{background:var(--gold-bg);color:var(--gold);}
    .stat-val{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;color:var(--text);margin-bottom:3px;}
    .stat-lbl{font-size:12px;color:var(--muted);}

    /* TABLES */
    .table-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.04);margin-bottom:18px;}
    .table-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .table-title{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:var(--text);}
    table{width:100%;border-collapse:collapse;}
    th{background:#F0F7F8;padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);}
    td{padding:12px 16px;font-size:13px;border-bottom:1px solid #EBF4F5;vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:#F8FCFC;}
    .empty-row td{text-align:center;padding:30px;color:var(--muted);font-size:13px;}

    /* BADGES */
    .status-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
    .s-active{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .s-returned{background:#EFF6F7;color:var(--muted);border:1px solid var(--border);}
    .s-cancelled{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .s-pending{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}
    .s-open{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .s-reviewed{background:var(--blue-bg);color:var(--blue);border:1px solid #C0D4F8;}
    .s-resolved{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .s-excellent{background:#EAF6EE;color:#2E8B57;border:1px solid #C0E0CC;}
    .s-good{background:var(--teal-bg);color:var(--teal);border:1px solid #B0D8DB;}
    .s-fair{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}
    .s-poor{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .s-flagged{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}
    .s-suspended{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .severity-minor{background:#EAF6EE;color:#2E8B57;border:1px solid #C0E0CC;}
    .severity-moderate{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}
    .severity-severe{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}

    /* BUTTONS */
    .btn{padding:7px 15px;border-radius:8px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;border:none;transition:all .18s;text-decoration:none;display:inline-block;}
    .btn-teal{background:var(--teal);color:#fff;}
    .btn-teal:hover{background:var(--teal-dk);}
    .btn-green{background:var(--green);color:#fff;}
    .btn-green:hover{background:#256B45;}
    .btn-red{background:var(--red);color:#fff;}
    .btn-red:hover{background:#A93226;}
    .btn-orange{background:var(--orange);color:#fff;}
    .btn-outline{background:none;border:1px solid var(--border);color:var(--text2);}
    .btn-outline:hover{border-color:var(--teal);color:var(--teal);background:var(--teal-bg);}
    .btn-sm{padding:5px 11px;font-size:11px;}
    .btn-group{display:flex;gap:6px;flex-wrap:wrap;}

    /* FORMS */
    .form-group{margin-bottom:16px;}
    .form-label{display:block;font-size:11px;font-weight:700;color:var(--text2);letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px;}
    .form-control{width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(14,124,134,.1);}
    .form-control::placeholder{color:#A0C4C6;}
    select.form-control{cursor:pointer;}
    .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

    /* MODALS */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(3px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;}
    .modal-overlay.show{opacity:1;pointer-events:all;}
    .modal{background:#fff;border-radius:18px;padding:26px 28px;width:500px;max-height:88vh;overflow-y:auto;transform:translateY(14px);transition:transform .2s;box-shadow:0 20px 60px rgba(0,0,0,.15);}
    .modal-overlay.show .modal{transform:translateY(0);}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
    .modal-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:var(--text);}
    .modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:#A0C4C6;line-height:1;}
    .modal-close:hover{color:var(--red);}
    .modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);}
    .modal-info{background:var(--bg);border-radius:10px;padding:13px;margin-bottom:18px;font-size:13px;line-height:1.7;color:var(--text2);}

    /* CONDITION STARS */
    .condition-selector{display:flex;gap:8px;margin-bottom:4px;}
    .cond-btn{flex:1;padding:10px 8px;border:2px solid var(--border);border-radius:10px;text-align:center;cursor:pointer;transition:all .18s;font-size:12px;font-weight:600;background:#fff;}
    .cond-btn:hover{border-color:var(--teal);}
    .cond-btn.selected{border-color:var(--teal);background:var(--teal-bg);color:var(--teal);}

    /* ALERTS */
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;font-weight:500;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .alert-error{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .alert-warn{background:var(--orange-bg);color:var(--orange);border:1px solid #FADDB8;}

    /* MISC */
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;}
    .page-head-title{font-family:'Playfair Display',serif;font-size:21px;font-weight:800;color:var(--text);}
    .page-head-sub{font-size:13px;color:var(--muted);margin-top:2px;}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
    .search-bar{display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap;}
    .search-wrap{position:relative;min-width:220px;}
    .search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:13px;color:#A0C4C6;pointer-events:none;}
    .search-input{background:#fff;border:1px solid var(--border);border-radius:10px;padding:8px 13px 8px 34px;color:var(--text);outline:none;width:100%;font-family:'DM Sans',sans-serif;font-size:13px;transition:all .2s;}
    .search-input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,124,134,.1);}

    ::-webkit-scrollbar{width:5px;}
    ::-webkit-scrollbar-track{background:var(--bg);}
    ::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name">Kinetic<span>Borrow</span></div>
    <div class="brand-sub">Handler Portal</div>
  </div>
  <a href="handler_profile.php" style="display:flex;align-items:center;gap:10px;padding:13px 20px;border-bottom:1px solid var(--sidebar3);text-decoration:none;cursor:pointer;transition:background .18s;background:transparent" onmouseover="this.style.background='#0E3E42'" onmouseout="this.style.background='transparent'" title="View My Profile">
    <div class="handler-avatar"><?= strtoupper(substr($handler['name'],0,1)) ?></div>
    <div>
      <div class="handler-name"><?= htmlspecialchars($handler['name']) ?></div>
      <div class="handler-role">Equipment Handler · View Profile →</div>
    </div>
  </a>
  <nav class="sidebar-nav">
    <div class="nav-section">Operations</div>
    <a class="nav-item <?= $menu_label==='dashboard'?'active':'' ?>" href="handler_dashboard.php">
      <span class="nav-icon">📋</span> Daily Queue
    </a>
    <a class="nav-item <?= $menu_label==='checkout'?'active':'' ?>" href="handler_checkout.php">
      <span class="nav-icon">✅</span> Check-Out
    </a>
    <a class="nav-item <?= $menu_label==='checkin'?'active':'' ?>" href="handler_checkin.php">
      <span class="nav-icon">📦</span> Check-In / Returns
    </a>
    <div class="nav-section">Tools</div>
    <a class="nav-item <?= $menu_label==='incidents'?'active':'' ?>" href="handler_incidents.php">
      <span class="nav-icon">🚨</span> Incident Reports
    </a>
    <a class="nav-item <?= $menu_label==='chat'?'active':'' ?>" href="handler_chat.php">
      <span class="nav-icon">💬</span> Messages
    </a>
    <a class="nav-item <?= $menu_label==='blocklist'?'active':'' ?>" href="handler_blocklist.php">
      <span class="nav-icon">🚫</span> Blocklist (View Only)
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-link" href="handler_logout.php"><span>🚪</span> Log Out</a>
  </div>
</aside>

<div class="main">
  <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;padding:14px 28px 0 28px;">
    <span style="font-size:12px;color:var(--muted);"><?= date('l, F j, Y') ?></span>
    <span style="background:var(--teal-bg);color:var(--teal);border:1px solid #B0D8DB;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;">🔧 Handler</span>
  </div>
  <div class="content">
