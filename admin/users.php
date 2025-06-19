<?php
session_start();
$config = require __DIR__ . '/../config.php';
// ...数据库连接，与 index.php 类似...

// 权限校验
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
if (!$user || $user['role'] !== 'admin') {
  http_response_code(403);
  exit('无权限');
}

// 处理表单操作（添加、删除、修改、重置密码）
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    if ($username && $password && in_array($role, ['user', 'admin'])) {
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
      $stmt->execute([$username]);
      if ($stmt->fetchColumn() > 0) {
        $msg = '用户名已存在。';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hash, $role]);
        $msg = '添加成功。';
      }
    } else {
      $msg = '请填写完整信息。';
    }
  } elseif ($action === 'del') {
    $id = intval($_POST['id'] ?? 0);
    if ($id && $id != $user['id']) {
      $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
      $stmt->execute([$id]);
      $msg = '已删除。';
    } else {
      $msg = '不能删除自己。';
    }
  } elseif ($action === 'resetpw') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      $newpw = bin2hex(random_bytes(4));
      $hash = password_hash($newpw, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
      $stmt->execute([$hash, $id]);
      $msg = "密码已重置为: $newpw";
    }
  } elseif ($action === 'role') {
    $id = intval($_POST['id'] ?? 0);
    $role = $_POST['role'] ?? 'user';
    if ($id && in_array($role, ['user', 'admin'])) {
      $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
      $stmt->execute([$role, $id]);
      $msg = '角色已更新。';
    }
  }
}

// 获取所有用户
$users = $pdo->query("SELECT id,username,role FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>用户管理 - NoWord</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    /* ...与 index.php 类似的 Material 绿色风格... */
    body { background: #f5fff5; color: #222; }
    .container { max-width: 700px; margin: 2rem auto; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
    th, td { padding: 0.7em 0.5em; border-bottom: 1px solid #e0e0e0; }
    th { color: #388e3c; }
    .actions button { margin-right: 0.5em; }
    .add-form { margin-bottom: 2rem; }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      table { color: #eee; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2 style="color:#388e3c;">用户管理</h2>
    <a href="index.php" style="color:#388e3c;">← 返回后台</a>
    <?php if ($msg): ?><div style="color:#388e3c;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="username" placeholder="新用户名" required>
      <input type="password" name="password" placeholder="初始密码" required>
      <select name="role">
        <option value="user">普通用户</option>
        <option value="admin">管理员</option>
      </select>
      <button type="submit" style="background:#388e3c;color:#fff;border:none;padding:0.5em 1.5em;border-radius:8px;">添加用户</button>
    </form>
    <table>
      <tr>
        <th>ID</th><th>用户名</th><th>角色</th><th>操作</th>
      </tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= $u['role'] ?></td>
        <td class="actions">
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="resetpw">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit">重置密码</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="del">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" onclick="return confirm('确定删除？')">删除</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="role">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <select name="role" onchange="this.form.submit()">
              <option value="user" <?= $u['role']==='user'?'selected':'' ?>>普通用户</option>
              <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>管理员</option>
            </select>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</body>
</html>
