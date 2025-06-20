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
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      min-height: 100vh;
      font-family: system-ui, sans-serif;
      margin: 0;
    }
    .container {
      max-width: 700px;
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
    .add-form input, .add-form select {
      padding: 0.5em 0.8em;
      border: 1px solid #c8e6c9;
      border-radius: 8px;
      background: #f9fff9;
      font-size: 1em;
      transition: border 0.2s;
    }
    .add-form input:focus, .add-form select:focus {
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
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 2rem;
      background: #f9fff9;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(56,142,60,0.06);
    }
    th, td {
      padding: 0.7em 0.5em;
      border-bottom: 1px solid #e0e0e0;
      text-align: center;
    }
    th {
      color: #388e3c;
      background: #e8f5e9;
      font-weight: 600;
      font-size: 1.05em;
    }
    .actions button, .actions select {
      margin-right: 0.5em;
      padding: 0.3em 0.8em;
      border-radius: 6px;
      border: 1px solid #c8e6c9;
      background: #fff;
      color: #388e3c;
      font-weight: 500;
      cursor: pointer;
      transition: background .2s, color .2s;
    }
    .actions button:hover, .actions select:focus {
      background: #e8f5e9;
      color: #2e7031;
      border-color: #43a047;
    }
    .msg {
      margin-bottom: 1em;
      padding: 0.7em 1em;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
      background: #e8f5e9;
      color: #388e3c;
      border: 1px solid #c8e6c9;
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .container { background: #232d23; border: 1.5px solid #37474f; }
      table { background: #232d23; color: #eee; }
      th { background: #263238; color: #66bb6a; }
      .msg { background: #263238; color: #66bb6a; border: 1px solid #388e3c; }
      .add-form input, .add-form select { background: #232d23; color: #eee; border: 1px solid #37474f; }
      .add-form input:focus, .add-form select:focus { background: #263238; border: 1.5px solid #66bb6a; }
      .actions button, .actions select { background: #232d23; color: #66bb6a; border: 1px solid #37474f; }
      .actions button:hover, .actions select:focus { background: #263238; color: #43a047; border-color: #43a047; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>用户管理</h2>
    <a href="index.php">← 返回后台</a>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="username" placeholder="新用户名" required>
      <input type="password" name="password" placeholder="初始密码" required>
      <select name="role">
        <option value="user">普通用户</option>
        <option value="admin">管理员</option>
      </select>
      <button type="submit">添加用户</button>
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
