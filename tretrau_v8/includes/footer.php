<?php
$currentPage = $_GET['page'] ?? 'home';
$user = currentUser();
$sidebarPages = ['home','profile','account','post-product'];
?>
<?php if (in_array($currentPage, $sidebarPages)): ?>
  </main>
</div><!-- /page-layout-sidebar -->
<?php elseif (!in_array($currentPage, ['login','register','chat','global-chat'])): ?>
</div><!-- /page-layout -->
<?php endif; ?>

<!-- BOTTOM NAVIGATION (Mobile) -->
<?php if ($user && !in_array($currentPage, ['login','register'])): ?>
<?php
// Count unread DM messages
$db2 = getDB();
try {
  $unreadStmt = $db2->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_dm=1 AND read_at IS NULL AND recalled=0");
  $unreadStmt->execute([$user['id']]);
  $unreadDM = (int)$unreadStmt->fetchColumn();
} catch(Exception $e) {
  $unreadDM = 0;
}
// Count unread channel messages  
try {
  $unreadChanStmt = $db2->prepare("
    SELECT COUNT(*) FROM messages m
    JOIN chat_channels cc ON cc.id = m.channel_id
    WHERE (cc.buyer_id=? OR cc.seller_id=?)
      AND m.sender_id != ? AND m.recalled=0
      AND m.read_at IS NULL
      AND m.channel_id IS NOT NULL AND m.is_global=0 AND m.is_dm=0
  ");
  $unreadChanStmt->execute([$user['id'], $user['id'], $user['id']]);
  $unreadChan = (int)$unreadChanStmt->fetchColumn();
} catch(Exception $e) {
  $unreadChan = 0;
}
$totalUnread = $unreadDM + $unreadChan;
?>
<nav class="bottom-nav">
  <a href="index.php" class="bottom-nav-item <?= $currentPage==='home'?'active':'' ?>">
    <span class="bottom-nav-icon icon-home"></span>
    <span>Trang chủ</span>
  </a>
  <a href="index.php?page=post-product" class="bottom-nav-item <?= $currentPage==='post-product'?'active':'' ?>">
    <span class="bottom-nav-icon icon-plus"></span>
    <span>Đăng bán</span>
  </a>
  <a href="index.php?page=chat" class="bottom-nav-item <?= ($currentPage==='chat'||$currentPage==='global-chat')?'active':'' ?>" style="position:relative;">
    <span class="bottom-nav-icon icon-chat"></span>
    <?php if ($totalUnread > 0): ?>
    <span class="bottom-nav-badge"><?= $totalUnread > 99 ? '99+' : $totalUnread ?></span>
    <?php endif; ?>
    <span>Tin nhắn</span>
  </a>
  <a href="index.php?page=profile&id=<?= $user['id'] ?>" class="bottom-nav-item <?= $currentPage==='profile'?'active':'' ?>">
    <span class="bottom-nav-icon icon-user"></span>
    <span>Hồ sơ</span>
  </a>
</nav>
<?php endif; ?>

<!-- SITE FOOTER -->
<?php if (!in_array($currentPage, ['chat','global-chat'])): ?>
<footer class="site-footer">
  <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
  <div class="footer-links">
    <a href="#">Điều khoản</a>
    <a href="#">Bảo mật</a>
    <a href="#">Liên hệ</a>
  </div>
</footer>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/chat.js"></script>

<script>
// Global search — real-time AJAX filtering on home page
const _isHomePage = (new URLSearchParams(window.location.search).get('page') || 'home') === 'home';

function handleSearch(q) {
  clearTimeout(window._searchTimer);
  if (_isHomePage) {
    // Sync both search inputs
    const inputs = document.querySelectorAll('#global-search, #mobile-search-input');
    inputs.forEach(inp => { if (inp.value !== q) inp.value = q; });
    window._searchTimer = setTimeout(() => doAjaxSearch(q), 350);
  } else {
    if (!q.trim()) return;
    window._searchTimer = setTimeout(() => {
      window.location.href = 'index.php?page=home&search=' + encodeURIComponent(q);
    }, 500);
  }
}

async function doAjaxSearch(q) {
  const grid = document.getElementById('product-grid-wrap');
  if (!grid) return;
  const currentCategory = new URLSearchParams(window.location.search).get('category') || '';
  const params = new URLSearchParams({ action: 'get_products', search: q, category: currentCategory });
  try {
    const res = await fetch('api.php?' + params);
    const data = await res.json();
    if (data.html !== undefined) {
      grid.innerHTML = data.html;
    } else if (data.products !== undefined) {
      renderProductGrid(data.products, q, grid);
    }
  } catch(e) {}
}

function renderProductGrid(products, q, container) {
  if (!products || products.length === 0) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon"><span class="icon-box"></span></div><div class="empty-title">Không tìm thấy sản phẩm</div><div class="empty-desc">Không tìm thấy sản phẩm nào khớp với "${q || 'tìm kiếm của bạn'}"</div></div>`;
    return;
  }
  // Update section title
  const titleEl = document.querySelector('.section-title');
  if (titleEl) {
    if (q) titleEl.innerHTML = `Kết quả cho "<strong>${q}</strong>" — ${products.length} sản phẩm`;
    else titleEl.textContent = 'Sản phẩm mới nhất';
  }
  const cats = {electronics:'Điện tử',fashion:'Thời trang',home:'Nhà cửa',books:'Sách',sports:'Thể thao',games:'Game',other:'Khác'};
  container.innerHTML = '<div class="product-grid">' + products.map(p => `
    <a href="index.php?page=product&id=${p.id}" class="product-card">
      <div class="product-thumb-wrap">
        <img src="${p.image}" class="product-thumb" alt="" loading="lazy">
        <span class="product-category-badge">${cats[p.category] || p.category}</span>
      </div>
      <div class="product-card-body">
        <div class="product-card-title">${p.title}</div>
        <div class="product-card-price">${p.price_fmt || p.price}</div>
        <div class="product-card-seller">
          <img src="${p.avatar || 'assets/default-avatar.png'}" class="seller-mini-avatar" alt="">
          ${p.username} · ${p.time_ago || ''}
        </div>
      </div>
    </a>
  `).join('') + '</div>';
}
</script>
</body>
</html>
