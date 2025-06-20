<?php
session_start();

// 检查配置文件
if (!file_exists(__DIR__ . '/../config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);

try {
  $pdo = null;
  if ($config['db_type'] === 'mysql') {
    $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['db']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['mysql']['user'], $config['mysql']['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  } else {
    $sqlite_path = $config['sqlite'];
    if (!preg_match('/^([a-zA-Z]:)?[\/\\\\]/', $sqlite_path)) {
      $sqlite_path = realpath(__DIR__ . '/../' . $sqlite_path);
      if ($sqlite_path === false) {
        $sqlite_path = __DIR__ . '/../' . $config['sqlite'];
      }
    }
    if (!file_exists($sqlite_path)) {
      throw new Exception('SQLite 数据库文件不存在，请检查路径或重新初始化。');
    }
    if (!is_writable($sqlite_path)) {
      throw new Exception('SQLite 数据库文件不可写，请检查权限。');
    }
    $dsn = "sqlite:$sqlite_path";
    $pdo = new PDO($dsn, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  }
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
  }
} catch (Exception $e) {
  echo '<div style="max-width:600px;margin:4em auto;padding:2em 1em;background:#fff3e0;border-radius:12px;border:1px solid #ffe0b2;color:#c62828;font-size:1.2em;text-align:center">';
  echo '数据库连接失败：' . htmlspecialchars($e->getMessage()) . '<br>';
  echo '<a href="/install.php" style="color:#388e3c;">前往初始化/修复</a>';
  echo '</div>';
  exit;
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>后台管理 - NoWord</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Material Design 3 CDN -->
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      min-height: 100vh;
      margin: 0;
      font-family: system-ui, sans-serif;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 900px;
      margin: 2rem auto 0 auto;
      padding: 0 1rem;
      background: #e8f5e9;
      border-radius: 16px 16px 0 0;
      box-shadow: 0 2px 8px rgba(56,142,60,0.07);
      border-bottom: 1.5px solid #c8e6c9;
    }
    .header span {
      font-size: 1.5rem;
      font-weight: bold;
      color: #388e3c;
      letter-spacing: 0.04em;
    }
    .header .user {
      margin-left: 1.5em;
      color: #388e3c;
      font-size: 1.1em;
      font-weight: 500;
    }
    .header a {
      color: #388e3c;
      text-decoration: underline;
      font-weight: 500;
      font-size: 1.05em;
      transition: color .2s;
    }
    .header a:hover {
      color: #2e7031;
      text-decoration: none;
    }
    .container {
      max-width: 900px;
      margin: 0 auto 2rem auto;
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      justify-content: center;
      background: #f5fff5;
      border-radius: 0 0 16px 16px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.10);
      padding-bottom: 2.5rem;
      padding-top: 2.5rem;
      border-top: none;
    }
    .card {
      flex: 1 1 260px;
      min-width: 260px;
      max-width: 320px;
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      padding: 2rem 1.5rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      transition: box-shadow .2s, transform .2s;
      border: 1.5px solid #c8e6c9;
      position: relative;
      overflow: hidden;
    }
    .card::before {
      content: "";
      position: absolute;
      left: -40px;
      top: -40px;
      width: 80px;
      height: 80px;
      background: radial-gradient(circle, #c8e6c9 0%, transparent 70%);
      z-index: 0;
    }
    .card:hover {
      box-shadow: 0 8px 32px rgba(56,142,60,0.18);
      transform: translateY(-4px) scale(1.025);
      border-color: #43a047;
    }
    .card-title {
      font-size: 1.3rem;
      font-weight: bold;
      color: #388e3c;
      margin-bottom: 1rem;
      letter-spacing: 0.03em;
      z-index: 1;
      position: relative;
    }
    .card-desc {
      flex: 1;
      color: #444;
      margin-bottom: 1.5rem;
      font-size: 1.05em;
      z-index: 1;
      position: relative;
    }
    .card-btn {
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 0.7em 1.7em;
      font-size: 1.08em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
      z-index: 1;
      position: relative;
    }
    .card-btn:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
      box-shadow: 0 4px 16px rgba(56,142,60,0.18);
      transform: scale(1.04);
    }
    @media (max-width: 700px) {
      .container { flex-direction: column; gap: 1.2rem; padding: 1.2rem 0; }
      .header { flex-direction: column; gap: 0.7em; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .header { background: #263238; border-bottom: 1.5px solid #37474f; }
      .container { background: #232d23; box-shadow: 0 4px 24px rgba(56,142,60,0.10);}
      .card { background: #232d23; border: 1.5px solid #37474f; }
      .card-desc { color: #bbb; }
      .card-title { color: #66bb6a; }
      .header span, .header .user { color: #66bb6a; }
      .header a { color: #66bb6a; }
      .header a:hover { color: #43a047; }
    }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <span>NoWord 后台</span>
      <span class="user">你好，<?= htmlspecialchars($user['username']) ?></span>
    </div>
    <a href="/logout.php">退出登录</a>
  </div>
  <div class="container">
    <?php if ($user['role'] === 'admin'): ?>
    <div class="card">
      <div class="card-title">用户管理</div>
      <div class="card-desc">添加、删除用户，修改角色，重置密码，修改用户名。</div>
      <a class="card-btn" href="users.php">进入</a>
    </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-title">方案管理</div>
      <div class="card-desc">管理所有词汇方案，新增、编辑、删除、复制、重命名。</div>
      <a class="card-btn" href="schemes.php">进入</a>
    </div>
    <div class="card">
      <div class="card-title">返回主页</div>
      <div class="card-desc">回到 NoWord 首页，查看和开始放映方案。</div>
      <a class="card-btn" href="/">返回</a>
    </div>
  </div>
</body>
</html>
