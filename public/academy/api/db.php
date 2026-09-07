<?php
declare(strict_types=1);

/**
 * 設定読み込み（config.php はサーバー上のみ・git管理外）。
 */
function ada_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfgPath = __DIR__ . '/config.php';
    if (!is_file($cfgPath)) {
        throw new RuntimeException(
            'config.php が見つかりません。config.sample.php をコピーして config.php を作成し、DB接続情報を設定してください。'
        );
    }
    $cfg = require $cfgPath;
    return $cfg;
}

/**
 * PDO(MySQL) 接続（シングルトン）。
 */
function ada_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = ada_config();
    $charset = $cfg['db_charset'] ?? 'utf8mb4';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['db_host'], $cfg['db_name'], $charset);
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
