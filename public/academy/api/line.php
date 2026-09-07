<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** [token, secret] を返す */
function line_cfg(): array
{
    $c = ada_config();
    return [(string)($c['line_channel_token'] ?? ''), (string)($c['line_channel_secret'] ?? '')];
}

/** Webhook署名検証（X-Line-Signature） */
function line_verify_signature(string $body): bool
{
    [, $secret] = line_cfg();
    if ($secret === '') {
        return false;
    }
    $sig = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
    $hash = base64_encode(hash_hmac('sha256', $body, $secret, true));
    return is_string($sig) && hash_equals($hash, $sig);
}

/** LINE APIへPOST。成功(HTTP 2xx)なら true, 失敗なら false。$err に理由 */
function line_api(string $url, array $payload, ?string &$err = null): bool
{
    [$token] = line_cfg();
    if ($token === '') {
        $err = 'no_token';
        return false;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false) {
            $err = 'curl:' . curl_error($ch);
        } elseif ($code < 200 || $code >= 300) {
            $err = 'http:' . $code . ' ' . substr((string)$res, 0, 120);
        }
        curl_close($ch);
        return $err === null;
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
        'content' => $json,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) { $err = 'stream_failed'; return false; }
    return true;
}

/** 指定LINEユーザーへプッシュ送信。成功可否を返す */
function line_push(string $to, string $text, ?string &$err = null): bool
{
    if ($to === '') { $err = 'no_recipient'; return false; }
    return line_api('https://api.line.me/v2/bot/message/push', [
        'to'       => $to,
        'messages' => [['type' => 'text', 'text' => $text]],
    ], $err);
}

/** 通知送信＋ログ記録（④）。成功可否を返す */
function ada_notify(string $to, string $text, string $event, ?int $studentId = null): bool
{
    $err = null;
    $ok = line_push($to, $text, $err);
    try {
        ada_db()->prepare(
            'INSERT INTO notification_logs (event, to_line_id, student_id, body, status, error)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$event, $to, $studentId, $text, $ok ? 'sent' : 'failed', $ok ? null : substr((string)$err, 0, 255)]);
    } catch (Throwable $e) { /* ログ失敗は無視 */ }
    return $ok;
}

/** Webhookの応答（replyToken使用） */
function line_reply(string $replyToken, string $text): void
{
    if ($replyToken === '') {
        return;
    }
    line_api('https://api.line.me/v2/bot/message/reply', [
        'replyToken' => $replyToken,
        'messages'   => [['type' => 'text', 'text' => $text]],
    ]);
}
