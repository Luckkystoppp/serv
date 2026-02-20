<style>
.site-footer { display: none !important; }
</style>

<?php
$db = getDB();
$activeChannel = (int)($_GET['channel'] ?? 0);
$dmUserId      = (int)($_GET['dm'] ?? 0);
$dmName        = sanitizeInput($_GET['name'] ?? '');

// Channels for current user
$stmt = $db->prepare("
  SELECT cc.*, p.title as product_title,
    buyer.username  as buyer_name,  buyer.avatar  as buyer_avatar,  buyer.role  as buyer_role,
    seller.username as seller_name, seller.avatar as seller_avatar, seller.role as seller_role
  FROM chat_channels cc
  JOIN products p  ON p.id  = cc.product_id
  JOIN users buyer  ON buyer.id  = cc.buyer_id
  JOIN users seller ON seller.id = cc.seller_id
  WHERE (cc.buyer_id=? OR cc.seller_id=?) AND cc.status='active'
  ORDER BY cc.created_at DESC
");
$stmt->execute([$user['id'], $user['id']]);
$channels = $stmt->fetchAll();

// DM list — only users already messaged
$stmt2 = $db->prepare("
  SELECT DISTINCT
    CASE WHEN m.sender_id=? THEN m.receiver_id ELSE m.sender_id END as peer_id,
    u.username as peer_name, u.avatar as peer_avatar, u.role as peer_role,
    MAX(m.created_at) as last_time
  FROM messages m
  JOIN users u ON u.id = CASE WHEN m.sender_id=? THEN m.receiver_id ELSE m.sender_id END
  WHERE m.is_dm=1 AND (m.sender_id=? OR m.receiver_id=?)
    AND u.id IS NOT NULL
  GROUP BY peer_id, peer_name, peer_avatar, peer_role
  ORDER BY last_time DESC
");
$stmt2->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$dmList = $stmt2->fetchAll();

$isDm      = $dmUserId > 0;
$isChannel = !$isDm && $activeChannel > 0;

// Find active channel object
$currentChannel = null;
if ($isChannel) {
  foreach ($channels as $ch) {
    if ((int)$ch['id'] === $activeChannel) { $currentChannel = $ch; break; }
  }
  if (!$currentChannel) $isChannel = false;
}

// Auto-select first channel if nothing selected
if (!$isChannel && !$isDm && count($channels) > 0) {
  $activeChannel  = (int)$channels[0]['id'];
  $currentChannel = $channels[0];
  $isChannel      = true;
}

// Helper: render input area
function renderInputArea() { ?>
<div class="chat-input-area">
  <div id="chat-reply-bar" class="chat-reply-bar">
    <span class="icon-reply" style="color:var(--accent);flex-shrink:0;"></span>
    <span id="chat-reply-text" class="chat-reply-text"></span>
    <button id="chat-reply-cancel" class="chat-reply-cancel"><span class="icon-x"></span></button>
  </div>
  <div id="chat-preview" style="display:none;margin-bottom:8px;">
    <div style="position:relative;display:inline-block;">
      <img id="chat-preview-img" src="" style="max-height:90px;border-radius:8px;border:1px solid var(--border);" alt="">
      <button onclick="window.chatApp?.clearPreview()"
        style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:var(--danger);border:none;border-radius:50%;color:#fff;cursor:pointer;font-size:.65rem;display:flex;align-items:center;justify-content:center;">
        <span class="icon-x"></span>
      </button>
    </div>
  </div>
  <div class="chat-input-row">
    <label class="chat-img-btn" title="Gửi ảnh">
      <span class="icon-image"></span>
      <input type="file" id="chat-file" accept="image/*" style="display:none;">
    </label>
    <div class="chat-input-wrap">
      <textarea class="chat-textarea" id="chat-input" placeholder="Nhập tin nhắn..." rows="1"></textarea>
    </div>
    <button class="chat-send-btn" id="chat-send"><span class="icon-send"></span></button>
  </div>
</div>
<?php } ?>

<!-- Chat layout: fixed, fills viewport -->
<div class="chat-layout">

  <!-- ── LIST PANEL ── -->
  <div class="chat-list-panel" id="chat-list-panel">

    <!-- Mobile header (back button) -->
    <div class="chat-list-mobile-hdr">
      <button class="chat-list-back-btn" onclick="window.location.href='index.php'" title="Quay lại">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M13 4L7 10l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <span class="icon-chat"></span>
      <span>Tin nhắn</span>
    </div>

    <!-- Desktop header -->
    <div class="chat-list-header">
      <button class="chat-list-back-arrow" onclick="window.location.href='index.php'" title="Quay lại">←</button>
      <span class="icon-chat"></span> Tin nhắn
    </div>

    <div class="chat-list-search">
      <input type="text" placeholder="Tìm cuộc trò chuyện...">
    </div>

    <div class="chat-list-scroll">
      <!-- PINNED: Chat chung luôn ở đầu -->
      <div style="padding:6px 14px 3px;font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;">Cộng đồng</div>
      <a href="index.php?page=global-chat"
         class="chat-list-item <?= $currentPage==='global-chat' ? 'active' : '' ?>"
         style="border-left:2px solid var(--accent);"
         onclick="if(window.innerWidth<=768) closeMobileList()">
        <div class="chat-list-avatar" style="position:relative;flex-shrink:0;width:38px;height:38px;">
          <img src="assets/dragon.jpg" alt="Chat chung" crossorigin="anonymous"
               style="border-radius:50%;width:38px;height:38px;object-fit:cover;display:block;"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
          <div style="display:none;width:38px;height:38px;background:var(--accent);border-radius:50%;align-items:center;justify-content:center;color:#fff;font-size:.9rem;position:absolute;top:0;left:0;">
            <span class="icon-global"></span>
          </div>
          <div style="position:absolute;bottom:0;right:0;width:10px;height:10px;background:#4caf50;border-radius:50%;border:2px solid var(--surface);z-index:1;"></div>
        </div>
        <div class="chat-list-info">
          <div class="chat-list-name" style="display:flex;align-items:center;gap:5px;">
            TreTrau Network
            <span style="display:inline-flex;align-items:center;gap:3px;background:linear-gradient(135deg,#ff3d00,#ff6d00);color:#fff;font-size:.52rem;font-weight:900;letter-spacing:.1em;padding:1px 5px;border-radius:99px;text-transform:uppercase;">
              <span style="width:4px;height:4px;background:#fff;border-radius:50%;animation:live-blink 1s infinite;display:inline-block;"></span>
              LIVE
            </span>
          </div>
          <div class="chat-list-preview">Trò chuyện cộng đồng</div>
        </div>
        <div class="chat-list-time" style="color:var(--accent);font-size:.7rem;font-weight:700;">📌</div>
      </a>

      <?php if ($channels || $dmList): ?>

        <?php if ($channels): ?>
        <div style="padding:6px 14px 3px;font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;">Kênh mua bán</div>
        <?php foreach ($channels as $ch): ?>
          <?php
          $isBuyer   = (int)$ch['buyer_id'] === (int)$user['id'];
          $peerName  = $isBuyer ? $ch['seller_name'] : $ch['buyer_name'];
          $peerAv    = $isBuyer ? $ch['seller_avatar'] : $ch['buyer_avatar'];
          $isActive  = $isChannel && (int)$ch['id'] === $activeChannel;
          ?>
          <a href="index.php?page=chat&channel=<?= $ch['id'] ?>"
             class="chat-list-item <?= $isActive ? 'active' : '' ?>"
             onclick="if(window.innerWidth<=768) closeMobileList()">
            <div class="chat-list-avatar">
              <img src="<?= htmlspecialchars(getAvatarUrl($peerAv)) ?>" alt="">
            </div>
            <div class="chat-list-info">
              <div class="chat-list-name" style="display:flex;align-items:center;gap:4px;">
                <?= htmlspecialchars($peerName) ?>
                <?php $peerRole = $isBuyer ? $ch['seller_role'] : $ch['buyer_role']; ?>
                <?php if ($peerRole === 'admin'): ?>
                <span title="Admin" style="display:inline-flex;flex-shrink:0;"><svg viewBox="0 0 14 14" fill="none" width="13" height="13"><circle cx="7" cy="7" r="7" fill="#e8192c"/><path d="M3.5 7.2l2 2 4-4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <?php elseif ($peerRole === 'premium'): ?>
                <span title="Premium" style="display:inline-flex;flex-shrink:0;"><svg viewBox="0 0 14 14" fill="none" width="13" height="13"><circle cx="7" cy="7" r="7" fill="#1877f2"/><path d="M3.5 7.2l2 2 4-4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <?php endif; ?>
              </div>
              <div class="chat-list-preview"><span class="icon-lock" style="font-size:.65rem;"></span> <?= htmlspecialchars(mb_substr($ch['product_title'],0,30)) ?></div>
            </div>
            <div class="chat-list-time"><?= timeAgo($ch['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($dmList): ?>
        <div style="padding:6px 14px 3px;font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;">Tin nhắn riêng</div>
        <?php foreach ($dmList as $dm): ?>
          <?php $isActive = $isDm && (int)$dm['peer_id'] === $dmUserId; ?>
          <a href="index.php?page=chat&dm=<?= $dm['peer_id'] ?>&name=<?= urlencode($dm['peer_name']) ?>"
             class="chat-list-item <?= $isActive ? 'active' : '' ?>"
             onclick="if(window.innerWidth<=768) closeMobileList()">
            <div class="chat-list-avatar">
              <img src="<?= htmlspecialchars(getAvatarUrl($dm['peer_avatar'])) ?>" alt="">
            </div>
            <div class="chat-list-info">
              <div class="chat-list-name" style="display:flex;align-items:center;gap:4px;">
                <?= htmlspecialchars($dm['peer_name']) ?>
                <?php if (($dm['peer_role'] ?? '') === 'admin'): ?>
                <span title="Admin" style="display:inline-flex;flex-shrink:0;"><svg viewBox="0 0 14 14" fill="none" width="13" height="13"><circle cx="7" cy="7" r="7" fill="#e8192c"/><path d="M3.5 7.2l2 2 4-4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <?php elseif (($dm['peer_role'] ?? '') === 'premium'): ?>
                <span title="Premium" style="display:inline-flex;flex-shrink:0;"><svg viewBox="0 0 14 14" fill="none" width="13" height="13"><circle cx="7" cy="7" r="7" fill="#1877f2"/><path d="M3.5 7.2l2 2 4-4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <?php endif; ?>
              </div>
              <div class="chat-list-preview">Tin nhắn riêng</div>
            </div>
            <div class="chat-list-time"><?= timeAgo($dm['last_time']) ?></div>
          </a>
        <?php endforeach; ?>
        <?php endif; ?>

      <?php else: ?>
        <div class="empty-state" style="padding:var(--sp-xl);">
          <div class="empty-icon"><span class="icon-chat"></span></div>
          <div class="empty-title">Chưa có cuộc trò chuyện</div>
          <div class="empty-desc">Khi bạn liên hệ mua sản phẩm, kênh chat sẽ xuất hiện ở đây.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── MAIN PANEL ── -->
  <div class="chat-main-panel">

    <?php if ($isChannel && $currentChannel): ?>
    <?php
    $isBuyer   = (int)$currentChannel['buyer_id'] === (int)$user['id'];
    $peerName  = $isBuyer ? $currentChannel['seller_name'] : $currentChannel['buyer_name'];
    $peerAv    = $isBuyer ? $currentChannel['seller_avatar'] : $currentChannel['buyer_avatar'];
    $peerRole  = $isBuyer ? $currentChannel['seller_role'] : $currentChannel['buyer_role'];
    $peerId2   = $isBuyer ? $currentChannel['seller_id'] : $currentChannel['buyer_id'];
    ?>
    <div class="chat-panel-header">
      <button class="chat-mob-back-btn" onclick="openMobileList()" title="Quay lại">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M11 4L6 9l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <img src="<?= htmlspecialchars(getAvatarUrl($peerAv)) ?>"
           class="chat-hdr-avatar" onclick="showUserProfile(<?= $peerId2 ?>)" alt="" title="Xem hồ sơ">
      <div class="chat-panel-info">
        <div class="chat-panel-name">
          <?= htmlspecialchars($peerName) ?>
          <?php if (in_array($peerRole, ['admin','premium'])): ?>
          <span class="chat-hdr-tick <?= $peerRole === 'admin' ? 'admin-tick' : 'premium-tick' ?>" title="<?= $peerRole === 'admin' ? 'Admin' : 'Premium' ?>">
            <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="<?= $peerRole === 'admin' ? '#e8192c' : '#1877f2' ?>"/><path d="M4.5 8.3l2.3 2.3 4.5-4.6" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <?php endif; ?>
        </div>
        <div class="chat-panel-sub"><?= htmlspecialchars(mb_substr($currentChannel['product_title'],0,42)) ?></div>
      </div>
      <div class="chat-secure-badge"><span class="icon-lock"></span> Bảo mật</div>
    </div>
    <div class="chat-messages-wrap" id="chat-messages"></div>
    <?php renderInputArea(); ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      window.chatApp = new ChatApp({ channelId: <?= $activeChannel ?>, isGlobal: false, isDm: false, peerId: null, currentUserId: <?= $user['id'] ?> });
    });
    </script>

    <?php elseif ($isDm && $dmUserId): ?>
    <?php
    $peerStmt = $db->prepare("SELECT id, username, avatar, role FROM users WHERE id=?");
    $peerStmt->execute([$dmUserId]);
    $peer = $peerStmt->fetch();
    ?>
    <div class="chat-panel-header">
      <button class="chat-mob-back-btn" onclick="openMobileList()" title="Quay lại">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M11 4L6 9l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <?php if ($peer): ?>
      <img src="<?= htmlspecialchars(getAvatarUrl($peer['avatar'])) ?>"
           class="chat-hdr-avatar" onclick="showUserProfile(<?= $peer['id'] ?>)" alt="" title="Xem hồ sơ">
      <div class="chat-panel-info">
        <div class="chat-panel-name">
          <?= htmlspecialchars($peer['username']) ?>
          <?php if (in_array($peer['role'] ?? '', ['admin','premium'])): ?>
          <span class="chat-hdr-tick <?= ($peer['role'] === 'admin') ? 'admin-tick' : 'premium-tick' ?>" title="<?= $peer['role'] === 'admin' ? 'Admin' : 'Premium' ?>">
            <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="<?= $peer['role'] === 'admin' ? '#e8192c' : '#1877f2' ?>"/><path d="M4.5 8.3l2.3 2.3 4.5-4.6" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <?php endif; ?>
        </div>
        <div class="chat-panel-sub">Tin nhắn riêng</div>
      </div>
      <?php else: ?>
      <div class="chat-panel-info">
        <div class="chat-panel-name"><?= htmlspecialchars($dmName) ?></div>
        <div class="chat-panel-sub">Tin nhắn riêng</div>
      </div>
      <?php endif; ?>
    </div>
    <div class="chat-messages-wrap" id="chat-messages"></div>
    <?php renderInputArea(); ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      window.chatApp = new ChatApp({ channelId: null, isGlobal: false, isDm: true, peerId: <?= $dmUserId ?>, currentUserId: <?= $user['id'] ?> });
    });
    </script>

    <?php else: ?>
    <div class="empty-state" style="flex:1;height:100%;justify-content:center;">
      <div class="empty-icon"><span class="icon-chat"></span></div>
      <div class="empty-title">Chọn cuộc trò chuyện</div>
      <div class="empty-desc">Chọn một kênh chat từ danh sách bên trái.</div>
    </div>
    <?php endif; ?>

  </div><!-- end main-panel -->
</div><!-- end chat-layout -->

<script>
function openMobileList()  {
  document.getElementById('chat-list-panel')?.classList.add('mobile-open');
}
function closeMobileList() {
  document.getElementById('chat-list-panel')?.classList.remove('mobile-open');
}

document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth <= 768) {
    const urlParams = new URLSearchParams(window.location.search);
    const hasConv = urlParams.has('channel') || urlParams.has('dm');
    if (!hasConv) {
      openMobileList();
    }
  }

  // Fix: khi bàn phím ảo hiện/ẩn, scroll messages xuống cuối
  const msgs = document.getElementById('chat-messages');
  if (msgs) {
    const scrollToBottom = () => {
      msgs.scrollTop = msgs.scrollHeight;
    };

    // VisualViewport API — chính xác nhất cho mobile keyboard
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', () => {
        scrollToBottom();
        // Đảm bảo input không bị bàn phím che
        const inputArea = document.querySelector('.chat-input-area');
        if (inputArea) {
          inputArea.scrollIntoView({ block: 'end', behavior: 'instant' });
        }
      });
    }

    // Khi input được focus, scroll xuống
    const input = document.getElementById('chat-input');
    if (input) {
      input.addEventListener('focus', () => {
        setTimeout(scrollToBottom, 300);
      });
    }
  }
});
</script>
