// /assets/js/session-price-editor.js
// Session price editor: selection tools and price assignment for schedule pages.
// Uses SeatingCanvasRender (render-only) to draw layout and stores price groups in hidden input price_ranges.
// Exposes global SessionPriceEditor.init(options) and instance methods:
// setTool, clearSelection, assignPriceToSelection, unassignPriceFromSelection, getSelectedSeatsCount,
// applyPriceRanges, addPriceGroup, removePriceGroupById, updatePriceGroupById, getPriceGroups, renderOverlay

(function (global) {
  'use strict';

  var SessionPriceEditor = (function () {
    var instance = null;

    function createInstance(opts) {
      opts = opts || {};
      var canvasId = opts.canvasId || 'editor-canvas';
      var canvas = document.getElementById(canvasId);
      if (!canvas) {
        return null;
      }

      // Ensure render canvas is initialized (idempotent)
      var sc = (global.SeatingCanvasRender && typeof global.SeatingCanvasRender.getInstance === 'function') ? global.SeatingCanvasRender.getInstance(canvasId) : null;
      if (!sc && global.SeatingCanvasRender && typeof global.SeatingCanvasRender.init === 'function') {
        sc = global.SeatingCanvasRender.init(canvasId, opts.seatingOptions || { seatSize: 28, gapX: 8, gapY: 12, showGrid: true });
      }
      if (!sc) {
        return null;
      }

      var internal = {
        sc: sc,
        canvas: canvas,
        tool: 'select',
        selection: [],
        rectSelecting: false,
        dragRect: null,
        selectedCountEl: opts.selectedCountId ? document.getElementById(opts.selectedCountId) : null,
        priceGroupsTbody: document.getElementById('priceGroupsTbody'),
        priceRangesField: document.querySelector('input[name="price_ranges"]') || document.getElementById('price_ranges') || document.getElementById('session_price_ranges')
      };

      function loadGroupsFromField() {
        var f = internal.priceRangesField;
        if (!f) return [];
        var v = f.value || '';
        if (!v) return [];
        try {
          var parsed = JSON.parse(v);
          if (Array.isArray(parsed)) return parsed;
        } catch (e) {}
        return [];
      }
      function saveGroupsToField(groups) {
        var f = internal.priceRangesField;
        if (!f) return;
        try { f.value = JSON.stringify(groups); } catch (e) { f.value = ''; }
      }

      function renderPriceGroupsTable() {
        var tbody = internal.priceGroupsTbody;
        if (!tbody) return;
        tbody.innerHTML = '';
        var groups = loadGroupsFromField();
        groups.forEach(function (g) {
          var tr = document.createElement('tr');
          tr.dataset.groupId = g.id || '';
          var priceCell = '<td style="width:20%;"><div class="pg-price-text" style="padding:6px 8px;">' + (g.price || '') + '</div></td>';
          var colorVal = g.color || '#cccccc';
          var colorCell = '<td style="width:30%;"><input class="pg-color form-control" type="color" value="' + colorVal + '" style="width:48px; height:28px; padding:0; border:none;" /></td>';
          var actionCell = '<td style="width:50%; display:flex; gap:8px; align-items:center;">' +
            '<button type="button" class="btn btn-primary btn-sm js-pg-apply">Применить</button>' +
            '<button type="button" class="btn btn-danger btn-sm js-pg-remove">Удалить</button>' +
            '</td>';
          tr.innerHTML = priceCell + colorCell + actionCell;
          tbody.appendChild(tr);
        });
      }

      function addPriceGroup(group) {
        var groups = loadGroupsFromField();
        group = group || {};
        if (!group.id) group.id = 'g' + Date.now() + Math.floor(Math.random() * 1000);
        var existing = groups.find(function (x) { return String(x.price) === String(group.price); });
        if (existing) {
          if (group.color) existing.color = group.color;
        } else {
          groups.push({ id: group.id, price: group.price || '', color: group.color || '#cccccc', label: group.label || '' });
        }
        saveGroupsToField(groups);
        renderPriceGroupsTable();
      }

      /**
       * normalizeSeatEntry
       * Ensure a seat entry is an object with optional meta object.
       * If seat is an array or non-object, replace with {}.
       */
      function normalizeSeatEntry(seat) {
        if (!seat || typeof seat !== 'object' || Array.isArray(seat)) {
          return {};
        }
        return seat;
      }

      /**
       * _cleanSeatMetaAndLayout
       * - Convert any seats that are arrays or non-objects into plain objects.
       * - Remove empty meta objects and empty meta keys.
       * - Remove seat entries that are empty (no meta and no other meaningful props).
       */
      function _cleanSeatMetaAndLayout(scInternal) {
        if (!scInternal || !scInternal.layout || !scInternal.layout.seats) return;
        var seats = scInternal.layout.seats;
        // If seats is array-like, convert to map
        if (Array.isArray(seats)) {
          var map = {};
          try {
            Object.keys(seats).forEach(function (k) {
              map[k] = seats[k];
            });
          } catch (e) {}
          scInternal.layout.seats = map;
          seats = scInternal.layout.seats;
        }
        Object.keys(seats).forEach(function (k) {
          var seat = seats[k];
          // if seat is array or not object -> replace with empty object
          if (!seat || typeof seat !== 'object' || Array.isArray(seat)) {
            seats[k] = {};
            seat = seats[k];
          }
          if (seat.meta && typeof seat.meta === 'object') {
            // remove keys with undefined/null/empty-string values
            Object.keys(seat.meta).forEach(function (mk) {
              var v = seat.meta[mk];
              if (v === null || typeof v === 'undefined' || (typeof v === 'string' && v.trim() === '')) {
                delete seat.meta[mk];
              }
            });
            // if meta is now empty, delete meta property
            if (Object.keys(seat.meta).length === 0) {
              delete seat.meta;
            }
          }
          // if seat object has no keys left, remove the seat entry entirely
          if (!seat || (typeof seat === 'object' && Object.keys(seat).length === 0)) {
            try { delete seats[k]; } catch (e) { seats[k] = {}; }
          }
        });
      }

      function removePriceGroupById(id) {
        var groups = loadGroupsFromField();
        var removed = groups.filter(function (g) { return g.id === id; });
        groups = groups.filter(function (g) { return g.id !== id; });
        saveGroupsToField(groups);
        // Also clear seat meta for seats that had this price (price match)
        try {
          var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
          if (scInternal && scInternal.layout && scInternal.layout.seats) {
            var priceToRemove = removed.length ? removed[0].price : null;
            var colorToRemove = removed.length ? (removed[0].color || null) : null;
            scInternal.layout.seats = scInternal.layout.seats || {};
            Object.keys(scInternal.layout.seats).forEach(function (k) {
              // normalize seat entry first
              if (!scInternal.layout.seats[k] || typeof scInternal.layout.seats[k] !== 'object' || Array.isArray(scInternal.layout.seats[k])) {
                scInternal.layout.seats[k] = {};
              }
              var seatMeta = scInternal.layout.seats[k].meta ? scInternal.layout.seats[k].meta : null;
              if (!seatMeta) return;
              if ((priceToRemove !== null && String(seatMeta.price) === String(priceToRemove)) || (colorToRemove && String((seatMeta.color || '')).toLowerCase() === String((colorToRemove || '')).toLowerCase())) {
                delete scInternal.layout.seats[k].meta.price;
                delete scInternal.layout.seats[k].meta.color;
                if (scInternal.layout.seats[k].meta && Object.keys(scInternal.layout.seats[k].meta).length === 0) {
                  delete scInternal.layout.seats[k].meta;
                }
              }
            });
            // ensure no empty meta objects remain and remove empty seat entries
            _cleanSeatMetaAndLayout(scInternal);
            sc.setLayout(scInternal.layout);
          }
        } catch (e) { /* ignore */ }
        renderPriceGroupsTable();
        if (instance && typeof instance.renderOverlay === 'function') instance.renderOverlay();
        return { success: true };
      }

      function updatePriceGroupById(id, data) {
        var groups = loadGroupsFromField();
        groups = groups.map(function (g) { if (g.id !== id) return g; return Object.assign({}, g, data); });
        saveGroupsToField(groups);
        // If color changed, update seats that have this price
        try {
          var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
          if (scInternal && scInternal.layout && scInternal.layout.seats) {
            var target = groups.find(function (x) { return x.id === id; });
            if (target) {
              scInternal.layout.seats = scInternal.layout.seats || {};
              Object.keys(scInternal.layout.seats).forEach(function (k) {
                if (!scInternal.layout.seats[k] || typeof scInternal.layout.seats[k] !== 'object' || Array.isArray(scInternal.layout.seats[k])) {
                  scInternal.layout.seats[k] = {};
                }
                var seatMeta = scInternal.layout.seats[k].meta ? scInternal.layout.seats[k].meta : null;
                if (!seatMeta) return;
                if (String(seatMeta.price) === String(target.price)) {
                  if (target.color) scInternal.layout.seats[k].meta.color = target.color;
                }
              });
              _cleanSeatMetaAndLayout(scInternal);
              sc.setLayout(scInternal.layout);
            }
          }
        } catch (e) {}
        renderPriceGroupsTable();
        if (instance && typeof instance.renderOverlay === 'function') instance.renderOverlay();
        return { success: true };
      }

      function findGroupByPrice(price) {
        var groups = loadGroupsFromField();
        return groups.find(function (g) { return String(g.price) === String(price); });
      }

      function getComputed() {
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        return scInternal && scInternal._computed ? scInternal._computed : null;
      }

      function clearSelection() {
        internal.selection = [];
        renderOverlay();
        emitSelectionChanged();
      }

      function getSelectedSeatsCount() { return internal.selection.length; }

      function findSeatAtClient(clientX, clientY) {
        var computed = getComputed();
        if (!computed || !computed.seats) return null;
        var rect = internal.canvas.getBoundingClientRect();
        var ratio = window.devicePixelRatio || 1;
        var x = (clientX - rect.left) / (ratio * (sc.getInternal ? (sc.getInternal().scale || 1) : 1));
        var y = (clientY - rect.top) / (ratio * (sc.getInternal ? (sc.getInternal().scale || 1) : 1));
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
        var ratio = window.devicePixelRatio || 1;
        var left = Math.min(clientX1, clientX2);
        var right = Math.max(clientX1, clientX2);
        var top = Math.min(clientY1, clientY2);
        var bottom = Math.max(clientY1, clientY2);
        left = (left - rect.left) / (ratio * (sc.getInternal ? (sc.getInternal().scale || 1) : 1));
        right = (right - rect.left) / (ratio * (sc.getInternal ? (sc.getInternal().scale || 1) : 1));
        top = (top - rect.top) / (ratio * (sc.getInternal ? (sc.getInternal().scale || 1) : 1));
        bottom = (bottom - rect.top) / (ratio * (sc.getInternal ? (sc.getInternal().scale || 1) : 1));
        internal.selection = [];
        Object.keys(computed.seats).forEach(function (k) {
          var s = computed.seats[k];
          if (!s) return;
          if (!(s.x > right || s.x + s.w < left || s.y > bottom || s.y + s.h < top)) internal.selection.push(k);
        });
        renderOverlay();
        emitSelectionChanged();
      }

      function assignPriceToSelection(opts) {
        opts = opts || {};
        var price = (typeof opts.price !== 'undefined') ? opts.price : '';
        var color = (typeof opts.color !== 'undefined') ? opts.color : '';
        var overwrite = !!opts.overwrite;
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        if (!scInternal) return { success: false, message: 'canvas-unavailable' };

        if ((price !== null && price !== '') && (!color || color === '')) {
          var existingByPrice = findGroupByPrice(price);
          if (existingByPrice && existingByPrice.color) {
            color = existingByPrice.color;
          }
        }

        if (!price || price === '') {
          return { success: false, message: 'missing_price' };
        }
        if (!color || color === '') {
          return { success: false, message: 'missing_color' };
        }

        if (!Array.isArray(internal.selection) || internal.selection.length === 0) {
          return { success: false, message: 'no_selection' };
        }

        scInternal.layout = scInternal.layout || {};
        scInternal.layout.seats = scInternal.layout.seats || {};
        internal.selection.forEach(function (key) {
          // ensure seat entry is object
          if (!scInternal.layout.seats[key] || typeof scInternal.layout.seats[key] !== 'object' || Array.isArray(scInternal.layout.seats[key])) {
            scInternal.layout.seats[key] = {};
          }
          scInternal.layout.seats[key].meta = scInternal.layout.seats[key].meta || {};
          if (!scInternal.layout.seats[key].meta.price || overwrite) {
            scInternal.layout.seats[key].meta.price = price;
            if (color) scInternal.layout.seats[key].meta.color = color;
          }
        });

        // ensure group exists: if price present, ensure group with that price exists and color is set (prefer existing color)
        if (price !== '' || (color && color !== '')) {
          var existing = findGroupByPrice(price);
          if (!existing) {
            addPriceGroup({ price: String(price), color: color || '#cccccc', label: String(price || '') });
          } else {
            // if existing group has no color but we have color, update it
            if ((!existing.color || existing.color === '') && color) {
              updatePriceGroupById(existing.id, { color: color });
            }
          }
        }

        // clean meta objects (remove empty keys) and remove empty seat entries
        _cleanSeatMetaAndLayout(scInternal);

        sc.setLayout(scInternal.layout);
        // update hidden seat_map but only save seats that have price (see updateHiddenSeatMap)
        updateHiddenSeatMap(scInternal);
        renderOverlay();
        return { success: true };
      }

      function unassignPriceFromSelection() {
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        if (!scInternal) return { success: false, message: 'canvas-unavailable' };
        scInternal.layout = scInternal.layout || {};
        scInternal.layout.seats = scInternal.layout.seats || {};
        internal.selection.forEach(function (key) {
          if (!scInternal.layout.seats[key] || typeof scInternal.layout.seats[key] !== 'object' || Array.isArray(scInternal.layout.seats[key])) {
            scInternal.layout.seats[key] = {};
          }
          if (scInternal.layout.seats[key] && scInternal.layout.seats[key].meta) {
            delete scInternal.layout.seats[key].meta.price;
            delete scInternal.layout.seats[key].meta.color;
            if (scInternal.layout.seats[key].meta && Object.keys(scInternal.layout.seats[key].meta).length === 0) {
              delete scInternal.layout.seats[key].meta;
            }
          }
        });
        _cleanSeatMetaAndLayout(scInternal);
        sc.setLayout(scInternal.layout);
        updateHiddenSeatMap(scInternal);
        renderOverlay();
        return { success: true };
      }

      /**
       * updateHiddenSeatMap(scInternal)
       * Writes a compact JSON into #seat_map containing only seats that have a price set.
       * Does not mutate renderer layout.
       */
      function updateHiddenSeatMap(scInternal) {
        try {
          var scAuthor = (global.SeatingCanvasRender && typeof global.SeatingCanvasRender.getInstance === 'function')
                         ? global.SeatingCanvasRender.getInstance(canvasId) : null;
          var authoritative = null;
          try { authoritative = scAuthor && typeof scAuthor.getInternal === 'function' ? scAuthor.getInternal() : scInternal || null; } catch (e) { authoritative = scInternal || null; }

          if (!authoritative || !authoritative.layout) return;

          // Work on a deep copy
          var copy = JSON.parse(JSON.stringify(authoritative.layout || {}));
          copy.seats = copy.seats || {};

          // Normalize seats map and keep only seats that have meta.price defined (non-empty)
          var outSeats = {};
          Object.keys(copy.seats).forEach(function (k) {
            var s = copy.seats[k];
            if (!s || typeof s !== 'object' || Array.isArray(s)) return;
            var meta = s.meta && typeof s.meta === 'object' ? s.meta : null;
            if (meta && (typeof meta.price !== 'undefined') && meta.price !== null && String(meta.price).trim() !== '') {
              // keep only meta (server expects meta inside seat)
              outSeats[k] = { meta: {} };
              // copy price and color if present
              if (typeof meta.price !== 'undefined') outSeats[k].meta.price = meta.price;
              if (typeof meta.color !== 'undefined') outSeats[k].meta.color = meta.color;
              // copy any other meta keys that are meaningful (optional)
              Object.keys(meta).forEach(function (mk) {
                if (mk === 'price' || mk === 'color') return;
                var mv = meta[mk];
                if (mv !== null && typeof mv !== 'undefined' && !(typeof mv === 'string' && mv.trim() === '')) {
                  outSeats[k].meta[mk] = mv;
                }
              });
            }
          });

          // Replace seats with compact map containing only priced seats
          copy.seats = outSeats;

          var compact = JSON.stringify(copy);
          var seatMapField = document.getElementById('seat_map');
          if (seatMapField) {
            try { seatMapField.value = compact; } catch (e) {}
            try { seatMapField.setAttribute('value', compact); } catch (e) {}
          }
        } catch (e) {
        }
      }

      function applyPriceRanges(pr) {
        var scInternal = sc.getInternal ? sc.getInternal() : (sc._internal || null);
        if (!scInternal) return { success: false, message: 'canvas-unavailable' };
        scInternal.layout = scInternal.layout || {};
        scInternal.layout.seats = scInternal.layout.seats || {};
        if (!Array.isArray(pr)) return { success: false, message: 'invalid-data' };
        pr.forEach(function (entry) {
          if (!entry) return;
          if (Array.isArray(entry.seat_ids)) {
            entry.seat_ids.forEach(function (sid) {
              if (!scInternal.layout.seats[sid] || typeof scInternal.layout.seats[sid] !== 'object' || Array.isArray(scInternal.layout.seats[sid])) {
                scInternal.layout.seats[sid] = {};
              }
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
                if (!scInternal.layout.seats[k] || typeof scInternal.layout.seats[k] !== 'object' || Array.isArray(scInternal.layout.seats[k])) {
                  scInternal.layout.seats[k] = {};
                }
                scInternal.layout.seats[k].meta = scInternal.layout.seats[k].meta || {};
                scInternal.layout.seats[k].meta.price = entry.price;
                if (entry.color) scInternal.layout.seats[k].meta.color = entry.color;
              }
            });
          }
        });
        _cleanSeatMetaAndLayout(scInternal);
        sc.setLayout(scInternal.layout);
        updateHiddenSeatMap(scInternal);
        renderOverlay();
        return { success: true };
      }

      function renderOverlay() {
        if (typeof sc.renderCanvas === 'function') sc.renderCanvas();
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
        if (internal.rectSelecting && internal.dragRect) {
          try {
            var rect = internal.canvas.getBoundingClientRect();
            var left = Math.min(internal.dragRect.x1, internal.dragRect.x2) - rect.left;
            var top = Math.min(internal.dragRect.y1, internal.dragRect.y2) - rect.top;
            var w = Math.abs(internal.dragRect.x2 - internal.dragRect.x1);
            var h = Math.abs(internal.dragRect.y2 - internal.dragRect.y1);
            ctx.save();
            ctx.fillStyle = 'rgba(50,120,200,0.12)';
            ctx.strokeStyle = 'rgba(50,120,200,0.6)';
            ctx.setLineDash([4, 2]);
            ctx.fillRect(left, top, w, h);
            ctx.strokeRect(left, top, w, h);
            ctx.restore();
          } catch (e) {}
        }
        ctx.restore();
        if (internal.selectedCountEl) internal.selectedCountEl.textContent = String(internal.selection.length || 0);
      }

      // pointer handlers
      var pointerDown = false;
      internal.canvas.addEventListener('mousedown', function (e) {
        var clientX = e.clientX, clientY = e.clientY;
        pointerDown = true;
        if (internal.tool === 'rect') {
          internal.rectSelecting = true;
          internal.dragRect = { x1: clientX, y1: clientY, x2: clientX, y2: clientY };
        }
      }, false);

      internal.canvas.addEventListener('mousemove', function (e) {
        if (!pointerDown) return;
        var clientX = e.clientX, clientY = e.clientY;
        if (internal.rectSelecting && internal.dragRect) {
          internal.dragRect.x2 = clientX; internal.dragRect.y2 = clientY;
          renderOverlay();
        }
      }, false);

      // Always clear selection state on mouseup anywhere in window.
      function _globalPointerUpHandler(e) {
        // Always reset pointerDown so that drag state doesn't persist if mouseup happened outside canvas
        pointerDown = false;
        var clientX = (e && typeof e.clientX === 'number') ? e.clientX : null;
        var clientY = (e && typeof e.clientY === 'number') ? e.clientY : null;

        if (internal.tool === 'select') {
          if (clientX !== null && clientY !== null) {
            var found = findSeatAtClient(clientX, clientY);
            if (found) toggleSeatByKey(found.key);
          }
        } else if (internal.tool === 'rect') {
          if (internal.dragRect) {
            try {
              selectRectArea(internal.dragRect.x1, internal.dragRect.y1, internal.dragRect.x2, internal.dragRect.y2);
            } catch (err) {}
          }
          internal.rectSelecting = false;
          internal.dragRect = null;
          renderOverlay();
        } else {
          // ensure rectSelecting cleared in any case
          internal.rectSelecting = false;
          internal.dragRect = null;
          renderOverlay();
        }
      }

      window.addEventListener('mouseup', _globalPointerUpHandler, false);
      // also clear on blur for robustness
      window.addEventListener('blur', function () { pointerDown = false; internal.rectSelecting = false; internal.dragRect = null; renderOverlay(); }, false);

      function emitSelectionChanged() {
        var ev = new CustomEvent('seating:selection-changed', { detail: { selection: internal.selection.slice() } });
        window.dispatchEvent(ev);
      }

      function exposeRenderOverlay() { renderOverlay(); }

      // initialize UI
      renderPriceGroupsTable();
      renderOverlay();

      var api = {
        setTool: function (t) { internal.tool = (t === 'rect') ? 'rect' : 'select'; },
        clearSelection: clearSelection,
        getSelectedSeatsCount: getSelectedSeatsCount,
        assignPriceToSelection: function (opts) { return assignPriceToSelection(opts); },
        unassignPriceFromSelection: function () { return unassignPriceFromSelection(); },
        applyPriceRanges: function (pr) { return applyPriceRanges(pr); },
        fitContentToView: function (opts) { if (sc && typeof sc.fitContentToView === 'function') sc.fitContentToView(opts || {}); },
        getSeatingCanvasInstance: function () { return sc; },
        addPriceGroup: addPriceGroup,
        removePriceGroupById: removePriceGroupById,
        updatePriceGroupById: updatePriceGroupById,
        getPriceGroups: function () { return loadGroupsFromField(); },
        renderOverlay: exposeRenderOverlay,
        getInstance: function () { return instance; }
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

  global.SessionPriceEditor = SessionPriceEditor;
})(window);
