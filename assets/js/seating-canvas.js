/* public/assets/js/seating-canvas.js
   Универсальный канвас с drag/move/resize для элементов и сегментов.
   Шаг привязки сетки: 1/8 ширины места.
   Логи удалены.
*/

(function (global) {
  'use strict';

  var SeatingCanvas = (function () {
    var instances = {};

    function createEventBus() {
      var handlers = {};
      return {
        on: function (name, fn) { handlers[name] = handlers[name] || []; handlers[name].push(fn); },
        off: function (name, fn) { if (!handlers[name]) return; if (!fn) { handlers[name] = []; return; } handlers[name] = handlers[name].filter(function (h) { return h !== fn; }); },
        emit: function (name, payload) { (handlers[name] || []).slice().forEach(function (h) { try { h(payload); } catch (e) { /* swallow */ } }); }
      };
    }

    function deepClone(obj) { try { return JSON.parse(JSON.stringify(obj)); } catch (e) { return obj; } }

    function init(canvasId, opts) {
      opts = opts || {};
      var canvas = document.getElementById(canvasId);
      if (!canvas) { return null; }
      var ctx = canvas.getContext('2d');
      var bus = createEventBus();

      var internal = {
        id: canvasId,
        canvas: canvas,
        ctx: ctx,
        bus: bus,
        layout: { meta: {}, anchors: {}, rows: [], elements: [], seats: {} },
        seatSize: Number(opts.seatSize) || 28,
        gapX: Number(opts.gapX) || 8,
        gapY: Number(opts.gapY) || 12,
        inspectorId: opts.inspectorId || null,
        showGrid: !!opts.showGrid,
        selectedSegments: [],
        selectedElements: [],
        scale: 1,
        dragging: false,
        dragMode: null,
        dragStart: null,
        dragState: null,
        // snapStep (logical units) — default 1/8 of seat size
        snapStep: Math.max(1, Math.round((Number(opts.seatSize) || 28) / 8)),
        _visual: Object.assign({
          defaultColor: '#dff3ff', defaultStroke: '#7fbfe6', textColor: '#0b3b4a', borderRadius: 4, fontSize: 12
        }, opts._visual || {}),
        debug: false
      };

      function resizeCanvasForHiDPI() {
        var ratio = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        var w = rect.width || canvas.width || 1200;
        var h = rect.height || canvas.height || 700;
        canvas.setAttribute('data-logical-width', w);
        canvas.setAttribute('data-logical-height', h);
        canvas.width = Math.max(1, Math.round(w * ratio));
        canvas.height = Math.max(1, Math.round(h * ratio));
        ctx.setTransform(ratio * internal.scale, 0, 0, ratio * internal.scale, 0, 0);
      }

      function roundRect(ctx, x, y, w, h, r, fill, stroke) {
        if (typeof r === 'undefined') r = 5;
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
        if (fill) ctx.fill();
        if (stroke) ctx.stroke();
      }

      function compute(layout) {
        var meta = layout.meta || {};
        var cw = parseFloat(canvas.getAttribute('data-logical-width')) || canvas.width || 1200;
        var ch = parseFloat(canvas.getAttribute('data-logical-height')) || canvas.height || 700;
        var baseSeat = Number(layout.meta && layout.meta.baseSeatSize) || internal.seatSize;
        var gapX = Number(layout.meta && layout.meta.gapX) || internal.gapX;
        var gapY = Number(layout.meta && layout.meta.gapY) || internal.gapY;

        var anchors = layout.anchors || {};
        var anchorsPx = {};
        Object.keys(anchors).forEach(function (name) {
          var a = anchors[name];
          var px = (a && a._px && typeof a._px.x === 'number') ? a._px.x : (a && typeof a.x === 'number' ? a.x : 0);
          var py = (a && a._px && typeof a._px.y === 'number') ? a._px.y : (a && typeof a.y === 'number' ? a.y : 0);
          anchorsPx[name] = { x: px, y: py, _src: a };
        });

        var seats = {};
        var segments = [];
        (layout.rows || []).forEach(function (row) {
          (row.segments || []).forEach(function (seg, si) {
            var anchorName = seg.anchor || Object.keys(anchorsPx)[0] || null;
            var anchorPx = anchorName && anchorsPx[anchorName] ? anchorsPx[anchorName] : { x: 0, y: 0 };
            var off = seg.offset || { x: 0, y: 0 };
            var offXpx = (off.x || 0) * (baseSeat + gapX);
            var offYpx = (off.y || 0) * (baseSeat + gapY);
            var start = Number(seg.start) || 1;
            var end = Number(seg.end) || start;
            for (var s = start; s <= end; s++) {
              var idx = s - start;
              var x = anchorPx.x + offXpx + idx * (baseSeat + gapX);
              var y = anchorPx.y + offYpx;
              var key = row.number + '-' + s;
              seats[key] = { x: x, y: y, w: baseSeat, h: baseSeat, row: row.number, col: s };
            }
            var segX = anchorPx.x + offXpx;
            var segY = anchorPx.y + offYpx;
            var segW = (end - start + 1) * (baseSeat + gapX) - gapX;
            var segH = baseSeat;
            segments.push({ row: row.number, segIndex: si, start: start, end: end, x: segX, y: segY, w: segW, h: segH, anchor: anchorName, offset: Object.assign({}, off) });
          });
        });

        return { seats: seats, segments: segments, anchorsPx: anchorsPx, canvas: { width: cw, height: ch }, baseSeat: baseSeat, gapX: gapX, gapY: gapY };
      }

      function clear() {
        var rect = canvas.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width, rect.height);
      }

      function draw() {
        resizeCanvasForHiDPI();
        var rect = canvas.getBoundingClientRect();
        ctx.save();
        ctx.fillStyle = '#f8fbff';
        ctx.fillRect(0, 0, rect.width, rect.height);
        ctx.restore();

        if (internal.showGrid) {
          var step = Math.max(1, Math.round(internal.snapStep));
          ctx.save();
          ctx.strokeStyle = '#e6eef6';
          ctx.lineWidth = 1;
          for (var x = 0; x < rect.width; x += step) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, rect.height); ctx.stroke(); }
          for (var y = 0; y < rect.height; y += step) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(rect.width, y); ctx.stroke(); }
          ctx.restore();
        }
var computed = compute(internal.layout);
        internal._computed = computed;

        (internal.layout.elements || []).forEach(function (el, ei) {
          ctx.save();
          var fill = el.fill || '#ffffff';
          var stroke = el.stroke || 'rgba(0,0,0,0.12)';
          ctx.fillStyle = fill;
          ctx.strokeStyle = stroke;
          ctx.lineWidth = 1;
          if (el.type === 'rect') {
            var rx = Math.round(el.x || 0), ry = Math.round(el.y || 0), rw = Math.round(el.width || el.w || 60), rh = Math.round(el.height || el.h || 30);
            roundRect(ctx, rx, ry, rw, rh, el._borderRadius || internal._visual.borderRadius, true, true);
            if (el.name) { ctx.fillStyle = el.textColor || internal._visual.textColor; ctx.font = (internal._visual.fontSize || 12) + 'px system-ui'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(el.name, rx + rw / 2, ry + rh / 2); }
          } else if (el.type === 'circle') {
            var r = el.radius || Math.round((el.width || el.height || 40) / 2);
            ctx.beginPath(); ctx.arc(Math.round((el.x || 0) + r), Math.round((el.y || 0) + r), r, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            if (el.name) { ctx.fillStyle = el.textColor || internal._visual.textColor; ctx.font = (internal._visual.fontSize || 12) + 'px system-ui'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(el.name, Math.round((el.x || 0) + r), Math.round((el.y || 0) + r)); }
          } else if (el.type === 'label') {
            ctx.fillStyle = el.textColor || internal._visual.textColor; ctx.font = (internal._visual.fontSize || 12) + 'px system-ui'; ctx.textAlign = el.align || 'left'; ctx.textBaseline = 'top'; ctx.fillText(el.text || el.name || '', Math.round(el.x || 0), Math.round(el.y || 0));
          }
          if (internal.selectedElements && internal.selectedElements.indexOf(ei) !== -1) {
            ctx.save();
            ctx.strokeStyle = 'rgba(200,80,40,0.95)';
            ctx.lineWidth = 2;
            ctx.setLineDash([6, 2]);
            if (el.type === 'rect') ctx.strokeRect(Math.round(el.x) - 3, Math.round(el.y) - 3, Math.round(el.width) + 6, Math.round(el.height) + 6);
            else {
              var r = el.radius || Math.round((el.width || el.height || 40) / 2);
              ctx.beginPath(); ctx.arc(Math.round((el.x || 0) + r), Math.round((el.y || 0) + r), r + 3, 0, Math.PI * 2); ctx.stroke();
            }
            ctx.restore();
            if (el.type === 'rect') {
              var handles = getElementHandles(el);
              ctx.fillStyle = '#fff';
              ctx.strokeStyle = '#888';
              handles.forEach(function (h) { ctx.beginPath(); ctx.rect(Math.round(h.x) - 4, Math.round(h.y) - 4, 8, 8); ctx.fill(); ctx.stroke(); });
            }
          }
          ctx.restore();
        });

        var seatsMap = computed.seats || {};
        ctx.font = (internal._visual.fontSize || 12) + 'px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        Object.keys(seatsMap).forEach(function (k) {
          var s = seatsMap[k];
          ctx.save();
          ctx.fillStyle = (s.color || internal._visual.defaultColor);
          ctx.strokeStyle = (s.stroke || internal._visual.defaultStroke);
          ctx.lineWidth = 1;
          roundRect(ctx, Math.round(s.x), Math.round(s.y), Math.round(s.w), Math.round(s.h), s._borderRadius || internal._visual.borderRadius, true, true);
          ctx.fillStyle = s.textColor || internal._visual.textColor;
          var label = s.label || String(s.col || '');
          ctx.fillText(label, Math.round(s.x + s.w / 2), Math.round(s.y + s.h / 2));
          ctx.restore();
        });

        (computed.segments || []).forEach(function (seg) {
          ctx.save();
          ctx.strokeStyle = 'rgba(120,120,120,0.12)';
          ctx.lineWidth = 1;
          ctx.setLineDash([4, 4]);
          ctx.strokeRect(Math.round(seg.x), Math.round(seg.y), Math.round(seg.w), Math.round(seg.h));
          ctx.restore();
        });

        (internal.selectedSegments || []).forEach(function (sel) {
          var seg = (computed.segments || []).find(function (s) { return s.row === sel.row && s.segIndex === sel.segIndex; });
          if (seg) {
            ctx.save();
            ctx.strokeStyle = 'rgba(200,80,40,0.95)';
            ctx.lineWidth = 2;
            ctx.setLineDash([6, 2]);
            ctx.strokeRect(Math.round(seg.x) - 2, Math.round(seg.y) - 2, Math.round(seg.w) + 4, Math.round(seg.h) + 4);
            ctx.restore();
          }
        });

        ctx.save();
        ctx.fillStyle = internal._visual.textColor;
        ctx.font = '13px system-ui';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        (internal.layout.rows || []).forEach(function (row) {
          var y = null;
          for (var key in computed.seats) {
            if (!computed.seats.hasOwnProperty(key)) continue;
            var s = computed.seats[key];
            if (s.row === row.number) { y = s.y + (s.h || internal.seatSize) / 2; break; }
          }
          if (y === null) {
            var idx = (row.number || 1) - 1;
            y = (internal.anchors && internal.anchors.layout_origin && internal.anchors.layout_origin._px ? internal.anchors.layout_origin._px.y : 40) + idx * (internal.seatSize + internal.gapY) + internal.seatSize / 2;
          }
          ctx.fillText(String(row.number), 8, Math.round(y));
        });
        ctx.restore();

        if (internal.dragMode === 'select-rect' && internal.dragState && internal.dragState.rectStart && internal.dragState.rectCurrent) {
          var r0 = internal.dragState.rectStart, r1 = internal.dragState.rectCurrent;
          var left = Math.min(r0.x, r1.x), right = Math.max(r0.x, r1.x), top = Math.min(r0.y, r1.y), bottom = Math.max(r0.y, r1.y);
          ctx.save();
          ctx.fillStyle = 'rgba(50,120,200,0.12)';
          ctx.strokeStyle = 'rgba(50,120,200,0.6)';
          ctx.lineWidth = 1;
          ctx.setLineDash([4, 2]);
          ctx.fillRect(left, top, right - left, bottom - top);
          ctx.strokeRect(left, top, right - left, bottom - top);
          ctx.restore();
        }
      }

      function getElementHandles(el) {
        var x = el.x || 0, y = el.y || 0, w = el.width || el.w || 60, h = el.height || el.h || 30;
        return [
          { name: 'nw', x: x, y: y },
          { name: 'ne', x: x + w, y: y },
          { name: 'se', x: x + w, y: y + h },
          { name: 'sw', x: x, y: y + h }
        ];
      }

      function clientToLogical(clientX, clientY) {
        var rect = canvas.getBoundingClientRect();
        var ratio = window.devicePixelRatio || 1;
        var scale = internal.scale || 1;
        var x = (clientX - rect.left) / (ratio * scale);
        var y = (clientY - rect.top) / (ratio * scale);
        return { x: x, y: y };
      }

      function getElementAt(clientX, clientY) {
        var p = clientToLogical(clientX, clientY);
        var els = internal.layout.elements || [];
        for (var i = els.length - 1; i >= 0; i--) {
          var el = els[i];
          if (el.type === 'rect') {
            if (p.x >= el.x && p.x <= el.x + el.width && p.y >= el.y && p.y <= el.y + el.height) return { el: el, index: i };
          } else if (el.type === 'circle') {
            var r = el.radius || Math.round((el.width || el.height || 40) / 2);
            var cx = (el.x || 0) + r, cy = (el.y || 0) + r;
            var dx = p.x - cx, dy = p.y - cy;
            if (dx * dx + dy * dy <= r * r) return { el: el, index: i };
          } else if (el.type === 'label') {
            var w = el.width || 100, h = el.height || 20;
            if (p.x >= el.x && p.x <= el.x + w && p.y >= el.y && p.y <= el.y + h) return { el: el, index: i };
          }
        }
        return null;
      }

      function getElementHandleAt(clientX, clientY) {
        var p = clientToLogical(clientX, clientY);
        var els = internal.layout.elements || [];
        for (var i = els.length - 1; i >= 0; i--) {
          var el = els[i];
          if (el.type !== 'rect') continue;
          var handles = getElementHandles(el);
          for (var h = 0; h < handles.length; h++) {
            var hh = handles[h];
            if (p.x >= hh.x - 6 && p.x <= hh.x + 6 && p.y >= hh.y - 6 && p.y <= hh.y + 6) return { el: el, index: i, handle: hh.name };
          }
        }
        return null;
      }
function getSegmentAt(clientX, clientY) {
        var p = clientToLogical(clientX, clientY);
        var segs = (internal._computed && internal._computed.segments) || compute(internal.layout).segments || [];
        for (var i = segs.length - 1; i >= 0; i--) {
          var s = segs[i];
          if (p.x >= s.x && p.x <= s.x + s.w && p.y >= s.y && p.y <= s.y + s.h) return { segment: s, index: i };
        }
        return null;
      }

      function snapToGridPx(value, stepPx) {
        if (!stepPx || stepPx === 0) return value;
        return Math.round(value / stepPx) * stepPx;
      }

      function snap(value) {
        var step = internal.snapStep || Math.max(1, Math.round(internal.seatSize / 8));
        return Math.round(value / step) * step;
      }

      function updateSegmentOffsetInLayout(rowNumber, segIndex, newXpx, newYpx) {
        var layout = internal.layout;
        var row = (layout.rows || []).find(function (r) { return r.number === rowNumber; });
        if (!row) return;
        var seg = (row.segments || [])[segIndex];
        if (!seg) return;
        var anchorName = seg.anchor || Object.keys((layout.anchors || {}))[0];
        var anchor = (layout.anchors || {})[anchorName] || { _px: { x: 0, y: 0 } };
        var anchorPx = (anchor && anchor._px) ? anchor._px : { x: 0, y: 0 };

        var baseSeat = Number(layout.meta && layout.meta.baseSeatSize) || internal.seatSize;
        var gapX = Number(layout.meta && layout.meta.gapX) || internal.gapX;
        var gapY = Number(layout.meta && layout.meta.gapY) || internal.gapY;

        var pixelOffsetX = newXpx - anchorPx.x;
        var pixelOffsetY = newYpx - anchorPx.y;

        seg.offset = seg.offset || {};
        seg.offset.x = +(pixelOffsetX / (baseSeat + gapX)).toFixed(4);
        seg.offset.y = +(pixelOffsetY / (baseSeat + gapY)).toFixed(4);
      }

      // update offsets for segments in a row when moving by delta pixels
      // If there are selectedSegments for this row, move only those; otherwise move all segments in the row.
      function updateRowOffsetsByDelta(rowNumber, deltaPxX, deltaPxY) {
        var layout = internal.layout;
        var row = (layout.rows || []).find(function (r) { return r.number === rowNumber; });
        if (!row) return;

        var baseSeat = Number(layout.meta && layout.meta.baseSeatSize) || internal.seatSize;
        var gapX = Number(layout.meta && layout.meta.gapX) || internal.gapX;
        var gapY = Number(layout.meta && layout.meta.gapY) || internal.gapY;

        var anchors = layout.anchors || {};
        var startOffsets = (internal.dragState && internal.dragState.startOffsetsPx && internal.dragState.startOffsetsPx[rowNumber]) ? internal.dragState.startOffsetsPx[rowNumber] : null;

        // grid step for segments: one eighth of seat size (in px)
        var stepPx = Math.max(1, Math.round(baseSeat / 8));
        var snappedDeltaX = snapToGridPx(deltaPxX, stepPx);
        var snappedDeltaY = snapToGridPx(deltaPxY, stepPx);

        // determine which segment indices to move: if there are selectedSegments for this row, use them
        var selectedForRow = (internal.selectedSegments || []).filter(function (s) { return s.row === rowNumber; }).map(function (s) { return s.segIndex; });
        var indicesToMove;
        if (selectedForRow && selectedForRow.length) {
          indicesToMove = selectedForRow.slice();
        } else {
          indicesToMove = row.segments.map(function (_, i) { return i; });
        }

        indicesToMove.forEach(function (idx) {
          var seg = row.segments[idx];
          if (!seg) return;
          seg.offset = seg.offset || {};

          var anchorName = seg.anchor || Object.keys(anchors)[0];
          var anchor = (anchors && anchors[anchorName]) || { _px: { x: 0, y: 0 } };
          var anchorPx = (anchor && anchor._px) ? anchor._px : { x: 0, y: 0 };

          var basePxX = 0;
          var basePxY = 0;

          if (startOffsets && Array.isArray(startOffsets) && startOffsets[idx]) {
            basePxX = startOffsets[idx].x;
            basePxY = startOffsets[idx].y;
          } else {
            basePxX = (seg.offset && typeof seg.offset.x === 'number') ? (anchorPx.x + seg.offset.x * (baseSeat + gapX)) : anchorPx.x;
            basePxY = (seg.offset && typeof seg.offset.y === 'number') ? (anchorPx.y + seg.offset.y * (baseSeat + gapY)) : anchorPx.y;
          }

          var newPxX = basePxX + snappedDeltaX;
          var newPxY = basePxY + snappedDeltaY;

          seg.offset.x = +((newPxX - anchorPx.x) / (baseSeat + gapX)).toFixed(4);
          seg.offset.y = +((newPxY - anchorPx.y) / (baseSeat + gapY)).toFixed(4);
        });
      }

      function updateElementInLayout(index, newX, newY, newW, newH) {
        var el = internal.layout.elements[index];
        if (!el) return;
        el.x = Math.round(newX);
        el.y = Math.round(newY);
        if (typeof newW === 'number') el.width = Math.max(8, Math.round(newW));
        if (typeof newH === 'number') el.height = Math.max(8, Math.round(newH));
      }

      function onPointerDown(e) {
        var isTouch = e.type.indexOf('touch') === 0;
        var clientX = isTouch ? e.touches[0].clientX : e.clientX;
        var clientY = isTouch ? e.touches[0].clientY : e.clientY;
        var elHandle = getElementHandleAt(clientX, clientY);
        var elHit = getElementAt(clientX, clientY);
        var segHit = getSegmentAt(clientX, clientY);

        internal.dragStart = { x: clientX, y: clientY, time: Date.now() };
        internal.dragState = {};

        if (elHandle) {
          internal.dragging = true;
          internal.dragMode = 'resize-element';
          internal.dragState = { elementIndex: elHandle.index, handle: elHandle.handle, startEl: deepClone(internal.layout.elements[elHandle.index]) };
          document.body.style.userSelect = 'none';
        } else if (elHit) {
          var idx = elHit.index;
          if (internal.selectedElements.indexOf(idx) === -1) {
            internal.selectedElements = [idx];
            bus.emit('elements:selected', deepClone(internal.selectedElements));
          }
          internal.dragging = true;
          internal.dragMode = 'move-elements';
          internal.dragState = { startMouse: clientToLogical(clientX, clientY), elements: internal.selectedElements.slice().map(function (i) { return { index: i, el: deepClone(internal.layout.elements[i]) }; }) };
          document.body.style.userSelect = 'none';
        } else if (segHit) {
          var seg = segHit.segment;

          // If clicked segment is already part of current multi-selection, keep selection.
          var alreadySelected = (internal.selectedSegments || []).some(function (s) { return s.row === seg.row && s.segIndex === seg.segIndex; });

          if (!alreadySelected) {
            // select only clicked segment
            internal.selectedSegments = [{ row: seg.row, segIndex: seg.segIndex }];
            bus.emit('segments:selected', deepClone(internal.selectedSegments));
          } else {
            // keep existing selection (multi-select) — emit to ensure UI sync
            bus.emit('segments:selected', deepClone(internal.selectedSegments));
          }

          internal.dragging = true;
          internal.dragMode = 'move-segments';

          var rowNumber = seg.row;
          var rowObj = (internal.layout.rows || []).find(function (r) { return r.number === rowNumber; });
          var startOffsetsPx = [];
          if (rowObj && Array.isArray(rowObj.segments)) {
            var baseSeat = Number(internal.layout.meta && internal.layout.meta.baseSeatSize) || internal.seatSize;
            var gapX = Number(internal.layout.meta && internal.layout.meta.gapX) || internal.gapX;
            var gapY = Number(internal.layout.meta && internal.layout.meta.gapY) || internal.gapY;
            var anchors = internal.layout.anchors || {};
            rowObj.segments.forEach(function (s, si) {
              var anchorName = s.anchor || Object.keys(anchors)[0];
              var anchor = (anchors && anchors[anchorName]) || { _px: { x: 0, y: 0 } };
              var anchorPx = (anchor && anchor._px) ? anchor._px : { x: 0, y: 0 };
              var curPxX = (s.offset && typeof s.offset.x === 'number') ? s.offset.x * (baseSeat + gapX) : 0;
              var curPxY = (s.offset && typeof s.offset.y === 'number') ? s.offset.y * (baseSeat + gapY) : 0;
              startOffsetsPx.push({ x: anchorPx.x + curPxX, y: anchorPx.y + curPxY });
            });
          }

          internal.dragState = {
            startMouse: clientToLogical(clientX, clientY),
            segment: seg,
            rowNumber: seg.row,
            segIndex: seg.segIndex,
            startSegPx: { x: seg.x, y: seg.y },
            startOffsetsPx: {}
          };

          internal.dragState.startOffsetsPx[seg.row] = startOffsetsPx;
document.body.style.userSelect = 'none';
        } else {
          internal.dragging = true;
          internal.dragMode = 'select-rect';
          internal.dragState = { rectStart: clientToLogical(clientX, clientY), rectCurrent: clientToLogical(clientX, clientY) };
          internal.selectedSegments = [];
          internal.selectedElements = [];
          bus.emit('segments:selected', deepClone(internal.selectedSegments));
          bus.emit('elements:selected', deepClone(internal.selectedElements));
          document.body.style.userSelect = 'none';
        }
        draw();
      }

      function onPointerMove(e) {
        if (!internal.dragging) return;
        var isTouch = e.type.indexOf('touch') === 0;
        var clientX = isTouch ? e.touches[0].clientX : e.clientX;
        var clientY = isTouch ? e.touches[0].clientY : e.clientY;
        var p = clientToLogical(clientX, clientY);

        if (internal.dragMode === 'select-rect') {
          internal.dragState.rectCurrent = p;
          var r0 = internal.dragState.rectStart, r1 = internal.dragState.rectCurrent;
          var left = Math.min(r0.x, r1.x), right = Math.max(r0.x, r1.x), top = Math.min(r0.y, r1.y), bottom = Math.max(r0.y, r1.y);
          var selSegs = [];
          (internal._computed && internal._computed.segments || []).forEach(function (seg) {
            if (!(seg.x > right || seg.x + seg.w < left || seg.y > bottom || seg.y + seg.h < top)) {
              selSegs.push({ row: seg.row, segIndex: seg.segIndex });
            }
          });
          var selEls = [];
          (internal.layout.elements || []).forEach(function (el, i) {
            var ex = el.x || 0, ey = el.y || 0, ew = el.width || el.w || 60, eh = el.height || el.h || 30;
            if (!(ex > right || ex + ew < left || ey > bottom || ey + eh < top)) selEls.push(i);
          });
          internal.selectedSegments = selSegs;
          internal.selectedElements = selEls;
          bus.emit('segments:selected', deepClone(internal.selectedSegments));
          bus.emit('elements:selected', deepClone(internal.selectedElements));
          draw();
        } else if (internal.dragMode === 'move-elements') {
          var start = internal.dragState.startMouse;
          var dx = p.x - start.x, dy = p.y - start.y;
          dx = snap(dx);
          dy = snap(dy);
          internal.dragState.elements.forEach(function (it) {
            var newX = it.el.x + dx;
            var newY = it.el.y + dy;
            updateElementInLayout(it.index, newX, newY, it.el.width, it.el.height);
          });
          bus.emit('layout:changed', deepClone(internal.layout));
          draw();
        } else if (internal.dragMode === 'resize-element') {
          var st = internal.dragState;
          var elIndex = st.elementIndex;
          var elStart = st.startEl;
          var dx = p.x - clientToLogical(internal.dragStart.x, internal.dragStart.y).x;
          var dy = p.y - clientToLogical(internal.dragStart.x, internal.dragStart.y).y;
          var nx = elStart.x, ny = elStart.y, nw = elStart.width, nh = elStart.height;
          var handle = st.handle;
          if (handle === 'nw') { nx = elStart.x + dx; ny = elStart.y + dy; nw = elStart.width - dx; nh = elStart.height - dy; }
          if (handle === 'ne') { ny = elStart.y + dy; nw = elStart.width + dx; nh = elStart.height - dy; }
          if (handle === 'se') { nw = elStart.width + dx; nh = elStart.height + dy; }
          if (handle === 'sw') { nx = elStart.x + dx; nw = elStart.width - dx; nh = elStart.height + dy; }
          nx = snap(nx); ny = snap(ny); nw = Math.max(8, snap(nw)); nh = Math.max(8, snap(nh));
          updateElementInLayout(elIndex, nx, ny, nw, nh);
          bus.emit('layout:changed', deepClone(internal.layout));
          draw();
        } else if (internal.dragMode === 'move-segments') {
          var st2 = internal.dragState;
          var dxPx = p.x - st2.startMouse.x;
          var dyPx = p.y - st2.startMouse.y;
          if (Math.abs(dxPx) < 0.5 && Math.abs(dyPx) < 0.5) return;

          var baseSeat = Number(internal.layout.meta && internal.layout.meta.baseSeatSize) || internal.seatSize;
          var stepPx = Math.max(1, Math.round(baseSeat / 8));

          var snappedDx = snapToGridPx(dxPx, stepPx);
          var snappedDy = snapToGridPx(dyPx, stepPx);

          updateRowOffsetsByDelta(st2.rowNumber, snappedDx, snappedDy);
          bus.emit('layout:changed', deepClone(internal.layout));
          draw();
        }
      }

      function onPointerUp(e) {
        if (!internal.dragging) return;
        if (internal.dragMode === 'move-elements' || internal.dragMode === 'resize-element' || internal.dragMode === 'move-segments') {
          bus.emit('layout:changed', deepClone(internal.layout));
        }
        internal.dragging = false;
        internal.dragMode = null;
        internal.dragStart = null;
        internal.dragState = null;
        document.body.style.userSelect = '';
        draw();
      }

      function onKeyDown(e) {
        if (e.key === 'Delete' || e.key === 'Backspace') {
          var changed = false;
          if (internal.selectedElements && internal.selectedElements.length) {
            var idxs = internal.selectedElements.slice().sort(function (a, b) { return b - a; });
            idxs.forEach(function (i) { internal.layout.elements.splice(i, 1); changed = true; });
            internal.selectedElements = [];
            bus.emit('elements:selected', deepClone(internal.selectedElements));
          }
          if (internal.selectedSegments && internal.selectedSegments.length) {
            internal.selectedSegments.slice().forEach(function (s) {
              var row = (internal.layout.rows || []).find(function (r) { return r.number === s.row; });
              if (row && Array.isArray(row.segments) && row.segments[s.segIndex]) {
                row.segments.splice(s.segIndex, 1);
                changed = true;
              }
            });
            internal.selectedSegments = [];
            bus.emit('segments:selected', deepClone(internal.selectedSegments));
          }
          if (changed) {
            bus.emit('layout:changed', deepClone(internal.layout));
            draw();
          }
        }
      }

      canvas.addEventListener('mousedown', onPointerDown);
      canvas.addEventListener('mousemove', onPointerMove);
      window.addEventListener('mouseup', onPointerUp);
      canvas.addEventListener('touchstart', function (ev) { onPointerDown(ev); ev.preventDefault(); }, { passive: false });
      canvas.addEventListener('touchmove', function (ev) { onPointerMove(ev); ev.preventDefault(); }, { passive: false });
      window.addEventListener('touchend', function (ev) { onPointerUp(ev); }, { passive: false });
      window.addEventListener('keydown', onKeyDown);

      function setLayout(layout) {
        internal.layout = layout || { meta: {}, anchors: {}, rows: [], elements: [], seats: {} };
        internal.anchors = internal.layout.anchors || internal.anchors;
        internal.seatSize = Number(internal.layout.meta && internal.layout.meta.baseSeatSize) || internal.seatSize;
        // snapStep for element moves (logical units) = one eighth of seat size
        internal.snapStep = Math.max(1, Math.round(internal.seatSize / 8));
        draw();
        bus.emit('layout:changed', deepClone(internal.layout));
      }

      function exportLayout() { return deepClone(internal.layout); }

      function selectSegment(sel) {
        if (!sel) { internal.selectedSegments = []; bus.emit('segments:selected', []); draw(); return; }
        internal.selectedSegments = Array.isArray(sel) ? sel.slice() : [{ row: sel.row, segIndex: sel.segIndex }];
        bus.emit('segments:selected', deepClone(internal.selectedSegments));
        draw();
      }

      function selectElement(index) {
        if (typeof index === 'undefined' || index === null) { internal.selectedElements = []; bus.emit('elements:selected', []); draw(); return; }
        internal.selectedElements = Array.isArray(index) ? index.slice() : [index];
        bus.emit('elements:selected', deepClone(internal.selectedElements));
        draw();
      }

      function on(name, fn) { bus.on(name, fn); }
      function off(name, fn) { bus.off(name, fn); }
      function getInternal() { return internal; }

      resizeCanvasForHiDPI();
      draw();

      var api = {
        init: function () { draw(); },
        draw: draw,
        setLayout: setLayout,
        exportLayout: exportLayout,
        selectSegment: selectSegment,
        selectElement: selectElement,
        on: on,
        off: off,
        getInternal: getInternal,
        _internal: internal
      };

      instances[canvasId] = api;
      return api;
    }

    function getInstance(canvasId) { return instances[canvasId] || null; }

    return { init: init, getInstance: getInstance };
  })();

  global.SeatingCanvas = SeatingCanvas;
})(window);			