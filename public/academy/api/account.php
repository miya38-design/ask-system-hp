<?php
declare(strict_types=1);

/**
 * 自分のアカウント設定（ログイン中の本人のみ）
 *   POST ?action=change_email     {email, current_password}
 *   POST ?action=change_password  {current_password, new_password}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

function verify_current(PDO $pdo, int $id, string $pw): bool
{
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$id]);
    $h = $st->fetchColumn();
    return is_string($h) && $h !== '' && password_verify($pw, $h);
}

try {
    switch ($action) {
        case 'change_email':
            require_method('POST');
            $b = json_body();
            $email = trim((string)($b['email'] ?? ''));
            $cur   = (string)($b['current_password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_out(['ok' => false, 'error' => 'メールアドレスの形式が正しくありません'], 400);
            }
            if (!verify_current($pdo, (int)$me['id'], $cur)) {
                json_out(['ok' => false, 'error' => '現在のパスワードが違います'], 401);
            }
            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, (int)$me['id']]);
            json_out(['ok' => true, 'email' => $email]);
            break;

        case 'change_password':
            require_method('POST');
            $b = json_body();
            $cur = (string)($b['current_password'] ?? '');
            $new = (string)($b['new_password'] ?? '');
            if (strlen($new) < 8) {
                json_out(['ok' => false, 'error' => '新しいパスワードは8文字以上にしてください'], 400);
            }
            if (!verify_current($pdo, (int)$me['id'], $cur)) {
                json_out(['ok' => false, 'error' => '現在のパスワードが違います'], 401);
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
