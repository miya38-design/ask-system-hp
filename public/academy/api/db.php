<?php
declare(strict_types=1);

/**
 * PDO(MySQL) 接続ヘルパー。
 * config.php（同ディレクトリ・git管理外）から接続情報を読み込む。
 */
function ada_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfgPath = __DIR__ . '/config.php';
    if (!is_file($cfgPath)) {
        throw new RuntimeException(
            'config.php が見つかりません。config.sample.php をコピーして config.php を作成し、DB接続情報を設定してください。'
        );
    }

    /** @var array{db_host:string,db_name:string,db_user:string,db_pass:string,db_charset?:string} $cfg */
    $cfg = require $cfgPath;

    $charset = $cfg['db_charset'] ?? 'utf8mb4';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['db_host'], $cfg['db_name'], $charset);

    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
