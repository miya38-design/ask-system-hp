<?php
declare(strict_types=1);

/**
 * 入会申込（公開・認証不要）
 *   POST ?action=submit  {parent_name, parent_email, phone?, child_name, grade?, plan?, note?,
 *                         agree, hp?}
 *   hp はハニーポット（bot対策：値が入っていたら無視）
 *
 * agree は利用規約への同意。画面のチェックボックスは回避できるので、
 * ここで必須にしたうえで、同意日時と規約の版を applications に残す。
 */
require_once __DIR__ . '/lib.php';

/** 現在の利用規約の版（terms.html の制定日）。改定したらここも更新する */
const ADA_TERMS_VERSION = '2026-09-10';

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

    ada_ensure_application_columns();

    $agree = $b['agree'] ?? false;
    if ($agree !== true && $agree !== 'true' && $agree !== 1 && $agree !== '1') {
        json_out(['ok' => false, 'error' => '利用規約へのご同意が必要です'], 400);
    }
    // 版は証跡なので、クライアントから来た値ではなくサーバー側の定数を記録する
    $termsVersion = ADA_TERMS_VERSION;

    $st = ada_db()->prepare(
        'INSERT INTO applications
           (parent_name, parent_email, phone, child_name, grade, plan, note,
            terms_version, terms_agreed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $st->execute([$parentName, $email, $phone, $childName, $grade, $plan, $note, $termsVersion]);
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
