<?php
if ($user) { header('Location: index.php'); exit; }
// Generate captcha
$captchaQ = getCaptchaQuestion();
$csrf = generateCSRF();
?>
<style>
.auth-wrap { min-height:calc(100vh - 60px); display:flex; align-items:center; justify-content:center; padding:var(--sp-lg); }
.auth-card { width:100%; max-width:400px; }
.auth-logo .logo-mark { width:52px; height:52px; margin:0 auto var(--sp-sm); border-radius:var(--r-md); overflow:hidden; background:transparent; display:flex; align-items:center; justify-content:center; }
.auth-title { font-size:1.4rem; font-weight:800; text-align:center; margin-bottom:6px; }
.auth-sub { text-align:center; color:var(--text-secondary); font-size:.875rem; margin-bottom:var(--sp-xl); }
.auth-form { display:flex; flex-direction:column; gap:var(--sp-md); }
.auth-footer { text-align:center; margin-top:var(--sp-lg); font-size:.875rem; color:var(--text-secondary); }
.auth-footer a { color:var(--text-primary); font-weight:600; }
.auth-logo { text-align:center; margin-bottom:var(--sp-xl); }

/* Captcha */
.captcha-box {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.captcha-question {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
  font-family: 'Courier New', monospace;
  letter-spacing: .08em;
  flex: 1;
  user-select: none;
  /* Make it harder to OCR */
  text-shadow: 0 0 1px rgba(255,255,255,.2);
  position: relative;
}
.captcha-question::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(45deg, transparent, transparent 3px, rgba(255,255,255,.03) 3px, rgba(255,255,255,.03) 4px);
  pointer-events: none;
}
.captcha-input {
  width: 80px;
  text-align: center;
  font-size: 1rem;
  font-weight: 700;
}
.captcha-refresh {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  font-size: .9rem;
  flex-shrink: 0;
}
.captcha-refresh:hover { color: var(--text-primary); background: var(--surface-3); }
</style>

<div class="auth-wrap">
  <div class="auth-card card">
    <div class="card-body">
      <div class="auth-logo">
        <div class="logo-mark"><img src="assets/dragon-icon.jpg" alt="TreTrau" style="width:100%;height:100%;object-fit:cover;border-radius:6px;"></div>
      </div>
      <h1 class="auth-title">Đăng nhập</h1>
      <p class="auth-sub">Chào mừng trở lại <?= SITE_NAME ?></p>

      <form class="auth-form" id="login-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="form-group">
          <label class="form-label">Username hoặc Email</label>
          <input type="text" name="identifier" class="form-input" placeholder="Nhập username hoặc email" required autocomplete="username">
        </div>
        <div class="form-group">
          <label class="form-label">Mật khẩu</label>
          <input type="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <!-- CAPTCHA -->
        <div class="form-group">
          <label class="form-label">Xác minh bảo mật</label>
          <div class="captcha-box">
            <div class="captcha-question" id="captcha-q" data-noise="true"><?= htmlspecialchars($captchaQ) ?></div>
            <input type="number" name="captcha" class="form-input captcha-input" id="captcha-in" placeholder="?" required autocomplete="off">
            <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="Câu hỏi khác">
              <span class="icon-refresh"></span>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
          <span class="icon-lock"></span> Đăng nhập
        </button>
      </form>

      <div class="auth-footer">
        Chưa có tài khoản? <a href="index.php?page=register">Đăng ký ngay</a>
      </div>
    </div>
  </div>
</div>

<script>
async function refreshCaptcha() {
  try {
    const res = await fetch('api.php?action=get_captcha');
    const d = await res.json();
    if (d.q) {
      document.getElementById('captcha-q').textContent = d.q;
      document.getElementById('captcha-in').value = '';
      document.getElementById('captcha-in').focus();
    }
  } catch(e) {}
}

document.getElementById('login-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;margin:0 auto;"></div>';
  const fd = new FormData(e.target);
  fd.append('action','login');
  try {
    const res = await fetch('api.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.error) {
      // Refresh captcha on error
      await refreshCaptcha();
      throw new Error(data.error);
    }
    window.location.href = data.redirect || 'index.php';
  } catch(err) {
    Toast.error(err.message);
    btn.disabled = false;
    btn.innerHTML = orig;
  }
});
</script>
