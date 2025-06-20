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

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) exit('参数错误');
$stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
$stmt->execute([$id]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$scheme) exit('方案不存在');

$data = json_decode($scheme['data'], true);
if (!$data) $data = ['words'=>[], 'settings'=>[]];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  // 保存方案设置和词汇
  $scheme_name = trim($_POST['name'] ?? '');
  $settings = [
    'rounds' => intval($_POST['rounds'] ?? 1),
    'repeat' => intval($_POST['repeat'] ?? 1),
    'wait'   => intval($_POST['wait'] ?? 1),
    'show_cn' => !empty($_POST['show_cn']),
    'shuffle' => !empty($_POST['shuffle']),
    'show_phonetic' => !empty($_POST['show_phonetic']),
    'font_size' => intval($_POST['font_size'] ?? 36),
  ];
  $words = [];
  if (isset($_POST['word']) && is_array($_POST['word'])) {
    foreach ($_POST['word'] as $i => $w) {
      $w = trim($w);
      if ($w === '') continue;
      $words[] = [
        'word' => $w,
        'uk_phonetic' => trim($_POST['uk_phonetic'][$i] ?? ''),
        'uk_audio' => trim($_POST['uk_audio'][$i] ?? ''),
        'us_phonetic' => trim($_POST['us_phonetic'][$i] ?? ''),
        'us_audio' => trim($_POST['us_audio'][$i] ?? ''),
        'cn' => trim($_POST['cn'][$i] ?? ''),
      ];
    }
  }
  $data = ['words'=>$words, 'settings'=>$settings];
  $stmt = $pdo->prepare("UPDATE schemes SET name=?, data=? WHERE id=?");
  $stmt->execute([$scheme_name, json_encode($data, JSON_UNESCAPED_UNICODE), $id]);
  $msg = '保存成功。';
  // 刷新数据
  $scheme['name'] = $scheme_name;
  $scheme['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
}

$data = json_decode($scheme['data'], true);
$settings = $data['settings'] ?? [];
$words = $data['words'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>编辑方案 - NoWord</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      min-height: 100vh;
      font-family: system-ui, sans-serif;
      margin: 0;
    }
    .container {
      max-width: 900px;
      margin: 2rem auto;
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.10);
      padding: 2.5rem 2rem 2rem 2rem;
      border: 1.5px solid #c8e6c9;
    }
    h2 {
      color: #388e3c;
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.2em;
    }
    a {
      color: #388e3c;
      text-decoration: underline;
      font-weight: 500;
      font-size: 1.05em;
      transition: color .2s;
    }
    a:hover {
      color: #2e7031;
      text-decoration: none;
    }
    .settings {
      margin-bottom: 2em;
      display: flex;
      flex-wrap: wrap;
      gap: 1.2em 2em;
      align-items: center;
      background: #f9fff9;
      border-radius: 10px;
      padding: 1.2em 1em 0.7em 1em;
      border: 1px solid #c8e6c9;
    }
    .settings label {
      margin-right: 1.5em;
      color: #388e3c;
      font-weight: 500;
      font-size: 1em;
      margin-bottom: 0.3em;
    }
    .settings input[type="text"], .settings input[type="number"] {
      padding: 0.4em 0.7em;
      border: 1px solid #c8e6c9;
      border-radius: 7px;
      background: #fff;
      font-size: 1em;
      margin-left: 0.3em;
      width: 6em;
      transition: border 0.2s;
    }
    .settings input[type="checkbox"] {
      margin-right: 0.3em;
      vertical-align: middle;
    }
    .settings input:focus {
      border: 1.5px solid #388e3c;
      outline: none;
      background: #f5fff5;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 2rem;
      background: #f9fff9;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(56,142,60,0.06);
    }
    th, td {
      padding: 0.5em 0.3em;
      border-bottom: 1px solid #e0e0e0;
      text-align: center;
    }
    th {
      color: #388e3c;
      background: #e8f5e9;
      font-weight: 600;
      font-size: 1.05em;
    }
    input[type="text"], input[type="number"] {
      padding: 0.4em 0.7em;
      border: 1px solid #c8e6c9;
      border-radius: 7px;
      background: #fff;
      font-size: 1em;
      transition: border 0.2s;
    }
    input[type="text"]:focus, input[type="number"]:focus {
      border: 1.5px solid #388e3c;
      outline: none;
      background: #f5fff5;
    }
    .add-row {
      margin-top: 1em;
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 0.5em 1.5em;
      font-size: 1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
    }
    .add-row:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
      box-shadow: 0 4px 16px rgba(56,142,60,0.18);
      transform: scale(1.04);
    }
    button[type="submit"], .add-row, .settings button {
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 0.7em 2em;
      font-size: 1.1em;
      font-weight: 600;
      letter-spacing: 0.05em;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      cursor: pointer;
      transition: background 0.2s, box-shadow .2s, transform .2s;
      margin-top: 1.2em;
    }
    button[type="submit"]:hover, .add-row:hover, .settings button:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
      box-shadow: 0 4px 16px rgba(56,142,60,0.18);
      transform: scale(1.04);
    }
    td button[type="button"] {
      background: #fff;
      color: #388e3c;
      border: 1px solid #c8e6c9;
      border-radius: 7px;
      padding: 0.3em 1em;
      font-size: 0.98em;
      font-weight: 500;
      cursor: pointer;
      transition: background .2s, color .2s, border .2s;
    }
    td button[type="button"]:hover {
      background: #e8f5e9;
      color: #2e7031;
      border-color: #43a047;
    }
    .msg {
      margin-bottom: 1em;
      padding: 0.7em 1em;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
      background: #e8f5e9;
      color: #388e3c;
      border: 1px solid #c8e6c9;
    }
    @media (max-width: 700px) {
      .container { padding: 1.2rem 0; }
      .settings { flex-direction: column; gap: 0.7em; }
      table { font-size: 0.97em; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .container { background: #232d23; border: 1.5px solid #37474f; }
      table { background: #232d23; color: #eee; }
      th { background: #263238; color: #66bb6a; }
      .msg { background: #263238; color: #66bb6a; border: 1px solid #388e3c; }
      input, .settings input, .settings label, .settings input[type="number"], .settings input[type="text"] { background: #232d23; color: #eee; border: 1px solid #37474f; }
      input:focus, .settings input:focus, .settings input[type="number"]:focus, .settings input[type="text"]:focus { background: #263238; border: 1.5px solid #66bb6a; }
      td button[type="button"] { background: #232d23; color: #66bb6a; border: 1px solid #37474f; }
      td button[type="button"]:hover { background: #263238; color: #43a047; border-color: #43a047; }
    }
  </style>
  <script>
    function addRow() {
      const table = document.getElementById('words-table');
      const row = table.insertRow(-1);
      const fields = ['word','uk_phonetic','uk_audio','us_phonetic','us_audio','cn'];
      for (let i = 0; i < fields.length; ++i) {
        let cell = row.insertCell(-1);
        cell.innerHTML = '<input type="text" name="'+fields[i]+'[]" />';
      }
      let cell = row.insertCell(-1);
      cell.innerHTML = '<button type="button" onclick="delRow(this)">删除</button> <button type="button" onclick="fetchWordInfo(this)">一键获取</button>';
    }
    function delRow(btn) {
      const row = btn.parentNode.parentNode;
      row.parentNode.removeChild(row);
    }

    // 一键获取词汇信息
    async function fetchWordInfo(btn) {
      // 获取当前行
      const row = btn.parentNode.parentNode;
      const wordInput = row.querySelector('input[name="word[]"]');
      if (!wordInput || !wordInput.value.trim()) {
        alert('请先输入单词');
        return;
      }
      const word = wordInput.value.trim();
      btn.disabled = true;
      btn.textContent = '获取中...';
      try {
        // 先用 dictionaryapi.dev 获取英文释义和音标
        let uk_phonetic = '';
        let uk_audio = '';
        let us_phonetic = '';
        let us_audio = '';
        let cn = '';
        // 先用 dictionaryapi.dev 获取英文释义和音标
        let resp = await fetch('https://api.dictionaryapi.dev/api/v2/entries/en/' + encodeURIComponent(word));
        if (resp.ok) {
          const data = await resp.json();
          if (Array.isArray(data) && data.length > 0) {
            const entry = data[0];
            if (entry.phonetics && entry.phonetics.length > 0) {
              for (const ph of entry.phonetics) {
                if (ph.audio && ph.audio.includes('uk.mp3')) {
                  uk_audio = ph.audio;
                  if (ph.text) uk_phonetic = ph.text.replace(/[\[\]]/g, '');
                }
                if (ph.audio && ph.audio.includes('us.mp3')) {
                  us_audio = ph.audio;
                  if (ph.text) us_phonetic = ph.text.replace(/[\[\]]/g, '');
                }
                if (!uk_audio && ph.audio) uk_audio = ph.audio;
                if (!uk_phonetic && ph.text) uk_phonetic = ph.text.replace(/[\[\]]/g, '');
              }
            }
            // 英文释义
            if (entry.meanings && entry.meanings.length > 0) {
              const defs = [];
              for (const m of entry.meanings) {
                if (m.definitions && m.definitions.length > 0) {
                  defs.push(m.definitions[0].definition);
                }
              }
              cn = defs.join('; ');
            }
          }
        }
        // 再用百度翻译API获取中文翻译（无需key，免费接口，适合小批量）
        try {
          let baiduResp = await fetch('https://fanyi.baidu.com/sug', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'kw=' + encodeURIComponent(word)
          });
          if (baiduResp.ok) {
            let baiduData = await baiduResp.json();
            if (baiduData && baiduData.data && baiduData.data.length > 0) {
              // 取第一个翻译
              cn = baiduData.data[0].v;
            }
          }
        } catch (e) {
          // 百度接口失败，忽略
        }
        // 填充到输入框
        const inputs = row.querySelectorAll('input');
        for (let input of inputs) {
          if (input.name === 'uk_phonetic[]') input.value = uk_phonetic;
          if (input.name === 'us_phonetic[]') input.value = us_phonetic;
          if (input.name === 'uk_audio[]') input.value = uk_audio;
          if (input.name === 'us_audio[]') input.value = us_audio;
          if (input.name === 'cn[]') input.value = cn;
        }
        btn.textContent = '一键获取';
      } catch (e) {
        alert('获取失败，请手动填写。');
        btn.textContent = '一键获取';
      }
      btn.disabled = false;
    }
    // 给现有行添加一键获取按钮
    window.addEventListener('DOMContentLoaded', function() {
      const table = document.getElementById('words-table');
      for (let i = 1; i < table.rows.length; ++i) {
        const td = table.rows[i].cells[table.rows[i].cells.length - 1];
        if (!td.querySelector('button[onclick^="fetchWordInfo"]')) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = '一键获取';
          btn.onclick = function() { fetchWordInfo(btn); };
          td.appendChild(document.createTextNode(' '));
          td.appendChild(btn);
        }
      }
    });
  </script>
</head>
<body>
  <div class="container">
    <h2>编辑方案</h2>
    <a href="schemes.php">← 返回方案管理</a>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="settings">
        <label>方案名称 <input type="text" name="name" value="<?= htmlspecialchars($scheme['name']) ?>" required></label>
        <label>轮数 <input type="number" name="rounds" value="<?= htmlspecialchars($settings['rounds'] ?? 1) ?>" min="1" style="width:4em;"></label>
        <label>每词重复 <input type="number" name="repeat" value="<?= htmlspecialchars($settings['repeat'] ?? 1) ?>" min="1" style="width:4em;"></label>
        <label>等待时长(s) <input type="number" name="wait" value="<?= htmlspecialchars($settings['wait'] ?? 1) ?>" min="0" style="width:4em;"></label>
        <label>字号 <input type="number" name="font_size" value="<?= htmlspecialchars($settings['font_size'] ?? 36) ?>" min="12" max="120" style="width:4em;"></label>
        <label><input type="checkbox" name="show_cn" value="1" <?= !empty($settings['show_cn'])?'checked':'' ?>>显示中文</label>
        <label><input type="checkbox" name="shuffle" value="1" <?= !empty($settings['shuffle'])?'checked':'' ?>>乱序</label>
        <label><input type="checkbox" name="show_phonetic" value="1" <?= !empty($settings['show_phonetic'])?'checked':'' ?>>显示音标</label>
      </div>
      <table id="words-table">
        <tr>
          <th>单词</th>
          <th>英式音标</th>
          <th>英式发音</th>
          <th>美式音标</th>
          <th>美式发音</th>
          <th>中文翻译</th>
          <th>操作</th>
        </tr>
        <?php foreach ($words as $i => $w): ?>
        <tr>
          <td><input type="text" name="word[]" value="<?= htmlspecialchars($w['word']) ?>" required></td>
          <td><input type="text" name="uk_phonetic[]" value="<?= htmlspecialchars($w['uk_phonetic']) ?>"></td>
          <td><input type="text" name="uk_audio[]" value="<?= htmlspecialchars($w['uk_audio']) ?>"></td>
          <td><input type="text" name="us_phonetic[]" value="<?= htmlspecialchars($w['us_phonetic']) ?>"></td>
          <td><input type="text" name="us_audio[]" value="<?= htmlspecialchars($w['us_audio']) ?>"></td>
          <td><input type="text" name="cn[]" value="<?= htmlspecialchars($w['cn']) ?>"></td>
          <td>
            <button type="button" onclick="delRow(this)">删除</button>
            <button type="button" onclick="fetchWordInfo(this)">一键获取</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <button type="button" class="add-row" onclick="addRow()">添加词汇</button>
      <br><br>
      <button type="submit" name="save" style="background:#388e3c;color:#fff;border:none;padding:0.7em 2em;border-radius:8px;">保存</button>
    </form>
  </div>
</body>
</html>
      <br><br>
      <button type="submit" name="save" style="background:#388e3c;color:#fff;border:none;padding:0.7em 2em;border-radius:8px;">保存</button>
    </form>
  </div>
</body>
</html>
