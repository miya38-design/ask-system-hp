<?php
declare(strict_types=1);

/**
 * 認証API（セッション方式）
 *   POST /academy/api/auth.php?action=login   body: {email, password}
 *   POST /academy/api/auth.php?action=logout
 *   GET  /academy/api/auth.php?action=me
 */
require_once __DIR__ . '/lib.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            require_method('POST');
            $b = json_body();
            $email = trim((string)($b['email'] ?? ''));
            $pw    = (string)($b['password'] ?? '');
            $accountId = (int)($b['account_id'] ?? 0); // 同一メール複数時の選択
            if ($email === '' || $pw === '') {
                json_out(['ok' => false, 'error' => 'email と password は必須です'], 400);
            }
            $st = ada_db()->prepare('SELECT * FROM users WHERE email = ?');
            $st->execute([$email]);
            $rows = $st->fetchAll();
            // パスワード一致するアカウントを抽出
            $matched = array_values(array_filter($rows, fn($r) => password_verify($pw, $r['password_hash'])));
            if ($accountId > 0) {
                $matched = array_values(array_filter($matched, fn($r) => (int)$r['id'] === $accountId));
            }
            if (count($matched) === 0) {
                json_out(['ok' => false, 'error' => 'メールアドレスまたはパスワードが違います'], 401);
            }
            // 在籍状態が active 以外はログイン不可（休会/退会/無効化）
            $matched = array_values(array_filter($matched, fn($r) => ($r['status'] ?? 'active') === 'active'));
            if (count($matched) === 0) {
                json_out(['ok' => false, 'error' => 'このアカウントは現在ご利用いただけません。教室までお問い合わせください。'], 403);
            }
            if (count($matched) > 1) {
                // 同一メール＆同一パスワードの複数アカウント → 選択させる
                $choices = array_map(fn($r) => [
                    'id' => (int)$r['id'], 'role' => $r['role'], 'display_name' => $r['display_name'],
                ], $matched);
                json_out(['ok' => false, 'choose' => $choices]);
            }
            $u = $matched[0];
            ada_session_start();
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            unset($u['password_hash']);
            json_out(['ok' => true, 'user' => $u]);
            break;

        case 'logout':
            require_method('POST');
            ada_session_start();
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            json_out(['ok' => true]);
            break;

        case 'me':
            json_out(['ok' => true, 'user' => current_user()]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
