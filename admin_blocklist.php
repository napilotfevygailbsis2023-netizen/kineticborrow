<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'blocklist';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['act'] ?? '';
    $uid    = intval($_POST['user_id'] ?? 0);
    $admin_id = $_SESSION['admin_id'];

    if ($act === 'flag') {
        $reason = $conn->real_escape_string($_POST['reason'] ?? 'Flagged by admin');
        $conn->query("UPDATE users SET is_blocked=1, block_reason='$reason' WHERE id=$uid");
        $conn->query("INSERT INTO blocklist (user_id,reason,status,flagged_by) VALUES ($uid,'$reason','flagged',$admin_id)
                      ON DUPLICATE KEY UPDATE reason='$reason', status='flagged', flagged_by=$admin_id, updated_at=NOW()");
        $msg = "Account flagged.";
    }
    if ($act === 'suspend') {
        $reason = $conn->real_escape_string($_POST['reason'] ?? 'Suspended by admin');
        $conn->query("UPDATE users SET is_blocked=1, block_reason='$reason' WHERE id=$uid");
        $conn->query("INSERT INTO blocklist (user_id,reason,status,flagged_by) VALUES ($uid,'$reason','suspended',$admin_id)
                      ON DUPLICATE KEY UPDATE reason='$reason', status='suspended', flagged_by=$admin_id, updated_at=NOW()");
        $msg = "Account suspended.";
    }
    if ($act === 'unblock') {
        $conn->query("UPDATE users SET is_blocked=0, block_reason=NULL WHERE id=$uid");
        $conn->query("UPDATE blocklist SET status='unblocked', updated_at=NOW() WHERE user_id=$uid");
        $msg = "Account unblocked successfully.";
    }
}

$filter = $_GET['filter'] ?? 'active';
$sql = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone
        FROM blocklist b JOIN users u ON b.user_id = u.id";
if ($filter === 'active')    $sql .= " WHERE b.status IN ('flagged','suspended')";
if ($filter === 'flagged')   $sql .= " WHERE b.status='flagged'";
if ($filter === 'suspended') $sql .= " WHERE b.status='suspended'";
if ($filter === 'unblocked') $sql .= " WHERE b.status='unblocked'";
$sql .= " ORDER BY b.updated_at DESC";
$blocklist = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);


$not_blocked = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE is_blocked=0 ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);">Blocklist Management</div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;">Flag, suspend, or unblock customer accounts</div>
  </div>
</div>


<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px"><button class="btn btn-gold" onclick="openModal('modal-add-block')">+ Flag Account</button></div>

<div class="search-bar">
  <div style="display:flex;gap:6px">
    <?php foreach(['active'=>'Active Blocks','flagged'=>'⚠️ Flagged','suspended'=>'🚫 Suspended','unblocked'=>'✅ Unblocked','all'=>'All'] as $k=>$v): ?>
    <a class="btn <?= $filter===$k?'btn-red':'btn-outline' ?> btn-sm" href="admin_blocklist.php?filter=<?= $k ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Status</th><th>Reason</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($blocklist as $b): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></td>
        <td><?= htmlspecialchars($b['email']) ?></td>
        <td><?= htmlspecialchars($b['phone']) ?></td>
        <td><span class="status-badge s-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
        <td style="font-size:12px;color:var(--muted);max-width:200px"><?= htmlspecialchars($b['reason']) ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($b['updated_at'])) ?></td>
        <td>
          <div class="btn-group">
            <?php if($b['status'] !== 'suspended'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Escalate to full suspension?')">
              <input type="hidden" name="act" value="suspend"/>
              <input type="hidden" name="user_id" value="<?= $b['user_id'] ?>"/>
              <input type="hidden" name="reason" value="<?= htmlspecialchars($b['reason']) ?>"/>
              <button type="submit" class="btn btn-red btn-sm">Suspend</button>
            </form>
            <?php endif; ?>
            <?php if($b['status'] !== 'unblocked'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Unblock this account?')">
              <input type="hidden" name="act" value="unblock"/>
              <input type="hidden" name="user_id" value="<?= $b['user_id'] ?>"/>
              <button type="submit" class="btn btn-green btn-sm">Unblock</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($blocklist)): ?><tr class="empty-row"><td colspan="7">No entries in this category.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="modal-add-block">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Flag / Block Account</h3><button class="modal-close" onclick="closeModal('modal-add-block')">×</button></div>
    <form method="POST">
      <div class="form-group"><label class="form-label">Customer</label>
        <select class="form-control" name="user_id" required>
          <option value="">— Select customer —</option>
          <?php foreach($not_blocked as $u): ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'].' '.$u['last_name'].' ('.$u['email'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Action</label>
        <select class="form-control" name="act">
          <option value="flag">⚠️ Flag — warn only</option>
          <option value="suspend">🚫 Suspend — block access</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="3" placeholder="Reason for flagging/suspending..." required></textarea></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add-block')">Cancel</button><button type="submit" class="btn btn-red">Confirm</button></div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
