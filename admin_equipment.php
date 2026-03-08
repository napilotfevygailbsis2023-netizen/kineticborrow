<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'equipment';
$msg = ''; $err = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $name     = trim($conn->real_escape_string($_POST['name']));
        $cat      = trim($conn->real_escape_string($_POST['category']));
        $price    = floatval($_POST['price_per_day']);
        $stock    = intval($_POST['stock']);
        $icon     = trim($conn->real_escape_string($_POST['icon']));
        $tag      = trim($conn->real_escape_string($_POST['tag']));
        $active   = isset($_POST['is_active']) ? 1 : 0;

        if ($act === 'add') {
            $stmt = $conn->prepare("INSERT INTO equipment (name,category,price_per_day,stock,icon,tag,is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('ssdiisi', $name,$cat,$price,$stock,$icon,$tag,$active);
            $stmt->execute();
            $msg = "Equipment '$name' added successfully.";
        } else {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE equipment SET name=?,category=?,price_per_day=?,stock=?,icon=?,tag=?,is_active=? WHERE id=?");
            $stmt->bind_param('ssdiisii', $name,$cat,$price,$stock,$icon,$tag,$active,$id);
            $stmt->execute();
            $msg = "Equipment updated.";
        }
    }

    if ($act === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("UPDATE equipment SET is_active=0 WHERE id=$id");
        $msg = "Equipment deactivated.";
    }
    if ($act === 'restore') {
        $id = intval($_POST['id']);
        $conn->query("UPDATE equipment SET is_active=1 WHERE id=$id");
        $msg = "Equipment restored.";
    }
}

$cat_filter = $conn->real_escape_string($_GET['cat'] ?? '');
$show_all   = isset($_GET['show_all']);
$sql = "SELECT * FROM equipment";
if (!$show_all) $sql .= " WHERE is_active=1";
if ($cat_filter) $sql .= ($show_all ? " WHERE" : " AND") . " category='$cat_filter'";
$sql .= " ORDER BY category, name";
$equipment = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT DISTINCT category FROM equipment ORDER BY category")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Equipment Management</div><div class="page-head-sub">Manage listings, categories, stock, and availability</div></div>
  <button class="btn btn-gold" onclick="openModal('modal-add')">+ Add Equipment</button>
</div>

<!-- Filters -->
<div class="search-bar">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn <?= !$cat_filter?'btn-gold':'btn-outline' ?>" href="admin_equipment.php<?= $show_all?'?show_all=1':'' ?>">All</a>
    <?php foreach($categories as $c): ?>
    <a class="btn <?= $cat_filter===$c['category']?'btn-gold':'btn-outline' ?>" href="admin_equipment.php?cat=<?= urlencode($c['category']) ?><?= $show_all?'&show_all=1':'' ?>"><?= htmlspecialchars($c['category']) ?></a>
    <?php endforeach; ?>
  </div>
  <a class="btn btn-outline btn-sm" href="admin_equipment.php?<?= $show_all?'':'show_all=1' ?><?= $cat_filter?"&cat=$cat_filter":'' ?>"><?= $show_all?'Hide Inactive':'Show Inactive' ?></a>
</div>

<div class="table-card">
  <table>
    <thead><tr><th>Icon</th><th>Name</th><th>Category</th><th>Price/Day</th><th>Stock</th><th>Tag</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($equipment as $eq): ?>
      <tr>
        <td style="font-size:24px"><?= $eq['icon'] ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($eq['name']) ?></td>
        <td><?= htmlspecialchars($eq['category']) ?></td>
        <td>₱<?= number_format($eq['price_per_day'],2) ?></td>
        <td>
          <span style="font-weight:600;color:<?= $eq['stock']<=2?'var(--red)':'var(--green)' ?>"><?= $eq['stock'] ?></span>
        </td>
        <td><?= $eq['tag'] ? '<span class="status-badge badge-gold">'.htmlspecialchars($eq['tag']).'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
        <td><span class="status-badge <?= $eq['is_active']?'s-active':'s-cancelled' ?>"><?= $eq['is_active']?'Active':'Inactive' ?></span></td>
        <td>
          <div class="btn-group">
            <button class="btn btn-outline btn-sm" onclick='editEquipment(<?= json_encode($eq) ?>)'>Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?= $eq['is_active']?'Deactivate':'Restore' ?> this equipment?')">
              <input type="hidden" name="act" value="<?= $eq['is_active']?'delete':'restore' ?>"/>
              <input type="hidden" name="id"  value="<?= $eq['id'] ?>"/>
              <button type="submit" class="btn <?= $eq['is_active']?'btn-red':'btn-green' ?> btn-sm"><?= $eq['is_active']?'Deactivate':'Restore' ?></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($equipment)): ?><tr class="empty-row"><td colspan="8">No equipment found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Add Equipment</h3><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="add"/>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" placeholder="Mountain Bike" required/></div>
        <div class="form-group"><label class="form-label">Category</label>
          <select class="form-control" name="category">
            <option>Cycling</option><option>Racket Sports</option><option>Water Sports</option><option>Combat Sports</option><option>Team Sports</option><option>Outdoor</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Price per Day (₱)</label><input class="form-control" name="price_per_day" type="number" step="0.01" placeholder="350.00" required/></div>
        <div class="form-group"><label class="form-label">Stock</label><input class="form-control" name="stock" type="number" placeholder="5" required/></div>
        <div class="form-group"><label class="form-label">Icon (emoji)</label><input class="form-control" name="icon" placeholder="🚵" maxlength="5"/></div>
        <div class="form-group"><label class="form-label">Tag (optional)</label><input class="form-control" name="tag" placeholder="Popular"/></div>
      </div>
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="is_active" checked/> Active / Available for rent</label></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button><button type="submit" class="btn btn-gold">Add Equipment</button></div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Edit Equipment</h3><button class="modal-close" onclick="closeModal('modal-edit')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="edit"/>
      <input type="hidden" name="id" id="edit-id"/>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" id="edit-name" required/></div>
        <div class="form-group"><label class="form-label">Category</label>
          <select class="form-control" name="category" id="edit-category">
            <option>Cycling</option><option>Racket Sports</option><option>Water Sports</option><option>Combat Sports</option><option>Team Sports</option><option>Outdoor</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Price per Day (₱)</label><input class="form-control" name="price_per_day" id="edit-price" type="number" step="0.01" required/></div>
        <div class="form-group"><label class="form-label">Stock</label><input class="form-control" name="stock" id="edit-stock" type="number" required/></div>
        <div class="form-group"><label class="form-label">Icon (emoji)</label><input class="form-control" name="icon" id="edit-icon" maxlength="5"/></div>
        <div class="form-group"><label class="form-label">Tag</label><input class="form-control" name="tag" id="edit-tag"/></div>
      </div>
      <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px"><input type="checkbox" name="is_active" id="edit-active"/> Active / Available</label></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Cancel</button><button type="submit" class="btn btn-gold">Save Changes</button></div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function editEquipment(eq) {
  document.getElementById('edit-id').value       = eq.id;
  document.getElementById('edit-name').value     = eq.name;
  document.getElementById('edit-price').value    = eq.price_per_day;
  document.getElementById('edit-stock').value    = eq.stock;
  document.getElementById('edit-icon').value     = eq.icon;
  document.getElementById('edit-tag').value      = eq.tag || '';
  document.getElementById('edit-active').checked = eq.is_active == 1;
  const sel = document.getElementById('edit-category');
  for(let o of sel.options) if(o.value === eq.category) o.selected = true;
  openModal('modal-edit');
}
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>

<?php include 'includes/admin_layout_end.php'; ?>
