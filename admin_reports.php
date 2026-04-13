<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'reports';

// Date filters
$month = intval($_GET['month'] ?? date('n'));
$year  = intval($_GET['year']  ?? date('Y'));

// Revenue summary
$rev_total  = $conn->query("SELECT SUM(total_amount) FROM rentals WHERE status!='cancelled'")->fetch_row()[0] ?? 0;
$rev_month  = $conn->query("SELECT SUM(total_amount) FROM rentals WHERE status!='cancelled' AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_row()[0] ?? 0;
$rev_disc   = $conn->query("SELECT SUM(total_amount * discount_pct / 100) FROM rentals WHERE discount_pct > 0 AND status!='cancelled'")->fetch_row()[0] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) FROM rentals WHERE MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_row()[0];
$ret_orders   = $conn->query("SELECT COUNT(*) FROM rentals WHERE status='returned' AND MONTH(created_at)=$month AND YEAR(created_at)=$year")->fetch_row()[0];

// Monthly revenue by month (last 6 months)
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') as label,
           MONTH(created_at) as mo, YEAR(created_at) as yr,
           SUM(total_amount) as revenue, COUNT(*) as orders
    FROM rentals WHERE status != 'cancelled'
    GROUP BY yr, mo ORDER BY yr DESC, mo DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Revenue by equipment
$by_equipment = $conn->query("
    SELECT e.name, e.icon, COUNT(r.id) as orders, SUM(r.total_amount) as revenue,
           AVG(r.days) as avg_days
    FROM equipment e LEFT JOIN rentals r ON e.id = r.equipment_id AND r.status != 'cancelled'
    GROUP BY e.id ORDER BY revenue DESC
")->fetch_all(MYSQLI_ASSOC);

// Revenue by category
$by_category = $conn->query("
    SELECT e.category, COUNT(r.id) as orders, SUM(r.total_amount) as revenue
    FROM equipment e LEFT JOIN rentals r ON e.id = r.equipment_id AND r.status != 'cancelled'
    GROUP BY e.category ORDER BY revenue DESC
")->fetch_all(MYSQLI_ASSOC);

// Payment status breakdown
$pay_paid     = $conn->query("SELECT COUNT(*),SUM(total_amount) FROM rentals WHERE payment_status='paid'")->fetch_row();
$pay_pending  = $conn->query("SELECT COUNT(*),SUM(total_amount) FROM rentals WHERE payment_status='pending'")->fetch_row();
$pay_refunded = $conn->query("SELECT COUNT(*),SUM(total_amount) FROM rentals WHERE payment_status='refunded'")->fetch_row();

// ID type discount breakdown
$disc_breakdown = $conn->query("
    SELECT u.id_type, COUNT(r.id) as orders,
           SUM(r.total_amount) as revenue,
           SUM(r.total_amount * r.discount_pct / 100) as discount_given
    FROM rentals r JOIN users u ON r.user_id = u.id
    WHERE r.status != 'cancelled'
    GROUP BY u.id_type
")->fetch_all(MYSQLI_ASSOC);

// All rentals for the selected month (for summary table)
$month_rentals = $conn->query("
    SELECT r.*, u.first_name, u.last_name, e.name as eq_name, e.icon as eq_icon
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN equipment e ON r.equipment_id = e.id
    WHERE MONTH(r.created_at)=$month AND YEAR(r.created_at)=$year
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);">Reports & Revenue</div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;">Rental and revenue summaries, payment status, discount analytics</div>
  </div>
</div>


<!-- FILTERS -->
<div style="display:flex;justify-content:flex-end;margin-bottom:16px"><form method="GET" style="display:flex;gap:8px;align-items:center">
    <select class="form-control" name="month" style="width:auto">
      <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=date('F',mktime(0,0,0,$m,1))?></option><?php endfor; ?>
    </select>
    <select class="form-control" name="year" style="width:auto">
      <?php for($y=date('Y');$y>=2024;$y--): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-gold">Filter</button>
  </form></div>

<!-- TOP STAT CARDS -->
<div class="stat-grid" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">💰</span><span class="stat-card-badge badge-green">This Month</span></div>
    <div class="stat-val">₱<?= number_format($rev_month, 0) ?></div>
    <div class="stat-lbl"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?> revenue</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">📋</span><span class="stat-card-badge badge-gold">Orders</span></div>
    <div class="stat-val"><?= $total_orders ?></div>
    <div class="stat-lbl"><?= $ret_orders ?> returned this month</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">📊</span><span class="stat-card-badge badge-blue">All Time</span></div>
    <div class="stat-val">₱<?= number_format($rev_total, 0) ?></div>
    <div class="stat-lbl">Total revenue generated</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">🎁</span><span class="stat-card-badge badge-red">Discounts</span></div>
    <div class="stat-val">₱<?= number_format($rev_disc, 0) ?></div>
    <div class="stat-lbl">Total discounts given (all time)</div>
  </div>
</div>

<div class="two-col" style="margin-bottom:20px">
  <!-- MONTHLY REVENUE TABLE -->
  <div class="table-card">
    <div class="table-header"><span class="table-title">Monthly Revenue (Last 6 Months)</span></div>
    <table>
      <thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach($monthly as $m): ?>
        <tr>
          <td><?= $m['label'] ?></td>
          <td><?= $m['orders'] ?></td>
          <td style="font-weight:600;color:var(--green)">₱<?= number_format($m['revenue'],0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($monthly)): ?><tr class="empty-row"><td colspan="3">No data</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- PAYMENT STATUS -->
  <div class="table-card">
    <div class="table-header"><span class="table-title">Payment Status Breakdown</span></div>
    <table>
      <thead><tr><th>Status</th><th>Orders</th><th>Amount</th></tr></thead>
      <tbody>
        <tr>
          <td><span class="status-badge s-paid">Paid</span></td>
          <td><?= $pay_paid[0] ?></td>
          <td style="color:var(--green);font-weight:600">₱<?= number_format($pay_paid[1] ?? 0,0) ?></td>
        </tr>
        <tr>
          <td><span class="status-badge s-pending">Pending</span></td>
          <td><?= $pay_pending[0] ?></td>
          <td style="color:var(--orange);font-weight:600">₱<?= number_format($pay_pending[1] ?? 0,0) ?></td>
        </tr>
        <tr>
          <td><span class="status-badge s-refunded">Refunded</span></td>
          <td><?= $pay_refunded[0] ?></td>
          <td style="color:var(--blue);font-weight:600">₱<?= number_format($pay_refunded[1] ?? 0,0) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="table-header" style="margin-top:0;border-top:1px solid var(--border)"><span class="table-title">Discount by ID Type</span></div>
    <table>
      <thead><tr><th>ID Type</th><th>Orders</th><th>Revenue</th><th>Discounts Given</th></tr></thead>
      <tbody>
        <?php foreach($disc_breakdown as $d): ?>
        <tr>
          <td><?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; echo $icons[$d['id_type']].' '.ucfirst($d['id_type']); ?></td>
          <td><?= $d['orders'] ?></td>
          <td>₱<?= number_format($d['revenue'],0) ?></td>
          <td style="color:var(--red)">−₱<?= number_format($d['discount_given'] ?? 0,0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- EQUIPMENT PERFORMANCE -->
<div class="table-card" style="margin-bottom:20px">
  <div class="table-header"><span class="table-title">Equipment Performance</span></div>
  <table>
    <thead><tr><th>Equipment</th><th>Category</th><th>Total Orders</th><th>Avg Rental Days</th><th>Total Revenue</th></tr></thead>
    <tbody>
      <?php foreach($by_equipment as $e): ?>
      <tr>
        <td><?= $e['icon'] ?> <strong><?= htmlspecialchars($e['name']) ?></strong></td>
        <td style="color:var(--muted)"><?= htmlspecialchars($e['category'] ?? '') ?></td>
        <td><?= $e['orders'] ?? 0 ?></td>
        <td><?= $e['avg_days'] ? round($e['avg_days'],1) : '—' ?> days</td>
        <td style="font-weight:600;color:var(--green)">₱<?= number_format($e['revenue'] ?? 0,0) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- MONTHLY RENTAL DETAIL -->
<div class="table-card">
  <div class="table-header">
    <span class="table-title">Rental Summary — <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></span>
    <span style="font-size:12px;color:var(--muted)"><?= count($month_rentals) ?> orders · ₱<?= number_format(array_sum(array_column($month_rentals,'total_amount')),0) ?> total</span>
  </div>
  <table>
    <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Days</th><th>Discount</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
    <tbody>
      <?php foreach($month_rentals as $r): ?>
      <tr>
        <td style="color:var(--gold);font-weight:600"><?= $r['order_code'] ?></td>
        <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
        <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
        <td><?= $r['days'] ?></td>
        <td><?= $r['discount_pct'] > 0 ? '<span style="color:var(--green)">−'.$r['discount_pct'].'%</span>' : '—' ?></td>
        <td style="font-weight:600">₱<?= number_format($r['total_amount'],0) ?></td>
        <td><span class="status-badge s-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
        <td><span class="status-badge s-<?= $r['payment_status'] ?? 'paid' ?>"><?= ucfirst($r['payment_status'] ?? 'paid') ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($month_rentals)): ?><tr class="empty-row"><td colspan="8">No rentals for this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include 'includes/admin_layout_end.php'; ?>
