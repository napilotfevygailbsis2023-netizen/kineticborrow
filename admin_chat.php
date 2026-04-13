<?php
require_once 'includes/db.php';
require_once 'includes/admin_auth.php';
requireAdmin();
$active_menu = 'chat';
$admin_id = $_SESSION['admin_id'];

// ── SEND MESSAGE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'send') {
    $handler_id = intval($_POST['handler_id'] ?? 0);
    $message    = trim($conn->real_escape_string($_POST['message'] ?? ''));

    if ($handler_id && $message !== '') {
        // Get or create thread
        $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$admin_id AND handler_id=$handler_id LIMIT 1")->fetch_assoc();
        if (!$thread) {
            $conn->query("INSERT INTO chat_threads (admin_id, handler_id) VALUES ($admin_id, $handler_id)");
            $thread_id = $conn->insert_id;
        } else {
            $thread_id = $thread['id'];
            $conn->query("UPDATE chat_threads SET updated_at=NOW() WHERE id=$thread_id");
        }
        $conn->query("INSERT INTO chat_messages (thread_id, sender_type, sender_id, message) VALUES ($thread_id, 'admin', $admin_id, '$message')");
    }
    header("Location: admin_chat.php?handler_id=$handler_id");
    exit();
}

// ── MARK AS READ ─────────────────────────────────────────────
$active_handler_id = intval($_GET['handler_id'] ?? 0);
if ($active_handler_id) {
    $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$admin_id AND handler_id=$active_handler_id LIMIT 1")->fetch_assoc();
    if ($thread) {
        $conn->query("UPDATE chat_messages SET is_read=1 WHERE thread_id={$thread['id']} AND sender_type='handler' AND is_read=0");
    }
}

// ── LOAD HANDLERS WITH THREAD INFO ───────────────────────────
$handlers = $conn->query("
    SELECT h.id, h.name, h.email,
           ct.id as thread_id,
           ct.updated_at as last_msg_time,
           (SELECT message FROM chat_messages WHERE thread_id=ct.id ORDER BY created_at DESC LIMIT 1) as last_msg,
           (SELECT COUNT(*) FROM chat_messages WHERE thread_id=ct.id AND sender_type='handler' AND is_read=0) as unread
    FROM handlers h
    LEFT JOIN chat_threads ct ON ct.handler_id=h.id AND ct.admin_id=$admin_id
    ORDER BY ct.updated_at DESC, h.name ASC
")->fetch_all(MYSQLI_ASSOC);

// ── LOAD MESSAGES FOR ACTIVE THREAD ──────────────────────────
$messages   = [];
$active_handler = null;
if ($active_handler_id) {
    $active_handler = $conn->query("SELECT * FROM handlers WHERE id=$active_handler_id LIMIT 1")->fetch_assoc();
    $thread = $conn->query("SELECT id FROM chat_threads WHERE admin_id=$admin_id AND handler_id=$active_handler_id LIMIT 1")->fetch_assoc();
    if ($thread) {
        $messages = $conn->query("
            SELECT * FROM chat_messages
            WHERE thread_id={$thread['id']}
            ORDER BY created_at ASC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

// Total unread for topbar badge
$total_unread = $conn->query("
    SELECT COUNT(*) FROM chat_messages cm
    JOIN chat_threads ct ON cm.thread_id=ct.id
    WHERE ct.admin_id=$admin_id AND cm.sender_type='handler' AND cm.is_read=0
")->fetch_row()[0];

include 'includes/admin_layout.php';
?>

<style>
.content { padding: 0 !important; overflow: hidden; }
</style>
<div style="display:grid;grid-template-columns:300px 1fr;gap:0;height:calc(100vh - 58px);">

  <!-- ── SIDEBAR: HANDLER LIST ── -->
  <div style="background:#fff;border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;">
    <div style="padding:18px 20px;border-bottom:1px solid var(--border);">
      <div style="font-family:'Playfair Display',serif;font-size:17px;font-weight:800;color:var(--text)">💬 Messages</div>
      <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= count($handlers) ?> handler<?= count($handlers)!==1?'s':'' ?></div>
    </div>
    <div style="overflow-y:auto;flex:1;">
      <?php foreach($handlers as $h): ?>
      <?php $is_active = $active_handler_id === (int)$h['id']; ?>
      <a href="admin_chat.php?handler_id=<?= $h['id'] ?>"
         style="display:flex;align-items:center;gap:12px;padding:14px 20px;text-decoration:none;border-bottom:1px solid #F5F2EE;transition:background .15s;background:<?= $is_active?'var(--gold-bg)':'#fff' ?>;border-left:3px solid <?= $is_active?'var(--gold)':'transparent' ?>">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#0E7C86,#095E66);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;flex-shrink:0;position:relative;">
          <?= strtoupper(substr($h['name'],0,1)) ?>
          <?php if($h['unread'] > 0): ?>
          <span style="position:absolute;top:-3px;right:-3px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $h['unread'] ?></span>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:<?= $h['unread']>0?'700':'600' ?>;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($h['name']) ?></div>
          <div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">
            <?= $h['last_msg'] ? htmlspecialchars(substr($h['last_msg'],0,35)).'...' : 'No messages yet' ?>
          </div>
        </div>
        <?php if($h['last_msg_time']): ?>
        <div style="font-size:10px;color:var(--muted);flex-shrink:0;"><?= date('M j', strtotime($h['last_msg_time'])) ?></div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
      <?php if(empty($handlers)): ?>
      <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px;">No handlers registered yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── CHAT AREA ── -->
  <div style="display:flex;flex-direction:column;background:#F7F5F2;overflow:hidden;">

    <?php if($active_handler): ?>
    <!-- CHAT HEADER -->
    <div style="background:#fff;border-bottom:1px solid var(--border);padding:14px 24px;display:flex;align-items:center;gap:12px;">
      <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#0E7C86,#095E66);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;">
        <?= strtoupper(substr($active_handler['name'],0,1)) ?>
      </div>
      <div>
        <div style="font-weight:700;color:var(--text);font-size:14px;"><?= htmlspecialchars($active_handler['name']) ?></div>
        <div style="font-size:11px;color:var(--muted);">🔧 Equipment Handler · <?= htmlspecialchars($active_handler['email']) ?></div>
      </div>
    </div>

    <!-- MESSAGES -->
    <div style="flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:10px;" id="msg-area">
      <?php if(empty($messages)): ?>
      <div style="text-align:center;color:var(--muted);font-size:13px;margin-top:40px;">
        <div style="font-size:36px;margin-bottom:10px">💬</div>
        Start a conversation with <?= htmlspecialchars($active_handler['name']) ?>
      </div>
      <?php endif; ?>

      <?php foreach($messages as $m): ?>
      <?php $is_mine = $m['sender_type'] === 'admin'; ?>
      <div style="display:flex;justify-content:<?= $is_mine?'flex-end':'flex-start' ?>;align-items:flex-end;gap:8px;">
        <?php if(!$is_mine): ?>
        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#0E7C86,#095E66);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($active_handler['name'],0,1)) ?>
        </div>
        <?php endif; ?>
        <div style="max-width:68%;">
          <div style="
            background:<?= $is_mine?'var(--gold)':'#fff' ?>;
            color:<?= $is_mine?'#fff':'var(--text)' ?>;
            border-radius:<?= $is_mine?'18px 18px 4px 18px':'18px 18px 18px 4px' ?>;
            padding:10px 14px;
            font-size:13px;
            line-height:1.5;
            box-shadow:0 1px 4px rgba(0,0,0,.08);
            border:<?= $is_mine?'none':'1px solid var(--border)' ?>;
          "><?= nl2br(htmlspecialchars($m['message'])) ?></div>
          <div style="font-size:10px;color:var(--muted);margin-top:4px;text-align:<?= $is_mine?'right':'left' ?>;">
            <?= date('g:i A · M j', strtotime($m['created_at'])) ?>
            <?= ($is_mine && $m['is_read']) ? ' · ✓ Read' : '' ?>
          </div>
        </div>
        <?php if($is_mine): ?>
        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($_SESSION['admin']['name'],0,1)) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- MESSAGE INPUT -->
    <div style="background:#fff;border-top:1px solid var(--border);padding:16px 24px;">
      <form method="POST" action="admin_chat.php?handler_id=<?= $active_handler_id ?>" style="display:flex;gap:10px;align-items:flex-end;">
        <input type="hidden" name="act" value="send"/>
        <input type="hidden" name="handler_id" value="<?= $active_handler_id ?>"/>
        <textarea name="message" id="msg-input" rows="1"
          placeholder="Type a message to <?= htmlspecialchars($active_handler['name']) ?>..."
          style="flex:1;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:13px;color:var(--text);outline:none;resize:none;max-height:120px;transition:border-color .2s;line-height:1.5;"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.closest('form').submit();}"
          oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"
          onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--border)'"></textarea>
        <button type="submit" style="background:var(--gold);color:#fff;border:none;border-radius:12px;padding:10px 18px;cursor:pointer;font-weight:700;font-size:13px;font-family:'DM Sans',sans-serif;transition:background .18s;flex-shrink:0;" onmouseover="this.style.background='#D9952E'" onmouseout="this.style.background='var(--gold)'">
          Send ↑
        </button>
      </form>
      <div style="font-size:11px;color:var(--muted);margin-top:6px;">Press Enter to send · Shift+Enter for new line</div>
    </div>

    <?php else: ?>
    <!-- NO HANDLER SELECTED -->
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);text-align:center;padding:40px;">
      <div style="font-size:56px;margin-bottom:16px;">💬</div>
      <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:var(--text);margin-bottom:8px;">Admin ↔ Handler Chat</div>
      <div style="font-size:13px;line-height:1.7;max-width:300px;">Select a handler from the left to start or continue a conversation. Messages are private between you and each handler.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Auto-scroll to bottom of messages
const area = document.getElementById('msg-area');
if (area) area.scrollTop = area.scrollHeight;

// Auto-poll for new messages every 5 seconds
<?php if($active_handler_id): ?>
setInterval(() => {
  fetch('chat_poll.php?handler_id=<?= $active_handler_id ?>&role=admin&last_id=<?= $messages ? end($messages)['id'] : 0 ?>')
    .then(r => r.json())
    .then(data => {
      if (data.messages && data.messages.length > 0) {
        location.reload();
      }
    }).catch(() => {});
}, 5000);
<?php endif; ?>
</script>

<?php include 'includes/admin_layout_end.php'; ?>
