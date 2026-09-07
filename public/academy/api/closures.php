<?php
declare(strict_types=1);

/**
 * 休校日（②）
 *   GET  ?action=list[&all=1]  (認証必須) 今日以降 / 全件(講師)
 *   POST ?action=save          (instructor) {id?, date, type[closed|special_open], reason?}
 *   POST ?action=delete        (instructor) {id}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'list':
            if (($me['role'] === 'instructor') && !empty($_GET['all'])) {
                $rows = $pdo->query('SELECT * FROM closures ORDER BY date DESC LIMIT 300')->fetchAll();
            } else {
                $rows = $pdo->query('SELECT * FROM closures WHERE date >= CURDATE() ORDER BY date ASC LIMIT 200')->fetchAll();
            }
            json_out(['ok' => true, 'closures' => $rows]);
            break;

        case 'save':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $date = trim((string)($b['date'] ?? ''));
            $type = (string)($b['type'] ?? 'closed');
            $reason = trim((string)($b['reason'] ?? '')) ?: null;
            if (!is_valid_date($date) || !in_array($type, ['closed', 'special_open'], true)) {
                json_out(['ok' => false, 'error' => '日付・種別を確認してください'], 400);
            }
            $id = (int)($b['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('UPDATE closures SET date=?, type=?, reason=? WHERE id=?')->execute([$date, $type, $reason, $id]);
            } else {
                $pdo->prepare('INSERT INTO closures (date, type, reason) VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE type=VALUES(type), reason=VALUES(reason)')
                    ->execute([$date, $type, $reason]);
            }
            ada_audit('closure_save', "{$date} {$type}");
            json_out(['ok' => true]);
            break;

        case 'delete':
            require_method('POST');
            require_role($me, 'instructor');
            $id = (int)(json_body()['id'] ?? 0);
            $pdo->prepare('DELETE FROM closures WHERE id = ?')->execute([$id]);
            ada_audit('closure_delete', "id={$id}");
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
