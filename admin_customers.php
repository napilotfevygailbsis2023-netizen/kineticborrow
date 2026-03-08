<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'customers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $uid = intval($_POST['user_id'] ?? 0);

    if ($act === 'block') {
        $reason = $conn->real_escape_string($_POST['reason'] ?? 'Flagged by admin');
        $conn->query("UPDATE users SET is_blocked=1, block_reason='$reason' WHERE id=$uid");
        $conn->query("INSERT INTO blocklist (user_id,reason,status) VALUES ($uid,'$reason','suspended') ON DUPLICATE KEY UPDATE reason='$reason', status='suspended', updated_at=NOW()");
        $msg = "Customer account suspended.";
    }
    if ($act === 'unblock') {
        $conn->query("UPDATE users SET is_blocked=0, block_reason=NULL WHERE id=$uid");
        $conn->query("UPDATE blocklist SET status='unblocked', updated_at=NOW() WHERE user_id=$uid");
        $msg = "Customer account unblocked.";
    }
    if ($act === 'edit') {
        $fn    = $conn->real_escape_string($_POST['first_name']);
        $ln    = $conn->real_escape_string($_POST['last_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $id_type = in_array($_POST['id_type'],['student','senior','pwd','regular'])?$_POST['id_type']:'regular';
        $pts   = intval($_POST['loyalty_pts']);
        $conn->query("UPDATE users SET first_name='$fn',last_name='$ln',email='$email',phone='$phone',id_type='$id_type',loyalty_pts=$pts WHERE id=$uid");
        $msg = "Customer updated.";
    }
}

$search = $conn->real_escape_string($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT u.*, COUNT(r.id) as rental_count FROM users u LEFT JOIN rentals r ON u.id = r.user_id";
$where = [];
if ($search) $where[] = "(u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.email LIKE '%$search%')";
if ($filter === 'blocked')  $where[] = "u.is_blocked=1";
if ($filter === 'student')  $where[] = "u.id_type='student'";
if ($filter === 'verified') $where[] = "u.id_verified=1";
if ($where) $sql .= " WHERE ".implode(" AND ",$where);
$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
$customers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Customer Accounts</div><div class="page-head-sub">Manage accounts, rental orders, and access</div></div>
</div>

<div class="search-bar">
  <form method="GET" style="display:contents">
    <div class="search-wrap"><span class="search-icon">🔍</span><input class="search-input" name="q" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>"/></div>
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"/>
    <button type="submit" class="btn btn-gold btn-sm">Search</button>
  </form>
  <div style="display:flex;gap:6px">
    <?php foreach(['all'=>'All','blocked'=>'Blocked','student'=>'Students','verified'=>'Verified ID'] as $k=>$v): ?>
    <a class="btn <?= $filter===$k?'btn-gold':'btn-outline' ?> btn-sm" href="admin_customers.php?filter=<?= $k ?><?= $search?"&q=$search":'' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>ID Type</th><th>Rentals</th><th>Points</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($customers as $c): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
        <td><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['phone']) ?></td>
        <td>
          <?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; ?>
          <?= $icons[$c['id_type']] ?? '🪪' ?> <?= ucfirst($c['id_type']) ?>
          <?php if($c['id_verified']): ?><span class="status-badge s-approved" style="margin-left:4px">✓</span><?php endif; ?>
        </td>
        <td><?= $c['rental_count'] ?></td>
        <td><?= number_format($c['loyalty_pts']) ?> pts</td>
        <td><span class="status-badge <?= $c['is_blocked']?'s-suspended':'s-active' ?>"><?= $c['is_blocked']?'Blocked':'Active' ?></span></td>
        <td>
          <div class="btn-group">
            <button class="btn btn-outline btn-sm" onclick='openEdit(<?= json_encode($c) ?>)'>Edit</button>
            <?php if($c['is_blocked']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="act" value="unblock"/>
              <input type="hidden" name="user_id" value="<?= $c['id'] ?>"/>
              <button type="submit" class="btn btn-green btn-sm">Unblock</button>
            </form>
            <?php else: ?>
            <button class="btn btn-red btn-sm" onclick="openBlock(<?= $c['id'] ?>, '<?= addslashes($c['first_name'].' '.$c['last_name']) ?>')">Block</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($customers)): ?><tr class="empty-row"><td colspan="8">No customers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Edit Customer</h3><button class="modal-close" onclick="closeModal('modal-edit')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="edit"/>
      <input type="hidden" name="user_id" id="edit-uid"/>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-control" name="first_name" id="edit-fn"/></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-control" name="last_name" id="edit-ln"/></div>
        <div class="form-group"><label class="form-label">Email</label><input class="form-control" name="email" id="edit-email" type="email"/></div>
        <div class="form-group"><label class="form-label">Phone</label><input class="form-control" name="phone" id="edit-phone"/></div>
        <div class="form-group"><label class="form-label">ID Type</label>
          <select class="form-control" name="id_type" id="edit-idtype">
            <option value="student">Student</option><option value="senior">Senior Citizen</option><option value="pwd">PWD</option><option value="regular">Regular</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Loyalty Points</label><input class="form-control" name="loyalty_pts" id="edit-pts" type="number"/></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Cancel</button><button type="submit" class="btn btn-gold">Save</button></div>
    </form>
  </div>
</div>

<!-- BLOCK MODAL -->
<div class="modal-overlay" id="modal-block">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Block Customer</h3><button class="modal-close" onclick="closeModal('modal-block')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="block"/>
      <input type="hidden" name="user_id" id="block-uid"/>
      <p style="font-size:13px;color:var(--muted);margin-bottom:18px">You are about to block <strong id="block-name"></strong>. They will not be able to log in or rent equipment.</p>
      <div class="form-group"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="3" placeholder="Enter reason for blocking..." required></textarea></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-block')">Cancel</button><button type="submit" class="btn btn-red">Confirm Block</button></div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openEdit(c) {
  document.getElementById('edit-uid').value   = c.id;
  document.getElementById('edit-fn').value    = c.first_name;
  document.getElementById('edit-ln').value    = c.last_name;
  document.getElementById('edit-email').value = c.email;
  document.getElementById('edit-phone').value = c.phone;
  document.getElementById('edit-pts').value   = c.loyalty_pts;
  const sel = document.getElementById('edit-idtype');
  for(let o of sel.options) if(o.value === c.id_type) o.selected = true;
  openModal('modal-edit');
}
function openBlock(uid, name) {
  document.getElementById('block-uid').value  = uid;
  document.getElementById('block-name').textContent = name;
  openModal('modal-block');
}
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
