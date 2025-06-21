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
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      margin: 0;
      font-family: system-ui, sans-serif;
      background: #222;
      color: #222;
      display: flex;
      align-items: stretch;
      justify-content: flex-end;
      position: relative;
      overflow: hidden;
    }
    .bing-bg {
      position: fixed;
      top: 0; left: 0; width: 100vw; height: 100vh;
      z-index: 0;
      object-fit: cover;
      filter: brightness(0.85) blur(0.5px);
      transition: filter .3s;
    }
    .login-layout {
      position: relative;
      z-index: 1;
      width: 100vw;
      min-height: 100vh;
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: flex-end;
    }
    .announcement {
      width: 40vw;
      max-width: 480px;
      min-width: 260px;
      max-height: 60vh;
      background: rgba(255,255,255,0.85);
      border-radius: 18px;
      margin-left: 6vw;
      margin-right: 2vw;
      padding: 2em 2em 1.5em 2em;
      overflow-y: auto;
      box-shadow: 0 4px 24px rgba(56,142,60,0.13);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: flex-start;
      font-size: 1.08em;
      color: #333;
    }
    .announcement h3 {
      margin-top: 0;
      color: #ff9800;
      font-size: 1.2em;
      font-weight: bold;
      margin-bottom: 0.7em;
    }
    .login-card {
      width: 340px;
      min-width: 260px;
      max-width: 400px;
      margin: 0 6vw 0 0;
      padding: 2.5rem 2rem 2rem 2rem;
      border-radius: 20px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.13);
      background: rgba(255,255,255,0.97);
      display: flex;
      flex-direction: column;
      gap: 1.1em;
      border: 1px solid #e0f2f1;
      align-self: center;
    }
    h2 {
      margin-bottom: 0.5em;
      color: #ff9800;
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
    }
    label {
      font-weight: 500;
      margin-bottom: 0.2em;
      color: #c66900;
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
      border: 1.5px solid #ff9800;
      outline: none;
      background: #fff;
    }
    .keep-login {
      display: flex;
      align-items: center;
      gap: 0.5em;
      margin-bottom: 0.5em;
      font-size: 0.98em;
      color: #888;
    }
    button[type="submit"] {
      margin-top: 1rem;
      background: linear-gradient(90deg, #ff9800 60%, #ffd149 100%);
      color: #fff;
      border: none;
      padding: 0.8em 2em;
      border-radius: 10px;
      font-size: 1.1em;
      font-weight: 600;
      letter-spacing: 0.05em;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      cursor: pointer;
      transition: background 0.2s;
      display: flex;
      align-items: center;
      gap: 0.4em;
      justify-content: center;
    }
    button[type="submit"]:hover {
      background: linear-gradient(90deg, #c66900 60%, #ff9800 100%);
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
    @media (max-width: 900px) {
      .announcement { display: none; }
      .login-card { margin: 0 auto; }
      .login-layout { justify-content: center; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: #181818; color: #eee; }
      .announcement { background: rgba(35,35,35,0.92); color: #eee; }
      .login-card { background: rgba(35,35,35,0.97); border: 1px solid #37474f; }
      input { background: #232d23; color: #eee; border: 1px solid #37474f; }
      input:focus { background: #263238; border: 1.5px solid #ffb300; }
      .msg { background: #2d2323; color: #ff8a80; border: 1px solid #c62828; }
    }
  </style>
  <script>
    // 加载Bing每日一图
    window.addEventListener('DOMContentLoaded', function() {
      document.getElementById('bing-bg').src = './banner.jpg';
      // 加载公告
      fetch('announcement.json').then(r=>r.json()).then(data=>{
        let box = document.getElementById('announcement-box');
        if (data.title) box.innerHTML = `<h3>${data.title}</h3>`;
        if (data.content) box.innerHTML += `<div>${data.content.replace(/\n/g,'<br>')}</div>`;
      }).catch(()=>{
        let box = document.getElementById('announcement-box');
        box.innerHTML = '<h3>公告</h3><div>暂无公告</div>';
      });
    });
  </script>
</head>
<body>
  <img id="bing-bg" class="bing-bg" src="" alt="Bing每日一图">
  <div class="login-layout">
    <div class="announcement" id="announcement-box"></div>
    <form class="login-card" method="post" autocomplete="off">
      <h2>NoWord 登录</h2>
      <label>用户名</label>
      <input type="text" name="username" required>
      <label>密码</label>
      <input type="password" name="password" required>
      <div class="keep-login">
        <input type="checkbox" name="keep_login" id="keep_login" style="width:auto;">
        <label for="keep_login" style="margin:0;display:inline;font-weight:400;">保持登录</label>
      </div>
      <button type="submit"><span class="material-icons">login</span>登录</button>
      <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
