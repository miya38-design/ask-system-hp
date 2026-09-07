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
                        u.course_type, u.plan, u.grade, u.area, u.level, u.exp, u.created_at,
                        u.status, u.admin_role, u.school_name,
                        (u.line_user_id IS NOT NULL) AS line_linked
                 FROM users u
                 LEFT JOIN users p ON p.id = u.parent_id
                 ORDER BY FIELD(u.status,'active','suspended','withdrawn'),
                          FIELD(u.role,'instructor','parent','student'), u.created_at DESC"
            )->fetchAll();
            json_out(['ok' => true, 'users' => $rows]);
            break;

        case 'line_status':
            $c = ada_config();
            $configured = (($c['line_channel_token'] ?? '') !== '') && (($c['line_channel_secret'] ?? '') !== '');
            json_out(['ok' => true, 'configured' => $configured]);
            break;

        case 'update_user':
            require_method('POST');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            if ($id <= 0) {
                json_out(['ok' => false, 'error' => 'IDが不正です'], 400);
            }
            $chk = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $chk->execute([$id]);
            $role = $chk->fetchColumn();
            if (!$role) {
                json_out(['ok' => false, 'error' => '対象が見つかりません'], 404);
            }
            $sets = [];
            $args = [];
            if (isset($b['display_name']) && trim((string)$b['display_name']) !== '') {
                $sets[] = 'display_name = ?'; $args[] = trim((string)$b['display_name']);
            }
            if (array_key_exists('parent_id', $b) && $role === 'student') {
                if (empty($b['parent_id'])) {
                    $sets[] = 'parent_id = NULL';
                } else {
                    $pv = (int)$b['parent_id'];
                    $pc = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'parent'");
                    $pc->execute([$pv]);
                    if (!$pc->fetch()) {
                        json_out(['ok' => false, 'error' => '指定した保護者が見つかりません'], 400);
                    }
                    $sets[] = 'parent_id = ?'; $args[] = $pv;
                }
            }
            foreach (['course_type' => ['junior','senior'], 'plan' => ['standard-a','standard-b','premium']] as $f => $allow) {
                if (array_key_exists($f, $b) && $role === 'student') {
                    if ($b[$f] === '' || $b[$f] === null) { $sets[] = "$f = NULL"; }
                    elseif (in_array($b[$f], $allow, true)) { $sets[] = "$f = ?"; $args[] = $b[$f]; }
                }
            }
            if (array_key_exists('area', $b)) {
                $sets[] = 'area = ?'; $args[] = trim((string)$b['area']) ?: null;
            }
            if (array_key_exists('grade', $b)) {
                $sets[] = 'grade = ?'; $args[] = trim((string)$b['grade']) ?: null;
            }
            if (array_key_exists('school_name', $b)) {
                $sets[] = 'school_name = ?'; $args[] = trim((string)$b['school_name']) ?: null;
            }
            foreach (['start_date', 'end_date'] as $df) {
                if (array_key_exists($df, $b)) {
                    $sets[] = "$df = ?"; $args[] = ($b[$df] && is_valid_date((string)$b[$df])) ? $b[$df] : null;
                }
            }
            if (array_key_exists('status', $b) && in_array($b['status'], ['active','suspended','withdrawn'], true)) {
                $sets[] = 'status = ?'; $args[] = $b['status'];
            }
            if (array_key_exists('admin_role', $b) && $role === 'instructor') {
                $sets[] = 'admin_role = ?'; $args[] = in_array($b['admin_role'], ['owner','staff'], true) ? $b['admin_role'] : null;
            }
            if (!$sets) {
                json_out(['ok' => false, 'error' => '更新項目がありません'], 400);
            }
            $args[] = $id;
            $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
            ada_audit('user_update', "id={$id} " . implode(',', array_map(fn($s)=>explode(' ',$s)[0], $sets)));
            json_out(['ok' => true]);
            break;

        case 'audit_list':
            $rows = $pdo->query('SELECT actor_name, action, detail, created_at FROM audit_logs ORDER BY id DESC LIMIT 100')->fetchAll();
            json_out(['ok' => true, 'logs' => $rows]);
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
                $newId = (int)$pdo->lastInsertId();
                ada_audit('user_create', "id={$newId} role={$role} {$email}");
                json_out(['ok' => true, 'id' => $newId]);
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
            ada_audit('user_delete', "id={$id}");
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
