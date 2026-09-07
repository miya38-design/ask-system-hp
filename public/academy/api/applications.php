<?php
declare(strict_types=1);

/**
 * 入会申込の管理（講師のみ）
 *   GET  ?action=list                       申込一覧（pending優先）
 *   POST ?action=approve  {id, parent_password?, student_password?, plan?, course_type?}
 *        承認：保護者＋生徒アカウントを発行し、申込を approved に
 *   POST ?action=reject   {id}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
require_role($me, 'instructor');
$action = $_GET['action'] ?? '';
$pdo = ada_db();

function gen_pw(): string
{
    // 読み間違えにくい8桁
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $s;
}

try {
    switch ($action) {
        case 'list':
            $rows = $pdo->query(
                "SELECT id, parent_name, parent_email, phone, child_name, grade, plan, note,
                        status, created_at, processed_at
                 FROM applications
                 ORDER BY (status='pending') DESC, created_at DESC
                 LIMIT 200"
            )->fetchAll();
            json_out(['ok' => true, 'applications' => $rows]);
            break;

        case 'approve':
            require_method('POST');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            $app = $pdo->prepare("SELECT * FROM applications WHERE id = ? AND status = 'pending'");
            $app->execute([$id]);
            $a = $app->fetch();
            if (!$a) {
                json_out(['ok' => false, 'error' => '対象の申込が見つかりません（処理済みの可能性）'], 404);
            }
            $parentPw  = (string)($b['parent_password'] ?? '') ?: gen_pw();
            $studentPw = (string)($b['student_password'] ?? '') ?: gen_pw();
            if (strlen($parentPw) < 8 || strlen($studentPw) < 8) {
                json_out(['ok' => false, 'error' => 'パスワードは8文字以上にしてください'], 400);
            }
            $courseType = in_array(($b['course_type'] ?? ''), ['junior', 'senior'], true) ? $b['course_type'] : null;
            $plan = in_array(($b['plan'] ?? ($a['plan'] ?? '')), ['standard-a', 'standard-b', 'premium'], true)
                ? ($b['plan'] ?? $a['plan']) : null;

            $pdo->beginTransaction();
            // 保護者
            $pInsert = $pdo->prepare(
                'INSERT INTO users (email, password_hash, display_name, role, area) VALUES (?, ?, ?, "parent", NULL)'
            );
            $pInsert->execute([$a['parent_email'], password_hash($parentPw, PASSWORD_DEFAULT), $a['parent_name']]);
            $parentId = (int)$pdo->lastInsertId();
            // 生徒（同じメールで登録可・QR自動発行）
            $sInsert = $pdo->prepare(
                'INSERT INTO users (email, password_hash, display_name, role, parent_id, course_type, plan, qr_token)
                 VALUES (?, ?, ?, "student", ?, ?, ?, ?)'
            );
            $sInsert->execute([
                $a['parent_email'], password_hash($studentPw, PASSWORD_DEFAULT), $a['child_name'],
                $parentId, $courseType, $plan, bin2hex(random_bytes(16)),
            ]);
            $studentId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE applications SET status='approved', processed_at=NOW(), parent_user_id=?, student_user_id=? WHERE id=?")
                ->execute([$parentId, $studentId, $id]);
            $pdo->commit();

            json_out([
                'ok' => true,
                'credentials' => [
                    'login_email'      => $a['parent_email'],
                    'parent_name'      => $a['parent_name'],
                    'parent_password'  => $parentPw,
                    'student_name'     => $a['child_name'],
                    'student_password' => $studentPw,
                ],
            ]);
            break;

        case 'reject':
            require_method('POST');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            $pdo->prepare("UPDATE applications SET status='rejected', processed_at=NOW() WHERE id=? AND status='pending'")->execute([$id]);
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
