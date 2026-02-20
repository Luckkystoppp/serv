<?php
require_once '../config.php';
startSession();
$user = currentUser();

// ── STEP 1: Must be logged in
if (!$user) {
    header('Location: ../index.php?page=login');
    exit;
}

// ── STEP 2: Auto-promote phuvanduc to admin
if ($user['username'] === 'phuvanduc' && $user['role'] !== 'admin') {
    $db = getDB();
    $db->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$user['id']]);
    $user['role'] = 'admin';
}

// ── STEP 3: Must be admin role
if ($user['role'] !== 'admin') {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title><style>body{background:#0a0a0a;color:#fff;font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;gap:12px;}</style></head><body><h2>🔒 Không có quyền truy cập</h2><a href="../index.php" style="color:#888;border:1px solid #333;padding:8px 20px;border-radius:8px;text-decoration:none;">← Về trang chủ</a></body></html>');
}

$db = getDB();

// ── STEP 4: 2FA password
define('ADMIN_2FA_PASS', 'abcxyz01#_');

// Xử lý POST submit password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_2fa'])) {
    if ($_POST['_2fa'] === ADMIN_2FA_PASS) {
        $_SESSION['admin_verified'] = time();
        header('Location: admin.php');
        exit;
    } else {
        $authError = 'Sai mật khẩu xác thực!';
    }
}

// ── STEP 5: Check if session is admin-verified (within 2 hours)
$adminVerified = $_SESSION['admin_verified'] ?? 0;
$needsAuth = (time() - $adminVerified) > 7200;

if ($needsAuth) {
    $authError = $authError ?? '';
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xác thực Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a0a0a;color:#fff;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
.card{background:#111;border:1px solid #222;border-radius:16px;padding:40px 32px;max-width:380px;width:100%;text-align:center}
.lock{font-size:2.5rem;margin-bottom:16px}
h2{font-size:1.2rem;font-weight:700;margin-bottom:6px}
p{color:#666;font-size:.85rem;margin-bottom:28px}
input{width:100%;background:#0a0a0a;border:1px solid #333;border-radius:8px;color:#fff;font-size:1rem;padding:12px 16px;outline:none;letter-spacing:2px;text-align:center}
input:focus{border-color:#555}
button{width:100%;margin-top:12px;padding:12px;background:#fff;color:#000;font-weight:700;font-size:.95rem;border:none;border-radius:8px;cursor:pointer}
button:hover{background:#ddd}
.err{color:#f87171;font-size:.82rem;margin-top:10px}
.back{display:inline-block;margin-top:20px;color:#555;font-size:.8rem;text-decoration:none}
.back:hover{color:#999}
</style>
</head>
<body>
<div class="card">
  <div class="lock">🔐</div>
  <h2>Xác thực Admin</h2>
  <p>Nhập mật khẩu xác thực để vào trang quản trị</p>
  <form method="POST">
    <input type="password" name="_2fa" placeholder="••••••••••" autofocus autocomplete="off">
    <button type="submit">Xác nhận</button>
    <?php if ($authError): ?>
    <div class="err"><?= htmlspecialchars($authError) ?></div>
    <?php endif; ?>
  </form>
  <a href="../index.php" class="back">← Về trang chủ</a>
</div>
</body>
</html>
    <?php
    exit;
}

// ── ADMIN PANEL BODY ──────────────────────────────────────────
$action = $_GET['action'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    switch ($act) {
        case 'ban_user':
            $uid = (int)$_POST['user_id'];
            $db->prepare("UPDATE users SET status='banned' WHERE id=?")->execute([$uid]);
            header('Location: admin.php?action=users&msg=banned'); exit;
        case 'unban_user':
            $uid = (int)$_POST['user_id'];
            $db->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$uid]);
            header('Location: admin.php?action=users&msg=unbanned'); exit;
        case 'set_premium':
            $uid = (int)$_POST['user_id'];
            $db->prepare("UPDATE users SET role='premium' WHERE id=?")->execute([$uid]);
            header('Location: admin.php?action=users'); exit;
        case 'set_admin':
            $uid = (int)$_POST['user_id'];
            $db->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$uid]);
            header('Location: admin.php?action=users'); exit;
        case 'set_user':
            $uid = (int)$_POST['user_id'];
            $db->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$uid]);
            header('Location: admin.php?action=users'); exit;
        case 'delete_product':
            $pid = (int)$_POST['product_id'];
            $db->prepare("UPDATE products SET status='deleted', deleted_at=NOW() WHERE id=?")->execute([$pid]);
            header('Location: admin.php?action=products'); exit;
        case 'resolve_report':
            $rid = (int)$_POST['report_id'];
            $db->prepare("UPDATE reports SET status='resolved' WHERE id=?")->execute([$rid]);
            header('Location: admin.php?action=reports'); exit;
    }
}

$stats = [
    'total_users'    => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'premium_users'  => $db->query("SELECT COUNT(*) FROM users WHERE role='premium'")->fetchColumn(),
    'admin_users'    => $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(),
    'new_users_today'=> $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
    'total_products' => $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
    'total_traffic'  => $db->query("SELECT COUNT(*) FROM traffic_logs")->fetchColumn(),
    'traffic_today'  => $db->query("SELECT COUNT(*) FROM traffic_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
    'pending_reports'=> $db->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn(),
    'total_messages' => $db->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
];
$trafficChart = $db->query("SELECT DATE(created_at) as d, COUNT(*) as cnt FROM traffic_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$userChart    = $db->query("SELECT DATE(created_at) as d, COUNT(*) as cnt FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$roleChart    = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
.role-actions { display:flex; gap:4px; flex-wrap:wrap; }
.btn-admin-sm { background:linear-gradient(135deg,#7c3aed,#5b21b6); color:#fff; border:none; padding:3px 9px; border-radius:4px; font-size:.72rem; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.btn-user-sm  { background:rgba(255,255,255,.1); color:#ccc; border:1px solid rgba(255,255,255,.15); padding:3px 9px; border-radius:4px; font-size:.72rem; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.btn-user-sm:hover { background:rgba(255,255,255,.18); }
.session-badge { background:rgba(0,200,100,.15); border:1px solid rgba(0,200,100,.3); color:#4ade80; padding:3px 10px; border-radius:999px; font-size:.72rem; display:inline-flex; align-items:center; gap:5px; }
</style>
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon"><span class="icon-shield"></span></div>
      <span><?= SITE_NAME ?></span>
    </div>
    <nav class="sidebar-nav">
      <a href="?action=dashboard" class="<?= $action==='dashboard'?'active':'' ?>"><span class="icon-grid"></span> Dashboard</a>
      <a href="?action=users"     class="<?= $action==='users'    ?'active':'' ?>"><span class="icon-users"></span> Users</a>
      <a href="?action=products"  class="<?= $action==='products' ?'active':'' ?>"><span class="icon-box"></span> Products</a>
      <a href="?action=traffic"   class="<?= $action==='traffic'  ?'active':'' ?>"><span class="icon-activity"></span> Traffic</a>
      <a href="?action=reports"   class="<?= $action==='reports'  ?'active':'' ?>"><span class="icon-flag"></span> Reports <?= $stats['pending_reports']>0?"<span class='badge-count'>{$stats['pending_reports']}</span>":'' ?></a>
      <a href="../backup.php" target="_blank" style="margin-top:8px;border-top:1px solid rgba(255,255,255,.08);padding-top:12px;"><span class="icon-shield"></span> Backup DB</a>
    </nav>
    <div class="sidebar-footer">
      <div style="margin-bottom:8px;"><span class="session-badge"><span class="icon-check"></span> Đã xác thực</span></div>
      <div style="font-size:.75rem;color:#888;padding:4px 0;display:flex;align-items:center;gap:6px;"><span class="icon-user"></span> <?= htmlspecialchars($user['username']) ?></div>
      <a href="../index.php" class="btn-back"><span class="icon-home"></span> Website</a>
    </div>
  </aside>

  <main class="admin-main">
    <header class="admin-header">
      <h1 class="page-title"><?= match($action) {
        'dashboard'=>'<span class="icon-grid"></span> Dashboard',
        'users'    =>'<span class="icon-users"></span> Quản lý Users',
        'products' =>'<span class="icon-box"></span> Sản phẩm',
        'traffic'  =>'<span class="icon-activity"></span> Traffic',
        'reports'  =>'<span class="icon-flag"></span> Báo cáo',
        default    =>'Admin'
      } ?></h1>
      <div class="header-right"><span class="status-dot"></span><span><?= date('d/m/Y H:i') ?></span></div>
    </header>

    <?php if (isset($_GET['msg'])): ?>
    <div style="background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25);border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:.85rem;display:flex;align-items:center;gap:8px;">
      <span class="icon-check" style="color:#4ade80;"></span>
      <?= match($_GET['msg']) { 'banned'=>'Đã ban user.', 'unbanned'=>'Đã unban user.', default=>'Thao tác thành công.' } ?>
    </div>
    <?php endif; ?>

    <?php if ($action === 'dashboard'): ?>
    <div class="stat-grid">
      <?php foreach ([
        ['icon-users','stat-num'=>$stats['total_users'],'label'=>'Tổng Users','sub'=>'+'.($stats['new_users_today']).' hôm nay'],
        ['icon-star','stat-num'=>$stats['premium_users'],'label'=>'Premium','sub'=>($stats['admin_users']).' Admin'],
        ['icon-box','stat-num'=>$stats['total_products'],'label'=>'Sản phẩm','sub'=>''],
        ['icon-activity','stat-num'=>$stats['traffic_today'],'label'=>'Traffic hôm nay','sub'=>'Tổng: '.number_format($stats['total_traffic'])],
        ['icon-chat','stat-num'=>$stats['total_messages'],'label'=>'Tin nhắn','sub'=>''],
        ['icon-flag','stat-num'=>$stats['pending_reports'],'label'=>'Báo cáo chờ','sub'=>''],
      ] as $s): ?>
      <div class="stat-card">
        <div class="stat-icon <?= $s[0] ?>"></div>
        <div class="stat-body">
          <div class="stat-num"><?= number_format($s['stat-num']) ?></div>
          <div class="stat-label"><?= $s['label'] ?></div>
          <?php if ($s['sub']): ?><div class="stat-sub"><?= $s['sub'] ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="chart-grid">
      <div class="chart-card"><h3>Traffic 7 ngày</h3><canvas id="c1"></canvas></div>
      <div class="chart-card"><h3>Đăng ký 7 ngày</h3><canvas id="c2"></canvas></div>
      <div class="chart-card chart-small"><h3>Phân bố roles</h3><canvas id="c3"></canvas></div>
    </div>
    <script>
    Chart.defaults.color='#aaa';
    const td=<?= json_encode($trafficChart) ?>, ud=<?= json_encode($userChart) ?>, rd=<?= json_encode($roleChart) ?>;
    new Chart(document.getElementById('c1'),{type:'line',data:{labels:td.map(r=>r.d),datasets:[{data:td.map(r=>r.cnt),borderColor:'#fff',backgroundColor:'rgba(255,255,255,.05)',tension:.4,fill:true,pointBackgroundColor:'#fff'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,.05)'}},y:{grid:{color:'rgba(255,255,255,.05)'},beginAtZero:true}}}});
    new Chart(document.getElementById('c2'),{type:'bar',data:{labels:ud.map(r=>r.d),datasets:[{data:ud.map(r=>r.cnt),backgroundColor:'rgba(255,255,255,.15)',borderColor:'#fff',borderWidth:1,borderRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,.05)'}},y:{grid:{color:'rgba(255,255,255,.05)'},beginAtZero:true}}}});
    new Chart(document.getElementById('c3'),{type:'doughnut',data:{labels:rd.map(r=>r.role),datasets:[{data:rd.map(r=>r.cnt),backgroundColor:['#fff','#7c3aed','#aaa','#333'],borderWidth:0}]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#aaa',padding:16}}},cutout:'65%'}});
    </script>

    <?php elseif ($action === 'users'): ?>
    <?php
    $search = sanitizeInput($_GET['q'] ?? '');
    $rf = sanitizeInput($_GET['role'] ?? '');
    $pg = max(1,(int)($_GET['p'] ?? 1)); $lim=25; $off=($pg-1)*$lim;
    $where = "WHERE 1=1"; $params = [];
    if ($search) { $where .= " AND (username LIKE ? OR email LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
    if ($rf)     { $where .= " AND role=?"; $params[]=$rf; }
    $users = $db->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $lim OFFSET $off");
    $users->execute($params); $users=$users->fetchAll();
    $tot = $db->prepare("SELECT COUNT(*) FROM users $where"); $tot->execute($params); $tot=$tot->fetchColumn();
    ?>
    <div class="table-toolbar" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
      <form method="get" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;">
        <input type="hidden" name="action" value="users">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm username / email..." style="flex:1;min-width:140px;">
        <select name="role" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#fff;padding:6px 10px;border-radius:6px;">
          <option value="">Tất cả role</option>
          <?php foreach(['new','user','premium','admin'] as $r): ?>
          <option value="<?= $r ?>" <?= $rf===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-sm"><span class="icon-search"></span> Tìm</button>
      </form>
      <div style="font-size:.8rem;color:#888;">Tổng: <?= $tot ?></div>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>Avatar</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><img src="../<?= htmlspecialchars(getAvatarUrl($u['avatar'])) ?>" class="table-avatar" alt=""></td>
          <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
          <td style="font-size:.8rem;color:#888;"><?= htmlspecialchars($u['email']) ?></td>
          <td><?= getRoleBadge($u['role']) ?></td>
          <td><span class="status-badge status-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
          <td style="font-size:.8rem;color:#888;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td class="action-cell">
            <div class="role-actions">
              <?php if ($u['status']==='active'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="ban_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="btn-danger-sm" onclick="return confirm('Ban?')"><span class="icon-slash"></span> Ban</button></form>
              <?php else: ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="unban_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="btn-sm"><span class="icon-check"></span> Unban</button></form>
              <?php endif; ?>
              <?php if (!in_array($u['role'],['premium','admin'])): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="set_premium"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="btn-primary-sm"><span class="icon-star"></span> Premium</button></form>
              <?php endif; ?>
              <?php if ($u['role']!=='admin'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="set_admin"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="btn-admin-sm" onclick="return confirm('Cấp Admin?')"><span class="icon-shield"></span> Admin</button></form>
              <?php endif; ?>
              <?php if (!in_array($u['role'],['user','new']) && $u['username']!=='phuvanduc'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="set_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="btn-user-sm" onclick="return confirm('Hạ xuống User?')"><span class="icon-arrow-down"></span> User</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($tot > $lim): ?>
    <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap;">
      <?php for($i=1;$i<=ceil($tot/$lim);$i++): ?>
      <a href="?action=users&p=<?= $i ?>&q=<?= urlencode($search) ?>&role=<?= urlencode($rf) ?>" style="padding:5px 12px;border-radius:6px;background:<?= $pg===$i?'rgba(255,255,255,.25)':'rgba(255,255,255,.08)' ?>;color:#fff;text-decoration:none;font-size:.82rem;"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($action === 'products'): ?>
    <?php
    $pg=max(1,(int)($_GET['p']??1)); $lim=20; $off=($pg-1)*$lim;
    $products=$db->query("SELECT p.*,u.username FROM products p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC LIMIT $lim OFFSET $off")->fetchAll();
    ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>Ảnh</th><th>Tiêu đề</th><th>Người đăng</th><th>Giá</th><th>Status</th><th>Ngày</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><img src="../<?= htmlspecialchars($p['image']) ?>" class="table-thumb" alt=""></td>
          <td><?= htmlspecialchars(mb_substr($p['title'],0,40)) ?></td>
          <td><?= htmlspecialchars($p['username']) ?></td>
          <td><?= formatPrice($p['price'],$p['currency']) ?></td>
          <td><span class="status-badge status-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
          <td><?= date('d/m/Y',strtotime($p['created_at'])) ?></td>
          <td><?php if ($p['status']!=='deleted'): ?><form method="post" style="display:inline"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="product_id" value="<?= $p['id'] ?>"><button class="btn-danger-sm" onclick="return confirm('Xóa?')"><span class="icon-trash"></span> Xóa</button></form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($action === 'traffic'): ?>
    <?php
    $t30=$db->query("SELECT DATE(created_at) as d,COUNT(*) as cnt FROM traffic_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
    $tp=$db->query("SELECT page,COUNT(*) as cnt FROM traffic_logs GROUP BY page ORDER BY cnt DESC LIMIT 10")->fetchAll();
    $ti=$db->query("SELECT ip_address,COUNT(*) as cnt FROM traffic_logs GROUP BY ip_address ORDER BY cnt DESC LIMIT 10")->fetchAll();
    ?>
    <div class="chart-grid"><div class="chart-card chart-wide"><h3>Traffic 30 ngày</h3><canvas id="c30"></canvas></div></div>
    <div class="table-row-2">
      <div class="table-wrap"><h3 class="section-title">Top Pages</h3><table class="admin-table"><thead><tr><th>Trang</th><th>Lượt</th></tr></thead><tbody><?php foreach($tp as $r): ?><tr><td><?= htmlspecialchars($r['page']) ?></td><td><?= number_format($r['cnt']) ?></td></tr><?php endforeach; ?></tbody></table></div>
      <div class="table-wrap"><h3 class="section-title">Top IPs</h3><table class="admin-table"><thead><tr><th>IP</th><th>Lượt</th></tr></thead><tbody><?php foreach($ti as $r): ?><tr><td><?= htmlspecialchars($r['ip_address']) ?></td><td><?= number_format($r['cnt']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
    <script>
    Chart.defaults.color='#aaa';
    const t30d=<?= json_encode($t30) ?>;
    new Chart(document.getElementById('c30'),{type:'line',data:{labels:t30d.map(r=>r.d),datasets:[{data:t30d.map(r=>r.cnt),borderColor:'#fff',backgroundColor:'rgba(255,255,255,.04)',tension:.3,fill:true,pointRadius:3,pointBackgroundColor:'#fff'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,.05)'},ticks:{maxRotation:45}},y:{grid:{color:'rgba(255,255,255,.05)'},beginAtZero:true}}}});
    </script>

    <?php elseif ($action === 'reports'): ?>
    <?php $reports=$db->query("SELECT r.*,rep.username as reporter_name,tu.username as target_name FROM reports r JOIN users rep ON rep.id=r.reporter_id LEFT JOIN users tu ON tu.id=r.target_user_id ORDER BY r.created_at DESC LIMIT 50")->fetchAll(); ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>Reporter</th><th>Target</th><th>Lý do</th><th>Status</th><th>Ngày</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['reporter_name']) ?></td>
          <td><?= htmlspecialchars($r['target_name']??'N/A') ?></td>
          <td><?= htmlspecialchars(mb_substr($r['reason'],0,60)) ?></td>
          <td><span class="status-badge status-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
          <td><?= date('d/m/Y',strtotime($r['created_at'])) ?></td>
          <td><?php if ($r['status']==='pending'): ?><form method="post" style="display:inline"><input type="hidden" name="action" value="resolve_report"><input type="hidden" name="report_id" value="<?= $r['id'] ?>"><button class="btn-sm"><span class="icon-check"></span> Resolve</button></form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
