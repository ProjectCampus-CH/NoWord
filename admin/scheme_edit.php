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

// 判断登录状态
$user = null;
if (isset($_SESSION['user_id'])) {
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>编辑方案 - NoWord</title>
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
    .top-app-bar .btn {
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
    .top-app-bar .btn:hover {
      background: var(--primary-light);
      color: var(--on-surface);
    }
    .container {
      width: 80vw;
      max-width: 1200px;
      min-width: 320px;
      margin-top: calc(var(--nav-height) + 16px);
      margin-left: auto;
      margin-right: auto;
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 4px 24px rgba(56,142,60,0.10);
      padding: 2.5rem 2rem 2rem 2rem;
      border: 1.5px solid #c8e6c9;
      overflow-x: auto;
    }
    h2 {
      color: var(--primary-dark);
      letter-spacing: 0.05em;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.2em;
    }
    a {
      color: var(--primary-dark);
      text-decoration: underline;
      font-weight: 500;
      font-size: 1.05em;
      transition: color .2s;
    }
    a:hover {
      color: var(--primary);
      text-decoration: none;
    }
    .settings {
      margin-bottom: 2em;
      display: flex;
      flex-direction: column;
      gap: 1em 2em;
      align-items: flex-start;
      background: var(--card);
      border-radius: 10px;
      padding: 1.2em 1em 0.7em 1em;
      border: 1px solid var(--primary-light);
    }
    .settings label {
      margin-right: 1.5em;
      color: var(--primary-dark);
      font-weight: 500;
      font-size: 1em;
      margin-bottom: 0.3em;
    }
    .settings input[type="text"], .settings input[type="number"] {
      padding: 0.4em 0.7em;
      border: 1px solid var(--primary-light);
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
      border: 1.5px solid var(--primary-dark);
      outline: none;
      background: #fff8e1;
    }
    .table-scroll {
      overflow-x: auto;
      width: 100%;
    }
    table {
      min-width: 900px;
      width: max-content;
      border-collapse: collapse;
      margin-bottom: 2rem;
      background: var(--card);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(255,152,0,0.06);
    }
    th, td {
      padding: 0.3em 0.2em;
      border-bottom: 1px solid #ffe0b2;
      text-align: center;
      font-size: 0.98em;
      white-space: nowrap;
    }
    th {
      color: var(--primary-dark);
      background: #fff3e0;
      font-weight: 600;
      font-size: 1.05em;
    }
    table input[type="text"], table input[type="number"] {
      padding: 0.4em 0.7em;
      border: 1px solid var(--primary-light);
      border-radius: 7px;
      background: #fff;
      font-size: 1em;
      margin: 0.1em 0;
      width: 7em;
      transition: border 0.2s;
    }
    table input[type="text"]:focus, table input[type="number"]:focus {
      border: 1.5px solid var(--primary-dark);
      outline: none;
      background: #fff8e1;
    }
    .word-actions {
      display: flex;
      gap: 0.3em;
      justify-content: center;
      align-items: center;
      flex-wrap: nowrap;
    }
    .word-actions button {
      min-width: 0;
      padding: 0.2em 0.7em;
      font-size: 0.97em;
      white-space: nowrap;
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 7px;
      transition: background .2s, color .2s;
    }
    .word-actions button:hover {
      background: var(--primary-light);
      color: var(--on-surface);
    }
    .get-methods {
      display: flex;
      gap: 1.2em;
      align-items: center;
      margin-bottom: 1.2em;
      margin-top: 0.5em;
    }
    .get-methods label {
      font-weight: 500;
      color: var(--primary-dark);
      margin-right: 0.7em;
    }
    .actions-row {
      display: flex;
      gap: 1.2em;
      align-items: center;
      margin-bottom: 1.5em;
      margin-top: 0.5em;
    }
    .actions-row button, .actions-row input[type="file"] {
      margin: 0;
    }
    .add-row, .actions-row button, .get-methods button {
      background: var(--primary-dark);
      color: var(--on-primary);
      border: none;
      border-radius: 10px;
      padding: 0.5em 1.5em;
      font-size: 1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.3em;
    }
    .add-row:hover, .actions-row button:hover, .get-methods button:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    .actions-row input[type="file"] {
      background: #fff;
      color: var(--primary-dark);
      border: 1.5px solid var(--primary-light);
      border-radius: 7px;
      padding: 0.38em 1em;
      font-size: 1em;
      font-weight: 500;
      cursor: pointer;
      transition: background .2s, color .2s, border .2s, box-shadow .2s;
      margin: 0 0.5em 0 0.5em;
      outline: none;
      box-shadow: 0 1px 4px rgba(255,152,0,0.07);
      min-width: 180px;
      max-width: 320px;
    }
    .actions-row input[type="file"]:hover, .actions-row input[type="file"]:focus {
      background: #fff3e0;
      color: var(--primary);
      border-color: var(--primary);
      box-shadow: 0 2px 8px var(--primary-light);
    }
    .file-label {
      display: inline-flex;
      align-items: center;
      gap: 0.5em;
      background: var(--primary-dark);
      color: var(--on-primary);
      border-radius: 10px;
      padding: 0.5em 1.5em;
      font-size: 1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(255,152,0,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
      border: none;
      margin: 0 0.5em 0 0.5em;
      user-select: none;
    }
    .file-label:hover {
      background: var(--primary-light);
      color: var(--on-surface);
      box-shadow: 0 4px 16px rgba(255,152,0,0.18);
      transform: scale(1.04);
    }
    .file-label input[type="file"] {
      display: none;
    }
    .file-filename {
      font-size: 0.98em;
      color: var(--primary-dark);
      margin-left: 0.5em;
      max-width: 180px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      vertical-align: middle;
      display: inline-block;
    }
    @media (prefers-color-scheme: dark) {
      .file-label {
        background: #37474f;
        color: #ffb300;
      }
      .file-label:hover {
        background: #ffb300;
        color: #232323;
      }
      .file-filename {
        color: #ffb300;
      }
    }
    .msg {
      margin-bottom: 1em;
      padding: 0.7em 1em;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
      background: #fff3e0;
      color: var(--primary-dark);
      border: 1px solid var(--primary-light);
    }
    @media (max-width: 900px) {
      .container { width: 99vw; padding: 1.2rem 0.2rem; }
      table { min-width: 700px; }
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .container { background: #232d23; border: 1.5px solid #37474f; }
      table { background: #232d23; color: #eee; }
      th { background: #263238; color: #ffb300; }
      .msg { background: #263238; color: #ffb300; border: 1px solid #c68400; }
      input, .settings input, .settings label, .settings input[type="number"], .settings input[type="text"] { background: #232d23; color: #eee; border: 1px solid #37474f; }
      input:focus, .settings input:focus, .settings input[type="number"]:focus, .settings input[type="text"]:focus { background: #263238; border: 1.5px solid #ffb300; }
      td button[type="button"], .word-actions button { background: #37474f; color: #ffb300; border: none; }
      td button[type="button"]:hover, .word-actions button:hover { background: #ffb300; color: #232323; }
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
      cell.className = 'word-actions';
      cell.innerHTML = '' +
        '<button type="button" class="btn" onclick="delRow(this)">删除</button> ' +
        '<button type="button" class="btn" onclick="fetchWordInfo(this)">一键获取</button>';
    }
    function delRow(btn) {
      const row = btn.parentNode.parentNode;
      row.parentNode.removeChild(row);
    }

    // 一键获取词汇信息
    async function fetchWordInfo(btn, method) {
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
        // 翻译源切换
        if (!method) {
          const checked = document.querySelector('input[name="get_method"]:checked');
          method = checked ? checked.value : 'auto';
        }
        let methodUsed = 1;
        if (method === 'auto' || method === 'appworlds') {
          try {
            let appworldsResp = await fetch('https://translate.appworlds.cn?text=' + encodeURIComponent(word) + '&from=en&to=zh-CN');
            if (appworldsResp.ok) {
              let appworldsData = await appworldsResp.json();
              if (appworldsData && appworldsData.code === 200 && appworldsData.data) {
                cn = appworldsData.data;
                methodUsed = 1;
              }
            }
          } catch (e) {}
        }
        if ((method === 'baidu' || (!cn || cn.trim() === '') && method !== 'google') && methodUsed !== 2) {
          try {
            let baiduResp = await fetch('https://fanyi.baidu.com/sug', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'kw=' + encodeURIComponent(word)
            });
            if (baiduResp.ok) {
              let baiduData = await baiduResp.json();
              if (baiduData && baiduData.data && baiduData.data.length > 0) {
                cn = baiduData.data[0].v;
                methodUsed = 2;
              }
            }
          } catch (e) {}
        }
        if ((method === 'google' || (!cn || cn.trim() === '')) && methodUsed !== 3) {
          try {
            let googleResp = await fetch('https://translate.google.com/?sl=en&tl=zh-CN&text=' + encodeURIComponent(word) + '&op=translate');
            if (googleResp.ok) {
              let html = await googleResp.text();
              let match = html.match(/<span[^>]+jsname="W297wb"[^>]*>(.*?)<\/span>/);
              if (match && match[1]) {
                cn = match[1].replace(/<[^>]+>/g, '').trim();
                methodUsed = 3;
              }
            }
          } catch (e) {}
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

    // 批量一键获取
    async function fetchAllWordInfo() {
      const table = document.getElementById('words-table');
      const trs = Array.from(table.rows).slice(1); // 跳过表头
      let count = 0;
      const checked = document.querySelector('input[name="get_method"]:checked');
      const method = checked ? checked.value : 'auto';
      for (const tr of trs) {
        const btn = tr.querySelector('button[onclick^="fetchWordInfo"]');
        if (btn) {
          await fetchWordInfo(btn, method);
          count++;
          if (count % 1 === 0) await new Promise(r => setTimeout(r, 100));
        }
      }
      alert('批量填充完成');
    }

    // 给现有行添加一键获取按钮
    window.addEventListener('DOMContentLoaded', function() {
      const table = document.getElementById('words-table');
      for (let i = 1; i < table.rows.length; ++i) {
        const td = table.rows[i].cells[table.rows[i].cells.length - 1];
        if (!td.querySelector('button[onclick^="fetchWordInfo"]')) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn';
          btn.textContent = '一键获取';
          btn.onclick = function() { fetchWordInfo(btn); };
          td.appendChild(document.createTextNode(' '));
          td.appendChild(btn);
        }
      }
    });

    // 导入功能
    function importWords() {
      const fileInput = document.getElementById('import-file');
      if (!fileInput.files.length) { alert('请选择文件'); return; }
      const file = fileInput.files[0];
      const ext = file.name.split('.').pop().toLowerCase();
      const reader = new FileReader();
      reader.onload = function(e) {
        let arr = [];
        try {
          if (ext === 'json') {
            arr = JSON.parse(e.target.result);
          } else if (ext === 'csv') {
            arr = csvToArray(e.target.result);
          } else {
            alert('仅支持json/csv');
            return;
          }
        } catch (err) {
          alert('文件解析失败');
          return;
        }
        if (!Array.isArray(arr)) { alert('文件格式不正确'); return; }
        // 清空表格
        const table = document.getElementById('words-table');
        while (table.rows.length > 1) table.deleteRow(1);
        // 填充数据
        for (const w of arr) {
          const row = table.insertRow(-1);
          const fields = ['word','uk_phonetic','uk_audio','us_phonetic','us_audio','cn'];
          for (let i = 0; i < fields.length; ++i) {
            let cell = row.insertCell(-1);
            cell.innerHTML = '<input type="text" name="'+fields[i]+'[]" value="'+(w[fields[i]]||'')+'" />';
          }
          let cell = row.insertCell(-1);
          cell.className = 'word-actions';
          cell.innerHTML = '<button type="button" class="btn" onclick="delRow(this)">删除</button> <button type="button" class="btn" onclick="fetchWordInfo(this)">一键获取</button>';
        }
        alert('导入成功');
      };
      if (ext === 'json' || ext === 'csv') reader.readAsText(file);
      else alert('仅支持json/csv');
    }
    // CSV转数组
    function csvToArray(str) {
      const lines = str.split(/\r?\n/).filter(l=>l.trim());
      const fields = ['word','uk_phonetic','uk_audio','us_phonetic','us_audio','cn'];
      let arr = [];
      for (let i=0; i<lines.length; ++i) {
        const vals = lines[i].split(',');
        let obj = {};
        for (let j=0; j<fields.length && j<vals.length; ++j) obj[fields[j]] = vals[j];
        arr.push(obj);
      }
      return arr;
    }
    // 导出功能
    function exportWords() {
      const table = document.getElementById('words-table');
      let arr = [];
      for (let i=1; i<table.rows.length; ++i) {
        const tds = table.rows[i].querySelectorAll('input');
        let obj = {};
        ['word','uk_phonetic','uk_audio','us_phonetic','us_audio','cn'].forEach((k,idx)=>{
          obj[k] = tds[idx] ? tds[idx].value : '';
        });
        arr.push(obj);
      }
      const blob = new Blob([JSON.stringify(arr, null, 2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'words.json';
      document.body.appendChild(a);
      a.click();
      setTimeout(()=>{ URL.revokeObjectURL(url); a.remove(); }, 500);
    }

    // 显示选中文件名
    function showFileName(input) {
      const span = document.getElementById('file-filename');
      if (input.files && input.files.length > 0) {
        span.textContent = input.files[0].name;
      } else {
        span.textContent = '';
      }
    }
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
      <a href="/" class="btn"><span class="material-icons">home</span>回到首页</a>
    </div>
    <div class="right">
      <?php if (isset($user['id'])): ?>
        <span style="display:flex;align-items:center;gap:0.2em;"><span class="material-icons" style="font-size:1.1em;">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <?php else: ?>
        <a href="/login.php" class="btn"><span class="material-icons">login</span>登录</a>
      <?php endif; ?>
    </div>
  </div>
  <!-- 页面内容 -->
  <div class="container">
    <h2>编辑方案</h2>
    <a href="schemes.php">← 返回方案管理</a>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="settings">
        <div class="settings-row">
          <label>方案名称 <input type="text" name="name" value="<?= htmlspecialchars($scheme['name']) ?>" required></label>
          <label>字号 <input type="number" name="font_size" value="<?= htmlspecialchars($settings['font_size'] ?? 36) ?>" min="12" max="120" style="width:4em;"></label>
        </div>
        <div class="settings-row">
          <label>轮数 <input type="number" name="rounds" value="<?= htmlspecialchars($settings['rounds'] ?? 1) ?>" min="1" style="width:4em;"></label>
          <label>每词重复 <input type="number" name="repeat" value="<?= htmlspecialchars($settings['repeat'] ?? 1) ?>" min="1" style="width:4em;"></label>
          <label>等待时长(s) <input type="number" name="wait" value="<?= htmlspecialchars($settings['wait'] ?? 1) ?>" min="0" style="width:4em;"></label>
        </div>
        <div class="settings-row">
          <label><input type="checkbox" name="show_cn" value="1" <?= !empty($settings['show_cn'])?'checked':'' ?>>显示中文</label>
          <label><input type="checkbox" name="shuffle" value="1" <?= !empty($settings['shuffle'])?'checked':'' ?>>乱序</label>
          <label><input type="checkbox" name="show_phonetic" value="1" <?= !empty($settings['show_phonetic'])?'checked':'' ?>>显示音标</label>
        </div>
      </div>
      <div class="get-methods">
        <label>获取方式：</label>
        <label><input type="radio" name="get_method" value="auto" checked>自动（推荐）</label>
        <label><input type="radio" name="get_method" value="appworlds">AppWorlds</label>
        <label><input type="radio" name="get_method" value="baidu">百度翻译</label>
        <label><input type="radio" name="get_method" value="google">Google翻译</label>
        <button type="button" onclick="fetchAllWordInfo()">批量一键填充</button>
      </div>
      <div class="table-scroll">
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
          <td class="word-actions">
            <button type="button" class="btn" onclick="delRow(this)">删除</button>
            <button type="button" class="btn" onclick="fetchWordInfo(this)">一键获取</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <div class="actions-row">
        <button type="button" class="add-row" onclick="addRow()">添加词汇</button>
        <label class="file-label"><span class="material-icons" style="font-size:1.1em;">upload_file</span>选择文件
          <input type="file" id="import-file" name="import_file" accept=".json,.csv" onchange="showFileName(this)">
        </label>
        <span class="file-filename" id="file-filename"></span>
        <button type="button" onclick="importWords()">导入</button>
        <button type="button" onclick="exportWords()">导出</button>
        <button type="submit" name="save">保存</button>
      </div>
    </form>
  </div>
</body>
</html>
