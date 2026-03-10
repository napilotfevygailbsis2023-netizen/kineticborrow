<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'checkout';
$hid   = $_SESSION['handler_id'];
$today = date('Y-m-d');
$msg   = ''; $err = '';

// Pre-select rental if passed via URL
$preselect = intval($_GET['rental_id'] ?? 0);

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'checkout') {
    $rid       = intval($_POST['rental_id']);
    $condition = in_array($_POST['condition'],['excellent','good','fair','poor'])?$_POST['condition']:'good';
    $notes     = $conn->real_escape_string($_POST['notes'] ?? '');
    $now       = date('Y-m-d H:i:s');

    // Verify rental is valid and not blocked
    $rental = $conn->query("
        SELECT r.*, u.is_blocked FROM rentals r
        JOIN users u ON r.user_id = u.id
        WHERE r.id=$rid AND r.status='active' AND r.checkout_by IS NULL
    ")->fetch_assoc();

    if (!$rental) {
        $err = "Rental not found or already checked out.";
    } elseif ($rental['is_blocked']) {
        $err = "⛔ Cannot release — this customer's account is blocked.";
    } else {
        // Mark checked out
        $conn->query("UPDATE rentals SET checkout_by=$hid, checkout_at='$now' WHERE id=$rid");
        // Log condition
        $stmt = $conn->prepare("INSERT INTO condition_logs (rental_id, handler_id, type, condition_rating, notes) VALUES (?,?,'checkout',?,?)");
        $stmt->bind_param('iiss', $rid, $hid, $condition, $notes);
        $stmt->execute();
        $msg = "✅ Equipment released successfully! Order {$rental['order_code']} checked out.";
        $preselect = 0;
    }
}

// Search/filter rentals ready for checkout
$search = $conn->real_escape_string($_GET['q'] ?? '');
$sql = "SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.is_blocked,
               e.name as eq_name, e.icon as eq_icon, e.category
        FROM rentals r
        JOIN users u ON r.user_id = u.id
        JOIN equipment e ON r.equipment_id = e.id
        WHERE r.status = 'active' AND r.checkout_by IS NULL
          AND r.start_date <= '$today'";
if ($search) $sql .= " AND (r.order_code LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.email LIKE '%$search%')";
$sql .= " ORDER BY u.is_blocked ASC, r.start_date ASC";
$rentals = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Load preselected rental detail
$selected = null;
if ($preselect) {
    $selected = $conn->query("
        SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.id_type, u.is_blocked, u.id_verified, u.loyalty_pts, u.block_reason,
               e.name as eq_name, e.icon as eq_icon, e.category, e.price_per_day
        FROM rentals r JOIN users u ON r.user_id=u.id JOIN equipment e ON r.equipment_id=e.id
        WHERE r.id=$preselect
    ")->fetch_assoc();
}

include 'includes/handler_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Equipment Check-Out</div><div class="page-head-sub">Validate booking, verify customer identity, and release equipment</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:18px;align-items:start">

  <!-- LEFT: RENTAL LIST -->
  <div>
    <div class="search-bar">
      <form method="GET" style="display:contents">
        <div class="search-wrap"><span class="search-icon">🔍</span><input class="search-input" name="q" placeholder="Search order, name, or email..." value="<?= htmlspecialchars($search) ?>"/></div>
        <button type="submit" class="btn btn-teal btn-sm">Search</button>
      </form>
    </div>

    <div class="table-card">
      <div class="table-header"><span class="table-title">Pending Check-Outs</span><span style="font-size:12px;color:var(--muted)"><?= count($rentals) ?> orders</span></div>
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($rentals as $r): ?>
          <tr style="<?= $r['is_blocked']?'background:#FFF5F5':($preselect==$r['id']?'background:#EBF8F9':'') ?>">
            <td style="color:var(--teal);font-weight:700"><?= $r['order_code'] ?></td>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['phone']) ?></div>
            </td>
            <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
            <td style="font-size:12px"><?= date('M j', strtotime($r['start_date'])) ?></td>
            <td>
              <?php if($r['is_blocked']): ?>
                <span class="status-badge s-suspended">🚫 Blocked</span>
              <?php elseif($r['start_date'] < $today): ?>
                <span class="status-badge s-pending">Overdue</span>
              <?php else: ?>
                <span class="status-badge s-active">Ready</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if(!$r['is_blocked']): ?>
              <a href="handler_checkout.php?rental_id=<?= $r['id'] ?><?= $search?"&q=$search":'' ?>" class="btn btn-teal btn-sm">Select</a>
              <?php else: ?>
              <span style="font-size:11px;color:var(--red);font-weight:600">Blocked</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rentals)): ?><tr class="empty-row"><td colspan="6">No pending check-outs.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RIGHT: CHECKOUT PANEL -->
  <div>
    <?php if($selected): ?>
    <?php if($selected['is_blocked']): ?>
    <div style="background:#fff;border:2px solid var(--red);border-radius:14px;padding:22px">
      <div style="text-align:center;margin-bottom:16px">
        <p style="font-size:36px">🚫</p>
        <p style="font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:var(--red);margin-bottom:6px">Account Blocked</p>
        <p style="font-size:13px;color:var(--red)">This customer is on the blocklist. Do not release equipment.</p>
      </div>
      <div style="background:var(--red-bg);border-radius:10px;padding:13px;font-size:13px;color:var(--red)">
        <strong>Customer:</strong> <?= htmlspecialchars($selected['first_name'].' '.$selected['last_name']) ?><br>
        <strong>Order:</strong> <?= $selected['order_code'] ?><br>
        <strong>Reason:</strong> <?= htmlspecialchars($selected['block_reason'] ?? 'No reason given') ?>
      </div>
    </div>
    <?php else: ?>
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
      <p style="font-family:'Playfair Display',serif;font-size:17px;font-weight:800;color:var(--text);margin-bottom:16px">📋 Checkout — <?= $selected['order_code'] ?></p>

      <!-- Customer Info -->
      <div style="background:var(--bg);border-radius:10px;padding:13px;margin-bottom:14px;font-size:13px;line-height:1.8">
        <p style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;color:var(--muted);margin-bottom:6px">Customer</p>
        <p style="font-weight:700;font-size:15px"><?= htmlspecialchars($selected['first_name'].' '.$selected['last_name']) ?></p>
        <p><?= htmlspecialchars($selected['email']) ?></p>
        <p><?= htmlspecialchars($selected['phone']) ?></p>
        <p style="margin-top:4px">
          ID: <?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; echo $icons[$selected['id_type']].' '.ucfirst($selected['id_type']); ?>
          <?= $selected['id_verified']?'<span class="status-badge s-active" style="margin-left:4px">Verified</span>':'<span class="status-badge s-pending" style="margin-left:4px">Unverified</span>' ?>
        </p>
      </div>

      <!-- Equipment Info -->
      <div style="background:var(--teal-bg);border-radius:10px;padding:13px;margin-bottom:14px;font-size:13px;line-height:1.8">
        <p style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;color:var(--teal);margin-bottom:6px">Equipment</p>
        <p style="font-weight:700;font-size:15px"><?= $selected['eq_icon'] ?> <?= htmlspecialchars($selected['eq_name']) ?></p>
        <p><?= $selected['days'] ?> day<?= $selected['days']>1?'s':'' ?> · <?= date('M j', strtotime($selected['start_date'])) ?> → <?= date('M j, Y', strtotime($selected['end_date'])) ?></p>
        <p>Total: <strong>₱<?= number_format($selected['total_amount'],0) ?></strong><?= $selected['discount_pct']>0?' ('.$selected['discount_pct'].'% discount applied)':'' ?></p>
      </div>

      <!-- Condition + Checkout Form -->
      <form method="POST">
        <input type="hidden" name="act" value="checkout"/>
        <input type="hidden" name="rental_id" value="<?= $selected['id'] ?>"/>

        <div class="form-group">
          <label class="form-label">Equipment Condition at Check-Out</label>
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
          <label class="form-label">Handler Notes (optional)</label>
          <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Minor scuff on left side, customer informed..."></textarea>
        </div>

        <div style="background:#EAF6EE;border:1px solid #C0E0CC;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#2E8B57">
          ✅ <strong>Checklist before releasing:</strong> Verify customer identity matches booking · Confirm booking is paid · Check equipment condition · Get customer acknowledgment
        </div>

        <button type="submit" class="btn btn-green" style="width:100%;padding:12px;font-size:14px">
          🚀 Confirm Release & Check Out
        </button>
      </form>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div style="background:#fff;border:1.5px dashed var(--border);border-radius:14px;padding:40px 22px;text-align:center;color:var(--muted)">
      <p style="font-size:36px;margin-bottom:12px">👈</p>
      <p style="font-size:14px;font-weight:600;margin-bottom:6px">Select a rental</p>
      <p style="font-size:13px">Click "Select" on any pending order to begin the check-out process.</p>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include 'includes/handler_layout_end.php'; ?>
