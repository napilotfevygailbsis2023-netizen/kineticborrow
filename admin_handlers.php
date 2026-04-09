<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'handlers';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $name  = trim($conn->real_escape_string($_POST['name']));
        $email = trim($conn->real_escape_string($_POST['email']));
        $pass  = $_POST['password'];
        $pass2 = $_POST['password2'];

        if (strlen($name) < 2) { $err = "Name is too short."; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = "Invalid email address."; }
        elseif (strlen($pass) < 6) { $err = "Password must be at least 6 characters."; }
        elseif ($pass !== $pass2) { $err = "Passwords do not match."; }
        else {
            $exists = $conn->query("SELECT id FROM handlers WHERE email='$email'")->fetch_row();
            if ($exists) { $err = "Email already registered."; }
            else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $conn->query("INSERT INTO handlers (name, email, password) VALUES ('$name', '$email', '$hash')");
                $msg = "Handler account created for $name.";
            }
        }
    }

    if ($act === 'delete') {
        $hid = intval($_POST['handler_id']);
        // Check if handler has any rentals
        $has_rentals = $conn->query("SELECT COUNT(*) FROM rentals WHERE checkout_by=$hid OR checkin_by=$hid")->fetch_row()[0];
        if ($has_rentals) {
            $err = "Cannot delete — this handler has rental activity records.";
        } else {
            $conn->query("DELETE FROM handlers WHERE id=$hid");
            $msg = "Handler account removed.";
        }
    }

    if ($act === 'reset_password') {
        $hid  = intval($_POST['handler_id']);
        $pass = $_POST['new_password'];
        if (strlen($pass) < 6) { $err = "Password must be at least 6 characters."; }
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $conn->query("UPDATE handlers SET password='$hash' WHERE id=$hid");
            $msg = "Password updated successfully.";
        }
    }
}

$handlers = $conn->query("
    SELECT h.*,
           COUNT(DISTINCT CASE WHEN r.checkout_by=h.id THEN r.id END) as total_checkouts,
           COUNT(DISTINCT CASE WHEN r.checkin_by=h.id THEN r.id END) as total_checkins,
           COUNT(DISTINCT ir.id) as total_incidents
    FROM handlers h
    LEFT JOIN rentals r ON r.checkout_by=h.id OR r.checkin_by=h.id
    LEFT JOIN incident_reports ir ON ir.handler_id=h.id
    GROUP BY h.id
    ORDER BY h.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include 'includes/admin_layout.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);">Handler Accounts</div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;">Register and manage equipment handler accounts</div>
  </div>
</div>


<?php if($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err):  ?><div class="alert alert-error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px"><button class="btn btn-gold" onclick="openModal('modal-add')">+ Register Handler</button></div>

<div class="table-card">
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Check-Outs</th><th>Check-Ins</th><th>Incidents</th><th>Registered</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($handlers as $h): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#0E7C86,#095E66);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0"><?= strtoupper(substr($h['name'],0,1)) ?></div>
            <div>
              <div style="font-weight:600"><?= htmlspecialchars($h['name']) ?></div>
              <span class="status-badge s-active" style="font-size:10px">🔧 Handler</span>
            </div>
          </div>
        </td>
        <td><?= htmlspecialchars($h['email']) ?></td>
        <td style="font-weight:600;color:var(--green)"><?= $h['total_checkouts'] ?></td>
        <td style="font-weight:600;color:var(--teal, #0E7C86)"><?= $h['total_checkins'] ?></td>
        <td style="font-weight:600;color:<?= $h['total_incidents']>0?'var(--red)':'var(--muted)' ?>"><?= $h['total_incidents'] ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($h['created_at'])) ?></td>
        <td>
          <div class="btn-group">
            <button class="btn btn-outline btn-sm" onclick="resetPw(<?= $h['id'] ?>,'<?= htmlspecialchars(addslashes($h['name'])) ?>')">🔑 Reset Password</button>
            <?php if($h['total_checkouts'] == 0 && $h['total_checkins'] == 0): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars($h['name']) ?>?')">
              <input type="hidden" name="act" value="delete"/>
              <input type="hidden" name="handler_id" value="<?= $h['id'] ?>"/>
              <button type="submit" class="btn btn-red btn-sm">🗑️ Remove</button>
            </form>
            <?php else: ?>
              <span style="font-size:11px;color:var(--muted)">Has activity</span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($handlers)): ?><tr class="empty-row"><td colspan="7">No handler accounts yet. Register one above.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ADD HANDLER MODAL -->
<div class="modal-overlay" id="modal-add">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><h3 class="modal-title">🔧 Register Handler</h3><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="add"/>
      <div class="form-group"><label class="form-label">Full Name *</label><input class="form-control" name="name" placeholder="Juan Dela Cruz" required/></div>
      <div class="form-group"><label class="form-label">Email Address *</label><input class="form-control" name="email" type="email" placeholder="handler@email.com" required/></div>
      <div class="form-group"><label class="form-label">Password *</label><input class="form-control" name="password" type="password" placeholder="Min. 6 characters" required/></div>
      <div class="form-group"><label class="form-label">Confirm Password *</label><input class="form-control" name="password2" type="password" placeholder="Repeat password" required/></div>
      <div style="background:var(--gold-bg);border:1px solid #EDD8B0;border-radius:10px;padding:11px 14px;font-size:12px;color:#7A5C1E;margin-bottom:16px">
        🔒 The handler will use these credentials to log in at <strong>handler_login.php</strong>. Share the password securely.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-gold">Create Account</button>
      </div>
    </form>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="modal-reset">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><h3 class="modal-title">🔑 Reset Password</h3><button class="modal-close" onclick="closeModal('modal-reset')">×</button></div>
    <form method="POST">
      <input type="hidden" name="act" value="reset_password"/>
      <input type="hidden" name="handler_id" id="reset-hid"/>
      <div class="modal-info">Resetting password for <strong id="reset-name"></strong></div>
      <div class="form-group"><label class="form-label">New Password *</label><input class="form-control" name="new_password" type="password" placeholder="Min. 6 characters" required/></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-reset')">Cancel</button>
        <button type="submit" class="btn btn-gold">Update Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
function resetPw(hid, name) {
  document.getElementById('reset-hid').value = hid;
  document.getElementById('reset-name').textContent = name;
  openModal('modal-reset');
}
</script>
<?php include 'includes/admin_layout_end.php'; ?>
