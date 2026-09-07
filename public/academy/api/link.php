<?php
declare(strict_types=1);

/**
 * LINE連携（保護者）
 *   GET ?action=status  (parent) 連携状態＋（未連携なら）連携コードを返す
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'status':
            require_role($me, 'parent');
            $q = $pdo->prepare('SELECT line_user_id, line_link_code FROM users WHERE id = ?');
            $q->execute([(int)$me['id']]);
            $row = $q->fetch() ?: [];
            if (!empty($row['line_user_id'])) {
                json_out(['ok' => true, 'linked' => true]);
            }
            $code = (string)($row['line_link_code'] ?? '');
            if ($code === '') {
                $code = strtoupper(bin2hex(random_bytes(3))); // 6桁
                $pdo->prepare('UPDATE users SET line_link_code = ? WHERE id = ?')->execute([$code, (int)$me['id']]);
            }
            $cfg = ada_config();
            json_out([
                'ok'      => true,
                'linked'  => false,
                'code'    => $code,
                'add_url' => (string)($cfg['line_add_url'] ?? 'https://lin.ee/xaSJRVQ'),
                'oa_id'   => (string)($cfg['line_oa_id'] ?? ''),
            ]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
