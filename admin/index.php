<?php
session_start();
$config = require __DIR__ . '/../config.php';

// 登录校验
if (empty($_SESSION['user_id'])) {
  header('Location: /login.php');
  exit;
}

// 获取当前用户信息
try {
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
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
  }
} catch (Exception $e) {
  die('数据库连接失败');
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
      background: var(--md-sys-color-background, #f5fff5);
      color: var(--md-sys-color-on-background, #222);
      min-height: 100vh;
      margin: 0;
      font-family: system-ui, sans-serif;
    }
    .container {
      max-width: 900px;
      margin: 2rem auto;
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      justify-content: center;
    }
    .card {
      flex: 1 1 260px;
      min-width: 260px;
      max-width: 320px;
      background: var(--md-sys-color-surface, #fff);
      border-radius: 18px;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      padding: 2rem 1.5rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      transition: box-shadow .2s;
    }
    .card:hover {
      box-shadow: 0 4px 16px rgba(56,142,60,0.18);
    }
    .card-title {
      font-size: 1.3rem;
      font-weight: bold;
      color: #388e3c;
      margin-bottom: 1rem;
    }
    .card-desc {
      flex: 1;
      color: #444;
      margin-bottom: 1.5rem;
    }
    .card-btn {
      background: #388e3c;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 0.6em 1.5em;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      transition: background .2s;
    }
    .card-btn:hover {
      background: #2e7031;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 900px;
      margin: 2rem auto 0 auto;
      padding: 0 1rem;
    }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      .card { background: #222; }
      .card-desc { color: #bbb; }
    }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <span style="font-size:1.5rem;font-weight:bold;color:#388e3c;">NoWord 后台</span>
      <span style="margin-left:1.5em;color:#666;">你好，<?= htmlspecialchars($user['username']) ?></span>
    </div>
    <a href="/logout.php" style="color:#388e3c;text-decoration:underline;">退出登录</a>
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
