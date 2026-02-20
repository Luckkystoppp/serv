<?php
ini_set("display_errors", "0");
error_reporting(E_ALL);
ini_set("log_errors", "1");

// ============================================================
// CONFIG
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'marketplace_db');
define('DB_USER', 'root');
define('DB_PASS', 'phuvanduc');
define('TELEGRAM_BOT_TOKEN', '8375712598:AAFTbqc-gOlj0EnLuyl3rYMYvmhMyMynZMk');
define('TELEGRAM_ADMIN_CHAT_ID', '7567975053');
define('SITE_NAME', 'TreTrauNetwork');
define('SITE_URL', 'https://yourdomain.com');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('SESSION_LIFETIME', 86400 * 7);
define('NEW_ACC_DAILY_POST_LIMIT', 1);
define('PREMIUM_ACC_DAILY_POST_LIMIT', 10);

// ============================================================
// SECURITY HEADERS
// ============================================================
function sendSecurityHeaders(): void {
    if (headers_sent()) return;
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}
sendSecurityHeaders();

// ============================================================
// ANTI-DEFACEMENT: verify critical files on startup
// ============================================================
function checkFileIntegrity(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $criticalFiles = [
        __DIR__ . '/index.php',
        __DIR__ . '/config.php',
        __DIR__ . '/api.php',
    ];
    foreach ($criticalFiles as $f) {
        if (!file_exists($f)) {
            error_log("INTEGRITY ALERT: Missing critical file: $f");
        }
        // Check for common webshell patterns in uploaded files
        $uploadsDir = __DIR__ . '/uploads/';
        if (is_dir($uploadsDir)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php','php3','php4','php5','phtml','phar','asp','aspx','jsp','sh'])) {
                    @unlink($file->getPathname());
                    error_log("SECURITY: Deleted suspicious file: " . $file->getPathname());
                }
            }
        }
    }
}
checkFileIntegrity();

// ============================================================
// DATABASE
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// ============================================================
// INSTALL SCHEMA
// ============================================================
function installDatabase(): void {
    $db = getDB();
    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        bio TEXT DEFAULT NULL,
        avatar VARCHAR(255) DEFAULT NULL,
        role ENUM('user','premium','admin') DEFAULT 'user',
        status ENUM('active','banned','suspended') DEFAULT 'active',
        username_changed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        INDEX idx_email (email),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(15,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'VND',
        image VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'other',
        status ENUM('active','sold','deleted','pending') DEFAULT 'pending',
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_status (status),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS chat_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        channel_key VARCHAR(64) UNIQUE NOT NULL,
        status ENUM('active','closed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (buyer_id) REFERENCES users(id),
        FOREIGN KEY (seller_id) REFERENCES users(id),
        UNIQUE KEY unique_channel (product_id, buyer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT DEFAULT NULL,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,
        content TEXT DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        type ENUM('text','image','system') DEFAULT 'text',
        is_global TINYINT(1) DEFAULT 0,
        is_dm TINYINT(1) DEFAULT 0,
        recalled TINYINT(1) DEFAULT 0,
        recalled_at TIMESTAMP NULL,
        read_at TIMESTAMP NULL,
        reply_to INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        INDEX idx_channel (channel_id),
        INDEX idx_global (is_global),
        INDEX idx_dm (sender_id, receiver_id, is_dm)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS traffic_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        page VARCHAR(255),
        action VARCHAR(100),
        extra JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at),
        INDEX idx_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        target_user_id INT DEFAULT NULL,
        target_product_id INT DEFAULT NULL,
        reason VARCHAR(255) NOT NULL,
        status ENUM('pending','resolved','dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS admin_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) UNIQUE NOT NULL,
        ip_address VARCHAR(45),
        used TINYINT(1) DEFAULT 0,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// ============================================================
// SESSION
// ============================================================
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function currentUser(): ?array {
    startSession();
    if (!isset($_SESSION['user_id'])) return null;
    $db = getDB();
    $db->exec("UPDATE users SET role='user' WHERE role='new' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) { header('Location: index.php?page=login'); exit; }
    return $user;
}

// ============================================================
// CSRF
// ============================================================
function generateCSRF(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(?string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ============================================================
// CAPTCHA — math-based, no external service
// ============================================================
function generateCaptcha(): array {
    $ops  = ['+', '-', '×'];
    $op   = $ops[array_rand($ops)];
    $a    = random_int(2, 15);
    $b    = random_int(1, 10);
    if ($op === '-') { if ($a < $b) [$a,$b] = [$b,$a]; }
    $ans  = match($op) { '+' => $a+$b, '-' => $a-$b, '×' => $a*$b };
    return ['q' => "{$a} {$op} {$b} = ?", 'a' => $ans];
}

function getCaptchaQuestion(): string {
    startSession();
    $c = generateCaptcha();
    $_SESSION['captcha_ans'] = $c['a'];
    $_SESSION['captcha_ts']  = time();
    return $c['q'];
}

function verifyCaptcha(string $input): bool {
    startSession();
    if (empty($_SESSION['captcha_ans']) || empty($_SESSION['captcha_ts'])) return false;
    if (time() - $_SESSION['captcha_ts'] > 300) { // 5 min expiry
        unset($_SESSION['captcha_ans'], $_SESSION['captcha_ts']);
        return false;
    }
    $ok = (int)$input === (int)$_SESSION['captcha_ans'];
    unset($_SESSION['captcha_ans'], $_SESSION['captcha_ts']); // one-time use
    return $ok;
}

// ============================================================
// RATE LIMITING
// ============================================================
function rateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool {
    startSession();
    $now = time();
    $sessionKey = "rl_{$key}";
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 0, 'window_start' => $now];
    }
    if ($now - $_SESSION[$sessionKey]['window_start'] > $windowSeconds) {
        $_SESSION[$sessionKey] = ['count' => 0, 'window_start' => $now];
    }
    $_SESSION[$sessionKey]['count']++;
    return $_SESSION[$sessionKey]['count'] <= $maxAttempts;
}

// ============================================================
// ANTI-XSS INPUT SANITIZATION
// ============================================================
function sanitizeInput(string $input): string {
    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $input;
}

function sanitizeTitle(string $input): string {
    return mb_substr(sanitizeInput($input), 0, 255);
}

function sanitizeDescription(string $input): string {
    return mb_substr(strip_tags(trim($input)), 0, 5000);
}

// ============================================================
// HELPERS
// ============================================================
function jsonResponse(array $data, int $code = 200): void {
    if (ob_get_level()) ob_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function isAccountMature(array $user): bool {
    return strtotime($user['created_at']) < (time() - 7 * 86400);
}

function getDailyPostLimit(array $user): int {
    if (in_array($user['role'], ['premium','admin'])) return PREMIUM_ACC_DAILY_POST_LIMIT;
    return NEW_ACC_DAILY_POST_LIMIT;
}

function getTodayPostCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE user_id=? AND DATE(created_at)=CURDATE() AND status!='deleted'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Vừa xong';
    if ($diff < 3600)   return floor($diff/60) . ' phút trước';
    if ($diff < 86400)  return floor($diff/3600) . ' giờ trước';
    if ($diff < 604800) return floor($diff/86400) . ' ngày trước';
    return date('d/m/Y', strtotime($datetime));
}

function formatPrice(float $price, string $currency = 'VND'): string {
    if ($currency === 'VND') return number_format($price, 0, ',', '.') . 'đ';
    return number_format($price, 2) . ' ' . $currency;
}

function getRoleBadge(string $role): string {
    return match($role) {
        'admin'   => '<span class="role-badge role-admin"><span class="icon-shield"></span> Admin</span>',
        'premium' => '<span class="role-badge role-premium"><span class="icon-star"></span> Premium</span>',
        'user'    => '<span class="role-badge role-user">User</span>',
        default   => '<span class="role-badge role-new">New</span>',
    };
}

function getAvatarUrl(?string $avatar): string {
    if ($avatar && file_exists(__DIR__ . '/' . ltrim($avatar, '/'))) return $avatar;
    return 'assets/default-avatar.png';
}

// ============================================================
// IMAGE UPLOAD (with anti-webshell)
// ============================================================
function handleImageUpload(string $fieldName): ?string {
    if (empty($_FILES[$fieldName]['name'])) return null;
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Lỗi upload file');
    if ($file['size'] > MAX_FILE_SIZE) throw new Exception('File quá lớn (tối đa 5MB)');

    // Double-check MIME using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME)) throw new Exception('Chỉ chấp nhận file ảnh (jpg/png/webp/gif)');

    // Check magic bytes
    $fp  = fopen($file['tmp_name'], 'rb');
    $sig = fread($fp, 8);
    fclose($fp);
    $validSigs = ["\xFF\xD8\xFF", "\x89PNG", "GIF8", "RIFF", "WEBP"];
    $valid = false;
    foreach ($validSigs as $s) { if (str_starts_with($sig, $s) || str_contains($sig, 'WEBP')) { $valid = true; break; } }
    // also check WEBP specifically
    if (!$valid && str_starts_with($sig, 'RIFF')) $valid = true;
    if (!$valid) throw new Exception('File ảnh không hợp lệ');

    // Scan for PHP tags in file content
    $content = file_get_contents($file['tmp_name']);
    if (preg_match('/<\?php|<\?=|<script/i', $content)) throw new Exception('File chứa nội dung không hợp lệ');

    $dir = UPLOAD_DIR . date('Y/m/') ;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Force safe extension based on actual MIME
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $ext    = $extMap[$mime] ?? 'jpg';
    $name   = hash('sha256', uniqid('', true) . $file['name']) . '.' . $ext;
    $dest   = $dir . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Không thể lưu file');

    // Write .htaccess to uploads folder to prevent PHP execution
    $htaccess = UPLOAD_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -ExecCGI\nAddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .htm .shtml .sh .cgi\nphp_flag engine off\n<FilesMatch \"\.(php|php3|php4|php5|phtml|phar|asp|aspx|jsp|sh|py|pl)$\">\n  Order allow,deny\n  Deny from all\n</FilesMatch>");
    }

    return 'uploads/' . date('Y/m/') . $name;
}

// ============================================================
// AVATAR GENERATION
// ============================================================
function generateUserAvatar(string $username, int $userId): string {
    $dir = UPLOAD_DIR . 'avatars/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $colors = ['#e74c3c','#3498db','#2ecc71','#9b59b6','#f39c12','#1abc9c','#e67e22','#e91e63'];
    $bg   = $colors[$userId % count($colors)];
    $letter = strtoupper(mb_substr($username, 0, 1));
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">
  <rect width="80" height="80" rx="40" fill="{$bg}"/>
  <text x="40" y="54" font-size="36" font-family="Arial,sans-serif" font-weight="bold"
        fill="white" text-anchor="middle">{$letter}</text>
</svg>
SVG;
    $fname = 'avatar_' . $userId . '_' . time() . '.svg';
    file_put_contents($dir . $fname, $svg);
    return 'uploads/avatars/' . $fname;
}

// ============================================================
// TELEGRAM — no curl, uses file_get_contents fallback
// ============================================================
function sendTelegram(string $message, string $chatId = null, array $keyboard = null): bool {
    $chatId = $chatId ?? TELEGRAM_ADMIN_CHAT_ID;
    $token  = TELEGRAM_BOT_TOKEN;
    if (!$token || !$chatId) return false;

    $payload = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML'];
    if ($keyboard) {
        $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $url  = "https://api.telegram.org/bot{$token}/sendMessage";

    // Dùng curl (nhanh hơn, timeout ngắn)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($err) { error_log("Telegram curl: $err"); return false; }
        $resp = json_decode($result, true);
        if (!($resp['ok'] ?? false)) error_log("Telegram: " . ($resp['description'] ?? $result));
        return $resp['ok'] ?? false;
    }

    // Fallback file_get_contents
    $opts = [
        'http' => ['method' => 'POST', 'header' => "Content-Type: application/json", 'content' => $json, 'timeout' => 5, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ];
    try {
        $result = @file_get_contents($url, false, stream_context_create($opts));
        if ($result === false) { error_log("Telegram: request failed"); return false; }
        $resp = json_decode($result, true);
        if (!($resp['ok'] ?? false)) error_log("Telegram: " . ($resp['description'] ?? $result));
        return $resp['ok'] ?? false;
    } catch (\Throwable $e) {
        error_log("Telegram: " . $e->getMessage());
        return false;
    }
}


function notifyNewUser(array $user): void {
    $ip  = getUserIP();
    $msg = "<b>[NEW] Nguoi dung moi dang ky</b>\n"
         . "[User] Username: <code>" . htmlspecialchars($user['username']) . "</code>\n"
         . "[Mail] Email: <code>" . htmlspecialchars($user['email']) . "</code>\n"
         . "[Date] " . date('d/m/Y H:i:s') . "  |  [IP] {$ip}";
    sendTelegram($msg);
}

function notifyNewProduct(array $product, array $user): void {
    $msg = "<b>[SHOP] San pham moi</b>\n"
         . "[Box] <code>" . htmlspecialchars($product['title']) . "</code>\n"
         . "[User] " . htmlspecialchars($user['username']) . "\n"
         . "[VND] " . formatPrice($product['price'], $product['currency'] ?? 'VND') . "\n"
         . "[Date] " . date('d/m/Y H:i:s');
    sendTelegram($msg);
}

// Send admin access request with Telegram button
function sendAdminAccessRequest(array $user, string $token): void {
    $ip  = getUserIP();
    $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100);
    $url = SITE_URL . "/admin/admin.php?token={$token}";
    $msg = "<b>[ADMIN] Yeu cau vao Admin Panel</b>\n"
         . "[User] <code>" . htmlspecialchars($user['username']) . "</code>\n"
         . "[IP] IP: <code>{$ip}</code>\n"
         . "[UA] UA: <code>" . htmlspecialchars($ua) . "</code>\n"
         . "[Date] " . date('d/m/Y H:i:s') . "\n\n"
         . "[!] An nut ben duoi de xac thuc (het han sau 5 phut):";
    // callback_data format: "verify_admin:TOKEN" — webhook will mark token as used
    $keyboard = [[
        ['text' => '[OK] Verify - Cho phep', 'callback_data' => 'verify_admin:' . $token],
        ['text' => '[X] Block - Tu choi', 'callback_data' => 'deny_admin:' . $token],
    ]];
    sendTelegram($msg, null, $keyboard);
}

function getUserIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ============================================================
// TRAFFIC LOG
// ============================================================
function logTraffic(string $page, string $action = 'view', array $extra = []): void {
    try {
        $db = getDB();
        $user = currentUser();
        $ip   = getUserIP();
        $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $stmt = $db->prepare("INSERT INTO traffic_logs (user_id, ip_address, user_agent, page, action, extra) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$user['id'] ?? null, $ip, $ua, $page, $action, $extra ? json_encode($extra) : null]);
    } catch (\Throwable $e) { /* silent */ }
}

// ============================================================
// AUTO-INSTALL
// ============================================================
if (!file_exists(__DIR__ . '/.installed')) {
    installDatabase();
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
}

// Always run migrations
(function() {
    try { getDB()->exec("ALTER TABLE messages ADD COLUMN reply_to INT DEFAULT NULL"); } catch(\Throwable $e) {}
    try { getDB()->exec("CREATE TABLE IF NOT EXISTS admin_tokens (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(64) UNIQUE NOT NULL, ip_address VARCHAR(45), used TINYINT(1) DEFAULT 0, expires_at TIMESTAMP NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); } catch(\Throwable $e) {}
    // Fix role ENUM — nếu DB cũ chưa có 'new' hoặc cần sync
    try { getDB()->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user','premium','admin') DEFAULT 'user'"); } catch(\Throwable $e) {}
    // Fix status column nếu chưa có
    try { getDB()->exec("ALTER TABLE users ADD COLUMN status ENUM('active','banned','suspended') DEFAULT 'active'"); } catch(\Throwable $e) {}
    // Update existing users có role=null hoặc rỗng
    try { getDB()->exec("UPDATE users SET role='user' WHERE role IS NULL OR role=''"); } catch(\Throwable $e) {}
    try { getDB()->exec("UPDATE users SET status='active' WHERE status IS NULL OR status=''"); } catch(\Throwable $e) {}
    // Fix messages read_at column
    try { getDB()->exec("ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL"); } catch(\Throwable $e) {}
    // Fix messages is_read for backward compat
    try { getDB()->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0"); } catch(\Throwable $e) {}
})();
