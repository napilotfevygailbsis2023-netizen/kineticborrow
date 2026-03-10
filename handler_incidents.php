<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'incidents';
$hid = $_SESSION['handler_id'];
$msg = ''; $err = '';

// Submit new incident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'report') {
    $eq_id      = intval($_POST['equipment_id']);
    $rental_id  = intval($_POST['rental_id']) ?: 'NULL';
    $type       = in_array($_POST['type'],['damage','loss','incident','maintenance'])?$_POST['type']:'incident';
    $severity   = in_array($_POST['severity'],['minor','moderate','severe'])?$_POST['severity']:'minor';
    $desc       = $conn->real_escape_string($_POST['description']);

    if (empty($desc)) {
        $err = "Please provide a description.";
    } elseif (!$eq_id) {
        $err = "Please select equipment.";
    } else {
        $rental_val = is_int($rental_id) && $rental_id > 0 ? $rental_id : 'NULL';
        $conn->query("INSERT INTO incident_reports (rental_id, equipment_id, handler_id, type, severity, description)
                      VALUES ($rental_val, $eq_id, $hid, '$type', '$severity', '$desc')");
        $msg = "🚨 Incident reported and sent to admin for review.";
    }
}

// My incidents + all incidents
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT ir.*, e.name as eq_name, e.icon as eq_icon, h.name as handler_name,
               r.order_code
        FROM incident_reports ir
        JOIN equipment e ON ir.equipment_id = e.id
        JOIN handlers h ON ir.handler_id = h.id
        LEFT JOIN rentals r ON ir.rental_id = r.id";
if ($filter === 'mine') $sql .= " WHERE ir.handler_id=$hid";
if ($filter === 'open') $sql .= " WHERE ir.status='open'";
$sql .= " ORDER BY ir.created_at DESC";
$incidents = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Equipment list for form
$equipment_list = $conn->query("SELECT id, name, icon FROM equipment WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Active rentals for linking
$active_rentals = $conn->query("
    SELECT r.id, r.order_code, u.first_name, u.last_name, e.name as eq_name
    FROM rentals r JOIN users u ON r.user_id=u.id JOIN equipment e ON r.equipment_id=e.id
    WHERE r.status='active'
    ORDER BY r.order_code DESC
")->fetch_all(MYSQLI_ASSOC);

include 'includes/handler_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Incident Reports</div><div class="page-head-sub">Report equipment damage, loss, or incidents for administrator review</div></div>
  <button class="btn btn-red" onclick="openModal('modal-report')">🚨 File Incident Report</button>
</div>

<!-- FILTER -->
<div class="search-bar">
  <a class="btn <?= $filter==='all'?'btn-teal':'btn-outline' ?> btn-sm" href="handler_incidents.php?filter=all">All Reports</a>
  <a class="btn <?= $filter==='open'?'btn-red':'btn-outline' ?> btn-sm" href="handler_incidents.php?filter=open">🔴 Open</a>
  <a class="btn <?= $filter==='mine'?'btn-teal':'btn-outline' ?> btn-sm" href="handler_incidents.php?filter=mine">My Reports</a>
</div>

<div class="table-card">
  <div class="table-header">
    <span class="table-title">Incident Log</span>
    <span style="font-size:12px;color:var(--muted)"><?= count($incidents) ?> report<?= count($incidents)!==1?'s':'' ?></span>
  </div>
  <table>
    <thead><tr><th>Date</th><th>Equipment</th><th>Order</th><th>Type</th><th>Severity</th><th>Description</th><th>Handler</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($incidents as $i): ?>
      <tr>
        <td style="font-size:12px;white-space:nowrap"><?= date('M j, Y', strtotime($i['created_at'])) ?><br><span style="color:var(--muted)"><?= date('g:i A', strtotime($i['created_at'])) ?></span></td>
        <td><?= $i['eq_icon'] ?> <strong><?= htmlspecialchars($i['eq_name']) ?></strong></td>
        <td style="color:var(--teal);font-weight:600"><?= $i['order_code'] ? htmlspecialchars($i['order_code']) : '—' ?></td>
        <td>
          <?php $type_icons=['damage'=>'💥','loss'=>'🔍','incident'=>'⚠️','maintenance'=>'🔧']; ?>
          <?= $type_icons[$i['type']] ?> <?= ucfirst($i['type']) ?>
        </td>
        <td><span class="status-badge severity-<?= $i['severity'] ?>"><?= ucfirst($i['severity']) ?></span></td>
        <td style="max-width:200px;font-size:12px;color:var(--text2)"><?= htmlspecialchars(substr($i['description'],0,80)).(strlen($i['description'])>80?'...':'') ?></td>
        <td style="font-size:12px"><?= htmlspecialchars($i['handler_name']) ?></td>
        <td>
          <span class="status-badge s-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span>
          <?php if($i['admin_notes']): ?>
          <div style="font-size:11px;color:var(--muted);margin-top:4px">💬 <?= htmlspecialchars(substr($i['admin_notes'],0,40)) ?></div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($incidents)): ?><tr class="empty-row"><td colspan="8">No incident reports found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- REPORT MODAL -->
<div class="modal-overlay" id="modal-report">
  <div class="modal" style="width:540px">
    <div class="modal-header">
      <h3 class="modal-title">🚨 File Incident Report</h3>
      <button class="modal-close" onclick="closeModal('modal-report')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="report"/>

      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Equipment *</label>
          <select class="form-control" name="equipment_id" required>
            <option value="">— Select equipment —</option>
            <?php foreach($equipment_list as $e): ?>
            <option value="<?= $e['id'] ?>"><?= $e['icon'] ?> <?= htmlspecialchars($e['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Linked Rental (optional)</label>
          <select class="form-control" name="rental_id">
            <option value="">— None —</option>
            <?php foreach($active_rentals as $r): ?>
            <option value="<?= $r['id'] ?>"><?= $r['order_code'] ?> — <?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Incident Type *</label>
          <select class="form-control" name="type" required>
            <option value="damage">💥 Damage</option>
            <option value="loss">🔍 Loss / Missing</option>
            <option value="incident">⚠️ Incident</option>
            <option value="maintenance">🔧 Maintenance Needed</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Severity *</label>
          <select class="form-control" name="severity" required>
            <option value="minor">🟡 Minor</option>
            <option value="moderate">🟠 Moderate</option>
            <option value="severe">🔴 Severe</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description *</label>
        <textarea class="form-control" name="description" rows="4" placeholder="Describe what happened in detail — what was damaged, how it occurred, what action was taken..." required></textarea>
      </div>

      <div style="background:var(--orange-bg);border:1px solid #FADDB8;border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:12px;color:var(--orange)">
        ℹ️ This report will be sent directly to the admin for review. They may follow up with you.
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-report')">Cancel</button>
        <button type="submit" class="btn btn-red">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/handler_layout_end.php'; ?>
