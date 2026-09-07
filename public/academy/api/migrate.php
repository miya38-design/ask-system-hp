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
    // 確認：テーブル一覧
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    json_out(['ok' => true, 'executed' => $count, 'tables' => $tables]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
