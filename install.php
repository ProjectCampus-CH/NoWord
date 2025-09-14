<?php
session_start();
$error = '';
$success = '';

function render_form($db_type = '', $mysql = [], $sqlite = '', $admin = []) {
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>ComboWord 初始化</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Tailwind CSS via CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#f0fdf4',
              100: '#dcfce7',
              200: '#bbf7d0',
              300: '#86efac',
              400: '#4ade80',
              500: '#22c55e',
              600: '#16a34a',
              700: '#15803d',
              800: '#166534',
              900: '#14532d',
            },
            dark: {
              50: '#f8fafc',
              100: '#f1f5f9',
              200: '#e2e8f0',
              300: '#cbd5e1',
              400: '#94a3b8',
              500: '#64748b',
              600: '#475569',
              700: '#334155',
              800: '#1e293b',
              900: '#0f172a',
            },
          },
          animation: {
            'gradient-x': 'gradient-x 8s ease infinite',
            'gradient-y': 'gradient-y 8s ease infinite',
            'gradient-xy': 'gradient-xy 8s ease infinite',
            'float': 'float 6s ease-in-out infinite',
          },
          keyframes: {
            'gradient-x': {
              '0%, 100%': {
                'background-size': '200% 200%',
                'background-position': 'left center',
              },
              '50%': {
                'background-size': '200% 200%',
                'background-position': 'right center',
              },
            },
            'gradient-y': {
              '0%, 100%': {
                'background-size': '400% 400%',
                'background-position': 'center top',
              },
              '50%': {
                'background-size': '400% 400%',
                'background-position': 'center bottom',
              },
            },
            'gradient-xy': {
              '0%, 100%': {
                'background-size': '400% 400%',
                'background-position': 'left center',
              },
              '50%': {
                'background-size': '400% 400%',
                'background-position': 'right center',
              },
            },
            'float': {
              '0%, 100%': {
                transform: 'translateY(0)',
              },
              '50%': {
                transform: 'translateY(-10px)',
              },
            },
          },
        },
      },
    };
  </script>
  <!-- Animate.css for additional animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <style type="text/tailwindcss">
    @layer components {
      .input-tech {
        @apply w-full px-4 py-3 rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-300 ease-in-out;
      }
      .select-tech {
        @apply appearance-none w-full px-4 py-3 rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-300 ease-in-out;
      }
      .btn-gradient {
        @apply bg-gradient-to-r from-primary-500 via-primary-600 to-primary-700 text-white font-bold py-3 px-6 rounded-lg capitalize shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all duration-300 ease-in-out animate-gradient-x bg-[length:200%_200%];
      }
      .btn-gradient:active {
        @apply scale-95;
      }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-800 min-h-screen flex items-center justify-center p-4">
  <form 
    class="bg-white dark:bg-gray-800 shadow-2xl rounded-2xl p-8 max-w-md w-full mx-auto border border-gray-200 dark:border-gray-700 animate__animated animate__fadeIn"
    method="post" 
    autocomplete="off"
  >
    <h2 class="text-3xl font-bold text-center mb-8 text-primary-600 dark:text-primary-400 animate-[float_6s_ease-in-out_infinite]">
      ComboWord 初始化
    </h2>
    
    <div class="mb-6">
      <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">数据库类型</label>
      <select 
        name="db_type" 
        id="db_type" 
        onchange="onDbTypeChange()" 
        required
        class="select-tech"
      >
        <option value="">请选择</option>
        <option value="mysql" <?= $db_type==='mysql'?'selected':'' ?>>MySQL</option>
        <option value="sqlite" <?= $db_type==='sqlite'?'selected':'' ?>>SQLite</option>
      </select>
    </div>
    
    <div 
      id="mysql_fields" 
      class="space-y-4 mb-6 <?= $db_type==='mysql'?'block':'hidden' ?> transition-all duration-500 ease-in-out"
    >
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">MySQL 主机</label>
        <input 
          type="text" 
          name="mysql_host" 
          value="<?= htmlspecialchars($mysql['host']??'localhost') ?>" 
          <?= $db_type==='mysql'?'required':'' ?>
          class="input-tech"
        >
      </div>
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">MySQL 数据库名</label>
        <input 
          type="text" 
          name="mysql_db" 
          value="<?= htmlspecialchars($mysql['db']??'') ?>" 
          <?= $db_type==='mysql'?'required':'' ?>
          class="input-tech"
        >
      </div>
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">MySQL 用户名</label>
        <input 
          type="text" 
          name="mysql_user" 
          value="<?= htmlspecialchars($mysql['user']??'') ?>" 
          <?= $db_type==='mysql'?'required':'' ?>
          class="input-tech"
        >
      </div>
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">MySQL 密码</label>
        <input 
          type="password" 
          name="mysql_pass" 
          value="<?= htmlspecialchars($mysql['pass']??'') ?>"
          class="input-tech"
        >
      </div>
    </div>
    
    <div 
      id="sqlite_fields" 
      class="space-y-4 mb-6 <?= $db_type==='sqlite'?'block':'hidden' ?> transition-all duration-500 ease-in-out"
    >
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">SQLite 文件路径</label>
        <input 
          type="text" 
          name="sqlite_path" 
          value="<?= htmlspecialchars($sqlite) ?>" 
          <?= $db_type==='sqlite'?'required':'' ?>
          class="input-tech"
        >
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">
          建议 SQLite 路径使用绝对路径或 <span class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">./data/xxx.db</span>，如 <span class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">./data/ComboWord.db</span>，确保 PHP 有写入权限。
        </p>
      </div>
    </div>
    
    <div class="relative py-6">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
      </div>
      <div class="relative flex justify-center">
        <span class="px-4 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-sm uppercase tracking-wider">管理员设置</span>
      </div>
    </div>
    
    <div class="space-y-4 mb-6">
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">站长用户名</label>
        <input 
          type="text" 
          name="admin_user" 
          value="<?= htmlspecialchars($admin['user']??'') ?>" 
          required
          class="input-tech"
        >
      </div>
      <div>
        <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2 text-sm uppercase tracking-wider">站长密码</label>
        <input 
          type="password" 
          name="admin_pass" 
          required
          class="input-tech"
        >
      </div>
    </div>
    
    <button type="submit" class="btn-gradient w-full">
      初始化
    </button>
    
    <?php if (!empty($GLOBALS['error'])): ?>
      <div class="mt-6 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 text-red-700 dark:text-red-300 rounded animate__animated animate__headShake">
        <?= htmlspecialchars($GLOBALS['error']) ?>
      </div>
    <?php elseif (!empty($GLOBALS['success'])): ?>
      <div class="mt-6 p-4 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 text-green-700 dark:text-green-300 rounded animate__animated animate__rubberBand">
        <?= htmlspecialchars($GLOBALS['success']) ?>
      </div>
    <?php endif; ?>
  </form>
  
  <script>
    function onDbTypeChange() {
      const type = document.getElementById('db_type').value;
      const mysqlFields = document.getElementById('mysql_fields');
      const sqliteFields = document.getElementById('sqlite_fields');
      
      // Animate fields
      if (type === 'mysql') {
        mysqlFields.classList.remove('hidden');
        mysqlFields.classList.add('block');
        sqliteFields.classList.remove('block');
        sqliteFields.classList.add('hidden');
      } else if (type === 'sqlite') {
        sqliteFields.classList.remove('hidden');
        sqliteFields.classList.add('block');
        mysqlFields.classList.remove('block');
        mysqlFields.classList.add('hidden');
      }
      
      // Set required fields
      document.querySelectorAll('#mysql_fields input').forEach(i => i.required = (type === 'mysql'));
      document.querySelectorAll('#sqlite_fields input').forEach(i => i.required = (type === 'sqlite'));
    }
    
    window.onload = onDbTypeChange;
    
    // Add focus effect labels
    document.querySelectorAll('.input-tech, .select-tech').forEach(input => {
      const label = input.previousElementSibling;
      if (label && label.tagName === 'LABEL') {
        input.addEventListener('focus', () => {
          label.classList.add('text-primary-500', 'dark:text-primary-400');
        });
        input.addEventListener('blur', () => {
          label.classList.remove('text-primary-500', 'dark:text-primary-400');
        });
      }
    });
  </script>
</body>
</html>
<?php
}

// 以下是原始PHP逻辑保持不变...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db_type = $_POST['db_type'] ?? '';
  $admin_user = trim($_POST['admin_user'] ?? '');
  $admin_pass = $_POST['admin_pass'] ?? '';
  $mysql = [
    'host' => $_POST['mysql_host'] ?? 'localhost',
    'db'   => $_POST['mysql_db'] ?? '',
    'user' => $_POST['mysql_user'] ?? '',
    'pass' => $_POST['mysql_pass'] ?? '',
  ];
  $sqlite = $_POST['sqlite_path'] ?? '';
  if (!$db_type || !$admin_user || !$admin_pass) {
    $error = '请填写所有必填项。';
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  }
  if ($db_type === 'mysql') {
    if (!$mysql['host'] || !$mysql['db'] || !$mysql['user']) {
      $error = '请填写完整的 MySQL 信息。';
      render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
      exit;
    }
  } elseif ($db_type === 'sqlite') {
    if (!$sqlite) {
      $error = '请填写 SQLite 文件路径。';
      render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
      exit;
    }
    $dir = dirname($sqlite);
    if (!is_dir($dir)) {
      if (!mkdir($dir, 0777, true)) {
        $error = '无法创建 SQLite 目录：' . htmlspecialchars($dir);
        render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
        exit;
      }
    }
  } else {
    $error = '未知数据库类型。';
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  }
  try {
    if ($db_type === 'mysql') {
      $dsn = "mysql:host={$mysql['host']};dbname={$mysql['db']};charset=utf8mb4";
      $pdo = new PDO($dsn, $mysql['user'], $mysql['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    } elseif ($db_type === 'sqlite') {
      $dsn = "sqlite:{$sqlite}";
      $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username VARCHAR(64) UNIQUE NOT NULL,
      password VARCHAR(255) NOT NULL,
      role VARCHAR(16) NOT NULL DEFAULT 'admin'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS schemes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name VARCHAR(128) NOT NULL,
      data TEXT NOT NULL,
      owner_id INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
    $stmt->execute([$admin_user, $hash]);
    $config = [
      'db_type' => $db_type,
      'mysql' => $mysql,
      'sqlite' => $sqlite,
    ];
    file_put_contents(__DIR__.'/config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    file_put_contents(__DIR__.'/config.php', "<?php\nreturn ".var_export($config, true).";\n");
    $success = '初始化成功！请删除 install.php 并重新访问首页。';
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  } catch (Exception $e) {
    $error = '初始化失败：' . $e->getMessage();
    render_form($db_type, $mysql, $sqlite, ['user'=>$admin_user]);
    exit;
  }
} else {
  render_form();
};
