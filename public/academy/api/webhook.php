<?php
declare(strict_types=1);

/**
 * LINE Messaging API Webhook
 *   LINE Developers の Webhook URL に設定：
 *   https://asksystem.jp/academy/api/webhook.php
 *
 *   - follow: 友だち追加時に連携方法を案内
 *   - message(text): 連携コードなら保護者アカウントに line_user_id を紐付け
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/line.php';

$body = file_get_contents('php://input') ?: '';

// 署名検証（不正・未設定は拒否）
if (!line_verify_signature($body)) {
    http_response_code(403);
    echo 'invalid signature';
    exit;
}

$data = json_decode($body, true);
$events = is_array($data) ? ($data['events'] ?? []) : [];

try {
    $pdo = ada_db();
    foreach ($events as $ev) {
        $type = $ev['type'] ?? '';
        $lineUserId = $ev['source']['userId'] ?? '';
        $replyToken = $ev['replyToken'] ?? '';

        if ($type === 'follow' && $lineUserId !== '') {
            line_reply(
                $replyToken,
                "友だち追加ありがとうございます。\nマイページの「LINE連携」に表示される連携コードをこのトークに送信すると、お子さまの入退室通知などを受け取れます。"
            );
            continue;
        }

        if ($type === 'message' && ($ev['message']['type'] ?? '') === 'text' && $lineUserId !== '') {
            $text = trim((string)($ev['message']['text'] ?? ''));
            $code = strtoupper(preg_replace('/\s+/', '', $text));

            $st = $pdo->prepare("SELECT id, display_name FROM users WHERE role = 'parent' AND line_link_code = ? LIMIT 1");
            $st->execute([$code]);
            $p = $st->fetch();

            if ($p) {
                $pdo->prepare('UPDATE users SET line_user_id = ?, line_link_code = NULL WHERE id = ?')
                    ->execute([$lineUserId, (int)$p['id']]);
                line_reply($replyToken, $p['display_name'] . "様のアカウントと連携しました。\n今後、入退室やお知らせをこちらにお送りします。");
            } else {
                line_reply($replyToken, "連携コードが確認できませんでした。\nマイページの「LINE連携」に表示されるコードをそのまま送信してください。");
            }
        }
    }
} catch (Throwable $e) {
    // Webhookは常に200で返す（LINE側の再送を防ぐ）
}

http_response_code(200);
echo 'ok';
