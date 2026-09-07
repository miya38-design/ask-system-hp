<?php
declare(strict_types=1);

/**
 * 通知ログ（④）
 *   GET  ?action=list           (instructor) 直近の通知ログ
 *   POST ?action=resend         (instructor) {id} 失敗/成功に関わらず同じ宛先へ再送
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/line.php';

$me = require_auth();
require_role($me, 'instructor');
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'list':
            $rows = $pdo->query(
                "SELECT n.id, n.event, n.to_line_id, n.status, n.error, n.created_at,
                        u.display_name AS student_name
                 FROM notification_logs n
                 LEFT JOIN users u ON u.id = n.student_id
                 ORDER BY n.id DESC LIMIT 100"
            )->fetchAll();
            // LINE IDは全表示せず末尾のみ
            foreach ($rows as &$r) {
                $r['to_short'] = $r['to_line_id'] ? ('…' . substr($r['to_line_id'], -6)) : '';
                unset($r['to_line_id']);
            }
            unset($r);
            json_out(['ok' => true, 'logs' => $rows]);
            break;

        case 'resend':
            require_method('POST');
            $id = (int)(json_body()['id'] ?? 0);
            $st = $pdo->prepare('SELECT event, to_line_id, student_id, body FROM notification_logs WHERE id = ?');
            $st->execute([$id]);
            $log = $st->fetch();
            if (!$log) {
                json_out(['ok' => false, 'error' => '対象の通知が見つかりません'], 404);
            }
            $ok = ada_notify($log['to_line_id'], (string)$log['body'], $log['event'] . '_resend', $log['student_id'] ? (int)$log['student_id'] : null);
            ada_audit('notify_resend', "src_id={$id} " . ($ok ? 'ok' : 'failed'));
            json_out(['ok' => $ok, 'error' => $ok ? null : '再送に失敗しました（LINE設定/宛先をご確認ください）']);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
