<?php
// schedule/list.php  (расписание - список сеансов)
require_once __DIR__ . '/../init.php';
require_login();

$page_scripts = $page_scripts ?? [];
$page_scripts[] = '';

$halls = db_fetch_all('SELECT id, name FROM halls ORDER BY name');
$events = db_fetch_all('SELECT id, title FROM events ORDER BY title');

$use_sidebar = true;
$active_menu = 'schedule';
$page_title_meta = 'Расписание';
$panel_title = 'Расписание';
$panel_subtitle = 'Список сеансов';
$panel_actions = [
  ['href'=>'/schedule/add.php','label'=>'Создать сеанс','class'=>'btn btn-primary btn-sm']
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';

if (function_exists('schedule_refresh_statuses') && isset($pdo) && $pdo instanceof PDO) {
  schedule_refresh_statuses($pdo);
}

// --- Хелперы для цвета статуса (как в events/list.php) ---
if (!function_exists('normalize_hex_color')) {
    function normalize_hex_color($raw) {
        if (empty($raw)) return null;
        $s = trim((string)$raw);
        if (preg_match('/^#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $s, $m)) {
            return '#' . strtolower($m[1]);
        }
        return null;
    }
}
if (!function_exists('contrast_text_color')) {
    function contrast_text_color($hex) {
        if (!$hex) return '#000';
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $r = hexdec(str_repeat($h[0],2));
            $g = hexdec(str_repeat($h[1],2));
            $b = hexdec(str_repeat($h[2],2));
        } else {
            $r = hexdec(substr($h,0,2));
            $g = hexdec(substr($h,2,2));
            $b = hexdec(substr($h,4,2));
        }
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b);
        return ($lum > 186) ? '#000' : '#fff';
    }
}

// --- Параметры фильтрации и сортировки (GET) ---
$filter_hall = isset($_GET['hall_id']) && $_GET['hall_id'] !== '' ? (int)$_GET['hall_id'] : null;
$filter_event = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? (int)$_GET['event_id'] : null;
$filter_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : null; // YYYY-MM-DD
$filter_status = isset($_GET['status']) ? (string)$_GET['status'] : ''; // '', active, upcoming, draft, archive, cancelled
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'date_desc'; // date_asc | date_desc

$where = [];
if ($filter_hall) $where[] = 's.hall_id = ' . $filter_hall;
if ($filter_event) $where[] = 's.event_id = ' . $filter_event;
if ($filter_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $where[] = "DATE(s.start_time) = '" . $filter_date . "'";
}
$where_sql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

$order_by = $sort === 'date_asc' ? 's.start_time ASC' : 's.start_time DESC';

// Получаем список сеансов с названиями событий и залов, диапазонами цен и примечаниями
$sql = "SELECT s.id, s.event_id, s.hall_id, s.start_time, s.end_time, s.base_price,
               s.sales_start_time, s.sales_end_time, s.status,
               s.price_ranges, s.notes,
         COALESCE(e.duration_minutes, 0) AS duration_minutes,
         e.title AS event_title, h.name AS hall_name
        FROM schedules s
        LEFT JOIN events e ON e.id = s.event_id
        LEFT JOIN halls h ON h.id = s.hall_id
        {$where_sql}
        ORDER BY {$order_by}
        LIMIT 500";
$schedules_raw = db_fetch_all($sql);

// допустимые статусы
$ALLOWED_STATUSES = ['draft','upcoming','active','archive','cancelled'];

$schedules = [];
foreach ($schedules_raw as $s) {
  $computed = function_exists('schedule_resolved_status')
    ? schedule_resolved_status($s)
    : 'upcoming';

    if ($filter_status !== '' && $computed !== $filter_status) continue;

    $s['_computed_status'] = $computed;
    $schedules[] = $s;
}
?>
<link rel="stylesheet" href="/assets/css/schedule.css">

<div class="card container-full">

    <form id="filterForm" style="display:flex; gap:8px; margin-bottom:12px; align-items:center;" method="get" action="">
      <select id="filter_hall" name="hall_id" class="form-control" style="width:220px;">
        <option value="">Все залы</option>
        <?php foreach ($halls as $h): ?>
          <option value="<?= h($h['id']) ?>" <?= ($filter_hall == $h['id']) ? 'selected' : '' ?>><?= h($h['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select id="filter_event" name="event_id" class="form-control" style="width:220px;">
        <option value="">Все события</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= h($ev['id']) ?>" <?= ($filter_event == $ev['id']) ? 'selected' : '' ?>><?= h($ev['title']) ?></option>
        <?php endforeach; ?>
      </select>

      <input type="date" id="filter_date" name="date" value="<?= h($filter_date ?? '') ?>" class="form-control" style="width:160px;" />

      <select id="filter_status" name="status" class="form-control" style="width:160px;">
        <option value="">Все статусы</option>
        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Активный</option>
        <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Ожидается</option>
        <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Черновик</option>
        <option value="archive" <?= $filter_status === 'archive' ? 'selected' : '' ?>>Архивный</option>
        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
      </select>

      <select id="filter_sort" name="sort" class="form-control" style="width:160px;">
        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Сначала новые</option>
        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Сначала старые</option>
      </select>

      <button id="btnFilter" type="submit" class="btn btn-secondary">Применить</button>
      <a id="btnClear" class="btn btn-ghost" href="/schedule/list.php">Сброс</a>
    </form>

    <div id="schedulesContainer">
      <table class="table admin-table table--compact" id="schedulesTable">
        <thead>
          <tr>
            <th style="width:6%;">#</th>
            <th style="width:12%;">Дата</th>
            <th style="width:22%;">Событие</th>
            <th style="width:10%;">Статус</th>
            <th style="width:8%;">Начало</th>
            <th style="width:8%;">Окончание</th>
            <th style="width:12%;">Зал</th>
            <th style="width:8%;">Цена</th>
            <th style="width:10%;">Примечание</th>
            <th class="actions-col" style="width:6%;">Действия</th>
          </tr>
        </thead>
        <tbody id="schedulesTbody">
          <?php if (empty($schedules)): ?>
            <tr><td colspan="10" style="text-align:center; color:#666; padding:18px;">Сеансов не найдено</td></tr>
          <?php else: ?>
            <?php foreach ($schedules as $s): ?>
              <?php
                $startTs = !empty($s['start_time']) ? strtotime($s['start_time']) : false;
                $endTs = !empty($s['end_time']) ? strtotime($s['end_time']) : false;
                $date = $startTs ? date('d/m/Y', $startTs) : '';
                $startTime = $startTs ? date('H:i', $startTs) : '';
                $endTime = $endTs ? date('H:i', $endTs) : '';
                $status = $s['_computed_status'] ?? 'upcoming';

                // mapping label and default color
                $status_label = 'Ожидается';
                $status_color = '#2d6fa3';
                if ($status === 'active') { $status_label = 'Активный'; $status_color = '#1a7f37'; }
                elseif ($status === 'draft') { $status_label = 'Черновик'; $status_color = '#b36b00'; }
                elseif ($status === 'archive') { $status_label = 'В архиве'; $status_color = '#555555'; }
                elseif ($status === 'cancelled') { $status_label = 'Отменён'; $status_color = '#a00'; }

                $normColor = normalize_hex_color($status_color) ?? null;
                $textColor = $normColor ? contrast_text_color($normColor) : '#fff';
                $styleAttr = $normColor ? ' style="background:'.h($normColor).'; color:'.h($textColor).'; border-color:'.h($normColor).';"' : '';

                // event title for data attribute (escaped)
                $event_title_attr = isset($s['event_title']) ? h($s['event_title']) : '';

                // --- compute min/max price from price_ranges ---
                $priceDisplay = '<span style="color:#888">—</span>';
                $minPrice = null;
                $maxPrice = null;

                // price_ranges may be stored as JSON string or already decoded
                $prRaw = $s['price_ranges'] ?? $s['priceRanges'] ?? null;
                if ($prRaw) {
                  $pr = null;
                  if (is_string($prRaw)) {
                    $pr = json_decode($prRaw, true);
                  } elseif (is_array($prRaw)) {
                    $pr = $prRaw;
                  }

                  if (is_array($pr)) {
                    $collected = [];

                    // If associative with price keys (e.g., {"2000": {...}, "3000": {...}})
                    $is_assoc_with_price_keys = false;
                    foreach ($pr as $k => $v) {
                      if (!is_int($k)) {
                        if (is_numeric($k)) { $is_assoc_with_price_keys = true; break; }
                      }
                    }

                    if ($is_assoc_with_price_keys) {
                      foreach ($pr as $k => $v) {
                        if (is_numeric($k)) $collected[] = (int) round((float)$k);
                        if (is_array($v) && isset($v['price']) && is_numeric($v['price'])) $collected[] = (int) round((float)$v['price']);
                      }
                    } else {
                      // array of items [{price:..., ranges:...}, ...] or [{value:...}, ...] or simple numeric array
                      foreach ($pr as $itemKey => $item) {
                        if (is_array($item)) {
                          if (isset($item['price']) && is_numeric($item['price'])) $collected[] = (int) round((float)$item['price']);
                          elseif (isset($item['value']) && is_numeric($item['value'])) $collected[] = (int) round((float)$item['value']);
                          elseif (isset($item['p']) && is_numeric($item['p'])) $collected[] = (int) round((float)$item['p']);
                        } elseif (is_numeric($item)) {
                          // sometimes price_ranges is a simple array of numbers
                          $collected[] = (int) round((float)$item);
                        } elseif (is_numeric($itemKey)) {
                          // fallback: numeric key might be price
                          $collected[] = (int) round((float)$itemKey);
                        }
                      }
                    }

                    // final fallback: try to extract numeric keys again
                    if (empty($collected)) {
                      foreach ($pr as $k => $v) {
                        if (is_numeric($k)) $collected[] = (int) round((float)$k);
                      }
                    }

                    if (!empty($collected)) {
                      $minPrice = min($collected);
                      $maxPrice = max($collected);
                    }
                  }
                }

                // Fallbacks: if no price_ranges found, use base_price if present
                if ($minPrice === null && $maxPrice === null) {
                  if (isset($s['base_price']) && $s['base_price'] !== null && $s['base_price'] !== '') {
                    $val = (int) round((float)$s['base_price']);
                    $minPrice = $maxPrice = $val;
                  }
                }

                if ($minPrice !== null && $maxPrice !== null) {
                  if ($minPrice === $maxPrice) {
                    $priceDisplay = h((string)(int)$minPrice);
                  } else {
                    $priceDisplay = h((string)(int)$minPrice) . ' — ' . h((string)(int)$maxPrice);
                  }
                }

                // Примечание (notes)
                $rawNote = $s['notes'] ?? $s['note'] ?? '';
                $noteDisplay = $rawNote !== '' ? h($rawNote) : '<span style="color:#888">—</span>';
              ?>
              <tr data-id="<?= h($s['id']) ?>" data-event-title="<?= $event_title_attr ?>" style="cursor:pointer;">
                <td style="padding:10px; vertical-align:middle;"><?= h($s['id']) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($date) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($s['event_title'] ?? ('#' . $s['event_id'])) ?></td>

                <td style="padding:10px; vertical-align:middle;">
                  <span class="badge"<?= $styleAttr ?>><?= h($status_label) ?></span>
                </td>

                <td style="padding:10px; vertical-align:middle;"><?= h($startTime) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($endTime) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($s['hall_name'] ?? ('#' . $s['hall_id'])) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= $priceDisplay ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= $noteDisplay ?></td>
                <td class="actions-col" style="padding:10px; vertical-align:middle;">
                  <div class="action-buttons">
                    <a class="btn btn-ghost btn-sm" href="/schedule/edit.php?id=<?= h($s['id']) ?>">Редактировать</a>
					<button class="btn btn-ghost btn-sm js-duplicate-session" data-id="<?= h($s['id']) ?>" data-event-title="<?= $event_title_attr ?>">Дублировать</button>  
                    <button class="btn btn-danger btn-sm js-delete-session" data-id="<?= h($s['id']) ?>" data-event-title="<?= $event_title_attr ?>">Удалить</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
(function(){

  function showToast(text, type) {
    if (typeof window.showToast === 'function') return window.showToast(text, type);
    var bg = type === 'success' ? '#2d9c2d' : (type === 'error' ? '#c0392b' : '#2d6fa3');
    var el = document.createElement('div');
    el.textContent = text;
    el.style.position = 'fixed';
    el.style.right = '20px';
    el.style.top = (20 + (window._toastCount || 0) * 60) + 'px';
    el.style.background = bg;
    el.style.color = '#fff';
    el.style.padding = '10px 14px';
    el.style.borderRadius = '8px';
    el.style.boxShadow = '0 6px 18px rgba(0,0,0,0.12)';
    el.style.zIndex = 99999;
    document.body.appendChild(el);
    window._toastCount = (window._toastCount || 0) + 1;
    setTimeout(function () {
      try { el.remove(); } catch (e) {}
      window._toastCount = Math.max(0, (window._toastCount || 1) - 1);
    }, 5000);
  }

  // helper: wait for element to appear in DOM (returns Promise)
  function waitForElement(selector, timeoutMs) {
    timeoutMs = typeof timeoutMs === 'number' ? timeoutMs : 1000;
    return new Promise(function(resolve, reject){
      var el = document.querySelector(selector);
      if (el) return resolve(el);
      var observer = new MutationObserver(function(){
        var found = document.querySelector(selector);
        if (found) {
          observer.disconnect();
          clearTimeout(timer);
          resolve(found);
        }
      });
      observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
      var timer = setTimeout(function(){
        observer.disconnect();
        reject(new Error('timeout'));
      }, timeoutMs);
    });
  }

  // --- AJAX фильтр без перезагрузки страницы ---
  (function bindAjaxFilter() {
    var form = document.getElementById('filterForm');
    if (!form) return;

    var inputs = form.querySelectorAll('select[name], input[type="date"][name]');
    var debounceMs = 300;
    var timer = null;

    function serializeForm(frm) {
      var params = new URLSearchParams();
      Array.prototype.slice.call(frm.elements).forEach(function(el){
        if (!el.name) return;
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (!el.checked) return;
        }
        params.append(el.name, el.value);
      });
      return params.toString();
    }

    function fetchAndReplace(queryString, pushUrl) {
      var url = window.location.pathname + (queryString ? ('?' + queryString) : '');
      var table = document.getElementById('schedulesTable');
      if (table) table.style.opacity = '0.6';

      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(resp){
          if (!resp.ok) throw new Error('Сервер вернул ' + resp.status);
          return resp.text();
        })
        .then(function(html){
          var parser = new DOMParser();
          var doc = parser.parseFromString(html, 'text/html');
          var newTbody = doc.querySelector('#schedulesTbody');
          if (!newTbody) throw new Error('Не удалось получить данные таблицы от сервера');
          var currentTbody = document.querySelector('#schedulesTbody');
          if (currentTbody) currentTbody.innerHTML = newTbody.innerHTML;
          if (pushUrl) {
            try { history.replaceState({}, '', url); } catch (e) {}
          }
        })
        .catch(function(err){
          showToast('Ошибка получения списка: ' + (err.message || err), 'error');
        })
        .finally(function(){
          if (table) table.style.opacity = '';
        });
    }

    function submitDebounced(pushUrl) {
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () {
        var qs = serializeForm(form);
        fetchAndReplace(qs, pushUrl !== false);
      }, debounceMs);
    }

    inputs.forEach(function (el) {
      el.addEventListener('change', function () { submitDebounced(true); });
      el.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          if (timer) clearTimeout(timer);
          var qs = serializeForm(form);
          fetchAndReplace(qs, true);
        }
      });
    });

    var applyBtn = document.getElementById('btnFilter');
    if (applyBtn) {
      applyBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        var qs = serializeForm(form);
        fetchAndReplace(qs, true);
      });
    }

    var clearBtn = document.getElementById('btnClear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        // allow default navigation to /schedule/list.php
      });
    }

    window.addEventListener('popstate', function () {
      var qs = window.location.search ? window.location.search.substring(1) : '';
      fetchAndReplace(qs, false);
    });
  })();

  // Делегируем клик по документу (удаление / дублирование / переход в редактирование)
  document.addEventListener('click', function(e){
    // Дублирование (новая логика, без confirm())
    var dupBtn = e.target.closest('.js-duplicate-session');
    if (dupBtn) {
      e.preventDefault();
      e.stopPropagation();

      var id = dupBtn.getAttribute('data-id');
      if (!id) return;

      var eventTitle = dupBtn.getAttribute('data-event-title') || '';
      var modalText = eventTitle ? ('Дублировать сеанс "' + eventTitle + '" #' + id + '?') : ('Дублировать сеанс #' + id + '?');

      // Prefer a dedicated duplicate modal if available, otherwise reuse delete modal UI
      var usedModal = null;
      try {
        if (typeof window.showModalDuplicate === 'function') {
          window.showModalDuplicate(modalText, { external: true, id: id, title: 'Дублировать сеанс', eventTitle: eventTitle });
          usedModal = 'duplicate';
        } else if (typeof window.showModalDelete === 'function') {
          window.showModalDelete(modalText, { external: true, id: id, title: 'Дублировать сеанс', eventTitle: eventTitle });
          usedModal = 'delete';
        } else {
          // no modal functions available -> fallback immediately
          performDuplicate(id, dupBtn);
          return;
        }
      } catch (err) {
        // if modal call throws, fallback
        performDuplicate(id, dupBtn);
        return;
      }

      // determine which confirm button to wait for
      var confirmSelector = (usedModal === 'duplicate') ? '#modal-duplicate-confirm' : '#modal-delete-confirm';
      var hideModalFn = (usedModal === 'duplicate') ? (window.hideModalDuplicate || null) : (window.hideModalDelete || null);

      // wait for the confirm button to appear (modal may render asynchronously)
      waitForElement(confirmSelector, 1500).then(function(confirmBtn){
        if (!confirmBtn) {
          performDuplicate(id, dupBtn);
          return;
        }

        // remove previous handler if present
        if (confirmBtn._schedule_duplicate_handler) {
          confirmBtn.removeEventListener('click', confirmBtn._schedule_duplicate_handler);
          delete confirmBtn._schedule_duplicate_handler;
        }

        // small guard: ensure we don't react to the same click that opened the modal
        var handler = function handlerFn(evt){
          evt && evt.preventDefault && evt.preventDefault();

          // verify modal meta matches id (if available)
          var meta = (typeof window.getModalMeta === 'function') ? window.getModalMeta() : {};
          var metaId = meta && meta.id ? String(meta.id) : null;
          if (metaId && metaId !== String(id)) return;

          confirmBtn.disabled = true;
          var prevText = confirmBtn.textContent;
          confirmBtn.textContent = 'Копирование...';

          var fd = new URLSearchParams();
          fd.append('action', 'duplicate');
          fd.append('id', id);
          fd.append('csrf_token', window.APP_CSRF_TOKEN || '');

          fetch('/ajax/schedule.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd.toString()
          }).then(function(resp){
            return resp.json().catch(function(){ return { success:false, message:'Неверный ответ сервера' }; });
          }).then(function(json){
            confirmBtn.disabled = false;
            confirmBtn.textContent = prevText;
            try { if (typeof hideModalFn === 'function') hideModalFn(); } catch(e){}
            if (json && json.success && json.id) {
              showToast('Сеанс дублирован', 'success');
              // Перейти на редактирование нового сеанса
              window.location.href = '/schedule/edit.php?id=' + encodeURIComponent(json.id);
            } else {
              var msg = (json && json.message) ? json.message : 'Ошибка при дублировании';
              showToast(msg, 'error');
            }
          }).catch(function(err){
            confirmBtn.disabled = false;
            confirmBtn.textContent = prevText;
            try { if (typeof hideModalFn === 'function') hideModalFn(); } catch(e){}
            showToast('Ошибка сети при дублировании', 'error');
          }).finally(function(){
            try {
              confirmBtn.removeEventListener('click', handlerFn);
              delete confirmBtn._schedule_duplicate_handler;
            } catch (e){}
          });
        };

        // attach handler
        confirmBtn._schedule_duplicate_handler = handler;
        confirmBtn.addEventListener('click', handler);

      }).catch(function(){
        // timeout -> fallback
        performDuplicate(id, dupBtn);
      });

      return;
    }

    // Обработка кнопки удаления (поддерживаем класс .js-delete-session)
    var delBtn = e.target.closest('.js-delete-session');
    if (delBtn) {
      e.preventDefault();
      e.stopPropagation();

      var id = delBtn.getAttribute('data-id');
      if (!id) return;

      // Try to get event title from button or row data attribute
      var eventTitle = delBtn.getAttribute('data-event-title') || '';
      if (!eventTitle) {
        var row = delBtn.closest('tr[data-id]');
        if (row) eventTitle = row.getAttribute('data-event-title') || '';
      }

      var modalText = eventTitle ? ('Удалить сеанс "' + eventTitle + '" #' + id + '?') : ('Удалить сеанс #' + id + '?');

      try {
        window.showModalDelete(modalText, { external: true, id: id, title: 'Удалить сеанс', eventTitle: eventTitle });
      } catch (err) {
        if (!confirm(modalText)) return;
        performDelete(id);
        return;
      }

      var confirmBtn = document.getElementById('modal-delete-confirm');
      if (!confirmBtn) {
        performDelete(id);
        return;
      }

      if (confirmBtn._schedule_delete_handler) {
        confirmBtn.removeEventListener('click', confirmBtn._schedule_delete_handler);
        delete confirmBtn._schedule_delete_handler;
      }

      var handler = function handlerFn(evt){
        evt && evt.preventDefault && evt.preventDefault();

        var meta = (typeof window.getModalMeta === 'function') ? window.getModalMeta() : {};
        var metaId = meta && meta.id ? String(meta.id) : null;
        if (!metaId || metaId !== String(id)) {
          return;
        }

        confirmBtn.disabled = true;
        var prevText = confirmBtn.textContent;
        confirmBtn.textContent = 'Удаление...';

        var fd = new URLSearchParams();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', window.APP_CSRF_TOKEN || '');

        fetch('/ajax/schedule.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
          body: fd.toString()
        }).then(function(resp){
          return resp.json().catch(function(){ return { success:false, message:'Неверный ответ сервера' }; });
        }).then(function(json){
          confirmBtn.disabled = false;
          confirmBtn.textContent = prevText;
          try { window.hideModalDelete && window.hideModalDelete(); } catch(e){}
          if (json && json.success) {
            showToast('Сеанс удалён', 'success');
            var row = document.querySelector('tr[data-id="'+id+'"]');
            if (row) row.remove();
          } else {
            var msg = (json && json.message) ? json.message : 'Ошибка при удалении';
            showToast(msg, 'error');
          }
        }).catch(function(err){
          confirmBtn.disabled = false;
          confirmBtn.textContent = prevText;
          try { window.hideModalDelete && window.hideModalDelete(); } catch(e){}
          showToast('Ошибка сети при удалении', 'error');
        }).finally(function(){
          try {
            confirmBtn.removeEventListener('click', handlerFn);
            delete confirmBtn._schedule_delete_handler;
          } catch (e){}
        });
      };

      confirmBtn._schedule_delete_handler = handler;
      confirmBtn.addEventListener('click', handler);

      return;
    }

    // Клик по строке — переход в редактирование (игнорируем клики по интерактивным элементам)
    var row = e.target.closest('tr[data-id]');
    if (!row) return;
    var insideLink = e.target.closest('a, button, input, select, label');
    if (insideLink) return;
    var rowId = row.getAttribute('data-id');
    if (!rowId) return;
    window.location.href = '/schedule/edit.php?id=' + encodeURIComponent(rowId);
  });

  // fallback delete function (used if modal not available)
  function performDelete(id) {
    var fd = new URLSearchParams();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('csrf_token', window.APP_CSRF_TOKEN || '');

    fetch('/ajax/schedule.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: fd.toString()
    }).then(function(resp){
      return resp.json().catch(function(){ return { success:false, message:'Неверный ответ сервера' }; });
    }).then(function(json){
      if (json && json.success) {
        showToast('Сеанс удалён', 'success');
        var row = document.querySelector('tr[data-id="'+id+'"]');
        if (row) row.remove();
      } else {
        var msg = (json && json.message) ? json.message : 'Ошибка при удалении';
        showToast(msg, 'error');
      }
    }).catch(function(err){
      showToast('Ошибка сети при удалении', 'error');
    });
  }

  // fallback duplicate function (used if modal not available)
  function performDuplicate(id, triggerBtn) {
    if (!id) return;
    var btn = triggerBtn || null;
    var prevText;
    if (btn) {
      btn.disabled = true;
      prevText = btn.textContent;
      btn.textContent = 'Копирование...';
    }

    var fd = new URLSearchParams();
    fd.append('action', 'duplicate');
    fd.append('id', id);
    fd.append('csrf_token', window.APP_CSRF_TOKEN || '');

    fetch('/ajax/schedule.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: fd.toString()
    }).then(function(resp){
      return resp.json().catch(function(){ return { success:false, message:'Неверный ответ сервера' }; });
    }).then(function(json){
      if (json && json.success && json.id) {
        showToast('Сеанс дублирован', 'success');
        window.location.href = '/schedule/edit.php?id=' + encodeURIComponent(json.id);
      } else {
        var msg = (json && json.message) ? json.message : 'Ошибка при дублировании';
        showToast(msg, 'error');
      }
    }).catch(function(err){
      showToast('Ошибка сети при дублировании', 'error');
    }).finally(function(){
      if (btn) {
        btn.disabled = false;
        try { btn.textContent = prevText; } catch (e) {}
      }
    });
  }

})();
</script>
