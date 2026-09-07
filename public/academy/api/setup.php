<?php
declare(strict_types=1);

/**
 * 初期アカウント作成ページ（管理トークンで保護）。
 * ブラウザで /academy/api/setup.php を開き、admin_token と項目を入力して作成。
 * ※最初の講師(管理者)アカウントを作るための簡易ツール。
 */
require_once __DIR__ . '/lib.php';

$cfg = ada_config();
$adminToken = (string)($cfg['admin_token'] ?? '');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$msg = '';
$msgType = '';
$given = (string)($_POST['token'] ?? ($_GET['token'] ?? ''));
$authed = ($adminToken !== '' && hash_equals($adminToken, $given));

if ($method === 'POST') {
    if (!$authed) {
        $msg = '管理トークンが違います。';
        $msgType = 'err';
    } else {
        $name = trim((string)($_POST['display_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        if ($name === '' || $email === '' || $pw === '' || !in_array($role, ['parent', 'student', 'instructor'], true)) {
            $msg = 'すべての項目を正しく入力してください。';
            $msgType = 'err';
        } elseif (strlen($pw) < 8) {
            $msg = 'パスワードは8文字以上にしてください。';
            $msgType = 'err';
        } else {
            try {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $st = ada_db()->prepare(
                    'INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)'
                );
                $st->execute([$email, $hash, $name, $role]);
                $msg = "アカウントを作成しました：{$email}（{$role}）。ログインできます。";
                $msgType = 'ok';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $msg = 'このメールアドレスは既に登録されています。';
                } else {
                    $msg = 'エラー：作成できませんでした。';
                }
                $msgType = 'err';
            }
        }
    }
}

$tokenValue = htmlspecialchars($given, ENT_QUOTES);
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>初期セットアップ｜ASKデジタルアカデミー</title>
<style>
  body{font-family:"Noto Sans JP",sans-serif;background:#0a1020;color:#eef3fc;margin:0;padding:40px 16px;}
  .card{max-width:460px;margin:0 auto;background:#111c34;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:28px;}
  h1{font-size:20px;margin:0 0 6px;}
  p.desc{color:#8496b6;font-size:14px;margin:0 0 20px;}
  label{display:block;font-size:13px;font-weight:700;margin:14px 0 6px;color:#c6d2e6;}
  input,select{width:100%;box-sizing:border-box;padding:11px 12px;border-radius:8px;border:1px solid #2a3a5a;background:#0d1526;color:#eef3fc;font-size:15px;}
  button{margin-top:22px;width:100%;padding:13px;border:0;border-radius:999px;font-weight:800;font-size:15px;color:#06132a;background:linear-gradient(135deg,#7fb0ff,#54e0d8);cursor:pointer;}
  .msg{padding:12px 14px;border-radius:8px;font-size:14px;margin-bottom:16px;}
  .ok{background:rgba(63,216,208,.15);border:1px solid #3fd8d0;color:#bff6f2;}
  .err{background:rgba(231,78,124,.15);border:1px solid #e74e7c;color:#ffc9d8;}
</style>
</head>
<body>
  <div class="card">
    <h1>初期アカウント作成</h1>
    <p class="desc">最初の講師（管理者）アカウントを作成します。管理トークンは config.php の admin_token です。</p>
    <?php if ($msg !== ''): ?>
      <div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="post" action="setup.php">
      <label>管理トークン</label>
      <input type="password" name="token" value="<?= $tokenValue ?>" required>
      <label>お名前（表示名）</label>
      <input type="text" name="display_name" required>
      <label>メールアドレス（ログインID）</label>
      <input type="email" name="email" required>
      <label>パスワード（8文字以上）</label>
      <input type="password" name="password" minlength="8" required>
      <label>ロール</label>
      <select name="role" required>
        <option value="instructor">講師（管理者）</option>
        <option value="parent">保護者</option>
        <option value="student">生徒</option>
      </select>
      <button type="submit">作成する</button>
    </form>
  </div>
</body>
</html>
