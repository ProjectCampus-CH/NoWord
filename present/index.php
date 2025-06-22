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
  <style>
    html, body {
      height:100%;
      margin:0;
      padding:0;
      background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
      color: #222;
      font-family: system-ui, sans-serif;
    }
    body {
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      min-height:100vh;
    }
    #main {
      width:100vw;
      height:100vh;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
    }
    .word {
      font-size: <?= intval($font_size) ?>px;
      font-weight: bold;
      margin-bottom: 0.5em;
      color: var(--primary-dark);
      letter-spacing: 0.04em;
      text-shadow: 0 2px 8px rgba(255,152,0,0.08);
      transition: font-size 0.3s, color 0.3s, text-shadow 0.3s;
      animation: fadeInScale 0.5s cubic-bezier(.4,2,.6,1) both;
    }
    .phonetic {
      font-size: <?= intval($font_size/2) ?>px;
      color: var(--primary);
      margin-bottom: 0.5em;
      letter-spacing: 0.02em;
      animation: fadeInScale 0.5s cubic-bezier(.4,2,.6,1) both;
    }
    .cn {
      font-size: <?= intval($font_size/2.2) ?>px;
      color: #666;
      margin-bottom: 1em;
      letter-spacing: 0.01em;
      animation: fadeInScale 0.5s cubic-bezier(.4,2,.6,1) both;
    }
    .progress {
      color: var(--primary-dark);
      font-size:1.1em;
      font-weight: 700;
      letter-spacing: 0.03em;
      margin-left: 1em;
      margin-right: 0.5em;
      white-space:nowrap;
      max-width: 60vw;
      overflow: hidden;
      text-overflow: ellipsis;
      display: inline-block;
      vertical-align: middle;
      transition: color 0.3s;
      animation: fadeInScale 0.5s cubic-bezier(.4,2,.6,1) both;
    }
    .preload-tip {
      font-size: 20px;
      color: var(--on-primary);
      background: var(--primary-dark);
      padding: 0.4em 1.2em;
      border-radius: 10px;
      margin-top: 1em;
      margin-bottom: 1em;
      box-shadow: 0 2px 8px var(--primary-light);
      display: inline-block;
      animation: fadeInScale 0.5s cubic-bezier(.4,2,.6,1) both;
    }
    .finish-tip {
      font-size: 32px;
      color: var(--on-primary);
      background: var(--primary);
      padding: 0.5em 1.5em;
      border-radius: 16px;
      box-shadow: 0 2px 16px var(--primary-light);
      display: inline-block;
      animation: fadeInScale 0.7s cubic-bezier(.4,2,.6,1) both, pulse 1.2s infinite alternate;
    }
    @keyframes fadeInScale {
      0% { opacity: 0; transform: scale(0.7); }
      80% { opacity: 1; transform: scale(1.08); }
      100% { opacity: 1; transform: scale(1); }
    }
    @keyframes pulse {
      0% { box-shadow: 0 2px 16px var(--primary-light); }
      100% { box-shadow: 0 6px 32px var(--primary); }
    }
    .preload-bar {
      width: 180px;
      height: 8px;
      background: #eee;
      border-radius: 5px;
      margin: 0.5em auto 0.2em auto;
      overflow: hidden;
      position: relative;
      display: block;
      animation: fadeInScale 0.5s cubic-bezier(.4,2,.6,1) both;
    }
    .preload-bar-inner {
      height: 100%;
      background: linear-gradient(90deg, var(--primary-dark) 60%, var(--primary-light) 100%);
      border-radius: 5px;
      transition: width 0.2s;
    }
    .topbar {
      position:fixed;
      top:0;
      right:0;
      left:auto;
      width:auto;
      min-width:320px;
      max-width:98vw;
      display:flex;
      justify-content:flex-end;
      align-items:center;
      padding:1em 2em 1em 2em;
      background: rgba(255,235,205,0.97);
      box-shadow: 0 2px 8px rgba(255,152,0,0.07);
      z-index: 10;
    }
    .controls {
      position:fixed;
      bottom:2em;
      left:0;
      width:100vw;
      display:flex;
      justify-content:center;
      gap:2em;
      z-index: 10;
    }
    .btn {
      background: var(--primary-dark);
      color: var(--on-primary);
      border:none;
      border-radius:10px;
      padding:0.7em 2em;
      font-size:1.1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor:pointer;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
    }
    .btn:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    .fs-btn {
      background:transparent;
      border:none;
      color: var(--primary-dark);
      font-size:1.5em;
      cursor:pointer;
      transition: color .2s;
    }
    .fs-btn:hover {
      color: var(--primary);
    }
    .font-size-bar {
      position:fixed;
      right:2em;
      bottom:2em;
      background:#fff;
      border-radius:8px;
      box-shadow:0 2px 8px var(--primary-light);
      padding:0.5em 1em;
      font-size: 1.05em;
      color: var(--primary-dark);
      border: 1.5px solid var(--primary-light);
      z-index: 10;
      display: flex;
      align-items: center;
      gap: 0.5em;
    }
    .font-size-bar input[type="range"] {
      accent-color: var(--primary);
      height: 4px;
      border-radius: 3px;
      outline: none;
      background: #ffe0b2;
      margin: 0 0.5em;
      width: 120px;
      transition: background 0.2s;
      -webkit-appearance: none;
      appearance: none;
    }
    .font-size-bar input[type="range"]::-webkit-slider-thumb {
      -webkit-appearance: none;
      appearance: none;
      width: 18px;
      height: 18px;
      margin-top: -7px;
      /* 使滑块垂直居中 */
      border-radius: 50%;
      background: var(--primary-dark);
      box-shadow: 0 2px 8px var(--primary-light);
      cursor: pointer;
      border: 2px solid #fff;
      transition: background 0.2s;
    }
    .font-size-bar input[type="range"]:hover::-webkit-slider-thumb {
      background: var(--primary);
    }
    .font-size-bar input[type="range"]::-webkit-slider-runnable-track {
      height: 4px;
      border-radius: 3px;
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]::-ms-fill-lower {
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]::-ms-fill-upper {
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]:focus {
      outline: none;
      box-shadow: 0 0 0 2px var(--primary-light);
    }
    .font-size-bar input[type="range"]::-moz-range-thumb {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: var(--primary-dark);
      box-shadow: 0 2px 8px var(--primary-light);
      cursor: pointer;
      border: 2px solid #fff;
      transition: background 0.2s;
    }
    .font-size-bar input[type="range"]:hover::-moz-range-thumb {
      background: var(--primary);
    }
    .font-size-bar input[type="range"]::-moz-range-track {
      height: 4px;
      border-radius: 3px;
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]::-ms-thumb {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: var(--primary-dark);
      box-shadow: 0 2px 8px var(--primary-light);
      cursor: pointer;
      border: 2px solid #fff;
      transition: background 0.2s;
    }
    .font-size-bar input[type="range"]:hover::-ms-thumb {
      background: var(--primary);
    }
    .font-size-bar input[type="range"]::-ms-fill-lower,
    .font-size-bar input[type="range"]::-ms-fill-upper {
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]:focus::-ms-fill-lower {
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]:focus::-ms-fill-upper {
      background: #ffe0b2;
    }
    .font-size-bar input[type="range"]::-ms-tooltip {
      display: none;
    }
    .font-size-bar input[type="range"]:focus {
      outline: none;
    }
    /* 兼容Firefox */
    .font-size-bar input[type="range"] {
      background: #ffe0b2;
    }
    @media (prefers-color-scheme: dark) {
      .font-size-bar input[type="range"] {
        background: #37474f;
      }
      .font-size-bar input[type="range"]::-webkit-slider-thumb {
        background: #ffb300;
        border: 2px solid #232323;
      }
      .font-size-bar input[type="range"]:hover::-webkit-slider-thumb {
        background: #ffd149;
      }
      .font-size-bar input[type="range"]::-webkit-slider-runnable-track {
        background: #37474f;
      }
      .font-size-bar input[type="range"]::-moz-range-thumb {
        background: #ffb300;
        border: 2px solid #232323;
      }
      .font-size-bar input[type="range"]:hover::-moz-range-thumb {
        background: #ffd149;
      }
      .font-size-bar input[type="range"]::-moz-range-track {
        background: #37474f;
      }
      .font-size-bar input[type="range"]::-ms-thumb {
        background: #ffb300;
        border: 2px solid #232323;
      }
      .font-size-bar input[type="range"]:hover::-ms-thumb {
        background: #ffd149;
      }
      .font-size-bar input[type="range"]::-ms-fill-lower,
      .font-size-bar input[type="range"]::-ms-fill-upper {
        background: #37474f;
      }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <span class="progress" id="progress"></span>
    <button class="fs-btn" id="fs-btn" title="全屏" style="margin-left:1em;">⛶</button>
  </div>
  <div id="main">
    <div class="word" id="word"></div>
    <?php if ($show_phonetic): ?>
    <div class="phonetic" id="phonetic"></div>
    <?php endif; ?>
    <?php if ($show_cn): ?>
    <div class="cn" id="cn"></div>
    <?php endif; ?>
  </div>
  <div class="controls" id="controls" style="display:none;">
    <button class="btn" id="done-btn" onclick="location.href='/'">完成</button>
    <button class="btn" id="restart-btn" onclick="restart()">再来一遍</button>
    <button class="btn" id="pause-btn" onclick="togglePause()">暂停</button>
  </div>
  <div class="font-size-bar">
    字号 <input type="range" min="18" max="120" value="<?= intval($font_size) ?>" id="font-size-range" style="vertical-align:middle;">
    <span id="font-size-val"><?= intval($font_size) ?></span>
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
    function startRound() {
      idx = 0;
      buildSeq();
      showCountdown(() => play(0));
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
      buildSeq();
      play(0);
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
      buildSeq();
      play(0);
    });
  </script>
</body>
</html>