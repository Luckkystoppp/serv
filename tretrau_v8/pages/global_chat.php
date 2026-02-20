<?php
$db = getDB();
$onlineStmt = $db->query("SELECT u.id, u.username, u.avatar, u.role FROM users u JOIN traffic_logs t ON t.user_id=u.id WHERE t.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY u.id ORDER BY MAX(t.created_at) DESC LIMIT 20");
$onlineUsers = $onlineStmt->fetchAll();
?>
<style>
/* ── FULL SCREEN CHAT ── */
html, body { overflow: hidden; height: 100%; }

/* Hide site header on mobile, chat takes full screen */
@media(max-width:768px) {
  .site-header { display: none !important; }
  .bottom-nav  { display: none !important; }
  .global-wrap { top: 0 !important; }
}

.global-wrap {
  position: fixed;
  top: var(--header-height);
  left: 0; right: 0; bottom: 0;
  display: grid;
  grid-template-columns: 1fr 200px;
  z-index: 10;
  background: var(--bg-0);
}

/* ── CHAT COLUMN ── */
.global-chat-col {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}

/* ── CHAT HEADER ── */
.global-hdr {
  padding: 8px 14px;
  border-bottom: 1px solid var(--border);
  background: var(--surface);
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  height: 56px;
}

/* Avatar group chat */
.global-hdr-avatar-wrap {
  position: relative;
  flex-shrink: 0;
  width: 38px;
  height: 38px;
}
.global-hdr-avatar {
  width: 38px; height: 38px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border);
}
.global-hdr-avatar-fallback {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: var(--accent);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1rem;
}
.global-online-dot {
  position: absolute;
  bottom: 1px; right: 1px;
  width: 10px; height: 10px;
  background: #4caf50;
  border-radius: 50%;
  border: 2px solid var(--surface);
  animation: pulse-green 2s infinite;
}
@keyframes pulse-green {
  0%, 100% { box-shadow: 0 0 0 0 rgba(76,175,80,0.5); }
  50% { box-shadow: 0 0 0 5px rgba(76,175,80,0); }
}

.global-hdr-info {
  flex: 1;
  min-width: 0;
}
.global-hdr-name {
  font-weight: 700;
  font-size: .93rem;
  display: flex;
  align-items: center;
  gap: 6px;
}
.global-hdr-sub {
  font-size: .73rem;
  color: var(--text-muted);
  margin-top: 1px;
}

/* ── LIVE BADGE — animated, premium look ── */
.global-live-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: linear-gradient(135deg, #ff3d00, #ff6d00);
  color: #fff;
  font-size: 0.58rem;
  font-weight: 900;
  letter-spacing: 0.12em;
  padding: 2px 7px 2px 5px;
  border-radius: 99px;
  text-transform: uppercase;
  position: relative;
  overflow: hidden;
  animation: badge-shimmer 3s infinite;
  box-shadow: 0 2px 8px rgba(255,61,0,0.4);
}
.global-live-badge::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 60%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
  animation: badge-shine 2.5s infinite;
}
@keyframes badge-shine {
  0% { left: -100%; }
  60%, 100% { left: 150%; }
}
@keyframes badge-shimmer {
  0%, 100% { box-shadow: 0 2px 8px rgba(255,61,0,0.4); }
  50% { box-shadow: 0 2px 16px rgba(255,61,0,0.7), 0 0 20px rgba(255,109,0,0.3); }
}
.global-live-dot {
  width: 5px; height: 5px;
  background: #fff;
  border-radius: 50%;
  animation: live-blink 1s infinite;
}
@keyframes live-blink {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.7); }
}

/* Old g-back-btn kept for compatibility */
.g-back-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-primary);
  cursor: pointer;
  padding: 6px 14px 6px 10px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 600;
  flex-shrink: 0;
  transition: all 0.15s;
  letter-spacing: 0.01em;
}
.g-back-btn:hover {
  background: var(--surface-3);
  border-color: var(--border-strong);
  transform: translateX(-2px);
}

.global-online-pill { display:flex; align-items:center; gap:5px; font-size:.74rem; color:var(--success); flex-shrink:0; }
.g-dot { width:7px; height:7px; background:var(--success); border-radius:50%; flex-shrink:0; }

/* ── MESSAGES ── */
#chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  background: var(--bg-0);
  min-height: 0;
}

/* ── INPUT AREA ── */
.g-input-area {
  border-top: 1px solid var(--border);
  background: var(--surface);
  padding: 8px 10px;
  flex-shrink: 0;
  padding-bottom: calc(8px + env(safe-area-inset-bottom));
}

/* Reply bar */
#chat-reply-bar {
  display: none;
  align-items: center;
  gap: 8px;
  background: var(--surface-2);
  border-left: 3px solid var(--accent);
  border-radius: 6px;
  padding: 6px 10px;
  margin-bottom: 6px;
}
#chat-reply-text { flex:1; font-size:.8rem; color:var(--text-secondary); overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
#chat-reply-cancel { background:none; border:none; color:var(--text-muted); cursor:pointer; padding:2px; flex-shrink:0; }

/* Image preview */
#chat-preview { display:none; margin-bottom:6px; position:relative; }
#chat-preview img { max-height:70px; border-radius:8px; border:1px solid var(--border); }
.g-preview-rm { position:absolute; top:-6px; right:-6px; width:20px; height:20px; background:var(--danger); border:none; border-radius:50%; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.6rem; }

.g-input-row { display:flex; align-items:flex-end; gap:8px; }
.g-file-btn { background:transparent; border:none; color:var(--text-muted); cursor:pointer; padding:5px; font-size:1rem; flex-shrink:0; display:flex; align-items:center; }
.g-textarea-wrap { flex:1; background:var(--surface-2); border:1px solid var(--border); border-radius:20px; display:flex; align-items:flex-end; padding:6px 10px 6px 14px; }
.g-textarea-wrap:focus-within { border-color:var(--border-focus); }
#chat-input { flex:1; background:transparent; border:none; outline:none; color:var(--text-primary); font-size:.9rem; resize:none; max-height:100px; min-height:22px; line-height:1.5; font-family:inherit; }
#chat-input::placeholder { color:var(--text-muted); }
#chat-send { width:38px; height:38px; border-radius:50%; background:var(--accent); color:var(--text-inverse); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; }
#chat-send:disabled { opacity:.4; cursor:not-allowed; }

/* ── ONLINE SIDEBAR ── */
.g-sidebar { display:flex; flex-direction:column; overflow:hidden; background:var(--surface); border-left:1px solid var(--border); }
.g-sidebar-hdr { padding:10px 12px; border-bottom:1px solid var(--border); font-size:.68rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; flex-shrink:0; }
.g-online-list { flex:1; overflow-y:auto; padding:4px; }
.g-user-row { display:flex; align-items:center; gap:7px; padding:6px 8px; border-radius:8px; cursor:pointer; transition:background .12s; }
.g-user-row:hover { background:var(--surface-2); }
.g-av { position:relative; flex-shrink:0; }
.g-av img { width:26px; height:26px; border-radius:50%; object-fit:cover; }
.g-av::after { content:''; position:absolute; bottom:0; right:0; width:7px; height:7px; background:var(--success); border-radius:50%; border:1.5px solid var(--surface); }
.g-uname { font-size:.79rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* ── REPLY BUBBLE STYLE ── */
.msg-reply-preview { display:flex; align-items:stretch; gap:6px; background:rgba(0,0,0,.15); border-radius:6px; padding:5px 8px; margin-bottom:5px; cursor:pointer; overflow:hidden; }
.msg-reply-bar { width:3px; background:var(--accent); border-radius:2px; flex-shrink:0; }
.msg-reply-body { display:flex; flex-direction:column; min-width:0; }
.msg-reply-name { font-size:.72rem; font-weight:700; color:var(--accent); }
.msg-reply-content { font-size:.78rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* ── MOBILE ── */
@media(max-width:768px) {
  .global-wrap { grid-template-columns: 1fr; }
  .g-sidebar { display: none; }
}
</style>

<div class="global-wrap">
  <div class="global-chat-col">

    <!-- Header với nút back mobile -->
    <div class="global-hdr">
      <button class="chat-mob-back-btn" onclick="window.location.replace('index.php?page=chat')" title="Quay lại" style="display:flex;">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M11 4L6 9l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="global-hdr-avatar-wrap">
        <img src="assets/dragon.jpg" class="global-hdr-avatar" alt="Chat chung"
             onerror="this.style.display='none';document.getElementById('global-hdr-av-fallback').style.display='flex'">
        <div id="global-hdr-av-fallback" class="global-hdr-avatar-fallback" style="display:none;">
          <span class="icon-global"></span>
        </div>
        <div class="global-online-dot"></div>
      </div>
      <div class="global-hdr-info">
        <div class="global-hdr-name">
          TreTrau Network
          <span class="global-live-badge">
            <span class="global-live-dot"></span>
            LIVE
          </span>
        </div>
        <div class="global-hdr-sub"><?= count($onlineUsers) ?> thành viên đang online</div>
      </div>
    </div>

    <!-- Messages -->
    <div id="chat-messages"></div>

    <!-- Input -->
    <div class="g-input-area">
      <div id="chat-reply-bar">
        <span class="icon-reply" style="color:var(--accent);font-size:.85rem;flex-shrink:0;"></span>
        <span id="chat-reply-text"></span>
        <button id="chat-reply-cancel"><span class="icon-x"></span></button>
      </div>
      <div id="chat-preview">
        <img id="chat-preview-img" src="" alt="">
        <button class="g-preview-rm" onclick="window.chatApp?.clearPreview()"><span class="icon-x"></span></button>
      </div>
      <div class="g-input-row">
        <label class="g-file-btn" title="Gửi ảnh">
          <span class="icon-image"></span>
          <input type="file" id="chat-file" accept="image/*" style="display:none;">
        </label>
        <div class="g-textarea-wrap">
          <textarea id="chat-input" class="chat-textarea" placeholder="Nhắn với mọi người..." rows="1"></textarea>
        </div>
        <button id="chat-send"><span class="icon-send"></span></button>
      </div>
    </div>

  </div>

  <!-- Online sidebar (desktop only) -->
  <div class="g-sidebar">
    <div class="g-sidebar-hdr">Online ngay</div>
    <div class="g-online-list">
      <?php if ($onlineUsers): foreach ($onlineUsers as $ou): ?>
      <div class="g-user-row" onclick="showUserProfile(<?= $ou['id'] ?>)" data-username="<?= htmlspecialchars($ou['username']) ?>">
        <div class="g-av">
          <img src="<?= htmlspecialchars(getAvatarUrl($ou['avatar'])) ?>" alt="">
        </div>
        <div class="g-uname">
          <?= htmlspecialchars($ou['username']) ?>
          <?php if (in_array($ou['role'], ['admin','premium'])): ?>
          <span style="display:inline-flex;align-items:center;margin-left:2px;vertical-align:middle;">
            <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><circle cx="7" cy="7" r="7" fill="<?= $ou['role']==='admin'?'#e8192c':'#1877f2' ?>"/><path d="M4 7.2l2 2 4-4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div style="padding:14px;text-align:center;color:var(--text-muted);font-size:.8rem;">Chưa có ai online</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  window.chatApp = new ChatApp({
    channelId: null,
    isGlobal: true,
    isDm: false,
    peerId: null,
    currentUserId: <?= (int)$user['id'] ?>
  });
});
</script>
