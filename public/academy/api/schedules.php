<?php
declare(strict_types=1);

/**
 * スケジュールAPI
 *   GET  ?action=list[&all=1]   (認証必須) 今日以降（all=1かつ講師で全件）
 *   POST ?action=save           (instructor) {id?, date, status, title?, capacity?, note?}
 *   POST ?action=delete         (instructor) {id}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'list':
            $all = ($me['role'] === 'instructor') && !empty($_GET['all']);
            if ($all) {
                $rows = $pdo->query('SELECT * FROM schedules ORDER BY date DESC LIMIT 200')->fetchAll();
            } else {
                $st = $pdo->query('SELECT * FROM schedules WHERE date >= CURDATE() ORDER BY date ASC LIMIT 60');
                $rows = $st->fetchAll();
            }
            json_out(['ok' => true, 'schedules' => $rows]);
            break;

        case 'save':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $date = trim((string)($b['date'] ?? ''));
            $status = (string)($b['status'] ?? 'open');
            $title = trim((string)($b['title'] ?? '')) ?: null;
            $note  = trim((string)($b['note'] ?? '')) ?: null;
            $capacity = ($b['capacity'] ?? '') === '' ? null : (int)$b['capacity'];
            if (!is_valid_date($date) || !in_array($status, ['open', 'few', 'full', 'closed'], true)) {
                json_out(['ok' => false, 'error' => '日付・状態を確認してください'], 400);
            }
            $id = (int)($b['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('UPDATE schedules SET date=?, status=?, title=?, capacity=?, note=? WHERE id=?')
                    ->execute([$date, $status, $title, $capacity, $note, $id]);
            } else {
                // 同一日付があれば更新（unique date）
                $pdo->prepare(
                    'INSERT INTO schedules (date, status, title, capacity, note) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE status=VALUES(status), title=VALUES(title), capacity=VALUES(capacity), note=VALUES(note)'
                )->execute([$date, $status, $title, $capacity, $note]);
            }
            json_out(['ok' => true]);
            break;

        case 'delete':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            if ($id <= 0) {
                json_out(['ok' => false, 'error' => 'IDが不正です'], 400);
            }
            $pdo->prepare('DELETE FROM schedules WHERE id = ?')->execute([$id]);
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
