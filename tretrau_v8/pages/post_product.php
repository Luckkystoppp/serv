<?php
$limit = getDailyPostLimit($user);
$todayCount = getTodayPostCount($user['id']);
$remaining = $limit - $todayCount;
$canPost = $remaining > 0;
$csrf = generateCSRF();
$categories = ['electronics'=>'Điện tử','fashion'=>'Thời trang','home'=>'Nhà cửa','books'=>'Sách','sports'=>'Thể thao','games'=>'Game','other'=>'Khác'];
?>
<style>
.post-form-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: var(--sp-xl);
  align-items: start;
}
.post-preview-wrap {
  position: sticky;
  top: calc(var(--header-height) + 16px);
}
.post-preview-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  overflow: hidden;
}
.post-limit-bar {
  padding: var(--sp-md);
  background: var(--surface-2);
  border-radius: var(--r-md);
  border: 1px solid var(--border);
  margin-bottom: var(--sp-md);
  display: flex; align-items: center; gap: var(--sp-sm);
  font-size: 0.85rem; color: var(--text-secondary);
}
.post-limit-count { font-weight: 700; color: var(--text-primary); }
.image-upload-area {
  border: 2px dashed var(--border);
  border-radius: var(--r-lg);
  padding: var(--sp-xl);
  text-align: center;
  cursor: pointer;
  transition: all var(--trans-fast);
  position: relative;
}
.image-upload-area:hover, .image-upload-area.drag-over {
  border-color: var(--border-strong);
  background: var(--surface-2);
}
.image-upload-area input[type=file] {
  position: absolute; inset:0; opacity:0; cursor:pointer;
}
.upload-icon { font-size: 1.8rem; color: var(--text-muted); margin-bottom: 8px; }
.img-preview-box {
  position: relative;
  border-radius: var(--r-md);
  overflow: hidden;
  margin-top: var(--sp-sm);
}
.img-preview-box img { width:100%; max-height:240px; object-fit:cover; }
.img-preview-remove {
  position: absolute; top:8px; right:8px;
  width:28px; height:28px;
  background: rgba(0,0,0,0.7);
  border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; color:#fff; font-size:0.8rem;
  border:none;
}
@media (max-width: 768px) {
  .post-form-grid { grid-template-columns: 1fr; }
  .post-preview-wrap { position: static; }
}
</style>

<div style="margin-bottom: var(--sp-lg);">
  <h1 style="font-size:1.3rem; font-weight:800;">Đăng bán sản phẩm</h1>
  <p style="color:var(--text-secondary); font-size:0.875rem; margin-top:4px;">Điền thông tin chi tiết để sản phẩm được chú ý nhiều hơn</p>
</div>

<!-- Limit notice -->
<div class="post-limit-bar">
  <span class="icon-tag"></span>
  <?php if ($canPost): ?>
  Hôm nay bạn còn <span class="post-limit-count"><?= $remaining ?>/<?= $limit ?></span> lượt đăng
  <?php else: ?>
  <span style="color:var(--danger);">Bạn đã đạt giới hạn <?= $limit ?> bài hôm nay. Hãy thử lại vào ngày mai.</span>
  <?php endif; ?>
  <?php if ($user['role'] === 'new' || $user['role'] === 'user'): ?>
  <span style="margin-left:auto; font-size:0.75rem;"><a href="#" style="color:var(--text-secondary);">Nâng cấp Premium</a> để đăng nhiều hơn</span>
  <?php endif; ?>
</div>

<?php if (!$canPost): ?>
<div class="empty-state">
  <div class="empty-icon"><span class="icon-clock"></span></div>
  <div class="empty-title">Đã đạt giới hạn hôm nay</div>
  <div class="empty-desc">Tài khoản <?= ucfirst($user['role']) ?> có thể đăng tối đa <?= $limit ?> bài/ngày.</div>
  <a href="index.php" class="btn btn-primary">Về trang chủ</a>
</div>
<?php return; endif; ?>

<div class="post-form-grid">
  <!-- FORM -->
  <div>
    <form id="post-form" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="card">
        <div class="card-header">Thông tin sản phẩm</div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:var(--sp-md);">

          <div class="form-group">
            <label class="form-label">Tiêu đề *</label>
            <input type="text" name="title" id="field-title" class="form-input" placeholder="Nhập tiêu đề sản phẩm..." required maxlength="200" oninput="updatePreview()">
            <span class="form-hint">Tối đa 200 ký tự. Không dùng HTML hoặc ký tự đặc biệt.</span>
          </div>

          <div class="form-group">
            <label class="form-label">Giá *</label>
            <div style="display:flex;gap:8px;">
              <input type="number" name="price" id="field-price" class="form-input" placeholder="0" min="0" required oninput="updatePreview()">
              <select name="currency" class="form-input" style="max-width:100px;">
                <option value="VND">VND</option>
                <option value="USD">USD</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Danh mục</label>
            <select name="category" class="form-input">
              <?php foreach ($categories as $k=>$v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Hình ảnh sản phẩm *</label>
            <div class="image-upload-area" id="upload-area">
              <input type="file" name="image" id="img-input" accept="image/*" onchange="previewImage(this)">
              <div id="upload-placeholder">
                <div class="upload-icon"><span class="icon-image"></span></div>
                <div style="font-weight:600; margin-bottom:4px;">Kéo thả hoặc click để chọn ảnh</div>
                <div style="font-size:0.78rem; color:var(--text-muted);">JPEG, PNG, WebP, GIF — tối đa 5MB</div>
              </div>
              <div class="img-preview-box" id="img-preview-box" style="display:none;">
                <img id="img-preview" src="" alt="">
                <button type="button" class="img-preview-remove" onclick="clearImage()">
                  <span class="icon-x"></span>
                </button>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Mô tả sản phẩm *</label>
            <textarea name="description" id="field-desc" class="form-input" rows="6"
              placeholder="Mô tả chi tiết sản phẩm, tình trạng, đặc điểm nổi bật..."
              required oninput="updatePreview()"></textarea>
            <span class="form-hint">Tối đa 5000 ký tự. Không dùng script, style, hoặc link không an toàn.</span>
          </div>

        </div>
      </div>

      <button type="submit" id="submit-btn" class="btn btn-primary" style="width:100%; justify-content:center; padding:14px; font-size:1rem; margin-top:var(--sp-md);">
        <span class="icon-check"></span> Đăng sản phẩm
      </button>
    </form>
  </div>

  <!-- PREVIEW SIDEBAR -->
  <div class="post-preview-wrap">
    <div style="font-size:0.8rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Xem trước</div>
    <div class="post-preview-card">
      <div style="aspect-ratio:4/3; background:var(--surface-2); display:flex; align-items:center; justify-content:center;" id="preview-img-wrap">
        <span class="icon-image" style="font-size:2rem; color:var(--text-muted);"></span>
      </div>
      <div style="padding:14px;">
        <div id="preview-title" style="font-weight:600; margin-bottom:6px; color:var(--text-muted);">Tiêu đề sản phẩm</div>
        <div id="preview-price" style="font-size:1.1rem; font-weight:700;">--</div>
        <div style="display:flex; align-items:center; gap:6px; margin-top:8px; font-size:0.78rem; color:var(--text-muted);">
          <img src="<?= htmlspecialchars(getAvatarUrl($user['avatar'])) ?>" style="width:18px;height:18px;border-radius:50%;object-fit:cover;" alt="">
          <?= htmlspecialchars($user['username']) ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function previewImage(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('img-preview').src = e.target.result;
    document.getElementById('img-preview-box').style.display = 'block';
    document.getElementById('upload-placeholder').style.display = 'none';
    // Update sidebar preview
    document.getElementById('preview-img-wrap').innerHTML =
      `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;" alt="">`;
  };
  reader.readAsDataURL(file);
}

function clearImage() {
  document.getElementById('img-input').value = '';
  document.getElementById('img-preview-box').style.display = 'none';
  document.getElementById('upload-placeholder').style.display = 'block';
  document.getElementById('preview-img-wrap').innerHTML = '<span class="icon-image" style="font-size:2rem;color:var(--text-muted);"></span>';
}

function updatePreview() {
  const title = document.getElementById('field-title').value || 'Tiêu đề sản phẩm';
  const price = document.getElementById('field-price').value;
  document.getElementById('preview-title').textContent = title;
  document.getElementById('preview-title').style.color = title ? 'var(--text-primary)' : 'var(--text-muted)';
  document.getElementById('preview-price').textContent = price
    ? parseInt(price).toLocaleString('vi-VN') + '₫'
    : '--';
}

// Drag & drop
const area = document.getElementById('upload-area');
area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
area.addEventListener('drop', e => {
  e.preventDefault();
  area.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('img-input').files = dt.files;
    previewImage(document.getElementById('img-input'));
  }
});

// Form submit
document.getElementById('post-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('submit-btn');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;margin:0 auto;"></div>';

  const fd = new FormData(e.target);
  fd.append('action','post_product');
  try {
    const res = await fetch('api.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    Toast.success('Đăng sản phẩm thành công!');
    setTimeout(() => window.location.href = data.redirect, 800);
  } catch(err) {
    Toast.error(err.message);
    btn.disabled = false;
    btn.innerHTML = orig;
  }
});
</script>
