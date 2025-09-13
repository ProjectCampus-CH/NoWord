<?php
session_start();
if (!file_exists(__DIR__ . '/config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host=" . ($config['mysql']['host'] ?? 'localhost') . ";dbname=" . ($config['mysql']['db'] ?? '') . ";charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'] ?? '', $config['mysql']['pass'] ?? '', [
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
  $pdo = $pdo ?? null;
  if ($pdo) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
  }
}
$schemes = $pdo->query("SELECT * FROM schemes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>NoWord 主页</title>
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
  <!-- 顶部导航栏 -->
  <div class="fixed top-0 left-0 right-0 h-16 bg-primary text-white flex items-center justify-between z-50 shadow-lg px-8 backdrop-blur-md">
    <div class="flex items-center gap-3 font-extrabold text-2xl tracking-wide select-none">
      <span class="material-icons text-2xl">auto_stories</span>
      NoWord - 没词
    </div>
    <div class="flex-1 flex justify-center items-center min-w-0">
      <input class="search-box rounded-xl border-none px-4 py-2 text-base bg-white text-gray-900 shadow focus:outline-none focus:ring-2 focus:ring-primary-light min-w-[180px] max-w-[320px] w-64 mr-2"
        type="text" placeholder="搜索方案/单词">
      <button class="search-btn bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-4 py-2 flex items-center gap-1 font-medium shadow transition-all duration-150">
        <span class="material-icons">search</span>搜索
      </button>
    </div>
    <div class="flex items-center gap-6 min-w-[120px] justify-end">
      <?php if (isset($user['id'])): ?>
        <span class="flex items-center gap-2"><span class="material-icons text-lg">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
        <?php if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false): ?>
          <a href="/admin/" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-4 py-2 flex items-center gap-1 font-medium shadow transition-all duration-150"><span class="material-icons">admin_panel_settings</span>后台管理</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/login.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-4 py-2 flex items-center gap-1 font-medium shadow transition-all duration-150"><span class="material-icons">login</span>登录</a>
      <?php endif; ?>
    </div>
  </div>
  <!-- Bing每日一图头图及一言 -->
  <div class="relative w-screen h-[40vh] min-h-[260px] max-h-[480px] overflow-hidden z-10 mt-16">
    <img id="bing-header-img" class="w-full h-full object-cover" src="" alt="Bing每日一图">
    <div id="bing-header-mask" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-primary-dark/80 text-white rounded-xl px-8 py-6 max-w-[70vw] min-w-[220px] text-lg font-medium text-center shadow-lg pointer-events-none select-none whitespace-pre-line"></div>
  </div>
  <!-- 主体内容 -->
  <div class="mt-4 flex justify-center w-screen">
    <div class="w-[60vw] min-w-[320px] max-w-[1200px] grid grid-cols-3 gap-8 mx-auto
      md:w-[95vw] md:grid-cols-2 sm:w-[99vw] sm:grid-cols-1">
      <?php if (empty($schemes)): ?>
        <div class="col-span-3 flex flex-col items-center justify-center py-24">
          <div class="bg-primary-pale/80 dark:bg-blue-900/80 border border-primary-light rounded-2xl shadow-lg px-10 py-8 text-center max-w-xl">
            <span class="material-icons text-5xl text-primary-dark mb-4">info</span>
            <div class="text-xl font-bold text-primary-dark dark:text-primary-light mb-2">暂无方案</div>
            <div class="text-gray-700 dark:text-blue-100 text-base">请联系管理员在后台添加词汇方案后再来体验。</div>
          </div>
        </div>
      <?php endif; ?>
      <?php foreach ($schemes as $s): ?>
      <div class="bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-8 min-w-[180px] mb-6 border border-primary-light hover:shadow-2xl hover:-translate-y-2 hover:scale-105 transition-all duration-200 flex flex-col backdrop-blur-md card" data-title="<?= htmlspecialchars($s['name']) ?>" data-words="<?= htmlspecialchars(implode(' ', array_column(json_decode($s['data'] ?? '', true)['words'] ?? [], 'word'))) ?>">
        <div class="text-lg font-bold text-primary-dark dark:text-primary-light mb-2 card-title"><?= htmlspecialchars($s['name']) ?></div>
        <div class="flex-1 text-gray-700 dark:text-blue-100 mb-6 text-base break-words card-desc">
          <?php
            $data = json_decode($s['data'] ?? '', true);
            if (isset($data['words']) && is_array($data['words'])) {
              $words = array_column($data['words'], 'word');
              echo htmlspecialchars(implode('、', $words));
            } else {
              echo '无词汇';
            }
          ?>
        </div>
        <a class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-6 py-2 flex items-center gap-1 font-semibold shadow transition-all duration-150 justify-center" href="/present/index.php?id=<?= $s['id'] ?>"><span class="material-icons">play_circle</span>开始</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
    // 获取Bing每日一图
    async function setBingImg() {
      document.getElementById('bing-header-img').src = './banner.jpg';
    }
    // 获取一言
    async function setHitokoto() {
      try {
        let resp = await fetch('https://international.v1.hitokoto.cn/?encode=json');
        let data = await resp.json();
        let text = data.hitokoto;
        let from = data.from ? `—— ${data.from}` : '';
        let from_who = data.from_who ? ` · ${data.from_who}` : '';
        document.getElementById('bing-header-mask').textContent = text + (from || from_who ? `\n${from}${from_who}` : '');
      } catch(e) {
        document.getElementById('bing-header-mask').textContent = '欢迎使用 NoWord!';
      }
    }
    window.addEventListener('DOMContentLoaded', function() {
      setBingImg();
      setHitokoto();
      // 搜索功能
      const searchInput = document.querySelector('.search-box');
      const searchBtn = document.querySelector('.search-btn');
      const cards = Array.from(document.querySelectorAll('.card'));
      function doSearch() {
        const kw = searchInput.value.trim().toLowerCase();
        cards.forEach(card => {
          const title = card.getAttribute('data-title')?.toLowerCase() || '';
          const words = card.getAttribute('data-words')?.toLowerCase() || '';
          if (!kw || title.includes(kw) || words.includes(kw)) {
            card.style.display = '';
          } else {
            card.style.display = 'none';
          }
        });
      }
      if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', doSearch);
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            doSearch();
          }
        });
      }
    });
  </script>
</body>
</html>