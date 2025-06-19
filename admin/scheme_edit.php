<?php
session_start();
$config = require __DIR__ . '/../config.php';
// ...数据库连接，与前述一致...

if (empty($_SESSION['user_id'])) {
  header('Location: /login.php');
  exit;
}
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
    body { background: #f5fff5; color: #222; }
    .container { max-width: 900px; margin: 2rem auto; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
    th, td { padding: 0.5em 0.3em; border-bottom: 1px solid #e0e0e0; }
    th { color: #388e3c; }
    .actions button { margin-right: 0.5em; }
    .add-row { margin-top: 1em; }
    .settings { margin-bottom: 2em; }
    .settings label { margin-right: 1.5em; }
    @media (prefers-color-scheme: dark) {
      body { background: #1a1f1a; color: #eee; }
      table { color: #eee; }
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
      cell.innerHTML = '<button type="button" onclick="delRow(this)">删除</button>';
    }
    function delRow(btn) {
      const row = btn.parentNode.parentNode;
      row.parentNode.removeChild(row);
    }
  </script>
</head>
<body>
  <div class="container">
    <h2 style="color:#388e3c;">编辑方案</h2>
    <a href="schemes.php" style="color:#388e3c;">← 返回方案管理</a>
    <?php if ($msg): ?><div style="color:#388e3c;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
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
          <th>中文番羽译</th>
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
          <td><button type="button" onclick="delRow(this)">删除</button></td>
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
