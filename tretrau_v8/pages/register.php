<?php
if ($user) { header('Location: index.php'); exit; }
$captchaQ = getCaptchaQuestion();
$csrf = generateCSRF();
?>
<style>
.auth-wrap { min-height:calc(100vh - 60px); display:flex; align-items:center; justify-content:center; padding:var(--sp-lg); }
.auth-card { width:100%; max-width:420px; }
.auth-logo { text-align:center; margin-bottom:var(--sp-xl); }
.auth-logo .logo-mark { width:52px; height:52px; margin:0 auto var(--sp-sm); border-radius:var(--r-md); overflow:hidden; background:transparent; display:flex; align-items:center; justify-content:center; }
.auth-title { font-size:1.4rem; font-weight:800; text-align:center; margin-bottom:6px; }
.auth-sub { text-align:center; color:var(--text-secondary); font-size:.875rem; margin-bottom:var(--sp-xl); }
.auth-form { display:flex; flex-direction:column; gap:var(--sp-md); }
.auth-footer { text-align:center; margin-top:var(--sp-lg); font-size:.875rem; color:var(--text-secondary); }
.auth-footer a { color:var(--text-primary); font-weight:600; }
.captcha-box { background:var(--surface-2); border:1px solid var(--border); border-radius:var(--r-md); padding:10px 14px; display:flex; align-items:center; gap:12px; }
.captcha-question { font-size:1.1rem; font-weight:700; color:var(--text-primary); font-family:'Courier New',monospace; letter-spacing:.08em; flex:1; user-select:none; }
.captcha-input { width:80px; text-align:center; font-size:1rem; font-weight:700; }
.captcha-refresh { background:none; border:none; color:var(--text-muted); cursor:pointer; padding:4px; border-radius:6px; font-size:.9rem; flex-shrink:0; }
.captcha-refresh:hover { color:var(--text-primary); background:var(--surface-3); }
.pw-strength { height:3px; border-radius:2px; margin-top:4px; transition:all .3s; }
</style>

<div class="auth-wrap">
  <div class="auth-card card">
    <div class="card-body">
      <div class="auth-logo"><div class="logo-mark"><img src="assets/dragon-icon.jpg" alt="TreTrau" style="width:100%;height:100%;object-fit:cover;border-radius:6px;"></div></div>
      <h1 class="auth-title">Tạo tài khoản</h1>
      <p class="auth-sub">Tham gia cộng đồng <?= SITE_NAME ?></p>

      <form class="auth-form" id="reg-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-input" placeholder="Chỉ chữ cái, số, dấu _" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9_]+" autocomplete="username">
          <span class="form-hint">3-30 ký tự, chỉ gồm chữ cái, số và dấu gạch dưới</span>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-input" placeholder="example@email.com" required autocomplete="email">
        </div>
        <div class="form-group">
          <label class="form-label">Mật khẩu</label>
          <input type="password" name="password" id="pw" class="form-input" placeholder="Tối thiểu 6 ký tự" required minlength="6" autocomplete="new-password" oninput="checkPw(this.value)">
          <div class="pw-strength" id="pw-bar"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Xác nhận mật khẩu</label>
          <input type="password" name="password2" class="form-input" placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
        </div>

        <div class="form-group">
          <label class="form-label">Xác minh bảo mật</label>
          <div class="captcha-box">
            <div class="captcha-question" id="captcha-q"><?= htmlspecialchars($captchaQ) ?></div>
            <input type="number" name="captcha" class="form-input captcha-input" id="captcha-in" placeholder="?" required autocomplete="off">
            <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="Câu hỏi khác">
              <span class="icon-refresh"></span>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
          <span class="icon-user"></span> Tạo tài khoản
        </button>
      </form>

      <div class="auth-footer">Đã có tài khoản? <a href="index.php?page=login">Đăng nhập</a></div>
    </div>
  </div>
</div>

<script>
function checkPw(v) {
  const bar = document.getElementById('pw-bar');
  let s = 0, c = '';
  if (v.length >= 6) s++;
  if (v.length >= 10) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  if (s <= 1) c = '#e74c3c';
  else if (s <= 3) c = '#f39c12';
  else c = '#2ecc71';
  bar.style.background = c;
  bar.style.width = (s * 20) + '%';
}

async function refreshCaptcha() {
  try {
    const res = await fetch('api.php?action=get_captcha');
    const d = await res.json();
    if (d.q) {
      document.getElementById('captcha-q').textContent = d.q;
      document.getElementById('captcha-in').value = '';
    }
  } catch(e) {}
}

document.getElementById('reg-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  if (fd.get('password') !== fd.get('password2')) { Toast.error('Mật khẩu không khớp'); return; }
  const btn = e.target.querySelector('[type=submit]');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;margin:0 auto;"></div>';
  fd.append('action','register');
  try {
    const res = await fetch('api.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.error) { await refreshCaptcha(); throw new Error(data.error); }
    Toast.success('Tạo tài khoản thành công!');
    setTimeout(() => window.location.href = data.redirect || 'index.php', 800);
  } catch(err) {
    Toast.error(err.message);
    btn.disabled = false;
    btn.innerHTML = orig;
  }
});
</script>
