<?php
session_start();
if (!file_exists(__DIR__ . '/config.json')) {
  header('Location: /install.php');
  exit;
}
session_destroy();
header('Location: /login.php');
exit;
