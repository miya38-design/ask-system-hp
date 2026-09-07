<?php
declare(strict_types=1);

/**
 * コース定義（⑤）
 *   GET  ?action=list          (認証必須) 有効/全コース
 *   POST ?action=save          (instructor) {id?, code, name, allowed_weekdays, active}
 *   POST ?action=delete        (instructor) {id}
 * allowed_weekdays はJSの getDay 準拠のカンマ区切り（日0〜土6）。例: 火木="2,4"
 */
require_once __DIR__ . '/lib.php';

$me = require_auth();
$action = $_GET['action'] ?? '';
$pdo = ada_db();

function norm_weekdays(string $s): string
{
    $parts = array_filter(array_map('trim', explode(',', $s)), fn($x) => $x !== '' && ctype_digit($x) && (int)$x >= 0 && (int)$x <= 6);
    return implode(',', array_values(array_unique($parts)));
}

try {
    switch ($action) {
        case 'list':
            $rows = $pdo->query('SELECT id, code, name, allowed_weekdays, active FROM course_plans ORDER BY sort_order, id')->fetchAll();
            json_out(['ok' => true, 'courses' => $rows]);
            break;

        case 'save':
            require_method('POST');
            require_role($me, 'instructor');
            $b = json_body();
            $code = trim((string)($b['code'] ?? ''));
            $name = trim((string)($b['name'] ?? ''));
            $wd   = norm_weekdays((string)($b['allowed_weekdays'] ?? ''));
            $active = !empty($b['active']) ? 1 : 0;
            if ($code === '' || $name === '' || $wd === '') {
                json_out(['ok' => false, 'error' => 'コード・名称・曜日を確認してください'], 400);
            }
            $id = (int)($b['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('UPDATE course_plans SET code=?, name=?, allowed_weekdays=?, active=? WHERE id=?')
                    ->execute([$code, $name, $wd, $active, $id]);
            } else {
                $pdo->prepare('INSERT INTO course_plans (code, name, allowed_weekdays, active) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE name=VALUES(name), allowed_weekdays=VALUES(allowed_weekdays), active=VALUES(active)')
                    ->execute([$code, $name, $wd, $active]);
            }
            ada_audit('course_save', "{$code}");
            json_out(['ok' => true]);
            break;

        case 'delete':
            require_method('POST');
            require_role($me, 'instructor');
            $id = (int)(json_body()['id'] ?? 0);
            $pdo->prepare('DELETE FROM course_plans WHERE id = ?')->execute([$id]);
            ada_audit('course_delete', "id={$id}");
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
