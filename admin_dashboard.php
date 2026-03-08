<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'dashboard';

// Stats
$total_users     = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_equipment = $conn->query("SELECT COUNT(*) FROM equipment WHERE is_active=1")->fetch_row()[0];
$total_rentals   = $conn->query("SELECT COUNT(*) FROM rentals")->fetch_row()[0];
$active_rentals  = $conn->query("SELECT COUNT(*) FROM rentals WHERE status='active'")->fetch_row()[0];
$revenue_total   = $conn->query("SELECT SUM(total_amount) FROM rentals WHERE status != 'cancelled'")->fetch_row()[0] ?? 0;
$revenue_month   = $conn->query("SELECT SUM(total_amount) FROM rentals WHERE status != 'cancelled' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0] ?? 0;
$pending_id      = $conn->query("SELECT COUNT(*) FROM id_verifications WHERE status='pending'")->fetch_row()[0];
$blocked_users   = $conn->query("SELECT COUNT(*) FROM users WHERE is_blocked=1")->fetch_row()[0];

// Recent rentals
$recent_rentals = $conn->query("
    SELECT r.*, u.first_name, u.last_name, e.name as eq_name, e.icon as eq_icon
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN equipment e ON r.equipment_id = e.id
    ORDER BY r.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Top equipment
$top_eq = $conn->query("
    SELECT e.name, e.icon, COUNT(r.id) as rentals, SUM(r.total_amount) as revenue
    FROM equipment e LEFT JOIN rentals r ON e.id = r.equipment_id
    GROUP BY e.id ORDER BY rentals DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<!-- STAT CARDS -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">👥</span><span class="stat-card-badge badge-blue">Users</span></div>
    <div class="stat-val"><?= number_format($total_users) ?></div>
    <div class="stat-lbl">Registered customers</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">📦</span><span class="stat-card-badge badge-gold">Active</span></div>
    <div class="stat-val"><?= number_format($active_rentals) ?></div>
    <div class="stat-lbl">Active rentals right now</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">💰</span><span class="stat-card-badge badge-green">Revenue</span></div>
    <div class="stat-val">₱<?= number_format($revenue_month, 0) ?></div>
    <div class="stat-lbl">This month's revenue</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">🪪</span><span class="stat-card-badge badge-red">Pending</span></div>
    <div class="stat-val"><?= number_format($pending_id) ?></div>
    <div class="stat-lbl">ID verifications pending</div>
  </div>
</div>

<!-- SECOND ROW STATS -->
<div class="three-col" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">🏋️</span><span class="stat-card-badge badge-blue">Inventory</span></div>
    <div class="stat-val"><?= $total_equipment ?></div>
    <div class="stat-lbl">Active equipment listings</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">📋</span><span class="stat-card-badge badge-gold">All Time</span></div>
    <div class="stat-val">₱<?= number_format($revenue_total, 0) ?></div>
    <div class="stat-lbl">Total revenue generated</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">🚫</span><span class="stat-card-badge badge-red">Blocked</span></div>
    <div class="stat-val"><?= $blocked_users ?></div>
    <div class="stat-lbl">Blocked customer accounts</div>
  </div>
</div>

<div class="two-col">
  <!-- RECENT RENTALS -->
  <div class="table-card">
    <div class="table-header">
      <span class="table-title">Recent Rentals</span>
      <a href="admin_rentals.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <table>
      <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Status</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach($recent_rentals as $r): ?>
        <tr>
          <td style="color:var(--gold);font-weight:600"><?= $r['order_code'] ?></td>
          <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
          <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
          <td><span class="status-badge s-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          <td>₱<?= number_format($r['total_amount'],0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($recent_rentals)): ?><tr class="empty-row"><td colspan="5">No rentals yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- TOP EQUIPMENT -->
  <div class="table-card">
    <div class="table-header">
      <span class="table-title">Top Equipment</span>
      <a href="admin_equipment.php" class="btn btn-outline btn-sm">Manage</a>
    </div>
    <table>
      <thead><tr><th>Equipment</th><th>Rentals</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach($top_eq as $e): ?>
        <tr>
          <td><?= $e['icon'] ?> <?= htmlspecialchars($e['name']) ?></td>
          <td><?= $e['rentals'] ?? 0 ?></td>
          <td>₱<?= number_format($e['revenue'] ?? 0, 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/admin_layout_end.php'; ?>
