<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'profile';
$admin = getAdmin();
$aid = $_SESSION['admin_id'];

$total_approvals = $conn->query("SELECT COUNT(*) FROM id_verifications WHERE reviewed_by=$aid AND status='approved'")->fetch_row()[0];
$total_rejections = $conn->query("SELECT COUNT(*) FROM id_verifications WHERE reviewed_by=$aid AND status='rejected'")->fetch_row()[0];
$total_rentals_managed = $conn->query("SELECT COUNT(*) FROM rentals WHERE admin_notes IS NOT NULL AND admin_notes != ''")->fetch_row()[0];

$role_labels = ['superadmin'=>'Super Administrator','admin'=>'Administrator','staff'=>'Staff'];
$role_label = $role_labels[$admin['role']] ?? ucfirst($admin['role']);

include 'includes/admin_layout.php';
?>

<div style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--text);">My Profile</div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;">Your admin account information</div>
  </div>
</div>




<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px 24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.05)">
    <div style="width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#fff;margin:0 auto 14px;border:4px solid var(--gold-bg)">
      <?= strtoupper(substr($admin['name'],0,1)) ?>
    </div>
    <div style="font-family:'Playfair Display',serif;font-size:19px;font-weight:800;color:var(--text);margin-bottom:4px"><?= htmlspecialchars($admin['name']) ?></div>
    <span class="status-badge badge-gold" style="font-size:11px"><?= $role_label ?></span>

    <div style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px;text-align:left;font-size:13px;line-height:2.2;color:var(--text2)">
      <div>📧 <strong>Email:</strong> <?= htmlspecialchars($admin['email']) ?></div>
      <div>🆔 <strong>Admin ID:</strong> #<?= str_pad($aid,4,'0',STR_PAD_LEFT) ?></div>
      <div>📅 <strong>Since:</strong> <?= date('F j, Y', strtotime($admin['created_at'])) ?></div>
    </div>

    <div style="margin-top:14px;background:var(--gold-bg);border-radius:10px;padding:10px 13px;font-size:12px;color:var(--gold);text-align:left">
      🔒 Profile details are managed at the system level. Contact your system administrator to update credentials.
    </div>
  </div>

  <div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px">
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">✅</span><span class="stat-badge badge-green">Approved</span></div>
        <div class="stat-val"><?= $total_approvals ?></div>
        <div class="stat-lbl">IDs Approved</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">❌</span><span class="stat-badge badge-red">Rejected</span></div>
        <div class="stat-val"><?= $total_rejections ?></div>
        <div class="stat-lbl">IDs Rejected</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><span class="stat-icon">📦</span><span class="stat-badge badge-gold">Managed</span></div>
        <div class="stat-val"><?= $total_rentals_managed ?></div>
        <div class="stat-lbl">Rentals Noted</div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header"><span class="table-title">🕐 Recent ID Review Activity</span></div>
      <table>
        <thead><tr><th>Customer</th><th>ID Type</th><th>Decision</th><th>Notes</th><th>Date</th></tr></thead>
        <tbody>
          <?php
          $recent = $conn->query("
            SELECT v.*, u.first_name, u.last_name FROM id_verifications v
            JOIN users u ON v.user_id=u.id
            WHERE v.reviewed_by=$aid
            ORDER BY v.updated_at DESC LIMIT 10
          ")->fetch_all(MYSQLI_ASSOC);
          foreach($recent as $r): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?php $icons=['student'=>'🎓','senior'=>'👴','pwd'=>'♿','regular'=>'🪪']; echo ($icons[$r['id_type']]??'🪪').' '.ucfirst($r['id_type']); ?></td>
            <td><span class="status-badge <?= $r['status']==='approved'?'s-active':($r['status']==='rejected'?'s-cancelled':'s-pending') ?>"><?= ucfirst($r['status']) ?></span></td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars(substr($r['notes']??'—',0,40)) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($r['updated_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recent)): ?><tr class="empty-row"><td colspan="5">No review activity yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'includes/admin_layout_end.php'; ?>
