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
  <title>初始化 NoWord</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Material Design 3 CDN -->
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body {
      background: var(--md-sys-color-background, #f5fff5);
      color: var(--md-sys-color-on-background, #222);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card {
      max-width: 420px;
      margin: 2rem;
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(0,128,0,0.08);
      background: var(--md-sys-color-surface, #fff);
    }
    .green {
      color: #388e3c;
    }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      .card { background: #222; }
    }
  </style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <h2 class="green">NoWord 初始化</h2>
    <label>数据库类型</label><br>
    <select name="db_type" id="db_type" onchange="onDbTypeChange()" required>
      <option value="">请选择</option>
      <option value="mysql" <?= $db_type==='mysql'?'selected':'' ?>>MySQL</option>
      <option value="sqlite" <?= $db_type==='sqlite'?'selected':'' ?>>SQLite</option>
    </select>
    <div id="mysql_fields" style="display:<?= $db_type==='mysql'?'block':'none' ?>">
      <label>MySQL 主机</label>
      <input type="text" name="mysql_host" value="<?= htmlspecialchars($mysql['host']??'localhost') ?>" required>
      <label>MySQL 数据库名</label>
      <input type="text" name="mysql_db" value="<?= htmlspecialchars($mysql['db']??'') ?>" required>
      <label>MySQL 用户名</label>
      <input type="text" name="mysql_user" value="<?= htmlspecialchars($mysql['user']??'') ?>" required>
      <label>MySQL 密码</label>
      <input type="password" name="mysql_pass" value="<?= htmlspecialchars($mysql['pass']??'') ?>">
    </div>
    <div id="sqlite_fields" style="display:<?= $db_type==='sqlite'?'block':'none' ?>">
      <label>SQLite 文件路径</label>
      <input type="text" name="sqlite_path" value="<?= htmlspecialchars($sqlite) ?>" required>
    </div>
    <hr>
    <label>站长用户名</label>
    <input type="text" name="admin_user" value="<?= htmlspecialchars($admin['user']??'') ?>" required>
    <label>站长密码</label>
    <input type="password" name="admin_pass" required>
    <button type="submit" style="margin-top:1rem;background:#388e3c;color:#fff;border:none;padding:0.7em 2em;border-radius:8px;">初始化</button>
    <?php if (!empty($GLOBALS['error'])): ?>
      <div style="color:#c00;margin-top:1em;"><?= htmlspecialchars($GLOBALS['error']) ?></div>
    <?php elseif (!empty($GLOBALS['success'])): ?>
      <div style="color:#388e3c;margin-top:1em;"><?= htmlspecialchars($GLOBALS['success']) ?></div>
    <?php endif; ?>
  </form>
  <script>
    function onDbTypeChange() {
      var type = document.getElementById('db_type').value;
      document.getElementById('mysql_fields').style.display = type === 'mysql' ? 'block' : 'none';
      document.getElementById('sqlite_fields').style.display = type === 'sqlite' ? 'block' : 'none';
    }
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
    } else {
      throw new Exception('未知数据库类型');
    }
    // 创建表
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
    // 插入管理员
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
    $stmt->execute([$admin_user, $hash]);
    // 保存配置
    $config = [
      'db_type' => $db_type,
      'mysql' => $mysql,
      'sqlite' => $sqlite,
    ];
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
}
