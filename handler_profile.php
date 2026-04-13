<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'profile';
$hid = $_SESSION['handler_id'];

$handler = $conn->query("SELECT * FROM handlers WHERE id=$hid")->fetch_assoc();

// Stats
$total_checkouts = $conn->query("SELECT COUNT(*) FROM rentals WHERE checkout_by=$hid")->fetch_row()[0];
$total_checkins  = $conn->query("SELECT COUNT(*) FROM rentals WHERE checkin_by=$hid")->fetch_row()[0];
$total_incidents = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE handler_id=$hid")->fetch_row()[0];
$today = date('Y-m-d');
$today_checkouts = $conn->query("SELECT COUNT(*) FROM rentals WHERE checkout_by=$hid AND DATE(checkout_at)='$today'")->fetch_row()[0];

include 'includes/handler_layout.php';
?>

<div class="page-head">
  <div>
    <div class="page-head-title">My Profile</div>
    <div class="page-head-sub">Your handler account information</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

  <!-- PROFILE CARD -->
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:28px 24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.05)">
    <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--teal-dk));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;margin:0 auto 16px;border:4px solid var(--teal-bg)">
      <?= strtoupper(substr($handler['name'],0,1)) ?>
    </div>
    <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:var(--text);margin-bottom:4px">
      <?= htmlspecialchars($handler['name']) ?>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:6px">Equipment Handler</div>
    <span class="status-badge s-active" style="font-size:11px">🟢 Active</span>

    <div style="margin-top:22px;border-top:1px solid var(--border);padding-top:18px;text-align:left">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--muted);margin-bottom:12px">Account Details</div>
      <div style="font-size:13px;color:var(--text2);line-height:2.2">
        <div>📧 <strong>Email:</strong> <?= htmlspecialchars($handler['email']) ?></div>
        <div>🆔 <strong>Handler ID:</strong> #<?= str_pad($hid, 4, '0', STR_PAD_LEFT) ?></div>
        <div>📅 <strong>Member Since:</strong> <?= date('F j, Y', strtotime($handler['created_at'])) ?></div>
      </div>
    </div>

    <div style="margin-top:16px;background:var(--teal-bg);border-radius:10px;padding:11px 14px;font-size:12px;color:var(--teal);text-align:left">
      🔒 Profile details are managed by the system administrator. Contact admin to update your information.
    </div>
  </div>

  <!-- RIGHT COLUMN -->
  <div>
    <!-- STATS -->
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:18px">
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">✅</span><span class="stat-badge badge-teal">All Time</span></div>
        <div class="stat-val"><?= $total_checkouts ?></div>
        <div class="stat-lbl">Total Check-Outs</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">📦</span><span class="stat-badge badge-green">All Time</span></div>
        <div class="stat-val"><?= $total_checkins ?></div>
        <div class="stat-lbl">Total Check-Ins</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">🚨</span><span class="stat-badge badge-orange">Filed</span></div>
        <div class="stat-val"><?= $total_incidents ?></div>
        <div class="stat-lbl">Incident Reports</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">⚡</span><span class="stat-badge badge-teal">Today</span></div>
        <div class="stat-val"><?= $today_checkouts ?></div>
        <div class="stat-lbl">Check-Outs Today</div>
      </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div class="table-card">
      <div class="table-header"><span class="table-title">🕐 Recent Activity</span></div>
      <table>
        <thead><tr><th>Order</th><th>Equipment</th><th>Action</th><th>Date & Time</th></tr></thead>
        <tbody>
          <?php
          $recent = $conn->query("
            SELECT r.order_code, e.name as eq_name, e.icon as eq_icon,
                   'Check-Out' as action, r.checkout_at as action_time
            FROM rentals r JOIN equipment e ON r.equipment_id=e.id
            WHERE r.checkout_by=$hid AND r.checkout_at IS NOT NULL
            UNION ALL
            SELECT r.order_code, e.name as eq_name, e.icon as eq_icon,
                   'Check-In' as action, r.checkin_at as action_time
            FROM rentals r JOIN equipment e ON r.equipment_id=e.id
            WHERE r.checkin_by=$hid AND r.checkin_at IS NOT NULL
            ORDER BY action_time DESC LIMIT 10
          ")->fetch_all(MYSQLI_ASSOC);
          foreach($recent as $a): ?>
          <tr>
            <td style="color:var(--teal);font-weight:700"><?= $a['order_code'] ?></td>
            <td><?= $a['eq_icon'] ?> <?= htmlspecialchars($a['eq_name']) ?></td>
            <td>
              <span class="status-badge <?= $a['action']==='Check-Out'?'s-active':'s-returned' ?>">
                <?= $a['action']==='Check-Out'?'✅':'📦' ?> <?= $a['action'] ?>
              </span>
            </td>
            <td style="font-size:12px;color:var(--muted)"><?= date('M j, Y g:i A', strtotime($a['action_time'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recent)): ?><tr class="empty-row"><td colspan="4">No activity yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'includes/handler_layout_end.php'; ?>
