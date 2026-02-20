<?php
$pid = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("SELECT p.*, u.username, u.avatar, u.bio, u.role, u.id as seller_id FROM products p JOIN users u ON u.id=p.user_id WHERE p.id=? AND p.status='active'");
$stmt->execute([$pid]);
$product = $stmt->fetch();

if (!$product) { ?>
<div class="empty-state">
  <div class="empty-icon"><span class="icon-box"></span></div>
  <div class="empty-title">Sản phẩm không tồn tại</div>
  <div class="empty-desc">Sản phẩm này đã bị xóa hoặc không tồn tại.</div>
  <a href="index.php" class="btn btn-primary">Về trang chủ</a>
</div>
<?php return; }

// Increment views
$db->prepare("UPDATE products SET views=views+1 WHERE id=?")->execute([$pid]);

$categories = ['electronics'=>'Điện tử','fashion'=>'Thời trang','home'=>'Nhà cửa','books'=>'Sách','sports'=>'Thể thao','games'=>'Game','other'=>'Khác'];
$isOwner = $user && (int)$user['id'] === (int)$product['seller_id'];
?>

<style>
.product-detail-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: var(--sp-xl);
  align-items: start;
  max-width: 1000px;
  margin: 0 auto;
}
.product-img-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  overflow: hidden;
  aspect-ratio: 4/3;
}
.product-img-wrap img {
  width:100%; height:100%; object-fit:cover; cursor:pointer;
  transition: transform var(--trans-slow);
}
.product-img-wrap img:hover { transform: scale(1.02); }
.product-detail-info { display:flex; flex-direction:column; gap: var(--sp-md); }
.product-detail-title { font-size:1.4rem; font-weight:800; line-height:1.2; }
.product-detail-price { font-size:1.8rem; font-weight:800; }
.product-detail-desc {
  color: var(--text-secondary);
  font-size:0.9rem;
  line-height:1.7;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  padding: var(--sp-md);
}
.product-meta-row { display:flex; gap: var(--sp-md); flex-wrap:wrap; }
.product-meta-item {
  display:flex; align-items:center; gap:6px;
  font-size:0.8rem; color: var(--text-muted);
}
.seller-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: var(--sp-md);
  display:flex; align-items:center; gap: var(--sp-md);
  cursor:pointer;
  transition: border-color var(--trans-fast);
  text-decoration:none; color:inherit;
}
.seller-card:hover { border-color: var(--border-strong); }
.seller-avatar {
  width: 44px; height:44px;
  border-radius: 50%;
  object-fit: cover;
}
.seller-name { font-weight:600; font-size:0.9rem; }
.seller-sub  { font-size:0.78rem; color: var(--text-muted); }
.buy-section { display:flex; flex-direction:column; gap: var(--sp-sm); }
@media (max-width: 768px) {
  .product-detail-grid { grid-template-columns: 1fr; }
  .product-detail-price { font-size: 1.4rem; }
}
</style>

<div style="margin-bottom: var(--sp-md);">
  <a href="javascript:history.back()" class="btn btn-ghost btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><polyline points="15 18 9 12 15 6"></polyline></svg>
    Quay lại
  </a>
</div>

<div class="product-detail-grid">
  <!-- LEFT: Images -->
  <div>
    <div class="product-img-wrap">
      <img src="<?= htmlspecialchars($product['image']) ?>"
           alt="<?= htmlspecialchars($product['title']) ?>"
           data-lightbox="<?= htmlspecialchars($product['image']) ?>">
    </div>
  </div>

  <!-- RIGHT: Info -->
  <div class="product-detail-info">
    <div>
      <span class="badge badge-user" style="margin-bottom:8px;"><?= htmlspecialchars($categories[$product['category']] ?? $product['category']) ?></span>
      <h1 class="product-detail-title"><?= htmlspecialchars($product['title']) ?></h1>
    </div>

    <div class="product-detail-price"><?= formatPrice($product['price'], $product['currency']) ?></div>

    <div class="product-meta-row">
      <div class="product-meta-item">
        <span class="icon-eye"></span> <?= number_format($product['views']) ?> lượt xem
      </div>
      <div class="product-meta-item">
        <span class="icon-clock"></span> <?= timeAgo($product['created_at']) ?>
      </div>
      <div class="product-meta-item">
        <span class="icon-tag"></span> #<?= $product['id'] ?>
      </div>
    </div>

    <!-- Buy button -->
    <div class="buy-section">
      <?php if (!$user): ?>
      <a href="index.php?page=login" class="btn btn-primary" style="width:100%; justify-content:center; padding:14px; font-size:1rem;">
        <span class="icon-lock"></span> Đăng nhập để liên hệ mua
      </a>
      <?php elseif ($isOwner): ?>
      <div style="display:flex; gap:8px;">
        <button class="btn btn-ghost" style="flex:1; justify-content:center;" onclick="confirmDelete()">
          <span class="icon-trash"></span> Xóa bài đăng
        </button>
      </div>
      <div class="form-hint">Đây là sản phẩm của bạn</div>
      <?php else: ?>
      <button class="btn btn-primary" id="buy-btn" style="width:100%; justify-content:center; padding:14px; font-size:1rem;">
        <span class="icon-cart"></span> Liên hệ mua
      </button>
      <?php endif; ?>
    </div>

    <!-- Seller card -->
    <div>
      <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:8px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Người bán</div>
      <div class="seller-card" onclick="showUserProfile(<?= $product['seller_id'] ?>)">
        <img src="<?= htmlspecialchars(getAvatarUrl($product['avatar'])) ?>" class="seller-avatar" alt="">
        <div>
          <div class="seller-name">
            <?= htmlspecialchars($product['username']) ?>
            <?= getRoleBadge($product['role']) ?>
          </div>
          <div class="seller-sub"><?= $product['bio'] ? htmlspecialchars(mb_substr($product['bio'],0,60)) : 'Không có giới thiệu' ?></div>
        </div>
      </div>
    </div>

    <!-- Description -->
    <div>
      <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:8px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Mô tả</div>
      <div class="product-detail-desc"><?= $product['description'] ?></div>
    </div>
  </div>
</div>

<script>
document.getElementById('buy-btn')?.addEventListener('click', async () => {
  const btn = document.getElementById('buy-btn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;margin:0 auto;"></div>';
  try {
    const fd = new FormData();
    fd.append('action','buy_product');
    fd.append('product_id', <?= $pid ?>);
    const res = await fetch('api.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    Toast.success('Đang mở kênh chat bảo mật...');
    setTimeout(() => window.location.href = data.redirect, 800);
  } catch(e) {
    Toast.error(e.message);
    btn.disabled = false;
    btn.innerHTML = '<span class="icon-cart"></span> Liên hệ mua';
  }
});

async function confirmDelete() {
  if (!confirm('Bạn chắc chắn muốn xóa sản phẩm này?')) return;
  const fd = new FormData();
  fd.append('action','delete_product');
  fd.append('product_id', <?= $pid ?>);
  try {
    const res = await fetch('api.php',{method:'POST',body:fd});
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    Toast.success('Đã xóa sản phẩm');
    setTimeout(() => window.location.href = 'index.php', 1000);
  } catch(e) { Toast.error(e.message); }
}
</script>
