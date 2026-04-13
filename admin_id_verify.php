<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'id_verify';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['act']       ?? '';
    $vid    = intval($_POST['verify_id']  ?? 0);
    $uid    = intval($_POST['user_id']    ?? 0);
    $notes  = $conn->real_escape_string($_POST['notes'] ?? '');
    $admin_id = $_SESSION['admin_id'];

    if ($act === 'approve') {
        $conn->query("UPDATE id_verifications SET status='approved', notes='$notes', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $conn->query("UPDATE users SET id_verified=1, id_status='approved', id_reject_reason=NULL WHERE id=$uid");
        $msg = "✅ ID approved. Customer can now book equipment and gets the applicable discount.";
    }
    if ($act === 'reject_no_discount') {
        // ID is valid but not eligible for discount (e.g. regular gov ID submitted hoping for discount)
        $reason = "ID type is not eligible for the 20% discount. Only Student ID, Senior Citizen ID, and PWD ID qualify. Your account is still active and you may borrow equipment. — $notes";
        $conn->query("UPDATE id_verifications SET status='rejected', notes='".($conn->real_escape_string($reason))."', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $conn->query("UPDATE users SET id_verified=1, id_status='approved', id_reject_reason='".($conn->real_escape_string($reason))."' WHERE id=$uid");
        // Still approve so they can borrow, just no discount
        $msg = "ID approved for borrowing but no discount applied (not eligible ID type).";
    }
    if ($act === 'reject_invalid') {
        // ID is invalid / cannot borrow
        $reason = $notes ?: 'ID could not be verified. Please resubmit a clear photo of a valid ID.';
        $conn->query("UPDATE id_verifications SET status='rejected', notes='".($conn->real_escape_string($reason))."', reviewed_by=$admin_id, updated_at=NOW() WHERE id=$vid");
        $conn->query("UPDATE users SET id_verified=0, id_status='rejected', id_reject_reason='".($conn->real_escape_string($reason))."' WHERE id=$uid");
        $msg = "❌ ID rejected. Customer cannot borrow equipment until they resubmit a valid ID.";
    }
}

$filter = $_GET['filter'] ?? 'pending';
$safe_f = in_array($filter,['pending','approved','rejected','all'])?$filter:'pending';
$sql = "SELECT v.*, u.first_name, u.last_name, u.email, u.id_type as current_id_type
        FROM id_verifications v JOIN users u ON v.user_id = u.id";
if ($safe_f !== 'all') $sql .= " WHERE v.status='$safe_f'";
$sql .= " ORDER BY v.created_at DESC";
$page  = max(1, intval($_GET['p'] ?? 1));
$limit = 15; $offset = ($page-1)*$limit;
$total_v  = $conn->query(str_replace("SELECT v.*", "SELECT COUNT(*)", $sql))->fetch_row()[0];
$total_pg = max(1, ceil($total_v/$limit));
$verifications = $conn->query($sql." LIMIT $limit OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

$counts = [];
foreach(['pending','approved','rejected','all'] as $s) {
    $q = $s === 'all' ? "SELECT COUNT(*) FROM id_verifications" : "SELECT COUNT(*) FROM id_verifications WHERE status='$s'";
    $counts[$s] = $conn->query($q)->fetch_row()[0];
}

include 'includes/admin_layout.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);">ID Verification</div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;">Review customer-submitted IDs — approve or reject</div>
  </div>
</div>


<?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>



<!-- INFO BOX -->
<div style="background:var(--gold-bg);border:1px solid #EDD8B0;border-radius:12px;padding:14px 18px;margin-bottom:18px;font-size:13px;color:#7A5C1E">
  <strong>ℹ️ Rejection Types:</strong>
  <span style="margin-left:8px">
    <strong>Reject (No Discount)</strong> — ID is valid but not eligible for 20% off (e.g. regular gov't ID). Customer can still borrow.
    &nbsp;·&nbsp;
    <strong>Reject (Invalid)</strong> — ID cannot be verified. Customer <u>cannot borrow</u> until resubmission.
  </span>
</div>

<div class="search-bar">
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach(['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','all'=>'All'] as $k=>$v): ?>
    <a class="btn <?= $safe_f===$k?'btn-gold':'btn-outline' ?> btn-sm" href="admin_id_verify.php?filter=<?= $k ?>">
      <?= $v ?> <span style="background:rgba(0,0,0,.12);border-radius:10px;padding:1px 7px;margin-left:4px;font-size:10px"><?= $counts[$k] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Customer</th><th>Email</th><th>ID Photo</th><th>Detected Type</th><th>AI Notes</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($verifications as $v): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></td>
        <td style="font-size:12px"><?= htmlspecialchars($v['email']) ?></td>
        <td>
          <?php if($v['id_image'] && file_exists($v['id_image'])): ?>
            <img src="<?= htmlspecialchars($v['id_image']) ?>" style="width:60px;height:44px;object-fit:cover;border-radius:6px;cursor:pointer;border:1px solid var(--border)"
              onclick="window.open('<?= htmlspecialchars($v['id_image']) ?>','_blank')"/>
          <?php else: ?><span style="color:var(--muted);font-size:12px">No photo</span><?php endif; ?>
        </td>
        <td><?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; echo ($icons[$v['id_type']]??'🪪').' '.ucfirst($v['id_type']); ?></td>
        <td style="font-size:11px;color:var(--muted);max-width:160px"><?= htmlspecialchars(substr($v['notes']??'—',0,80)) ?></td>
        <td>
          <?php $bs=['approved'=>'s-active','rejected'=>'s-cancelled','pending'=>'s-pending','escalated'=>'s-reviewed']; ?>
          <span class="status-badge <?= $bs[$v['status']]??'s-pending' ?>"><?= ucfirst($v['status']) ?></span>
        </td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($v['created_at'])) ?></td>
        <td>
          <?php if($v['status'] === 'pending'): ?>
          <div class="btn-group" style="flex-direction:column;gap:5px">
            <button class="btn btn-green btn-sm" onclick="reviewID(<?= $v['id'] ?>,<?= $v['user_id'] ?>,'approve','<?= htmlspecialchars(addslashes($v['first_name'].' '.$v['last_name'])) ?>')">✅ Approve</button>
            <button class="btn btn-outline btn-sm" style="color:var(--orange);border-color:var(--orange)" onclick="reviewID(<?= $v['id'] ?>,<?= $v['user_id'] ?>,'reject_no_discount','<?= htmlspecialchars(addslashes($v['first_name'].' '.$v['last_name'])) ?>')">⚠️ No Discount</button>
            <button class="btn btn-red btn-sm" onclick="reviewID(<?= $v['id'] ?>,<?= $v['user_id'] ?>,'reject_invalid','<?= htmlspecialchars(addslashes($v['first_name'].' '.$v['last_name'])) ?>')">❌ Invalid ID</button>
          </div>
          <?php else: ?>
            <span style="font-size:11px;color:var(--muted)">Reviewed</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($verifications)): ?><tr class="empty-row"><td colspan="8">No <?= $safe_f !== 'all'?$safe_f:'' ?> verifications.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if($total_pg>1): ?>
  <div class="pager">
    <?php for($i=1;$i<=$total_pg;$i++): ?>
      <?php if($i==1||$i==$total_pg||abs($i-$page)<=2): ?>
        <a href="?filter=<?=$safe_f?>&p=<?=$i?>" <?=$i==$page?'class="cur"':''?>><?=$i?></a>
      <?php elseif(abs($i-$page)==3): ?><span style="color:var(--muted);padding:0 4px;border:none">…</span><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- REVIEW MODAL -->
<div class="modal-overlay" id="modal-review">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><h3 class="modal-title" id="review-title">Review ID</h3><button class="modal-close" onclick="closeModal('modal-review')">×</button></div>
    <form method="POST">
      <input type="hidden" name="verify_id" id="rv-vid"/>
      <input type="hidden" name="user_id"   id="rv-uid"/>
      <input type="hidden" name="act"        id="rv-act"/>
      <div class="modal-info" id="rv-info" style="margin-bottom:14px"></div>
      <div class="form-group">
        <label class="form-label">Notes / Reason <span id="rv-note-hint" style="font-weight:400;color:var(--muted);text-transform:none"></span></label>
        <textarea class="form-control" name="notes" id="rv-notes" rows="3" placeholder="Optional notes..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-review')">Cancel</button>
        <button type="submit" class="btn" id="rv-submit-btn">Confirm</button>
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

function reviewID(vid, uid, act, name) {
  document.getElementById('rv-vid').value = vid;
  document.getElementById('rv-uid').value = uid;
  document.getElementById('rv-act').value = act;

  const titles = {
    'approve':           '✅ Approve ID',
    'reject_no_discount':'⚠️ Reject — No Discount',
    'reject_invalid':    '❌ Reject — Invalid ID'
  };
  const infos = {
    'approve':           name + "'s ID will be approved. They can borrow equipment and receive any applicable discount.",
    'reject_no_discount': name + "'s ID will be marked approved for borrowing but NO discount will be applied (ID type not eligible).",
    'reject_invalid':    name + "'s ID will be rejected. They CANNOT borrow equipment until they resubmit a valid ID."
  };
  const btnColors = {approve:'btn-green', reject_no_discount:'btn-orange', reject_invalid:'btn-red'};

  document.getElementById('review-title').textContent = titles[act];
  document.getElementById('rv-info').textContent = infos[act];
  const btn = document.getElementById('rv-submit-btn');
  btn.className = 'btn ' + (btnColors[act]||'btn-gold');
  btn.textContent = titles[act];
  document.getElementById('rv-notes').value = '';
  openModal('modal-review');
}
</script>
<?php include 'includes/admin_layout_end.php'; ?>
