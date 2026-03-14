<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'equipment';
$msg = $_SESSION['eq_msg'] ?? ''; unset($_SESSION['eq_msg']);
$err = $_SESSION['eq_err'] ?? ''; unset($_SESSION['eq_err']);
$active_tab = $_GET['tab'] ?? 'all';

$conn->query("ALTER TABLE equipment ADD COLUMN IF NOT EXISTS image VARCHAR(255) NULL");
$conn->query("ALTER TABLE equipment ADD COLUMN IF NOT EXISTS description TEXT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Auto-assign emoji icon based on category
    $cat_icons = [
        'Cycling'       => '🚴',
        'Racket Sports' => '🎾',
        'Water Sports'  => '🏊',
        'Combat Sports' => '🥊',
        'Team Sports'   => '⚽',
        'Outdoor'       => '🏕️',
        'Fitness'       => '🏋️',
        'Winter Sports' => '⛷️',
    ];

    if ($act === 'add') {
        $name  = trim($conn->real_escape_string($_POST['name']));
        $desc  = trim($conn->real_escape_string($_POST['description'] ?? ''));
        $cat   = trim($conn->real_escape_string($_POST['category']));
        $price = floatval($_POST['price_per_day']);
        $qty   = intval($_POST['stock']);
        $is_active = $qty > 0 ? 1 : 0;
        $image_path = null;
        if (!empty($_FILES['eq_image']['name']) && $_FILES['eq_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/equipment/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['eq_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png','webp']) && $_FILES['eq_image']['size'] <= 5*1024*1024) {
                $fname = 'eq_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['eq_image']['tmp_name'], $upload_dir.$fname))
                    $image_path = $upload_dir.$fname;
            }
        }
        $conn->query("ALTER TABLE equipment ADD COLUMN IF NOT EXISTS icon VARCHAR(10) NULL");
        $icon = $cat_icons[$cat] ?? '🏅';
        // sssdii s s = name(s), desc(s), cat(s), price(d), stock(i), is_active(i), image(s), icon(s)
        $stmt = $conn->prepare("INSERT INTO equipment (name, description, category, price_per_day, stock, is_active, image, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssdiiss', $name, $desc, $cat, $price, $qty, $is_active, $image_path, $icon);
        $stmt->execute();
        $_SESSION['eq_msg'] = "✅ Equipment '$name' added successfully.";
        header('Location: admin_equipment.php?tab=all'); exit();
    }

    if ($act === 'edit') {
        $id    = intval($_POST['id']);
        $name  = trim($conn->real_escape_string($_POST['name']));
        $desc  = trim($conn->real_escape_string($_POST['description'] ?? ''));
        $cat   = trim($conn->real_escape_string($_POST['category']));
        $price = floatval($_POST['price_per_day']);
        $qty   = intval($_POST['stock']);
        $is_active = $qty > 0 ? 1 : 0;
        $img_sql = '';
        if (!empty($_FILES['eq_image']['name']) && $_FILES['eq_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/equipment/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['eq_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png','webp']) && $_FILES['eq_image']['size'] <= 5*1024*1024) {
                $fname = 'eq_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['eq_image']['tmp_name'], $upload_dir.$fname))
                    $img_sql = ",image='".$conn->real_escape_string($upload_dir.$fname)."'";
            }
        }
        $icon = $cat_icons[$cat] ?? '🏅';
        $conn->query("UPDATE equipment SET name='$name',description='$desc',category='$cat',price_per_day=$price,stock=$qty,is_active=$is_active,icon='$icon'$img_sql WHERE id=$id");
        $_SESSION['eq_msg'] = "✅ Equipment updated successfully.";
        header('Location: admin_equipment.php?tab=all'); exit();
    }

    if ($act === 'archive') {
        $conn->query("UPDATE equipment SET is_active=0 WHERE id=".intval($_POST['id']));
        $_SESSION['eq_msg'] = "📦 Equipment archived.";
        header('Location: admin_equipment.php?tab=all'); exit();
    }
    if ($act === 'restore') {
        $conn->query("UPDATE equipment SET is_active=1 WHERE id=".intval($_POST['id']));
        $_SESSION['eq_msg'] = "✅ Equipment restored.";
        header('Location: admin_equipment.php?tab=archive'); exit();
    }
    if ($act === 'delete_perm') {
        $id = intval($_POST['id']);
        $has = $conn->query("SELECT COUNT(*) FROM rentals WHERE equipment_id=$id")->fetch_row()[0];
        if ($has) {
            $_SESSION['eq_err'] = "❌ Cannot delete — this equipment has rental history.";
            header('Location: admin_equipment.php?tab=archive'); exit();
        } else {
            $conn->query("DELETE FROM equipment WHERE id=$id");
            $_SESSION['eq_msg'] = "🗑️ Equipment permanently deleted.";
            header('Location: admin_equipment.php?tab=archive'); exit();
        }
    }
}

// Filters
$cat_f   = $conn->real_escape_string($_GET['cat']   ?? '');
$avail_f = $_GET['avail'] ?? '';
$sort_f  = in_array($_GET['sort']??'',['price_asc','price_desc','stock_asc','stock_desc']) ? $_GET['sort'] : 'priority';
$search_f = $conn->real_escape_string($_GET['sq'] ?? '');
$pg = max(1, intval($_GET['pg'] ?? 1));
$per_page = 15;

$sort_map = ['price_asc'=>'price_per_day ASC','price_desc'=>'price_per_day DESC','stock_asc'=>'stock ASC','stock_desc'=>'stock DESC',
             'priority'=>'FIELD(IF(stock=0,"none",IF(stock<=5,"low","ok")),"none","low","ok"), name ASC'];
$order_by = $sort_map[$sort_f];

$where = ["is_active=1"];
if ($cat_f)             $where[] = "category='$cat_f'";
if ($avail_f==='avail') $where[] = "stock >= 6";
if ($avail_f==='low')   $where[] = "stock BETWEEN 1 AND 5";
if ($avail_f==='none')  $where[] = "stock = 0";
if ($search_f)          $where[] = "(name LIKE '%$search_f%' OR description LIKE '%$search_f%' OR category LIKE '%$search_f%')";

$where_sql = implode(' AND ', $where);
$total_count = $conn->query("SELECT COUNT(*) FROM equipment WHERE $where_sql")->fetch_row()[0];
$total_pages = max(1, ceil($total_count / $per_page));
$offset      = ($pg - 1) * $per_page;

$equipment  = $conn->query("SELECT * FROM equipment WHERE $where_sql ORDER BY $order_by LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
$archived   = $conn->query("SELECT * FROM equipment WHERE is_active=0 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT DISTINCT category FROM equipment ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// Build filter query string helper
function fqs($extra=[]) {
    global $cat_f, $avail_f, $sort_f, $search_f;
    $p = ['tab'=>'all'];
    if ($cat_f)   $p['cat']  = $cat_f;
    if ($avail_f) $p['avail']= $avail_f;
    if ($sort_f && $sort_f!=='priority') $p['sort'] = $sort_f;
    if ($search_f) $p['sq'] = $search_f;
    return '?' . http_build_query(array_merge($p, $extra));
}

include 'includes/admin_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="page-head">
  <div><div class="page-head-title">Equipment Management</div><div class="page-head-sub">Manage listings, stock, images, and availability</div></div>
</div>

<!-- TABS -->
<div style="display:flex;border-bottom:2px solid var(--border);margin-bottom:20px">
  <?php foreach(['all'=>'📋 All Equipment','add'=>'➕ Add Equipment','archive'=>'📦 Archived ('.count($archived).')'] as $k=>$lbl): $on=$active_tab===$k; ?>
  <a href="admin_equipment.php?tab=<?=$k?>" style="padding:11px 22px;font-size:13px;font-weight:<?=$on?'700':'500'?>;color:<?=$on?'var(--gold)':'var(--muted)'?>;border-bottom:3px solid <?=$on?'var(--gold)':'transparent'?>;text-decoration:none;background:<?=$on?'var(--gold-bg)':'transparent'?>;border-radius:8px 8px 0 0;margin-bottom:-2px;transition:all .18s"><?=$lbl?></a>
  <?php endforeach; ?>
</div>

<!-- ═══════════ TAB: ALL ═══════════ -->
<?php if($active_tab==='all'): ?>

<!-- Filter bar -->
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">

  <!-- Search -->
  <div style="flex:1;min-width:200px;position:relative">
    <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted)">🔍</span>
    <form method="GET">
      <input type="hidden" name="tab" value="all"/>
      <?php if($cat_f): ?><input type="hidden" name="cat" value="<?=htmlspecialchars($cat_f)?>"/><?php endif; ?>
      <?php if($avail_f): ?><input type="hidden" name="avail" value="<?=htmlspecialchars($avail_f)?>"/><?php endif; ?>
      <?php if($sort_f&&$sort_f!=='priority'): ?><input type="hidden" name="sort" value="<?=htmlspecialchars($sort_f)?>"/><?php endif; ?>
      <input name="sq" value="<?=htmlspecialchars($search_f)?>" placeholder="Search name, description, category..." style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px 8px 34px;font-family:'DM Sans',sans-serif;font-size:13px;outline:none;background:var(--bg)" oninput="this.form.submit()"/>
    </form>
  </div>

  <div style="width:1px;height:28px;background:var(--border)"></div>

  <!-- Category dropdown -->
  <div style="position:relative">
    <select id="cat-sel" onchange="location.href=this.value" style="border:1px solid var(--border);border-radius:8px;padding:8px 30px 8px 12px;font-family:'DM Sans',sans-serif;font-size:13px;background:var(--bg);cursor:pointer;appearance:none;outline:none;color:var(--text2)">
      <option value="<?=fqs(['cat'=>'','pg'=>1])?>" <?=!$cat_f?'selected':''?>>📁 All Categories</option>
      <?php foreach($categories as $c): ?>
      <option value="<?=fqs(['cat'=>$c['category'],'pg'=>1])?>" <?=$cat_f===$c['category']?'selected':''?>><?=htmlspecialchars($c['category'])?></option>
      <?php endforeach; ?>
    </select>
    <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muted)">▾</span>
  </div>

  <!-- Status dropdown -->
  <div style="position:relative">
    <select id="avail-sel" onchange="location.href=this.value" style="border:1px solid var(--border);border-radius:8px;padding:8px 30px 8px 12px;font-family:'DM Sans',sans-serif;font-size:13px;background:var(--bg);cursor:pointer;appearance:none;outline:none;color:var(--text2)">
      <option value="<?=fqs(['avail'=>'','pg'=>1])?>" <?=!$avail_f?'selected':''?>>🏷️ All Status</option>
      <option value="<?=fqs(['avail'=>'avail','pg'=>1])?>" <?=$avail_f==='avail'?'selected':''?>>✅ Available</option>
      <option value="<?=fqs(['avail'=>'low','pg'=>1])?>" <?=$avail_f==='low'?'selected':''?>>⚠️ Low Qty</option>
      <option value="<?=fqs(['avail'=>'none','pg'=>1])?>" <?=$avail_f==='none'?'selected':''?>>❌ Not Available</option>
    </select>
    <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muted)">▾</span>
  </div>

  <!-- Sort -->
  <div style="position:relative">
    <select onchange="location.href=this.value" style="border:1px solid var(--border);border-radius:8px;padding:8px 30px 8px 12px;font-family:'DM Sans',sans-serif;font-size:13px;background:var(--bg);cursor:pointer;appearance:none;outline:none;color:var(--text2)">
      <option value="<?=fqs(['sort'=>'priority','pg'=>1])?>" <?=$sort_f==='priority'?'selected':''?>>↕️ Priority Sort</option>
      <option value="<?=fqs(['sort'=>'price_asc','pg'=>1])?>" <?=$sort_f==='price_asc'?'selected':''?>>💰 Price ↑</option>
      <option value="<?=fqs(['sort'=>'price_desc','pg'=>1])?>" <?=$sort_f==='price_desc'?'selected':''?>>💰 Price ↓</option>
      <option value="<?=fqs(['sort'=>'stock_asc','pg'=>1])?>" <?=$sort_f==='stock_asc'?'selected':''?>>📦 Stock ↑</option>
      <option value="<?=fqs(['sort'=>'stock_desc','pg'=>1])?>" <?=$sort_f==='stock_desc'?'selected':''?>>📦 Stock ↓</option>
    </select>
    <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muted)">▾</span>
  </div>

  <?php if($cat_f||$avail_f||$search_f||$sort_f!=='priority'): ?>
  <a href="admin_equipment.php?tab=all" class="btn btn-outline btn-sm" style="white-space:nowrap">✕ Clear</a>
  <?php endif; ?>
</div>

<div class="table-card">
  <div class="table-header">
    <span class="table-title">Equipment List</span>
    <span style="font-size:12px;color:var(--muted)"><?=$total_count?> total · showing <?=count($equipment)?></span>
  </div>
  <table>
    <thead><tr><th>Photo</th><th>Name & Description</th><th>Category</th><th>Price/Day</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($equipment as $eq):
        $qty = intval($eq['stock']);
        if ($qty===0)    { $rb='#FFF2F2';$rl='3px solid #FFAAAA';$sl='Not Available';$sc='s-cancelled'; }
        elseif($qty<=5)  { $rb='#FFFBEA';$rl='3px solid #F6E05E';$sl='Low Qty';$sc='s-pending'; }
        else             { $rb='transparent';$rl='3px solid transparent';$sl='Available';$sc='s-active'; }
      ?>
      <tr style="background:<?=$rb?>;border-left:<?=$rl?>">
        <td>
          <?php if(!empty($eq['image'])): ?>
            <img src="<?=htmlspecialchars($eq['image'])?>" style="width:46px;height:46px;object-fit:cover;border-radius:8px;border:1px solid var(--border);cursor:pointer"
                 onclick="window.open('<?=htmlspecialchars($eq['image'])?>','_blank')"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
            <div style="display:none;width:46px;height:46px;background:var(--bg);border-radius:8px;border:1px solid var(--border);align-items:center;justify-content:center;font-size:22px">📷</div>
          <?php else: ?>
            <div style="width:46px;height:46px;background:var(--bg);border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px">📷</div>
          <?php endif; ?>
        </td>
        <td>
          <div style="font-weight:600"><?=htmlspecialchars($eq['name'])?></div>
          <?php if(!empty($eq['description'])): ?><div style="font-size:11px;color:var(--muted);margin-top:2px"><?=htmlspecialchars(mb_substr($eq['description'],0,50))?><?=mb_strlen($eq['description'])>50?'…':''?></div><?php endif; ?>
        </td>
        <td><?=htmlspecialchars($eq['category'])?></td>
        <td style="font-weight:600">₱<?=number_format($eq['price_per_day'],2)?></td>
        <td><span style="font-size:16px;font-weight:800;color:<?=$qty===0?'var(--red)':($qty<=5?'#B7791F':'var(--green)')?>"> <?=$qty?></span></td>
        <td><span class="status-badge <?=$sc?>"><?=$sl?></span></td>
        <td>
          <div class="btn-group">
            <button class="btn btn-sm" style="background:#2E8B57;color:#fff;border:none" onclick='editEq(<?=json_encode($eq)?>)'>✏️ Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Archive \'<?=addslashes($eq['name'])?>\'?')">
              <input type="hidden" name="act" value="archive"/><input type="hidden" name="id" value="<?=$eq['id']?>"/>
              <button class="btn btn-sm" style="background:#C9A227;color:#fff;border:none">📦 Archive</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($equipment)): ?><tr class="empty-row"><td colspan="7">No equipment matches filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- PAGINATION -->
<?php if($total_pages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;flex-wrap:wrap">
  <?php if($pg>1): ?><a href="<?=fqs(['pg'=>$pg-1])?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
  <?php for($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?>
    <a href="<?=fqs(['pg'=>$i])?>" class="btn btn-sm <?=$i===$pg?'btn-gold':'btn-outline'?>"><?=$i?></a>
  <?php endfor; ?>
  <?php if($pg<$total_pages): ?><a href="<?=fqs(['pg'=>$pg+1])?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
  <span style="font-size:12px;color:var(--muted);margin-left:8px">Page <?=$pg?> of <?=$total_pages?></span>
</div>
<?php endif; ?>

<!-- Legend -->
<div style="display:flex;gap:18px;font-size:12px;color:var(--muted);padding:8px 2px;flex-wrap:wrap;margin-top:8px">
  <span><span style="display:inline-block;width:11px;height:11px;background:#FFF2F2;border:1px solid #FFAAAA;border-radius:2px;margin-right:4px;vertical-align:middle"></span>0 stock → Not Available (floats to top)</span>
  <span><span style="display:inline-block;width:11px;height:11px;background:#FFFBEA;border:1px solid #F6E05E;border-radius:2px;margin-right:4px;vertical-align:middle"></span>1–5 stock → Low Qty (floats above available)</span>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal" style="max-width:560px">
    <div class="modal-header"><h3 class="modal-title">✏️ Edit Equipment</h3><button class="modal-close" onclick="closeModal('modal-edit')">×</button></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="act" value="edit"/><input type="hidden" name="id" id="edit-id"/>
      <div class="form-group">
        <label class="form-label">Photo</label>
        <div style="border:2px dashed var(--border);border-radius:10px;padding:12px;text-align:center;cursor:pointer;background:var(--bg)" onclick="document.getElementById('edit-img-f').click()">
          <img id="edit-img-p" style="max-height:80px;border-radius:8px;display:none"/>
          <div id="edit-img-ph" style="font-size:12px;color:var(--muted)">Click to change photo</div>
        </div>
        <input type="file" id="edit-img-f" name="eq_image" accept="image/*" style="display:none" onchange="previewImg(this,'edit-img-p','edit-img-ph')"/>
      </div>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" id="e-name" required/></div>
        <div class="form-group"><label class="form-label">Category</label>
          <select class="form-control" name="category" id="e-cat">
            <option>Cycling</option><option>Racket Sports</option><option>Water Sports</option><option>Combat Sports</option><option>Team Sports</option><option>Outdoor</option>
          </select></div>
        <div class="form-group"><label class="form-label">Price/Day (₱)</label><input class="form-control" name="price_per_day" id="e-price" type="number" step="0.01" required/></div>
        <div class="form-group"><label class="form-label">Quantity / Stock</label><input class="form-control" name="stock" id="e-stock" type="number" min="0" required/></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" id="e-desc" rows="2"></textarea></div>
      <p style="font-size:11px;color:var(--muted);margin-bottom:12px">ℹ️ Status auto-set from stock (0 = Not Available).</p>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Cancel</button><button type="submit" class="btn btn-gold">Save</button></div>
    </form>
  </div>
</div>

<?php endif; // tab all ?>


<!-- ═══════════ TAB: ADD ═══════════ -->
<?php if($active_tab==='add'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">
  <div class="table-card" style="padding:28px">
    <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:800;margin-bottom:4px">➕ Add New Equipment</div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:22px">Status is set automatically from quantity entered.</div>
    <form method="POST" enctype="multipart/form-data" id="add-form">
      <input type="hidden" name="act" value="add"/>

      <div class="form-group">
        <label class="form-label">Equipment Name *</label>
        <input class="form-control" name="name" placeholder="e.g. Mountain Bike" required oninput="updatePreview()"/>
      </div>
      <div class="form-group">
        <label class="form-label">Category *</label>
        <select class="form-control" name="category" id="add-cat" onchange="updatePreview()">
          <option>Cycling</option><option>Racket Sports</option><option>Water Sports</option><option>Combat Sports</option><option>Team Sports</option><option>Outdoor</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Price per Day (₱) *</label>
        <input class="form-control" name="price_per_day" type="number" step="0.01" min="0" placeholder="350.00" required id="add-price" oninput="updatePreview()"/>
      </div>
      <div class="form-group">
        <label class="form-label">Quantity / Stock * <span style="font-weight:400;color:var(--muted);font-size:11px;text-transform:none">(0 = Not Available)</span></label>
        <input class="form-control" name="stock" type="number" min="0" placeholder="10" required id="add-qty" oninput="updatePreview();previewStatus(this.value)"/>
      </div>
      <div class="form-group">
        <label class="form-label">Description <span style="font-weight:400;color:var(--muted);text-transform:none">(shown to customers)</span></label>
        <textarea class="form-control" name="description" rows="3" placeholder="What's included, condition, features..." id="add-desc" oninput="updatePreview()"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Photo <span style="font-weight:400;color:var(--muted);text-transform:none">(optional)</span></label>
        <div id="add-dz" onclick="document.getElementById('add-img-f').click()"
          style="border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer;background:var(--bg);transition:border-color .2s"
          ondragover="this.style.borderColor='var(--gold)';event.preventDefault()"
          ondragleave="this.style.borderColor='var(--border)'"
          ondrop="dropImg(event)">
          <div id="add-dz-c"><p style="font-size:28px">🖼️</p><p style="font-size:12px;color:var(--muted);margin-top:4px">Click or drag photo · JPG/PNG/WEBP · max 5MB</p></div>
          <img id="add-img-p" src="" style="display:none;max-height:120px;border-radius:8px;margin-top:8px"/>
        </div>
        <input type="file" id="add-img-f" name="eq_image" accept="image/*" style="display:none" onchange="previewImg(this,'add-img-p','add-dz-c');updatePreview()"/>
      </div>

      <div id="status-preview" style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:11px 14px;margin-bottom:18px;font-size:13px;color:var(--muted)">
        🏷️ Enter quantity to see status preview
      </div>

      <button type="submit" class="btn btn-gold" style="width:100%;padding:13px;font-size:14px;font-weight:700">➕ Add Equipment</button>
    </form>
  </div>

  <!-- Live Preview -->
  <div>
    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--muted);margin-bottom:10px">👁️ Customer Preview</div>
    <div id="card-preview" style="background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06);max-width:280px">
      <div id="prev-img-box" style="background:var(--gold-bg);padding:32px 0;text-align:center;font-size:56px;border-bottom:1px solid #EDD8B0">🏅</div>
      <div style="padding:18px">
        <div id="prev-cat" style="font-size:12px;color:var(--muted);margin-bottom:6px">Category</div>
        <div id="prev-name" style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;margin-bottom:8px">Equipment Name</div>
        <div id="prev-desc" style="font-size:12px;color:var(--muted);margin-bottom:14px;line-height:1.5">Description will show here</div>
        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);padding-top:12px">
          <span id="prev-price" style="font-size:18px;font-weight:800;color:var(--gold)">₱—/day</span>
          <span id="prev-status" class="status-badge s-active">Available</span>
        </div>
      </div>
    </div>
    <div style="margin-top:16px;font-size:12px;color:var(--muted);line-height:1.6">
      ℹ️ This preview shows how the equipment will appear to customers when browsing.
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ═══════════ TAB: ARCHIVE ═══════════ -->
<?php if($active_tab==='archive'): ?>
<div style="background:#FFF8E8;border:1px solid #EDD8B0;border-radius:12px;padding:13px 18px;margin-bottom:18px;font-size:13px;color:#7A5C1E">
  📦 Archived equipment is hidden from customers. Restore to make it bookable again. Delete only removes it if there's no rental history.
</div>
<div class="table-card">
  <div class="table-header"><span class="table-title">Archived Equipment</span><span style="font-size:12px;color:var(--muted)"><?=count($archived)?> item<?=count($archived)!=1?'s':''?></span></div>
  <table>
    <thead><tr><th>Photo</th><th>Name</th><th>Category</th><th>Price/Day</th><th>Last Stock</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($archived as $eq): ?>
      <tr style="opacity:.8">
        <td><?php if(!empty($eq['image'])&&file_exists($eq['image'])): ?><img src="<?=htmlspecialchars($eq['image'])?>" style="width:42px;height:42px;object-fit:cover;border-radius:8px;filter:grayscale(.5);border:1px solid var(--border)"/><?php else: ?><div style="width:42px;height:42px;background:#eee;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;opacity:.5">🏅</div><?php endif; ?></td>
        <td style="font-weight:600;color:var(--muted)"><?=htmlspecialchars($eq['name'])?></td>
        <td style="color:var(--muted)"><?=htmlspecialchars($eq['category'])?></td>
        <td style="color:var(--muted)">₱<?=number_format($eq['price_per_day'],2)?></td>
        <td style="color:var(--muted)"><?=$eq['stock']?></td>
        <td>
          <div class="btn-group">
            <form method="POST" style="display:inline"><input type="hidden" name="act" value="restore"/><input type="hidden" name="id" value="<?=$eq['id']?>"/><button class="btn btn-green btn-sm">♻️ Restore</button></form>
            <form method="POST" style="display:inline" onsubmit="return confirmDel('<?=addslashes($eq['name'])?>')"><input type="hidden" name="act" value="delete_perm"/><input type="hidden" name="id" value="<?=$eq['id']?>"/><button class="btn btn-red btn-sm">🗑️ Delete</button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($archived)): ?><tr class="empty-row"><td colspan="6">No archived equipment.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>


<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }

function previewImg(input, prevId, hideId) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    const img = document.getElementById(prevId); if(img){img.src=e.target.result;img.style.display='block';}
    const ph  = document.getElementById(hideId);  if(ph) ph.style.display='none';
    updatePreview();
  };
  r.readAsDataURL(input.files[0]);
}

function dropImg(e) {
  e.preventDefault();
  document.getElementById('add-dz').style.borderColor='var(--border)';
  const f=e.dataTransfer.files[0]; if(!f)return;
  const dt=new DataTransfer(); dt.items.add(f);
  const inp=document.getElementById('add-img-f'); inp.files=dt.files;
  previewImg(inp,'add-img-p','add-dz-c');
}

function previewStatus(val) {
  const qty=parseInt(val); const el=document.getElementById('status-preview'); if(!el)return;
  if(isNaN(qty)||val===''){el.innerHTML='🏷️ Enter quantity to see status preview';el.style.background='var(--bg)';el.style.borderColor='var(--border)';return;}
  if(qty===0){el.innerHTML='🏷️ Status: <span class="status-badge s-cancelled" style="margin-left:6px">❌ Not Available</span> — customers cannot book this';el.style.background='#FFF2F2';el.style.borderColor='#FFAAAA';}
  else if(qty<=5){el.innerHTML='🏷️ Status: <span class="status-badge s-pending" style="margin-left:6px">⚠️ Low Qty ('+qty+')</span> — still bookable';el.style.background='#FFFBEA';el.style.borderColor='#F6E05E';}
  else{el.innerHTML='🏷️ Status: <span class="status-badge s-active" style="margin-left:6px">✅ Available ('+qty+')</span>';el.style.background='#F0FFF4';el.style.borderColor='#9AE6B4';}
}

function updatePreview() {
  const name  = document.querySelector('[name="name"]')?.value || 'Equipment Name';
  const cat   = document.getElementById('add-cat')?.value    || 'Category';
  const price = document.getElementById('add-price')?.value  || '—';
  const qty   = parseInt(document.getElementById('add-qty')?.value)||0;
  const desc  = document.getElementById('add-desc')?.value   || 'Description will show here';
  const imgEl = document.getElementById('add-img-p');

  document.getElementById('prev-name').textContent  = name;
  document.getElementById('prev-cat').textContent   = cat;
  document.getElementById('prev-price').textContent = price ? '₱'+parseFloat(price).toLocaleString()+'/day' : '₱—/day';
  document.getElementById('prev-desc').textContent  = desc.substring(0,80)+(desc.length>80?'…':'');

  const st = document.getElementById('prev-status');
  if(qty===0){st.className='status-badge s-cancelled';st.textContent='Not Available';}
  else if(qty<=5){st.className='status-badge s-pending';st.textContent='Low Qty';}
  else{st.className='status-badge s-active';st.textContent='Available';}

  const pb = document.getElementById('prev-img-box');
  if(imgEl&&imgEl.src&&imgEl.style.display!=='none'){
    pb.innerHTML='<img src="'+imgEl.src+'" style="width:100%;height:160px;object-fit:cover"/>';
  } else {
    pb.innerHTML='🏅';pb.style.fontSize='56px';pb.style.paddingTop='32px';pb.style.paddingBottom='32px';
  }
}

function editEq(eq) {
  document.getElementById('edit-id').value   = eq.id;
  document.getElementById('e-name').value    = eq.name;
  document.getElementById('e-price').value   = eq.price_per_day;
  document.getElementById('e-stock').value   = eq.stock;
  document.getElementById('e-desc').value    = eq.description || '';
  const cat=document.getElementById('e-cat'); for(let o of cat.options) if(o.value===eq.category) o.selected=true;
  const prev=document.getElementById('edit-img-p'),ph=document.getElementById('edit-img-ph');
  if(eq.image){prev.src=eq.image;prev.style.display='block';ph.style.display='none';}
  else{prev.style.display='none';ph.style.display='block';ph.textContent='Click to change photo';}
  openModal('modal-edit');
}

function confirmDel(name) {
  return confirm('⚠️ PERMANENT DELETE\n\nDelete "'+name+'"?\n\nThis cannot be undone. Equipment with rental history cannot be deleted.');
}
</script>

<?php include 'includes/admin_layout_end.php'; ?>
