<?php
$db = getDB();
$csrf = generateCSRF();
$canChangeUsername = !$user['username_changed_at'] || (strtotime($user['username_changed_at']) < time() - 604800);
$nextChangeDate = $user['username_changed_at'] ? date('d/m/Y H:i', strtotime($user['username_changed_at']) + 604800) : null;
$stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM products WHERE user_id=? GROUP BY status");
$stmt->execute([$user['id']]);
$postStats = [];
foreach ($stmt->fetchAll() as $row) $postStats[$row['status']] = (int)$row['cnt'];
$limit = getDailyPostLimit($user);
$todayCount = getTodayPostCount($user['id']);
$myPosts = $db->prepare("SELECT * FROM products WHERE user_id=? ORDER BY created_at DESC LIMIT 8");
$myPosts->execute([$user['id']]);
$myPostList = $myPosts->fetchAll();
$roleColor = match($user['role']) { 'admin' => '#e8192c', 'premium' => '#1877f2', default => '#555' };
$roleName  = match($user['role']) { 'admin' => 'Admin', 'premium' => 'Premium', 'user' => 'Member', default => 'New' };
?>
<style>
/* ── ACCOUNT PAGE ── */
.acc-page-title {
  font-size: 1.3rem; font-weight: 800; letter-spacing: -0.02em;
  margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
}
.acc-page-title svg { color: var(--text-muted); }

.acc-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
}
@media(max-width:768px){ .acc-grid { grid-template-columns: 1fr; } }

/* ── ACC CARD ── */
.acc-card {
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: 18px;
  overflow: hidden;
}
.acc-card-hdr {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  font-size: 0.72rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.09em;
  color: var(--text-muted);
  display: flex; align-items: center; gap: 8px;
}
.acc-card-hdr svg { flex-shrink: 0; }
.acc-card-body { padding: 18px; }

/* Avatar upload */
.acc-av-block {
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
.acc-av-label {
  position: relative; cursor: pointer; flex-shrink: 0;
}
.acc-av-img {
  width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
  border: 2.5px solid <?= $roleColor ?>;
  box-shadow: 0 0 0 3px color-mix(in srgb, <?= $roleColor ?> 18%, transparent);
  display: block; transition: opacity .15s;
}
.acc-av-label:hover .acc-av-img { opacity: 0.75; }
.acc-av-overlay {
  position: absolute; inset: 0; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .15s;
  background: rgba(0,0,0,0.55); color: #fff; font-size: 1rem;
  pointer-events: none;
}
.acc-av-label:hover .acc-av-overlay { opacity: 1; }
.acc-av-info { flex: 1; min-width: 0; }
.acc-av-name { font-weight: 800; font-size: 1rem; }
.acc-av-role {
  display: inline-flex; align-items: center; gap: 4px; margin-top: 4px;
  padding: 3px 8px;
  background: color-mix(in srgb, <?= $roleColor ?> 14%, transparent);
  border: 1px solid color-mix(in srgb, <?= $roleColor ?> 36%, transparent);
  color: <?= $roleColor ?>;
  border-radius: 999px; font-size: 0.66rem; font-weight: 800; letter-spacing: 0.07em;
  text-transform: uppercase;
}
.acc-av-hint { font-size: 0.7rem; color: var(--text-muted); margin-top: 5px; }

/* Form elements */
.acc-form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.acc-form-group:last-of-type { margin-bottom: 0; }
.acc-label {
  font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.07em;
}
.acc-input {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text-primary);
  padding: 10px 13px;
  font-size: 0.88rem;
  outline: none;
  transition: border-color .15s;
  font-family: inherit;
  width: 100%; box-sizing: border-box;
}
.acc-input:focus { border-color: var(--border-focus); }
.acc-input:disabled { opacity: 0.4; cursor: not-allowed; }
.acc-hint { font-size: 0.7rem; color: var(--text-muted); }
.acc-submit {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 18px; margin-top: 16px;
  background: var(--accent); color: var(--text-inverse);
  border: none; border-radius: 11px;
  font-size: 0.84rem; font-weight: 700; cursor: pointer;
  transition: all .15s; letter-spacing: 0.01em;
}
.acc-submit:hover { opacity: .86; transform: translateY(-1px); }

/* Stats boxes */
.acc-stats-3 {
  display: grid; grid-template-columns: repeat(3,1fr); gap: 10px;
  margin-bottom: 18px;
}
.acc-stat-box {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px; padding: 14px 10px; text-align: center;
}
.acc-stat-n { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.03em; }
.acc-stat-l { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.07em; margin-top: 2px; }

/* Info rows */
.acc-info-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 0; border-bottom: 1px solid var(--border);
  gap: 12px;
}
.acc-info-row:last-child { border-bottom: none; padding-bottom: 0; }
.acc-info-lbl { font-size: 0.78rem; color: var(--text-muted); flex-shrink: 0; }
.acc-info-val { font-size: 0.84rem; font-weight: 600; text-align: right; }

/* Post list */
.acc-post-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 10px; margin-bottom: 6px;
  transition: background .12s;
}
.acc-post-item:hover { background: var(--surface-3); }
.acc-post-thumb {
  width: 40px; height: 40px; border-radius: 8px; object-fit: cover; flex-shrink: 0;
}
.acc-post-info { flex: 1; min-width: 0; }
.acc-post-title {
  font-size: 0.82rem; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.acc-post-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }
.acc-post-status {
  font-size: 0.65rem; padding: 2px 7px; border-radius: 99px;
  font-weight: 700; flex-shrink: 0; white-space: nowrap;
}

/* Upgrade card */
.acc-upgrade-card {
  background: linear-gradient(135deg, #0f1b2d 0%, #0a0a0a 60%, #0d1520 100%);
  border: 1px solid rgba(24,119,242,0.3);
  border-radius: 18px; padding: 20px;
  position: relative; overflow: hidden;
}
.acc-upgrade-glow {
  position: absolute; top: -40px; right: -40px;
  width: 180px; height: 180px;
  background: radial-gradient(circle, rgba(24,119,242,0.2), transparent 70%);
  pointer-events: none;
}
.acc-upgrade-title {
  font-size: 0.95rem; font-weight: 800; margin-bottom: 6px;
  display: flex; align-items: center; gap: 8px;
}
.acc-upgrade-desc {
  font-size: 0.81rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 14px;
}
.acc-upgrade-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 18px;
  background: #1877f2; color: #fff;
  border: none; border-radius: 11px;
  font-size: 0.83rem; font-weight: 700; cursor: pointer;
  transition: all .15s; letter-spacing: 0.01em;
}
.acc-upgrade-btn:hover { background: #1466d8; transform: translateY(-1px); }
</style>

<div class="acc-page-title">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.5" stroke="currentColor" stroke-width="1.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
  Tài khoản
</div>

<div class="acc-grid">

  <!-- CỘT TRÁI -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- CHỈNH SỬA HỒ SƠ -->
    <div class="acc-card">
      <div class="acc-card-hdr">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M10 2l2 2-7 7H3v-2l7-7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
        Thông tin cá nhân
      </div>
      <div class="acc-card-body">
        <form id="acc-form" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">

          <div class="acc-av-block">
            <label class="acc-av-label">
              <img src="<?= htmlspecialchars(getAvatarUrl($user['avatar'])) ?>" class="acc-av-img" id="av-preview" alt="">
              <div class="acc-av-overlay"><span class="icon-image"></span></div>
              <input type="file" name="avatar" accept="image/*" onchange="previewAv(this)" style="position:absolute;inset:0;opacity:0;cursor:pointer;border-radius:50%;">
            </label>
            <div class="acc-av-info">
              <div class="acc-av-name"><?= htmlspecialchars($user['username']) ?></div>
              <div class="acc-av-role"><?= $roleName ?></div>
              <div class="acc-av-hint">Nhấp vào ảnh để thay · Max 5MB</div>
            </div>
          </div>

          <div class="acc-form-group">
            <label class="acc-label">
              Username
              <?php if (!$canChangeUsername): ?><span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted);font-size:0.7rem;"> — đổi được vào <?= $nextChangeDate ?></span><?php endif; ?>
            </label>
            <input type="text" name="username" class="acc-input"
              value="<?= htmlspecialchars($user['username']) ?>"
              <?= !$canChangeUsername ? 'disabled' : '' ?>
              pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="30">
            <span class="acc-hint">Thay đổi tối đa 1 lần / tuần</span>
          </div>

          <div class="acc-form-group">
            <label class="acc-label">Giới thiệu</label>
            <textarea name="bio" class="acc-input" rows="3" maxlength="300"
              placeholder="Viết gì đó về bản thân..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            <span class="acc-hint">Tối đa 300 ký tự</span>
          </div>

          <button type="submit" class="acc-submit">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M2.5 7.5l3 3 6-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Lưu thay đổi
          </button>
        </form>
      </div>
    </div>

    <!-- THÔNG TIN TÀI KHOẢN -->
    <div class="acc-card">
      <div class="acc-card-hdr">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="3.5" width="11" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5 3.5V3a2 2 0 014 0v.5" stroke="currentColor" stroke-width="1.3"/></svg>
        Thông tin tài khoản
      </div>
      <div class="acc-card-body">
        <div class="acc-info-row">
          <span class="acc-info-lbl">Email</span>
          <span class="acc-info-val" style="font-size:0.8rem;"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="acc-info-row">
          <span class="acc-info-lbl">Loại tài khoản</span>
          <span class="acc-info-val">
            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;background:color-mix(in srgb,<?= $roleColor ?> 14%,transparent);border:1px solid color-mix(in srgb,<?= $roleColor ?> 35%,transparent);color:<?= $roleColor ?>;border-radius:999px;font-size:0.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;">
              <?= $roleName ?>
            </span>
          </span>
        </div>
        <div class="acc-info-row">
          <span class="acc-info-lbl">Trạng thái</span>
          <span class="acc-info-val" style="color:#4ade80;display:flex;align-items:center;gap:4px;">
            <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="currentColor"/></svg>
            Hoạt động
          </span>
        </div>
        <div class="acc-info-row">
          <span class="acc-info-lbl">Ngày tham gia</span>
          <span class="acc-info-val"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
        </div>
        <div class="acc-info-row">
          <span class="acc-info-lbl">Đăng nhập cuối</span>
          <span class="acc-info-val"><?= $user['last_login'] ? timeAgo($user['last_login']) : 'Lần đầu' ?></span>
        </div>
        <div class="acc-info-row">
          <span class="acc-info-lbl">Đăng hôm nay</span>
          <span class="acc-info-val">
            <?= $todayCount ?> / <?= $limit ?>
            <span style="font-size:0.7rem;color:var(--text-muted);font-weight:400;">bài</span>
          </span>
        </div>
        <?php if ($user['role'] === 'new'): ?>
        <?php $daysLeft = max(0, 7 - floor((time() - strtotime($user['created_at'])) / 86400)); ?>
        <div style="margin-top:12px;padding:11px 13px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;font-size:0.78rem;color:var(--text-secondary);display:flex;gap:8px;align-items:flex-start;">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="flex-shrink:0;margin-top:1px;color:var(--text-muted);"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M7 4.5V7l1.5 1.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
          <span>Tài khoản tự nâng lên <strong>Member</strong> sau 7 ngày. Còn <strong><?= $daysLeft ?> ngày</strong>.</span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CỘT PHẢI -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- THỐNG KÊ -->
    <div class="acc-card">
      <div class="acc-card-hdr">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="7.5" width="2.5" height="5" rx="0.7" stroke="currentColor" stroke-width="1.2"/><rect x="5.75" y="4.5" width="2.5" height="8" rx="0.7" stroke="currentColor" stroke-width="1.2"/><rect x="10" y="1.5" width="2.5" height="11" rx="0.7" stroke="currentColor" stroke-width="1.2"/></svg>
        Thống kê bài đăng
      </div>
      <div class="acc-card-body">
        <div class="acc-stats-3">
          <div class="acc-stat-box">
            <div class="acc-stat-n" style="color:#4ade80;"><?= $postStats['active'] ?? 0 ?></div>
            <div class="acc-stat-l">Đang bán</div>
          </div>
          <div class="acc-stat-box">
            <div class="acc-stat-n" style="color:#60a5fa;"><?= $postStats['sold'] ?? 0 ?></div>
            <div class="acc-stat-l">Đã bán</div>
          </div>
          <div class="acc-stat-box">
            <div class="acc-stat-n" style="color:var(--text-muted);"><?= $postStats['deleted'] ?? 0 ?></div>
            <div class="acc-stat-l">Đã xóa</div>
          </div>
        </div>

        <?php if ($myPostList): ?>
        <div style="font-size:0.68rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.09em;margin-bottom:10px;">Bài đăng gần đây</div>
        <?php foreach ($myPostList as $p): ?>
        <a href="index.php?page=product&id=<?= $p['id'] ?>" class="acc-post-item" style="text-decoration:none;color:inherit;">
          <img src="<?= htmlspecialchars($p['image']) ?>" class="acc-post-thumb" alt="">
          <div class="acc-post-info">
            <div class="acc-post-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="acc-post-meta"><?= formatPrice($p['price'],$p['currency']) ?> · <?= timeAgo($p['created_at']) ?></div>
          </div>
          <span class="acc-post-status" style="<?= $p['status']==='active' ? 'background:rgba(74,222,128,.12);color:#4ade80;' : ($p['status']==='deleted' ? 'background:rgba(100,100,100,.12);color:#666;' : 'background:rgba(96,165,250,.12);color:#60a5fa;') ?>">
            <?= match($p['status']) { 'active'=>'Đang bán','sold'=>'Đã bán',default=>'Đã xóa' } ?>
          </span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- UPGRADE -->
    <?php if ($user['role'] !== 'premium' && $user['role'] !== 'admin'): ?>
    <div class="acc-upgrade-card">
      <div class="acc-upgrade-glow"></div>
      <div class="acc-upgrade-title">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1.5L9.8 6H15l-4 3 1.5 5L8 11.5 3.5 14 5 9 1 6h5.2L8 1.5z" fill="#1877f2" stroke="#1877f2" stroke-width="0.5" stroke-linejoin="round"/></svg>
        Nâng cấp Premium
      </div>
      <div class="acc-upgrade-desc">
        Premium cho phép đăng tới <strong><?= PREMIUM_ACC_DAILY_POST_LIMIT ?> bài/ngày</strong> thay vì <?= NEW_ACC_DAILY_POST_LIMIT ?> bài hiện tại. Ưu tiên hiển thị và tick xanh xác nhận.
      </div>
      <button class="acc-upgrade-btn" onclick="buyPremium()">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M7 1L8.5 5.5H13l-3.5 2.5 1.5 4.5L7 10 3.5 12.5l1.5-4.5L1.5 5.5H6L7 1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
        Nâng cấp ngay
      </button>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function previewAv(input) {
  const file = input.files[0]; if (!file) return;
  const r = new FileReader();
  r.onload = e => document.getElementById('av-preview').src = e.target.result;
  r.readAsDataURL(file);
}
document.getElementById('acc-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  const orig = btn.innerHTML; btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;margin:0 auto;"></div>';
  const fd = new FormData(e.target); fd.append('action','update_account');
  try {
    const res = await fetch('api.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    Toast.success('Đã lưu thay đổi!');
    setTimeout(() => location.reload(), 900);
  } catch(err) { Toast.error(err.message); }
  finally { btn.disabled = false; btn.innerHTML = orig; }
});
async function buyPremium() {
  try {
    const form = new FormData(); form.append('action','buy_premium');
    const res = await fetch('api.php',{method:'POST',body:form});
    const data = await res.json();
    if (data.error) { Toast.error(data.error); return; }
    if (data.redirect) window.location.href = data.redirect;
  } catch(e) { Toast.error('Lỗi kết nối'); }
}
</script>
