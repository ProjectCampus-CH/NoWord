<?php
$config = require __DIR__ . '/../config.php';
$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['db']};charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'], $config['mysql']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} else {
  $dsn = "sqlite:{$config['sqlite']}";
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
    html, body { height:100%; margin:0; padding:0; background: #f5fff5; color: #222;}
    body { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh;}
    #main { width:100vw; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;}
    .word { font-size: <?= intval($font_size) ?>px; font-weight: bold; margin-bottom: 0.5em;}
    .phonetic { font-size: <?= intval($font_size/2) ?>px; color: #388e3c; margin-bottom: 0.5em;}
    .cn { font-size: <?= intval($font_size/2.2) ?>px; color: #666; margin-bottom: 1em;}
    .topbar { position:fixed; top:0; left:0; width:100vw; display:flex; justify-content:space-between; align-items:center; padding:1em;}
    .progress { color:#388e3c; font-size:1.1em;}
    .controls { position:fixed; bottom:2em; left:0; width:100vw; display:flex; justify-content:center; gap:2em;}
    .btn { background:#388e3c; color:#fff; border:none; border-radius:8px; padding:0.7em 2em; font-size:1.1em; cursor:pointer;}
    .btn:hover { background:#2e7031; }
    .fs-btn { background:transparent; border:none; color:#388e3c; font-size:1.5em; cursor:pointer;}
    .font-size-bar { position:fixed; right:2em; bottom:2em; background:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(56,142,60,0.10); padding:0.5em 1em;}
    @media (prefers-color-scheme: dark) {
      body, #main { background: #1a1f1a; color: #eee; }
      .font-size-bar { background: #222; }
      .cn { color: #bbb; }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <span class="progress" id="progress"></span>
    <button class="fs-btn" id="fs-btn" title="全屏">⛶</button>
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
    <button class="btn" onclick="location.href='/'">完成</button>
    <button class="btn" onclick="restart()">再来一遍</button>
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
    let total = 0, idx = 0, round = 1;
    let seq = [];
    function shuffleArr(arr) {
      for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
    }
    function buildSeq() {
      seq = [];
      for (let r = 0; r < rounds; ++r) {
        let widx = Array.from({length: words.length}, (_,i)=>i);
        if (shuffle) shuffleArr(widx);
        for (let i of widx) {
          for (let t = 0; t < repeat; ++t) seq.push(i);
        }
      }
      total = seq.length;
    }
    function play(idx) {
      if (words.length === 0) {
        document.getElementById('progress').textContent = '';
        document.getElementById('word').textContent = '（方案词汇为空）';
        if (show_phonetic) document.getElementById('phonetic').textContent = '';
        if (show_cn) document.getElementById('cn').textContent = '';
        document.getElementById('controls').style.display = '';
        return;
      }
      if (idx >= total) {
        document.getElementById('controls').style.display = '';
        document.getElementById('progress').textContent = '已完成';
        document.getElementById('word').textContent = '';
        if (show_phonetic) document.getElementById('phonetic').textContent = '';
        if (show_cn) document.getElementById('cn').textContent = '';
        window.scrollTo(0,0);
        return;
      }
      let w = words[seq[idx]];
      document.getElementById('progress').textContent = `第 ${idx+1} / ${total} 个`;
      document.getElementById('word').textContent = w.word;
      if (show_phonetic) document.getElementById('phonetic').textContent = w.uk_phonetic || w.us_phonetic || '';
      if (show_cn) document.getElementById('cn').textContent = w.cn || '';
      // 播放英式音标和发音
      if (w.uk_audio) {
        let audio = new Audio(w.uk_audio);
        audio.play();
        audio.onended = function() {
          // 播放美式音标和发音
          if (w.us_audio) {
            let audio2 = new Audio(w.us_audio);
            audio2.play();
            audio2.onended = function() {
              setTimeout(()=>play(idx+1), wait);
            }
          } else {
            setTimeout(()=>play(idx+1), wait);
          }
        }
      } else if (w.us_audio) {
        let audio2 = new Audio(w.us_audio);
        audio2.play();
        audio2.onended = function() {
          setTimeout(()=>play(idx+1), wait);
        }
      } else {
        setTimeout(()=>play(idx+1), wait);
      }
    }
    function restart() {
      document.getElementById('controls').style.display = 'none';
      fontSize = initialFontSize;
      document.getElementById('font-size-range').value = fontSize;
      document.getElementById('font-size-val').textContent = fontSize;
      document.getElementById('word').style.fontSize = fontSize + 'px';
      if (document.getElementById('phonetic')) document.getElementById('phonetic').style.fontSize = (fontSize/2) + 'px';
      if (document.getElementById('cn')) document.getElementById('cn').style.fontSize = (fontSize/2.2) + 'px';
      buildSeq();
      play(0);
    }
    // 字号调节
    document.getElementById('font-size-range').addEventListener('input', function() {
      fontSize = parseInt(this.value);
      document.getElementById('font-size-val').textContent = fontSize;
      document.getElementById('word').style.fontSize = fontSize + 'px';
      if (document.getElementById('phonetic')) document.getElementById('phonetic').style.fontSize = (fontSize/2) + 'px';
      if (document.getElementById('cn')) document.getElementById('cn').style.fontSize = (fontSize/2.2) + 'px';
    });
    // 全屏
    document.getElementById('fs-btn').onclick = function() {
      let el = document.documentElement;
      if (!document.fullscreenElement) el.requestFullscreen();
      else document.exitFullscreen();
    };
    // 初始化
    words = wordsRaw;
    buildSeq();
    play(0);
  </script>
</body>
</html>