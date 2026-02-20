<?php
if (!defined('DB_HOST')) require_once __DIR__ . '/../config.php';
$user = currentUser();
$currentPage = $_GET['page'] ?? 'home';
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf" content="<?= $csrf ?>">
  <title><?= SITE_NAME ?></title>
  <link rel="icon" type="image/jpeg" href="assets/dragon-icon.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/variables.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <?php if (in_array($currentPage, ['chat','global-chat'])): ?>
  <link rel="stylesheet" href="assets/css/chat.css">
  <?php endif; ?>
  <style>
    @keyframes fadeOut { to { opacity: 0; transform: translateY(-8px); } }
    .user-dropdown { display: none; }
    .user-dropdown.show { display: block; }
  </style>
</head>
<body>

<!-- SITE HEADER -->
<header class="site-header">
  <a href="index.php" class="header-logo">
    <div class="logo-mark">
      <img src="assets/dragon-icon.jpg" alt="TreTrau" style="width:32px;height:32px;border-radius:6px;object-fit:cover;display:block;">
    </div>
    <span class="hide-mobile"><?= SITE_NAME ?></span>
  </a>

  <div class="header-search">
    <span class="search-icon icon-search"></span>
    <input type="text" placeholder="Tìm kiếm sản phẩm..." id="global-search"
      autocomplete="off" oninput="handleSearch(this.value)">
  </div>

  <div class="header-actions">
    <?php if ($user): ?>
      <a href="index.php?page=post-product" class="header-icon-btn" title="Đăng bán">
        <span class="icon-plus"></span>
      </a>
      <a href="index.php?page=chat" class="header-icon-btn <?= in_array($currentPage, ['chat','global-chat']) ? 'active' : '' ?>" title="Tin nhắn" id="header-chat-btn" style="position:relative;">
        <span class="icon-chat"></span>
        <?php
        // Quick unread count for header
        $hdrDB = getDB();
        try {
          $hdrUnread = (int)$hdrDB->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_dm=1 AND is_read=0 AND recalled=0")->execute([$user['id']]) ? 0 : 0;
        } catch(Exception $e) { $hdrUnread = 0; }
        ?>
      </a>
      <div class="user-dropdown-wrap">
        <img src="<?= htmlspecialchars(getAvatarUrl($user['avatar'])) ?>"
             class="header-avatar"
             data-dropdown-trigger="user-menu"
             alt="Avatar">
        <div class="user-dropdown" id="user-menu">
          <div class="dropdown-info">
            <div class="dropdown-username"><?= htmlspecialchars($user['username']) ?></div>
            <div class="dropdown-role"><?= ucfirst($user['role']) ?> account</div>
          </div>
          <a href="index.php?page=profile&id=<?= $user['id'] ?>" class="dropdown-item">
            <span class="icon-user"></span> Trang cá nhân
          </a>
          <a href="index.php?page=account" class="dropdown-item">
            <span class="icon-settings"></span> Cài đặt tài khoản
          </a>
          <a href="index.php?page=chat" class="dropdown-item">
            <span class="icon-chat"></span> Tin nhắn & Chat chung
          </a>
          <div class="dropdown-divider"></div>
          <?php if ($user['role'] === 'admin'): ?>
          <a href="admin/admin.php" class="dropdown-item" target="_blank">
            <span class="icon-shield"></span> Admin Panel
          </a>
          <div class="dropdown-divider"></div>
          <?php endif; ?>
          <a href="index.php?page=logout" class="dropdown-item danger">
            <span class="icon-logout"></span> Đăng xuất
          </a>
        </div>
      </div>
    <?php else: ?>
      <a href="index.php?page=login" class="btn btn-ghost btn-sm">Đăng nhập</a>
      <a href="index.php?page=register" class="btn btn-primary btn-sm">Đăng ký</a>
    <?php endif; ?>
  </div>
</header>

<!-- MOBILE SEARCH BAR -->
<div class="mobile-search-bar" id="mobile-search-bar">
  <div class="mobile-search-bar-inner">
    <span class="mobile-search-icon icon-search"></span>
    <input type="text" placeholder="Tìm kiếm sản phẩm..." id="mobile-search-input"
      autocomplete="off" oninput="handleSearch(this.value)">
  </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- Set current user for JS -->
<script>
window.CURRENT_USER_ID = <?= $user ? (int)$user['id'] : 'null' ?>;
</script>

<?php
// Determine if this page uses sidebar layout
$sidebarPages = ['home','profile','account','post-product'];
if (in_array($currentPage, $sidebarPages)):
?>
<div class="page-layout-sidebar">
  <!-- SIDEBAR -->
  <aside>
    <nav class="side-nav">
      <span class="side-nav-section">Khám phá</span>
      <a href="index.php?page=home" class="side-nav-item <?= $currentPage==='home'?'active':'' ?>">
        <span class="icon-home"></span> Trang chủ
      </a>
      <?php if ($user): ?>
      <a href="index.php?page=post-product" class="side-nav-item <?= $currentPage==='post-product'?'active':'' ?>">
        <span class="icon-plus"></span> Đăng bán
      </a>
      <span class="side-nav-section">Cá nhân</span>
      <a href="index.php?page=profile&id=<?= $user['id'] ?>" class="side-nav-item <?= $currentPage==='profile'?'active':'' ?>">
        <span class="icon-user"></span> Hồ sơ
      </a>
      <a href="index.php?page=account" class="side-nav-item <?= $currentPage==='account'?'active':'' ?>">
        <span class="icon-settings"></span> Tài khoản
      </a>
      <span class="side-nav-section">Cộng đồng</span>
      <a href="index.php?page=chat" class="side-nav-item <?= in_array($currentPage, ['chat','global-chat'])?'active':'' ?>">
        <span class="icon-chat"></span> Tin nhắn
      </a>
      <?php endif; ?>
    </nav>
  </aside>
  <main>
<?php else: ?>
<?php if (!in_array($currentPage, ['login','register','chat','global-chat'])): ?>
<div class="page-layout">
<?php endif; ?>
<?php endif; ?>
