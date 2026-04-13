<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'blocklist';

$search = $conn->real_escape_string($_GET['q'] ?? '');
$sql = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone, u.id_type
        FROM blocklist b
        JOIN users u ON b.user_id = u.id
        WHERE b.status IN ('flagged','suspended')";
if ($search) $sql .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%')";
$sql .= " ORDER BY b.status DESC, b.updated_at DESC";
$blocklist = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

include 'includes/handler_layout.php';
?>

<div class="page-head">
  <div>
    <div class="page-head-title">Blocklist — Read Only</div>
    <div class="page-head-sub">Check this list before releasing equipment to any customer</div>
  </div>
  <div style="background:var(--red-bg);border:1px solid #F5C6C2;border-radius:10px;padding:10px 16px;font-size:13px;color:var(--red);font-weight:600">
    🚫 Do NOT release equipment to any customer listed here
  </div>
</div>

<!-- WARNING BANNER -->
<div class="alert alert-warn" style="display:flex;align-items:center;gap:12px">
  <span style="font-size:24px">⚠️</span>
  <div>
    <strong>Handler Notice:</strong> Always verify the customer's name against this list before processing any check-out.
    If a customer on this list attempts to pick up equipment, do not release it and contact your admin immediately.
  </div>
</div>

<!-- SEARCH -->
<div class="search-bar">
  <form method="GET" style="display:contents">
    <div class="search-wrap"><span class="search-icon">🔍</span><input class="search-input" name="q" placeholder="Search name, email, or phone..." value="<?= htmlspecialchars($search) ?>"/></div>
    <button type="submit" class="btn btn-teal btn-sm">Search</button>
    <?php if($search): ?><a href="handler_blocklist.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<!-- BLOCKLIST TABLE -->
<div class="table-card">
  <div class="table-header">
    <span class="table-title">Active Blocked Accounts</span>
    <span style="font-size:12px;color:var(--red);font-weight:600"><?= count($blocklist) ?> blocked account<?= count($blocklist)!==1?'s':'' ?></span>
  </div>
  <table>
    <thead><tr><th>Customer Name</th><th>Email</th><th>Phone</th><th>ID Type</th><th>Status</th><th>Reason</th><th>Since</th></tr></thead>
    <tbody>
      <?php foreach($blocklist as $b): ?>
      <tr style="background:<?= $b['status']==='suspended'?'#FFF5F5':'#FFFBF5' ?>">
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:18px"><?= $b['status']==='suspended'?'🚫':'⚠️' ?></span>
            <div>
              <div style="font-weight:700;color:<?= $b['status']==='suspended'?'var(--red)':'var(--orange)' ?>"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)">User ID #<?= $b['user_id'] ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:13px"><?= htmlspecialchars($b['email']) ?></td>
        <td style="font-size:13px"><?= htmlspecialchars($b['phone']) ?></td>
        <td>
          <?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; ?>
          <?= $icons[$b['id_type']]??'🪪' ?> <?= ucfirst($b['id_type']) ?>
        </td>
        <td><span class="status-badge s-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
        <td style="font-size:12px;color:var(--muted);max-width:180px"><?= htmlspecialchars($b['reason']) ?></td>
        <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= date('M j, Y', strtotime($b['updated_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($blocklist)): ?>
      <tr class="empty-row">
        <td colspan="7">
          <p style="font-size:22px;margin-bottom:8px">✅</p>
          <p>No active blocked accounts<?= $search?' matching your search':'' ?>.</p>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- INSTRUCTIONS CARD -->
<div style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px;box-shadow:0 1px 6px rgba(0,0,0,.04)">
  <p style="font-family:'Playfair Display',serif;font-size:16px;font-weight:800;color:var(--text);margin-bottom:14px">📋 What to do if a blocked customer shows up</p>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:13px;color:var(--text2)">
    <div style="background:var(--bg);border-radius:10px;padding:14px">
      <p style="font-weight:700;margin-bottom:8px;color:var(--red)">🚫 DO NOT:</p>
      <ul style="margin-left:16px;line-height:2">
        <li>Release equipment to a blocked customer</li>
        <li>Accept payment on behalf of admin</li>
        <li>Promise future resolution to the customer</li>
      </ul>
    </div>
    <div style="background:var(--bg);border-radius:10px;padding:14px">
      <p style="font-weight:700;margin-bottom:8px;color:var(--green)">✅ DO:</p>
      <ul style="margin-left:16px;line-height:2">
        <li>Politely inform the customer their account has been restricted</li>
        <li>Direct them to contact the admin for resolution</li>
        <li>Log the interaction as an incident report if needed</li>
      </ul>
    </div>
  </div>
</div>

<?php include 'includes/handler_layout_end.php'; ?>
