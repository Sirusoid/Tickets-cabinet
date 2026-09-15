<?php
// scanner.php
// Мобильная страница проверки QR-билетов для контролёра.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_login();

$scannerAllowed = (string)($_SESSION['user']['role'] ?? '') === 'admin';
if (!$scannerAllowed && isset($pdo) && $pdo instanceof PDO && function_exists('settings_get_value')) {
    $rawPermissions = (string)settings_get_value($pdo, 'security.role_permissions', '');
    $permissions = function_exists('settings_decode_json_value')
        ? settings_decode_json_value($rawPermissions, [])
        : [];
    $scannerAllowed = !empty($permissions[$_SESSION['user']['role'] ?? '']['scanner']);
}
if (!$scannerAllowed) {
    http_response_code(403);
    exit('Доступ к сканеру запрещён.');
}

$use_sidebar = true;
$active_menu = 'scanner';
$page_title_meta = 'Сканер билетов';
$panel_title = 'Сканер билетов';
$panel_subtitle = 'Проверка QR-кода на входе с телефона или планшета';
$page_styles = ['/qr-scanner-app/assets/css/ticket-scanner.css'];
$page_scripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    '/qr-scanner-app/assets/js/ticket_scanner.js',
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full scanner-page">
  <div class="scanner-layout">
    <section class="card scanner-card" aria-labelledby="scanner-title">
      <div class="scanner-card__header">
        <div>
          <h2 id="scanner-title">Проверить билет</h2>
          <p>Разрешите доступ к камере, наведите её на QR-код билета и дождитесь результата.</p>
        </div>
        <span class="scanner-secure-note">Для камеры нужен HTTPS</span>
      </div>

      <div id="qr-reader" class="scanner-camera" aria-label="Область камеры для QR-кода"></div>
      <div id="scanner-status" class="scanner-status scanner-status--idle" role="status" aria-live="polite">
        Камера не запущена
      </div>

      <div class="scanner-actions">
        <button id="start-camera" type="button" class="btn btn-primary">Включить камеру</button>
        <button id="stop-camera" type="button" class="btn btn-ghost" disabled>Остановить</button>
      </div>

      <div class="scanner-divider"><span>или</span></div>

      <form id="manual-scan-form" class="scanner-manual">
        <label for="manual-code">UID билета</label>
        <div class="scanner-manual__row">
          <input id="manual-code" class="form-control" type="text" inputmode="text" autocomplete="off" placeholder="Например, 0d8bebd1c7dba490">
          <button type="submit" class="btn btn-secondary">Проверить</button>
        </div>
        <div class="scanner-help">Можно вставить UID из билета, если камера недоступна.</div>
      </form>

      <label class="scanner-file">
        <span>Сканировать QR с изображения</span>
        <input id="qr-image" type="file" accept="image/*" capture="environment">
      </label>
    </section>

    <section id="scan-result" class="card scanner-result scanner-result--empty" aria-live="polite">
      <div class="scanner-result__placeholder">
        <strong>Результат проверки появится здесь</strong>
        <span>После успешного прохода билет будет отмечен как использованный.</span>
      </div>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
