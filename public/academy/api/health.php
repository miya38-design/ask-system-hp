<?php
declare(strict_types=1);

/**
 * 疎通確認用エンドポイント。
 * ブラウザで /academy/api/health.php を開くと、PHP と MySQL の接続状態を JSON で返す。
 * （フェーズ1の動作確認用。バックエンド構築後もヘルスチェックとして利用可）
 */
header('Content-Type: application/json; charset=utf-8');

$out = [
    'ok'   => true,
    'php'  => PHP_VERSION,
    'time' => date('c'),
    'db'   => null,
];

try {
    require __DIR__ . '/db.php';
    $pdo = ada_db();
    $row = $pdo->query('SELECT VERSION() AS v')->fetch();
    $out['db'] = ['connected' => true, 'server_version' => $row['v'] ?? null];
} catch (Throwable $e) {
    http_response_code(500);
    $out['ok'] = false;
    $out['db'] = ['connected' => false, 'error' => $e->getMessage()];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
