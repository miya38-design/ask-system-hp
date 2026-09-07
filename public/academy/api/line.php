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

/** LINE APIへPOST（失敗しても例外は投げない） */
function line_api(string $url, array $payload): void
{
    [$token] = line_cfg();
    if ($token === '') {
        return;
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
        curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
            'content' => $json,
            'timeout' => 10,
        ]]);
        @file_get_contents($url, false, $ctx);
    }
}

/** 指定LINEユーザーへプッシュ送信 */
function line_push(string $to, string $text): void
{
    if ($to === '') {
        return;
    }
    line_api('https://api.line.me/v2/bot/message/push', [
        'to'       => $to,
        'messages' => [['type' => 'text', 'text' => $text]],
    ]);
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
