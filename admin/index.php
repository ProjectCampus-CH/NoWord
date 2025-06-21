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
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    :root {
      --primary: #ff9800;
      --primary-dark: #c66900;
      --primary-light: #ffd149;
      --on-primary: #fff;
      --surface: #fff;
      --on-surface: #222;
      --background: #f5f5f5;
      --card: #fff;
      --card-shadow: 0 2px 8px rgba(255,152,0,0.08);
      --border-radius: 16px;
      --nav-height: 64px;
    }
    @media (prefers-color-scheme: dark) {
      :root {
        --primary: #ffb300;
        --primary-dark: #c68400;
        --primary-light: #ffe082;
        --on-primary: #222;
        --surface: #232323;
        --on-surface: #eee;
        --background: #181818;
        --card: #232323;
        --card-shadow: 0 2px 8px rgba(255,152,0,0.16);
      }
    }
    body {
      background: var(--background);
      color: var(--on-surface);
      font-family: system-ui, sans-serif;
      margin: 0;
      min-height: 100vh;
    }
    .top-app-bar {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: var(--nav-height);
      background: var(--primary);
      color: var(--on-primary);
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      padding: 0 2vw;
      font-family: 'Roboto', system-ui, sans-serif;
    }
    .top-app-bar .left {
      font-size: 1.35em;
      font-weight: 700;
      letter-spacing: 0.04em;
      display: flex;
      align-items: center;
      gap: 0.5em;
      user-select: none;
    }
    .top-app-bar .left .material-icons {
      font-size: 1.3em;
      vertical-align: middle;
    }
    .top-app-bar .center {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      min-width: 0;
    }
    .top-app-bar .right {
      display: flex;
      align-items: center;
      gap: 1.2em;
      font-size: 1em;
      min-width: 120px;
      justify-content: flex-end;
    }
    .top-app-bar .btn {
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 8px;
      padding: 0.5em 1.3em;
      font-size: 1em;
      font-weight: 500;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.4em;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s;
      outline: none;
      text-decoration: none;
    }
    .top-app-bar .btn:hover {
      background: var(--primary-light);
      color: var(--on-surface);
    }
    .main-content {
      margin-top: calc(var(--nav-height) + 24px);
      display: flex;
      justify-content: center;
      width: 100vw;
    }
    .waterfall {
      width: 60vw;
      min-width: 320px;
      max-width: 1200px;
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin: 0 auto;
      justify-content: flex-start;
      align-items: stretch;
    }
    .card {
      background: var(--card);
      border-radius: 20px;
      box-shadow: var(--card-shadow);
      padding: 2rem 1.5rem;
      min-width: 260px;
      max-width: 1fr;
      flex: 1 1 calc((100% - 40px)/3);
      display: flex;
      flex-direction: column;
      margin-bottom: 1rem;
      border: 1.5px solid #c8e6c9;
      transition: box-shadow .2s, transform .2s, border .2s;
      position: relative;
      overflow: hidden;
    }
    .card:hover {
      box-shadow: 0 8px 32px rgba(255,152,0,0.18);
      transform: translateY(-4px) scale(1.025);
      border-color: var(--primary);
    }
    .card-title {
      font-size: 1.2rem;
      font-weight: bold;
      color: var(--primary-dark);
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
      word-break: break-all;
    }
    .card-btn {
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 10px;
      padding: 0.6em 1.5em;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.05em;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
      z-index: 1;
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.4em;
      justify-content: center;
    }
    .card-btn:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    @media (max-width: 1100px) {
      .waterfall { width: 95vw; }
    }
    @media (max-width: 900px) {
      .waterfall { width: 99vw; }
      .card { min-width: 220px; }
    }
    @media (max-width: 700px) {
      .main-content { margin-top: calc(var(--nav-height) + 12px);}
      .waterfall { flex-direction: column; gap: 1.2rem; width: 99vw; }
      .card { min-width: 0; }
      .top-app-bar { flex-direction: column; gap: 0.7em; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .top-app-bar { background: #263238; }
      .card { background: #232d23; border: 1.5px solid #37474f; }
      .card-desc { color: #bbb; }
      .card-title { color: #ffb300; }
      .top-app-bar .left { color: #ffb300; }
      .top-app-bar .btn { background: #37474f; }
      .top-app-bar .btn:hover { background: #455a64; }
    }
  </style>
</head>
<body>
  <div class="top-app-bar">
    <div class="left">
      <span class="material-icons">admin_panel_settings</span>
      NoWord 后台
    </div>
    <div class="center">
      <a href="/" class="btn"><span class="material-icons">home</span>回到首页</a>
    </div>
    <div class="right">
      <span style="display:flex;align-items:center;gap:0.2em;"><span class="material-icons" style="font-size:1.1em;">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <a href="/logout.php" class="btn"><span class="material-icons">logout</span>退出登录</a>
    </div>
  </div>
  <div class="main-content">
    <div class="waterfall">
      <?php if ($user['role'] === 'admin'): ?>
      <div class="card">
        <div class="card-title"><span class="material-icons">group</span> 用户管理</div>
        <div class="card-desc">添加、删除用户，修改角色，重置密码，修改用户名。</div>
        <a class="card-btn" href="users.php"><span class="material-icons">arrow_forward</span>进入</a>
      </div>
      <?php endif; ?>
      <div class="card">
        <div class="card-title"><span class="material-icons">library_books</span> 方案管理</div>
        <div class="card-desc">管理所有词汇方案，新增、编辑、删除、复制、重命名。</div>
        <a class="card-btn" href="schemes.php"><span class="material-icons">arrow_forward</span>进入</a>
      </div>
      <div class="card">
        <div class="card-title"><span class="material-icons">home</span> 返回主页</div>
        <div class="card-desc">回到 NoWord 首页，查看和开始放映方案。</div>
        <a class="card-btn" href="/"><span class="material-icons">arrow_forward</span>返回</a>
      </div>
    </div>
  </div>
</body>
</html>
