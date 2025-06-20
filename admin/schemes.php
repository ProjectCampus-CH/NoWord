<?php
session_start();
if (!file_exists(__DIR__ . '/../config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);

$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host=" . ($config['mysql']['host'] ?? 'localhost') . ";dbname=" . ($config['mysql']['db'] ?? '') . ";charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'] ?? '', $config['mysql']['pass'] ?? '', [
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
    die('SQLite 数据库文件不存在，请检查路径或重新初始化。');
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

// 处理表单操作（新增、删除、重命名、复制、编辑）
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
      $stmt = $pdo->prepare("INSERT INTO schemes (name, data, owner_id) VALUES (?, ?, ?)");
      $stmt->execute([$name, json_encode(['words'=>[], 'settings'=>[]]), $user['id']]);
      $msg = '新增成功。';
    }
  } elseif ($action === 'del') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      $stmt = $pdo->prepare("DELETE FROM schemes WHERE id=?");
      $stmt->execute([$id]);
      $msg = '已删除。';
    }
  } elseif ($action === 'rename') {
    $id = intval($_POST['id'] ?? 0);
    $newname = trim($_POST['newname'] ?? '');
    if ($id && $newname) {
      $stmt = $pdo->prepare("UPDATE schemes SET name=? WHERE id=?");
      $stmt->execute([$newname, $id]);
      $msg = '已重命名。';
    }
  } elseif ($action === 'copy') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      $stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
      $stmt->execute([$id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $newname = $row['name'].'_副本';
        $stmt2 = $pdo->prepare("INSERT INTO schemes (name, data, owner_id) VALUES (?, ?, ?)");
        $stmt2->execute([$newname, $row['data'], $user['id']]);
        $msg = '已复制。';
      }
    }
  } elseif ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      header("Location: scheme_edit.php?id=$id");
      exit;
    }
  }
}

// 获取所有方案
$schemes = $pdo->query("SELECT * FROM schemes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>方案管理 - NoWord</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      min-height: 100vh;
      font-family: system-ui, sans-serif;
      margin: 0;
    }
    .container {
      max-width: 900px;
      margin: 2rem auto;
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.10);
      padding: 2.5rem 2rem 2rem 2rem;
      border: 1.5px solid #c8e6c9;
    }
    h2 {
      color: #388e3c;
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.2em;
    }
    a {
      color: #388e3c;
      text-decoration: underline;
      font-weight: 500;
      font-size: 1.05em;
      transition: color .2s;
    }
    a:hover {
      color: #2e7031;
      text-decoration: none;
    }
    .add-form {
      display: flex;
      gap: 0.7em;
      margin-bottom: 2rem;
      align-items: center;
      flex-wrap: wrap;
    }
    .add-form input {
      padding: 0.5em 0.8em;
      border: 1px solid #c8e6c9;
      border-radius: 8px;
      background: #f9fff9;
      font-size: 1em;
      transition: border 0.2s;
    }
    .add-form input:focus {
      border: 1.5px solid #388e3c;
      outline: none;
      background: #fff;
    }
    .add-form button {
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 0.5em 1.5em;
      font-size: 1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
    }
    .add-form button:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
      box-shadow: 0 4px 16px rgba(56,142,60,0.18);
      transform: scale(1.04);
    }
    .schemes-list {
      display: flex;
      flex-wrap: wrap;
      gap: 1.5rem;
      justify-content: flex-start;
    }
    .scheme-card {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      padding: 1.5rem;
      min-width: 240px;
      max-width: 320px;
      flex: 1 1 240px;
      display: flex;
      flex-direction: column;
      margin-bottom: 1rem;
      border: 1.5px solid #c8e6c9;
      position: relative;
      overflow: hidden;
      transition: box-shadow .2s, transform .2s, border .2s;
    }
    .scheme-card::before {
      content: "";
      position: absolute;
      left: -30px;
      top: -30px;
      width: 60px;
      height: 60px;
      background: radial-gradient(circle, #c8e6c9 0%, transparent 70%);
      z-index: 0;
    }
    .scheme-card:hover {
      box-shadow: 0 8px 32px rgba(56,142,60,0.18);
      transform: translateY(-3px) scale(1.018);
      border-color: #43a047;
    }
    .scheme-title {
      font-size: 1.1rem;
      font-weight: bold;
      color: #388e3c;
      margin-bottom: 0.5em;
      letter-spacing: 0.02em;
      z-index: 1;
      position: relative;
    }
    .scheme-actions {
      z-index: 1;
      position: relative;
    }
    .scheme-actions button, .scheme-actions input[type="text"] {
      margin-right: 0.5em;
      padding: 0.3em 0.8em;
      border-radius: 6px;
      border: 1px solid #c8e6c9;
      background: #fff;
      color: #388e3c;
      font-weight: 500;
      cursor: pointer;
      transition: background .2s, color .2s;
      font-size: 0.98em;
    }
    .scheme-actions button:hover, .scheme-actions input[type="text"]:focus {
      background: #e8f5e9;
      color: #2e7031;
      border-color: #43a047;
    }
    .scheme-actions input[type="text"] {
      width: 6em;
      margin-right: 0.3em;
      background: #f9fff9;
    }
    .scheme-actions input[type="text"]:focus {
      background: #fff;
    }
    @media (max-width: 700px) {
      .schemes-list { flex-direction: column; gap: 1.2rem; }
      .container { padding: 1.2rem 0; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .container { background: #232d23; border: 1.5px solid #37474f; }
      .scheme-card { background: #232d23; border: 1.5px solid #37474f; }
      .scheme-title { color: #66bb6a; }
      .scheme-actions button, .scheme-actions input[type="text"] { background: #232d23; color: #66bb6a; border: 1px solid #37474f; }
      .scheme-actions button:hover, .scheme-actions input[type="text"]:focus { background: #263238; color: #43a047; border-color: #43a047; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>方案管理</h2>
    <a href="index.php">← 返回后台</a>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="新方案名称" required>
      <button type="submit">新增方案</button>
    </form>
    <div class="schemes-list">
      <?php foreach ($schemes as $s): ?>
      <div class="scheme-card">
        <div class="scheme-title"><?= htmlspecialchars($s['name']) ?></div>
        <div style="font-size:0.95em;color:#666;margin-bottom:1em;">创建于 <?= htmlspecialchars($s['created_at']) ?></div>
        <div class="scheme-actions">
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit">编辑</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <input type="text" name="newname" placeholder="重命名">
            <button type="submit">重命名</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="copy">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit">复制</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="del">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit" onclick="return confirm('确定删除？')">删除</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
