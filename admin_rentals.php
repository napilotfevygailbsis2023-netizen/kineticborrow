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
}

$status_f = $conn->real_escape_string($_GET['status'] ?? '');
$search   = $conn->real_escape_string($_GET['q'] ?? '');

$sql = "SELECT r.*, u.first_name, u.last_name, e.name as eq_name, e.icon as eq_icon,
               h_out.name as checkout_handler, h_in.name as checkin_handler
        FROM rentals r
        JOIN users u ON r.user_id = u.id
        JOIN equipment e ON r.equipment_id = e.id
        LEFT JOIN handlers h_out ON r.checkout_by = h_out.id
        LEFT JOIN handlers h_in  ON r.checkin_by  = h_in.id";
$where = [];
if ($status_f)  $where[] = "r.status='$status_f'";
if ($search)    $where[] = "(r.order_code LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
if ($where) $sql .= " WHERE ".implode(" AND ",$where);
$sql .= " ORDER BY r.created_at DESC";
$page   = max(1, intval($_GET['p'] ?? 1));
$limit  = 15; $offset = ($page-1)*$limit;
$count_sql = str_replace("SELECT r.*, u.first_name, u.last_name, e.name as eq_name, e.icon as eq_icon,
               h_out.name as checkout_handler, h_in.name as checkin_handler", "SELECT COUNT(*)", $sql);
$total_r = $conn->query($count_sql)->fetch_row()[0];
$total_pg = max(1, ceil($total_r/$limit));
$rentals = $conn->query($sql." LIMIT $limit OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Rentals & Orders</div><div class="page-head-sub">Monitor bookings, returns, and cancellations</div></div>
</div>

<div class="search-bar">
  <form method="GET" style="display:contents">
    <div class="search-wrap"><span class="search-icon">🔍</span><input class="search-input" name="q" placeholder="Search order or customer..." value="<?= htmlspecialchars($search) ?>"/></div>
    <input type="hidden" name="status" value="<?= htmlspecialchars($status_f) ?>"/>
    <button type="submit" class="btn btn-gold btn-sm">Search</button>
  </form>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach([''=> 'All','active'=>'Active','returned'=>'Returned','cancelled'=>'Cancelled'] as $k=>$v): ?>
    <a class="btn <?= $status_f===$k?'btn-gold':'btn-outline' ?> btn-sm" href="admin_rentals.php?status=<?= $k ?><?= $search?"&q=$search":'' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Dates</th><th>Total</th><th>Payment</th><th>Status</th><th>Handler</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($rentals as $r): ?>
      <tr>
        <td style="color:var(--gold);font-weight:700;font-size:13px"><?= $r['order_code'] ?></td>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= date('M j, Y', strtotime($r['created_at'])) ?></div>
        </td>
        <td><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?><br><span style="font-size:11px;color:var(--muted)"><?= $r['days'] ?> day<?= $r['days']>1?'s':'' ?></span></td>
        <td style="font-size:12px">
          <?= date('M j', strtotime($r['start_date'])) ?> → <?= date('M j, Y', strtotime($r['end_date'])) ?>
          <?php if($r['return_date']): ?>
            <div style="color:var(--green);font-size:11px">Returned <?= date('M j', strtotime($r['return_date'])) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <strong>₱<?= number_format($r['total_amount'],0) ?></strong>
          <?php if($r['discount_pct']>0): ?><div style="font-size:11px;color:var(--green)"><?= $r['discount_pct'] ?>% disc.</div><?php endif; ?>
        </td>
        <td>
          <?php
          // Payment is only set AFTER handler checkout — before that show as not yet paid
          $paid = $r['payment_status'] === 'paid';
          $checkedout = !empty($r['checkout_at']);
          if (!$checkedout): ?>
            <span class="status-badge s-returned" style="color:var(--muted)">— Not yet</span>
          <?php elseif($paid): ?>
            <span class="status-badge s-active">✓ Paid</span>
          <?php elseif($r['payment_status']==='refunded'): ?>
            <span class="status-badge s-reviewed">Refunded</span>
          <?php else: ?>
            <span class="status-badge s-pending">Pending</span>
          <?php endif; ?>
        </td>
        <td>
          <?php
          $sbadge = ['active'=>'s-active','returned'=>'s-returned','cancelled'=>'s-cancelled'];
          echo '<span class="status-badge '.($sbadge[$r['status']]??'s-pending').'">'.ucfirst($r['status']).'</span>';
          ?>
        </td>
        <td style="font-size:12px;color:var(--muted)">
          <?php if($r['checkout_handler']): ?>
            <div>↑ <?= htmlspecialchars($r['checkout_handler']) ?></div>
          <?php endif; ?>
          <?php if($r['checkin_handler']): ?>
            <div>↓ <?= htmlspecialchars($r['checkin_handler']) ?></div>
          <?php endif; ?>
          <?php if(!$r['checkout_handler'] && !$r['checkin_handler']): ?>
            <span style="color:var(--muted)">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($r['status'] === 'active'): ?>
            <button class="btn btn-outline btn-sm" onclick="updateStatus(<?= $r['id'] ?>,'<?= $r['order_code'] ?>','<?= $r['status'] ?>')">Update</button>
          <?php else: ?>
            <span style="font-size:11px;color:var(--muted)">
              <?= $r['status']==='returned'?'↓ By handler':'✕ By customer' ?>
            </span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($rentals)): ?><tr class="empty-row"><td colspan="9">No rentals found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if($total_pg > 1): ?>
  <div class="pager">
    <?php for($i=1;$i<=$total_pg;$i++): ?>
      <?php if($i==1||$i==$total_pg||abs($i-$page)<=2): ?>
        <a href="?status=<?=$status_f?>&q=<?=$search?>&p=<?=$i?>" <?=$i==$page?'class="cur"':''?>><?=$i?></a>
      <?php elseif(abs($i-$page)==3): ?><span style="color:var(--muted);padding:0 4px;border:none">…</span><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- UPDATE STATUS MODAL (only for active rentals, admin can only cancel) -->
<div class="modal-overlay" id="modal-status">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><h3 class="modal-title">Update Rental</h3><button class="modal-close" onclick="closeModal('modal-status')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="update_status"/>
      <input type="hidden" name="rental_id" id="modal-rid"/>
      <div class="modal-info" id="modal-order-info"></div>
      <div class="form-group">
        <label class="form-label">Action</label>
        <select class="form-control" name="status">
          <option value="cancelled">Cancel this booking</option>
        </select>
        <p style="font-size:11px;color:var(--muted);margin-top:5px">
          Note: Returns are processed by handlers. Cancellations can be done here by admin.
        </p>
      </div>
      <div class="form-group">
        <label class="form-label">Admin Notes</label>
        <textarea class="form-control" name="notes" rows="2" placeholder="Reason for cancellation..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-status')">Close</button>
        <button type="submit" class="btn btn-red">Cancel Booking</button>
      </div>
    </form>
  </div>
</div>

<style>
.pager{display:flex;align-items:center;gap:5px;padding:12px 16px;border-top:1px solid var(--border)}
.pager a,.pager .cur{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid var(--border);color:var(--text2)}
.pager a:hover{background:var(--gold-bg);border-color:var(--gold);color:var(--gold)}
.pager .cur{background:var(--gold);border-color:var(--gold);color:#fff}
</style>
<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
function updateStatus(rid, code, status) {
  document.getElementById('modal-rid').value = rid;
  document.getElementById('modal-order-info').textContent = 'Order ' + code;
  openModal('modal-status');
}
</script>
<?php include 'includes/admin_layout_end.php'; ?>
