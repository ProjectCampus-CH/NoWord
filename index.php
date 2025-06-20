<?php
if (!file_exists(__DIR__ . '/config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host=" . ($config['mysql']['host'] ?? 'localhost') . ";dbname=" . ($config['mysql']['db'] ?? '') . ";charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'] ?? '', $config['mysql']['pass'] ?? '', [
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
    die('SQLite 数据库文件不存在，请检查路径或重新初始化。');
  }
  $dsn = "sqlite:$sqlite_path";
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
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      margin:0;
      font-family: system-ui, sans-serif;
      min-height: 100vh;
    }
    .header {
      max-width: 900px;
      margin: 2rem auto 0 auto;
      padding: 0 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
      max-width: 1100px;
      margin: 0 auto 2rem auto;
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      justify-content: flex-start;
      background: #f5fff5;
      border-radius: 0 0 16px 16px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.10);
      padding-bottom: 2.5rem;
      padding-top: 2.5rem;
      border-top: none;
    }
    .card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.13);
      padding: 2rem 1.5rem;
      min-width: 260px;
      max-width: 320px;
      flex: 1 1 260px;
      display: flex;
      flex-direction: column;
      margin-bottom: 1rem;
      border: 1.5px solid #c8e6c9;
      transition: box-shadow .2s, transform .2s, border .2s;
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
      font-size: 1.2rem;
      font-weight: bold;
      color: #388e3c;
      margin-bottom: 0.5em;
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
      padding: 0.6em 1.5em;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.05em;
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
      .header span { color: #66bb6a; }
      .header a { color: #66bb6a; }
      .header a:hover { color: #43a047; }
    }
  </style>
</head>
<body>
  <div class="header">
    <span>NoWord</span>
    <a href="/login.php">后台登录</a>
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
