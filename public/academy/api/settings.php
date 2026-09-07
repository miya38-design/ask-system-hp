<?php
declare(strict_types=1);

/**
 * 運用設定（③）
 *   GET  ?action=list             (認証必須) 全設定
 *   POST ?action=update           (instructor) {key, value}
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'list':
            $rows = $pdo->query('SELECT setting_key, setting_value, value_type, description, updated_at FROM settings ORDER BY setting_key')->fetchAll();
            json_out(['ok' => true, 'settings' => $rows]);
            break;

        case 'update':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $key = trim((string)($b['key'] ?? ''));
            $val = (string)($b['value'] ?? '');
            if ($key === '') {
                json_out(['ok' => false, 'error' => 'キーがありません'], 400);
            }
            $st = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
            $st->execute([$val, $key]);
            if ($st->rowCount() === 0) {
                // 未知キーは拒否（不用意な追加を防ぐ）
                $exists = $pdo->prepare('SELECT 1 FROM settings WHERE setting_key = ?');
                $exists->execute([$key]);
                if (!$exists->fetch()) {
                    json_out(['ok' => false, 'error' => '不明な設定キーです'], 400);
                }
            }
            ada_audit('setting_update', "{$key}={$val}");
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
