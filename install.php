<?php
session_start();
$error = '';
$success = '';

function render_form($db_type = '', $mysql = [], $sqlite = '', $admin = []) {
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>ComboWord 初始化</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: system-ui, sans-serif;
    }
    .card {
      max-width: 440px;
      margin: 2rem;
      padding: 2.5rem 2rem 2rem 2rem;
      border-radius: 20px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.13);
      background: #fff;
      display: flex;
      flex-direction: column;
      gap: 1.1em;
      border: 1px solid #e0f2f1;
    }
    h2 {
      margin-bottom: 0.5em;
      color: #388e3c;
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
    }
    label {
      font-weight: 500;
      margin-bottom: 0.2em;
      color: #388e3c;
      display: block;
    }
    input[type="text"], input[type="password"], select {
      width: 100%;
      padding: 0.6em 0.8em;
      margin-bottom: 0.7em;
      border: 1px solid #c8e6c9;
      border-radius: 8px;
      background: #f9fff9;
      font-size: 1em;
      transition: border 0.2s;
    }
    input:focus, select:focus {
      border: 1.5px solid #388e3c;
      outline: none;
      background: #fff;
    }
    button[type="submit"] {
      margin-top: 1rem;
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color: #fff;
      border: none;
      padding: 0.8em 2em;
      border-radius: 10px;
      font-size: 1.1em;
      font-weight: 600;
      letter-spacing: 0.05em;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      cursor: pointer;
      transition: background 0.2s;
    }
    button[type="submit"]:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
    }
    hr {
      border: none;
      border-top: 1px solid #e0e0e0;
      margin: 1.2em 0;
    }
    .msg {
      margin-top: 1em;
      padding: 0.7em 1em;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
    }
    .msg.error {
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ffcdd2;
    }
    .msg.success {
      background: #e8f5e9;
      color: #388e3c;
      border: 1px solid #c8e6c9;
    }
    .tip {
      color: #888;
      font-size: 0.97em;
      margin-bottom: 0.5em;
      margin-top: -0.5em;
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .card { background: #232d23; border: 1px solid #37474f; }
      input, select { background: #232d23; color: #eee; border: 1px solid #37474f; }
      input:focus, select:focus { background: #263238; border: 1.5px solid #66bb6a; }
      .msg.success { background: #263238; color: #66bb6a; border: 1px solid #388e3c; }
      .msg.error { background: #2d2323; color: #ff8a80; border: 1px solid #c62828; }
    }
  </style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <h2>ComboWord 初始化</h2>
    <label>数据库类型</label>
    <select name="db_type" id="db_type" onchange="onDbTypeChange()" required>
      <option value="">请选择</option>
      <option value="mysql" <?= $db_type==='mysql'?'selected':'' ?>>MySQL</option>
      <option value="sqlite" <?= $db_type==='sqlite'?'selected':'' ?>>SQLite</option>
    </select>
    <div id="mysql_fields" style="display:<?= $db_type==='mysql'?'block':'none' ?>">
      <label>MySQL 主机</label>
      <input type="text" name="mysql_host" value="<?= htmlspecialchars($mysql['host']??'localhost') ?>" <?= $db_type==='mysql'?'required':'' ?>>
      <label>MySQL 数据库名</label>
      <input type="text" name="mysql_db" value="<?= htmlspecialchars($mysql['db']??'') ?>" <?= $db_type==='mysql'?'required':'' ?>>
      <label>MySQL 用户名</label>
      <input type="text" name="mysql_user" value="<?= htmlspecialchars($mysql['user']??'') ?>" <?= $db_type==='mysql'?'required':'' ?>>
      <label>MySQL 密码</label>
      <input type="password" name="mysql_pass" value="<?= htmlspecialchars($mysql['pass']??'') ?>">
    </div>
    <div id="sqlite_fields" style="display:<?= $db_type==='sqlite'?'block':'none' ?>">
      <label>SQLite 文件路径</label>
      <input type="text" name="sqlite_path" value="<?= htmlspecialchars($sqlite) ?>" <?= $db_type==='sqlite'?'required':'' ?>>
      <div class="tip">
        建议 SQLite 路径使用绝对路径或 <b>./data/xxx.db</b>，如 <b>./data/ComboWord.db</b>，确保 PHP 有写入权限。
      </div>
    </div>
    <hr>
    <label>站长用户名</label>
    <input type="text" name="admin_user" value="<?= htmlspecialchars($admin['user']??'') ?>" required>
    <label>站长密码</label>
    <input type="password" name="admin_pass" required>
    <button type="submit">初始化</button>
    <?php if (!empty($GLOBALS['error'])): ?>
      <div class="msg error"><?= htmlspecialchars($GLOBALS['error']) ?></div>
    <?php elseif (!empty($GLOBALS['success'])): ?>
      <div class="msg success"><?= htmlspecialchars($GLOBALS['success']) ?></div>
    <?php endif; ?>
  </form>
  <script>
    function onDbTypeChange() {
      var type = document.getElementById('db_type').value;
      document.getElementById('mysql_fields').style.display = type === 'mysql' ? 'block' : 'none';
      document.getElementById('sqlite_fields').style.display = type === 'sqlite' ? 'block' : 'none';
      document.querySelectorAll('#mysql_fields input').forEach(i=>i.required = (type==='mysql'));
      document.querySelectorAll('#sqlite_fields input').forEach(i=>i.required = (type==='sqlite'));
    }
    window.onload = onDbTypeChange;
  </script>
</body>
</html>
<?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db_type = $_POST['db_type'] ?? '';
  $admin_user = trim($_POST['admin_user'] ?? '');
  $admin_pass = $_POST['admin_pass'] ?? '';
  $mysql = [
    'host' => $_POST['mysql_host'] ?? 'localhost',
    'db'   => $_POST['mysql_db'] ?? '',
    'user' => $_POST['mysql_user'] ?? '',
    'pass' => $_POST['mysql_pass'] ?? '',
  ];
  $sqlite = $_POST['sqlite_path'] ?? '';
  if (!$db_type || !$admin_user || !$admin_pass) {
    $error = '请填写所有必填项。';
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  }
  if ($db_type === 'mysql') {
    if (!$mysql['host'] || !$mysql['db'] || !$mysql['user']) {
      $error = '请填写完整的 MySQL 信息。';
      render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
      exit;
    }
  } elseif ($db_type === 'sqlite') {
    if (!$sqlite) {
      $error = '请填写 SQLite 文件路径。';
      render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
      exit;
    }
    $dir = dirname($sqlite);
    if (!is_dir($dir)) {
      if (!mkdir($dir, 0777, true)) {
        $error = '无法创建 SQLite 目录：' . htmlspecialchars($dir);
        render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
        exit;
      }
    }
  } else {
    $error = '未知数据库类型。';
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  }
  try {
    if ($db_type === 'mysql') {
      $dsn = "mysql:host={$mysql['host']};dbname={$mysql['db']};charset=utf8mb4";
      $pdo = new PDO($dsn, $mysql['user'], $mysql['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    } elseif ($db_type === 'sqlite') {
      $dsn = "sqlite:{$sqlite}";
      $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username VARCHAR(64) UNIQUE NOT NULL,
      password VARCHAR(255) NOT NULL,
      role VARCHAR(16) NOT NULL DEFAULT 'admin'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS schemes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name VARCHAR(128) NOT NULL,
      data TEXT NOT NULL,
      owner_id INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
    $stmt->execute([$admin_user, $hash]);
    $config = [
      'db_type' => $db_type,
      'mysql' => $mysql,
      'sqlite' => $sqlite,
    ];
    file_put_contents(__DIR__.'/config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    file_put_contents(__DIR__.'/config.php', "<?php\nreturn ".var_export($config, true).";\n");
    $success = '初始化成功！请删除 install.php 并重新访问首页。';
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  } catch (Exception $e) {
    $error = '初始化失败：' . $e->getMessage();
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  }
} else {
  render_form();
};
