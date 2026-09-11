<?php
// events/list.php
require_once __DIR__ . '/../init.php';

// Включаем sidebar для этой страницы и подсвечиваем пункт 'events'
$use_sidebar = true;
$active_menu = 'events';

// Метаданные для <title> (не отображаются в панели)
$page_title_meta = 'События — админка';
$page_styles = $page_styles ?? [];
$page_scripts = $page_scripts ?? [];

// Не добавляем глобальную кнопку в header
$page_actions = [];

// Локальные значения для панели (вставляются только в панели через includes/panel.php)
$panel_title = 'Спектакли';
$panel_subtitle = 'Список всех спектаклей';
$panel_actions = [
    ['href' => '/events/add.php', 'label' => 'Создать спектакль', 'class' => 'btn btn-primary btn-sm']
];

require __DIR__ . '/../includes/header.php';

// Хелперы
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

// Получаем данные для таблицы (добавлен join по языкам)
$sql = "
    SELECT
        e.id,
        e.title,
        ec.name AS category,
        e.duration_minutes,
        e.age_limit,
        e.image,
        e.created_at,
        e.status AS status_code,
        COALESCE(es.label, e.status) AS status_label,
        es.color_hex AS status_color,
        el.label AS language_label
    FROM events e
    LEFT JOIN event_categories ec ON ec.id = e.category_id
    LEFT JOIN event_statuses es ON es.code = e.status
    LEFT JOIN event_languages el ON el.id = e.language_id
    ORDER BY e.title ASC
    LIMIT 500
";

$events = [];
try {
    $events = db_fetch_all($sql) ?: [];
} catch (Throwable $e) {
    error_log('events/list.php SQL error: ' . $e->getMessage());
    $events = [];
}
?>

<?php
// Подключаем панель заголовка (локальная, подключаемая)
require_once __DIR__ . '/../includes/panel.php';
?>

<!-- Панель таблицы (контейнер) -->
<div class="panel panel--content">
  <div class="panel__inner">
    <div class="table-container events-list">
      <table class="table admin-table table--compact" aria-describedby="events-table">
        <thead>
          <tr>
            <th>#</th>
            <th class="col-poster">Афиша</th>
            <th class="col-title">Название</th>
            <th class="col-category">Категория</th>
            <th class="col-small">Длительность</th>
            <th class="col-small">Язык</th>
            <th class="col-small">Возраст</th>
            <th class="col-small">Статус</th>
            <th class="actions-col">Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($events as $ev): ?>
            <tr class="event-row" data-id="<?= h($ev['id']) ?>">
              <td><?= $i++ ?></td>
              <td class="col-poster">
                <?php if (!empty($ev['image'])): ?>
                  <img src="<?= h($ev['image']) ?>" alt="Афиша" class="thumb">
                <?php else: ?> —
                <?php endif; ?>
              </td>
              <td class="col-title"><span class="truncate"><?= h($ev['title']) ?></span></td>
              <td class="col-category"><?= h($ev['category'] ?? '-') ?></td>
              <td class="col-small"><?= h($ev['duration_minutes'] ?? '-') ?> мин</td>
              <td class="col-small"><?= h($ev['language_label'] ?? '-') ?></td>
              <td class="col-small"><?= h($ev['age_limit'] ?? '-') ?>+</td>
              <td class="col-small">
                  <?php
                  $status_label_local = $ev['status_label'] ?? ($ev['status_code'] ?? '');
                  $rawColorLocal = $ev['status_color'] ?? null;
                  $status_style_attr = '';
                  if ($rawColorLocal !== null) {
                      $colorLocal = normalize_hex_color($rawColorLocal);
                      if ($colorLocal !== null) {
                          $textColorLocal = contrast_text_color($colorLocal);
                          $status_style_attr = ' style="background:'.h($colorLocal).'; color:'.h($textColorLocal).'; border-color:'.h($colorLocal).';"';
                      }
                  }
                ?>
                <span class="badge"<?= $status_style_attr ?>><?= h($status_label_local) ?></span>
              </td>
              <td class="actions actions--center">
                <a href="/events/edit.php?id=<?= h($ev['id']) ?>" class="btn btn-ghost btn-sm" title="Редактировать">Редактировать</a>
                <button class="btn btn-danger btn-sm js-delete-event" data-id="<?= h($ev['id']) ?>" title="Удалить">Удалить</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div> <!-- .table-container -->
  </div>
</div>

<!-- Скрипт: обработка удаления мероприятия -->
<script>
(function(){
    'use strict';

    var CSRF_TOKEN = '<?= h($_SESSION['csrf_token'] ?? '') ?>';
    var JQUERY_CDN = 'https://code.jquery.com/jquery-3.6.0.min.js';

    function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = function(){ cb(null); };
        s.onerror = function(){ cb(new Error('Failed to load ' + src)); };
        document.head.appendChild(s);
    }

    function runListScript($) {

        function ensureConfirmModal() {
            if (window.showModalDelete && window.hideModalDelete && document.getElementById('modal-delete')) {
                return;
            }

            if (!document.getElementById('modal-delete')) {
                var fallback = document.createElement('div');
                fallback.id = 'modal-delete';
                fallback.style.position = 'fixed';
                fallback.style.inset = '0';
                fallback.style.display = 'none';
                fallback.style.alignItems = 'center';
                fallback.style.justifyContent = 'center';
                fallback.style.background = 'rgba(0,0,0,0.5)';
                fallback.style.zIndex = '99999';
                fallback.innerHTML = '\
                    <div id="modal-delete-box" style="background:#fff;padding:20px;border-radius:6px;max-width:420px;width:90%;box-shadow:0 6px 24px rgba(0,0,0,.2);">\
                        <div id="modal-delete-title" style="font-weight:600;margin-bottom:8px;">Подтвердите действие</div>\
                        <div id="modal-delete-text" style="margin-bottom:16px;color:#333;"></div>\
                        <div style="text-align:right;">\
                            <button id="modal-delete-cancel" class="btn btn-ghost" style="margin-right:8px;">Отмена</button>\
                            <button id="modal-delete-confirm" class="btn btn-danger">Да</button>\
                        </div>\
                    </div>';
                document.body.appendChild(fallback);
            }

            window.showModalDelete = function() {
                var el = document.getElementById('modal-delete');
                if (!el) return;
                el.style.display = 'flex';
            };
            window.hideModalDelete = function() {
                var el = document.getElementById('modal-delete');
                if (!el) return;
                el.style.display = 'none';
            };
        }

        function showConfirm(title, text, onConfirm) {
            ensureConfirmModal();

            var titleEl = document.querySelector('#modal-delete-box > div:first-child') || document.getElementById('modal-delete-title');
            var textEl = document.getElementById('modal-delete-text');
            var confirmBtn = document.getElementById('modal-delete-confirm');
            var cancelBtn = document.getElementById('modal-delete-cancel');

            if (titleEl) titleEl.textContent = title || 'Подтвердите действие';
            if (textEl) textEl.textContent = text || '';

            if (confirmBtn) confirmBtn.onclick = null;
            if (cancelBtn) cancelBtn.onclick = null;

            if (confirmBtn) {
                confirmBtn.onclick = function(){
                    hideModalDelete();
                    if (typeof onConfirm === 'function') onConfirm();
                };
            }
            if (cancelBtn) {
                cancelBtn.onclick = function(){
                    hideModalDelete();
                };
            }

            if (typeof showModalDelete === 'function') showModalDelete();
        }

        function showToast(message, type, timeout, cb) {
            timeout = timeout || 2000;
            if (typeof window.showToast === 'function') {
                try { window.showToast(message, type, timeout); if (cb) setTimeout(cb, timeout); return; } catch(e) {}
            }
            var container = document.getElementById('app-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'app-toast-container';
                container.style.position = 'fixed';
                container.style.right = '20px';
                container.style.bottom = '20px';
                container.style.zIndex = '100000';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.gap = '8px';
                document.body.appendChild(container);
            }
            var t = document.createElement('div');
            t.textContent = message || '';
            t.style.background = (type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#6c757d'));
            t.style.color = '#fff';
            t.style.padding = '8px 12px';
            t.style.borderRadius = '6px';
            t.style.boxShadow = '0 6px 18px rgba(0,0,0,.12)';
            container.appendChild(t);
            var timer = setTimeout(function(){
                try { container.removeChild(t); } catch(e){}
                if (cb) cb();
            }, timeout);
            t.addEventListener('click', function(){
                clearTimeout(timer);
                try { container.removeChild(t); } catch(e){}
                if (cb) cb();
            });
        }

        $(document).on('click', '.js-delete-event', function(e){
            e.preventDefault();
            var btn = $(this);
            var id = btn.data('id');
            if (!id) return;

            showConfirm('Удалить мероприятие?', 'Вы уверены, что хотите удалить это мероприятие? Действие необратимо.', function(){
                $.ajax({
                    url: '/ajax/event.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete',
                        id: id,
                        csrf_token: CSRF_TOKEN
                    }
                }).done(function(res){
                    if (res && res.success) {
                        var row = btn.closest('tr.event-row');
                        row.fadeOut(200, function(){ $(this).remove(); });
                        showToast('Удалено', 'success', 900);
                    } else {
                        var msg = (res && res.message) ? res.message : 'Ошибка при удалении';
                        alert(msg);
                    }
                }).fail(function(xhr){
                    var msg = 'Ошибка сети при удалении';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        msg = json.message || msg;
                    } catch (e) {}
                    alert(msg);
                });
            });
        });

    } // end runListScript

    (function ensureJQueryAndRun(){
        if (window.jQuery) {
            runListScript(window.jQuery);
            return;
        }

        loadScript(JQUERY_CDN, function(err){
            if (err) {
                return;
            }
            runListScript(window.jQuery);
        });
    })();

})();
</script>

<?php
require __DIR__ . '/../includes/footer.php';
