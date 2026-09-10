<?php
// includes/sidebar.php
// Модуль бокового меню. Header перед подключением устанавливает $sidebar_active = $active_menu (опционально).

require_once __DIR__ . '/settings_manager.php';

$active = $sidebar_active ?? null;
$currentPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$settingsPages = function_exists('settings_pages_map') ? settings_pages_map() : [];
$isSettingsPath = strpos($currentPath, '/settings/') === 0;
$currentRole = (string)($_SESSION['user']['role'] ?? '');
$rawMatrix = '';
if (isset($pdo) && $pdo instanceof PDO && function_exists('settings_get_value')) {
  $rawMatrix = (string)settings_get_value($pdo, 'security.role_permissions', '');
}
$permissionsMatrix = function_exists('settings_decode_json_value')
  ? settings_decode_json_value($rawMatrix, [])
  : [];
$hasPermissionsMatrix = is_array($permissionsMatrix) && !empty($permissionsMatrix);
$isAllowed = function ($permKey, $fallback = true) use ($currentRole, $permissionsMatrix, $hasPermissionsMatrix) {
  if ($currentRole === 'admin') {
    return true;
  }
  if (!$hasPermissionsMatrix || $permKey === null || $permKey === '') {
    return (bool)$fallback;
  }
  $roleMatrix = $permissionsMatrix[$currentRole] ?? null;
  if (!is_array($roleMatrix)) {
    return (bool)$fallback;
  }
  return array_key_exists($permKey, $roleMatrix)
    ? !empty($roleMatrix[$permKey])
    : (bool)$fallback;
};

// Основное меню (простые ссылки)
$menu = [
  ['id'=>'dashboard','href'=>'/dashboard.php','label'=>'Панель','perm'=>'dashboard','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 13h8V3H3v10zM3 21h8v-6H3v6zM13 21h8V11h-8v10zM13 3v6h8V3h-8z" fill="currentColor"/></svg>'],
  ['id'=>'cash','href'=>'/cash/index.php','label'=>'Касса','perm'=>'cash','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7h18v10H3z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 11h10M7 15h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="16" y="3" width="5" height="4" rx="1" fill="currentColor"/></svg>'],
  ['id'=>'schedule','href'=>'/schedule/list.php','label'=>'Расписание','perm'=>'schedule','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 11h10M7 15h6M3 7h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
  ['id'=>'events','href'=>'/events/list.php','label'=>'Спектакли','perm'=>'events','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16v10H4z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
  ['id'=>'tickets','href'=>'/tickets/list.php','label'=>'Билеты','perm'=>'tickets','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 10V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
  ['id'=>'customers','href'=>'/customers/list.php','label'=>'Клиенты','perm'=>'customers','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5c0-2.2-1.8-4-4-4H7c-2.2 0-4 1.8-4 4V20M9.5 10.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17 3.5a3.5 3.5 0 1 1 0 7M17 14.5h1c2.2 0 4 1.8 4 4V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
  ['id'=>'actors','href'=>'/actors/list.php','label'=>'Актёры','perm'=>'actors','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm-7 18c0-3.866 3.582-7 8-7s8 3.134 8 7v1H5v-1z" fill="currentColor"/></svg>'],
  ['id'=>'halls','href'=>'/halls/list.php','label'=>'Залы','perm'=>'halls','icon'=>'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 21h18M5 3v18M19 3v18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
];

$settingsPermMap = [
  'general' => 'settings_general',
  'interface' => 'settings_interface',
  'tickets' => 'settings_tickets',
  'discounts' => 'settings_discounts',
  'payment' => 'settings_payment',
  'notifications' => 'settings_notifications',
  'security' => 'settings_security',
  'users' => 'settings_users',
  'permissions' => 'settings_permissions',
];

$allowedSettingsLinks = [];
foreach ($settingsPages as $slug => $cfg) {
  $perm = $settingsPermMap[$slug] ?? null;
  if ($isAllowed($perm, true)) {
    $allowedSettingsLinks[$slug] = $cfg;
  }
}

$hasSettingsLinks = !empty($allowedSettingsLinks);
$isSettingsExpanded = $isSettingsPath ? 'true' : 'false';
?>
<aside class="admin-sidebar" id="admin-sidebar" aria-hidden="false">
  <div class="sidebar-inner">
    <nav class="sidebar-menu" role="navigation" aria-label="Главная навигация">
      <?php foreach ($menu as $item):
        if (!$isAllowed($item['perm'] ?? null, true)) {
          continue;
        }
        $isActive = ($active === $item['id']) ? ' is-active' : '';
      ?>
        <a role="button" href="<?= h($item['href']) ?>" class="sidebar-button<?= $isActive ?>" data-menu-id="<?= h($item['id']) ?>">
          <span class="sidebar-button__icon" aria-hidden="true"><?= $item['icon'] ?></span>
          <span class="sidebar-button__label"><?= h($item['label']) ?></span>
        </a>
      <?php endforeach; ?>


    <!-- Нижняя группа: Настройки и Выход -->
    <div class="sidebar-bottom" style="margin-top:16px; padding-top:12px; border-top:1px solid #eef2f6;">
      <?php if ($hasSettingsLinks): ?>
      <button
        type="button"
        id="sidebar-settings-toggle"
        class="sidebar-button sidebar-button--muted sidebar-settings-toggle<?= ($active === 'settings' || $isSettingsPath ? ' is-active' : '') ?>"
        aria-expanded="<?= h($isSettingsExpanded) ?>"
        aria-controls="sidebar-settings-submenu"
      >
        <span class="sidebar-button__icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.67 0 1.2-.5 1.51-1a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06c.5.5 1.2.67 1.82.33.5-.28 1.1-.44 1.51-1V3a2 2 0 0 1 4 0v.09c.41.56 1.01.72 1.51 1 .62.34 1.32.17 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06c-.28.5-.44 1.1-.33 1.82.28.5.44 1.1 1 1.51H21a2 2 0 0 1 0 4h-.09c-.56 0-1.2.5-1.51 1z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="sidebar-button__label">Настройки</span>
        <span class="sidebar-settings-toggle__caret" aria-hidden="true">▾</span>
      </button>
      <?php endif; ?>

      <?php if ($hasSettingsLinks): ?>
        <div
          id="sidebar-settings-submenu"
          class="sidebar-submenu<?= $isSettingsExpanded !== 'true' ? ' is-collapsed' : '' ?>"
          aria-label="Разделы настроек"
        >
          <?php foreach ($allowedSettingsLinks as $slug => $cfg):
            $path = (string)($cfg['path'] ?? '#');
            $subActive = $currentPath === $path;
          ?>
            <a href="<?= h($path) ?>" class="sidebar-submenu__link<?= $subActive ? ' is-active' : '' ?>">
              <?= h($cfg['label'] ?? $slug) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form id="logoutForm" method="post" action="/logout.php" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
        <button id="logoutButton" type="button" class="sidebar-button sidebar-button--muted" style="width:100%; text-align:left; border:none; background:transparent;">
          <span class="sidebar-button__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span class="sidebar-button__label">Выход</span>
        </button>
      </form>
    </div>
  </div>
</aside>

<script>
(function(){
  var toggle = document.getElementById('sidebar-toggle');
  var sidebar = document.getElementById('admin-sidebar');
  var settingsToggle = document.getElementById('sidebar-settings-toggle');
  var settingsSubmenu = document.getElementById('sidebar-settings-submenu');

  if (settingsToggle && settingsSubmenu) {
    settingsToggle.addEventListener('click', function(){
      var expanded = settingsToggle.getAttribute('aria-expanded') === 'true';
      settingsToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      settingsSubmenu.classList.toggle('is-collapsed', expanded);
    });
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function(){
      document.body.classList.toggle('sidebar-collapsed');
      var hidden = sidebar.getAttribute('aria-hidden') === 'true';
      sidebar.setAttribute('aria-hidden', hidden ? 'false' : 'true');
    });
  }
})();
</script>
