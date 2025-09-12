<?php
if (!file_exists(__DIR__ . '/../config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);

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
    die('SQLite 数据库文件不存在，请检查路径或重新初始化。');
  }
  $dsn = "sqlite:$sqlite_path";
  $pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
}
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
$stmt->execute([$id]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$scheme) exit('方案不存在');
$data = json_decode($scheme['data'], true);
$settings = $data['settings'] ?? [];
$words = $data['words'] ?? [];
if (!is_array($words)) $words = [];
$rounds = $settings['rounds'] ?? 1;
$repeat = $settings['repeat'] ?? 1;
$wait = $settings['wait'] ?? 1;
$show_cn = !empty($settings['show_cn']);
$shuffle = !empty($settings['shuffle']);
$show_phonetic = !isset($settings['show_phonetic']) || $settings['show_phonetic'];
$font_size = $settings['font_size'] ?? 36;
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>放映 - <?= htmlspecialchars($scheme['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
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
    body { overscroll-behavior-y: none; }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-900 dark:to-blue-950 text-gray-900 dark:text-gray-100 min-h-screen font-sans flex flex-col items-center justify-center">
  <div class="fixed top-0 right-0 left-0 flex items-center justify-between px-8 h-16 bg-primary text-white shadow-lg z-50">
    <span class="font-bold text-lg tracking-wide flex items-center gap-2">
      <span class="material-icons text-xl">slideshow</span>
      <span id="progress"></span>
    </span>
    <button class="ml-4 text-2xl hover:text-primary-light transition" id="fs-btn" title="全屏">⛶</button>
  </div>
  <div id="main" class="flex flex-col items-center justify-center w-screen h-screen pt-16">
    <div id="word" class="font-bold mb-2 text-primary-dark dark:text-primary-light transition-all duration-300" style="font-size: <?= intval($font_size) ?>px;"></div>
    <?php if ($show_phonetic): ?>
    <div id="phonetic" class="mb-2 text-primary text-center transition-all duration-300" style="font-size: <?= intval($font_size/2) ?>px;"></div>
    <?php endif; ?>
    <?php if ($show_cn): ?>
    <div id="cn" class="mb-4 text-blue-700 dark:text-blue-200 text-center transition-all duration-300" style="font-size: <?= intval($font_size/2.2) ?>px;"></div>
    <?php endif; ?>
  </div>
  <div id="controls" class="fixed bottom-8 left-0 w-full flex justify-center gap-6 z-50" style="display:none;">
    <button id="done-btn" onclick="location.href='/'" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-8 py-3 font-semibold shadow transition-all duration-150">完成</button>
    <button id="restart-btn" onclick="restart()" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-8 py-3 font-semibold shadow transition-all duration-150">再来一遍</button>
    <button id="pause-btn" onclick="togglePause()" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-8 py-3 font-semibold shadow transition-all duration-150">暂停</button>
  </div>
  <div class="fixed right-8 bottom-8 bg-white/90 dark:bg-blue-950/80 rounded-xl shadow-lg px-4 py-2 flex items-center gap-2 border border-primary-light z-50">
    字号
    <input type="range" min="18" max="120" value="<?= intval($font_size) ?>" id="font-size-range" class="accent-primary w-32 mx-2">
    <span id="font-size-val" class="font-semibold text-primary-dark dark:text-primary-light"><?= intval($font_size) ?></span>
  </div>
  <script>
    const wordsRaw = <?= json_encode($words, JSON_UNESCAPED_UNICODE) ?>;
    let words = [];
    let rounds = <?= intval($rounds) ?>;
    let repeat = <?= intval($repeat) ?>;
    let wait = <?= intval($wait) ?> * 1000;
    let show_cn = <?= $show_cn ? 'true' : 'false' ?>;
    let show_phonetic = <?= $show_phonetic ? 'true' : 'false' ?>;
    let shuffle = <?= $shuffle ? 'true' : 'false' ?>;
    let fontSize = <?= intval($font_size) ?>;
    let initialFontSize = fontSize;
    let total = 0, idx = 0;
    let seq = [];
    let _pause = false;
    let _timer = null;
    let _pendingPlay = null;
    let _countdownTimer = null;
    let roundCount = 0;

    function shuffleArr(arr) {
      for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
    }
    function buildSeq() {
      seq = [];
      let oneRound = [];
      let widx = Array.from({length: words.length}, (_,i)=>i);
      if (shuffle) shuffleArr(widx);
      for (let i of widx) {
        for (let t = 0; t < repeat; ++t) oneRound.push(i);
      }
      seq = oneRound;
      total = seq.length;
    }
    function showCountdown(cb) {
      let countdown = 3;
      document.getElementById('controls').style.display = 'none';
      document.getElementById('progress').textContent = '';
      document.getElementById('word').textContent = '';
      if (show_phonetic) document.getElementById('phonetic').textContent = '';
      if (show_cn) document.getElementById('cn').textContent = '';
      function tick() {
        if (countdown > 0) {
          document.getElementById('word').textContent = countdown;
          document.getElementById('word').classList.add('word');
          countdown--;
          _countdownTimer = setTimeout(tick, 1000);
        } else {
          document.getElementById('word').textContent = '';
          if (show_phonetic) document.getElementById('phonetic').textContent = '';
          if (show_cn) document.getElementById('cn').textContent = '';
          cb && cb();
        }
      }
      tick();
    }
    function setControlsMode(mode) {
      const doneBtn = document.getElementById('done-btn');
      const restartBtn = document.getElementById('restart-btn');
      if (mode === 'playing') {
        doneBtn.style.display = 'none';
        restartBtn.style.display = 'none';
      } else {
        doneBtn.style.display = '';
        restartBtn.style.display = '';
      }
    }
    function showFinishTip() {
      const wordDiv = document.getElementById('word');
      wordDiv.innerHTML = '<span class="finish-tip">🎉 已完成本轮词汇播放！</span>';
      if (show_phonetic) document.getElementById('phonetic').textContent = '';
      if (show_cn) document.getElementById('cn').textContent = '';
    }
    function getProgressText(idx) {
      let seen = new Set();
      for (let i = 0; i <= idx && i < seq.length; ++i) {
        seen.add(seq[i]);
      }
      return `已完成 ${seen.size} / ${words.length} 个`;
    }
    async function preloadAllAudio(cb) {
      let audios = [];
      let loaded = 0, total = 0;
      let audioUrls = new Set();
      for (const w of words) {
        if (w.uk_audio) audioUrls.add(w.uk_audio);
        if (w.us_audio) audioUrls.add(w.us_audio);
      }
      total = audioUrls.size;
      if (total === 0) { cb && cb(); return; }
      let tipDiv = document.getElementById('word');
      tipDiv.innerHTML = '<span class="preload-tip">正在预加载音频，请稍候...</span><div class="preload-bar"><div class="preload-bar-inner" id="preload-bar-inner" style="width:0%"></div></div><div id="preload-bar-text" style="font-size:0.95em;color:orange;margin-top:0.2em;"></div>';
      let finished = false;
      let done = () => {
        if (!finished) {
          finished = true;
          tipDiv.innerHTML = '';
          cb && cb();
        }
      };
      let count = 0;
      for (const url of audioUrls) {
        const audio = new Audio();
        audio.preload = 'auto';
        audio.src = url;
        audio.oncanplaythrough = audio.onerror = function() {
          loaded++;
          let percent = Math.round(loaded/total*100);
          document.getElementById('preload-bar-inner').style.width = percent + '%';
          document.getElementById('preload-bar-text').textContent = `已加载 ${loaded} / ${total}`;
          if (loaded === total) setTimeout(done, 200);
        };
        audio.load();
        audios.push(audio);
        count++;
      }
      setTimeout(done, 6000);
    }
    function startRound(isFirst) {
      idx = 0;
      buildSeq();
      if (isFirst) {
        showCountdown(() => play(0));
      } else {
        play(0);
      }
    }
    function play(idx) {
      if (_pause) {
        _pendingPlay = () => play(idx);
        return;
      }
      if (words.length === 0) {
        document.getElementById('progress').textContent = '';
        document.getElementById('word').textContent = '（方案词汇为空）';
        if (show_phonetic) document.getElementById('phonetic').textContent = '';
        if (show_cn) document.getElementById('cn').textContent = '';
        document.getElementById('controls').style.display = '';
        setControlsMode('done');
        return;
      }
      if (idx >= total) {
        document.getElementById('controls').style.display = '';
        setControlsMode('done');
        document.getElementById('progress').innerHTML = `<span style="color:var(--primary);font-weight:bold;">已读 0 / ${words.length} 个</span>`;
        if (roundCount + 1 < rounds) {
          roundCount++;
          setTimeout(() => {
            idx = 0;
            buildSeq();
            // 不是第一轮，直接进入
            play(0);
          }, 300);
        } else {
          showFinishTip();
          window.scrollTo(0,0);
        }
        return;
      }
      let w = words[seq[idx]];
      document.getElementById('controls').style.display = '';
      setControlsMode('playing');
      document.getElementById('progress').textContent = getProgressText(idx);
      document.getElementById('word').textContent = w.word;
      document.getElementById('word').classList.add('word');
      if (show_phonetic) {
        document.getElementById('phonetic').textContent = w.uk_phonetic || w.us_phonetic || '';
        document.getElementById('phonetic').classList.add('phonetic');
      }
      if (show_cn) {
        document.getElementById('cn').textContent = w.cn || '';
        document.getElementById('cn').classList.add('cn');
      }
      if (!window._audioErrorUrlSet) window._audioErrorUrlSet = new Set();
      function playAudio(url, cb) {
        if (!url || window._audioErrorUrlSet.has(url)) { cb && cb(); return; }
        let audio = new Audio(url);
        audio.onerror = function(e) {
          if (!window._audioErrorLog) window._audioErrorLog = [];
          window._audioErrorLog.push({url, time: Date.now()});
          window._audioErrorUrlSet.add(url);
          cb && cb();
        };
        audio.onended = function() { cb && cb(); };
        try {
          audio.play().catch(function(){ cb && cb(); });
        } catch(e) {
          cb && cb();
        }
      }
      function nextStep() {
        if (_pause) {
          _pendingPlay = () => nextStep();
        } else if (idx + 1 < total) {
          play(idx+1);
        } else {
          document.getElementById('controls').style.display = '';
          setControlsMode('done');
          document.getElementById('progress').innerHTML = `<span style="color:var(--primary);font-weight:bold;">已读 0 / ${words.length} 个</span>`;
          if (roundCount + 1 < rounds) {
            roundCount++;
            setTimeout(() => {
              idx = 0;
              buildSeq();
              play(0);
            }, 300);
          } else {
            showFinishTip();
            window.scrollTo(0,0);
          }
        }
      }
      playAudio(w.uk_audio, function() {
        setTimeout(function() {
          playAudio(w.us_audio, function() {
            _timer = setTimeout(nextStep, wait);
          });
        }, wait);
      });
    }
    function restart() {
      document.getElementById('controls').style.display = '';
      setControlsMode('playing');
      fontSize = initialFontSize;
      document.getElementById('font-size-range').value = fontSize;
      document.getElementById('font-size-val').textContent = fontSize;
      document.getElementById('word').style.fontSize = fontSize + 'px';
      if (document.getElementById('phonetic')) document.getElementById('phonetic').style.fontSize = (fontSize/2) + 'px';
      if (document.getElementById('cn')) document.getElementById('cn').style.fontSize = (fontSize/2.2) + 'px';
      roundCount = 0;
      window._audioErrorUrlSet = new Set();
      _pause = false;
      if (_timer) { clearTimeout(_timer); _timer = null; }
      if (_countdownTimer) { clearTimeout(_countdownTimer); _countdownTimer = null; }
      document.getElementById('pause-btn').textContent = '暂停';
      startRound(true);
    }
    function togglePause() {
      _pause = !_pause;
      let btn = document.getElementById('pause-btn');
      if (_pause) {
        btn.textContent = '继续';
        if (_timer) { clearTimeout(_timer); _timer = null; }
      } else {
        btn.textContent = '暂停';
        if (_pendingPlay) {
          let fn = _pendingPlay;
          _pendingPlay = null;
          fn();
        }
      }
    }
    document.getElementById('font-size-range').addEventListener('input', function() {
      fontSize = parseInt(this.value);
      document.getElementById('font-size-val').textContent = fontSize;
      document.getElementById('word').style.fontSize = fontSize + 'px';
      if (document.getElementById('phonetic')) document.getElementById('phonetic').style.fontSize = (fontSize/2) + 'px';
      if (document.getElementById('cn')) document.getElementById('cn').style.fontSize = (fontSize/2.2) + 'px';
    });
    document.getElementById('fs-btn').onclick = function() {
      let el = document.documentElement;
      if (!document.fullscreenElement) el.requestFullscreen();
      else document.exitFullscreen();
    };
    // 初始化
    words = wordsRaw;
    roundCount = 0;
    preloadAllAudio(() => {
      startRound(true);
    });
  </script>
</body>
</html>