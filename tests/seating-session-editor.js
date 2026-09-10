// assets/js/seating-session-editor.js
// Session editor: uses SeatingCanvas (editor) to render a hall layout in view-only mode,
// provides selection tools (select/rect), overlay highlighting, price assignment/unassignment,
// groups management (synchronized with hidden input price_ranges) and required API methods.
//
// Exposes global SeatingSessionEditor with methods:
// init(options) -> instance
// instance methods: setTool, clearSelection, getSelectedSeatsCount, assignPriceToSelection, unassignPriceFromSelection, applyPriceRanges, fitContentToView, getSeatingCanvasInstance

(function (global) {
  'use strict';

  var SeatingSessionEditor = (function () {
    var instance = null;

    function createInstance(opts) {
      opts = opts || {};
      var canvasId = opts.canvasId || 'editor-canvas';
      var canvasEl = document.getElementById(canvasId);
      if (!canvasEl) {
        console.warn('SeatingSessionEditor: canvas not found', canvasId);
        return null;
      }

      // Ensure SeatingCanvas exists
      if (!global.SeatingCanvas || typeof global.SeatingCanvas.init !== 'function') {
        console.warn('SeatingSessionEditor: SeatingCanvas not available');
        return null;
      }

      // Initialize or get existing SeatingCanvas instance
      var sc = global.SeatingCanvas.getInstance(canvasId);
      if (!sc) sc = global.SeatingCanvas.init(canvasId, opts.seatingOptions || { seatSize: 28, gapX: 8, gapY: 12, showGrid: true });

      // For session editor we want the canvas to be view-only (no row/element editing).
      // We rely on SeatingCanvas being the same instance, but we will not use its editing events.
      var internal = {
        sc: sc,
        canvas: canvasEl,
        wrapper: canvasEl.parentElement || document.body,
        tool: 'select',
        selection: [],
        rectSelecting: false,
        dragRect: null,
        selectedCountEl: opts.selectedCountId ? document.getElementById(opts.selectedCountId) : null,
        priceGroupsTbody: document.getElementById('priceGroupsTbody'),
        priceRangesField: document.querySelector('input[name="price_ranges"]') || document.getElementById('price_ranges') || document.getElementById('session_price_ranges'),
        // small event bus
        bus: { on: function(){}, off: function(){} }
      };

      // Helpers to read computed seats from SeatingCanvas
      function getComputed() {
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        return scInternal && scInternal._computed ? scInternal._computed : null;
      }

      // Price groups storage helpers
      function loadGroupsFromField() {
        var field = internal.priceRangesField;
        if (!field) return [];
        var val = field.value || '';
        if (!val) return [];
        try {
          var parsed = JSON.parse(val);
          if (Array.isArray(parsed)) return parsed;
        } catch (e) {}
        return [];
      }
      function saveGroupsToField(groups) {
        var field = internal.priceRangesField;
        if (!field) return;
        try { field.value = JSON.stringify(groups); } catch (e) { field.value = ''; }
      }

      // Render groups table
      function renderPriceGroupsTable() {
        var tbody = internal.priceGroupsTbody;
        if (!tbody) return;
        tbody.innerHTML = '';
        var groups = loadGroupsFromField();
        groups.forEach(function (g) {
          var tr = document.createElement('tr');
          tr.dataset.groupId = g.id || '';
          tr.innerHTML = '<td><input class="pg-price form-control" value="' + (g.price || '') + '" /></td>' +
            '<td><input class="pg-color form-control" type="color" value="' + (g.color || '#cccccc') + '" /></td>' +
            '<td style="display:flex; gap:8px; align-items:center;">' +
              '<input class="pg-label form-control" value="' + (g.label || '') + '" placeholder="Название" style="flex:1;" />' +
              '<button type="button" class="btn btn-ghost btn-sm js-pg-apply">Применить</button>' +
              '<button type="button" class="btn btn-ghost btn-sm js-pg-save">Сохранить</button>' +
              '<button type="button" class="btn btn-danger btn-sm js-pg-remove">Удалить</button>' +
            '</td>';
          tbody.appendChild(tr);
        });
      }

      function addPriceGroup(group) {
        var groups = loadGroupsFromField();
        group = group || {};
        if (!group.id) group.id = 'g' + Date.now() + Math.floor(Math.random() * 1000);
        groups.push({ id: group.id, price: group.price || '', color: group.color || '#cccccc', label: group.label || '' });
        saveGroupsToField(groups);
        renderPriceGroupsTable();
      }
      function removePriceGroupById(id) {
        var groups = loadGroupsFromField();
        groups = groups.filter(function (g) { return g.id !== id; });
        saveGroupsToField(groups);
        renderPriceGroupsTable();
      }
      function updatePriceGroupById(id, data) {
        var groups = loadGroupsFromField();
        groups = groups.map(function (g) { if (g.id !== id) return g; return Object.assign({}, g, data); });
        saveGroupsToField(groups);
        renderPriceGroupsTable();
      }
      function findGroupByPriceColor(price, color) {
        var groups = loadGroupsFromField();
        return groups.find(function (g) { return String(g.price) === String(price) && String((g.color || '')).toLowerCase() === String((color || '')).toLowerCase(); });
      }

      // Selection helpers
      function clearSelection() { internal.selection = []; renderOverlay(); emitSelectionChanged(); }
      function getSelectedSeatsCount() { return internal.selection.length; }

      function findSeatAtClient(clientX, clientY) {
        var computed = getComputed();
        if (!computed || !computed.seats) return null;
        var rect = internal.canvas.getBoundingClientRect();
        var x = clientX - rect.left;
        var y = clientY - rect.top;
        var found = null;
        Object.keys(computed.seats).forEach(function (k) {
          var s = computed.seats[k];
          if (!s) return;
          if (x >= s.x && x <= s.x + s.w && y >= s.y && y <= s.y + s.h) found = { key: k, seat: s };
        });
        return found;
      }

      function toggleSeatByKey(key) {
        if (!key) return;
        var idx = internal.selection.indexOf(key);
        if (idx === -1) internal.selection.push(key);
        else internal.selection.splice(idx, 1);
        renderOverlay();
        emitSelectionChanged();
      }

      function selectRectArea(clientX1, clientY1, clientX2, clientY2) {
        var computed = getComputed();
        if (!computed || !computed.seats) return;
        var rect = internal.canvas.getBoundingClientRect();
        var left = Math.min(clientX1, clientX2) - rect.left;
        var right = Math.max(clientX1, clientX2) - rect.left;
        var top = Math.min(clientY1, clientY2) - rect.top;
        var bottom = Math.max(clientY1, clientY2) - rect.top;
        internal.selection = [];
        Object.keys(computed.seats).forEach(function (k) {
          var s = computed.seats[k];
          if (!s) return;
          if (!(s.x > right || s.x + s.w < left || s.y > bottom || s.y + s.h < top)) internal.selection.push(k);
        });
        renderOverlay();
        emitSelectionChanged();
      }

      // Price assignment
      function assignPriceToSelection(opts) {
        opts = opts || {};
        var price = (typeof opts.price !== 'undefined') ? opts.price : '';
        var color = (typeof opts.color !== 'undefined') ? opts.color : '';
        var overwrite = !!opts.overwrite;
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        if (!scInternal) return;
        scInternal.layout = scInternal.layout || {};
        scInternal.layout.seats = scInternal.layout.seats || {};
        internal.selection.forEach(function (key) {
          scInternal.layout.seats[key] = scInternal.layout.seats[key] || {};
          scInternal.layout.seats[key].meta = scInternal.layout.seats[key].meta || {};
          if (!scInternal.layout.seats[key].meta.price || overwrite) {
            scInternal.layout.seats[key].meta.price = price;
            if (color) scInternal.layout.seats[key].meta.color = color;
          }
        });
        // ensure group exists
        if (price !== '' || (color && color !== '')) {
          var existing = findGroupByPriceColor(price, color);
          if (!existing) addPriceGroup({ price: String(price), color: color || '#cccccc', label: String(price || '') });
        }
        sc.setLayout(scInternal.layout);
        renderOverlay();
      }

      function unassignPriceFromSelection() {
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        if (!scInternal) return;
        scInternal.layout = scInternal.layout || {};
        scInternal.layout.seats = scInternal.layout.seats || {};
        internal.selection.forEach(function (key) {
          if (scInternal.layout.seats[key] && scInternal.layout.seats[key].meta) {
            delete scInternal.layout.seats[key].meta.price;
            delete scInternal.layout.seats[key].meta.color;
          }
        });
        sc.setLayout(scInternal.layout);
        renderOverlay();
      }

      function applyPriceRanges(pr) {
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        if (!scInternal) return;
        scInternal.layout = scInternal.layout || {};
        scInternal.layout.seats = scInternal.layout.seats || {};
        if (!Array.isArray(pr)) return;
        pr.forEach(function (entry) {
          if (!entry) return;
          if (Array.isArray(entry.seat_ids)) {
            entry.seat_ids.forEach(function (sid) {
              scInternal.layout.seats[sid] = scInternal.layout.seats[sid] || {};
              scInternal.layout.seats[sid].meta = scInternal.layout.seats[sid].meta || {};
              scInternal.layout.seats[sid].meta.price = entry.price;
              if (entry.color) scInternal.layout.seats[sid].meta.color = entry.color;
            });
          } else if (typeof entry.row_start !== 'undefined' || typeof entry.row_end !== 'undefined') {
            var rs = Number(entry.row_start || -Infinity);
            var re = Number(entry.row_end || Infinity);
            var computed = getComputed();
            if (!computed || !computed.seats) return;
            Object.keys(computed.seats).forEach(function (k) {
              var s = computed.seats[k];
              if (!s) return;
              var row = Number(s.row || (s.meta && s.meta.row) || NaN);
              if (!isNaN(row) && row >= rs && row <= re) {
                scInternal.layout.seats[k] = scInternal.layout.seats[k] || {};
                scInternal.layout.seats[k].meta = scInternal.layout.seats[k].meta || {};
                scInternal.layout.seats[k].meta.price = entry.price;
                if (entry.color) scInternal.layout.seats[k].meta.color = entry.color;
              }
            });
          }
        });
        sc.setLayout(scInternal.layout);
        renderOverlay();
      }

      // Overlay rendering
      function renderOverlay() {
        sc.renderCanvas();
        var computed = getComputed();
        if (!computed) return;
        var ctx = internal.canvas.getContext('2d');
        ctx.save();
        internal.selection.forEach(function (key) {
          var s = computed.seats && computed.seats[key];
          if (!s) return;
          ctx.beginPath();
          ctx.strokeStyle = 'rgba(45,156,219,0.95)';
          ctx.lineWidth = 2;
          ctx.setLineDash([6, 4]);
          ctx.strokeRect(Math.round(s.x) - 2, Math.round(s.y) - 2, Math.round(s.w) + 4, Math.round(s.h) + 4);
        });
        ctx.restore();
        if (internal.selectedCountEl) internal.selectedCountEl.textContent = String(internal.selection.length || 0);
      }

      // Pointer handlers for selection (independent from SeatingCanvas editing)
      var pointerDown = false;
      function onPointerDown(e) {
        var isTouch = e.type.indexOf('touch') === 0;
        var clientX = isTouch ? e.touches[0].clientX : e.clientX;
        var clientY = isTouch ? e.touches[0].clientY : e.clientY;
        pointerDown = true;
        if (internal.tool === 'select') {
          // wait for up
        } else if (internal.tool === 'rect') {
          internal.rectSelecting = true;
          internal.dragRect = { x1: clientX, y1: clientY, x2: clientX, y2: clientY };
        }
      }
      function onPointerMove(e) {
        if (!pointerDown) return;
        var isTouch = e.type.indexOf('touch') === 0;
        var clientX = isTouch ? e.touches[0].clientX : e.clientX;
        var clientY = isTouch ? e.touches[0].clientY : e.clientY;
        if (internal.rectSelecting && internal.dragRect) {
          internal.dragRect.x2 = clientX; internal.dragRect.y2 = clientY;
          renderOverlay();
          var ctx = internal.canvas.getContext('2d');
          ctx.save();
          ctx.fillStyle = 'rgba(50,120,200,0.12)';
          ctx.strokeStyle = 'rgba(50,120,200,0.6)';
          ctx.setLineDash([4, 2]);
          var rect = internal.canvas.getBoundingClientRect();
          var left = Math.min(internal.dragRect.x1, internal.dragRect.x2) - rect.left;
          var top = Math.min(internal.dragRect.y1, internal.dragRect.y2) - rect.top;
          var w = Math.abs(internal.dragRect.x2 - internal.dragRect.x1);
          var h = Math.abs(internal.dragRect.y2 - internal.dragRect.y1);
          ctx.fillRect(left, top, w, h);
          ctx.strokeRect(left, top, w, h);
          ctx.restore();
        }
      }
      function onPointerUp(e) {
        var isTouch = e.type.indexOf('touch') === 0;
        var clientX = isTouch ? (e.changedTouches && e.changedTouches[0] && e.changedTouches[0].clientX) : e.clientX;
        var clientY = isTouch ? (e.changedTouches && e.changedTouches[0] && e.changedTouches[0].clientY) : e.clientY;
        pointerDown = false;
        if (internal.tool === 'select') {
          var found = findSeatAtClient(clientX, clientY);
          if (found) toggleSeatByKey(found.key);
        } else if (internal.tool === 'rect') {
          if (internal.dragRect) {
            selectRectArea(internal.dragRect.x1, internal.dragRect.y1, internal.dragRect.x2, internal.dragRect.y2);
            internal.rectSelecting = false;
            internal.dragRect = null;
          }
        }
      }

      internal.canvas.addEventListener('mousedown', onPointerDown, false);
      internal.canvas.addEventListener('mousemove', onPointerMove, false);
      window.addEventListener('mouseup', onPointerUp, false);
      internal.canvas.addEventListener('touchstart', function (ev) { onPointerDown(ev); ev.preventDefault(); }, { passive: false });
      internal.canvas.addEventListener('touchmove', function (ev) { onPointerMove(ev); ev.preventDefault(); }, { passive: false });
      window.addEventListener('touchend', function (ev) { onPointerUp(ev); }, { passive: false });

      // Price groups table delegation
      function onPriceGroupsClick(e) {
        var btn = e.target.closest && e.target.closest('button');
        if (!btn) return;
        var tr = btn.closest('tr');
        if (!tr) return;
        var gid = tr.dataset.groupId;
        if (btn.classList.contains('js-pg-remove')) {
          // remove group and optionally remove meta from seats (do not auto-remove seat meta by default)
          removePriceGroupById(gid);
        } else if (btn.classList.contains('js-pg-apply')) {
          applyGroupToSelectionById(gid);
        } else if (btn.classList.contains('js-pg-save')) {
          var price = tr.querySelector('.pg-price').value;
          var color = tr.querySelector('.pg-color').value;
          var label = tr.querySelector('.pg-label').value;
          updatePriceGroupById(gid, { price: price, color: color, label: label });
        }
      }
      if (internal.priceGroupsTbody) internal.priceGroupsTbody.addEventListener('click', onPriceGroupsClick, false);

      // helper wrappers for groups (reuse functions defined above)
      function applyGroupToSelectionById(id) {
        var groups = loadGroupsFromField();
        var g = groups.find(function (x) { return x.id === id; });
        if (!g) return;
        assignPriceToSelection({ price: g.price, color: g.color, overwrite: true });
      }

      // Emit selection changed
      function emitSelectionChanged() {
        var ev = new CustomEvent('seating:selection-changed', { detail: { selection: internal.selection.slice() } });
        window.dispatchEvent(ev);
      }

      // initial render groups and overlay
      renderPriceGroupsTable();
      renderOverlay();

      // Public API
      var api = {
        setTool: function (t) { internal.tool = (t === 'rect') ? 'rect' : 'select'; },
        clearSelection: clearSelection,
        getSelectedSeatsCount: getSelectedSeatsCount,
        assignPriceToSelection: assignPriceToSelection,
        unassignPriceFromSelection: unassignPriceFromSelection,
        applyPriceRanges: applyPriceRanges,
        fitContentToView: function (opts) { if (sc && typeof sc.fitContentToView === 'function') sc.fitContentToView(opts || {}); },
        getSeatingCanvasInstance: function () { return sc; },
        getSelection: function () { return internal.selection.slice(); },
        addPriceGroup: addPriceGroup,
        removePriceGroupById: removePriceGroupById,
        updatePriceGroupById: updatePriceGroupById,
        getPriceGroups: function () { return loadGroupsFromField(); }
      };

      instance = api;
      return api;
    }

    return {
      init: function (opts) {
        if (instance) return instance;
        instance = createInstance(opts || {});
        return instance;
      },
      getInstance: function () { return instance; }
    };
  })();

  global.SeatingSessionEditor = SeatingSessionEditor;
})(window);
