<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'incidents';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iid    = intval($_POST['incident_id']);
    $status = in_array($_POST['status'],['open','reviewed','resolved'])?$_POST['status']:'reviewed';
    $notes  = $conn->real_escape_string($_POST['admin_notes'] ?? '');
    $conn->query("UPDATE incident_reports SET status='$status', admin_notes='$notes', updated_at=NOW() WHERE id=$iid");
    // If resolved and damage, we could auto-deactivate equipment
    if ($status === 'resolved' && $_POST['deactivate_eq'] ?? false) {
        $eqid = intval($_POST['eq_id']);
        $conn->query("UPDATE equipment SET is_active=0 WHERE id=$eqid");
    }
    $msg = "Incident report updated.";
}

$filter = $_GET['filter'] ?? 'open';
$sql = "SELECT ir.*, e.name as eq_name, e.icon as eq_icon, e.id as equipment_id,
               h.name as handler_name, r.order_code,
               u.first_name, u.last_name
        FROM incident_reports ir
        JOIN equipment e ON ir.equipment_id = e.id
        JOIN handlers h ON ir.handler_id = h.id
        LEFT JOIN rentals r ON ir.rental_id = r.id
        LEFT JOIN users u ON r.user_id = u.id";
if ($filter !== 'all') $sql .= " WHERE ir.status='$filter'";
$sql .= " ORDER BY ir.created_at DESC";
$incidents = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$open_count     = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status='open'")->fetch_row()[0];
$reviewed_count = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status='reviewed'")->fetch_row()[0];
$resolved_count = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status='resolved'")->fetch_row()[0];

include 'includes/admin_layout.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);">Incident Reports</div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;">Review and resolve handler-submitted equipment incidents</div>
  </div>
</div>


<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>



<!-- MINI STATS -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px">
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">🔴</span><span class="stat-card-badge badge-red">Action Needed</span></div>
    <div class="stat-val"><?= $open_count ?></div><div class="stat-lbl">Open reports</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">🔵</span><span class="stat-card-badge badge-blue">In Progress</span></div>
    <div class="stat-val"><?= $reviewed_count ?></div><div class="stat-lbl">Under review</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><span class="stat-card-icon">✅</span><span class="stat-card-badge badge-green">Done</span></div>
    <div class="stat-val"><?= $resolved_count ?></div><div class="stat-lbl">Resolved</div>
  </div>
</div>

<div class="search-bar">
  <?php foreach(['open'=>'🔴 Open','reviewed'=>'🔵 Reviewed','resolved'=>'✅ Resolved','all'=>'All'] as $k=>$v): ?>
  <a class="btn <?= $filter===$k?'btn-red':'btn-outline' ?> btn-sm" href="admin_incidents.php?filter=<?= $k ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Date</th><th>Equipment</th><th>Order</th><th>Customer</th><th>Type</th><th>Severity</th><th>Handler</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($incidents as $i): ?>
      <tr style="<?= $i['severity']==='severe'&&$i['status']==='open'?'background:#FFF5F5':'' ?>">
        <td style="font-size:12px;white-space:nowrap"><?= date('M j, Y', strtotime($i['created_at'])) ?></td>
        <td><?= $i['eq_icon'] ?> <strong><?= htmlspecialchars($i['eq_name']) ?></strong></td>
        <td style="color:var(--gold);font-size:12px"><?= $i['order_code'] ?: '—' ?></td>
        <td style="font-size:12px"><?= $i['first_name'] ? htmlspecialchars($i['first_name'].' '.$i['last_name']) : '—' ?></td>
        <td><?php $ti=['damage'=>'💥','loss'=>'🔍','incident'=>'⚠️','maintenance'=>'🔧']; echo $ti[$i['type']].' '.ucfirst($i['type']); ?></td>
        <td><span class="status-badge severity-<?= $i['severity'] ?>"><?= ucfirst($i['severity']) ?></span></td>
        <td style="font-size:12px"><?= htmlspecialchars($i['handler_name']) ?></td>
        <td><span class="status-badge s-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td>
        <td><button class="btn btn-outline btn-sm" onclick='reviewIncident(<?= json_encode($i) ?>)'>Review</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($incidents)): ?><tr class="empty-row"><td colspan="9">No <?= $filter !== 'all' ? $filter : '' ?> incidents.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- REVIEW MODAL -->
<div class="modal-overlay" id="modal-review">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Review Incident</h3><button class="modal-close" onclick="closeModal('modal-review')">×</button></div>
    <div class="modal-info" id="incident-info"></div>
    <form method="POST">
      <input type="hidden" name="incident_id" id="inc-id"/>
      <input type="hidden" name="eq_id" id="inc-eq-id"/>
      <div class="form-group">
        <label class="form-label">Update Status</label>
        <select class="form-control" name="status" id="inc-status">
          <option value="open">🔴 Open</option>
          <option value="reviewed">🔵 Reviewed</option>
          <option value="resolved">✅ Resolved</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Admin Notes / Resolution</label>
        <textarea class="form-control" name="admin_notes" id="inc-notes" rows="3" placeholder="Add your review notes or resolution steps..."></textarea>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="deactivate_eq" id="inc-deactivate"/>
          Deactivate equipment (remove from listings)
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-review')">Cancel</button>
        <button type="submit" class="btn btn-gold">Save Review</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function reviewIncident(i) {
  document.getElementById('inc-id').value    = i.id;
  document.getElementById('inc-eq-id').value = i.equipment_id;
  document.getElementById('inc-notes').value = i.admin_notes || '';
  document.getElementById('inc-deactivate').checked = false;
  const sel = document.getElementById('inc-status');
  for(let o of sel.options) if(o.value===i.status) o.selected=true;
  const ti = {damage:'💥',loss:'🔍',incident:'⚠️',maintenance:'🔧'};
  document.getElementById('incident-info').innerHTML =
    `<b>${i.eq_icon} ${i.eq_name}</b>${i.order_code?' · Order '+i.order_code:''}<br>
     Type: ${ti[i.type]||''} <b>${i.type}</b> · Severity: <b>${i.severity}</b><br>
     Reported by: ${i.handler_name} on ${i.created_at.substring(0,10)}<br>
     <div style="margin-top:8px;padding:8px;background:#F5F2EE;border-radius:8px;font-size:12px">${i.description}</div>`;
  openModal('modal-review');
}
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
