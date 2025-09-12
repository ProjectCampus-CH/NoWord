<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header('Location: /admin/index.php');
  exit;
}
if (!file_exists(__DIR__ . '/config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['db']};charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'], $config['mysql']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} else {
  $sqlite_path = $config['sqlite'];
  if (!preg_match('/^([a-zA-Z]:)?[\/\\\\]/', $sqlite_path)) {
    $sqlite_path = realpath(__DIR__ . '/' . $sqlite_path);
    if ($sqlite_path === false) {
      $sqlite_path = __DIR__ . '/' . $config['sqlite'];
    }
  }
  if (!file_exists($sqlite_path)) {
    $msg = 'SQLite 数据库文件不存在，请检查路径或重新初始化。';
  } elseif (!is_writable($sqlite_path)) {
    $msg = 'SQLite 数据库文件不可写，请检查权限。';
  } else {
    $dsn = "sqlite:$sqlite_path";
    $pdo = new PDO($dsn, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  }
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    header('Location: /admin/index.php');
    exit;
  } else {
    $msg = '用户名或密码错误';
  }
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>登录 - NoWord</title>
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
  <script>
    // 加载Bing每日一图
    window.addEventListener('DOMContentLoaded', function() {
      document.getElementById('bing-bg').src = './banner.jpg';
      // 加载公告
      fetch('announcement.json').then(r=>r.json()).then(data=>{
        let box = document.getElementById('announcement-box');
        if (data.title) box.innerHTML = `<h3>${data.title}</h3>`;
        if (data.content) box.innerHTML += `<div>${data.content.replace(/\n/g,'<br>')}</div>`;
      }).catch(()=>{
        let box = document.getElementById('announcement-box');
        box.innerHTML = '<h3>公告</h3><div>暂无公告</div>';
      });
    });
  </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-900 dark:to-blue-950 text-gray-900 dark:text-gray-100 min-h-screen font-sans flex items-stretch justify-end relative overflow-hidden">
  <img id="bing-bg" class="fixed top-0 left-0 w-screen h-screen z-0 object-cover brightness-90 blur-[1px] transition" src="" alt="Bing每日一图">
  <div class="relative z-10 w-screen min-h-screen flex flex-row items-center justify-end">
    <div class="w-[40vw] max-w-[480px] min-w-[260px] max-h-[60vh] bg-white/90 dark:bg-blue-950/80 rounded-3xl ml-[6vw] mr-[2vw] p-8 overflow-y-auto shadow-xl flex flex-col justify-center items-start text-[1.08em] text-gray-800 dark:text-blue-100 hidden md:hidden lg:flex">
      <div class="w-full" id="announcement-box"></div>
    </div>
    <form class="w-[340px] min-w-[260px] max-w-[400px] m-0 ml-0 mr-[6vw] p-10 pt-10 pb-8 rounded-3xl shadow-xl bg-white/95 dark:bg-blue-950/90 flex flex-col gap-4 border border-primary-light self-center backdrop-blur-md" method="post" autocomplete="off">
      <h2 class="mb-2 text-primary-dark tracking-wide font-bold text-center text-2xl">NoWord 登录</h2>
      <label class="font-medium mb-1 text-primary-dark dark:text-primary-light">用户名</label>
      <input type="text" name="username" required class="w-full px-3 py-2 mb-2 border border-primary-light dark:border-primary-dark rounded-lg bg-primary-pale dark:bg-blue-900 text-base focus:border-primary-dark focus:bg-white dark:focus:bg-blue-950 focus:outline-none">
      <label class="font-medium mb-1 text-primary-dark dark:text-primary-light">密码</label>
      <input type="password" name="password" required class="w-full px-3 py-2 mb-2 border border-primary-light dark:border-primary-dark rounded-lg bg-primary-pale dark:bg-blue-900 text-base focus:border-primary-dark focus:bg-white dark:focus:bg-blue-950 focus:outline-none">
      <div class="flex items-center gap-2 mb-2 text-sm text-gray-500 dark:text-blue-200">
        <input type="checkbox" name="keep_login" id="keep_login" class="w-auto">
        <label for="keep_login" class="m-0 inline font-normal">保持登录</label>
      </div>
      <button type="submit" class="mt-4 bg-gradient-to-r from-primary-dark to-primary-light text-white rounded-xl px-6 py-2 flex items-center gap-1 font-semibold shadow hover:from-primary hover:to-primary-light hover:text-primary-dark transition-all duration-150 justify-center">
        <span class="material-icons">login</span>登录
      </button>
      <?php if ($msg): ?>
        <div class="mt-4 p-3 rounded-lg text-base text-center bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border border-primary-light dark:border-primary-dark"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
    </form>
  </div>
  <style>
    @media (max-width: 900px) {
      .announcement { display: none !important; }
      .login-card { margin: 0 auto !important; }
      .login-layout { justify-content: center !important; }
    }
  </style>
</body>
</html>
