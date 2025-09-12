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
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#2563eb',
              dark: '#1e40af',
              light: '#60a5fa',
              pale: '#dbeafe',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-900 dark:to-blue-950 text-gray-900 dark:text-gray-100 min-h-screen font-sans">
  <div class="fixed top-0 left-0 right-0 h-16 bg-primary text-white flex items-center justify-between z-50 shadow-lg px-8 backdrop-blur-md">
    <div class="flex items-center gap-3 font-extrabold text-2xl tracking-wide select-none">
      <span class="material-icons text-2xl">manage_accounts</span>
      用户管理
    </div>
    <div class="flex-1 flex justify-center items-center min-w-0">
      <a href="index.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">arrow_back</span>返回后台</a>
    </div>
    <div class="flex items-center gap-6 min-w-[120px] justify-end">
      <?php if (isset($user['id'])): ?>
        <span class="flex items-center gap-2 text-base"><span class="material-icons text-lg">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <?php else: ?>
        <a href="/login.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">login</span>登录</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="max-w-2xl mx-auto mt-24 mb-8 bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-10 border border-primary-light backdrop-blur-md">
    <h2 class="text-2xl font-bold text-primary-dark dark:text-primary-light text-center mb-8">用户管理</h2>
    <?php if ($msg): ?><div class="mb-4 p-3 rounded-lg text-base text-center bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border border-primary-light dark:border-primary-dark"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="flex gap-3 mb-8 items-center flex-wrap" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="username" placeholder="新用户名" required class="px-3 py-2 border border-primary-light rounded-lg bg-primary-pale focus:border-primary-dark focus:bg-white focus:outline-none">
      <input type="password" name="password" placeholder="初始密码" required class="px-3 py-2 border border-primary-light rounded-lg bg-primary-pale focus:border-primary-dark focus:bg-white focus:outline-none">
      <select name="role" class="px-3 py-2 border border-primary-light rounded-lg bg-primary-pale focus:border-primary-dark focus:bg-white focus:outline-none">
        <option value="user">普通用户</option>
        <option value="admin">管理员</option>
      </select>
      <button type="submit" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150">添加用户</button>
    </form>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse rounded-xl shadow bg-white dark:bg-blue-950">
        <thead>
          <tr>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">ID</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">用户名</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">角色</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="hover:bg-primary-pale/70">
            <td class="py-2 px-3"><?= $u['id'] ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($u['username']) ?></td>
            <td class="py-2 px-3"><?= $u['role'] ?></td>
            <td class="py-2 px-3 flex flex-wrap gap-2 justify-center items-center">
              <form method="post" class="inline">
                <input type="hidden" name="action" value="resetpw">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150">重置密码</button>
              </form>
              <form method="post" class="inline">
                <input type="hidden" name="action" value="del">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" onclick="return confirm('确定删除？')" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150">删除</button>
              </form>
              <form method="post" class="inline">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <select name="role" onchange="this.form.submit()" class="px-2 py-1 border border-primary-light rounded-lg bg-primary-pale focus:border-primary-dark focus:bg-white focus:outline-none text-sm">
                  <option value="user" <?= $u['role']==='user'?'selected':'' ?>>普通用户</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>管理员</option>
                </select>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
