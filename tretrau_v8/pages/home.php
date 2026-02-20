<?php
$db = getDB();
$search   = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$page_n   = max(1, (int)($_GET['p'] ?? 1));
$limit    = 12;
$offset   = ($page_n - 1) * $limit;

$where  = "WHERE p.status='active'";
$params = [];
if ($category) { $where .= " AND p.category=?"; $params[] = $category; }
if ($search)   { $where .= " AND (p.title LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("SELECT p.*, u.username, u.avatar FROM products p JOIN users u ON u.id=p.user_id $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$totalStmt = $db->prepare("SELECT COUNT(*) FROM products p $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$categories = ['electronics' => 'Điện tử', 'fashion' => 'Thời trang', 'home' => 'Nhà cửa', 'books' => 'Sách', 'sports' => 'Thể thao', 'games' => 'Game', 'other' => 'Khác'];
?>

<!-- CATEGORY FILTER -->
<div style="margin-bottom: var(--sp-lg);">
  <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
    <a href="index.php?page=home<?= $search ? '&search=' . urlencode($search) : '' ?>"
       class="btn btn-sm <?= !$category ? 'btn-primary' : 'btn-ghost' ?>">
      Tất cả
    </a>
    <?php foreach ($categories as $key => $label): ?>
    <a href="index.php?page=home?category=<?= $key ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
       class="btn btn-sm <?= $category === $key ? 'btn-primary' : 'btn-ghost' ?>">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- HEADER ROW -->
<div class="section-header">
  <div class="section-title">
    <?php if ($search): ?>
      Kết quả cho "<strong><?= htmlspecialchars($search) ?></strong>" — <?= $total ?> sản phẩm
    <?php elseif ($category): ?>
      <?= $categories[$category] ?? $category ?>
    <?php else: ?>
      Sản phẩm mới nhất
    <?php endif; ?>
  </div>
  <?php if ($user): ?>
  <a href="index.php?page=post-product" class="btn btn-primary btn-sm">
    <span class="icon-plus"></span> Đăng bán
  </a>
  <?php endif; ?>
</div>

<!-- PRODUCT GRID -->
<div id="product-grid-wrap">
<?php if ($products): ?>
<div class="product-grid">
  <?php foreach ($products as $p): ?>
  <a href="index.php?page=product&id=<?= $p['id'] ?>" class="product-card">
    <div class="product-thumb-wrap">
      <img src="<?= htmlspecialchars($p['image']) ?>" class="product-thumb" alt="" loading="lazy">
      <span class="product-category-badge"><?= htmlspecialchars($categories[$p['category']] ?? $p['category']) ?></span>
    </div>
    <div class="product-card-body">
      <div class="product-card-title"><?= htmlspecialchars($p['title']) ?></div>
      <div class="product-card-price"><?= formatPrice($p['price'], $p['currency']) ?></div>
      <div class="product-card-seller">
        <img src="<?= htmlspecialchars(getAvatarUrl($p['avatar'])) ?>" class="seller-mini-avatar" alt="">
        <?= htmlspecialchars($p['username']) ?> · <?= timeAgo($p['created_at']) ?>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div style="display:flex; justify-content:center; gap:8px; margin-top:var(--sp-xl);">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
  <a href="?page=home&p=<?= $i ?><?= $category ? '&category=' . urlencode($category) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
     class="btn btn-sm <?= $i === $page_n ? 'btn-primary' : 'btn-ghost' ?>">
    <?= $i ?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state">
  <div class="empty-icon"><span class="icon-box"></span></div>
  <div class="empty-title">Chưa có sản phẩm nào</div>
  <div class="empty-desc">
    <?= $search ? "Không tìm thấy sản phẩm nào khớp với \"$search\"" : 'Hãy là người đầu tiên đăng bán!' ?>
  </div>
  <?php if ($user): ?>
  <a href="index.php?page=post-product" class="btn btn-primary">
    <span class="icon-plus"></span> Đăng sản phẩm
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>
</div><!-- /product-grid-wrap -->
