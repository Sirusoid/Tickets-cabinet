<?php
// includes/header.php
// Универсальный header — учитывает $use_sidebar и автоматически подключает sidebar.php,
// если $use_sidebar === true. Перед require header.php можно задать $use_sidebar и $active_menu.

require_once __DIR__ . '/../init.php';

if (!function_exists('current_user')) {
    function current_user() { return $GLOBALS['currentUser'] ?? null; }
}

if (!isset($skip_require_login) || !$skip_require_login) {
    if (function_exists('require_login')) require_login();
    else if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
}

$siteTitle = APP_NAME;
if (function_exists('db_fetch_one')) {
  try {
    $siteRow = db_fetch_one('SELECT value FROM settings WHERE `key` = ? LIMIT 1', ['system.site_name']);
    $siteValue = trim((string)($siteRow['value'] ?? ''));
    if ($siteValue !== '') {
      $siteTitle = $siteValue;
    }
  } catch (Throwable $e) {
    // Keep fallback title if settings table is unavailable.
  }
}

// Метаданные страницы (только для <title> и прочего, не для панели)
$page_title_meta = $page_title_meta ?? ($siteTitle . ' — Панель');
$page_styles   = $page_styles   ?? [];
$page_scripts  = $page_scripts  ?? [];

// Флаг: использовать ли боковое меню (устанавливается в странице до require header.php)
$use_sidebar = isset($use_sidebar) && $use_sidebar === true;

// Активный пункт меню (опционально задаётся в странице)
$active_menu = $active_menu ?? null;

$csrf_token = $_SESSION['csrf_token'] ?? null;
$user = current_user();
$roleLabels = [
  'admin' => 'Администратор',
  'manager' => 'Менеджер',
  'cashier' => 'Кассир',
];
$userRole = strtolower(trim((string)($user['role'] ?? '')));
$userRoleLabel = $roleLabels[$userRole] ?? ($userRole !== '' ? ucfirst($userRole) : 'Пользователь');
$userName = trim((string)($user['full_name'] ?? $user['name'] ?? $user['username'] ?? $user['email'] ?? ''));
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title><?= h($page_title_meta) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <?php foreach ($page_styles as $s): ?><link rel="stylesheet" href="<?= h($s) ?>"><?php endforeach; ?>
  <script>
    window.APP = {
      csrf: <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE) ?>,
      currentUser: <?= json_encode($user ?? null, JSON_UNESCAPED_UNICODE) ?>,
      baseUrl: <?= json_encode('/', JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
</head>
<body class="admin-ui<?= $use_sidebar ? ' with-sidebar' : '' ?>">
  <?php if (empty($hide_admin_header)): ?>
  <header class="admin-header">
    <div class="brand">
      <?php if ($use_sidebar): ?>
        <button id="sidebar-toggle" class="sidebar-toggle" aria-label="Toggle menu">☰</button>
      <?php endif; ?>
      <a href="/dashboard.php"><?= h($siteTitle) ?></a>
    </div>
    <div class="admin-header__user" aria-label="Текущий пользователь">
      <span class="admin-header__user-role"><?= h($userRoleLabel) ?>:</span>
      <strong class="admin-header__user-name"><?= h($userName !== '' ? $userName : '—') ?></strong>
    </div>
  </header>
  <?php endif; ?>

  <?php
  // Если страница запросила sidebar — подключаем модуль и передаём $active_menu
  if ($use_sidebar) {
      $sidebar_active = $active_menu;
      require_once __DIR__ . '/sidebar.php';
  }
  ?>

  <main class="container<?= !empty($hide_admin_header) ? ' widget-iframe-mode' : '' ?>" role="main">
    <!-- Панель заголовка теперь формируется в конкретной странице (events/list.php и т.д.) -->
