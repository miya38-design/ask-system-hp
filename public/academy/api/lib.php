<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** JSONレスポンスを返して終了 */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** リクエストボディ(JSON)を配列で取得 */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/** 指定メソッド以外は405 */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
}

/** セッション開始（httponly / https時secure / SameSite=Lax） */
function ada_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('ada_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** 管理トークン（config.php の admin_token）を検証。migrate/setup 用 */
function require_admin_token(): void
{
    $cfg = ada_config();
    $token = (string)($cfg['admin_token'] ?? '');
    $given = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
    if ($token === '' || !hash_equals($token, $given)) {
        json_out(['ok' => false, 'error' => 'forbidden (invalid admin token)'], 403);
    }
}

/** ログイン中ユーザー（未ログインなら null） */
function current_user(): ?array
{
    ada_session_start();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = ada_db()->prepare(
        'SELECT id, email, display_name, role, parent_id, level, exp, exp_to_next,
                course_type, plan, area, created_at
         FROM users WHERE id = ?'
    );
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    return $u ?: null;
}

/** 未ログインなら401 */
function require_auth(): array
{
    $u = current_user();
    if (!$u) {
        json_out(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    return $u;
}

/** 指定ロール以外は403 */
function require_role(array $user, string ...$roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        json_out(['ok' => false, 'error' => 'forbidden'], 403);
    }
}
