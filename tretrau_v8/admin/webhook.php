<?php
/**
 * Telegram Webhook — xử lý callback từ inline buttons
 *
 * Đăng ký 1 lần duy nhất (thay YOURDOMAIN):
 * https://api.telegram.org/botTOKEN/setWebhook?url=https://YOURDOMAIN.COM/admin/webhook.php
 */
require_once '../config.php';

// GET ?debug=1 để kiểm tra webhook còn sống
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'webhook alive', 'time' => date('Y-m-d H:i:s'), 'bot' => TELEGRAM_BOT_TOKEN ? 'configured' : 'MISSING']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input  = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) { error_log('Webhook: invalid JSON: ' . $input); http_response_code(400); exit; }
error_log('Webhook received: ' . json_encode(array_keys($update)));

$botToken = TELEGRAM_BOT_TOKEN;
$db       = getDB();

// ── Helpers ──────────────────────────────────────────────────

function tgPost(string $method, array $data): ?array {
    global $botToken;
    $json = json_encode($data);
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($json),
            'content' => $json,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ];
    $result = @file_get_contents(
        "https://api.telegram.org/bot{$botToken}/{$method}",
        false,
        stream_context_create($opts)
    );
    return $result ? json_decode($result, true) : null;
}

function answerCallback(string $cbId, string $text = '', bool $alert = false): void {
    tgPost('answerCallbackQuery', [
        'callback_query_id' => $cbId,
        'text'              => $text,
        'show_alert'        => $alert,
    ]);
}

function editMessage(string|int $chatId, int $msgId, string $text): void {
    tgPost('editMessageText', [
        'chat_id'    => $chatId,
        'message_id' => $msgId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ]);
}

// ── Xử lý callback_query ─────────────────────────────────────

if (isset($update['callback_query'])) {
    $cb      = $update['callback_query'];
    $cbId    = $cb['id'];
    $data    = $cb['data'] ?? '';
    $chatId  = (string)($cb['message']['chat']['id'] ?? 0); // string để tránh overflow
    $msgId   = (int)($cb['message']['message_id'] ?? 0);
    $from    = '@' . ($cb['from']['username'] ?? $cb['from']['first_name'] ?? 'Admin');

    // Bảo vệ: chỉ xử lý từ đúng chat admin (dùng string để tránh int overflow với group ID âm)
    if (trim((string)$chatId) !== trim((string)TELEGRAM_ADMIN_CHAT_ID)) {
        answerCallback($cbId, 'Khong co quyen!', true);
        http_response_code(200); exit;
    }

    // ── [OK] VERIFY ──────────────────────────────────────────
    if (str_starts_with($data, 'verify_admin:')) {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', substr($data, 13));

        $stmt = $db->prepare(
            "UPDATE admin_tokens SET used=1 WHERE token=? AND used=0 AND expires_at > NOW()"
        );
        $stmt->execute([$token]);

        if ($stmt->rowCount() > 0) {
            answerCallback($cbId, 'Da xac thuc! Admin se tu dong vao panel...', false);
            editMessage($chatId, $msgId,
                "<b>[OK] DA XAC THUC</b>\n" .
                "Token: <code>" . substr($token, 0, 12) . "...</code>\n" .
                "Xac thuc boi: {$from}\n" .
                "[Date] " . date('d/m/Y H:i:s')
            );
        } else {
            answerCallback($cbId, 'Token da het han hoac da duoc su dung!', true);
        }
    }

    // ── [X] BLOCK ────────────────────────────────────────────
    elseif (str_starts_with($data, 'deny_admin:')) {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', substr($data, 11));

        $db->prepare("DELETE FROM admin_tokens WHERE token=?")->execute([$token]);

        answerCallback($cbId, 'Da tu choi truy cap admin!', false);
        editMessage($chatId, $msgId,
            "<b>[X] DA TU CHOI</b>\n" .
            "Token: <code>" . substr($token, 0, 12) . "...</code>\n" .
            "Tu choi boi: {$from}\n" .
            "[Date] " . date('d/m/Y H:i:s')
        );
    }

    // Legacy
    elseif ($data === 'deny_admin') {
        answerCallback($cbId, 'Da tu choi!', false);
    }
}

http_response_code(200);
echo 'OK';
