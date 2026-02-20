// ================================================================
// CHAT.JS — Real-time polling chat with Reply, Mobile support
// ================================================================

class ChatApp {
  constructor(config) {
    this.config = config;
    this.lastMsgId = 0;
    this.polling = null;
    this.selectedFile = null;
    this.replyTo = null;

    this.el = {
      messages:    document.getElementById('chat-messages'),
      textarea:    document.getElementById('chat-input'),
      sendBtn:     document.getElementById('chat-send'),
      fileInput:   document.getElementById('chat-file'),
      previewWrap: document.getElementById('chat-preview'),
      previewImg:  document.getElementById('chat-preview-img'),
      replyBar:    document.getElementById('chat-reply-bar'),
      replyText:   document.getElementById('chat-reply-text'),
      replyCancelBtn: document.getElementById('chat-reply-cancel'),
    };

    this.init();
  }

  init() {
    this.loadHistory();
    this.setupEvents();
    this.startPolling();
  }

  async loadHistory() {
    const msgs = await this.fetchMessages(0);
    msgs.forEach(m => this.renderMessage(m, false));
    this.scrollBottom();
  }

  startPolling() {
    this.polling = setInterval(async () => {
      const msgs = await this.fetchMessages(this.lastMsgId);
      if (msgs.length) {
        msgs.forEach(m => this.renderMessage(m, true));
        this.scrollBottom();
      }
    }, 1800);
  }

  stopPolling() { clearInterval(this.polling); }

  async fetchMessages(after) {
    const p = new URLSearchParams({
      action: 'get_messages',
      after,
      channel_id: this.config.channelId || 0,
      is_global:  this.config.isGlobal ? 1 : 0,
      is_dm:      this.config.isDm ? 1 : 0,
      peer_id:    this.config.peerId || 0,
    });
    try {
      const res = await fetch('api.php?' + p);
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch(e) { console.error('Non-JSON:', text.substring(0,300)); return []; }
      const msgs = data.messages || [];
      if (msgs.length) this.lastMsgId = Math.max(...msgs.map(m => m.id));
      return msgs;
    } catch (e) { return []; }
  }

  renderMessage(msg, animate = true) {
    if (document.getElementById('msg-' + msg.id)) return;

    const isMine = parseInt(msg.sender_id) === parseInt(this.config.currentUserId);
    const wrapper = document.createElement('div');
    wrapper.id = 'msg-' + msg.id;
    wrapper.className = 'msg-row ' + (isMine ? 'mine' : 'theirs');
    if (!animate) wrapper.style.animation = 'none';

    if (msg.type === 'system') {
      wrapper.innerHTML = '<div class="msg-system">' + escHtml(msg.content || '') + '</div>';
      this.el.messages.appendChild(wrapper);
      return;
    }

    if (parseInt(msg.recalled)) {
      wrapper.innerHTML = `
        <img src="${msg.avatar || 'assets/default-avatar.png'}" class="msg-avatar"
          data-user-popup="${msg.sender_id}" data-username="${escHtml(msg.username || '')}" alt="">
        <div class="msg-bubble">
          ${!isMine ? '<div class="msg-sender-name">' + escHtml(msg.username || '') + '</div>' : ''}
          <div class="msg-content"><span class="msg-recalled"><span class="icon-undo"></span> Tin nhắn đã bị thu hồi</span></div>
        </div>
      `;
      this.el.messages.appendChild(wrapper);
      return;
    }

    const tickHtml = (typeof getRoleBadgeSmall === 'function') ? getRoleBadgeSmall(msg.sender_role || '') : '';
    const avatarHtml = `<img src="${msg.avatar || 'assets/default-avatar.png'}" class="msg-avatar"
      data-user-popup="${msg.sender_id}" data-username="${escHtml(msg.username || '')}"
      onclick="if(typeof showUserProfile==='function') showUserProfile(${msg.sender_id})" alt="">`;

    let replyHtml = '';
    if (msg.reply_to && msg.reply_username) {
      const rc = msg.reply_type === 'image'
        ? '<span class="icon-image"></span> Hình ảnh'
        : escHtml((msg.reply_content || '').substring(0, 60));
      replyHtml = `<div class="msg-reply-preview" onclick="document.getElementById('msg-${msg.reply_to}')?.scrollIntoView({behavior:'smooth',block:'center'})">
        <div class="msg-reply-bar"></div>
        <div class="msg-reply-body"><span class="msg-reply-name">${escHtml(msg.reply_username)}</span><span class="msg-reply-content">${rc}</span></div>
      </div>`;
    }

    let contentHtml = '';
    if (msg.type === 'image' && msg.image) {
      contentHtml = `<div class="msg-content" style="padding:4px;">${replyHtml}<img src="${msg.image}" class="msg-image" data-lightbox="${msg.image}" alt=""></div>`;
    } else {
      contentHtml = `<div class="msg-content">${replyHtml}${escHtml(msg.content || '')}</div>`;
    }

    const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

    wrapper.innerHTML = `
      ${!isMine ? avatarHtml : ''}
      <div class="msg-bubble" data-msg-id="${msg.id}">
        ${!isMine && (this.config.isGlobal || this.config.isDm) ? '<div class="msg-sender-name">' + escHtml(msg.username || '') + tickHtml + '</div>' : ''}
        ${contentHtml}
        <div class="msg-meta"><span>${time}</span>${isMine ? '<span class="icon-check"></span>' : ''}</div>
      </div>
      ${isMine ? avatarHtml : ''}
    `;

    const bubble = wrapper.querySelector('.msg-bubble');
    if (bubble) {
      bubble.addEventListener('contextmenu', e => { e.preventDefault(); this.showContextMenu(e, msg, isMine); });
      let pressTimer;
      bubble.addEventListener('touchstart', () => { pressTimer = setTimeout(() => { const r = bubble.getBoundingClientRect(); this.showContextMenu({clientX: r.left + r.width/2, clientY: r.top}, msg, isMine); }, 600); }, {passive:true});
      bubble.addEventListener('touchend',   () => clearTimeout(pressTimer));
      bubble.addEventListener('touchmove',  () => clearTimeout(pressTimer));
    }

    this.el.messages.appendChild(wrapper);
  }

  showContextMenu(event, msg, isMine) {
    document.querySelector('.msg-ctx-menu')?.remove();
    const menu = document.createElement('div');
    menu.className = 'msg-ctx-menu';

    const items = [];
    if (!parseInt(msg.recalled)) {
      items.push({ icon: 'icon-reply', label: 'Trả lời', fn: () => this.setReply(msg) });
    }
    if (msg.image) {
      items.push({ icon: 'icon-eye', label: 'Xem ảnh', fn: () => openLightbox(msg.image) });
    }
    if (!parseInt(msg.recalled) && msg.content) {
      items.push({ icon: 'icon-copy', label: 'Sao chép', fn: () => navigator.clipboard?.writeText(msg.content) });
    }
    if (isMine && !parseInt(msg.recalled)) {
      const age = (Date.now() - new Date(msg.created_at).getTime()) / 1000;
      if (age < 3600) {
        items.push({ icon: 'icon-undo', label: 'Thu hồi', fn: () => this.recallMessage(msg.id) });
      }
      items.push({ icon: 'icon-trash', label: 'Xóa bên mình', fn: () => this.deleteMessage(msg.id), danger: true });
    }

    if (!items.length) return;
    menu.innerHTML = items.map(it =>
      `<button class="msg-ctx-item ${it.danger ? 'danger' : ''}"><span class="${it.icon}"></span> ${it.label}</button>`
    ).join('');

    const x = Math.min(event.clientX || 20, window.innerWidth - 190);
    const y = Math.max(Math.min(event.clientY || 100, window.innerHeight - items.length * 44 - 20), 60);
    menu.style.cssText = `position:fixed;left:${x}px;top:${y}px;z-index:9999;`;

    document.body.appendChild(menu);
    menu.querySelectorAll('button').forEach((btn, i) => {
      btn.addEventListener('click', () => { items[i].fn(); menu.remove(); });
    });
    setTimeout(() => {
      document.addEventListener('click', function h() { menu.remove(); document.removeEventListener('click', h); });
    }, 10);
  }

  setReply(msg) {
    this.replyTo = { id: msg.id, username: msg.username, content: msg.content, type: msg.type, image: msg.image };
    if (this.el.replyBar) this.el.replyBar.style.display = 'flex';
    if (this.el.replyText) {
      const preview = msg.type === 'image' ? '📷 Hình ảnh' : (msg.content || '').substring(0, 60);
      this.el.replyText.innerHTML = '<strong>' + escHtml(msg.username) + '</strong>: ' + escHtml(preview);
    }
    this.el.textarea?.focus();
  }

  clearReply() {
    this.replyTo = null;
    if (this.el.replyBar) this.el.replyBar.style.display = 'none';
    if (this.el.replyText) this.el.replyText.innerHTML = '';
  }

  async recallMessage(msgId) {
    try {
      const form = new FormData();
      form.append('action', 'recall_message');
      form.append('message_id', msgId);
      await fetch('api.php', { method: 'POST', body: form });
      const el = document.getElementById('msg-' + msgId);
      if (el) {
        const bubble = el.querySelector('.msg-content');
        if (bubble) bubble.innerHTML = '<span class="msg-recalled"><span class="icon-undo"></span> Tin nhắn đã bị thu hồi</span>';
      }
    } catch(e) { if(typeof Toast!=='undefined') Toast.error(e.message); }
  }

  async deleteMessage(msgId) {
    try {
      const form = new FormData();
      form.append('action', 'delete_message_self');
      form.append('message_id', msgId);
      await fetch('api.php', { method: 'POST', body: form });
      document.getElementById('msg-' + msgId)?.remove();
    } catch(e) { if(typeof Toast!=='undefined') Toast.error(e.message); }
  }

  async sendMessage() {
    const content = this.el.textarea?.value.trim() || '';
    if (!content && !this.selectedFile) return;

    const btn = this.el.sendBtn;
    if (btn) btn.disabled = true;

    try {
      const form = new FormData();
      form.append('action', 'send_message');
      form.append('channel_id', this.config.channelId || 0);
      form.append('receiver_id', this.config.peerId || 0);
      form.append('is_global', this.config.isGlobal ? 1 : 0);
      form.append('is_dm', this.config.isDm ? 1 : 0);
      if (this.replyTo) form.append('reply_to', this.replyTo.id);
      if (content) form.append('content', content);
      if (this.selectedFile) form.append('image', this.selectedFile);

      const res = await fetch('api.php', { method: 'POST', body: form });
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch(e) { throw new Error('Lỗi server, thử lại.'); }

      if (data.error) throw new Error(data.error);
      if (data.message) { this.renderMessage(data.message, true); this.scrollBottom(); }

      if (this.el.textarea) { this.el.textarea.value = ''; this.el.textarea.style.height = 'auto'; }
      this.clearReply();
      this.clearPreview();
    } catch(e) {
      if (typeof Toast !== 'undefined') Toast.error(e.message);
      else alert(e.message);
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  setupEvents() {
    this.el.sendBtn?.addEventListener('click', () => this.sendMessage());
    this.el.textarea?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }
    });
    this.el.fileInput?.addEventListener('change', () => {
      const file = this.el.fileInput.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        this.selectedFile = file;
        if (this.el.previewImg) this.el.previewImg.src = e.target.result;
        if (this.el.previewWrap) { this.el.previewWrap.style.display = 'block'; this.el.previewWrap.classList.add('has-file'); }
      };
      reader.readAsDataURL(file);
    });
    this.el.replyCancelBtn?.addEventListener('click', () => this.clearReply());
  }

  clearPreview() {
    this.selectedFile = null;
    if (this.el.fileInput) this.el.fileInput.value = '';
    if (this.el.previewWrap) { this.el.previewWrap.classList.remove('has-file'); this.el.previewWrap.style.display = 'none'; }
    if (this.el.previewImg) this.el.previewImg.src = '';
  }

  scrollBottom() {
    if (this.el.messages) this.el.messages.scrollTop = this.el.messages.scrollHeight;
  }
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = String(s); return d.innerHTML;
}
