<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * 日本語メール送信（info@ask-system.net から）。
 * 送信可否を bool で返す（失敗しても例外は投げない）。
 */
function ada_send_mail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $cfg = ada_config();
    $from     = (string)($cfg['mail_from'] ?? 'info@ask-system.net');
    $fromName = (string)($cfg['mail_from_name'] ?? 'ASKデジタルアカデミー');

    if (function_exists('mb_send_mail')) {
        mb_language('ja');
        mb_internal_encoding('UTF-8');
        $headers = 'From: ' . mb_encode_mimeheader($fromName) . ' <' . $from . ">\r\n"
                 . 'Reply-To: ' . $from;
        return @mb_send_mail($to, $subject, $body, $headers, '-f' . $from);
    }

    // フォールバック（mbstring不可時）
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\n"
             . 'Reply-To: ' . $from . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . 'Content-Transfer-Encoding: base64';
    return @mail($to, $encSubject, base64_encode($body), $headers, '-f' . $from);
}
