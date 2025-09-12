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
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  session_destroy();
  header('Location: /login.php');
  exit;
}

// 处理表单操作（新增、删除、重命名、复制、编辑）
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
      $stmt = $pdo->prepare("INSERT INTO schemes (name, data, owner_id) VALUES (?, ?, ?)");
      $stmt->execute([$name, json_encode(['words'=>[], 'settings'=>[]]), $user['id']]);
      $newId = $pdo->lastInsertId();
      header("Location: scheme_edit.php?id=$newId");
      exit;
    }
  } elseif ($action === 'del') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      $stmt = $pdo->prepare("DELETE FROM schemes WHERE id=?");
      $stmt->execute([$id]);
      $msg = '已删除。';
    }
  } elseif ($action === 'rename') {
    $id = intval($_POST['id'] ?? 0);
    $newname = trim($_POST['newname'] ?? '');
    if ($id && $newname) {
      $stmt = $pdo->prepare("UPDATE schemes SET name=? WHERE id=?");
      $stmt->execute([$newname, $id]);
      $msg = '已重命名。';
    }
  } elseif ($action === 'copy') {
    $id = intval($_POST['id'] ?? 0);
    $newname = trim($_POST['newname'] ?? '');
    if ($id && $newname) {
      $stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
      $stmt->execute([$id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $stmt2 = $pdo->prepare("INSERT INTO schemes (name, data, owner_id) VALUES (?, ?, ?)");
        $stmt2->execute([$newname, $row['data'], $user['id']]);
        $newId = $pdo->lastInsertId();
        header("Location: scheme_edit.php?id=$newId");
        exit;
      }
    }
  } elseif ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
      header("Location: scheme_edit.php?id=$id");
      exit;
    }
  }
}

// 获取所有方案
$schemes = $pdo->query("SELECT * FROM schemes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>方案管理 - NoWord</title>
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
  <div class="fixed top-0 left-0 right-0 h-16 bg-primary text-white flex items-center justify-between z-50 shadow-lg px-8 backdrop-blur-md">
    <div class="flex items-center gap-3 font-extrabold text-2xl tracking-wide select-none">
      <span class="material-icons text-2xl">library_books</span>
      方案管理 - NoWord
    </div>
    <div class="flex-1 flex justify-center items-center min-w-0">
      <a href="/admin/index.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">admin_panel_settings</span>后台首页</a>
      <span class="inline-block w-5"></span>
      <a href="/" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">home</span>回到首页</a>
    </div>
    <div class="flex items-center gap-6 min-w-[120px] justify-end">
      <span class="flex items-center gap-2 text-base"><span class="material-icons text-lg">person</span>您好，<?= htmlspecialchars($user['username']) ?></span>
      <a href="/logout.php" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 flex items-center gap-2 font-semibold shadow transition-all duration-150"><span class="material-icons">logout</span>退出登录</a>
    </div>
  </div>
  <div class="w-[80vw] max-w-[1200px] min-w-[320px] mx-auto mt-24 mb-8 bg-white/90 dark:bg-blue-950/80 rounded-3xl shadow-xl p-10 border border-primary-light backdrop-blur-md">
    <h2 class="text-2xl font-bold text-primary-dark dark:text-primary-light text-center mb-8">方案管理</h2>
    <a href="index.php" class="inline-block mb-4 text-primary-dark hover:text-primary-light underline">← 返回后台</a>
    <?php if ($msg): ?><div class="mb-4 p-3 rounded-lg text-base text-center bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border border-primary-light dark:border-primary-dark"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form class="flex gap-3 mb-8 items-center flex-wrap" method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="新方案名称" required class="px-3 py-2 border border-primary-light rounded-lg bg-primary-pale focus:border-primary-dark focus:bg-white focus:outline-none">
      <button type="submit" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-xl px-5 py-2 font-semibold shadow transition-all duration-150">新增方案</button>
    </form>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse rounded-xl shadow bg-white dark:bg-blue-950">
        <thead>
          <tr>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold w-[40%]">方案名称</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold w-[20%]">创建时间</th>
            <th class="py-2 px-3 text-primary-dark dark:text-primary-light bg-primary-pale font-semibold w-[40%]">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($schemes as $s): ?>
          <tr class="hover:bg-primary-pale/70">
            <td class="py-2 px-3"><?= htmlspecialchars($s['name']) ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($s['created_at']) ?></td>
            <td class="py-2 px-3 flex flex-wrap gap-2 items-center">
              <form method="post" class="inline">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150 flex items-center gap-1"><span class="material-icons text-base">edit</span>编辑</button>
              </form>
              <button type="button" onclick="renameScheme(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150 flex items-center gap-1"><span class="material-icons text-base">drive_file_rename_outline</span>重命名</button>
              <button type="button" onclick="copyScheme(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150 flex items-center gap-1"><span class="material-icons text-base">content_copy</span>复制</button>
              <form method="post" class="inline">
                <input type="hidden" name="action" value="del">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" onclick="return confirm('确定删除？')" class="bg-primary-dark hover:bg-primary-light hover:text-primary-dark text-white rounded-lg px-3 py-1 text-sm font-semibold shadow transition-all duration-150 flex items-center gap-1"><span class="material-icons text-base">delete</span>删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script>
    function renameScheme(id, oldName) {
      const newName = prompt('请输入新方案名：', oldName);
      if (newName && newName.trim() && newName !== oldName) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = `<input name=\"action\" value=\"rename\"><input name=\"id\" value=\"${id}\"><input name=\"newname\" value=\"${newName}\">`;
        document.body.appendChild(form);
        form.submit();
      }
    }
    function copyScheme(id, oldName) {
      const newName = prompt('请输入新方案名（复制）：', oldName + '_副本');
      if (newName && newName.trim()) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = `<input name=\"action\" value=\"copy\"><input name=\"id\" value=\"${id}\"><input name=\"newname\" value=\"${newName}\">`;
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</body>
</html>
