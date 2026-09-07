<?php
declare(strict_types=1);

/**
 * スキーマ適用（冪等：CREATE TABLE IF NOT EXISTS）。
 * ブラウザで  /academy/api/migrate.php?token=（admin_token）  を開くと実行。
 */
require_once __DIR__ . '/lib.php';
require_admin_token();

try {
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        json_out(['ok' => false, 'error' => 'schema.sql not found'], 500);
    }
    $pdo = ada_db();
    $count = 0;
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }

    // --- 追加マイグレーション（冪等） ---
    // messages.batch_id（お知らせの一括削除用）
    $has = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'batch_id'"
    );
    $has->execute();
    if (!(int)$has->fetchColumn()) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN batch_id VARCHAR(40) NULL");
        try { $pdo->exec("ALTER TABLE messages ADD INDEX idx_messages_batch (batch_id)"); } catch (Throwable $e) {}
    }
    // 既存（batch_id未設定）のお知らせに、タイトル+本文+日付でまとめてIDを付与
    $pdo->exec(
        "UPDATE messages
         SET batch_id = SUBSTRING(MD5(CONCAT(title,'|',body,'|',DATE(created_at))),1,32)
         WHERE batch_id IS NULL"
    );

    // LINE連携・QR用カラム（users）
    $ensureCol = function (string $table, string $col, string $ddl) use ($pdo) {
        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $q->execute([$table, $col]);
        if (!(int)$q->fetchColumn()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        }
    };
    // メール一意制約を撤廃（生徒と保護者で同じメールを許可）
    $uq = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_email'"
    )->fetchColumn();
    if ((int)$uq) {
        $pdo->exec('ALTER TABLE users DROP INDEX uq_users_email');
        try { $pdo->exec('ALTER TABLE users ADD INDEX idx_users_email (email)'); } catch (Throwable $e) {}
    }

    $ensureCol('users', 'line_user_id', 'line_user_id VARCHAR(64) NULL');
    $ensureCol('users', 'line_link_code', 'line_link_code VARCHAR(12) NULL');
    $ensureCol('users', 'qr_token', 'qr_token VARCHAR(32) NULL');
    $ensureCol('attendance', 'absence_reason', 'absence_reason VARCHAR(255) NULL');
    try { $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_qr (qr_token)'); } catch (Throwable $e) {}
    try { $pdo->exec('ALTER TABLE users ADD KEY idx_users_line (line_user_id)'); } catch (Throwable $e) {}

    // 既存の生徒でqr_token未設定のものに発行
    $need = $pdo->query("SELECT id FROM users WHERE role='student' AND (qr_token IS NULL OR qr_token='')")->fetchAll(PDO::FETCH_COLUMN);
    if ($need) {
        $up = $pdo->prepare('UPDATE users SET qr_token = ? WHERE id = ?');
        foreach ($need as $uid) {
            $up->execute([bin2hex(random_bytes(16)), (int)$uid]);
        }
    }

    // 確認：テーブル一覧
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    json_out(['ok' => true, 'executed' => $count, 'tables' => $tables]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
