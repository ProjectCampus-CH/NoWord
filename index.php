<?php
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
$schemes = $pdo->query("SELECT * FROM schemes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>NoWord 主页</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body { background: #f5fff5; color: #222; margin:0; }
    .header { max-width: 900px; margin: 2rem auto 0 auto; padding: 0 1rem; display: flex; justify-content: space-between; align-items: center;}
    .container { max-width: 1100px; margin: 2rem auto; display: flex; flex-wrap: wrap; gap: 2rem; justify-content: flex-start;}
    .card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      padding: 2rem 1.5rem;
      min-width: 260px;
      max-width: 320px;
      flex: 1 1 260px;
      display: flex;
      flex-direction: column;
      margin-bottom: 1rem;
    }
    .card-title { font-size: 1.2rem; font-weight: bold; color: #388e3c; margin-bottom: 0.5em;}
    .card-desc { flex: 1; color: #444; margin-bottom: 1.5rem;}
    .card-btn {
      background: #388e3c;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 0.6em 1.5em;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      text-align: center;
      transition: background .2s;
    }
    .card-btn:hover { background: #2e7031; }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      .card { background: #222; }
      .card-desc { color: #bbb; }
    }
  </style>
</head>
<body>
  <div class="header">
    <span style="font-size:1.5rem;font-weight:bold;color:#388e3c;">NoWord</span>
    <a href="/login.php" style="color:#388e3c;">后台登录</a>
  </div>
  <div class="container">
    <?php foreach ($schemes as $s): ?>
    <div class="card">
      <div class="card-title"><?= htmlspecialchars($s['name']) ?></div>
      <div class="card-desc">创建于 <?= htmlspecialchars($s['created_at']) ?></div>
      <a class="card-btn" href="/present/index.php?id=<?= $s['id'] ?>">开始</a>
    </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
