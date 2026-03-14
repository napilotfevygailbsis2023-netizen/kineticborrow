<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'dashboard';
$hid = $_SESSION['handler_id'];
$today = date('Y-m-d');
$page_title = 'Dashboard';

// Today's pickups (active rentals starting today or overdue)
$pickups = $conn->query("
    SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.is_blocked,
           e.name as eq_name, e.icon as eq_icon, e.category
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.status = 'active' AND r.checkout_by IS NULL
      AND r.start_date <= '$today'
    ORDER BY r.start_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Today's expected returns
$returns = $conn->query("
    SELECT r.*, u.first_name, u.last_name, u.email, u.phone,
           e.name as eq_name, e.icon as eq_icon
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.status = 'active' AND r.checkout_by IS NOT NULL
      AND r.end_date <= '$today'
    ORDER BY r.end_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Upcoming (next 30 days)
$upcoming = $conn->query("
    SELECT r.*, u.first_name, u.last_name, e.name as eq_name, e.icon as eq_icon
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE r.status = 'active' AND r.checkout_by IS NULL
      AND r.start_date > '$today' AND r.start_date <= DATE_ADD('$today', INTERVAL 30 DAY)
    ORDER BY r.start_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Stats
$my_checkouts_today = $conn->query("SELECT COUNT(*) FROM rentals WHERE checkout_by=$hid AND DATE(checkout_at)='$today'")->fetch_row()[0];
$my_checkins_today  = $conn->query("SELECT COUNT(*) FROM rentals WHERE checkin_by=$hid  AND DATE(checkin_at)='$today'")->fetch_row()[0];
$open_incidents     = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status='open'")->fetch_row()[0];

include 'includes/handler_layout.php';
?>

<!-- STAT CARDS -->
<div style="margin-bottom:8px">
  <h2 style="font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--text);margin:0 0 16px 0">Dashboard</h2>
</div>
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span class="stat-icon">📋</span><span class="stat-badge badge-orange">Today</span></div>
    <div class="stat-val"><?= count($pickups) ?></div>
    <div class="stat-lbl">Pending pick-ups</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-icon">📦</span><span class="stat-badge badge-teal">Due</span></div>
    <div class="stat-val"><?= count($returns) ?></div>
    <div class="stat-lbl">Returns due today</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-icon">✅</span><span class="stat-badge badge-green">My Activity</span></div>
    <div class="stat-val"><?= $my_checkouts_today ?></div>
    <div class="stat-lbl">My check-outs today</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-icon">🚨</span><span class="stat-badge badge-red">Open</span></div>
    <div class="stat-val"><?= $open_incidents ?></div>
    <div class="stat-lbl">Open incident reports</div>
  </div>
</div>

<!-- PENDING PICK-UPS -->
<div class="table-card">
  <div class="table-header">
    <span class="table-title">⏳ Pending Pick-Ups — Today & Overdue</span>
    <a href="handler_checkout.php" class="btn btn-teal btn-sm">Go to Check-Out →</a>
  </div>
  <table>
    <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Start Date</th><th>Days</th><th>Alert</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($pickups as $r): ?>
      <?php $overdue = $r['start_date'] < $today; ?>
      <tr style="<?= $overdue?'background:#FFF8F8':'' ?>">
        <td style="color:var(--teal);font-weight:700"><?= $r['order_code'] ?></td>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['phone']) ?></div>
        </td>
        <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
        <td><?= date('M j, Y', strtotime($r['start_date'])) ?></td>
        <td><?= $r['days'] ?> day<?= $r['days']>1?'s':'' ?></td>
        <td>
          <?php if($r['is_blocked']): ?>
            <span class="status-badge s-suspended">🚫 BLOCKED</span>
          <?php elseif($overdue): ?>
            <span class="status-badge s-pending">⚠️ Overdue</span>
          <?php else: ?>
            <span class="status-badge s-active">Today</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($r['is_blocked']): ?>
            <span style="font-size:12px;color:var(--red);font-weight:600">Cannot release</span>
          <?php else: ?>
            <a href="handler_checkout.php?rental_id=<?= $r['id'] ?>" class="btn btn-green btn-sm">Check Out</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($pickups)): ?><tr class="empty-row"><td colspan="7">✅ No pending pick-ups for today.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="two-col">
  <!-- RETURNS DUE -->
  <div class="table-card">
    <div class="table-header">
      <span class="table-title">📦 Returns Due Today</span>
      <a href="handler_checkin.php" class="btn btn-outline btn-sm">View All Returns</a>
    </div>
    <table>
      <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Due</th></tr></thead>
      <tbody>
        <?php foreach($returns as $r): ?>
        <tr>
          <td style="color:var(--teal);font-weight:700"><?= $r['order_code'] ?></td>
          <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
          <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
          <td style="color:<?= $r['end_date']<$today?'var(--red)':'var(--text)' ?>;font-weight:600"><?= date('M j', strtotime($r['end_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($returns)): ?><tr class="empty-row"><td colspan="4">No returns due today.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- UPCOMING (30 DAYS) -->
  <div class="table-card">
    <div class="table-header"><span class="table-title">📅 Upcoming (Next 30 Days)</span></div>
    <table>
      <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Pick-up</th></tr></thead>
      <tbody>
        <?php foreach($upcoming as $r): ?>
        <tr>
          <td style="color:var(--teal);font-weight:700"><?= $r['order_code'] ?></td>
          <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
          <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
          <td><?= date('M j (D)', strtotime($r['start_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($upcoming)): ?><tr class="empty-row"><td colspan="4">No upcoming reservations.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/handler_layout_end.php'; ?>
