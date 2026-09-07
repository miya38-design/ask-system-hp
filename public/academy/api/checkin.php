<?php
declare(strict_types=1);

/**
 * 入退室API
 *   POST ?action=scan     (instructor/kiosk) {qr_token}  入室/退室をトグル記録＋保護者へLINE通知
 *   GET  ?action=my_qr    (student)          自分のQRトークン
 *   GET  ?action=history&student_id=  (instructor/保護者/本人)  最近の入退室
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/line.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'scan':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $tok = trim((string)($b['qr_token'] ?? ''));
            if ($tok === '') {
                json_out(['ok' => false, 'error' => 'QRトークンがありません'], 400);
            }
            $st = $pdo->prepare("SELECT id, display_name, parent_id FROM users WHERE qr_token = ? AND role = 'student'");
            $st->execute([$tok]);
            $stu = $st->fetch();
            if (!$stu) {
                json_out(['ok' => false, 'error' => '該当する生徒が見つかりません'], 404);
            }
            $sid = (int)$stu['id'];

            // 本日の直近の記録から in/out を決定
            $last = $pdo->prepare("SELECT type FROM checkins WHERE user_id = ? AND DATE(at) = CURDATE() ORDER BY at DESC LIMIT 1");
            $last->execute([$sid]);
            $lastType = $last->fetchColumn();
            $type = ($lastType === 'in') ? 'out' : 'in';

            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO checkins (user_id, type, at) VALUES (?, ?, ?)')->execute([$sid, $type, $now]);

            // 入室時：出席（present）記録＋EXP付与（その日初回のみ）
            if ($type === 'in') {
                $today = date('Y-m-d');
                $cur = $pdo->prepare('SELECT status FROM attendance WHERE user_id = ? AND date = ?');
                $cur->execute([$sid, $today]);
                $ex = $cur->fetch();
                $wasPresent = $ex && $ex['status'] === 'present';
                $pdo->prepare(
                    'INSERT INTO attendance (user_id, date, status, stamp_count) VALUES (?, ?, "present", 1)
                     ON DUPLICATE KEY UPDATE status = "present", stamp_count = 1'
                )->execute([$sid, $today]);
                if (!$wasPresent) {
                    award_attendance_exp($sid, 20);
                }
            }

            // 保護者へLINE通知
            $hhmm = date('H:i', strtotime($now));
            $verb = $type === 'in' ? '入室' : '退室';
            if (!empty($stu['parent_id'])) {
                $pp = $pdo->prepare('SELECT line_user_id FROM users WHERE id = ?');
                $pp->execute([(int)$stu['parent_id']]);
                $lineId = (string)($pp->fetchColumn() ?: '');
                if ($lineId !== '') {
                    line_push($lineId, "【ASKデジタルアカデミー】\n{$stu['display_name']}さんが{$verb}しました（{$hhmm}）");
                }
            }

            json_out(['ok' => true, 'student' => $stu['display_name'], 'type' => $type, 'time' => $hhmm]);
            break;

        case 'my_qr':
            require_role($me, 'student');
            $q = $pdo->prepare('SELECT qr_token FROM users WHERE id = ?');
            $q->execute([(int)$me['id']]);
            $tok = (string)($q->fetchColumn() ?: '');
            if ($tok === '') {
                $tok = bin2hex(random_bytes(16));
                $pdo->prepare('UPDATE users SET qr_token = ? WHERE id = ?')->execute([$tok, (int)$me['id']]);
            }
            json_out(['ok' => true, 'qr_token' => $tok, 'name' => $me['display_name']]);
            break;

        case 'child_qr':
            $sid = (int)($_GET['student_id'] ?? 0);
            if ($sid <= 0 || !can_view_student($me, $sid)) {
                json_out(['ok' => false, 'error' => 'forbidden'], 403);
            }
            $q = $pdo->prepare("SELECT display_name, qr_token FROM users WHERE id = ? AND role='student'");
            $q->execute([$sid]);
            $row = $q->fetch();
            if (!$row) {
                json_out(['ok' => false, 'error' => 'not found'], 404);
            }
            if (empty($row['qr_token'])) {
                $tok = bin2hex(random_bytes(16));
                $pdo->prepare('UPDATE users SET qr_token = ? WHERE id = ?')->execute([$tok, $sid]);
                $row['qr_token'] = $tok;
            }
            json_out(['ok' => true, 'qr_token' => $row['qr_token'], 'name' => $row['display_name']]);
            break;

        case 'history':
            $sid = (int)($_GET['student_id'] ?? 0);
            if ($sid <= 0 || !can_view_student($me, $sid)) {
                json_out(['ok' => false, 'error' => 'forbidden'], 403);
            }
            $h = $pdo->prepare('SELECT type, at FROM checkins WHERE user_id = ? ORDER BY at DESC LIMIT 40');
            $h->execute([$sid]);
            json_out(['ok' => true, 'history' => $h->fetchAll()]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
