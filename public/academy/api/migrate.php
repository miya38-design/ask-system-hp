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

    // 確認：テーブル一覧
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    json_out(['ok' => true, 'executed' => $count, 'tables' => $tables]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
