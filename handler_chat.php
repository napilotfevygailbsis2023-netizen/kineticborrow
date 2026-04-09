<?php
require_once 'includes/db.php';
require_once 'includes/handler_auth.php';
requireHandler();
$active_menu = 'chat';
$hid = $_SESSION['handler_id'];

// ── SEND MESSAGE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'send') {
    $admin_id = intval($_POST['admin_id'] ?? 0);
    $message  = trim($conn->real_escape_string($_POST['message'] ?? ''));

    if ($admin_id && $message !== '') {
        $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$admin_id AND handler_id=$hid LIMIT 1")->fetch_assoc();
        if (!$thread) {
            $conn->query("INSERT INTO chat_threads (admin_id, handler_id) VALUES ($admin_id, $hid)");
            $thread_id = $conn->insert_id;
        } else {
            $thread_id = $thread['id'];
            $conn->query("UPDATE chat_threads SET updated_at=NOW() WHERE id=$thread_id");
        }
        $conn->query("INSERT INTO chat_messages (thread_id, sender_type, sender_id, message) VALUES ($thread_id, 'handler', $hid, '$message')");
    }
    header("Location: handler_chat.php?admin_id=$admin_id");
    exit();
}

// ── MARK AS READ ─────────────────────────────────────────────
$active_admin_id = intval($_GET['admin_id'] ?? 0);
if ($active_admin_id) {
    $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$active_admin_id AND handler_id=$hid LIMIT 1")->fetch_assoc();
    if ($thread) {
        $conn->query("UPDATE chat_messages SET is_read=1 WHERE thread_id={$thread['id']} AND sender_type='admin' AND is_read=0");
    }
}

// ── LOAD ADMINS WITH THREAD INFO ─────────────────────────────
$admins = $conn->query("
    SELECT a.id, a.name, a.email, a.role,
           ct.id as thread_id,
           ct.updated_at as last_msg_time,
           (SELECT message FROM chat_messages WHERE thread_id=ct.id ORDER BY created_at DESC LIMIT 1) as last_msg,
           (SELECT COUNT(*) FROM chat_messages WHERE thread_id=ct.id AND sender_type='admin' AND is_read=0) as unread
    FROM admins a
    LEFT JOIN chat_threads ct ON ct.admin_id=a.id AND ct.handler_id=$hid
    ORDER BY ct.updated_at DESC, a.name ASC
")->fetch_all(MYSQLI_ASSOC);

// ── LOAD MESSAGES ─────────────────────────────────────────────
$messages    = [];
$active_admin = null;
if ($active_admin_id) {
    $active_admin = $conn->query("SELECT * FROM admins WHERE id=$active_admin_id LIMIT 1")->fetch_assoc();
    $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$active_admin_id AND handler_id=$hid LIMIT 1")->fetch_assoc();
    if ($thread) {
        $messages = $conn->query("
            SELECT * FROM chat_messages
            WHERE thread_id={$thread['id']}
            ORDER BY created_at ASC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

// Total unread
$total_unread = $conn->query("
    SELECT COUNT(*) FROM chat_messages cm
    JOIN chat_threads ct ON cm.thread_id=ct.id
    WHERE ct.handler_id=$hid AND cm.sender_type='admin' AND cm.is_read=0
")->fetch_row()[0];

include 'includes/handler_layout.php';
?>

<style>
.content { padding: 0 !important; overflow: hidden; }
</style>
<div style="display:grid;grid-template-columns:280px 1fr;gap:0;height:calc(100vh - 56px);">

  <!-- ── SIDEBAR: ADMIN LIST ── -->
  <div style="background:#fff;border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;">
    <div style="padding:16px 18px;border-bottom:1px solid var(--border);">
      <div style="font-family:'Playfair Display',serif;font-size:16px;font-weight:800;color:var(--text)">💬 Messages</div>
      <div style="font-size:11px;color:var(--muted);margin-top:2px;">Admin team</div>
    </div>
    <div style="overflow-y:auto;flex:1;">
      <?php foreach($admins as $a): ?>
      <?php $is_active = $active_admin_id === (int)$a['id']; ?>
      <a href="handler_chat.php?admin_id=<?= $a['id'] ?>"
         style="display:flex;align-items:center;gap:11px;padding:13px 18px;text-decoration:none;border-bottom:1px solid #EBF4F5;transition:background .15s;background:<?= $is_active?'var(--teal-bg)':'#fff' ?>;border-left:3px solid <?= $is_active?'var(--teal)':'transparent' ?>">
        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0;position:relative;">
          <?= strtoupper(substr($a['name'],0,1)) ?>
          <?php if($a['unread'] > 0): ?>
          <span style="position:absolute;top:-3px;right:-3px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $a['unread'] ?></span>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:<?= $a['unread']>0?'700':'600' ?>;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($a['name']) ?></div>
          <div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;">
            <?= $a['last_msg'] ? htmlspecialchars(substr($a['last_msg'],0,30)).'...' : 'No messages yet' ?>
          </div>
        </div>
        <?php if($a['last_msg_time']): ?>
        <div style="font-size:10px;color:var(--muted);flex-shrink:0;"><?= date('M j', strtotime($a['last_msg_time'])) ?></div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── CHAT AREA ── -->
  <div style="display:flex;flex-direction:column;background:var(--bg);overflow:hidden;">

    <?php if($active_admin): ?>
    <!-- HEADER -->
    <div style="background:#fff;border-bottom:1px solid var(--border);padding:13px 22px;display:flex;align-items:center;gap:11px;">
      <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;">
        <?= strtoupper(substr($active_admin['name'],0,1)) ?>
      </div>
      <div>
        <div style="font-weight:700;color:var(--text);font-size:14px;"><?= htmlspecialchars($active_admin['name']) ?></div>
        <div style="font-size:11px;color:var(--muted);">⚙️ <?= ucfirst($active_admin['role']) ?> · <?= htmlspecialchars($active_admin['email']) ?></div>
      </div>
    </div>

    <!-- MESSAGES -->
    <div style="flex:1;overflow-y:auto;padding:18px 22px;display:flex;flex-direction:column;gap:10px;" id="msg-area">
      <?php if(empty($messages)): ?>
      <div style="text-align:center;color:var(--muted);font-size:13px;margin-top:40px;">
        <div style="font-size:36px;margin-bottom:10px">💬</div>
        Send a message to <?= htmlspecialchars($active_admin['name']) ?>
      </div>
      <?php endif; ?>

      <?php foreach($messages as $m): ?>
      <?php $is_mine = $m['sender_type'] === 'handler'; ?>
      <div style="display:flex;justify-content:<?= $is_mine?'flex-end':'flex-start' ?>;align-items:flex-end;gap:8px;">
        <?php if(!$is_mine): ?>
        <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($active_admin['name'],0,1)) ?>
        </div>
        <?php endif; ?>
        <div style="max-width:68%;">
          <div style="
            background:<?= $is_mine?'var(--teal)':'#fff' ?>;
            color:<?= $is_mine?'#fff':'var(--text)' ?>;
            border-radius:<?= $is_mine?'18px 18px 4px 18px':'18px 18px 18px 4px' ?>;
            padding:10px 14px;font-size:13px;line-height:1.5;
            box-shadow:0 1px 4px rgba(0,0,0,.08);
            border:<?= $is_mine?'none':'1px solid var(--border)' ?>;
          "><?= nl2br(htmlspecialchars($m['message'])) ?></div>
          <div style="font-size:10px;color:var(--muted);margin-top:3px;text-align:<?= $is_mine?'right':'left' ?>;">
            <?= date('g:i A · M j', strtotime($m['created_at'])) ?>
            <?= ($is_mine && $m['is_read']) ? ' · ✓ Read' : '' ?>
          </div>
        </div>
        <?php if($is_mine): ?>
        <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--teal-dk));display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($handler['name'],0,1)) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- INPUT -->
    <div style="background:#fff;border-top:1px solid var(--border);padding:14px 22px;">
      <form method="POST" action="handler_chat.php?admin_id=<?= $active_admin_id ?>" style="display:flex;gap:10px;align-items:flex-end;">
        <input type="hidden" name="act" value="send"/>
        <input type="hidden" name="admin_id" value="<?= $active_admin_id ?>"/>
        <textarea name="message" rows="1"
          placeholder="Message <?= htmlspecialchars($active_admin['name']) ?>..."
          style="flex:1;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:13px;color:var(--text);outline:none;resize:none;max-height:120px;line-height:1.5;transition:border-color .2s;"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.closest('form').submit();}"
          oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"
          onfocus="this.style.borderColor='var(--teal)'" onblur="this.style.borderColor='var(--border)'"></textarea>
        <button type="submit" style="background:var(--teal);color:#fff;border:none;border-radius:12px;padding:10px 16px;cursor:pointer;font-weight:700;font-size:13px;font-family:'DM Sans',sans-serif;transition:background .18s;flex-shrink:0;" onmouseover="this.style.background='var(--teal-dk)'" onmouseout="this.style.background='var(--teal)'">
          Send ↑
        </button>
      </form>
      <div style="font-size:11px;color:var(--muted);margin-top:5px;">Enter to send · Shift+Enter for new line</div>
    </div>

    <?php else: ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);text-align:center;padding:40px;">
      <div style="font-size:52px;margin-bottom:14px;">💬</div>
      <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:8px;">Handler ↔ Admin Chat</div>
      <div style="font-size:13px;line-height:1.7;max-width:280px;">Select an admin from the left to send a message or view your conversation.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const area = document.getElementById('msg-area');
if (area) area.scrollTop = area.scrollHeight;

<?php if($active_admin_id): ?>
setInterval(() => {
  fetch('chat_poll.php?admin_id=<?= $active_admin_id ?>&role=handler&last_id=<?= $messages ? end($messages)['id'] : 0 ?>')
    .then(r => r.json())
    .then(data => { if (data.messages && data.messages.length > 0) location.reload(); })
    .catch(() => {});
}, 5000);
<?php endif; ?>
</script>

<?php include 'includes/handler_layout_end.php'; ?>
