<?php
declare(strict_types=1);

/**
 * お知らせ／メッセージAPI
 *   GET  ?action=my                 (認証必須) 自分宛の一覧
 *   POST ?action=mark_read          (本人) {id}
 *   POST ?action=send               (instructor) {title, body, user_id? | to_role?('all'|'parent'|'student')}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'my':
            $st = $pdo->prepare('SELECT id, title, body, is_read, created_at FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
            $st->execute([(int)$me['id']]);
            json_out(['ok' => true, 'messages' => $st->fetchAll()]);
            break;

        case 'mark_read':
            require_method('POST');
            $b = json_body();
            $id = (int)($b['id'] ?? 0);
            $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, (int)$me['id']]);
            json_out(['ok' => true]);
            break;

        case 'send':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $title = trim((string)($b['title'] ?? ''));
            $body  = trim((string)($b['body'] ?? ''));
            if ($title === '' || $body === '') {
                json_out(['ok' => false, 'error' => 'タイトルと本文は必須です'], 400);
            }
            $targets = [];
            if (!empty($b['user_id'])) {
                $targets[] = (int)$b['user_id'];
            } else {
                $role = (string)($b['to_role'] ?? 'all');
                if ($role === 'all') {
                    $q = $pdo->query("SELECT id FROM users WHERE role IN ('parent','student')");
                } elseif (in_array($role, ['parent', 'student'], true)) {
                    $q = $pdo->prepare('SELECT id FROM users WHERE role = ?');
                    $q->execute([$role]);
                } else {
                    json_out(['ok' => false, 'error' => '送信先が不正です'], 400);
                }
                $targets = $q->fetchAll(PDO::FETCH_COLUMN);
            }
            if (!$targets) {
                json_out(['ok' => false, 'error' => '送信先が見つかりません'], 400);
            }
            $ins = $pdo->prepare('INSERT INTO messages (user_id, title, body) VALUES (?, ?, ?)');
            $pdo->beginTransaction();
            foreach ($targets as $uid) {
                $ins->execute([(int)$uid, $title, $body]);
            }
            $pdo->commit();
            json_out(['ok' => true, 'sent' => count($targets)]);
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
