<?php
/**
 * DB接続設定テンプレート
 * ------------------------------------------------------------------
 * このファイルをコピーして、サーバー上に「config.php」を作成し、
 * 実際の値を入れてください。config.php は .gitignore 済みで、
 * GitHub には絶対にコミットされません（＝パスワードは私(Claude)にも渡りません）。
 *
 * 設置場所: public/academy/api/config.php（このファイルと同じ場所）
 * ------------------------------------------------------------------
 */
return [
    // お名前.com のデータベースサーバー
    'db_host'    => 'mysql47.onamae.ne.jp',
    // コントロールパネルで作成したDB名 / ユーザー名 / パスワード
    'db_name'    => 'YOUR_DB_NAME',
    'db_user'    => 'YOUR_DB_USER',
    'db_pass'    => 'YOUR_DB_PASSWORD',
    'db_charset' => 'utf8mb4',
];
