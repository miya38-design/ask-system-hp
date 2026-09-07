<?php
declare(strict_types=1);

/**
 * 保護者向けAPI
 *   GET ?action=children   (parent) 自分の子（生徒）一覧＋概要
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

try {
    switch ($action) {
        case 'children':
            require_role($me, 'parent');
            $st = $pdo->prepare(
                'SELECT id, display_name, level, exp, exp_to_next, course_type, plan
                 FROM users WHERE parent_id = ? AND role = "student" ORDER BY display_name'
            );
            $st->execute([(int)$me['id']]);
            $kids = $st->fetchAll();

            $stampStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND status = 'present'");
            $lastStmt  = $pdo->prepare('SELECT MAX(date) FROM attendance WHERE user_id = ?');
            foreach ($kids as &$k) {
                $stampStmt->execute([(int)$k['id']]);
                $k['stamps'] = (int)$stampStmt->fetchColumn();
                $lastStmt->execute([(int)$k['id']]);
                $k['last_attended'] = $lastStmt->fetchColumn() ?: null;
            }
            unset($k);
            json_out(['ok' => true, 'children' => $kids]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
