<?php
$profileId = (int)($_GET['id'] ?? 0);
if (!$profileId) { header('Location: index.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=? AND status='active'");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) { ?>
<div class="empty-state">
  <div class="empty-icon"><span class="icon-user"></span></div>
  <div class="empty-title">Người dùng không tồn tại</div>
</div>
<?php return; }

$isMe = $user && (int)$user['id'] === $profileId;
$stmt = $db->prepare("SELECT * FROM products WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 12");
$stmt->execute([$profileId]);
$userProducts = $stmt->fetchAll();
$activeStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE user_id=? AND status='active'"); $activeStmt->execute([$profileId]); $activeCount = $activeStmt->fetchColumn();
$soldStmt   = $db->prepare("SELECT COUNT(*) FROM products WHERE user_id=? AND status='sold'");   $soldStmt->execute([$profileId]);   $soldCount   = $soldStmt->fetchColumn();
$viewsStmt  = $db->prepare("SELECT COALESCE(SUM(views),0) FROM products WHERE user_id=?");       $viewsStmt->execute([$profileId]);  $totalViews  = $viewsStmt->fetchColumn();
$joinDays = max(1, floor((time() - strtotime($profile['created_at'])) / 86400));

$roleColor = match($profile['role']) { 'admin' => '#e8192c', 'premium' => '#1877f2', default => '#444' };
$roleName  = match($profile['role']) { 'admin' => 'Admin', 'premium' => 'Premium', 'user' => 'Member', default => 'New' };
$hasVerify = in_array($profile['role'], ['admin','premium']);
?>
<style>
/* ────────────────────────────────────────────
   PROFILE PAGE — luxury dark, mobile-first
   ──────────────────────────────────────────── */

/* Negative margin để hero chạm edge */
.prof-wrap {
  margin: calc(-1 * var(--sp-xl)) calc(-1 * var(--sp-xl)) 0;
}
@media(max-width:768px){ .prof-wrap { margin: -16px -16px 0; } }

/* ── HERO ── */
.prof-hero {
  position: relative;
  height: 240px;
  overflow: hidden;
  background: #060606;
}
@media(max-width:768px){ .prof-hero { height: 180px; } }

.prof-hero-grad {
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 120% 100% at 0% 100%, color-mix(in srgb, <?= $roleColor ?> 35%, transparent) 0%, transparent 55%),
    radial-gradient(ellipse 80% 80% at 100% 0%, color-mix(in srgb, <?= $roleColor ?> 12%, transparent) 0%, transparent 55%),
    linear-gradient(180deg, #0a0a0a 0%, #060606 100%);
}
.prof-hero-lines {
  position: absolute; inset: 0; opacity: 0.05;
  background-image:
    repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(255,255,255,.5) 39px, rgba(255,255,255,.5) 40px),
    repeating-linear-gradient(90deg, transparent, transparent 39px, rgba(255,255,255,.5) 39px, rgba(255,255,255,.5) 40px);
}
.prof-hero-vignette {
  position: absolute; inset: 0;
  background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.8) 100%);
}

/* ── PROFILE CONTENT ── */
.prof-content {
  padding: 0 var(--sp-xl) var(--sp-xl);
  background: var(--bg-0);
}
@media(max-width:768px){ .prof-content { padding: 0 16px 24px; } }

/* ── IDENTITY ROW ── */
.prof-identity-row {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  margin-top: -44px;
  margin-bottom: 20px;
  position: relative;
  z-index: 5;
}
@media(max-width:768px){
  .prof-identity-row { margin-top: -38px; gap: 12px; }
}

.prof-av-wrap { position: relative; flex-shrink: 0; }
.prof-av {
  width: 88px; height: 88px;
  border-radius: 50%;
  object-fit: cover;
  border: 3.5px solid var(--bg-0);
  box-shadow:
    0 0 0 2px <?= $roleColor ?>,
    0 8px 32px rgba(0,0,0,0.7);
  display: block;
  transition: transform .2s;
}
@media(max-width:768px){ .prof-av { width: 72px; height: 72px; } }
.prof-av:hover { transform: scale(1.04); }

.prof-tick {
  position: absolute; bottom: 2px; right: 2px;
  width: 24px; height: 24px;
  border-radius: 50%;
  border: 2.5px solid var(--bg-0);
  overflow: hidden; display: <?= $hasVerify ? 'flex' : 'none' ?>;
  box-shadow: 0 2px 8px rgba(0,0,0,0.5);
}
.prof-tick svg { width: 100%; height: 100%; }

.prof-meta { flex: 1; min-width: 0; padding-bottom: 6px; }
.prof-name {
  font-size: 1.35rem; font-weight: 900;
  letter-spacing: -0.025em; color: #fff;
  display: flex; align-items: center; gap: 8px;
  flex-wrap: wrap; line-height: 1.1;
}
@media(max-width:768px){ .prof-name { font-size: 1.1rem; } }
.prof-role-badge {
  display: inline-flex; align-items: center;
  padding: 2px 9px;
  background: color-mix(in srgb, <?= $roleColor ?> 18%, transparent);
  border: 1px solid color-mix(in srgb, <?= $roleColor ?> 45%, transparent);
  color: <?= $roleColor ?>;
  border-radius: 999px; font-size: 0.62rem; font-weight: 800;
  letter-spacing: 0.08em; text-transform: uppercase;
  vertical-align: middle;
}
.prof-join {
  font-size: 0.73rem; color: rgba(255,255,255,0.35);
  margin-top: 5px; display: flex; align-items: center; gap: 5px;
}

/* ── BIO ── */
.prof-bio {
  font-size: 0.85rem; color: var(--text-secondary);
  line-height: 1.65; margin-bottom: 20px;
  padding: 12px 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  border-left: 3px solid color-mix(in srgb, <?= $roleColor ?> 60%, transparent);
}

/* ── STATS ROW ── */
.prof-stats {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 10px; margin-bottom: 20px;
}
.prof-stat-card {
  background: var(--surface);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 14px; padding: 16px 12px;
  text-align: center; position: relative; overflow: hidden;
  transition: border-color .15s, transform .15s;
}
.prof-stat-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, <?= $roleColor ?>55, transparent);
}
.prof-stat-card:hover { border-color: rgba(255,255,255,0.15); transform: translateY(-2px); }
.prof-stat-n {
  font-size: 1.6rem; font-weight: 900;
  letter-spacing: -0.04em; line-height: 1;
  color: var(--text-primary);
}
@media(max-width:768px){ .prof-stat-n { font-size: 1.3rem; } }
.prof-stat-l {
  font-size: 0.62rem; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.09em; margin-top: 4px;
}

/* ── ACTIONS ── */
.prof-actions {
  display: flex; gap: 10px; margin-bottom: 28px; flex-wrap: wrap;
}
.prof-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 11px 20px; border-radius: 12px;
  font-size: 0.84rem; font-weight: 700; cursor: pointer;
  border: none; text-decoration: none;
  transition: all 0.18s cubic-bezier(.34,1.56,.64,1);
  letter-spacing: 0.01em; white-space: nowrap;
}
.prof-btn-msg {
  background: var(--accent); color: var(--text-inverse);
  flex: 1;
  box-shadow: 0 4px 20px rgba(255,255,255,0.1);
}
.prof-btn-msg:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(255,255,255,0.18); }
.prof-btn-edit {
  background: var(--surface-2); color: var(--text-primary);
  border: 1px solid var(--border-strong); flex: 1;
}
.prof-btn-edit:hover { background: var(--surface-3); transform: translateY(-1px); }
.prof-btn-report {
  background: rgba(239,68,68,0.1); color: #ef4444;
  border: 1px solid rgba(239,68,68,0.22);
  padding: 11px 16px;
}
.prof-btn-report:hover { background: rgba(239,68,68,0.2); transform: translateY(-1px); }

/* ── PRODUCTS SECTION ── */
.prof-prods-hdr {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 14px; padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
}
.prof-prods-title {
  font-size: 0.68rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted);
}
.prof-prods-count {
  font-size: 0.7rem; color: var(--text-muted);
  background: var(--surface-2); border: 1px solid var(--border);
  padding: 2px 9px; border-radius: 99px; font-weight: 600;
}
</style>

<div class="prof-wrap">
  <!-- HERO BANNER -->
  <div class="prof-hero">
    <div class="prof-hero-grad"></div>
    <div class="prof-hero-lines"></div>
    <div class="prof-hero-vignette"></div>
  </div>

  <!-- CONTENT BLOCK -->
  <div class="prof-content">

    <!-- IDENTITY -->
    <div class="prof-identity-row">
      <div class="prof-av-wrap">
        <img src="<?= htmlspecialchars(getAvatarUrl($profile['avatar'])) ?>" class="prof-av" alt="">
        <?php if ($hasVerify): ?>
        <div class="prof-tick">
          <svg viewBox="0 0 22 22" fill="none">
            <circle cx="11" cy="11" r="11" fill="<?= $roleColor ?>"/>
            <path d="M5.5 11.5l3.5 3.5 7.5-8" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <?php endif; ?>
      </div>
      <div class="prof-meta">
        <div class="prof-name">
          <?= htmlspecialchars($profile['username']) ?>
          <span class="prof-role-badge"><?= $roleName ?></span>
        </div>
        <div class="prof-join">
          <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 3.5V6l1.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
          Tham gia <?= $joinDays ?> ngày trước
        </div>
      </div>
    </div>

    <?php if ($profile['bio']): ?>
    <div class="prof-bio"><?= htmlspecialchars($profile['bio']) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="prof-stats">
      <div class="prof-stat-card">
        <div class="prof-stat-n"><?= $activeCount ?></div>
        <div class="prof-stat-l">Đang bán</div>
      </div>
      <div class="prof-stat-card">
        <div class="prof-stat-n"><?= $soldCount ?></div>
        <div class="prof-stat-l">Đã bán</div>
      </div>
      <div class="prof-stat-card">
        <div class="prof-stat-n"><?= $totalViews > 999 ? round($totalViews/1000,1).'k' : $totalViews ?></div>
        <div class="prof-stat-l">Lượt xem</div>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="prof-actions">
      <?php if ($isMe): ?>
      <a href="index.php?page=account" class="prof-btn prof-btn-edit">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M10 2l2 2-7 7H3v-2l7-7z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
        Chỉnh sửa hồ sơ
      </a>
      <?php elseif ($user): ?>
      <button class="prof-btn prof-btn-msg" onclick="startDM(<?= $profileId ?>, '<?= htmlspecialchars($profile['username']) ?>')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2.5a.5.5 0 01.5-.5h9a.5.5 0 01.5.5v6a.5.5 0 01-.5.5H5l-3 2V2.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
        Nhắn tin
      </button>
      <button class="prof-btn prof-btn-report" onclick="reportUser(<?= $profileId ?>)">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1.5l1.3 4H13l-3.5 2.5 1.3 4L7 9.5 3.2 12l1.3-4L1 5.5h4.7L7 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
      </button>
      <?php else: ?>
      <a href="index.php?page=login" class="prof-btn prof-btn-msg">Đăng nhập để nhắn tin</a>
      <?php endif; ?>
    </div>

    <!-- PRODUCTS -->
    <div class="prof-prods-hdr">
      <span class="prof-prods-title">Sản phẩm đang bán</span>
      <span class="prof-prods-count"><?= $activeCount ?></span>
    </div>

    <?php if ($userProducts): ?>
    <div class="product-grid">
      <?php foreach ($userProducts as $p): ?>
      <a href="index.php?page=product&id=<?= $p['id'] ?>" class="product-card">
        <div class="product-thumb-wrap">
          <img src="<?= htmlspecialchars($p['image']) ?>" class="product-thumb" alt="" loading="lazy">
        </div>
        <div class="product-card-body">
          <div class="product-card-title"><?= htmlspecialchars($p['title']) ?></div>
          <div class="product-card-price"><?= formatPrice($p['price'], $p['currency']) ?></div>
          <div class="product-card-seller">
            <span class="icon-eye" style="font-size:.7rem;"></span> <?= $p['views'] ?> · <?= timeAgo($p['created_at']) ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:40px 0;">
      <div class="empty-icon"><span class="icon-box"></span></div>
      <div class="empty-title">Chưa có sản phẩm nào</div>
      <?php if ($isMe): ?>
      <a href="index.php?page=post-product" class="btn btn-primary" style="margin-top:12px;"><span class="icon-plus"></span> Đăng bán ngay</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /prof-content -->
</div><!-- /prof-wrap -->
