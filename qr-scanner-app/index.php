<?php
// scanner.php
// Мобильная страница проверки QR-билетов для контролёра.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_once __DIR__ . '/../includes/permissions.php';
require_login();

$scannerAllowed = isset($pdo) && $pdo instanceof PDO
    ? user_has_permission($pdo, 'scanner', false)
    : false;

if (!$scannerAllowed) {
    http_response_code(403);
    exit('Доступ к сканеру запрещён.');
}

19 | $use_sidebar = true; // Показываем боковую панель (для роли "Сканер")
20 | $hide_admin_header = false; // Показываем заголовок панели
$page_title_meta = 'Сканер билетов';
$page_styles = ['/qr-scanner-app/assets/css/ticket-scanner.css?v=' . filemtime(__DIR__ . '/assets/css/ticket-scanner.css')];
$page_scripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    '/qr-scanner-app/assets/js/ticket_scanner.js?v=' . filemtime(__DIR__ . '/assets/js/ticket_scanner.js'),
];

require __DIR__ . '/../includes/header.php';
?>
<div class="page container-full scanner-page">
  <div class="scanner-layout">
    <section class="card scanner-card" aria-labelledby="scanner-title">
      <div class="scanner-card__header">
        <div>
          <h2 id="scanner-title">Проверить билет</h2>
          <p>Выберите сеанс и наведите камеру на QR-код билета.</p>
        </div>
        <span class="scanner-live"><span></span> Контроль входа</span>
      </div>

      <label class="scanner-session-field" for="scanner-session">
        <span>Текущий сеанс</span>
        <select id="scanner-session" required disabled>
          <option value="">Загружаем расписание…</option>
        </select>
      </label>

      <div id="qr-reader" class="scanner-camera" aria-label="Область камеры для QR-кода"></div>
      <div id="scanner-status" class="scanner-status scanner-status--idle" role="status" aria-live="polite">
        Камера не запущена
      </div>

      <div class="scanner-actions">
        <button id="start-camera" type="button" class="btn btn-primary">Включить камеру</button>
        <button id="stop-camera" type="button" class="btn btn-ghost" disabled>Остановить</button>
      </div>

      <section class="scanner-stats" aria-labelledby="scanner-stats-title">
        <div class="scanner-stats__heading">
          <div>
            <span class="scanner-eyebrow">Посещаемость сеанса</span>
            <h3 id="scanner-stats-title">Вход по билетам</h3>
          </div>
          <span id="scanner-stats-updated" class="scanner-stats__updated">—</span>
        </div>
        <div class="scanner-stats__content">
          <div id="scanner-donut" class="scanner-donut" role="img" aria-label="Статистика прохода загружается">
            <div class="scanner-donut__center">
              <strong id="scanner-checked-count">—</strong>
              <span>прошли</span>
            </div>
          </div>
          <div class="scanner-stats__legend">
            <div><span class="scanner-legend-dot scanner-legend-dot--total"></span><span>Всего билетов</span><strong id="scanner-total-count">—</strong></div>
            <div><span class="scanner-legend-dot scanner-legend-dot--checked"></span><span>Отсканировано</span><strong id="scanner-checked-label">—</strong></div>
            <div><span class="scanner-legend-dot scanner-legend-dot--remaining"></span><span>Ожидают входа</span><strong id="scanner-remaining-count">—</strong></div>
          </div>
        </div>
      </section>

      <div class="scanner-divider"><span>или</span></div>


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