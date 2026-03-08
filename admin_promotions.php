<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'promotions';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $title      = $conn->real_escape_string($_POST['title']);
        $code       = strtoupper($conn->real_escape_string($_POST['code']));
        $type       = in_array($_POST['type'],['percentage','fixed'])?$_POST['type']:'percentage';
        $value      = floatval($_POST['value']);
        $min_days   = intval($_POST['min_days']);
        $start      = $conn->real_escape_string($_POST['start_date']);
        $end        = $conn->real_escape_string($_POST['end_date']);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($act === 'add') {
            $stmt = $conn->prepare("INSERT INTO promotions (title,code,type,value,min_days,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssdiisi',$title,$code,$type,$value,$min_days,$start,$end,$is_active);
            if ($stmt->execute()) $msg = "Promotion '$title' created.";
            else $msg = "Error: code may already exist.";
        } else {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE promotions SET title=?,code=?,type=?,value=?,min_days=?,start_date=?,end_date=?,is_active=? WHERE id=?");
            $stmt->bind_param('sssdiisii',$title,$code,$type,$value,$min_days,$start,$end,$is_active,$id);
            $stmt->execute();
            $msg = "Promotion updated.";
        }
    }
    if ($act === 'toggle') {
        $id  = intval($_POST['id']);
        $cur = intval($_POST['current']);
        $conn->query("UPDATE promotions SET is_active=".($cur?0:1)." WHERE id=$id");
        $msg = "Promotion ".($cur?"deactivated":"activated").".";
    }
    if ($act === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM promotions WHERE id=$id");
        $msg = "Promotion deleted.";
    }
}

$promos = $conn->query("SELECT * FROM promotions ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Promotions & Campaigns</div><div class="page-head-sub">Create and manage discount codes, seasonal deals, and loyalty rewards</div></div>
  <button class="btn btn-gold" onclick="openModal('modal-add')">+ New Promotion</button>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Title</th><th>Code</th><th>Type</th><th>Value</th><th>Min Days</th><th>Valid Period</th><th>Used</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($promos as $p): ?>
      <?php $expired = strtotime($p['end_date']) < time(); ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($p['title']) ?></td>
        <td><code style="background:var(--gold-bg);color:var(--gold);padding:3px 8px;border-radius:5px;font-weight:700"><?= htmlspecialchars($p['code']) ?></code></td>
        <td><?= $p['type'] === 'percentage' ? '📊 Percentage' : '💰 Fixed (₱)' ?></td>
        <td style="font-weight:600;color:var(--gold)"><?= $p['type']==='percentage' ? $p['value'].'%' : '₱'.number_format($p['value'],0) ?></td>
        <td><?= $p['min_days'] ?> day<?= $p['min_days']>1?'s':'' ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j', strtotime($p['start_date'])) ?> – <?= date('M j, Y', strtotime($p['end_date'])) ?></td>
        <td><?= $p['usage_count'] ?> times</td>
        <td>
          <?php if($expired): ?>
          <span class="status-badge s-cancelled">Expired</span>
          <?php else: ?>
          <span class="status-badge <?= $p['is_active']?'s-active':'s-returned' ?>"><?= $p['is_active']?'Active':'Inactive' ?></span>
          <?php endif; ?>
        </td>
        <td>
          <div class="btn-group">
            <button class="btn btn-outline btn-sm" onclick='editPromo(<?= json_encode($p) ?>)'>Edit</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="act" value="toggle"/>
              <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
              <input type="hidden" name="current" value="<?= $p['is_active'] ?>"/>
              <button type="submit" class="btn <?= $p['is_active']?'btn-outline':'btn-green' ?> btn-sm"><?= $p['is_active']?'Disable':'Enable' ?></button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this promotion?')">
              <input type="hidden" name="act" value="delete"/>
              <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
              <button type="submit" class="btn btn-red btn-sm">Del</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($promos)): ?><tr class="empty-row"><td colspan="9">No promotions yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">New Promotion</h3><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="add"/>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Summer Sale 2026" required/></div>
        <div class="form-group"><label class="form-label">Promo Code</label><input class="form-control" name="code" placeholder="SUMMER25" style="text-transform:uppercase" required/></div>
        <div class="form-group"><label class="form-label">Discount Type</label>
          <select class="form-control" name="type">
            <option value="percentage">📊 Percentage (%)</option>
            <option value="fixed">💰 Fixed Amount (₱)</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Discount Value</label><input class="form-control" name="value" type="number" step="0.01" placeholder="25" required/></div>
        <div class="form-group"><label class="form-label">Minimum Days</label><input class="form-control" name="min_days" type="number" value="1" min="1"/></div>
        <div class="form-group"></div>
        <div class="form-group"><label class="form-label">Start Date</label><input class="form-control" name="start_date" type="date" required/></div>
        <div class="form-group"><label class="form-label">End Date</label><input class="form-control" name="end_date" type="date" required/></div>
      </div>
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="is_active" checked/> Active immediately</label></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button><button type="submit" class="btn btn-gold">Create Promotion</button></div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Edit Promotion</h3><button class="modal-close" onclick="closeModal('modal-edit')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="edit"/>
      <input type="hidden" name="id" id="edit-id"/>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Title</label><input class="form-control" name="title" id="edit-title" required/></div>
        <div class="form-group"><label class="form-label">Code</label><input class="form-control" name="code" id="edit-code" required/></div>
        <div class="form-group"><label class="form-label">Type</label>
          <select class="form-control" name="type" id="edit-type">
            <option value="percentage">📊 Percentage</option>
            <option value="fixed">💰 Fixed (₱)</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Value</label><input class="form-control" name="value" id="edit-value" type="number" step="0.01"/></div>
        <div class="form-group"><label class="form-label">Min Days</label><input class="form-control" name="min_days" id="edit-min" type="number"/></div>
        <div class="form-group"></div>
        <div class="form-group"><label class="form-label">Start Date</label><input class="form-control" name="start_date" id="edit-start" type="date"/></div>
        <div class="form-group"><label class="form-label">End Date</label><input class="form-control" name="end_date" id="edit-end" type="date"/></div>
      </div>
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="is_active" id="edit-active"/> Active</label></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Cancel</button><button type="submit" class="btn btn-gold">Save Changes</button></div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function editPromo(p) {
  document.getElementById('edit-id').value    = p.id;
  document.getElementById('edit-title').value = p.title;
  document.getElementById('edit-code').value  = p.code;
  document.getElementById('edit-value').value = p.value;
  document.getElementById('edit-min').value   = p.min_days;
  document.getElementById('edit-start').value = p.start_date;
  document.getElementById('edit-end').value   = p.end_date;
  document.getElementById('edit-active').checked = p.is_active == 1;
  const sel = document.getElementById('edit-type');
  for(let o of sel.options) if(o.value === p.type) o.selected = true;
  openModal('modal-edit');
}
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
