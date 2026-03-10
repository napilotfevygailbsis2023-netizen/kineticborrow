<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'checkin';
$hid   = $_SESSION['handler_id'];
$today = date('Y-m-d');
$msg   = ''; $err = '';
$preselect = intval($_GET['rental_id'] ?? 0);

// Process return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'checkin') {
    $rid       = intval($_POST['rental_id']);
    $condition = in_array($_POST['condition'],['excellent','good','fair','poor'])?$_POST['condition']:'good';
    $notes     = $conn->real_escape_string($_POST['notes'] ?? '');
    $now       = date('Y-m-d H:i:s');

    $rental = $conn->query("SELECT * FROM rentals WHERE id=$rid AND status='active' AND checkout_by IS NOT NULL")->fetch_assoc();

    if (!$rental) {
        $err = "Rental not found or not yet checked out.";
    } else {
        // Mark returned
        $conn->query("UPDATE rentals SET status='returned', checkin_by=$hid, checkin_at='$now', return_date='$today' WHERE id=$rid");
        // Update equipment stock
        $conn->query("UPDATE equipment SET stock=stock+1 WHERE id={$rental['equipment_id']}");
        // Log condition
        $stmt = $conn->prepare("INSERT INTO condition_logs (rental_id, handler_id, type, condition_rating, notes) VALUES (?,?,'checkin',?,?)");
        $stmt->bind_param('iiss', $rid, $hid, $condition, $notes);
        $stmt->execute();
        $msg = "✅ Return confirmed! Order {$rental['order_code']} marked as returned. Stock updated.";
        $preselect = 0;
    }
}

// All active + checked-out rentals (awaiting return)
$search = $conn->real_escape_string($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT r.*, u.first_name, u.last_name, u.phone,
               e.name as eq_name, e.icon as eq_icon
        FROM rentals r
        JOIN users u ON r.user_id = u.id
        JOIN equipment e ON r.equipment_id = e.id
        WHERE r.status='active' AND r.checkout_by IS NOT NULL";
if ($filter === 'overdue') $sql .= " AND r.end_date < '$today'";
if ($filter === 'today')   $sql .= " AND r.end_date = '$today'";
if ($search) $sql .= " AND (r.order_code LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
$sql .= " ORDER BY r.end_date ASC";
$rentals = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$selected = null;
if ($preselect) {
    $selected = $conn->query("
        SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.id_type,
               e.name as eq_name, e.icon as eq_icon, e.category
        FROM rentals r JOIN users u ON r.user_id=u.id JOIN equipment e ON r.equipment_id=e.id
        WHERE r.id=$preselect AND r.status='active' AND r.checkout_by IS NOT NULL
    ")->fetch_assoc();
}

// Recent returns today (my activity)
$recent = $conn->query("
    SELECT r.order_code, u.first_name, u.last_name, e.name as eq_name, e.icon,
           cl.condition_rating, r.checkin_at
    FROM rentals r
    JOIN users u ON r.user_id=u.id
    JOIN equipment e ON r.equipment_id=e.id
    LEFT JOIN condition_logs cl ON cl.rental_id=r.id AND cl.type='checkin'
    WHERE r.checkin_by=$hid AND DATE(r.checkin_at)='$today'
    ORDER BY r.checkin_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include 'includes/handler_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Check-In / Returns</div><div class="page-head-sub">Receive returned equipment, confirm condition, and update availability</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:18px;align-items:start">

  <!-- LEFT -->
  <div>
    <div class="search-bar">
      <form method="GET" style="display:contents">
        <div class="search-wrap"><span class="search-icon">🔍</span><input class="search-input" name="q" placeholder="Search order or customer..." value="<?= htmlspecialchars($search) ?>"/></div>
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"/>
        <button type="submit" class="btn btn-teal btn-sm">Search</button>
      </form>
      <a class="btn <?= $filter==='all'?'btn-teal':'btn-outline' ?> btn-sm" href="handler_checkin.php?filter=all">All</a>
      <a class="btn <?= $filter==='overdue'?'btn-red':'btn-outline' ?> btn-sm" href="handler_checkin.php?filter=overdue">⚠️ Overdue</a>
      <a class="btn <?= $filter==='today'?'btn-orange':'btn-outline' ?> btn-sm" href="handler_checkin.php?filter=today">Due Today</a>
    </div>

    <div class="table-card">
      <div class="table-header"><span class="table-title">Active Rentals — Awaiting Return</span><span style="font-size:12px;color:var(--muted)"><?= count($rentals) ?> rentals</span></div>
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>End Date</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($rentals as $r): ?>
          <?php $overdue=$r['end_date']<$today; $due_today=$r['end_date']===$today; ?>
          <tr style="<?= $overdue?'background:#FFF8F8':($preselect==$r['id']?'background:#EBF8F9':'') ?>">
            <td style="color:var(--teal);font-weight:700"><?= $r['order_code'] ?></td>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['phone']) ?></div>
            </td>
            <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
            <td style="color:<?= $overdue?'var(--red)':($due_today?'var(--orange)':'inherit') ?>;font-weight:<?= ($overdue||$due_today)?'600':'400' ?>"><?= date('M j, Y', strtotime($r['end_date'])) ?></td>
            <td>
              <?php if($overdue): ?><span class="status-badge s-cancelled">Overdue</span>
              <?php elseif($due_today): ?><span class="status-badge s-pending">Due Today</span>
              <?php else: ?><span class="status-badge s-active">Active</span>
              <?php endif; ?>
            </td>
            <td><a href="handler_checkin.php?rental_id=<?= $r['id'] ?><?= $search?"&q=$search":'' ?>&filter=<?= $filter ?>" class="btn btn-teal btn-sm">Select</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rentals)): ?><tr class="empty-row"><td colspan="6">No rentals awaiting return.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if(!empty($recent)): ?>
    <div class="table-card">
      <div class="table-header"><span class="table-title">✅ My Returns Today</span></div>
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Condition</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach($recent as $r): ?>
          <tr>
            <td style="color:var(--teal);font-weight:700"><?= $r['order_code'] ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= $r['icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
            <td><span class="status-badge s-<?= $r['condition_rating'] ?>"><?= ucfirst($r['condition_rating'] ?? '—') ?></span></td>
            <td style="font-size:12px;color:var(--muted)"><?= date('g:i A', strtotime($r['checkin_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- RIGHT: CHECK-IN PANEL -->
  <div>
    <?php if($selected): ?>
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
      <p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:800;color:var(--text);margin-bottom:16px">📦 Return — <?= $selected['order_code'] ?></p>

      <div style="background:var(--bg);border-radius:10px;padding:13px;margin-bottom:12px;font-size:13px;line-height:1.8">
        <p style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;color:var(--muted);margin-bottom:4px">Customer</p>
        <p style="font-weight:700"><?= htmlspecialchars($selected['first_name'].' '.$selected['last_name']) ?></p>
        <p><?= htmlspecialchars($selected['phone']) ?></p>
      </div>

      <div style="background:var(--teal-bg);border-radius:10px;padding:13px;margin-bottom:14px;font-size:13px;line-height:1.8">
        <p style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;color:var(--teal);margin-bottom:4px">Equipment</p>
        <p style="font-weight:700;font-size:15px"><?= $selected['eq_icon'] ?> <?= htmlspecialchars($selected['eq_name']) ?></p>
        <p>Rented for <?= $selected['days'] ?> day<?= $selected['days']>1?'s':'' ?></p>
        <p>Return due: <strong><?= date('M j, Y', strtotime($selected['end_date'])) ?></strong>
          <?php if($selected['end_date']<$today): ?><span style="color:var(--red);font-weight:600"> (OVERDUE)</span><?php endif; ?>
        </p>
      </div>

      <form method="POST">
        <input type="hidden" name="act" value="checkin"/>
        <input type="hidden" name="rental_id" value="<?= $selected['id'] ?>"/>

        <div class="form-group">
          <label class="form-label">Equipment Condition on Return</label>
          <div class="condition-selector">
            <?php foreach(['excellent'=>'⭐ Excellent','good'=>'👍 Good','fair'=>'⚠️ Fair','poor'=>'❌ Poor'] as $val=>$lbl): ?>
            <label style="flex:1">
              <input type="radio" name="condition" value="<?= $val ?>" style="display:none" <?= $val==='good'?'checked':'' ?> onchange="document.querySelectorAll('.cond-btn').forEach(b=>b.classList.remove('selected'));this.closest('label').querySelector('.cond-btn').classList.add('selected')"/>
              <div class="cond-btn <?= $val==='good'?'selected':'' ?>"><?= $lbl ?></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Return Notes</label>
          <textarea class="form-control" name="notes" rows="3" placeholder="Describe the condition on return, any damage or missing parts..."></textarea>
        </div>

        <div style="background:var(--orange-bg);border:1px solid #FADDB8;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:var(--orange)">
          ⚠️ <strong>If equipment is damaged or lost</strong>, confirm return first, then go to <a href="handler_incidents.php" style="color:var(--orange);font-weight:600">Incident Reports</a> to file a report.
        </div>

        <button type="submit" class="btn btn-teal" style="width:100%;padding:12px;font-size:14px">
          📦 Confirm Return & Update Stock
        </button>
      </form>
    </div>
    <?php else: ?>
    <div style="background:#fff;border:1.5px dashed var(--border);border-radius:14px;padding:40px 22px;text-align:center;color:var(--muted)">
      <p style="font-size:36px;margin-bottom:12px">👈</p>
      <p style="font-size:14px;font-weight:600;margin-bottom:6px">Select a rental</p>
      <p style="font-size:13px">Click "Select" on any active rental to process its return.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/handler_layout_end.php'; ?>
