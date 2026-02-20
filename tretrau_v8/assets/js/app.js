// ================================================================
// APP.JS — Main JavaScript: Toast, Modal, Lightbox, Dropdown, etc.
// ================================================================

// ── TOAST SYSTEM ──────────────────────────────────────────────
const Toast = (() => {
  let wrap;
  function getWrap() {
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      document.body.appendChild(wrap);
    }
    return wrap;
  }

  function show(msg, type = 'info', duration = 3500) {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    // Icon
    const icons = { success: 'icon-check', error: 'icon-x', info: 'icon-activity' };
    t.innerHTML = `<span class="${icons[type] || 'icon-activity'}"></span><span>${msg}</span>`;
    getWrap().appendChild(t);
    setTimeout(() => {
      t.style.animation = 'fadeOut 0.3s ease forwards';
      t.addEventListener('animationend', () => t.remove());
    }, duration);
  }

  return { show, success: m => show(m, 'success'), error: m => show(m, 'error'), info: m => show(m, 'info') };
})();

// ── API HELPER ─────────────────────────────────────────────────
async function api(action, data = {}, files = null) {
  const form = new FormData();
  form.append('action', action);
  // Add CSRF if available
  const csrf = document.querySelector('meta[name="csrf"]')?.content;
  if (csrf) form.append('csrf', csrf);
  for (const [k, v] of Object.entries(data)) form.append(k, v);
  if (files) for (const [k, f] of Object.entries(files)) form.append(k, f);

  try {
    const res = await fetch('api.php', { method: 'POST', body: form });
    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch(e) {
      console.error('Non-JSON from server:', text.substring(0, 500));
      throw new Error('Lỗi server. Vui lòng thử lại.');
    }
    if (json.error) throw new Error(json.error);
    return json;
  } catch (e) {
    throw e;
  }
}

// ── LIGHTBOX ───────────────────────────────────────────────────
function openLightbox(src) {
  const overlay = document.createElement('div');
  overlay.className = 'lightbox-overlay';
  overlay.innerHTML = `
    <img src="${src}" class="lightbox-img" alt="">
    <button class="lightbox-close"><span class="icon-x"></span></button>
  `;
  overlay.addEventListener('click', e => {
    if (e.target === overlay || e.target.closest('.lightbox-close')) overlay.remove();
  });
  document.body.appendChild(overlay);
  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', esc); }
  });
}

// Auto-bind lightbox to images with data-lightbox
document.addEventListener('click', e => {
  const img = e.target.closest('[data-lightbox]');
  if (img) openLightbox(img.dataset.lightbox || img.src);
});

// ── DROPDOWN USER MENU ─────────────────────────────────────────
document.addEventListener('click', e => {
  const trigger = e.target.closest('[data-dropdown-trigger]');
  const dropdowns = document.querySelectorAll('.user-dropdown');

  if (trigger) {
    e.stopPropagation();
    const target = document.getElementById(trigger.dataset.dropdownTrigger);
    const isOpen = target?.classList.contains('show');
    dropdowns.forEach(d => d.classList.remove('show'));
    if (!isOpen && target) target.classList.add('show');
  } else {
    dropdowns.forEach(d => d.classList.remove('show'));
  }
});

// ── MODAL ──────────────────────────────────────────────────────
function openModal(html) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `<div class="modal">${html}</div>`;
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.remove();
  });
  document.body.appendChild(overlay);
  return overlay;
}

function closeModal() {
  document.querySelector('.modal-overlay')?.remove();
}

// ── USER PROFILE POPUP ─────────────────────────────────────────
async function showUserProfile(userId) {
  try {
    const res = await fetch(`api.php?action=get_user_profile&user_id=${userId}`);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { console.error('Profile parse err:', text.substring(0,200)); return; }
    if (data.error) { Toast.error(data.error); return; }
    const u = data.user;
    const currentUserId = window.CURRENT_USER_ID;
    const isMe = currentUserId && parseInt(currentUserId) === parseInt(userId);

    const tickBig = getRoleBadgeHtml(u.role);
    const roleLabelMap = { admin: 'Admin', premium: 'Premium', user: 'Thành viên', new: 'Mới' };
    const roleColorMap = { admin: '#e8192c', premium: '#1877f2', user: '#555', new: '#888' };
    const roleColor = roleColorMap[u.role] || '#555';

    const html = `
      <div class="pcard">
        <div class="pcard-banner">
          <div class="pcard-banner-glow" style="--rc:${roleColor}"></div>
        </div>
        <div class="pcard-body">
          <div class="pcard-top">
            <div class="pcard-av-wrap">
              <img src="${u.avatar || 'assets/default-avatar.png'}" class="pcard-av" alt="">
              ${tickBig ? `<div class="pcard-tick">${tickBig}</div>` : ''}
            </div>
            <div class="pcard-role-pill" style="background:${roleColor}18;color:${roleColor};border-color:${roleColor}40;">
              ${roleLabelMap[u.role] || u.role}
            </div>
          </div>

          <div class="pcard-name">${escHtml(u.username)}</div>
          ${u.bio ? `<div class="pcard-bio">${escHtml(u.bio)}</div>` : ''}

          <div class="pcard-stats">
            <div class="pcard-stat">
              <span class="pcard-stat-val">${u.product_count}</span>
              <span class="pcard-stat-lbl">sản phẩm</span>
            </div>
            <div class="pcard-stat-div"></div>
            <div class="pcard-stat">
              <span class="pcard-stat-val">${formatDate(u.created_at)}</span>
              <span class="pcard-stat-lbl">tham gia</span>
            </div>
          </div>

          <div class="pcard-actions">
            ${!isMe ? `
              <button class="pcard-btn pcard-btn-msg" onclick="startDM(${u.id}, '${escHtml(u.username)}'); closeModal();">
                <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M2 4a2 2 0 012-2h12a2 2 0 012 2v9a2 2 0 01-2 2H6l-4 3V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                Nhắn tin
              </button>
              <button class="pcard-btn pcard-btn-report" onclick="reportUser(${u.id}); closeModal();">
                <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M10 3L3 17h14L10 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 9v4M10 14.5v.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                Báo cáo
              </button>
            ` : `
              <a href="index.php?page=account" class="pcard-btn pcard-btn-msg">
                <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M14.5 3.5l2 2-9 9H6v-1.5l9-9z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                Chỉnh sửa
              </a>
            `}
            <a href="index.php?page=profile&id=${u.id}" class="pcard-btn pcard-btn-ghost">
              <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              Trang cá nhân
            </a>
          </div>
        </div>
      </div>
    `;
    openModal(`<div class="pcard-modal-wrap">${html}</div>`);
  } catch(e) {
    Toast.error('Không thể tải hồ sơ');
  }
}

function getRoleBadgeHtml(role) {
  if (role === 'admin') {
    return `<span class="verified-tick admin-tick" title="Admin">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="12" fill="#e8192c"/>
        <path d="M7 12.5l3.5 3.5 6.5-7" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>`;
  }
  if (role === 'premium') {
    return `<span class="verified-tick premium-tick" title="Premium">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="12" fill="#1877f2"/>
        <path d="M7 12.5l3.5 3.5 6.5-7" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>`;
  }
  return '';
}

function getRoleBadgeSmall(role) {
  if (role === 'admin') return `<span class="tick-sm admin-tick" title="Admin"><svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="#e8192c"/><path d="M4.5 8.3l2.3 2.3 4.5-4.6" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>`;
  if (role === 'premium') return `<span class="tick-sm premium-tick" title="Premium"><svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="#1877f2"/><path d="M4.5 8.3l2.3 2.3 4.5-4.6" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>`;
  return '';
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = s; return d.innerHTML;
}

function formatDate(str) {
  const d = new Date(str);
  return d.toLocaleDateString('vi-VN', { year: 'numeric', month: 'short' });
}

// ── REPORT USER ────────────────────────────────────────────────
function reportUser(userId) {
  const html = `
    <div class="modal-title"><span class="icon-flag"></span> Báo cáo người dùng</div>
    <div class="form-group">
      <label class="form-label">Lý do báo cáo</label>
      <textarea class="form-input" id="report-reason" placeholder="Mô tả vấn đề..."></textarea>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal()">Hủy</button>
      <button class="btn btn-primary" onclick="submitReport(${userId})">Gửi báo cáo</button>
    </div>
  `;
  openModal(html);
}

async function submitReport(userId) {
  const reason = document.getElementById('report-reason').value.trim();
  if (!reason) { Toast.error('Vui lòng nhập lý do'); return; }
  try {
    await api('report_user', { target_user_id: userId, reason });
    Toast.success('Đã gửi báo cáo');
    closeModal();
  } catch(e) { Toast.error(e.message); }
}

// ── DM CHAT ────────────────────────────────────────────────────
function startDM(userId, username) {
  window.location.href = `index.php?page=chat&dm=${userId}&name=${encodeURIComponent(username)}`;
}

// ── AVATAR POPUP IN CHAT ───────────────────────────────────────
document.addEventListener('click', e => {
  // Close existing popups
  const existing = document.querySelector('.avatar-popup');

  const avatarEl = e.target.closest('[data-user-popup]');
  if (avatarEl) {
    e.stopPropagation();
    if (existing) { existing.remove(); return; }
    const userId = avatarEl.dataset.userPopup;
    const username = avatarEl.dataset.username || '';
    const popup = document.createElement('div');
    popup.className = 'avatar-popup';
    popup.innerHTML = `
      <button onclick="showUserProfile(${userId})"><span class="icon-user"></span> Hồ sơ</button>
      <button onclick="startDM(${userId}, '${escHtml(username)}')"><span class="icon-chat"></span> Nhắn tin</button>
      <button class="danger" onclick="reportUser(${userId})"><span class="icon-flag"></span> Báo cáo</button>
    `;
    const rect = avatarEl.getBoundingClientRect();
    popup.style.position = 'fixed';
    popup.style.top = (rect.bottom + 6) + 'px';
    popup.style.left = Math.min(rect.left, window.innerWidth - 175) + 'px';
    document.body.appendChild(popup);
  } else if (existing && !e.target.closest('.avatar-popup')) {
    existing.remove();
  }
});

// ── FORM SUBMISSION HELPER ─────────────────────────────────────
function handleFormSubmit(formEl, successMsg, redirect) {
  formEl.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = formEl.querySelector('[type=submit]');
    const origText = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;margin:0;"></span>'; }

    try {
      const data = {};
      const files = {};
      new FormData(formEl).forEach((val, key) => {
        if (val instanceof File) { if (val.size) files[key] = val; }
        else data[key] = val;
      });
      const action = formEl.dataset.action || data.action;
      delete data.action;
      const res = await api(action, data, Object.keys(files).length ? files : null);

      if (res.redirect) window.location.href = res.redirect;
      else if (successMsg) Toast.success(successMsg);
    } catch(e) {
      Toast.error(e.message);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = origText; }
    }
  });
}

// ── AUTO-GROW TEXTAREA ─────────────────────────────────────────
document.addEventListener('input', e => {
  const el = e.target;
  if (el.tagName === 'TEXTAREA' && el.classList.contains('chat-textarea')) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  }
});

// ── SEARCH DEBOUNCE ────────────────────────────────────────────
function debounce(fn, ms) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// ── INIT ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Auto-attach forms with data-action
  document.querySelectorAll('form[data-action]').forEach(f => {
    const successMsg = f.dataset.success;
    handleFormSubmit(f, successMsg);
  });

  // Image preview on file input
  document.querySelectorAll('[data-preview-target]').forEach(input => {
    input.addEventListener('change', () => {
      const target = document.getElementById(input.dataset.previewTarget);
      const file = input.files[0];
      if (!file || !target) return;
      const reader = new FileReader();
      reader.onload = e => { target.src = e.target.result; target.style.display = 'block'; };
      reader.readAsDataURL(file);
    });
  });
});
