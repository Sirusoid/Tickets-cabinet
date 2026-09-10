<?php
// public/halls/edit.php
require_once __DIR__ . '/../init.php';
require_login();

// Expect ?id=... in query string
$hall_id = isset($_GET['id']) ? trim($_GET['id']) : '';
$hall_id_esc = htmlspecialchars($hall_id, ENT_QUOTES, 'UTF-8');

$page_scripts = $page_scripts ?? [];
$page_scripts[] = 'assets/js/seating-canvas.js';
$page_scripts[] = 'halls/js/hall-add-panel-control.js';

$use_sidebar = true;
$active_menu = 'halls';
$page_title_meta = 'Редактировать зал - Админка';
$panel_title = 'Редактировать зал';
$panel_subtitle = 'Редактирование схемы зала: загрузите существующую схему, вносите правки, сохраняйте изменения.';
$panel_actions = [
    ['href'=>'/halls/list.php','label'=>'К списку залов','class'=>'btn btn-primary btn-sm']
];

$page_title = 'Редактировать зал';
$csrf_token = $_SESSION['csrf_token'] ?? '';

$headerPath = __DIR__ . '/../includes/header.php';
$panelPath = __DIR__ . '/../includes/panel.php';
if (file_exists($headerPath)) require_once $headerPath;
if (file_exists($panelPath)) require_once $panelPath;
?>

<link rel="stylesheet" href="/assets/css/seating.css">

<main id="admin-main" role="main" aria-label="Редактировать зал">
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
  <input type="hidden" name="action" value="update">
  <input type="hidden" name="id" id="form-id" value="<?php echo $hall_id_esc; ?>">
  <input type="hidden" name="code" id="form-code" value="">
  <input type="hidden" name="name" id="form-name" value="">
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

  var hallId = '<?php echo $hall_id_esc; ?>';
  var apiEndpoint = '/ajax/hall.php';
  var currentHallCode = '';
  var currentHallName = '';

  function showToastMessage(msg, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(msg, type || 'success');
    } else if (typeof window.appAlert === 'function') {
      window.appAlert(msg);
    } else {
      alert(msg);
    }
  }

  // ask footer modal confirm using modal-delete buttons
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

          if (!btnConfirm && !btnCancel) {
            showToastMessage('Кнопки подтверждения в модалке не найдены', 'error');
            try { if (typeof window.hideModalDelete === 'function') window.hideModalDelete(); } catch (e) {}
            return resolve(false);
          }
          return;
        }
      } catch (e) {}
      showToastMessage('Модалка подтверждения недоступна', 'error');
      resolve(false);
    });
  }

  function fetchHall(id) {
    if (!id) return Promise.reject(new Error('ID не указан'));
    var url = apiEndpoint + '?action=get&id=' + encodeURIComponent(id);
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function initCanvasAndPanel() {
    if (window.SeatingCanvas && !SeatingCanvas.getInstance('seating-canvas')) {
      SeatingCanvas.init('seating-canvas', { inspectorId: 'inspector-content', seatSize: 28, gapX: 8, gapY: 12, debug: false });
    }
  }

  function loadLayoutToEditor(layout, meta) {
    if (!window.HallAddPanel || !window.SeatingCanvas) return;
    var parsed = layout;
    if (typeof layout === 'string') {
      try { parsed = JSON.parse(layout); } catch (e) { parsed = {}; }
    }
    if (!parsed) parsed = {};
    if (meta && typeof meta === 'object') parsed.meta = Object.assign({}, parsed.meta || {}, meta);
    if (!parsed.name && currentHallName) parsed.name = currentHallName;
    HallAddPanel.loadLayoutToPanel(parsed);
    var nameInput = document.getElementById('hall-name');
    if (nameInput && parsed.name) nameInput.value = parsed.name;
  }

  function overrideSaveBehavior() {
    var origBtn = document.getElementById('btn-save-hall');
    if (!origBtn) return;
    var newBtn = origBtn.cloneNode(true);
    origBtn.parentNode.replaceChild(newBtn, origBtn);

    newBtn.addEventListener('click', function (e) {
      e.preventDefault();

      askFooterModalConfirm('Сохранить изменения?').then(function (confirmed) {
        if (!confirmed) return;

        var canvasInst = SeatingCanvas.getInstance('seating-canvas');
        if (!canvasInst) {
          showToastMessage('Ошибка: канвас не инициализирован', 'error');
          return;
        }

        var layout = canvasInst.exportLayout() || {};

        var nameEl = document.getElementById('hall-name');
        var name = (nameEl && nameEl.value && nameEl.value.trim()) ? nameEl.value.trim() : (layout && layout.name ? String(layout.name).trim() : (currentHallName || ''));
        if (!name && layout && layout.meta && layout.meta.name) name = String(layout.meta.name).trim();

        if (!name) {
          showToastMessage('Укажите название зала', 'error');
          return;
        }

        var codeEl = document.getElementById('form-code');
        var code = (codeEl && codeEl.value && codeEl.value.trim()) ? codeEl.value.trim() : currentHallCode || '';
        if (!code) code = slugify(name) + '_' + nowSuffix();

        var rowsCount = (layout.rows && layout.rows.length) || 0;
        var colsCount = 0;
        (layout.rows || []).forEach(function (r) { (r.segments || []).forEach(function (s) { colsCount = Math.max(colsCount, Number(s.end || 0)); }); });

        var form = new FormData();
        form.append('action', 'update');
        if (hallId) form.append('id', hallId);
        form.append('code', code);
        form.append('name', name);
        form.append('rows_count', rowsCount);
        form.append('cols_count', colsCount);
        form.append('seat_map', JSON.stringify(layout));
        var csrfEl = document.querySelector('input[name="csrf_token"]');
        if (csrfEl) form.append('csrf_token', csrfEl.value);

        fetch(apiEndpoint, { method: 'POST', credentials: 'same-origin', body: form })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (json && json.success) {
              if (json.data && json.data.code) {
                currentHallCode = String(json.data.code);
                var codeField = document.getElementById('form-code');
                if (codeField) codeField.value = currentHallCode;
              }
              if (json.data && json.data.id) {
                hallId = String(json.data.id);
                var idField = document.getElementById('form-id');
                if (idField) idField.value = hallId;
              }
              showToastMessage('Изменения сохранены', 'success');
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

  document.addEventListener('DOMContentLoaded', function () {
    initCanvasAndPanel();

    if (!hallId) {
      overrideSaveBehavior();
      return;
    }

    fetchHall(hallId)
      .then(function (json) {
        if (!json) { showToastMessage('Пустой ответ сервера', 'error'); return; }
        if (!json.success) { showToastMessage(json.message || 'Не удалось загрузить зал', 'error'); return; }
        var data = json.data || {};
        var seatMap = data.seat_map || data.layout || data.map || null;
        if (typeof seatMap === 'string') {
          try { seatMap = JSON.parse(seatMap); } catch (e) { seatMap = null; }
        }
        currentHallCode = data.code || (seatMap && seatMap.code) || '';
        currentHallName = data.name || (seatMap && seatMap.name) || currentHallName || '';
        var codeField = document.getElementById('form-code');
        if (codeField) codeField.value = currentHallCode;
        if (seatMap && typeof seatMap === 'object') seatMap.name = data.name || seatMap.name || '';
        loadLayoutToEditor(seatMap, data.meta || null);
      })
      .catch(function (err) {
        console.error(err);
        showToastMessage('Сетевая ошибка при загрузке зала', 'error');
      })
      .finally(function () {
        overrideSaveBehavior();
      });
  });
})();
</script>

<?php
$footerPath = __DIR__ . '/../includes/footer.php';
if (file_exists($footerPath)) require_once $footerPath;
?>
