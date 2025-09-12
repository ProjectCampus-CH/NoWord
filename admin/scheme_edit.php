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
    function addRow() {
      const table = document.getElementById('words-table');
      const row = table.insertRow(-1);
      const fields = ['word','uk_phonetic','uk_audio','us_phonetic','us_audio','cn'];
      for (let i = 0; i < fields.length; ++i) {
        let cell = row.insertCell(-1);
        // 应用 tailwind 样式
        cell.innerHTML = '<input type="text" name="'+fields[i]+'[]" class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-28" />';
      }
      let cell = row.insertCell(-1);
      cell.className = 'flex gap-2 items-center';
      cell.innerHTML =
        '<button type="button" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150" onclick="delRow(this)">删除</button>' +
        '<button type="button" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150" onclick="fetchWordInfo(this)">一键获取</button>';
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
<body class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-900 dark:to-blue-950 text-gray-900 dark:text-gray-100 min-h-screen font-sans">
  <!-- 顶部导航栏 -->
  <div class="fixed top-0 left-0 right-0 h-16 bg-primary text-white flex items-center justify-between z-50 shadow-lg px-8 backdrop-blur-md">
    <div class="flex items-center gap-3 font-extrabold text-2xl tracking-wide select-none">
      <span class="material-icons text-2xl">auto_stories</span>
      NoWord - 没词
    </div>
    <div class="flex-1 flex justify-center items-center min-w-0">
      <a href="/" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">home</span>回到首页</a>
    </div>
    <div class="flex items-center gap-6 min-w-[120px] justify-end">
      <?php if (isset($user['id'])): ?>
        <span class="flex items-center gap-2 text-base"><span class="material-icons text-lg">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <?php else: ?>
        <a href="/login.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">login</span>登录</a>
      <?php endif; ?>
    </div>
  </div>
  <!-- 页面内容 -->
  <div class="w-[80vw] max-w-[1200px] min-w-[320px] mx-auto mt-24 mb-8 bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-10 border border-primary-light backdrop-blur-md">
    <h2 class="text-2xl font-bold text-primary-dark dark:text-primary-light text-center mb-8">编辑方案</h2>
    <a href="schemes.php" class="inline-block mb-4 text-primary-dark hover:text-primary-light underline">← 返回方案管理</a>
    <?php if ($msg): ?><div class="mb-4 p-3 rounded-lg text-base text-center bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border border-primary-light dark:border-primary-dark"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="flex flex-col gap-6 mb-8 bg-primary-pale/60 dark:bg-blue-900/60 rounded-xl p-6 border border-primary-light">
        <div class="flex flex-wrap gap-8 items-center">
          <label class="font-medium text-primary-dark dark:text-primary-light">方案名称 <input type="text" name="name" value="<?= htmlspecialchars($scheme['name']) ?>" required class="ml-2 px-3 py-2 border border-primary-light rounded-lg bg-white focus:border-primary-dark focus:outline-none"></label>
          <label class="font-medium text-primary-dark dark:text-primary-light">字号 <input type="number" name="font_size" value="<?= htmlspecialchars($settings['font_size'] ?? 36) ?>" min="12" max="120" class="ml-2 px-3 py-2 border border-primary-light rounded-lg bg-white focus:border-primary-dark focus:outline-none w-20"></label>
        </div>
        <div class="flex flex-wrap gap-8 items-center">
          <label class="font-medium text-primary-dark dark:text-primary-light">轮数 <input type="number" name="rounds" value="<?= htmlspecialchars($settings['rounds'] ?? 1) ?>" min="1" class="ml-2 px-3 py-2 border border-primary-light rounded-lg bg-white focus:border-primary-dark focus:outline-none w-20"></label>
          <label class="font-medium text-primary-dark dark:text-primary-light">每词重复 <input type="number" name="repeat" value="<?= htmlspecialchars($settings['repeat'] ?? 1) ?>" min="1" class="ml-2 px-3 py-2 border border-primary-light rounded-lg bg-white focus:border-primary-dark focus:outline-none w-20"></label>
          <label class="font-medium text-primary-dark dark:text-primary-light">等待时长(s) <input type="number" name="wait" value="<?= htmlspecialchars($settings['wait'] ?? 1) ?>" min="0" class="ml-2 px-3 py-2 border border-primary-light rounded-lg bg-white focus:border-primary-dark focus:outline-none w-20"></label>
        </div>
        <div class="flex flex-wrap gap-8 items-center">
          <label class="font-medium text-primary-dark dark:text-primary-light"><input type="checkbox" name="show_cn" value="1" <?= !empty($settings['show_cn'])?'checked':'' ?> class="mr-2">显示中文</label>
          <label class="font-medium text-primary-dark dark:text-primary-light"><input type="checkbox" name="shuffle" value="1" <?= !empty($settings['shuffle'])?'checked':'' ?> class="mr-2">乱序</label>
          <label class="font-medium text-primary-dark dark:text-primary-light"><input type="checkbox" name="show_phonetic" value="1" <?= !empty($settings['show_phonetic'])?'checked':'' ?> class="mr-2">显示音标</label>
        </div>
      </div>
      <div class="flex gap-6 items-center mb-6">
        <label class="font-medium text-primary-dark dark:text-primary-light">获取方式：</label>
        <label class="font-medium"><input type="radio" name="get_method" value="auto" checked class="mr-1">自动（推荐）</label>
        <label class="font-medium"><input type="radio" name="get_method" value="appworlds" class="mr-1">AppWorlds</label>
        <label class="font-medium"><input type="radio" name="get_method" value="baidu" class="mr-1">百度翻译</label>
        <label class="font-medium"><input type="radio" name="get_method" value="google" class="mr-1">Google翻译</label>
        <button type="button" onclick="fetchAllWordInfo()" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150">批量一键填充</button>
      </div>
      <div class="overflow-x-auto mb-6">
        <table id="words-table" class="min-w-[900px] w-full border-collapse rounded-xl shadow bg-white dark:bg-blue-950">
          <tr>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">单词</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">英式音标</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">英式发音</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">美式音标</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">美式发音</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">中文翻译</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold">操作</th>
          </tr>
          <?php foreach ($words as $i => $w): ?>
          <tr class="<?= $i % 2 === 0 ? 'bg-white dark:bg-blue-950' : 'bg-primary-pale/60 dark:bg-blue-900/60' ?> hover:bg-primary-pale/70">
            <td><input type="text" name="word[]" value="<?= htmlspecialchars($w['word']) ?>" required class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-28"></td>
            <td><input type="text" name="uk_phonetic[]" value="<?= htmlspecialchars($w['uk_phonetic']) ?>" class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-28"></td>
            <td><input type="text" name="uk_audio[]" value="<?= htmlspecialchars($w['uk_audio']) ?>" class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-28"></td>
            <td><input type="text" name="us_phonetic[]" value="<?= htmlspecialchars($w['us_phonetic']) ?>" class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-28"></td>
            <td><input type="text" name="us_audio[]" value="<?= htmlspecialchars($w['us_audio']) ?>" class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-28"></td>
            <td><input type="text" name="cn[]" value="<?= htmlspecialchars($w['cn']) ?>" class="px-2 py-1 border border-primary-light rounded bg-white focus:border-primary-dark focus:outline-none w-32"></td>
            <td class="flex gap-2 items-center">
              <button type="button" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150" onclick="delRow(this)">删除</button>
              <button type="button" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150" onclick="fetchWordInfo(this)">一键获取</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="flex flex-wrap gap-4 items-center mb-6">
        <button type="button" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150" onclick="addRow()">添加词汇</button>
        <label class="flex items-center gap-2 bg-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow cursor-pointer transition-all duration-150 hover:bg-primary-light hover:text-primary-dark">
          <span class="material-icons text-base">upload_file</span>选择文件
          <input type="file" id="import-file" name="import_file" accept=".json,.csv" onchange="showFileName(this)" class="hidden">
        </label>
        <span class="text-primary-dark dark:text-primary-light" id="file-filename"></span>
        <button type="button" onclick="importWords()" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150">导入</button>
        <button type="button" onclick="exportWords()" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150">导出</button>
        <button type="submit" name="save" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150">保存</button>
      </div>
    </form>
  </div>
</body>
</html>
