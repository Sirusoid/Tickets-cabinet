<?php
// public/halls/add.php
require_once __DIR__ . '/../init.php';
require_login();

$page_scripts = $page_scripts ?? [];
$page_scripts[] = 'assets/js/seating-canvas.js';
$page_scripts[] = 'halls/js/hall-add-panel-control.js';

$use_sidebar = true;
$active_menu = 'halls';
$page_title_meta = 'Создать зал - Админка';
$panel_title = 'Создать зал';
$panel_subtitle = 'Редактор схемы зала: добавляйте ряды, сегменты и объекты. Перетаскивайте ряды мышью по канвасу.';
$panel_actions = [
    ['href'=>'/halls/list.php','label'=>'К списку залов','class'=>'btn btn-primary btn-sm']
];

$page_title = 'Создать зал';
$csrf_token = $_SESSION['csrf_token'] ?? '';

$headerPath = __DIR__ . '/../includes/header.php';
$panelPath = __DIR__ . '/../includes/panel.php';
if (file_exists($headerPath)) require_once $headerPath;
if (file_exists($panelPath)) require_once $panelPath;
?>

<link rel="stylesheet" href="/assets/css/seating.css">

<main id="admin-main" role="main" aria-label="Создать зал">
  <section class="canvas-workshop" aria-label="Редактор зала">
    <div class="canvas-layout">
      <div id="hall-editor-container" aria-label="Панель управления"></div>

      <div id="canvas-area">
        <div id="canvas-container">
          <canvas id="seating-canvas" width="1200" height="700" aria-label="Канвас схемы рассадки"></canvas>
        </div>
      </div>
    </div>
  </section>
</main>

<form id="hall-save-form" style="display:none;">
  <input type="hidden" name="action" value="create">
  <input type="hidden" name="name" id="form-name" value="">
  <input type="hidden" name="code" id="form-code" value="">
  <input type="hidden" name="rows_count" id="form-rows" value="0">
  <input type="hidden" name="cols_count" id="form-cols" value="0">
  <input type="hidden" name="seat_map" id="form-seatmap" value="">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
</form>

<script src="/assets/js/seating-canvas.js"></script>
<script src="/halls/js/hall-add-panel-control.js"></script>

<script>
(function () {
  'use strict';

  function nowSuffix() { return String(Date.now()).slice(-6); }
  function slugify(s) { return String(s || '').toLowerCase().trim().replace(/[^a-z0-9а-яё\s\-]/g, '').replace(/\s+/g, '_').replace(/_+/g, '_'); }

  function showToastMessage(msg, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(msg, type || 'success');
    } else if (typeof window.appAlert === 'function') {
      window.appAlert(msg);
    } else {
      alert(msg);
    }
  }

  // Показываем футерную модалку и ждём клика по кнопкам с id modal-delete-confirm / modal-delete-cancel
  // Возвращает Promise<boolean>
  function askFooterModalConfirm(text) {
    return new Promise(function (resolve) {
      try {
        if (typeof window.showModalDelete === 'function') {
          window.showModalDelete(text || 'Подтвердите действие', {});
          var wrap = document.getElementById('modal-delete');
          if (!wrap) {
            showToastMessage('Модалка подтверждения не найдена', 'error');
            return resolve(false);
          }
          var btnConfirm = document.getElementById('modal-delete-confirm');
          var btnCancel = document.getElementById('modal-delete-cancel');

          var cleanup = function () {
            try {
              if (btnConfirm) btnConfirm.removeEventListener('click', onConfirm);
              if (btnCancel) btnCancel.removeEventListener('click', onCancel);
            } catch (e) {}
            try { if (typeof window.hideModalDelete === 'function') window.hideModalDelete(); } catch (e) {}
          };

          var onConfirm = function (ev) { ev && ev.preventDefault && ev.preventDefault(); cleanup(); resolve(true); };
          var onCancel = function (ev) { ev && ev.preventDefault && ev.preventDefault(); cleanup(); resolve(false); };

          if (btnConfirm) btnConfirm.addEventListener('click', onConfirm);
          if (btnCancel) btnCancel.addEventListener('click', onCancel);

          // Если ни одной кнопки нет — не продолжаем (без native confirm)
          if (!btnConfirm && !btnCancel) {
            showToastMessage('Кнопки подтверждения в модалке не найдены', 'error');
            try { if (typeof window.hideModalDelete === 'function') window.hideModalDelete(); } catch (e) {}
            return resolve(false);
          }
          return;
        }
      } catch (e) {
        // ignore
      }
      showToastMessage('Модалка подтверждения недоступна', 'error');
      resolve(false);
    });
  }

  function init() {
    if (window.SeatingCanvas && !SeatingCanvas.getInstance('seating-canvas')) {
      SeatingCanvas.init('seating-canvas', { inspectorId: 'inspector-content', seatSize: 28, gapX: 8, gapY: 12, debug: false });
    }

    var origBtn = document.getElementById('btn-save-hall');
    if (!origBtn) return;
    var newBtn = origBtn.cloneNode(true);
    origBtn.parentNode.replaceChild(newBtn, origBtn);

    newBtn.addEventListener('click', function (e) {
      e.preventDefault();

      askFooterModalConfirm('Сохранить новый зал?').then(function (confirmed) {
        if (!confirmed) return;

        var canvasInst = SeatingCanvas.getInstance('seating-canvas');
        if (!canvasInst) {
          showToastMessage('Ошибка: канвас не инициализирован', 'error');
          return;
        }

        var layout = canvasInst.exportLayout() || {};

        var nameEl = document.getElementById('hall-name');
        var name = (nameEl && nameEl.value && nameEl.value.trim()) ? nameEl.value.trim() : (layout && layout.name ? String(layout.name).trim() : '');
        if (!name && layout && layout.meta && layout.meta.name) name = String(layout.meta.name).trim();

        if (!name) {
          showToastMessage('Укажите название зала', 'error');
          return;
        }

        var codeEl = document.getElementById('form-code');
        var code = (codeEl && codeEl.value && codeEl.value.trim()) ? codeEl.value.trim() : '';
        if (!code) code = slugify(name) + '_' + nowSuffix();

        var rowsCount = (layout.rows && layout.rows.length) || 0;
        var colsCount = 0;
        (layout.rows || []).forEach(function (r) { (r.segments || []).forEach(function (s) { colsCount = Math.max(colsCount, Number(s.end || 0)); }); });

        var form = new FormData();
        form.append('action', 'create');
        form.append('name', name);
        form.append('code', code);
        form.append('rows_count', rowsCount);
        form.append('cols_count', colsCount);
        form.append('seat_map', JSON.stringify(layout));
        var csrfEl = document.querySelector('input[name="csrf_token"]');
        if (csrfEl) form.append('csrf_token', csrfEl.value);

        fetch('/ajax/hall.php', { method: 'POST', credentials: 'same-origin', body: form })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (json && json.success) {
              showToastMessage('Зал успешно создан', 'success');
              setTimeout(function () { window.location.href = '/halls/list.php'; }, 600);
            } else {
              var msg = (json && json.message) ? json.message : 'Ошибка на сервере при сохранении';
              showToastMessage(msg, 'error');
            }
          })
          .catch(function (err) {
            console.error(err);
            showToastMessage('Сетевая ошибка при сохранении', 'error');
          });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>

<?php
$footerPath = __DIR__ . '/../includes/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>
