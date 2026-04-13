<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'checkout';
$hid   = $_SESSION['handler_id'];
$today = date('Y-m-d');
$msg = ''; $err = '';

// ─── CONFIRM FINAL CHECKOUT ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='confirm_checkout') {
    $rid       = intval($_POST['rental_id']);
    $condition = in_array($_POST['condition'],['excellent','good','fair','poor'])?$_POST['condition']:'good';
    $notes     = $conn->real_escape_string($_POST['notes']??'');
    $payment   = in_array($_POST['payment_method'],['cash','gcash','maya','bank'])?$_POST['payment_method']:'cash';
    $now       = date('Y-m-d H:i:s');

    $rental = $conn->query("
        SELECT r.*, u.is_blocked FROM rentals r JOIN users u ON r.user_id=u.id
        WHERE r.id=$rid AND r.status='active' AND r.checkout_by IS NULL
    ")->fetch_assoc();

    if (!$rental)              { $err = "Rental not found or already checked out."; }
    elseif ($rental['is_blocked']) { $err = "⛔ Customer account is blocked. Cannot release equipment."; }
    else {
        $pm_safe = $conn->real_escape_string($payment);
        $conn->query("UPDATE rentals SET checkout_by=$hid, checkout_at='$now', payment_status='paid', payment_method='$pm_safe' WHERE id=$rid");
        // Deduct stock when physically released
        $conn->query("UPDATE equipment SET stock=GREATEST(0,stock-1) WHERE id={$rental['equipment_id']}");
        // Auto-set not available if stock hits 0
        $conn->query("UPDATE equipment SET is_active=CASE WHEN stock=0 THEN 0 ELSE is_active END WHERE id={$rental['equipment_id']}");
        $stmt = $conn->prepare("INSERT INTO condition_logs (rental_id,handler_id,type,condition_rating,notes) VALUES (?,?,'checkout',?,?)");
        $stmt->bind_param('iiss',$rid,$hid,$condition,$notes); $stmt->execute();
        header("Location: handler_checkout.php?step=receipt&rental_id=$rid"); exit();
    }
}

// Determine step: select → payment → receipt
$step       = $_GET['step']      ?? 'select';
$rental_id  = intval($_GET['rental_id'] ?? 0);
$selected   = null;
$done_rental= null;

if ($rental_id) {
    $selected = $conn->query("
        SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.id_type, u.is_blocked,
               u.id_verified, u.loyalty_pts, u.block_reason, u.id_status,
               e.name as eq_name, e.icon as eq_icon, e.category, e.price_per_day, e.description as eq_desc
        FROM rentals r JOIN users u ON r.user_id=u.id JOIN equipment e ON r.equipment_id=e.id
        WHERE r.id=$rental_id
    ")->fetch_assoc();
}

if ($step==='receipt' && $rental_id) {
    $done_rental = $conn->query("
        SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.id_type,
               e.name as eq_name, e.icon as eq_icon, h.name as handler_name
        FROM rentals r JOIN users u ON r.user_id=u.id
        JOIN equipment e ON r.equipment_id=e.id
        JOIN handlers h ON r.checkout_by=h.id
        WHERE r.id=$rental_id
    ")->fetch_assoc();
    if (!$done_rental) { header('Location: handler_checkout.php'); exit(); }
}

// Load pending list
$search = $conn->real_escape_string($_GET['q']??'');
$pg     = max(1,intval($_GET['pg']??1));
$per    = 10;
$where  = "r.status='active' AND r.checkout_by IS NULL AND r.start_date <= DATE_ADD('$today', INTERVAL 30 DAY)";
if ($search) $where .= " AND (r.order_code LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
$total_r= $conn->query("SELECT COUNT(*) FROM rentals r JOIN users u ON r.user_id=u.id WHERE $where")->fetch_row()[0];
$total_pg = max(1,ceil($total_r/$per));
$offset = ($pg-1)*$per;
$rentals = $conn->query("
    SELECT r.*, u.first_name, u.last_name, u.email, u.phone, u.is_blocked,
           e.name as eq_name, e.icon as eq_icon, e.category
    FROM rentals r JOIN users u ON r.user_id=u.id JOIN equipment e ON r.equipment_id=e.id
    WHERE $where ORDER BY u.is_blocked ASC, r.start_date ASC LIMIT $per OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$pm_icons = ['cash'=>'💵','gcash'=>'📱','maya'=>'💜','bank'=>'🏦'];
$pm_names = ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank'=>'Bank Transfer'];

include 'includes/handler_layout.php';
?>

<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endif; ?>

<!-- ═══ RECEIPT VIEW ═══ -->
<?php if($step==='receipt' && $done_rental): ?>
<div style="max-width:560px;margin:0 auto">
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
    <div style="background:linear-gradient(135deg,var(--teal),var(--teal-dk));padding:24px 28px;text-align:center">
      <div style="font-size:44px;margin-bottom:8px">✅</div>
      <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:#fff">Equipment Released!</div>
      <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:4px">Check-out completed</div>
    </div>
    <div style="padding:26px 28px">
      <div style="text-align:center;margin-bottom:20px">
        <div style="font-size:22px;font-weight:800;color:var(--teal);letter-spacing:.04em"><?=$done_rental['order_code']?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px"><?=date('F j, Y · g:i A',strtotime($done_rental['checkout_at']))?></div>
      </div>
      <div style="border:1px dashed var(--border);border-radius:12px;padding:18px;margin-bottom:18px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:13px">
          <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin-bottom:4px">Customer</div><div style="font-weight:700"><?=htmlspecialchars($done_rental['first_name'].' '.$done_rental['last_name'])?></div><div style="color:var(--muted);font-size:12px"><?=htmlspecialchars($done_rental['phone'])?></div></div>
          <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin-bottom:4px">Equipment</div><div style="font-weight:700"><?=$done_rental['eq_icon']?> <?=htmlspecialchars($done_rental['eq_name'])?></div><div style="color:var(--muted);font-size:12px"><?=$done_rental['days']?> day<?=$done_rental['days']>1?'s':''?></div></div>
          <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin-bottom:4px">Pick-up</div><div style="font-weight:600"><?=date('M j, Y',strtotime($done_rental['start_date']))?></div></div>
          <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin-bottom:4px">Return By</div><div style="font-weight:600;color:var(--red)"><?=date('M j, Y',strtotime($done_rental['end_date']))?></div></div>
        </div>
      </div>
      <div style="background:var(--teal-bg,#E6F7F8);border-radius:10px;padding:14px 18px;margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px"><span style="color:var(--muted)">Subtotal (<?=$done_rental['days']?> × ₱<?=number_format($done_rental['price_per_day'],0)?>)</span><span>₱<?=number_format($done_rental['price_per_day']*$done_rental['days'],0)?></span></div>
        <?php if($done_rental['discount_pct']>0): ?><div style="display:flex;justify-content:space-between;font-size:13px;color:var(--green);margin-bottom:8px"><span>Discount (<?=$done_rental['discount_pct']?>%)</span><span>−₱<?=number_format($done_rental['price_per_day']*$done_rental['days']*$done_rental['discount_pct']/100,0)?></span></div><?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-weight:800;font-size:16px;border-top:1px solid rgba(0,0,0,.08);padding-top:10px;margin-top:4px"><span>Total Paid</span><span style="color:var(--teal)">₱<?=number_format($done_rental['total_amount'],0)?></span></div>
        <div style="font-size:12px;color:var(--muted);margin-top:8px">
          <?=$pm_icons[$done_rental['payment_method']??'cash']??'💵'?> <?=$pm_names[$done_rental['payment_method']??'cash']??'Cash'?> · <span style="background:#D4EDDA;color:#2E8B57;border-radius:6px;padding:2px 8px;font-weight:600">PAID</span>
        </div>
      </div>
      <div style="background:var(--gold-bg);border:1px solid #EDD8B0;border-radius:10px;padding:12px 14px;font-size:12px;color:#7A5C1E;margin-bottom:18px">
        📅 <strong>Return Reminder:</strong> Equipment must be returned by <?=date('l, F j, Y',strtotime($done_rental['end_date']))?>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button onclick="printReceipt()" class="btn btn-teal" style="flex:1">🖨️ Print Receipt</button>
        <a href="handler_checkout.php" class="btn btn-outline" style="flex:1;text-align:center">← Back to Queue</a>
      </div>
    </div>
  </div>
</div>

<script>
function printReceipt() {
  const w = window.open('','_blank','width=480,height=700');
  const d = <?=json_encode($done_rental)?>;
  const pm_names={'cash':'Cash','gcash':'GCash','maya':'Maya','bank':'Bank Transfer'};
  const pm_icons={'cash':'💵','gcash':'📱','maya':'💜','bank':'🏦'};
  const pm = d.payment_method||'cash';
  const disc_amt = d.discount_pct>0 ? (d.price_per_day*d.days*d.discount_pct/100).toFixed(0) : 0;
  w.document.write(`<!DOCTYPE html><html><head><title>Receipt ${d.order_code}</title>
  <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'DM Sans',sans-serif;background:#fff;padding:28px;font-size:13px;color:#1C1916;max-width:420px;margin:0 auto}
  .logo{font-family:'Georgia',serif;font-size:22px;font-weight:800;text-align:center;margin-bottom:2px}.logo span{color:#C47F2B}
  .sub{text-align:center;font-size:11px;color:#888;margin-bottom:18px;letter-spacing:.06em}
  .divider{border:none;border-top:1px dashed #ccc;margin:14px 0}
  .row{display:flex;justify-content:space-between;margin:5px 0;font-size:12px}
  .row.big{font-size:15px;font-weight:800;margin-top:8px;border-top:1px solid #eee;padding-top:8px}
  .badge{background:#d4edda;color:#2E8B57;border-radius:5px;padding:2px 8px;font-weight:700;font-size:11px}
  .stamp{text-align:center;font-size:26px;font-weight:900;letter-spacing:.2em;color:var(--teal,#0E7C86);border:3px solid #0E7C86;border-radius:8px;padding:6px 14px;display:inline-block;margin:14px auto;transform:rotate(-4deg)}
  .center{text-align:center}.section{margin:14px 0;padding:12px;background:#f9f9f9;border-radius:8px}
  @media print{body{padding:0}button{display:none}}</style></head><body>
  <div class="logo">Kinetic<span>Borrow</span></div><div class="sub">RENTAL RECEIPT</div>
  <div style="text-align:center;font-size:14px;font-weight:700;color:#C47F2B;letter-spacing:.04em">${d.order_code}</div>
  <div style="text-align:center;font-size:11px;color:#888;margin-top:2px">${new Date(d.checkout_at).toLocaleDateString('en-PH',{month:'long',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
  <hr class="divider"/>
  <div class="section">
    <div class="row"><span style="color:#888">Customer</span><span style="font-weight:700">${d.first_name} ${d.last_name}</span></div>
    <div class="row"><span style="color:#888">Phone</span><span>${d.phone}</span></div>
    <div class="row"><span style="color:#888">ID Type</span><span style="text-transform:capitalize">${d.id_type||'—'}</span></div>
  </div>
  <div class="section">
    <div class="row"><span style="color:#888">Equipment</span><span style="font-weight:700">${d.eq_icon} ${d.eq_name}</span></div>
    <div class="row"><span style="color:#888">Duration</span><span>${d.days} day${d.days>1?'s':''}</span></div>
    <div class="row"><span style="color:#888">Pick-up</span><span>${d.start_date}</span></div>
    <div class="row"><span style="color:#888">Return By</span><span style="color:#C0392B;font-weight:600">${d.end_date}</span></div>
  </div>
  <div class="section">
    <div class="row"><span style="color:#888">Rate</span><span>₱${parseFloat(d.price_per_day).toLocaleString()} × ${d.days} days</span></div>
    ${d.discount_pct>0?`<div class="row"><span style="color:#2E8B57">Discount (${d.discount_pct}%)</span><span style="color:#2E8B57">−₱${parseInt(disc_amt).toLocaleString()}</span></div>`:''}
    <div class="row big"><span>TOTAL</span><span>₱${parseFloat(d.total_amount).toLocaleString()}</span></div>
    <div class="row" style="margin-top:8px"><span style="color:#888">Payment</span><span>${pm_icons[pm]} ${pm_names[pm]} &nbsp;<span class="badge">PAID</span></span></div>
  </div>
  <hr class="divider"/>
  <div class="center"><div class="stamp">✓ RELEASED</div></div>
  <div style="text-align:center;font-size:12px;color:#888;margin-top:12px">Handler: <?=htmlspecialchars($done_rental['handler_name']??'')?></div>
  <hr class="divider"/>
  <div style="text-align:center;font-size:11px;color:#888;margin-top:8px">Please return equipment by <strong style="color:#C0392B">${d.end_date}</strong><br/>Thank you for using KineticBorrow!</div>
  <br/><button onclick="window.print()" style="width:100%;padding:10px;background:#0E7C86;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">🖨️ Print / Save PDF</button>
  </body></html>`);
  w.document.close();
}
</script>

<?php
include 'includes/handler_layout_end.php';
exit();
endif; // receipt
?>

<!-- ═══ CHECKOUT LIST + DETAIL ═══ -->
<div class="page-head">
  <div><div class="page-head-title">Check-Out Queue</div><div class="page-head-sub">Select a booking, collect payment, then release equipment</div></div>
</div>

<!-- Steps indicator -->
<div style="display:flex;align-items:center;gap:0;margin-bottom:20px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 20px">
  <?php $steps=[1=>'Select Booking',2=>'Payment Method',3=>'Receipt & Release']; $cur_step=$step==='payment'?2:1; ?>
  <?php foreach($steps as $n=>$lbl): ?>
  <div style="display:flex;align-items:center;gap:8px;flex:1">
    <div style="width:28px;height:28px;border-radius:50%;background:<?=$n<=$cur_step?'var(--teal)':'var(--border)'?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:<?=$n<=$cur_step?'#fff':'var(--muted)'?>;flex-shrink:0"><?=$n?></div>
    <span style="font-size:13px;font-weight:<?=$n===$cur_step?'700':'400'?>;color:<?=$n===$cur_step?'var(--teal)':'var(--muted)'?>"><?=$lbl?></span>
    <?php if($n<3): ?><div style="flex:1;height:1px;background:<?=$n<$cur_step?'var(--teal)':'var(--border)'?>;margin:0 8px"></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php if($step==='payment' && $selected): ?>
<!-- ═══ STEP 2: PAYMENT ═══ -->
<div style="max-width:640px">
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)">

    <!-- Booking summary banner -->
    <div style="background:var(--teal-bg,#E6F7F8);padding:16px 22px;border-bottom:1px solid #B2DFE3;display:flex;align-items:center;gap:14px">
      <div style="font-size:36px"><?=$selected['eq_icon']?></div>
      <div>
        <div style="font-weight:700;font-size:15px"><?=htmlspecialchars($selected['eq_name'])?></div>
        <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($selected['first_name'].' '.$selected['last_name'])?> · <?=$selected['days']?> day<?=$selected['days']>1?'s':''?> · <?=date('M j',strtotime($selected['start_date']))?> → <?=date('M j, Y',strtotime($selected['end_date']))?></div>
        <div style="font-size:13px;color:var(--teal);font-weight:700;margin-top:2px">Total: ₱<?=number_format($selected['total_amount'],0)?></div>
      </div>
    </div>

    <form method="POST" action="handler_checkout.php" style="padding:22px 24px">
      <input type="hidden" name="act" value="confirm_checkout"/>
      <input type="hidden" name="rental_id" value="<?=$rental_id?>"/>

      <!-- Payment method -->
      <div class="form-group">
        <label class="form-label">Payment Method *</label>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:6px">
          <?php foreach($pm_icons as $k=>$icon): ?>
          <label style="border:2px solid var(--border);border-radius:10px;padding:14px 16px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:all .18s" id="pm-lbl-<?=$k?>">
            <input type="radio" name="payment_method" value="<?=$k?>" style="display:none" <?=$k==='cash'?'checked':''?> onchange="selectPM('<?=$k?>')"/>
            <span style="font-size:22px"><?=$icon?></span>
            <span style="font-size:14px;font-weight:600"><?=$pm_names[$k]?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Condition -->
      <div class="form-group">
        <label class="form-label">Equipment Condition at Release *</label>
        <select class="form-control" name="condition">
          <option value="excellent">Excellent — like new</option>
          <option value="good" selected>Good — minor wear</option>
          <option value="fair">Fair — visible wear</option>
          <option value="poor">Poor — damaged</option>
        </select>
      </div>

      <!-- Notes -->
      <div class="form-group">
        <label class="form-label">Handler Notes <span style="font-weight:400;color:var(--muted);text-transform:none">(optional)</span></label>
        <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Helmet with minor scratches, paddle grip replaced..."></textarea>
      </div>

      <!-- Amount reminder -->
      <div style="background:var(--teal-bg,#E6F7F8);border:1px solid #B2DFE3;border-radius:10px;padding:14px 18px;margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span style="color:var(--muted)">Rate</span><span>₱<?=number_format($selected['price_per_day'],0)?>/day × <?=$selected['days']?> days</span></div>
        <?php if($selected['discount_pct']>0): ?><div style="display:flex;justify-content:space-between;font-size:13px;color:var(--green);margin-bottom:6px"><span>Discount (<?=$selected['discount_pct']?>%)</span><span>−₱<?=number_format($selected['price_per_day']*$selected['days']*$selected['discount_pct']/100,0)?></span></div><?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-weight:800;font-size:16px;border-top:1px solid rgba(0,0,0,.08);padding-top:10px"><span>Collect Amount</span><span style="color:var(--teal)">₱<?=number_format($selected['total_amount'],0)?></span></div>
      </div>

      <?php if($selected['is_blocked']): ?>
      <div class="alert alert-error" style="margin-bottom:14px">⛔ This customer's account is blocked. Cannot proceed with check-out.</div>
      <?php endif; ?>

      <div style="display:flex;gap:10px">
        <a href="handler_checkout.php" class="btn btn-outline" style="flex:1;text-align:center">← Back</a>
        <button type="submit" class="btn btn-teal" style="flex:2" <?=$selected['is_blocked']?'disabled':''?>>✅ Confirm Release & Check-Out</button>
      </div>
    </form>
  </div>
</div>

<script>
function selectPM(key) {
  document.querySelectorAll('[id^="pm-lbl-"]').forEach(el => {
    el.style.borderColor = 'var(--border)'; el.style.background = 'transparent';
  });
  const el = document.getElementById('pm-lbl-'+key);
  if (el) { el.style.borderColor = 'var(--teal,#0E7C86)'; el.style.background = 'var(--teal-bg,#E6F7F8)'; }
}
selectPM('cash');
</script>

<?php else: ?>
<!-- ═══ STEP 1: SELECT ═══ -->
<div style="display:grid;grid-template-columns:<?=$selected?'1fr 380px':'1fr'?>;gap:20px;align-items:start">

  <!-- LIST -->
  <div>
    <form method="GET" style="display:flex;gap:8px;margin-bottom:14px">
      <input type="hidden" name="step" value="select"/>
      <div style="flex:1;position:relative"><span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)">🔍</span><input name="q" value="<?=htmlspecialchars($search)?>" placeholder="Search order, customer name..." style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px 8px 34px;font-family:'DM Sans',sans-serif;font-size:13px;outline:none;background:var(--bg)"/></div>
      <button type="submit" class="btn btn-teal btn-sm">Search</button>
    </form>

    <div class="table-card">
      <div class="table-header"><span class="table-title">⏳ Pending Pick-Ups</span><span style="font-size:12px;color:var(--muted)"><?=$total_r?> booking<?=$total_r!=1?'s':''?></span></div>
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Equipment</th><th>Pick-up</th><th>Total</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($rentals as $r): ?>
          <tr style="<?=$r['is_blocked']?'background:#FFF2F2':($rental_id==$r['id']?'background:var(--teal-bg,#E6F7F8)':'')?>">
            <td style="color:var(--teal);font-weight:700"><?=$r['order_code']?></td>
            <td>
              <div style="font-weight:600"><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></div>
              <?php if($r['is_blocked']): ?><span style="font-size:10px;background:#FEE;color:var(--red);border-radius:4px;padding:1px 6px">⛔ Blocked</span><?php endif; ?>
            </td>
            <td><?=$r['eq_icon']?> <?=htmlspecialchars($r['eq_name'])?></td>
            <td style="font-size:12px"><?=date('M j, Y',strtotime($r['start_date']))?><?=strtotime($r['start_date'])<strtotime($today)?' <span style="color:var(--red);font-size:10px">OVERDUE</span>':''?></td>
            <td style="font-weight:600">₱<?=number_format($r['total_amount'],0)?></td>
            <td>
              <?php if(!$r['is_blocked']): ?>
              <a href="handler_checkout.php?step=select&rental_id=<?=$r['id']?>" class="btn btn-sm btn-teal">Select →</a>
              <?php else: ?>
              <span style="font-size:11px;color:var(--muted)">Blocked</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rentals)): ?><tr class="empty-row"><td colspan="6">No pending pick-ups.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($total_pg>1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:12px">
      <?php if($pg>1): ?><a href="?step=select&q=<?=urlencode($search)?>&pg=<?=$pg-1?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
      <?php for($i=max(1,$pg-2);$i<=min($total_pg,$pg+2);$i++): ?><a href="?step=select&q=<?=urlencode($search)?>&pg=<?=$i?>" class="btn btn-sm <?=$i===$pg?'btn-teal':'btn-outline'?>"><?=$i?></a><?php endfor; ?>
      <?php if($pg<$total_pg): ?><a href="?step=select&q=<?=urlencode($search)?>&pg=<?=$pg+1?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- DETAIL PANEL -->
  <?php if($selected): ?>
  <div style="background:#fff;border:1.5px solid var(--teal,#0E7C86);border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(14,124,134,.1)">
    <div style="background:linear-gradient(135deg,var(--teal),var(--teal-dk));padding:16px 20px">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.7);margin-bottom:4px">Selected Booking</div>
      <div style="font-size:18px;font-weight:800;color:#fff;letter-spacing:.04em"><?=$selected['order_code']?></div>
    </div>
    <div style="padding:18px 20px">
      <!-- Customer -->
      <div style="margin-bottom:14px">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--muted);margin-bottom:6px">Customer</div>
        <div style="font-weight:700;font-size:14px"><?=htmlspecialchars($selected['first_name'].' '.$selected['last_name'])?></div>
        <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($selected['email'])?></div>
        <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($selected['phone'])?></div>
        <?php $badges=['student'=>'🎓 Student','senior'=>'👴 Senior','pwd'=>'♿ PWD','regular'=>'🪪 Regular']; ?>
        <span class="status-badge <?=$selected['id_status']==='approved'?'s-active':'s-pending'?>" style="margin-top:5px;display:inline-block"><?=$badges[$selected['id_type']]??'🪪 Regular'?> · ID <?=ucfirst($selected['id_status']??'pending')?></span>
      </div>
      <!-- Equipment -->
      <div style="border-top:1px solid var(--border);padding-top:14px;margin-bottom:14px">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--muted);margin-bottom:6px">Equipment</div>
        <div style="font-size:22px;margin-bottom:4px"><?=$selected['eq_icon']?></div>
        <div style="font-weight:700"><?=htmlspecialchars($selected['eq_name'])?></div>
        <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($selected['category'])?></div>
        <?php if(!empty($selected['eq_desc'])): ?><div style="font-size:11px;color:var(--muted);margin-top:4px"><?=htmlspecialchars(substr($selected['eq_desc'],0,80))?></div><?php endif; ?>
      </div>
      <!-- Dates & Amount -->
      <div style="border-top:1px solid var(--border);padding-top:14px;margin-bottom:18px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;margin-bottom:10px">
          <div><div style="color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Pick-up</div><div style="font-weight:600"><?=date('M j, Y',strtotime($selected['start_date']))?></div></div>
          <div><div style="color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Return</div><div style="font-weight:600;color:var(--red)"><?=date('M j, Y',strtotime($selected['end_date']))?></div></div>
          <div><div style="color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Duration</div><div style="font-weight:600"><?=$selected['days']?> day<?=$selected['days']>1?'s':''?></div></div>
          <div><div style="color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Points Earned</div><div style="font-weight:600;color:var(--gold)">+<?=intval($selected['total_amount']/10)?> pts</div></div>
        </div>
        <div style="background:var(--teal-bg,#E6F7F8);border-radius:10px;padding:12px;font-size:13px">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="color:var(--muted)">Rate</span><span>₱<?=number_format($selected['price_per_day'],0)?>/day</span></div>
          <?php if($selected['discount_pct']>0): ?><div style="display:flex;justify-content:space-between;margin-bottom:4px;color:var(--green)"><span>Discount (<?=$selected['discount_pct']?>%)</span><span>−₱<?=number_format($selected['price_per_day']*$selected['days']*$selected['discount_pct']/100,0)?></span></div><?php endif; ?>
          <div style="display:flex;justify-content:space-between;font-weight:800;font-size:15px;border-top:1px solid rgba(0,0,0,.08);padding-top:8px;margin-top:4px"><span>Total</span><span style="color:var(--teal)">₱<?=number_format($selected['total_amount'],0)?></span></div>
        </div>
      </div>

      <a href="handler_checkout.php?step=payment&rental_id=<?=$rental_id?>" class="btn btn-teal" style="display:block;text-align:center;text-decoration:none">Proceed to Payment →</a>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; // step select ?>

<?php include 'includes/handler_layout_end.php'; ?>
