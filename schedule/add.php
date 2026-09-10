<?php
// schedule/add.php - страница добавления сеанса
require_once __DIR__ . '/../init.php';
require_login();

$page_scripts = $page_scripts ?? [];
$page_scripts[] = '/assets/js/schedule-seating-canvas.js';
$page_scripts[] = '/assets/js/session-price-editor.js';
$page_scripts[] = '/assets/js/schedule.js';

$halls = db_fetch_all('SELECT id, name FROM halls ORDER BY name');
$events = db_fetch_all("SELECT id, title FROM events WHERE status = 'published' ORDER BY title");

$csrf = $_SESSION['csrf_token'] ?? '';

$use_sidebar = true;
$active_menu = 'schedule';
$page_title_meta = 'Создать сеанс';
$panel_title = 'Создать сеанс';
$panel_subtitle = 'Добавление нового сеанса';
$panel_actions = [
    ['href'=>'/schedule/list.php','label'=>'К расписанию','class'=>'btn btn-primary btn-sm js-panel-back']
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>
<link rel="stylesheet" href="/assets/css/forms.css">
<link rel="stylesheet" href="/assets/css/schedule.css">
<link rel="stylesheet" href="/assets/css/seatmap.css">

<div class="form-grid">

  <div class="form-block" style="padding:16px;">
    <form id="scheduleForm" method="post" action="/ajax/schedule.php" style="width:100%;">
      <input type="hidden" name="action" value="create" />
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />

      <?php
        // Унифицированный вывод заголовка: если есть id (например при редактировании), покажем #id.
        $sessId = $schedule['id'] ?? $id ?? ($_GET['id'] ?? null);
      ?>
      <h2 style="margin-top:0;">
        Параметры сеанса
        <?php if (!empty($sessId)): ?>
          <span class="muted">#<?= intval($sessId) ?></span>
        <?php endif; ?>
      </h2>

      <div style="display:flex; gap:12px; margin-bottom:12px;">
        <div style="flex:1;">
          <label for="event_id">Событие</label>
          <select id="event_id" name="event_id" class="form-control" required>
            <option value="">— выберите событие —</option>
            <?php foreach ($events as $ev): ?>
              <option value="<?= h($ev['id']) ?>"><?= h($ev['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1;">
          <label for="hall_id">Зал</label>
          <select id="hall_id" name="hall_id" class="form-control" required>
            <option value="">— выберите зал —</option>
            <?php foreach ($halls as $h): ?>
              <option value="<?= h($h['id']) ?>"><?= h($h['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="flex:1;">
          <label for="start_time">Начало</label>
          <input id="start_time" name="start_time" type="datetime-local" class="form-control" required />
        </div>

        <div style="flex:1;">
          <label for="end_time">Окончание</label>
          <input id="end_time" name="end_time" type="datetime-local" class="form-control" required />
        </div>
      </div>

      <div style="display:flex; gap:12px; margin-bottom:6px;">
        <div style="flex:1;">
          <label for="sales_start_time">Начало продаж</label>
          <input id="sales_start_time" name="sales_start_time" type="datetime-local" class="form-control" />
        </div>

        <div style="flex:1;">
          <label for="sales_end_time">Окончание продаж</label>
          <input id="sales_end_time" name="sales_end_time" type="datetime-local" class="form-control" />
        </div>

        <div style="flex:1;">
          <label for="base_price">Базовая цена</label>
          <input id="base_price" name="base_price" type="number" step="1" min="0" class="form-control" />
        </div>

        <div style="flex:1;">
          <label for="status">Статус (опционально)</label>
          <select id="status" name="status" class="form-control">
            <option value="">(Авто)</option>
            <option value="draft">Черновик</option>
            <option value="upcoming">Ожидается</option>
            <option value="active">Активный</option>
            <option value="archive">Архивный</option>
            <option value="cancelled">Отменён</option>
          </select>
        </div>
      </div>

      <small style="color:#666; display:block; margin-bottom:6px;">Если оставить статус пустым, он будет вычислен автоматически.</small>

      <!-- Скрытые поля: seat_map и price_ranges.
           JS ожидает id="seat_map" и id/name="price_ranges" -->
      <input type="hidden" id="seat_map" name="seat_map" value="" />
      <input type="hidden" id="price_ranges" name="price_ranges" value="" />
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
      <div style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">
      </div>
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
        <textarea id="notes" name="notes" class="other-details-textarea" style="width:100%; min-height:120px;"></textarea>
      </div>

      <div class="form-actions-bottom" style="margin-top:14px;">
        <button id="saveBtn" type="button" class="btn btn-primary">Сохранить</button>
        <a id="cancelBtn" href="/schedule/list.php" class="btn btn-ghost">Отмена</a>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // При изменении селекта зала вызываем публичную функцию загрузки схемы.
  // Скрипт пытается вызвать несколько возможных точек входа и в крайнем случае диспатчит событие,
  // чтобы другие модули могли подхватить загрузку.
  function tryLoadHallLayout(hallId) {
    if (!hallId) return false;

    // 1) ScheduleApp.loadHallLayout
    try {
      if (window.ScheduleApp && typeof window.ScheduleApp.loadHallLayout === 'function') {
        window.ScheduleApp.loadHallLayout(hallId);
        return true;
      }
    } catch (e) {}

    // 2) SeatingCanvasRender.loadHallLayout
    try {
      if (window.SeatingCanvasRender && typeof window.SeatingCanvasRender.loadHallLayout === 'function') {
        window.SeatingCanvasRender.loadHallLayout(hallId);
        return true;
      }
    } catch (e) {}

    // 3) Если есть глобальная функция с похожим именем (на случай старых реализаций)
    try {
      if (typeof window.loadHallLayout === 'function') {
        window.loadHallLayout(hallId);
        return true;
      }
    } catch (e) {}

    // 4) Диспатчим событие — слушатели могут подхватить
    try {
      var ev = new CustomEvent('schedule:load-hall', { detail: { hallId: hallId } });
      window.dispatchEvent(ev);
    } catch (e) {}

    return false;
  }

  function bindHallSelect() {
    var hallSelect = document.getElementById('hall_id');
    if (!hallSelect) return;

    hallSelect.addEventListener('change', function () {
      var hid = hallSelect.value || '';
      // Попробуем вызвать загрузку сразу; если не удалось — будем пытаться в цикле (retry)
      var loaded = tryLoadHallLayout(hid);
      if (!loaded && hid) {
        var tries = 0;
        var maxTries = 40; // ~4 секунды
        var t = setInterval(function () {
          tries++;
          var ok = tryLoadHallLayout(hid);
          if (ok || tries >= maxTries) {
            clearInterval(t);
          }
        }, 100);
      }
    });

    // Если на странице уже выбран зал (например при редиректе), загрузим его сразу
    if (hallSelect.value) {
      // Делаем небольшой таймаут, чтобы другие скрипты успели инициализироваться
      setTimeout(function () {
        var ev = new Event('change', { bubbles: true });
        hallSelect.dispatchEvent(ev);
      }, 50);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindHallSelect); else bindHallSelect();
})();
</script>

<script>
/*
  Универсальная функция загрузки схемы зала по id.
  Размещена перед закрывающим тегом body, чтобы гарантировать доступность canvas/реденера.
*/
(function () {
  'use strict';

  function sanitizeIncomingLayout(layout) {
    if (!layout || typeof layout !== 'object') return layout;
    try {
      var copy = JSON.parse(JSON.stringify(layout));
      copy.seats = copy.seats || {};
      if (Array.isArray(copy.seats)) {
        var map = {};
        copy.seats.forEach(function (el) {
          if (!el || typeof el !== 'object') return;
          var keys = Object.keys(el || {});
          if (keys.length === 1) {
            var k = keys[0];
            map[k] = el[k];
          }
        });
        if (Object.keys(map).length) copy.seats = map;
        else copy.seats = {};
      } else {
        Object.keys(copy.seats).forEach(function (k) {
          var s = copy.seats[k];
          if (!s || typeof s !== 'object' || Array.isArray(s)) {
            copy.seats[k] = {};
          } else {
            if (s.meta && (typeof s.meta !== 'object' || Array.isArray(s.meta))) {
              delete s.meta;
            }
          }
        });
      }
      return copy;
    } catch (e) {
      return layout;
    }
  }

  function loadHallLayoutById(hallId) {
    if (!hallId) return Promise.reject(new Error('hallId required'));

    var url = '/ajax/get_hall.php?hall_id=' + encodeURIComponent(hallId);
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) {
          return r.json().catch(function () { return { error: 'server error' }; }).then(function (j) {
            throw new Error(j && j.error ? j.error : 'Failed to load hall');
          });
        }
        return r.text();
      })
      .then(function (text) {
        var layout = null;
        try { layout = JSON.parse(text); } catch (e) { throw new Error('Invalid seat_map JSON'); }
        layout = sanitizeIncomingLayout(layout || {});
        try {
          if (window.SeatingCanvasRender && typeof window.SeatingCanvasRender.getInstance === 'function') {
            var sc = window.SeatingCanvasRender.getInstance('editor-canvas');
            if (sc && typeof sc.setLayout === 'function') {
              sc.setLayout(layout);
            }
          }
        } catch (e) { console.warn('setLayout failed', e); }

        try {
          var editor = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null;
          if (editor && typeof editor.renderOverlay === 'function') {
            editor.renderOverlay();
          }
        } catch (e) {}

        try {
          var ev = new CustomEvent('schedule:hall-loaded', { detail: { hallId: hallId, layout: layout } });
          window.dispatchEvent(ev);
        } catch (e) {}

        return layout;
      })
      .catch(function (err) {
        try { window.showToast && window.showToast('Не удалось загрузить схему зала: ' + (err.message || err), 'error'); } catch (e) {}
        console.error('loadHallLayoutById error', err);
        throw err;
      });
  }

  // Экспортируем в глобальную область
  window.ScheduleApp = window.ScheduleApp || {};
  window.ScheduleApp.loadHallLayout = function (hallId) {
    return loadHallLayoutById(hallId);
  };

  // Подписка на событие schedule:load-hall
  window.addEventListener('schedule:load-hall', function (ev) {
    try {
      var hid = ev && ev.detail && ev.detail.hallId ? ev.detail.hallId : null;
      if (hid) loadHallLayoutById(hid);
    } catch (e) {}
  }, false);

})();
</script>

<?php
$footerPath = __DIR__ . '/../includes/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>
