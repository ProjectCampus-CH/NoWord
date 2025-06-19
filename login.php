<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header('Location: /admin/index.php');
  exit;
}
$config = require __DIR__ . '/config.php';
$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['db']};charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'], $config['mysql']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} else {
  $dsn = "sqlite:{$config['sqlite']}";
  $pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    header('Location: /admin/index.php');
    exit;
  } else {
    $msg = '用户名或密码错误';
  }
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>登录 - NoWord</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body { background: #f5fff5; color: #222; min-height: 100vh; display: flex; align-items: center; justify-content: center;}
    .card { max-width: 340px; margin: 2rem; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,128,0,0.08); background: #fff;}
    .green { color: #388e3c; }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      .card { background: #222; }
    }
  </style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <h2 class="green">NoWord 登录</h2>
    <label>用户名</label>
    <input type="text" name="username" required>
    <label>密码</label>
    <input type="password" name="password" required>
    <button type="submit" style="margin-top:1rem;background:#388e3c;color:#fff;border:none;padding:0.7em 2em;border-radius:8px;">登录</button>
    <?php if ($msg): ?>
      <div style="color:#c00;margin-top:1em;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
  </form>
</body>
</html>
