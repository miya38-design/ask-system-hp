<?php
declare(strict_types=1);

/**
 * 疎通確認用エンドポイント。
 *   GET /academy/api/health.php              稼働しているかどうかだけを返す（誰でも可）
 *   GET /academy/api/health.php?token=xxx    PHP/MySQLのバージョン等も返す（config.php の admin_token）
 *
 * バージョン番号や接続エラーの本文は、攻撃側にとっては下調べの材料になる。
 * 監視サービスから叩ける手軽さは残したいので、公開時は ok と接続可否だけにして、
 * 詳細はトークンを持っている場合に限って返す。
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$cfg      = [];
$detailed = false;
try {
    $cfg   = ada_config();
    $token = (string)($cfg['admin_token'] ?? '');
    $given = (string)($_GET['token'] ?? '');
    $detailed = ($token !== '' && $given !== '' && hash_equals($token, $given));
} catch (Throwable $e) {
    // config が読めない場合は詳細なしで続行する
}

$out = ['ok' => true, 'time' => date('c')];
if ($detailed) {
    $out['php']  = PHP_VERSION;
    $out['sapi'] = PHP_SAPI;
    // 入退室スキャンは「先に応答 → 後でLINE通知」に finish_request を使う。
    // 使えない構成では通知の完了を待つ旧来の挙動に戻るため、ここで見る。
    $out['early_response'] = function_exists('litespeed_finish_request')
                          || function_exists('fastcgi_finish_request');
}

try {
    $pdo = ada_db();
    $row = $pdo->query('SELECT VERSION() AS v')->fetch();
    $out['db'] = ['connected' => true];
    if ($detailed) {
        $out['db']['server_version'] = $row['v'] ?? null;
    }
} catch (Throwable $e) {
    http_response_code(500);
    $out['ok'] = false;
    $out['db'] = ['connected' => false];
    if ($detailed) {
        // 例外文にはホスト名や接続ユーザーが載ることがあるので、トークンがある時だけ
        $out['db']['error'] = $e->getMessage();
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
