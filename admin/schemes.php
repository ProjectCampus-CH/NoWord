<?php
session_start();
$config = require __DIR__ . '/../config.php';
// ...数据库连接，与 index.php 类似...

if (empty($_SESSION['user_id'])) {
  header('Location: /login.php');
  exit;
}
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
    /* ...与 index.php 类似的 Material 绿色风格... */
    body { background: #f5fff5; color: #222; }
    .container { max-width: 900px; margin: 2rem auto; }
    .schemes-list { display: flex; flex-wrap: wrap; gap: 1.5rem; }
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
    }
    .scheme-title { font-size: 1.1rem; font-weight: bold; color: #388e3c; margin-bottom: 0.5em; }
    .scheme-actions button { margin-right: 0.5em; }
    .add-form { margin-bottom: 2rem; }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      .scheme-card { background: #222; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2 style="color:#388e3c;">方案管理</h2>
    <a href="index.php" style="color:#388e3c;">← 返回后台</a>
    <?php if ($msg): ?><div style="color:#388e3c;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="新方案名称" required>
      <button type="submit" style="background:#388e3c;color:#fff;border:none;padding:0.5em 1.5em;border-radius:8px;">新增方案</button>
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
            <input type="text" name="newname" placeholder="重命名" style="width:6em;">
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
