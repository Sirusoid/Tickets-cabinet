<?php
// schedule/edit.php - страница редактирования сеанса
require_once __DIR__ . '/../init.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    redirect('/schedule/list.php');
}

// Получаем данные сеанса (таблица schedules)
$session = db_fetch_one('SELECT * FROM schedules WHERE id = ?', [$id]);
if (!$session) {
    redirect('/schedule/list.php');
}

// Подготовка скриптов
$page_scripts = $page_scripts ?? [];
$page_scripts[] = '/assets/js/schedule-seating-canvas.js';
$page_scripts[] = '/assets/js/session-price-editor.js';
$page_scripts[] = '/assets/js/schedule.js';

$halls = db_fetch_all('SELECT id, name FROM halls ORDER BY name');
$events = db_fetch_all("SELECT id, title FROM events WHERE status = 'published' ORDER BY title");

$editing = !empty($session) && !empty($session['id']); // true если редактирование
$disabledAttr = $editing ? 'disabled' : '';

$csrf = $_SESSION['csrf_token'] ?? '';

$use_sidebar = true;
$active_menu = 'schedule';
$page_title_meta = 'Редактировать сеанс';
$panel_title = 'Редактировать сеанс';
$panel_subtitle = 'Изменение параметров сеанса';
$panel_actions = [
    ['href' => '/schedule/list.php', 'label' => 'К расписанию', 'class' => 'btn btn-primary btn-sm js-panel-back']
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';

// Вспомогательная функция для value поля datetime-local
if (!function_exists('__format_datetime_local_fallback')) {
    function __format_datetime_local_fallback($val) {
        if (!$val) return '';
        // Поддерживаем форматы: 'YYYY-MM-DD HH:MM:SS' или ISO
        $ts = false;
        if (is_numeric($val)) {
            $ts = (int)$val;
        } else {
            // Попытка распарсить через strtotime
            $ts = strtotime($val);
        }
        if ($ts === false || $ts <= 0) return '';
        // Формат для input[type=datetime-local]: YYYY-MM-DDTHH:MM
        return date('Y-m-d\TH:i', $ts);
    }
}

// Подготовка значений для полей
$seat_map_value = $session['seat_map'] ?? '';
$price_ranges_value = $session['price_ranges'] ?? '';
$notes_value = $session['notes'] ?? '';

// Форматируем даты для datetime-local (используем существующую функцию, если есть)
function _fmt_local($val) {
    if (!$val) return '';
    if (function_exists('format_datetime_local')) {
        try { return format_datetime_local($val); } catch (Throwable $e) {}
    }
    return __format_datetime_local_fallback($val);
}

// Базовая цена — целое число
$base_price_value = isset($session['base_price']) ? (int)$session['base_price'] : '';

?>
<link rel="stylesheet" href="/assets/css/forms.css">
<link rel="stylesheet" href="/assets/css/schedule.css">
<link rel="stylesheet" href="/assets/css/seatmap.css">

<div class="form-grid">

  <div class="form-block" style="padding:16px;">
    <form id="scheduleForm" method="post" action="/ajax/schedule.php" style="width:100%;">
      <input type="hidden" name="action" value="update" />
      <input type="hidden" name="id" value="<?= h($session['id']) ?>" />
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />

      <h2 style="margin-top:0;">Параметры сеанса #<?= h($session['id']) ?></h2>

      <div style="display:flex; gap:12px; margin-bottom:12px;">
        <div style="flex:1;">
          <label for="event_id">Событие</label>
			<select id="event_id" name="event_id" <?= $disabledAttr ?> class="form-control">
			  <?php foreach ($events as $ev): ?>
				<option value="<?= htmlspecialchars($ev['id'], ENT_QUOTES) ?>" <?= (isset($session['event_id']) && $session['event_id'] == $ev['id']) ? 'selected' : '' ?>>
				  <?= htmlspecialchars($ev['title'], ENT_QUOTES) ?>
				</option>
			  <?php endforeach; ?>
			</select>

			<?php if ($editing): ?>
			  <!-- disabled поля не отправляются, поэтому добавляем скрытое поле с тем же именем -->
			  <input type="hidden" name="event_id" value="<?= htmlspecialchars($session['event_id'], ENT_QUOTES) ?>">
			<?php endif; ?>
        </div>

        <div style="flex:1;">
          <label for="hall_id">Зал</label>
			<select id="hall_id" name="hall_id" <?= $disabledAttr ?> class="form-control">
			  <?php foreach ($halls as $h): ?>
				<option value="<?= htmlspecialchars($h['id'], ENT_QUOTES) ?>" <?= (isset($session['hall_id']) && $session['hall_id'] == $h['id']) ? 'selected' : '' ?>>
				  <?= htmlspecialchars($h['name'], ENT_QUOTES) ?>
				</option>
			  <?php endforeach; ?>
			</select>

			<?php if ($editing): ?>
			  <input type="hidden" name="hall_id" value="<?= htmlspecialchars($session['hall_id'], ENT_QUOTES) ?>">
			<?php endif; ?>
        </div>

        <div style="flex:1;">
          <label for="start_time">Начало</label>
          <input id="start_time" name="start_time" type="datetime-local" class="form-control" required value="<?= h(_fmt_local($session['start_time'] ?? '')) ?>" />
        </div>

        <div style="flex:1;">
          <label for="end_time">Окончание</label>
          <input id="end_time" name="end_time" type="datetime-local" class="form-control" required value="<?= h(_fmt_local($session['end_time'] ?? '')) ?>" />
        </div>
      </div>

      <div style="display:flex; gap:12px; margin-bottom:6px;">
        <div style="flex:1;">
          <label for="sales_start_time">Начало продаж</label>
          <input id="sales_start_time" name="sales_start_time" type="datetime-local" class="form-control" value="<?= h(_fmt_local($session['sales_start_time'] ?? '')) ?>" />
        </div>

        <div style="flex:1;">
          <label for="sales_end_time">Окончание продаж</label>
          <input id="sales_end_time" name="sales_end_time" type="datetime-local" class="form-control" value="<?= h(_fmt_local($session['sales_end_time'] ?? '')) ?>" />
        </div>

        <div style="flex:1;">
          <label for="base_price">Базовая цена</label>
          <input id="base_price" name="base_price" type="number" step="1" min="0" class="form-control" value="<?= h($base_price_value) ?>" />
        </div>

        <div style="flex:1;">
          <label for="status">Статус (опционально)</label>
          <select id="status" name="status" class="form-control">
            <option value="" <?= ($session['status'] === null || $session['status'] === '') ? 'selected' : '' ?>>(Авто)</option>
            <option value="draft" <?= ($session['status'] === 'draft') ? 'selected' : '' ?>>Черновик</option>
            <option value="upcoming" <?= ($session['status'] === 'upcoming') ? 'selected' : '' ?>>Ожидается</option>
            <option value="active" <?= ($session['status'] === 'active') ? 'selected' : '' ?>>Активный</option>
            <option value="archive" <?= ($session['status'] === 'archive') ? 'selected' : '' ?>>Архивный</option>
            <option value="cancelled" <?= ($session['status'] === 'cancelled') ? 'selected' : '' ?>>Отменён</option>
          </select>
        </div>
      </div>

      <small style="color:#666; display:block; margin-bottom:6px;">Если оставить статус пустым, он будет вычислен автоматически.</small>

      <input type="hidden" id="seat_map" name="seat_map" value='<?= h($seat_map_value) ?>' />
      <input type="hidden" id="price_ranges" name="price_ranges" value='<?= h($price_ranges_value) ?>' />
    </form>
  </div>

  <div class="form-block" style="padding:0 16px 16px 16px;">
    <h2 style="margin:12px 0 8px 0;">Визуальное редактирование цен</h2>

    <section style="width:1350px; max-width:100%; box-sizing:border-box;">
      <div id="editor-canvas-wrapper" class="editor-wrapper" style="display:flex; justify-content:center;">
        <canvas id="editor-canvas" width="1320" height="760" style="max-width:100%; height:auto; display:block;"></canvas>
      </div>
      <div class="editor-hint" style="margin-top:8px;">
        <small>Клик по месту — выделение. Зажать и тянуть — прямоугольное выделение. Используйте панель ниже для назначения цены/цвета.</small>
      </div>
    </section>

    <div style="margin-top:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <label style="font-size:13px; color:#444; margin-right:6px;">Инструмент:</label>
      <button id="tool-select" class="btn btn-ghost btn-xs">Выделение</button>
      <button id="tool-rect" class="btn btn-ghost btn-xs">Прямоугольник</button>
      <button id="tool-clear-selection" class="btn btn-ghost btn-xs">Снять выделение</button>

      <label style="margin-left:12px; font-size:13px;">Поведение при назначении:</label>
      <label style="font-size:13px;"><input id="optOverwrite" type="checkbox" /> Перезаписывать существующие</label>
    </div>

    <div style="margin-top:6px; font-size:12px; color:#666;">
      Схема загружается после выбора зала. Назначение цены — через панель ниже.
    </div>
  </div>

  <div class="form-block" style="padding:12px 16px;">
    <div style="width:1228px; max-width:100%; box-sizing:border-box; border:1px solid #e6e6e6; padding:12px; background:#fff;">
      <div style="display:flex; gap:12px; align-items:center; width:100%; flex-wrap:wrap;">
        <div style="display:flex; gap:8px; align-items:center; flex:1; min-width:220px;">
          <label style="width:60px;">Цена</label>
          <input id="assignPrice" type="text" class="form-control" placeholder="например 3000" style="width:160px;" />
        </div>

        <div style="display:flex; gap:8px; align-items:center; min-width:180px;">
          <label style="width:60px;">Цвет</label>
          <input id="assignColor" type="color" class="form-control" style="width:64px; padding:0; border:none;" />
          <button id="autoColor" class="btn btn-ghost btn-xs">Авто</button>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
          <button id="assignBtn" class="btn btn-primary">Назначить</button>
          <button id="unassignBtn" class="btn btn-ghost">Снять цену</button>
        </div>

        <div style="margin-left:auto; font-size:13px; color:#333;">
          <strong>Выбрано мест:</strong> <span id="selectedCount">0</span>
        </div>
      </div>

      <hr style="margin:12px 0;" />

      <h3 style="margin:0 0 8px 0;">Группы цен (редактирование)</h3>
      <div style="max-height:220px; overflow:auto;">
        <table class="table table--compact" id="priceGroupsTable" style="width:100%; table-layout:fixed;">
          <thead>
            <tr>
              <th style="width:20%;">Цена</th>
              <th style="width:30%;">Цвет</th>
              <th style="width:50%;">Действие</th>
            </tr>
          </thead>
          <tbody id="priceGroupsTbody"></tbody>
        </table>
      </div>

      <div style="margin-top:8px; font-size:12px; color:#666;">
        Редактирование группы изменит цвет для всех мест с этой ценой.
      </div>
    </div>
  </div>

  <div class="form-block" style="padding:16px;">
    <div style="width:1228px; max-width:100%; box-sizing:border-box;">
      <h2 style="margin-top:0;">Примечания и сохранение</h2>

      <div style="margin-top:6px;">
        <label for="notes" style="font-weight:600; display:block; margin-bottom:8px;">Примечания</label>
        <textarea id="notes" name="notes" class="other-details-textarea" style="width:100%; min-height:120px;"><?= h($notes_value) ?></textarea>
      </div>

      <div class="form-actions-bottom" style="margin-top:14px;">
        <button id="saveBtn" type="button" class="btn btn-primary">Сохранить</button>
        <a id="cancelBtn" href="/schedule/list.php" class="btn btn-ghost">Отмена</a>
      </div>
    </div>
  </div>

</div>

<?php
$footerPath = __DIR__ . '/../includes/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>
