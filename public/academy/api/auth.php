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
            if ($email === '' || $pw === '') {
                json_out(['ok' => false, 'error' => 'email と password は必須です'], 400);
            }
            $st = ada_db()->prepare('SELECT * FROM users WHERE email = ?');
            $st->execute([$email]);
            $u = $st->fetch();
            if (!$u || !password_verify($pw, $u['password_hash'])) {
                json_out(['ok' => false, 'error' => 'メールアドレスまたはパスワードが違います'], 401);
            }
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
