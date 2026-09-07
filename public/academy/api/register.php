<?php
declare(strict_types=1);

/**
 * 入会申込（公開・認証不要）
 *   POST ?action=submit  {parent_name, parent_email, phone?, child_name, grade?, plan?, note?, hp?}
 *   hp はハニーポット（bot対策：値が入っていたら無視）
 */
require_once __DIR__ . '/lib.php';

$action = $_GET['action'] ?? '';

try {
    if ($action !== 'submit') {
        json_out(['ok' => false, 'error' => 'unknown action'], 404);
    }
    require_method('POST');
    $b = json_body();

    if (trim((string)($b['hp'] ?? '')) !== '') {
        json_out(['ok' => true]); // ボットには成功を装って無視
    }

    $parentName = trim((string)($b['parent_name'] ?? ''));
    $email      = trim((string)($b['parent_email'] ?? ''));
    $childName  = trim((string)($b['child_name'] ?? ''));
    $phone      = trim((string)($b['phone'] ?? ''));
    $grade      = trim((string)($b['grade'] ?? '')) ?: null;
    $plan       = trim((string)($b['plan'] ?? '')) ?: null;
    $note       = trim((string)($b['note'] ?? '')) ?: null;

    if ($parentName === '' || $childName === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'error' => '保護者名・お子さまのお名前・電話番号・メールアドレスは必須です'], 400);
    }

    $st = ada_db()->prepare(
        'INSERT INTO applications (parent_name, parent_email, phone, child_name, grade, plan, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$parentName, $email, $phone, $childName, $grade, $plan, $note]);
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
