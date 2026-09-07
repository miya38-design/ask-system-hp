<?php
declare(strict_types=1);

/**
 * 出席・スタンプAPI
 *   POST ?action=record        (instructor) {student_id, date, status}
 *   GET  ?action=my            (student)    自分の出席・スタンプ
 *   GET  ?action=for_student&student_id=  (instructor / 保護者 / 本人)
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

function attendance_summary(PDO $pdo, int $studentId): array
{
    $st = $pdo->prepare(
        "SELECT date, status FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 60"
    );
    $st->execute([$studentId]);
    $rows = $st->fetchAll();

    $stamps = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'present') {
            $stamps++;
        }
    }
    $u = $pdo->prepare('SELECT level, exp, exp_to_next FROM users WHERE id = ?');
    $u->execute([$studentId]);
    $ux = $u->fetch() ?: ['level' => 1, 'exp' => 0, 'exp_to_next' => 100];

    return [
        'stamps'      => $stamps,
        'level'       => (int)$ux['level'],
        'exp'         => (int)$ux['exp'],
        'exp_to_next' => (int)$ux['exp_to_next'],
        'records'     => $rows,
    ];
}

try {
    switch ($action) {
        case 'record':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $sid    = (int)($b['student_id'] ?? 0);
            $date   = trim((string)($b['date'] ?? ''));
            $status = (string)($b['status'] ?? 'present');
            if ($sid <= 0 || !is_valid_date($date) || !in_array($status, ['present', 'absent', 'late'], true)) {
                json_out(['ok' => false, 'error' => '生徒・日付・状態を確認してください'], 400);
            }
            $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
            $chk->execute([$sid]);
            if (!$chk->fetch()) {
                json_out(['ok' => false, 'error' => '対象の生徒が見つかりません'], 404);
            }
            $cur = $pdo->prepare('SELECT status FROM attendance WHERE user_id = ? AND date = ?');
            $cur->execute([$sid, $date]);
            $existing = $cur->fetch();
            $wasPresent = $existing && $existing['status'] === 'present';

            $pdo->prepare(
                'INSERT INTO attendance (user_id, date, status, stamp_count) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), stamp_count = VALUES(stamp_count)'
            )->execute([$sid, $date, $status, $status === 'present' ? 1 : 0]);

            $leveledFrom = null;
            if ($status === 'present' && !$wasPresent) {
                $before = $pdo->prepare('SELECT level FROM users WHERE id = ?');
                $before->execute([$sid]);
                $leveledFrom = (int)$before->fetchColumn();
                award_attendance_exp($sid, 20);
            }
            json_out(['ok' => true, 'summary' => attendance_summary($pdo, $sid), 'awarded' => ($status === 'present' && !$wasPresent)]);
            break;

        case 'my':
            require_role($me, 'student');
            json_out(['ok' => true, 'summary' => attendance_summary($pdo, (int)$me['id'])]);
            break;

        case 'for_student':
            $sid = (int)($_GET['student_id'] ?? 0);
            if ($sid <= 0 || !can_view_student($me, $sid)) {
                json_out(['ok' => false, 'error' => 'forbidden'], 403);
            }
            json_out(['ok' => true, 'summary' => attendance_summary($pdo, $sid)]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
