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
// 判断登录状态
$user = null;
if (isset($_SESSION['user_id'])) {
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
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
      min-height: 100vh;
      font-family: system-ui, sans-serif;
      margin: 0;
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
    .container {
      max-width: 700px;
      margin: calc(var(--nav-height) + 2rem) auto 2rem auto;
      background: var(--card);
      border-radius: 18px;
      box-shadow: 0 4px 24px rgba(255,152,0,0.10);
      padding: 2.5rem 2rem 2rem 2rem;
      border: 1.5px solid var(--primary-light);
    }
    h2 {
      color: var(--primary-dark);
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.2em;
    }
    a {
      color: var(--primary-dark);
      text-decoration: underline;
      font-weight: 500;
      font-size: 1.05em;
      transition: color .2s;
    }
    a:hover {
      color: var(--primary);
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
      border: 1px solid var(--primary-light);
      border-radius: 8px;
      background: #fff;
      font-size: 1em;
      transition: border 0.2s;
    }
    .add-form input:focus, .add-form select:focus {
      border: 1.5px solid var(--primary-dark);
      outline: none;
      background: #fff8e1;
    }
    .add-form button {
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 10px;
      padding: 0.5em 1.5em;
      font-size: 1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
    }
    .add-form button:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 2rem;
      background: var(--card);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(255,152,0,0.06);
    }
    th, td {
      padding: 0.7em 0.5em;
      border-bottom: 1px solid #ffe0b2;
      text-align: center;
    }
    th {
      color: var(--primary-dark);
      background: #fff3e0;
      font-weight: 600;
      font-size: 1.05em;
    }
    .actions button, .actions select {
      margin-right: 0.5em;
      padding: 0.3em 0.8em;
      border-radius: 6px;
      border: 1px solid var(--primary-light);
      background: #fff;
      color: var(--primary-dark);
      font-weight: 500;
      cursor: pointer;
      transition: background .2s, color .2s;
    }
    .actions button:hover, .actions select:focus {
      background: #fff3e0;
      color: var(--primary);
      border-color: var(--primary);
    }
    .msg {
      margin-bottom: 1em;
      padding: 0.7em 1em;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
      background: #fff3e0;
      color: var(--primary-dark);
      border: 1px solid var(--primary-light);
    }
    @media (max-width: 900px) {
      .container { width: 99vw; padding: 1.2rem 0.2rem; }
      table { min-width: 700px; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .container { background: #232d23; border: 1.5px solid #37474f; }
      table { background: #232d23; color: #eee; }
      th { background: #263238; color: #ffb300; }
      .msg { background: #263238; color: #ffb300; border: 1px solid #c68400; }
      .add-form input, .add-form select { background: #232d23; color: #eee; border: 1px solid #37474f; }
      .add-form input:focus, .add-form select:focus { background: #263238; border: 1.5px solid #ffb300; }
      .actions button, .actions select { background: #37474f; color: #ffb300; border: none; }
      .actions button:hover, .actions select:focus { background: #ffb300; color: #232323; }
    }
  </style>
</head>
<body>
  <div class="top-app-bar">
    <div class="left">
      <span class="material-icons">manage_accounts</span>
      用户管理
    </div>
    <div class="center">
      <a href="index.php" class="btn"><span class="material-icons">arrow_back</span>返回后台</a>
    </div>
    <div class="right">
      <?php if (isset($user['id'])): ?>
        <span style="display:flex;align-items:center;gap:0.2em;"><span class="material-icons" style="font-size:1.1em;">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <?php else: ?>
        <a href="/login.php" class="btn"><span class="material-icons">login</span>登录</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="container">
    <h2>用户管理</h2>
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
