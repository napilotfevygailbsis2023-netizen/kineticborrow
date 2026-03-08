<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'rentals';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $rid = intval($_POST['rental_id'] ?? 0);

    if ($act === 'update_status') {
        $status = in_array($_POST['status'],['active','returned','cancelled'])?$_POST['status']:'active';
        $notes  = $conn->real_escape_string($_POST['notes'] ?? '');
        $return_date = $status === 'returned' ? "return_date='".date('Y-m-d')."'," : '';
        $conn->query("UPDATE rentals SET status='$status', {$return_date} admin_notes='$notes' WHERE id=$rid");
        $msg = "Rental status updated.";
    }
    if ($act === 'update_payment') {
        $pay = in_array($_POST['payment_status'],['pending','paid','refunded'])?$_POST['payment_status']:'paid';
        $conn->query("UPDATE rentals SET payment_status='$pay' WHERE id=$rid");
        $msg = "Payment status updated.";
    }
}

$status_f  = $conn->real_escape_string($_GET['status'] ?? '');
$payment_f = $conn->real_escape_string($_GET['payment'] ?? '');
$search    = $conn->real_escape_string($_GET['q'] ?? '');

$sql = "SELECT r.*, u.first_name, u.last_name, e.name as eq_name, e.icon as eq_icon
        FROM rentals r
        JOIN users u ON r.user_id = u.id
        JOIN equipment e ON r.equipment_id = e.id";
$where = [];
if ($status_f)  $where[] = "r.status='$status_f'";
if ($payment_f) $where[] = "r.payment_status='$payment_f'";
if ($search)    $where[] = "(r.order_code LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
if ($where) $sql .= " WHERE ".implode(" AND ",$where);
$sql .= " ORDER BY r.created_at DESC";
$rentals = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Rentals & Orders</div><div class="page-head-sub">Manage rental orders, returns, and payment status</div></div>
</div>

<div class="search-bar">
  <form method="GET" style="display:contents">
    <div class="search-wrap"><span class="search-icon">🔍</span><input class="search-input" name="q" placeholder="Search order ID or customer..." value="<?= htmlspecialchars($search) ?>"/></div>
    <input type="hidden" name="status"  value="<?= htmlspecialchars($status_f) ?>"/>
    <input type="hidden" name="payment" value="<?= htmlspecialchars($payment_f) ?>"/>
    <button type="submit" class="btn btn-gold btn-sm">Search</button>
  </form>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach([''=> 'All',  'active'=>'Active','returned'=>'Returned','cancelled'=>'Cancelled'] as $k=>$v): ?>
    <a class="btn <?= $status_f===$k?'btn-gold':'btn-outline' ?> btn-sm" href="admin_rentals.php?status=<?= $k ?><?= $search?"&q=$search":'' ?>"><?= $v ?></a>
    <?php endforeach; ?>
    <span style="color:var(--muted);font-size:12px;padding:5px 4px">|</span>
    <?php foreach([''=> 'Any Payment','pending'=>'Pending','paid'=>'Paid','refunded'=>'Refunded'] as $k=>$v): ?>
    <a class="btn <?= $payment_f===$k?'btn-blue':'btn-outline' ?> btn-sm" href="admin_rentals.php?payment=<?= $k ?><?= $status_f?"&status=$status_f":'' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Days</th><th>Dates</th><th>Total</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rentals as $r): ?>
      <tr>
        <td style="color:var(--gold);font-weight:600"><?= htmlspecialchars($r['order_code']) ?></td>
        <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
        <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></td>
        <td><?= $r['days'] ?> day<?= $r['days']>1?'s':'' ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j', strtotime($r['start_date'])) ?> – <?= date('M j', strtotime($r['end_date'])) ?></td>
        <td style="font-weight:600">₱<?= number_format($r['total_amount'],0) ?></td>
        <td><span class="status-badge s-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
        <td><span class="status-badge s-<?= $r['payment_status'] ?? 'paid' ?>"><?= ucfirst($r['payment_status'] ?? 'paid') ?></span></td>
        <td><button class="btn btn-outline btn-sm" onclick='openRental(<?= json_encode($r) ?>)'>Manage</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($rentals)): ?><tr class="empty-row"><td colspan="9">No rentals found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- MANAGE MODAL -->
<div class="modal-overlay" id="modal-rental">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Manage Rental <span id="modal-order" style="color:var(--gold)"></span></h3><button class="modal-close" onclick="closeModal('modal-rental')">×</button></div>
    <div style="background:var(--bg);border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px" id="rental-info"></div>
    <form method="POST">
      <input type="hidden" name="act" value="update_status"/>
      <input type="hidden" name="rental_id" id="rental-id"/>
      <div class="form-group"><label class="form-label">Rental Status</label>
        <select class="form-control" name="status" id="rental-status">
          <option value="active">Active</option>
          <option value="returned">Returned</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Admin Notes</label><textarea class="form-control" name="notes" id="rental-notes" rows="2" placeholder="Optional notes..."></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-rental')">Cancel</button>
        <button type="submit" name="act" value="update_payment" class="btn btn-blue" id="pay-btn">Mark Paid</button>
        <button type="submit" class="btn btn-gold">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openRental(r) {
  document.getElementById('rental-id').value      = r.id;
  document.getElementById('modal-order').textContent = r.order_code;
  document.getElementById('rental-notes').value   = r.admin_notes || '';
  const sel = document.getElementById('rental-status');
  for(let o of sel.options) if(o.value === r.status) o.selected = true;
  document.getElementById('rental-info').innerHTML =
    `<b>${r.eq_icon} ${r.eq_name}</b> · ${r.days} day(s)<br>
     Customer: ${r.first_name} ${r.last_name}<br>
     Total: ₱${parseFloat(r.total_amount).toLocaleString()} · Discount: ${r.discount_pct}%`;
  const payBtn = document.getElementById('pay-btn');
  const pay = r.payment_status || 'paid';
  payBtn.textContent = pay === 'paid' ? 'Mark Refunded' : 'Mark Paid';
  payBtn.setAttribute('onclick', `document.querySelector('[name=act]').value='update_payment'; document.querySelector('[name=payment_status]')?.remove(); const i=document.createElement('input');i.type='hidden';i.name='payment_status';i.value='${pay==='paid'?'refunded':'paid'}';document.querySelector('#modal-rental form').appendChild(i);`);
  openModal('modal-rental');
}
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
