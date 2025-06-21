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
      $newId = $pdo->lastInsertId();
      header("Location: scheme_edit.php?id=$newId");
      exit;
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
    $newname = trim($_POST['newname'] ?? '');
    if ($id && $newname) {
      $stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
      $stmt->execute([$id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $stmt2 = $pdo->prepare("INSERT INTO schemes (name, data, owner_id) VALUES (?, ?, ?)");
        $stmt2->execute([$newname, $row['data'], $user['id']]);
        $newId = $pdo->lastInsertId();
        header("Location: scheme_edit.php?id=$newId");
        exit;
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
    .container {
      width: 80vw;
      max-width: 1200px;
      min-width: 320px;
      margin: calc(var(--nav-height) + 2rem) auto 2rem auto;
      background: var(--card);
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      padding: 2.5rem 2rem 2rem 2rem;
      border: 1.5px solid #c8e6c9;
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
    .add-form input {
      padding: 0.5em 0.8em;
      border: 1px solid #c8e6c9;
      border-radius: 8px;
      background: #f9fff9;
      font-size: 1em;
      transition: border 0.2s;
    }
    .add-form input:focus {
      border: 1.5px solid var(--primary-dark);
      outline: none;
      background: #fff;
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
    table.schemes-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1.5em;
      background: var(--card);
    }
    table.schemes-table th, table.schemes-table td {
      border: 1px solid #e0f2f1;
      padding: 0.7em 0.8em;
      text-align: left;
      font-size: 1.05em;
    }
    table.schemes-table th {
      background: #f5fff5;
      color: var(--primary-dark);
      font-weight: 700;
    }
    table.schemes-table tr:hover td {
      background: #f9fff9;
    }
    .scheme-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5em;
      align-items: center;
    }
    .scheme-actions button {
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 8px;
      padding: 0.3em 1.1em;
      font-size: 1em;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
      display: flex;
      align-items: center;
      gap: 0.3em;
    }
    .scheme-actions button:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    @media (max-width: 900px) {
      .container { width: 99vw; padding: 1.2rem 0.2rem; }
      table.schemes-table th, table.schemes-table td { font-size: 0.98em; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .top-app-bar { background: #263238; }
      .container { background: #232d23; border: 1.5px solid #37474f; }
      table.schemes-table { background: #232d23; }
      table.schemes-table th { background: #263238; color: #ffb300; }
      table.schemes-table td { color: #eee; }
      .scheme-actions button { background: #37474f; color: #ffb300; }
      .scheme-actions button:hover { background: #ffb300; color: #232323; }
    }
  </style>
  <script>
    function renameScheme(id, oldName) {
      const newName = prompt('请输入新方案名：', oldName);
      if (newName && newName.trim() && newName !== oldName) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = `<input name=\"action\" value=\"rename\"><input name=\"id\" value=\"${id}\"><input name=\"newname\" value=\"${newName}\">`;
        document.body.appendChild(form);
        form.submit();
      }
    }
    function copyScheme(id, oldName) {
      const newName = prompt('请输入新方案名（复制）：', oldName + '_副本');
      if (newName && newName.trim()) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = `<input name=\"action\" value=\"copy\"><input name=\"id\" value=\"${id}\"><input name=\"newname\" value=\"${newName}\">`;
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</head>
<body>
  <div class="top-app-bar">
    <div class="left">
      <span class="material-icons">library_books</span>
      方案管理 - NoWord
    </div>
    <div class="center">
      <a href="/admin/index.php" class="btn"><span class="material-icons">admin_panel_settings</span>后台首页</a>
      <span style="display:inline-block;width:1.2em;"></span>
      <a href="/" class="btn"><span class="material-icons">home</span>回到首页</a>
    </div>
    <div class="right">
      <span style="display:flex;align-items:center;gap:0.2em;"><span class="material-icons" style="font-size:1.1em;">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <a href="/logout.php" class="btn"><span class="material-icons">logout</span>退出登录</a>
    </div>
  </div>
  <div class="container">
    <h2>方案管理</h2>
    <a href="index.php">← 返回后台</a>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="add-form" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="新方案名称" required>
      <button type="submit">新增方案</button>
    </form>
    <table class="schemes-table">
      <thead>
        <tr>
          <th style="width:40%;">方案名称</th>
          <th style="width:20%;">创建时间</th>
          <th style="width:40%;">操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($schemes as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['created_at']) ?></td>
          <td class="scheme-actions">
            <form method="post" style="display:inline;">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="submit"><span class="material-icons" style="font-size:1em;vertical-align:middle;">edit</span>编辑</button>
            </form>
            <button type="button" onclick="renameScheme(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"><span class="material-icons" style="font-size:1em;vertical-align:middle;">drive_file_rename_outline</span>重命名</button>
            <button type="button" onclick="copyScheme(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"><span class="material-icons" style="font-size:1em;vertical-align:middle;">content_copy</span>复制</button>
            <form method="post" style="display:inline;">
              <input type="hidden" name="action" value="del">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="submit" onclick="return confirm('确定删除？')"><span class="material-icons" style="font-size:1em;vertical-align:middle;">delete</span>删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
