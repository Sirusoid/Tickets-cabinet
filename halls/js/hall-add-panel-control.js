// public/halls/js/hall-add-panel-control.js
// Панель управления синхронизирована с SeatingCanvas API (drag/move/resize).
// Передаём в канвас deep clone layout, snapStep = 1/4 ширины места.

(function () {
  'use strict';

  function $id(id) { return document.getElementById(id); }
  function el(tag, attrs, html) { var d = document.createElement(tag); attrs = attrs || {}; Object.keys(attrs).forEach(function (k) { if (k === 'class') d.className = attrs[k]; else if (k === 'style') d.style.cssText = attrs[k]; else d.setAttribute(k, attrs[k]); }); if (html !== undefined) d.innerHTML = html; return d; }
  function nowSuffix() { return String(Date.now()).slice(-6); }
  function slugify(s) { return String(s || '').toLowerCase().trim().replace(/[^a-z0-9а-яё\s\-]/g, '').replace(/\s+/g, '_').replace(/_+/g, '_'); }
  function deepClone(obj) { try { return JSON.parse(JSON.stringify(obj)); } catch (e) { return obj; } }

  function showMessage(title, text) {
    try { if (typeof window.showModalDelete === 'function') { window.showModalDelete(text || title || ''); return Promise.resolve(true); } } catch (e) { }
    alert((title ? title + '\n\n' : '') + (text || ''));
    return Promise.resolve(true);
  }

  var state = {
    meta: { baseSeatSize: 28, gapX: 8, gapY: 12 },
    anchors: { layout_origin: { x: 0, y: 0, _px: { x: 80, y: 40 } } },
    rows: [],
    elements: [],
    seats: {}
  };

  var canvasId = 'seating-canvas';
  var inst = null;

  function getCanvas() {
    if (!inst && window.SeatingCanvas) {
      inst = SeatingCanvas.getInstance(canvasId) || SeatingCanvas.init(canvasId, { inspectorId: 'inspector-content', seatSize: state.meta.baseSeatSize, gapX: state.meta.gapX, gapY: state.meta.gapY, showGrid: true });
    }
    return inst;
  }

  function buildPanel(container) {
    container.innerHTML = '';
    var panel = el('div', { class: 'seating-panel' });

    panel.appendChild(el('h3', {}, 'Редактор зала'));

    var nameRow = el('div', { class: 'panel-row' });
    nameRow.appendChild(el('label', {}, 'Название'));
    nameRow.appendChild(el('input', { id: 'hall-name', type: 'text', placeholder: 'Название зала', style: 'flex:1;' }));
    panel.appendChild(nameRow);

    var baseRow = el('div', { class: 'panel-row' });
    baseRow.appendChild(el('label', {}, 'Размер места'));
    baseRow.appendChild(el('input', { id: 'meta-base', type: 'number', value: state.meta.baseSeatSize, style: 'width:120px;' }));
    panel.appendChild(baseRow);

    var gapRow = el('div', { class: 'panel-row' });
    gapRow.appendChild(el('label', {}, 'Отступ по X'));
    gapRow.appendChild(el('input', { id: 'meta-gapx', type: 'number', value: state.meta.gapX, style: 'width:120px;' }));
    panel.appendChild(gapRow);

    panel.appendChild(el('h4', {}, 'Ряды / сегменты'));

    var rowNumRow = el('div', { class: 'panel-row' });
    rowNumRow.appendChild(el('label', {}, 'Ряд'));
    rowNumRow.appendChild(el('input', { id: 'row-num', type: 'number', value: 1, style: 'width:80px;' }));
    panel.appendChild(rowNumRow);

    var segRangeRow = el('div', { class: 'panel-row' });
    segRangeRow.appendChild(el('label', {}, 'Номера мест'));
    var segRangeWrap = el('div', { style: 'display:flex; gap:8px; align-items:center;' });
    segRangeWrap.appendChild(el('input', { id: 'seg-start', type: 'number', value: 1, style: 'width:80px;' }));
    segRangeWrap.appendChild(el('span', {}, '—'));
    segRangeWrap.appendChild(el('input', { id: 'seg-end', type: 'number', value: 10, style: 'width:80px;' }));
    segRangeRow.appendChild(segRangeWrap);
    panel.appendChild(segRangeRow);

    var segActionsRow = el('div', { class: 'panel-row' });
    segActionsRow.appendChild(el('button', { id: 'btn-add-seg', class: 'btn small' }, 'Добавить сегмент'));
    segActionsRow.appendChild(el('button', { id: 'btn-toggle-grid-panel', class: 'btn small' }, 'Сетка'));
    panel.appendChild(segActionsRow);

    panel.appendChild(el('div', { id: 'rows-list', class: 'row-list' }));

    panel.appendChild(el('h4', {}, 'Объекты'));
    var eo = el('div', { class: 'panel-row' });
    eo.appendChild(el('label', {}, 'Тип'));
    var selType = el('select', { id: 'elem-type', style: 'width:120px;' });
    selType.innerHTML = '<option value="rect">Прямоуг.</option><option value="circle">Круг</option><option value="label">Надпись</option>';
    eo.appendChild(selType);
    eo.appendChild(el('input', { id: 'elem-text', type: 'text', placeholder: 'Текст', style: 'flex:1;' }));
    eo.appendChild(el('button', { id: 'btn-add-elem', class: 'btn small' }, 'Добавить'));
    panel.appendChild(eo);

    panel.appendChild(el('div', { id: 'elems-list', class: 'row-list' }));

    var actions = el('div', { class: 'panel-actions' });
    actions.appendChild(el('button', { id: 'btn-save-hall', class: 'btn' }, 'Сохранить схему'));
    actions.appendChild(el('button', { id: 'btn-panel-reset', class: 'btn' }, 'Сброс'));
    panel.appendChild(actions);

    container.appendChild(panel);

    bindEvents();
    renderLists();
  }

  function renderLists() {
    var rowsList = $id('rows-list');
    rowsList.innerHTML = '';
    state.rows.forEach(function (r, idx) {
      var segHtml = (r.segments || []).map(function (s, si) {
        return '<span class="seg-wrap" data-row="' + r.number + '" data-seg="' + si + '">' +
          '<span class="seg-ref" data-row="' + r.number + '" data-seg="' + si + '">[' + s.start + '-' + s.end + ']</span>' +
          '<button class="btn small seg-del" data-row="' + r.number + '" data-seg="' + si + '">Удалить сегмент</button>' +
          '</span>';
      }).join(' ');
      var div = el('div', { class: 'row-item' });
      div.innerHTML = '<div><strong>Ряд ' + r.number + '</strong><div style="margin-top:6px;">' + segHtml + '</div></div><div><button class="btn small row-del" data-idx="' + idx + '">Удалить</button></div>';
      rowsList.appendChild(div);
    });

    var elemsList = $id('elems-list');
    elemsList.innerHTML = '';
    state.elements.forEach(function (e, idx) {
      var label = (e.type === 'label' ? 'Надпись' : e.type === 'rect' ? 'Прямоуг.' : 'Круг') + (e.name ? ' — ' + e.name : '');
      var div = el('div', { class: 'row-item' });
      div.innerHTML = '<div>' + label + '</div><div><button class="btn small elem-del" data-idx="' + idx + '">Удалить</button></div>';
      elemsList.appendChild(div);
    });
  }

  function bindEvents() {
    $id('meta-base').addEventListener('input', function () { state.meta.baseSeatSize = Number(this.value) || 28; applyToCanvas(); });
    $id('meta-gapx').addEventListener('input', function () { state.meta.gapX = Number(this.value) || 8; applyToCanvas(); });

    $id('btn-add-seg').addEventListener('click', function (e) {
      e.preventDefault();
      var rn = Number($id('row-num').value) || 1;
      var st = Number($id('seg-start').value) || 1;
      var ed = Number($id('seg-end').value) || st;
      var row = state.rows.find(function (r) { return r.number === rn; });
      if (!row) { row = { number: rn, segments: [] }; state.rows.push(row); }
      var seg = { id: 'seg_' + nowSuffix(), start: st, end: ed, anchor: 'layout_origin', offset: { x: 0, y: 0 } };
      row.segments.push(seg);
      renderLists();
      applyToCanvas();
    });

    $id('btn-toggle-grid-panel').addEventListener('click', function () {
      var c = getCanvas();
      if (!c) return;
      var internal = c.getInternal ? c.getInternal() : (c._internal || null);
      if (internal) { internal.showGrid = !internal.showGrid; c.draw(); }
    });

    $id('rows-list').addEventListener('click', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.classList.contains('row-del')) {
        var idx = Number(t.getAttribute('data-idx'));
        if (isNaN(idx)) return;
        state.rows.splice(idx, 1);
        renderLists();
        applyToCanvas();
        return;
      }
      if (t.classList.contains('seg-del')) {
        var rowNum = Number(t.getAttribute('data-row'));
        var segIndex = Number(t.getAttribute('data-seg'));
        if (isNaN(rowNum) || isNaN(segIndex)) return;
        var rowIdx = state.rows.findIndex(function (r) { return r.number === rowNum; });
        if (rowIdx === -1) return;
        if (!Array.isArray(state.rows[rowIdx].segments)) return;
        if (segIndex >= 0 && segIndex < state.rows[rowIdx].segments.length) {
          state.rows[rowIdx].segments.splice(segIndex, 1);
          renderLists();
          applyToCanvas();
        }
        return;
      }
      if (t.classList.contains('seg-ref')) {
        var row = Number(t.getAttribute('data-row'));
        var segIndex = Number(t.getAttribute('data-seg'));
        var c = getCanvas();
        if (c) {
          c.selectSegment({ row: row, segIndex: segIndex });
          setTimeout(function () { syncSelectionFromCanvas(); scrollToSegment(row, segIndex); }, 50);
        }
        return;
      }
    });

    $id('btn-add-elem').addEventListener('click', function (e) {
      e.preventDefault();
      var type = $id('elem-type').value;
      var text = ($id('elem-text').value || '').trim() || (type === 'label' ? 'Надпись' : type === 'rect' ? 'Прямоуг.' : 'Круг');
      var anchorPx = state.anchors.layout_origin && state.anchors.layout_origin._px ? state.anchors.layout_origin._px : { x: 80, y: 40 };
      var obj = { id: 'el_' + nowSuffix(), type: type, name: text, text: text, x: anchorPx.x + 10, y: anchorPx.y + 10, width: (type === 'circle' ? 60 : 120), height: (type === 'circle' ? 60 : 40), fill: (type === 'label' ? 'transparent' : '#ffffff'), stroke: '#2f9b6f', textColor: '#0b3b4a' };
      if (type === 'circle') obj.radius = Math.round(Math.max(obj.width, obj.height) / 2);
      state.elements.push(obj);
      renderLists();
      applyToCanvas();
    });

    $id('elems-list').addEventListener('click', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.classList.contains('elem-del')) {
        var idx = Number(t.getAttribute('data-idx'));
        if (isNaN(idx)) return;
        state.elements.splice(idx, 1);
        renderLists();
        applyToCanvas();
      }
    });

    $id('btn-save-hall').addEventListener('click', function (e) {
      e.preventDefault();
      var name = ($id('hall-name').value || '').trim();
      if (!name) { showMessage('Ошибка', 'Введите название зала'); return; }
      var code = slugify(name) + '_' + nowSuffix();
      var rowsCount = state.rows.length || 0;
      var colsCount = 0;
      state.rows.forEach(function (r) { (r.segments || []).forEach(function (s) { colsCount = Math.max(colsCount, Number(s.end || 0)); }); });
      var layout = { meta: state.meta, anchors: state.anchors, rows: state.rows, elements: state.elements, seats: state.seats };
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
          if (json && json.success) { showMessage('Зал сохранён', 'Зал сохранён. ID: ' + (json.data && json.data.id ? json.data.id : '—')); if (json.data && json.data.id) window.location.href = '/halls/edit.php?id=' + encodeURIComponent(json.data.id); }
          else showMessage('Ошибка', (json && json.message) ? json.message : 'Ошибка на сервере');
        })
        .catch(function (err) { console.error(err); showMessage('Ошибка', 'Сетевая ошибка при сохранении'); });
    });

    $id('btn-panel-reset').addEventListener('click', function (e) {
      e.preventDefault();
      state = { meta: { baseSeatSize: 28, gapX: 8, gapY: 12 }, anchors: { layout_origin: { x: 0, y: 0, _px: { x: 80, y: 40 } } }, rows: [], elements: [], seats: {} };
      $id('hall-name').value = '';
      $id('meta-base').value = state.meta.baseSeatSize;
      $id('meta-gapx').value = state.meta.gapX;
      renderLists();
      applyToCanvas();
    });
  }

  function applyToCanvas() {
    var c = getCanvas();
    if (!c) return;
    state.meta.baseSeatSize = Number(state.meta.baseSeatSize) || 28;
    state.meta.gapX = Number(state.meta.gapX) || 8;
    var layout = { meta: state.meta, anchors: state.anchors, rows: state.rows, elements: state.elements, seats: state.seats };
    c.setLayout(deepClone(layout));
    var internal = c.getInternal ? c.getInternal() : (c._internal || null);
    if (internal) {
      internal.seatSize = state.meta.baseSeatSize;
      internal.gapX = state.meta.gapX;
      internal.gapY = state.meta.gapY;
      // snapStep for element moves = quarter of seat size (logical units)
      internal.snapStep = Math.max(1, Math.round(internal.seatSize / 4));
    }
  }

  function scrollToSegment(row, segIndex) {
    var rowsList = $id('rows-list');
    if (!rowsList) return;
    var sel = rowsList.querySelector('.seg-wrap[data-row="' + row + '"][data-seg="' + segIndex + '"]');
    if (sel) {
      var top = sel.offsetTop;
      var h = rowsList.clientHeight;
      if (top < rowsList.scrollTop) rowsList.scrollTop = top - 8;
      else if (top + sel.offsetHeight > rowsList.scrollTop + h) rowsList.scrollTop = top - h / 2;
      sel.style.transition = 'background 0.3s';
      sel.style.background = '#fff2e6';
      setTimeout(function () { sel.style.background = ''; }, 900);
    }
  }

  function syncSelectionFromCanvas() {
    var c = getCanvas();
    if (!c) return;
    var internal = c.getInternal ? c.getInternal() : (c._internal || null);
    var selectedSegs = internal && internal.selectedSegments ? internal.selectedSegments : [];
    var segEls = document.querySelectorAll('#rows-list .seg-ref');
    segEls.forEach(function (el) {
      var row = Number(el.getAttribute('data-row'));
      var segIndex = Number(el.getAttribute('data-seg'));
      var found = selectedSegs.find(function (s) { return s.row === row && s.segIndex === segIndex; });
      el.style.background = found ? '#fff2e6' : 'transparent';
    });
    if (selectedSegs && selectedSegs.length) scrollToSegment(selectedSegs[0].row, selectedSegs[0].segIndex);
  }

  function enableSync() {
    var c = getCanvas();
    if (!c) return;
    c.on && c.on('layout:changed', function (layout) {
      state.rows = layout.rows || [];
      state.elements = layout.elements || [];
      renderLists();
      syncSelectionFromCanvas();
    });
    c.on && c.on('segments:selected', function (sel) { syncSelectionFromCanvas(); });
    c.on && c.on('elements:selected', function (sel) { /* optional */ });
  }

  function loadLayoutToPanel(layout) {
    if (!layout) return;
    state.meta = layout.meta || state.meta;
    state.anchors = layout.anchors || state.anchors;
    state.rows = layout.rows || state.rows;
    state.elements = layout.elements || state.elements;
    state.seats = layout.seats || state.seats;
    var container = $id('hall-editor-container');
    if (container) { container.innerHTML = ''; buildPanel(container); }
    applyToCanvas();
    setTimeout(enableSync, 150);
  }

  function init() {
    var container = $id('hall-editor-container');
    if (!container) return;
    buildPanel(container);
    getCanvas();
    applyToCanvas();
    enableSync();
  }

  window.HallAddPanel = { loadLayoutToPanel: loadLayoutToPanel };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

})();
