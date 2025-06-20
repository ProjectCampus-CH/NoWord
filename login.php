<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header('Location: /admin/index.php');
  exit;
}
if (!file_exists(__DIR__ . '/config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['db']};charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'], $config['mysql']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} else {
  $sqlite_path = $config['sqlite'];
  if (!preg_match('/^([a-zA-Z]:)?[\/\\\\]/', $sqlite_path)) {
    $sqlite_path = realpath(__DIR__ . '/' . $sqlite_path);
    if ($sqlite_path === false) {
      $sqlite_path = __DIR__ . '/' . $config['sqlite'];
    }
  }
  if (!file_exists($sqlite_path)) {
    $msg = 'SQLite 数据库文件不存在，请检查路径或重新初始化。';
  } elseif (!is_writable($sqlite_path)) {
    $msg = 'SQLite 数据库文件不可写，请检查权限。';
  } else {
    $dsn = "sqlite:$sqlite_path";
    $pdo = new PDO($dsn, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  }
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
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: system-ui, sans-serif;
    }
    .card {
      max-width: 340px;
      margin: 2rem;
      padding: 2.5rem 2rem 2rem 2rem;
      border-radius: 20px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.13);
      background: #fff;
      display: flex;
      flex-direction: column;
      gap: 1.1em;
      border: 1px solid #e0f2f1;
    }
    h2 {
      margin-bottom: 0.5em;
      color: #388e3c;
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
    }
    label {
      font-weight: 500;
      margin-bottom: 0.2em;
      color: #388e3c;
      display: block;
    }
    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 0.6em 0.8em;
      margin-bottom: 0.7em;
      border: 1px solid #c8e6c9;
      border-radius: 8px;
      background: #f9fff9;
      font-size: 1em;
      transition: border 0.2s;
    }
    input:focus {
      border: 1.5px solid #388e3c;
      outline: none;
      background: #fff;
    }
    button[type="submit"] {
      margin-top: 1rem;
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color: #fff;
      border: none;
      padding: 0.8em 2em;
      border-radius: 10px;
      font-size: 1.1em;
      font-weight: 600;
      letter-spacing: 0.05em;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      cursor: pointer;
      transition: background 0.2s;
    }
    button[type="submit"]:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
    }
    .msg {
      margin-top: 1em;
      padding: 0.7em 1em;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ffcdd2;
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .card { background: #232d23; border: 1px solid #37474f; }
      input { background: #232d23; color: #eee; border: 1px solid #37474f; }
      input:focus { background: #263238; border: 1.5px solid #66bb6a; }
      .msg { background: #2d2323; color: #ff8a80; border: 1px solid #c62828; }
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
