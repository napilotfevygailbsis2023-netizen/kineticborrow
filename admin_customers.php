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
$page  = max(1, intval($_GET['p'] ?? 1));
$limit = 15; $offset = ($page-1)*$limit;
$total_c  = $conn->query("SELECT COUNT(DISTINCT u.id) FROM users u".($where?" WHERE ".implode(" AND ",$where):""))->fetch_row()[0];
$total_pg = max(1, ceil($total_c/$limit));
$customers = $conn->query($sql." LIMIT $limit OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Customer Accounts</div><div class="page-head-sub">View customer info and manage account status</div></div>
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
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>ID Type</th><th>ID Status</th><th>Rentals</th><th>Points</th><th>Account Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($customers as $c): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)">Joined <?= date('M j, Y', strtotime($c['created_at'])) ?></div>
        </td>
        <td><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['phone']) ?></td>
        <td><?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; echo ($icons[$c['id_type']]??'🪪').' '.ucfirst($c['id_type']); ?></td>
        <td>
          <?php
          $id_badges = ['approved'=>'s-active','pending'=>'s-pending','rejected'=>'s-cancelled','none'=>'s-returned'];
          $id_status = $c['id_status'] ?? 'none';
          echo '<span class="status-badge '.($id_badges[$id_status]??'s-returned').'">'.ucfirst($id_status).'</span>';
          ?>
        </td>
        <td style="font-weight:600;color:var(--text2)"><?= $c['rental_count'] ?></td>
        <td>
          <span style="font-size:12px;color:var(--gold);font-weight:600">⭐ <?= number_format($c['loyalty_pts']) ?></span>
        </td>
        <td>
          <?php if($c['is_blocked']): ?>
            <span class="status-badge s-cancelled">🚫 Blocked</span>
            <?php if($c['block_reason']): ?><div style="font-size:11px;color:var(--red);margin-top:3px"><?= htmlspecialchars(substr($c['block_reason'],0,30)) ?></div><?php endif; ?>
          <?php else: ?>
            <span class="status-badge s-active">✓ Active</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($c['is_blocked']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="act" value="unblock"/>
              <input type="hidden" name="user_id" value="<?= $c['id'] ?>"/>
              <button type="submit" class="btn btn-green btn-sm">Unblock</button>
            </form>
          <?php else: ?>
            <button class="btn btn-red btn-sm" onclick="blockCustomer(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['first_name'].' '.$c['last_name'])) ?>')">Block</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($customers)): ?><tr class="empty-row"><td colspan="9">No customers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if($total_pg>1): ?>
  <div class="pager">
    <?php for($i=1;$i<=$total_pg;$i++): ?>
      <?php if($i==1||$i==$total_pg||abs($i-$page)<=2): ?>
        <a href="?filter=<?=$filter?>&q=<?=urlencode($search)?>&p=<?=$i?>" <?=$i==$page?'class="cur"':''?>><?=$i?></a>
      <?php elseif(abs($i-$page)==3): ?><span style="color:var(--muted);padding:0 4px;border:none">…</span><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- BLOCK MODAL -->
<div class="modal-overlay" id="modal-block">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><h3 class="modal-title">🚫 Block Customer</h3><button class="modal-close" onclick="closeModal('modal-block')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="block"/>
      <input type="hidden" name="user_id" id="block-uid"/>
      <div class="modal-info">Blocking <strong id="block-name"></strong> will prevent them from borrowing equipment.</div>
      <div class="form-group">
        <label class="form-label">Reason for blocking *</label>
        <textarea class="form-control" name="reason" rows="3" placeholder="e.g. Returned equipment damaged, failed to return on time..." required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-block')">Cancel</button>
        <button type="submit" class="btn btn-red">Confirm Block</button>
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
function blockCustomer(uid, name) {
  document.getElementById('block-uid').value = uid;
  document.getElementById('block-name').textContent = name;
  openModal('modal-block');
}
</script>
<?php include 'includes/admin_layout_end.php'; ?>
