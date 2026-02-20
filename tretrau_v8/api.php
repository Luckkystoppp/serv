<?php
// Capture any stray output (warnings, notices) so JSON is never corrupted
ob_start();

// Suppress display of errors — log them instead
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once 'config.php';
startSession();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user = currentUser();

// Discard any output that happened during bootstrap (warnings etc.)
ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
switch ($action) {

    // ── AUTH ──────────────────────────────────────────────────────
    case 'register':
        if ($user) jsonResponse(['error' => 'Already logged in']);
        if (!rateLimit('register_' . getUserIP(), 3, 300))
            jsonResponse(['error' => 'Đăng ký quá nhanh. Vui lòng đợi.'], 429);
        // Captcha check
        if (!verifyCaptcha($_POST['captcha'] ?? ''))
            jsonResponse(['error' => 'Mã xác minh sai. Vui lòng thử lại.'], 400);
        $username = sanitizeInput($_POST['username'] ?? '');
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pass     = $_POST['password'] ?? '';
        if (!$username || !$email || strlen($pass) < 6) jsonResponse(['error' => 'Thiếu thông tin hoặc mật khẩu quá ngắn'], 400);
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) jsonResponse(['error' => 'Username chỉ gồm chữ cái, số, dấu gạch dưới (3-30 ký tự)'], 400);
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?,?,?,'user','active')");
            $stmt->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $newUserId = (int)$db->lastInsertId();
            // Generate default avatar — tách riêng để lỗi file không crash register
            try {
                $avatarPath = generateUserAvatar($username, $newUserId);
                $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatarPath, $newUserId]);
            } catch (\Throwable $avatarErr) {
                error_log("Avatar gen error: " . $avatarErr->getMessage());
            }
            $_SESSION['user_id'] = $newUserId;
            // Notify — không blocking
            try { notifyNewUser(['username' => $username, 'email' => $email, 'id' => $newUserId]); } catch (\Throwable $ne) {}
            jsonResponse(['success' => true, 'redirect' => 'index.php']);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) jsonResponse(['error' => 'Username hoặc email đã tồn tại'], 400);
            error_log('Register PDO: ' . $e->getMessage());
            jsonResponse(['error' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
        break;

    case 'get_captcha':
        jsonResponse(['q' => getCaptchaQuestion()]);
        break;

    case 'login':
        if (!rateLimit('login_' . getUserIP(), 5, 60))
            jsonResponse(['error' => 'Quá nhiều lần thử. Vui lòng đợi 1 phút.'], 429);
        $identifier = trim($_POST['identifier'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$identifier || !$pass) jsonResponse(['error' => 'Thiếu thông tin'], 400);
        // Captcha check
        if (!verifyCaptcha($_POST['captcha'] ?? ''))
            jsonResponse(['error' => 'Mã xác minh sai. Vui lòng thử lại.'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND status='active'");
        $stmt->execute([$identifier, $identifier]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($pass, $u['password_hash'])) jsonResponse(['error' => 'Sai thông tin đăng nhập'], 401);
        $_SESSION['user_id'] = $u['id'];
        $db->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$u['id']]);
        jsonResponse(['success' => true, 'redirect' => 'index.php']);
        break;

    // ── PRODUCTS ──────────────────────────────────────────────────
    case 'post_product':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        if (!verifyCSRF($_POST['csrf'] ?? '')) jsonResponse(['error' => 'Invalid CSRF'], 403);
        $limit = getDailyPostLimit($user);
        $todayCount = getTodayPostCount($user['id']);
        if ($todayCount >= $limit) jsonResponse(['error' => "Bạn đã đạt giới hạn {$limit} bài/ngày"], 429);
        $title = sanitizeTitle($_POST['title'] ?? '');
        $desc  = sanitizeDescription($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $cat   = sanitizeInput($_POST['category'] ?? 'other');
        if (!$title || !$desc || $price <= 0) jsonResponse(['error' => 'Thiếu thông tin bài đăng'], 400);
        try {
            $imgPath = handleImageUpload('image');
            if (!$imgPath) jsonResponse(['error' => 'Vui lòng chọn ảnh sản phẩm'], 400);
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO products (user_id, title, description, price, image, category, status) VALUES (?,?,?,?,?,?,'active')");
            $stmt->execute([$user['id'], $title, $desc, $price, $imgPath, $cat]);
            $pid = $db->lastInsertId();
            notifyNewProduct(['title'=>$title,'price'=>$price,'currency'=>'VND'], $user);
            jsonResponse(['success' => true, 'redirect' => "index.php?page=product&id={$pid}"]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
        break;

    case 'delete_product':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $pid = (int)($_POST['product_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch();
        if (!$prod) jsonResponse(['error' => 'Sản phẩm không tồn tại'], 404);
        if ($prod['user_id'] != $user['id'] && $user['role'] !== 'admin') jsonResponse(['error' => 'Không có quyền'], 403);
        $db->prepare("UPDATE products SET status='deleted', deleted_at=NOW() WHERE id=?")->execute([$pid]);
        jsonResponse(['success' => true]);
        break;

    case 'buy_product':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $pid = (int)($_POST['product_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM products WHERE id=? AND status='active'");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch();
        if (!$prod) jsonResponse(['error' => 'Sản phẩm không tồn tại'], 404);
        if ($prod['user_id'] == $user['id']) jsonResponse(['error' => 'Không thể mua sản phẩm của chính mình'], 400);
        // Create or find channel (use INSERT IGNORE to handle race conditions)
        $channelKey = hash('sha256', $pid . ':' . $user['id']);
        $db->prepare("INSERT IGNORE INTO chat_channels (product_id, buyer_id, seller_id, channel_key) VALUES (?,?,?,?)")
           ->execute([$pid, $user['id'], $prod['user_id'], $channelKey]);
        $newChannel = (int)$db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM chat_channels WHERE channel_key=?");
        $stmt->execute([$channelKey]);
        $channel = $stmt->fetch();
        $channelId = $channel['id'];

        // Only send system message if channel was just created
        if ($newChannel > 0) {
            $db->prepare("INSERT INTO messages (channel_id, sender_id, content, type) VALUES (?,?,?,'system')")
               ->execute([$channelId, $user['id'], "Người mua quan tâm đến sản phẩm: " . $prod['title']]);
        }
        jsonResponse(['success' => true, 'channel_id' => $channelId, 'redirect' => "index.php?page=chat&channel={$channelId}"]);
        break;

    // ── CHAT ──────────────────────────────────────────────────────
    case 'send_message':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $channelId  = (int)($_POST['channel_id'] ?? 0);
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $content    = trim($_POST['content'] ?? '');
        $isGlobal   = (int)($_POST['is_global'] ?? 0);
        $isDm       = (int)($_POST['is_dm'] ?? 0);
        $replyTo    = (int)($_POST['reply_to'] ?? 0) ?: null;

        $imgPath = null;
        if (!empty($_FILES['image']['name'])) {
            try { $imgPath = handleImageUpload('image'); } catch (Exception $e) {
                jsonResponse(['error' => $e->getMessage()], 400);
            }
        }
        if (!$content && !$imgPath) jsonResponse(['error' => 'Tin nhắn trống'], 400);

        $type = $imgPath ? 'image' : 'text';

        // Verify channel access
        if ($channelId) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM chat_channels WHERE id=?");
            $stmt->execute([$channelId]);
            $ch = $stmt->fetch();
            if (!$ch || ($ch['buyer_id'] != $user['id'] && $ch['seller_id'] != $user['id'])) {
                jsonResponse(['error' => 'Không có quyền truy cập kênh này'], 403);
            }
        }

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO messages (channel_id, sender_id, receiver_id, content, image, type, is_global, is_dm, reply_to) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$channelId ?: null, $user['id'], $receiverId ?: null, $content ?: null, $imgPath, $type, $isGlobal, $isDm, $replyTo]);
        $msgId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT m.*, u.username, u.avatar,
                   rm.content as reply_content, rm.type as reply_type, rm.image as reply_image,
                   ru.username as reply_username
            FROM messages m
            JOIN users u ON u.id=m.sender_id
            LEFT JOIN messages rm ON rm.id=m.reply_to
            LEFT JOIN users ru ON ru.id=rm.sender_id
            WHERE m.id=?
        ");
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();
        jsonResponse(['success' => true, 'message' => $msg]);
        break;

    case 'buy_premium':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        // Find admin user phuvanduc to start DM
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username FROM users WHERE username='phuvanduc' AND role='admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        if (!$admin) {
            // fallback: any admin
            $stmt = $db->prepare("SELECT id, username FROM users WHERE role='admin' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch();
        }
        if (!$admin) jsonResponse(['error' => 'Không tìm thấy admin'], 404);
        jsonResponse(['success' => true, 'redirect' => "index.php?page=chat&dm={$admin['id']}&name=" . urlencode($admin['username'])]);
        break;

    case 'recall_message':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $msgId = (int)($_POST['message_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM messages WHERE id=?");
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();
        if (!$msg || $msg['sender_id'] != $user['id']) jsonResponse(['error' => 'Không có quyền'], 403);
        if (time() - strtotime($msg['created_at']) > 3600) jsonResponse(['error' => 'Chỉ có thể thu hồi trong vòng 1 giờ'], 400);
        $db->prepare("UPDATE messages SET recalled=1, recalled_at=NOW() WHERE id=?")->execute([$msgId]);
        jsonResponse(['success' => true]);
        break;

    case 'delete_message_self':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $msgId = (int)($_POST['message_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM messages WHERE id=?");
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();
        if (!$msg || $msg['sender_id'] != $user['id']) jsonResponse(['error' => 'Không có quyền'], 403);
        $db->prepare("DELETE FROM messages WHERE id=?")->execute([$msgId]);
        jsonResponse(['success' => true]);
        break;

    case 'get_messages':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $channelId = (int)($_GET['channel_id'] ?? 0);
        $after     = (int)($_GET['after'] ?? 0);
        $isGlobal  = (int)($_GET['is_global'] ?? 0);
        $isDm      = (int)($_GET['is_dm'] ?? 0);
        $peerId    = (int)($_GET['peer_id'] ?? 0);
        $db = getDB();

        $baseSelect = "SELECT m.*, u.username, u.avatar, u.role as sender_role,
                   rm.content as reply_content, rm.type as reply_type, rm.image as reply_image,
                   ru.username as reply_username
            FROM messages m
            JOIN users u ON u.id=m.sender_id
            LEFT JOIN messages rm ON rm.id=m.reply_to
            LEFT JOIN users ru ON ru.id=rm.sender_id";

        if ($isGlobal) {
            $stmt = $db->prepare("$baseSelect WHERE m.is_global=1 AND m.id > ? ORDER BY m.created_at DESC LIMIT 50");
            $stmt->execute([$after]);
        } elseif ($isDm && $peerId) {
            $stmt = $db->prepare("$baseSelect WHERE m.is_dm=1 AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)) AND m.id > ? ORDER BY m.created_at DESC LIMIT 50");
            $stmt->execute([$user['id'], $peerId, $peerId, $user['id'], $after]);
        } else {
            $stmt = $db->prepare("$baseSelect WHERE m.channel_id=? AND m.id > ? ORDER BY m.created_at DESC LIMIT 50");
            $stmt->execute([$channelId, $after]);
        }
        $msgs = array_reverse($stmt->fetchAll());
        jsonResponse(['messages' => $msgs]);
        break;

    // ── ACCOUNT ───────────────────────────────────────────────────
    case 'update_account':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        if (!verifyCSRF($_POST['csrf'] ?? '')) jsonResponse(['error' => 'Invalid CSRF'], 403);
        $db = getDB();
        $bio = mb_substr(strip_tags($_POST['bio'] ?? ''), 0, 300);
        $newUsername = sanitizeInput($_POST['username'] ?? '');

        if ($newUsername && $newUsername !== $user['username']) {
            // Check username change cooldown (1 week)
            if ($user['username_changed_at'] && strtotime($user['username_changed_at']) > time() - 604800) {
                $nextChange = date('d/m/Y H:i', strtotime($user['username_changed_at']) + 604800);
                jsonResponse(['error' => "Bạn chỉ có thể đổi tên sau ngày {$nextChange}"], 400);
            }
            if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newUsername)) jsonResponse(['error' => 'Username không hợp lệ'], 400);
            try {
                $db->prepare("UPDATE users SET username=?, bio=?, username_changed_at=NOW() WHERE id=?")->execute([$newUsername, $bio, $user['id']]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) jsonResponse(['error' => 'Username đã tồn tại'], 400);
                jsonResponse(['error' => 'Lỗi hệ thống'], 500);
            }
        } else {
            $db->prepare("UPDATE users SET bio=? WHERE id=?")->execute([$bio, $user['id']]);
        }

        // Update avatar
        if (!empty($_FILES['avatar']['name'])) {
            try {
                $avatarPath = handleImageUpload('avatar');
                if ($avatarPath) $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatarPath, $user['id']]);
            } catch (Exception $e) { jsonResponse(['error' => $e->getMessage()], 400); }
        }
        jsonResponse(['success' => true]);
        break;

    case 'report_user':
        if (!$user) jsonResponse(['error' => 'Chưa đăng nhập'], 401);
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        if (!$targetId || !$reason) jsonResponse(['error' => 'Thiếu thông tin'], 400);
        $db = getDB();
        $db->prepare("INSERT INTO reports (reporter_id, target_user_id, reason) VALUES (?,?,?)")->execute([$user['id'], $targetId, $reason]);
        jsonResponse(['success' => true]);
        break;

    case 'get_user_profile':
        $uid = (int)($_GET['user_id'] ?? 0);
        if (!$uid) jsonResponse(['error' => 'Invalid user'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, bio, avatar, role, created_at FROM users WHERE id=? AND status='active'");
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        if (!$u) jsonResponse(['error' => 'Không tìm thấy người dùng'], 404);
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM products WHERE user_id=? AND status='active'");
        $stmt2->execute([$uid]);
        $u['product_count'] = $stmt2->fetchColumn();
        jsonResponse(['user' => $u]);
        break;

    case 'get_products':
        $cat    = sanitizeInput($_GET['category'] ?? '');
        $search = sanitizeInput($_GET['search'] ?? '');
        $page_n = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 12;
        $offset = ($page_n - 1) * $limit;
        $db = getDB();
        $where = "WHERE p.status='active'";
        $params = [];
        if ($cat) { $where .= " AND p.category=?"; $params[] = $cat; }
        if ($search) { $where .= " AND (p.title LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $stmt = $db->prepare("SELECT p.*, u.username, u.avatar FROM products p JOIN users u ON u.id=p.user_id $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        // Add formatted fields
        foreach ($products as &$p) {
            $p['price_fmt'] = formatPrice($p['price'], $p['currency'] ?? 'vnd');
            $p['time_ago'] = timeAgo($p['created_at']);
            $p['avatar'] = getAvatarUrl($p['avatar']);
        }
        unset($p);
        jsonResponse(['products' => $products]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
} catch (Throwable $e) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}

// Global exception safety net
