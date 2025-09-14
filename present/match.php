<?php
session_start();
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  die('参数错误');
}
$id = intval($_GET['id']);
if (!file_exists(__DIR__ . '/../config.json')) {
  die('未初始化');
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
$stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
$stmt->execute([$id]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$scheme) die('方案不存在');
$data = json_decode($scheme['data'], true);
$settings = $data['settings'] ?? [];
$words = $data['words'] ?? [];
// 处理消消乐设置
$match_count = isset($settings['match_count']) ? intval($settings['match_count']) : count($words);
$match_count = max(2, min($match_count, count($words)));
$match_audio = !empty($settings['match_audio']);
$match_show_phonetic = !empty($settings['match_show_phonetic']);
// 随机选取指定数量的单词
$all_pairs = [];
foreach ($words as $w) {
  if (!empty($w['word']) && !empty($w['cn'])) {
    $all_pairs[] = [
      'english' => $w['word'],
      'chinese' => $w['cn'],
      'uk_audio' => $w['uk_audio'] ?? '',
      'us_audio' => $w['us_audio'] ?? '',
      'uk_phonetic' => $w['uk_phonetic'] ?? '',
      'us_phonetic' => $w['us_phonetic'] ?? '',
    ];
  }
}
shuffle($all_pairs);
$wordPairs = array_slice($all_pairs, 0, $match_count);
if (empty($wordPairs)) {
  die('该方案没有可用的单词和释义');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>英语单词消消乐 - <?= htmlspecialchars($scheme['name']) ?></title>
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
  <style>
    .circle {
      min-width: 90px;
      min-height: 90px;
      max-width: 120px;
      max-height: 120px;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 1rem;
      font-weight: bold;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s;
      box-sizing: border-box;
      padding: 8px 6px 6px 6px;
      margin: 0 auto;
      user-select: none;
      word-break: break-all;
      line-height: 1.25;
      position: relative;
      background: linear-gradient(135deg, #dbeafe 60%, #fff 100%);
      overflow: hidden;
    }
    .dark .circle {
      background: linear-gradient(135deg, #1e293b 60%, #172554 100%);
    }
    .circle.selected {
      border: 2.5px solid #2563eb;
      background: #e0e7ff;
      box-shadow: 0 0 0 2px #2563eb33;
    }
    .dark .circle.selected {
      background: #1e40af;
      border-color: #60a5fa;
    }
    .circle.matched {
      opacity: 0.4;
      pointer-events: none;
      background: #a7f3d0;
      border-color: #10b981;
    }
    .dark .circle.matched {
      background: #047857;
      border-color: #6ee7b7;
    }
    .card-content {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-size: 1.08rem;
      transition: font-size 0.1s;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.25;
      word-break: break-all;
    }
    .phonetic {
      font-size: 0.82em;
      margin-top: 0.2em;
      color: #2563eb;
    }
    .dark .phonetic {
      color: #60a5fa;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-900 dark:to-blue-950 min-h-screen flex flex-col items-center py-10">
  <div class="fixed top-0 left-0 right-0 h-16 bg-primary text-white flex items-center justify-between z-50 shadow-lg px-8 backdrop-blur-md">
    <div class="flex items-center gap-3 font-extrabold text-xl md:text-2xl tracking-wide select-none">
      <span class="material-icons text-2xl">sports_esports</span>
      单词消消乐
    </div>
    <div class="flex-1 flex justify-center items-center min-w-0">
      <span class="hidden md:inline text-lg font-semibold tracking-wide"><?= htmlspecialchars($scheme['name']) ?></span>
    </div>
    <div class="flex items-center gap-6 min-w-[120px] justify-end">
      <a href="/" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">home</span>首页</a>
    </div>
  </div>
  <div class="w-full max-w-4xl bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-4 md:p-10 mt-24 mb-8 border border-primary-light backdrop-blur-md relative">
    <header class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
      <div class="timer text-xl font-bold text-primary-dark dark:text-primary-light bg-primary-pale px-6 py-2 rounded-full shadow">时间: 00:00</div>
      <div class="score text-xl font-bold text-primary-dark dark:text-primary-light bg-primary-pale px-6 py-2 rounded-full shadow">得分: 0</div>
    </header>
    <div class="w-full h-3 bg-primary-pale rounded-full overflow-hidden mb-4">
      <div class="progress-bar h-full bg-gradient-to-r from-primary-dark to-primary-light rounded-full transition-all duration-500" style="width:0%"></div>
    </div>
    <div class="game-container grid gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 mt-4 p-2 min-h-[180px]">
      <!-- 卡片由JS生成 -->
    </div>
    <?php if ($match_audio): ?>
    <div class="text-primary-dark dark:text-primary-light text-center mt-4 text-base font-medium">
      <span class="inline-flex items-center gap-1"><span class="material-icons text-base align-middle">volume_up</span>点击英文单词可播放音频（如有）</span>
    </div>
    <?php endif; ?>
    <?php if ($match_show_phonetic): ?>
    <div class="text-primary-dark dark:text-primary-light text-center mt-2 text-base font-medium">
      <span class="inline-flex items-center gap-1"><span class="material-icons text-base align-middle">spellcheck</span>中文卡片下方会显示音标（如有）</span>
    </div>
    <?php endif; ?>
    <div class="controls flex justify-center mt-8">
      <button id="restart-btn" class="bg-gradient-to-r from-primary-dark to-primary-light text-white font-bold px-8 py-3 rounded-full shadow-lg hover:scale-105 transition-all duration-200">重新开始</button>
    </div>
  </div>
  <div class="win-message fixed top-0 left-0 w-full h-full hidden justify-center items-center bg-black/70 z-50 flex-col">
    <div class="win-text text-white text-4xl md:text-5xl font-extrabold text-center mb-8 drop-shadow">🎉 Done! </div>
    <div class="win-time text-white text-2xl md:text-3xl font-bold text-center mb-6"></div>
    <button onclick="restartGame()" class="bg-gradient-to-r from-primary-dark to-primary-light text-white font-bold px-8 py-3 rounded-full shadow-lg hover:scale-105 transition-all duration-200">再玩一次</button>
  </div>
  <audio id="success-sound" src="https://assets.mixkit.co/sfx/preview/mixkit-winning-chimes-2015.mp3"></audio>
  <script>
    const wordPairs = <?= json_encode($wordPairs, JSON_UNESCAPED_UNICODE) ?>;
    const matchAudio = <?= $match_audio ? 'true' : 'false' ?>;
    const matchShowPhonetic = <?= $match_show_phonetic ? 'true' : 'false' ?>;
    let gameState = {
      score: 0,
      time: 0,
      timerInterval: null,
      firstClick: null,
      secondClick: null,
      canClick: true,
      matchedPairs: 0,
      totalPairs: wordPairs.length
    };

    function playAudioForWord(word) {
      let audioUrl = '';
      for (const pair of wordPairs) {
        if (pair.english === word) {
          audioUrl = pair.uk_audio || pair.us_audio || '';
          break;
        }
      }
      if (audioUrl) {
        const audio = new Audio(audioUrl);
        audio.play();
      }
    }
    function getPhoneticForWord(word) {
      for (const pair of wordPairs) {
        if (pair.english === word) {
          return pair.uk_phonetic || pair.us_phonetic || '';
        }
      }
      return '';
    }

    // 自动缩小字体直到内容不溢出
    function fitFontSize(cardContent, minFont = 0.7, maxFont = 1.08) {
      let fontSize = maxFont;
      cardContent.style.fontSize = fontSize + 'rem';
      // 检查溢出
      while ((cardContent.scrollHeight > cardContent.offsetHeight || cardContent.scrollWidth > cardContent.offsetWidth) && fontSize > minFont) {
        fontSize -= 0.08;
        cardContent.style.fontSize = fontSize + 'rem';
      }
    }

    function createCard(item) {
      const circle = document.createElement('div');
      circle.className = "circle";
      circle.tabIndex = 0;
      circle.dataset.type = item.type;
      circle.dataset.pair = item.pair;
      // 内容
      const content = document.createElement('div');
      content.className = "card-content";
      if (matchShowPhonetic && item.type === 'chinese') {
        let phonetic = getPhoneticForWord(item.pair);
        if (phonetic) {
          content.innerHTML = `<div>${item.text}</div><div class="phonetic">${phonetic}</div>`;
        } else {
          content.textContent = item.text;
        }
      } else {
        content.textContent = item.text;
      }
      circle.appendChild(content);

      // 事件绑定
      circle.addEventListener('click', function(e) {
        // 只处理.circle本身的点击
        if (!gameState.canClick || circle.classList.contains('matched')) return;
        if (circle.classList.contains('selected')) {
          circle.classList.remove('selected');
          if (gameState.firstClick === circle) {
            gameState.firstClick = null;
          }
          return;
        }
        if (!gameState.timerInterval && !gameState.firstClick) startTimer();
        if (gameState.firstClick) {
          if (gameState.firstClick === circle) return;
          circle.classList.add('selected');
          gameState.secondClick = circle;
          gameState.canClick = false;
          checkMatch();
        } else {
          circle.classList.add('selected');
          gameState.firstClick = circle;
        }
        // 播放音频
        if (matchAudio && item.type === 'english') {
          playAudioForWord(item.text);
        }
      });

      // 自动缩小字体
      setTimeout(() => fitFontSize(content), 0);

      return circle;
    }

    function initGame() {
      const gameContainer = document.querySelector('.game-container');
      gameContainer.innerHTML = '';
      let allItems = [];
      wordPairs.forEach(pair => {
        allItems.push({ text: pair.english, type: 'english', pair: pair.chinese });
        allItems.push({ text: pair.chinese, type: 'chinese', pair: pair.english });
      });
      allItems.sort(() => Math.random() - 0.5);
      allItems.forEach(item => {
        const card = createCard(item);
        gameContainer.appendChild(card);
      });
      gameState.score = 0;
      gameState.time = 0;
      gameState.matchedPairs = 0;
      gameState.firstClick = null;
      gameState.secondClick = null;
      gameState.canClick = true;
      document.querySelector('.score').textContent = `得分: ${gameState.score}`;
      document.querySelector('.timer').textContent = `时间: 00:00`;
      document.querySelector('.progress-bar').style.width = '0%';
      if (gameState.timerInterval) {
        clearInterval(gameState.timerInterval);
        gameState.timerInterval = null;
      }
      document.querySelector('.win-message').classList.add('hidden');
    }

    function handleCircleClick(circle) {
      // 已合并进 createCard 的事件处理
    }

    function checkMatch() {
      const first = gameState.firstClick;
      const second = gameState.secondClick;
      // 只比对内容文本（忽略音标div）
      const getText = el => {
        const content = el.querySelector('.card-content');
        if (!content) return '';
        // 如果有子div，取第一个div的textContent，否则直接取textContent
        if (content.children.length && content.children[0].tagName === 'DIV') {
          return content.children[0].textContent;
        }
        return content.textContent;
      };
      if ((first.dataset.type !== second.dataset.type) &&
          (first.dataset.pair === getText(second) || second.dataset.pair === getText(first))) {
        setTimeout(() => {
          first.classList.add('matched');
          second.classList.add('matched');
          first.classList.remove('selected');
          second.classList.remove('selected');
          gameState.score += 5;
          document.querySelector('.score').textContent = `得分: ${gameState.score}`;
          gameState.matchedPairs++;
          const progress = (gameState.matchedPairs / gameState.totalPairs) * 100;
          document.querySelector('.progress-bar').style.width = `${progress}%`;
          if (gameState.matchedPairs === gameState.totalPairs) endGame();
          resetSelection();
        }, 300);
      } else {
        setTimeout(() => {
          first.classList.remove('selected');
          second.classList.remove('selected');
          resetSelection();
        }, 500);
      }
    }
    function resetSelection() {
      gameState.firstClick = null;
      gameState.secondClick = null;
      gameState.canClick = true;
    }
    function startTimer() {
      gameState.timerInterval = setInterval(() => {
        gameState.time++;
        const minutes = Math.floor(gameState.time / 60).toString().padStart(2, '0');
        const seconds = (gameState.time % 60).toString().padStart(2, '0');
        document.querySelector('.timer').textContent = `时间: ${minutes}:${seconds}`;
      }, 1000);
    }
    function endGame() {
      clearInterval(gameState.timerInterval);
      gameState.timerInterval = null;
      document.getElementById('success-sound').play();
      showWinMessage();
    }
    function showWinMessage() {
      const winMessage = document.querySelector('.win-message');
      winMessage.classList.remove('hidden');
      winMessage.classList.add('flex');
      // 显示用时
      const winTime = document.querySelector('.win-time');
      const minutes = Math.floor(gameState.time / 60).toString().padStart(2, '0');
      const seconds = (gameState.time % 60).toString().padStart(2, '0');
      winTime.innerHTML = `<span class="material-icons align-middle text-2xl mr-2">schedule</span>用时：${minutes}:${seconds}`;
      createConfetti();
    }
    function createConfetti() {
      const colors = ['#FF9A8B', '#FF6A88', '#FF99AC', '#6A11CB', '#2575FC', '#4A6572'];
      for (let i = 0; i < 150; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.width = Math.random() * 10 + 5 + 'px';
        confetti.style.height = Math.random() * 10 + 5 + 'px';
        confetti.style.opacity = Math.random() + 0.5;
        confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
        document.body.appendChild(confetti);
        setTimeout(() => { confetti.remove(); }, 5000);
      }
    }
    function restartGame() {
      document.querySelector('.win-message').classList.add('hidden');
      document.querySelectorAll('.confetti').forEach(confetti => confetti.remove());
      initGame();
    }
    document.addEventListener('DOMContentLoaded', () => {
      initGame();
      document.getElementById('restart-btn').addEventListener('click', restartGame);
    });
  </script>
</body>
</html>
