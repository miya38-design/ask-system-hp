<?php
declare(strict_types=1);

/**
 * 管理API（講師=instructor のみ）。セッション認証必須。
 *   GET  ?action=list_users
 *   POST ?action=create_user     {email,password,display_name,role,parent_id?,course_type?,plan?,area?}
 *   POST ?action=reset_password  {id,password}
 *   POST ?action=delete_user     {id}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
require_role($me, 'instructor');

$action = $_GET['action'] ?? '';
$ROLES = ['parent', 'student', 'instructor'];

try {
    $pdo = ada_db();

    switch ($action) {
        case 'list_users':
            $rows = $pdo->query(
                "SELECT u.id, u.email, u.display_name, u.role, u.parent_id,
                        p.display_name AS parent_name,
                        u.course_type, u.plan, u.area, u.level, u.exp, u.created_at
                 FROM users u
                 LEFT JOIN users p ON p.id = u.parent_id
                 ORDER BY FIELD(u.role,'instructor','parent','student'), u.created_at DESC"
            )->fetchAll();
            json_out(['ok' => true, 'users' => $rows]);
            break;

        case 'create_user':
            require_method('POST');
            $b = json_body();
            $email = trim((string)($b['email'] ?? ''));
            $pw    = (string)($b['password'] ?? '');
            $name  = trim((string)($b['display_name'] ?? ''));
            $role  = (string)($b['role'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $ROLES, true)) {
                json_out(['ok' => false, 'error' => '入力内容を確認してください（氏名・メール・ロール）'], 400);
            }
            if (strlen($pw) < 8) {
                json_out(['ok' => false, 'error' => 'パスワードは8文字以上にしてください'], 400);
            }
            $parentId = null;
            $courseType = null;
            $plan = null;
            $area = trim((string)($b['area'] ?? '')) ?: null;
            if ($role === 'student') {
                $courseType = in_array(($b['course_type'] ?? ''), ['junior', 'senior'], true) ? $b['course_type'] : null;
                $plan = in_array(($b['plan'] ?? ''), ['standard-a', 'standard-b', 'premium'], true) ? $b['plan'] : null;
                if (!empty($b['parent_id'])) {
                    $st = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'parent'");
                    $st->execute([(int)$b['parent_id']]);
                    if (!$st->fetch()) {
                        json_out(['ok' => false, 'error' => '指定した保護者が見つかりません'], 400);
                    }
                    $parentId = (int)$b['parent_id'];
                }
            }
            $qrToken = $role === 'student' ? bin2hex(random_bytes(16)) : null;
            try {
                $st = $pdo->prepare(
                    'INSERT INTO users (email, password_hash, display_name, role, parent_id, course_type, plan, area, qr_token)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $st->execute([$email, password_hash($pw, PASSWORD_DEFAULT), $name, $role, $parentId, $courseType, $plan, $area, $qrToken]);
                json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    json_out(['ok' => false, 'error' => 'このメールアドレスは既に登録されています'], 409);
                }
                throw $e;
            }
            break;

        case 'reset_password':
            require_method('POST');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            $pw = (string)($b['password'] ?? '');
            if ($id <= 0 || strlen($pw) < 8) {
                json_out(['ok' => false, 'error' => 'IDと8文字以上のパスワードが必要です'], 400);
            }
            $st = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $st->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            json_out(['ok' => true]);
            break;

        case 'delete_user':
            require_method('POST');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            if ($id <= 0) {
                json_out(['ok' => false, 'error' => 'IDが不正です'], 400);
            }
            if ($id === (int)$me['id']) {
                json_out(['ok' => false, 'error' => '自分自身は削除できません'], 400);
            }
            $st = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $st->execute([$id]);
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
