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
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#2563eb', // blue-600
              dark: '#1e40af',    // blue-800
              light: '#60a5fa',   // blue-400
              pale: '#dbeafe',    // blue-100
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
      <span class="material-icons text-2xl">admin_panel_settings</span>
      NoWord 后台
    </div>
    <div class="flex-1 flex justify-center items-center min-w-0">
      <a href="/" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">home</span>回到首页</a>
    </div>
    <div class="flex items-center gap-6 min-w-[120px] justify-end">
      <span class="flex items-center gap-2 text-base"><span class="material-icons text-lg">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <a href="/logout.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">logout</span>退出登录</a>
    </div>
  </div>
  <div class="mt-24 flex justify-center w-screen">
    <div class="w-[60vw] min-w-[320px] max-w-[1200px] grid grid-cols-3 gap-8 mx-auto
      md:w-[95vw] md:grid-cols-2 sm:w-[99vw] sm:grid-cols-1">
      <?php if ($user['role'] === 'admin'): ?>
      <div class="bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-10 min-w-[220px] mb-6 border border-primary-light hover:shadow-2xl hover:-translate-y-2 hover:scale-105 transition-all duration-200 flex flex-col backdrop-blur-md">
        <div class="text-xl font-bold text-primary-dark dark:text-primary-light mb-3 flex items-center gap-2"><span class="material-icons">group</span> 用户管理</div>
        <div class="flex-1 text-gray-700 dark:text-blue-100 mb-8 text-base break-words">添加、删除用户，修改角色，重置密码，修改用户名。</div>
        <a class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-7 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150 justify-center" href="users.php"><span class="material-icons">arrow_forward</span>进入</a>
      </div>
      <?php endif; ?>
      <div class="bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-10 min-w-[220px] mb-6 border border-primary-light hover:shadow-2xl hover:-translate-y-2 hover:scale-105 transition-all duration-200 flex flex-col backdrop-blur-md">
        <div class="text-xl font-bold text-primary-dark dark:text-primary-light mb-3 flex items-center gap-2"><span class="material-icons">library_books</span> 方案管理</div>
        <div class="flex-1 text-gray-700 dark:text-blue-100 mb-8 text-base break-words">管理所有词汇方案，新增、编辑、删除、复制、重命名。</div>
        <a class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-7 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150 justify-center" href="schemes.php"><span class="material-icons">arrow_forward</span>进入</a>
      </div>
      <div class="bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-10 min-w-[220px] mb-6 border border-primary-light hover:shadow-2xl hover:-translate-y-2 hover:scale-105 transition-all duration-200 flex flex-col backdrop-blur-md">
        <div class="text-xl font-bold text-primary-dark dark:text-primary-light mb-3 flex items-center gap-2"><span class="material-icons">home</span> 返回主页</div>
        <div class="flex-1 text-gray-700 dark:text-blue-100 mb-8 text-base break-words">回到 NoWord 首页，查看和开始放映方案。</div>
        <a class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-7 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150 justify-center" href="/"><span class="material-icons">arrow_forward</span>返回</a>
      </div>
    </div>
  </div>
</body>
</html>
