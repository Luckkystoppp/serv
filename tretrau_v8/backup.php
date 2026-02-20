<?php
/**
 * backup.php — Database backup tool
 * Truy cập: backup.php?secret=DB_PASS
 */
require_once 'config.php';  // PHẢI load trước

$secret = $_GET['secret'] ?? '';
startSession();
$_bkUser = currentUser();
if ((!$_bkUser || $_bkUser['role'] !== 'admin') && $secret !== DB_PASS) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Use ?secret=YOUR_DB_PASS']));
}

$action = $_GET['action'] ?? 'ui';

// ── TẠO SQL BACKUP ─────────────────────────────────────────────
function createBackup(PDO $db): string {
    $tables = ['users', 'products', 'chat_channels', 'messages', 'traffic_logs', 'reports', 'admin_tokens'];
    $sql = "-- TreTrau Network Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Server: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    foreach ($tables as $table) {
        try {
            // Get CREATE TABLE
            $createStmt = $db->query("SHOW CREATE TABLE `$table`");
            $row = $createStmt->fetch(PDO::FETCH_NUM);
            if (!$row) continue;

            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Table: `$table`\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $row[1] . ";\n\n";

            // Get data
            $dataStmt = $db->query("SELECT * FROM `$table`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                $sql .= "-- No data in `$table`\n\n";
                continue;
            }

            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $columns) . '`';

            $sql .= "INSERT INTO `$table` ($colList) VALUES\n";
            $values = [];
            foreach ($rows as $r) {
                $escaped = array_map(function($v) use ($db) {
                    if ($v === null) return 'NULL';
                    // PDO::quote adds surrounding quotes
                    return $db->quote((string)$v);
                }, array_values($r));
                $values[] = '(' . implode(', ', $escaped) . ')';
            }
            $sql .= implode(",\n", $values) . ";\n\n";

        } catch (\Throwable $e) {
            $sql .= "-- ERROR backing up `$table`: " . $e->getMessage() . "\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "-- End of backup\n";
    return $sql;
}

// ── DOWNLOAD SQL ───────────────────────────────────────────────
if ($action === 'download') {
    $db = getDB();
    $sql = createBackup($db);
    $filename = 'tretrau_backup_' . date('Y-m-d_H-i-s') . '.sql';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Pragma: no-cache');
    echo $sql;
    exit;
}

// ── LƯU FILE TRÊN SERVER ───────────────────────────────────────
if ($action === 'save') {
    $db = getDB();
    $sql = createBackup($db);
    $backupDir = __DIR__ . '/backups/';
    if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);

    // Thêm .htaccess để block web access
    if (!file_exists($backupDir . '.htaccess')) {
        file_put_contents($backupDir . '.htaccess', "Deny from all\n");
    }

    $filename = 'tretrau_backup_' . date('Y-m-d_H-i-s') . '.sql';
    file_put_contents($backupDir . $filename, $sql);

    // Giữ tối đa 10 backup, xóa cũ nhất
    $files = glob($backupDir . '*.sql');
    if (count($files) > 10) {
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        foreach (array_slice($files, 0, count($files) - 10) as $old) {
            unlink($old);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'file' => $filename, 'size' => strlen($sql)]);
    exit;
}

// ── LIST BACKUPS ──────────────────────────────────────────────
if ($action === 'list') {
    $backupDir = __DIR__ . '/backups/';
    $files = glob($backupDir . '*.sql') ?: [];
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    $list = array_map(fn($f) => [
        'name' => basename($f),
        'size' => round(filesize($f) / 1024, 1) . ' KB',
        'date' => date('d/m/Y H:i:s', filemtime($f)),
    ], $files);
    header('Content-Type: application/json');
    echo json_encode(['backups' => $list]);
    exit;
}

// ── STATS ─────────────────────────────────────────────────────
if ($action === 'stats') {
    $db = getDB();
    $stats = [];
    foreach (['users', 'products', 'messages', 'chat_channels'] as $t) {
        try {
            $stats[$t] = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        } catch (\Throwable $e) {
            $stats[$t] = '?';
        }
    }
    header('Content-Type: application/json');
    echo json_encode($stats);
    exit;
}

// ── GỬI FILE SQL QUA TELEGRAM ─────────────────────────────────
if ($action === 'send_telegram') {
    $db = getDB();
    $sql = createBackup($db);

    // Lưu tạm ra file
    $tmpFile = sys_get_temp_dir() . '/tretrau_backup_' . date('Y-m-d_H-i-s') . '.sql';
    file_put_contents($tmpFile, $sql);

    $token  = TELEGRAM_BOT_TOKEN;
    $chatId = TELEGRAM_ADMIN_CHAT_ID;
    $caption = "💾 TreTrau Backup\n📅 " . date('d/m/Y H:i:s') . "\n📦 " . round(strlen($sql)/1024, 1) . " KB";

    // Gửi file qua Telegram sendDocument
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendDocument");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS     => [
            'chat_id'  => $chatId,
            'document' => new CURLFile($tmpFile, 'application/sql', basename($tmpFile)),
            'caption'  => $caption,
        ],
    ]);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    unlink($tmpFile); // Xóa file tạm

    header('Content-Type: application/json');
    if ($err) {
        echo json_encode(['error' => 'Curl error: ' . $err]);
    } else {
        $resp = json_decode($result, true);
        if ($resp['ok'] ?? false) {
            echo json_encode(['success' => true, 'message' => 'Đã gửi file SQL qua Telegram!']);
        } else {
            echo json_encode(['error' => $resp['description'] ?? 'Telegram error: ' . $result]);
        }
    }
    exit;
}

// ── UI ────────────────────────────────────────────────────────
$encodedSecret = htmlspecialchars($secret);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TreTrau Backup</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d1117; color: #e6edf3; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; min-height: 100vh; padding: 24px; }
  .container { max-width: 700px; margin: 0 auto; }
  .header { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; padding-bottom: 16px; border-bottom: 1px solid #21262d; }
  .logo { width: 40px; height: 40px; border-radius: 8px; overflow: hidden; }
  .logo img { width: 100%; height: 100%; object-fit: cover; }
  h1 { font-size: 1.4rem; font-weight: 700; }
  .subtitle { color: #8b949e; font-size: .85rem; margin-top: 2px; }

  .card { background: #161b22; border: 1px solid #21262d; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
  .card-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #8b949e; margin-bottom: 16px; }

  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: .9rem; font-weight: 600; transition: all .15s; text-decoration: none; }
  .btn-primary { background: #238636; color: #fff; }
  .btn-primary:hover { background: #2ea043; }
  .btn-secondary { background: #21262d; color: #e6edf3; border: 1px solid #30363d; }
  .btn-secondary:hover { background: #30363d; }
  .btn-danger { background: #da3633; color: #fff; }
  .btn:disabled { opacity: .5; cursor: not-allowed; }

  .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .stat-item { background: #0d1117; border: 1px solid #21262d; border-radius: 8px; padding: 12px 16px; }
  .stat-value { font-size: 1.6rem; font-weight: 800; color: #58a6ff; }
  .stat-label { font-size: .75rem; color: #8b949e; margin-top: 2px; }

  .backup-list { display: flex; flex-direction: column; gap: 8px; }
  .backup-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #0d1117; border: 1px solid #21262d; border-radius: 8px; font-size: .85rem; }
  .backup-name { flex: 1; font-family: monospace; color: #58a6ff; }
  .backup-meta { color: #8b949e; font-size: .78rem; }

  .actions { display: flex; gap: 12px; flex-wrap: wrap; }
  .status { padding: 8px 12px; border-radius: 6px; font-size: .85rem; margin-top: 12px; display: none; }
  .status.success { background: rgba(35,134,54,.2); border: 1px solid #238636; color: #56d364; display: block; }
  .status.error { background: rgba(218,54,51,.2); border: 1px solid #da3633; color: #f85149; display: block; }

  .warning { background: rgba(210,153,34,.15); border: 1px solid #9e6a03; border-radius: 8px; padding: 12px 16px; font-size: .85rem; color: #e3b341; margin-bottom: 16px; }
  .warning strong { display: block; margin-bottom: 4px; }

  #backup-list-wrap { margin-top: 16px; }
  .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="logo"><img src="assets/dragon-icon.jpg" alt=""></div>
    <div>
      <div style="font-size:.7rem;color:#8b949e;margin-bottom:2px;">TRETRAU NETWORK</div>
      <h1>Database Backup</h1>
    </div>
  </div>

  <div class="warning">
    <strong>⚠️ Bảo mật</strong>
    Trang này chứa toàn bộ dữ liệu. Đừng để lộ URL có secret. Xóa file backup sau khi đã tải xong.
  </div>

  <!-- Stats -->
  <div class="card">
    <div class="card-title">📊 Thống kê database</div>
    <div class="stats-grid" id="stats-grid">
      <div class="stat-item"><div class="stat-value">...</div><div class="stat-label">Users</div></div>
      <div class="stat-item"><div class="stat-value">...</div><div class="stat-label">Sản phẩm</div></div>
      <div class="stat-item"><div class="stat-value">...</div><div class="stat-label">Tin nhắn</div></div>
      <div class="stat-item"><div class="stat-value">...</div><div class="stat-label">Kênh chat</div></div>
    </div>
  </div>

  <!-- Backup actions -->
  <div class="card">
    <div class="card-title">💾 Tạo backup</div>
    <div class="actions">
      <button class="btn btn-primary" onclick="downloadBackup()">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 12L3 7h3V1h4v6h3L8 12z"/><path d="M1 14h14v1.5H1z"/></svg>
        Tải xuống .sql
      </button>
      <button class="btn btn-secondary" onclick="saveBackup()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Lưu trên server
      </button>
      <button class="btn" style="background:#0088cc;color:#fff;" onclick="sendTelegram()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg>
        Gửi qua Telegram
      </button>
    </div>
    <div id="action-status" class="status"></div>
  </div>

  <!-- Saved backups list -->
  <div class="card">
    <div class="card-title">📁 Backup đã lưu trên server</div>
    <div id="backup-list-wrap">
      <div style="color:#8b949e;font-size:.85rem;">Đang tải...</div>
    </div>
    <button class="btn btn-secondary" style="margin-top:12px;font-size:.8rem;" onclick="loadBackupList()">
      🔄 Làm mới danh sách
    </button>
  </div>

  <!-- Hướng dẫn restore -->
  <div class="card">
    <div class="card-title">📖 Hướng dẫn restore sang server mới</div>
    <div style="font-size:.85rem;line-height:1.7;color:#8b949e;">
      <p style="margin-bottom:8px;"><strong style="color:#e6edf3;">1. Tải file .sql</strong> về máy bằng nút "Tải xuống" ở trên</p>
      <p style="margin-bottom:8px;"><strong style="color:#e6edf3;">2. Upload code</strong> toàn bộ thư mục tretrau lên server mới</p>
      <p style="margin-bottom:8px;"><strong style="color:#e6edf3;">3. Tạo database</strong> mới trên server mới (MySQL/MariaDB)</p>
      <p style="margin-bottom:8px;"><strong style="color:#e6edf3;">4. Import SQL</strong> bằng phpMyAdmin hoặc lệnh:</p>
      <code style="display:block;background:#0d1117;padding:10px;border-radius:6px;border:1px solid #21262d;margin:8px 0;font-size:.8rem;color:#58a6ff;">mysql -u USER -p DATABASE &lt; tretrau_backup_DATE.sql</code>
      <p style="margin-bottom:8px;"><strong style="color:#e6edf3;">5. Cập nhật config.php</strong> với thông tin DB mới</p>
      <p><strong style="color:#e6edf3;">6. Chạy setup.php</strong> nếu cần tạo lại admin account</p>
    </div>
  </div>
</div>

<script>
const secret = '<?= $encodedSecret ?>';
const base = `backup.php?secret=${secret}`;

async function loadStats() {
  try {
    const r = await fetch(`${base}&action=stats`);
    const d = await r.json();
    const grid = document.getElementById('stats-grid');
    const labels = ['Users', 'Sản phẩm', 'Tin nhắn', 'Kênh chat'];
    const keys = ['users', 'products', 'messages', 'chat_channels'];
    grid.innerHTML = keys.map((k, i) => `
      <div class="stat-item">
        <div class="stat-value">${d[k] ?? '?'}</div>
        <div class="stat-label">${labels[i]}</div>
      </div>`).join('');
  } catch(e) {}
}

function downloadBackup() {
  window.location.href = `${base}&action=download`;
}

async function saveBackup() {
  const btn = event.target.closest('button');
  const status = document.getElementById('action-status');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> Đang tạo...';
  status.className = 'status';
  try {
    const r = await fetch(`${base}&action=save`);
    const d = await r.json();
    if (d.success) {
      status.className = 'status success';
      status.textContent = `✓ Đã lưu: ${d.file} (${Math.round(d.size/1024)} KB)`;
      loadBackupList();
    } else {
      throw new Error(d.error || 'Lỗi không xác định');
    }
  } catch(e) {
    status.className = 'status error';
    status.textContent = '✗ Lỗi: ' + e.message;
  }
  btn.disabled = false;
  btn.innerHTML = orig;
}

async function sendTelegram() {
  const btn = event.target.closest('button');
  const status = document.getElementById('action-status');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> Đang gửi...';
  status.className = 'status';
  try {
    const r = await fetch(`${base}&action=send_telegram`);
    const d = await r.json();
    if (d.success) {
      status.className = 'status success';
      status.textContent = '✓ ' + d.message;
    } else {
      throw new Error(d.error || 'Lỗi Telegram');
    }
  } catch(e) {
    status.className = 'status error';
    status.textContent = '✗ ' + e.message;
  }
  btn.disabled = false;
  btn.innerHTML = orig;
}

async function loadBackupList() {
  const wrap = document.getElementById('backup-list-wrap');
  try {
    const r = await fetch(`${base}&action=list`);
    const d = await r.json();
    if (!d.backups || d.backups.length === 0) {
      wrap.innerHTML = '<div style="color:#8b949e;font-size:.85rem;">Chưa có backup nào được lưu trên server.</div>';
      return;
    }
    wrap.innerHTML = '<div class="backup-list">' + d.backups.map(b => `
      <div class="backup-item">
        <div class="backup-name">${b.name}</div>
        <div class="backup-meta">${b.size} · ${b.date}</div>
      </div>`).join('') + '</div>';
  } catch(e) {
    wrap.innerHTML = '<div style="color:#8b949e;font-size:.85rem;">Không thể tải danh sách.</div>';
  }
}

loadStats();
loadBackupList();
</script>
</body>
</html>
