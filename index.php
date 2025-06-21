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
    .top-app-bar .btn, .top-app-bar .search-btn {
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
    .top-app-bar .btn:hover, .top-app-bar .search-btn:hover {
      background: var(--primary-light);
      color: var(--on-surface);
    }
    .top-app-bar .search-box {
      border-radius: 8px;
      border: none;
      padding: 0.4em 1em;
      font-size: 1em;
      margin-right: 0.5em;
      background: var(--surface);
      color: var(--on-surface);
      box-shadow: 0 1px 4px rgba(255,152,0,0.07);
      outline: none;
      min-width: 180px;
      max-width: 320px;
      transition: box-shadow .2s;
    }
    .top-app-bar .search-box:focus {
      box-shadow: 0 2px 8px var(--primary-light);
    }
    .bing-header-img {
      width: 100vw;
      height: 40vh;
      min-height: 260px;
      max-height: 480px;
      object-fit: cover;
      display: block;
      margin: 0;
      position: relative;
      z-index: 1;
    }
    .bing-header-mask {
      position: absolute;
      left: 50%;
      top: 20vh;
      transform: translate(-50%, -50%);
      background: rgba(0,0,0,0.45);
      color: #fff;
      border-radius: 18px;
      padding: 1.2em 2em;
      max-width: 70vw;
      min-width: 220px;
      font-size: 1.3em;
      font-weight: 500;
      text-align: center;
      box-shadow: 0 2px 16px rgba(0,0,0,0.13);
      z-index: 2;
      word-break: break-all;
      line-height: 1.5;
      pointer-events: none;
      user-select: none;
    }
    .main-content {
      margin-top: 12px;
      display: flex;
      justify-content: center;
      width: 100vw;
    }
    .waterfall {
      width: 60vw;
      min-width: 320px;
      max-width: 1200px;
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin: 0 auto;
      justify-content: flex-start;
      align-items: stretch;
    }
    .card {
      background: var(--card);
      border-radius: 20px;
      box-shadow: var(--card-shadow);
      padding: 2rem 1.5rem;
      min-width: 260px;
      max-width: 1fr;
      flex: 1 1 calc((100% - 40px)/3);
      display: flex;
      flex-direction: column;
      margin-bottom: 1rem;
      border: 1.5px solid #c8e6c9;
      transition: box-shadow .2s, transform .2s, border .2s;
      position: relative;
      overflow: hidden;
    }
    .card:hover {
      box-shadow: 0 8px 32px rgba(255,152,0,0.18);
      transform: translateY(-4px) scale(1.025);
      border-color: var(--primary);
    }
    .card-title {
      font-size: 1.2rem;
      font-weight: bold;
      color: var(--primary-dark);
      margin-bottom: 0.5em;
      letter-spacing: 0.03em;
      z-index: 1;
      position: relative;
    }
    .card-desc {
      flex: 1;
      color: #444;
      margin-bottom: 1.5rem;
      font-size: 1.05em;
      z-index: 1;
      position: relative;
      word-break: break-all;
    }
    .card-btn {
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 10px;
      padding: 0.6em 1.5em;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.05em;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
      z-index: 1;
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.4em;
      justify-content: center;
    }
    .card-btn:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    @media (max-width: 1100px) {
      .waterfall { width: 95vw; }
    }
    @media (max-width: 900px) {
      .waterfall { width: 99vw; }
      .card { min-width: 220px; }
    }
    @media (max-width: 700px) {
      .main-content { margin-top: calc(var(--nav-height) + 22vh + 12px);}
      .waterfall { flex-direction: column; gap: 1.2rem; width: 99vw; }
      .card { min-width: 0; }
      .top-app-bar { flex-direction: column; gap: 0.7em; }
      .bing-header-mask { font-size: 1em; padding: 0.7em 1em; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .top-app-bar { background: #263238; }
      .card { background: #232d23; border: 1.5px solid #37474f; }
      .card-desc { color: #bbb; }
      .card-title { color: #ffb300; }
      .top-app-bar .left { color: #ffb300; }
      .top-app_bar .btn, .top-app_bar .search-btn { background: #37474f; }
      .top-app_bar .btn:hover, .top-app_bar .search-btn:hover { background: #455a64; }
      .bing-header-mask { background: rgba(0,0,0,0.65);}
    }
  </style>
  <script>
    // 获取Bing每日一图
    async function setBingImg() {
      document.getElementById('bing-header-img').src = './banner.jpg';
    }
    // 获取一言
    async function setHitokoto() {
      try {
        let resp = await fetch('https://v1.hitokoto.cn/?encode=json');
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
          const title = card.querySelector('.card-title').textContent.toLowerCase();
          const desc = card.querySelector('.card-desc').textContent.toLowerCase();
          if (!kw || title.includes(kw) || desc.includes(kw)) {
            card.style.display = '';
          } else {
            card.style.display = 'none';
          }
        });
      }
      searchBtn.addEventListener('click', doSearch);
      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          doSearch();
        }
      });
    });
  </script>
</head>
<body>
  <!-- 顶部导航栏 -->
  <div class="top-app-bar">
    <div class="left">
      <span class="material-icons">auto_stories</span>
      NoWord - 没词
    </div>
    <div class="center">
      <input class="search-box" type="text" placeholder="搜索方案/单词">
      <button class="search-btn"><span class="material-icons">search</span>搜索</button>
    </div>
    <div class="right">
      <?php if (isset($user['id'])): ?>
        <span style="display:flex;align-items:center;gap:0.2em;"><span class="material-icons" style="font-size:1.1em;">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
        <?php if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false): ?>
          <a href="/admin/" class="btn"><span class="material-icons">admin_panel_settings</span>后台管理</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="/login.php" class="btn"><span class="material-icons">login</span>登录</a>
      <?php endif; ?>
    </div>
  </div>
  <!-- Bing每日一图头图及一言 -->
  <div style="position:relative;width:100vw;height:40vh;min-height:260px;max-height:480px;overflow:hidden;z-index:1;margin-top:var(--nav-height);">
    <img id="bing-header-img" class="bing-header-img" src="" alt="Bing每日一图">
    <div id="bing-header-mask" class="bing-header-mask"></div>
  </div>
  <!-- 主体内容 -->
  <div class="main-content">
    <div class="waterfall">
      <?php foreach ($schemes as $s): ?>
      <div class="card">
        <div class="card-title"><?= htmlspecialchars($s['name']) ?></div>
        <div class="card-desc">
          <?php
            $data = json_decode($s['data'] ?? '', true);
            if (isset($data['words']) && is_array($data['words'])) {
              $words = array_column($data['words'], 'word');
              echo htmlspecialchars(implode('、', array_slice($words, 0, 12)));
              if (count($words) > 12) echo ' ...';
            } else {
              echo '无词汇';
            }
          ?>
        </div>
        <a class="card-btn" href="/present/index.php?id=<?= $s['id'] ?>"><span class="material-icons">play_circle</span>开始</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
