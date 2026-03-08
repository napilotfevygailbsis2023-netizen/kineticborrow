<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'id_verify';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['act'] ?? '';
    $vid    = intval($_POST['verify_id'] ?? 0);
    $uid    = intval($_POST['user_id']   ?? 0);
    $notes  = $conn->real_escape_string($_POST['notes'] ?? '');
    $admin_id = $_SESSION['admin_id'];

    if ($act === 'approve') {
        $conn->query("UPDATE id_verifications SET status='approved', notes='$notes', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $conn->query("UPDATE users SET id_verified=1 WHERE id=$uid");
        $msg = "ID verification approved.";
    }
    if ($act === 'reject') {
        $conn->query("UPDATE id_verifications SET status='rejected', notes='$notes', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $conn->query("UPDATE users SET id_verified=0 WHERE id=$uid");
        $msg = "ID verification rejected.";
    }
    if ($act === 'escalate') {
        $conn->query("UPDATE id_verifications SET status='escalated', notes='$notes', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $msg = "Case escalated for manual review.";
    }
    if ($act === 'override_type') {
        $new_type = in_array($_POST['id_type'],['student','senior','pwd','regular'])?$_POST['id_type']:'regular';
        $conn->query("UPDATE users SET id_type='$new_type', id_verified=1 WHERE id=$uid");
        $conn->query("UPDATE id_verifications SET status='approved', notes='Override: type changed to $new_type. $notes', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $msg = "ID type overridden to ".ucfirst($new_type).".";
    }
    if ($act === 'add_manual') {
        $uid2     = intval($_POST['user_id2']);
        $id_type2 = in_array($_POST['id_type2'],['student','senior','pwd','regular'])?$_POST['id_type2']:'regular';
        $conn->query("INSERT INTO id_verifications (user_id, id_type, status, reviewed_by) VALUES ($uid2,'$id_type2','approved',$admin_id) ON DUPLICATE KEY UPDATE id_type='$id_type2', status='approved', reviewed_by=$admin_id, updated_at=NOW()");
        $conn->query("UPDATE users SET id_type='$id_type2', id_verified=1 WHERE id=$uid2");
        $msg = "Manual ID verification added.";
    }
}

$filter = $_GET['filter'] ?? 'pending';
$safe_f = in_array($filter,['pending','approved','rejected','escalated','all'])?$filter:'pending';
$sql = "SELECT v.*, u.first_name, u.last_name, u.email, u.id_type as current_id_type
        FROM id_verifications v JOIN users u ON v.user_id = u.id";
if ($safe_f !== 'all') $sql .= " WHERE v.status='$safe_f'";
$sql .= " ORDER BY v.created_at DESC";
$verifications = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$users_list = $conn->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">ID Verification</div><div class="page-head-sub">Review AI-detected IDs, approve, reject, or escalate</div></div>
  <button class="btn btn-gold" onclick="openModal('modal-manual')">+ Manual Entry</button>
</div>

<div class="search-bar">
  <div style="display:flex;gap:6px">
    <?php foreach(['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','escalated'=>'🔺 Escalated','all'=>'All'] as $k=>$v): ?>
    <a class="btn <?= $safe_f===$k?'btn-gold':'btn-outline' ?> btn-sm" href="admin_id_verify.php?filter=<?= $k ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Customer</th><th>Email</th><th>Submitted ID Type</th><th>Current Type</th><th>Status</th><th>Submitted</th><th>Notes</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($verifications as $v): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></td>
        <td><?= htmlspecialchars($v['email']) ?></td>
        <td><?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; echo $icons[$v['id_type']].' '.ucfirst($v['id_type']); ?></td>
        <td><?= $icons[$v['current_id_type']].' '.ucfirst($v['current_id_type']) ?></td>
        <td><span class="status-badge s-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($v['created_at'])) ?></td>
        <td style="font-size:12px;color:var(--muted);max-width:140px"><?= $v['notes'] ? htmlspecialchars(substr($v['notes'],0,50)).'...' : '—' ?></td>
        <td>
          <?php if($v['status'] === 'pending' || $v['status'] === 'escalated'): ?>
          <button class="btn btn-outline btn-sm" onclick='openReview(<?= json_encode($v) ?>)'>Review</button>
          <?php else: ?>
          <button class="btn btn-outline btn-sm" onclick='openReview(<?= json_encode($v) ?>)'>Override</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($verifications)): ?><tr class="empty-row"><td colspan="8">No <?= $safe_f !== 'all' ? $safe_f : '' ?> verifications found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- REVIEW MODAL -->
<div class="modal-overlay" id="modal-review">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Review ID Verification</h3><button class="modal-close" onclick="closeModal('modal-review')">×</button></div>
    <div style="background:var(--bg);border-radius:10px;padding:14px;margin-bottom:18px;font-size:13px" id="review-info"></div>
    <form method="POST" id="review-form">
      <input type="hidden" name="verify_id" id="rev-vid"/>
      <input type="hidden" name="user_id"   id="rev-uid"/>
      <div class="form-group"><label class="form-label">Override ID Type (optional)</label>
        <select class="form-control" name="id_type" id="rev-idtype">
          <option value="student">🎓 Student</option>
          <option value="senior">👴 Senior Citizen</option>
          <option value="pwd">♿ PWD</option>
          <option value="regular">🪪 Regular</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="rev-notes" rows="2" placeholder="Add review notes..."></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-review')">Cancel</button>
        <button type="submit" name="act" value="escalate" class="btn btn-blue">🔺 Escalate</button>
        <button type="submit" name="act" value="reject"   class="btn btn-red">❌ Reject</button>
        <button type="submit" name="act" value="override_type" class="btn btn-outline">Override Type</button>
        <button type="submit" name="act" value="approve"  class="btn btn-green">✅ Approve</button>
      </div>
    </form>
  </div>
</div>

<!-- MANUAL ENTRY MODAL -->
<div class="modal-overlay" id="modal-manual">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Manual ID Entry</h3><button class="modal-close" onclick="closeModal('modal-manual')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="add_manual"/>
      <div class="form-group"><label class="form-label">Customer</label>
        <select class="form-control" name="user_id2" required>
          <option value="">— Select customer —</option>
          <?php foreach($users_list as $u): ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'].' '.$u['last_name'].' ('.$u['email'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">ID Type</label>
        <select class="form-control" name="id_type2">
          <option value="student">🎓 Student</option>
          <option value="senior">👴 Senior Citizen</option>
          <option value="pwd">♿ PWD</option>
          <option value="regular">🪪 Regular</option>
        </select>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-manual')">Cancel</button><button type="submit" class="btn btn-gold">Add & Approve</button></div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openReview(v) {
  document.getElementById('rev-vid').value   = v.id;
  document.getElementById('rev-uid').value   = v.user_id;
  document.getElementById('rev-notes').value = v.notes || '';
  const icons = {student:'🎓',senior:'👴',pwd:'♿',regular:'🪪'};
  document.getElementById('review-info').innerHTML =
    `<b>${v.first_name} ${v.last_name}</b> — ${v.email}<br>
     AI Detected: ${icons[v.id_type]||'🪪'} <b>${v.id_type}</b> &nbsp;|&nbsp; Current: ${icons[v.current_id_type]||'🪪'} ${v.current_id_type}<br>
     Status: <b>${v.status}</b>`;
  const sel = document.getElementById('rev-idtype');
  for(let o of sel.options) if(o.value === v.id_type) o.selected = true;
  openModal('modal-review');
}
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
