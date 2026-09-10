// /assets/js/schedule-seating-canvas.js
// Рендерер схемы зала (только отрисовка), используется на страницах расписания и в кассе.
// Вариант "trimmed" редактора залов — только визуализация.
// Экспортирует SeatingCanvasRender.init(canvasId, opts) и SeatingCanvasRender.getInstance(canvasId).
// Публичные методы: setLayout(layout), exportLayout(), fitContentToView(), resize(w,h), renderCanvas(), getInternal()
// Дополнительно (опционально): applyPriceMap(pricedSeats, pricedColors), setSoldSeats(soldKeys, soldColor), setReservedSeats(reservedKeys, ownedKeys, reservedColor)
// Опция hideLabelsWhenNoPrice (opts.hideLabelsWhenNoPrice) — если true, на странице кассы номера мест без цены не показываются.

(function (global) {
  'use strict';

  var SeatingCanvasRender = (function () {
    var instances = {};

    function createEventBus() {
      var handlers = {};
      return {
        on: function (name, fn) {
          if (typeof fn !== 'function') return;
          handlers[name] = handlers[name] || [];
          handlers[name].push(fn);
        },
        off: function (name, fn) {
          if (!handlers[name]) return;
          if (!fn) {
            handlers[name] = [];
            return;
          }
          handlers[name] = handlers[name].filter(function (handler) { return handler !== fn; });
        },
        emit: function (name) {
          var args = Array.prototype.slice.call(arguments, 1);
          (handlers[name] || []).slice().forEach(function (handler) {
            try { handler.apply(null, args); } catch (e) { /* ignore listener errors */ }
          });
        }
      };
    }

    function deepClone(obj) { try { return JSON.parse(JSON.stringify(obj)); } catch (e) { return obj; } }

    function init(canvasId, opts) {
      opts = opts || {};
      var canvas = document.getElementById(canvasId);
      if (!canvas) return null;
      var ctx = canvas.getContext('2d');
      var bus = createEventBus();

      // Если передана готовая схема в opts.seatmap — используем её
      if (opts.seatmap && typeof opts.seatmap === 'object') {
        opts.layout = opts.seatmap;
      }

      // Сохраняем начальные логические размеры (полезно, если CSS/markup задаёт их)
      var initialLogicalWidth = (function () {
        var w = canvas.getAttribute('data-logical-width');
        if (w) return parseFloat(w);
        if (canvas.clientWidth && canvas.clientWidth > 0) return canvas.clientWidth;
        var attrW = canvas.getAttribute('width');
        if (attrW) return parseFloat(attrW);
        return opts.defaultWidth || 1192;
      })();

      var initialLogicalHeight = (function () {
        var h = canvas.getAttribute('data-logical-height');
        if (h) return parseFloat(h);
        if (canvas.clientHeight && canvas.clientHeight > 0) return canvas.clientHeight;
        var attrH = canvas.getAttribute('height');
        if (attrH) return parseFloat(attrH);
        return opts.defaultHeight || 760;
      })();

      var internal = {
        id: canvasId,
        canvas: canvas,
        ctx: ctx,
        layout: opts.layout || opts.seatmap || { meta: {}, anchors: {}, rows: [], elements: [], seats: {} },
        seatSize: Number(opts.seatSize) || 28,
        gapX: Number(opts.gapX) || 8,
        gapY: Number(opts.gapY) || 12,
        showGrid: !!opts.showGrid,
        disableWheelZoom: !!opts.disableWheelZoom,
        plainUnavailableSeats: !!opts.plainUnavailableSeats,
        scale: 1,
        _visual: Object.assign({
          defaultColor: '#dff3ff', defaultStroke: '#7fbfe6', textColor: '#0b3b4a', borderRadius: 4, fontSize: 12
        }, opts._visual || {}),
        debug: !!opts.debug,
        _computed: null,
        // стабильные логические размеры
        _logicalWidth: initialLogicalWidth,
        _logicalHeight: initialLogicalHeight,

        // --- дополнительные поля для динамической раскраски и скрытия меток ---
        pricedSeats: {},            // карта цен: ключ места -> цена (заполняется applyPriceMap)
        pricedColors: {},           // карта цветов по цене
        soldSeats: {},              // проданные места (ключи)
        reservedSeats: {},          // зарезервированные места { owner: 'me' | null, meta: any }
        soldColor: opts.soldColor || '#bdbdbd',           // цвет для проданных мест (по умолчанию)
        reservedColor: opts.reservedColor || '#ffd966',   // цвет для резервов (по умолчанию)
        ownedReservedColor: opts.ownedReservedColor || '#ffb84d', // цвет для резервов текущего пользователя
        hideLabelsWhenNoPrice: !!opts.hideLabelsWhenNoPrice, // если true — скрывать номера без цены (используется в кассе)
        context: opts.context || null, // опциональный контекст ('cash' и т.д.)
        selectedSeats: {}
      };

      // Если у canvas нет CSS высоты, установим стабильную высоту (предотвращает прыжки)
      if (!canvas.style.height || canvas.style.height === '') {
        canvas.style.height = (opts.defaultHeight || internal._logicalHeight || 760) + 'px';
      }

      function resizeCanvasForHiDPI() {
        var ratio = window.devicePixelRatio || 1;

        // Предпочитаем clientWidth/clientHeight (стабильный CSS размер). Иначе — сохранённые логические значения.
        var clientW = Math.max(1, Math.round(canvas.clientWidth || internal._logicalWidth || 1200));
        var clientH = Math.max(1, Math.round(canvas.clientHeight || internal._logicalHeight || 760));

        // Сохраняем логические размеры (если они корректны)
        internal._logicalWidth = clientW;
        internal._logicalHeight = clientH;

        // Записываем атрибуты для внешнего кода, который может их читать
        canvas.setAttribute('data-logical-width', internal._logicalWidth);
        canvas.setAttribute('data-logical-height', internal._logicalHeight);

        // Устанавливаем backing store size для HiDPI
        canvas.width = Math.max(1, Math.round(internal._logicalWidth * ratio));
        canvas.height = Math.max(1, Math.round(internal._logicalHeight * ratio));

        // Трансформ: HiDPI ratio * fit scale, затем сдвиг для fitContentToView
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        internal.scale = fit.scale;
        ctx.setTransform(ratio * fit.scale, 0, 0, ratio * fit.scale, ratio * fit.offsetX, ratio * fit.offsetY);
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
        var cw = internal._logicalWidth || 1200;
        var ch = internal._logicalHeight || 760;
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
              seats[key] = { x: x, y: y, w: baseSeat, h: baseSeat, row: row.number, col: s, key: key, _srcKey: key };
            }
            var segX = anchorPx.x + offXpx;
            var segY = anchorPx.y + offYpx;
            var segW = (end - start + 1) * (baseSeat + gapX) - gapX;
            var segH = baseSeat;
            segments.push({ row: row.number, segIndex: si, start: start, end: end, x: segX, y: segY, w: segW, h: segH, anchor: anchorName, offset: Object.assign({}, off) });
          });
        });

        // объединяем метаданные мест
        Object.keys(seats).forEach(function (k) {
          var s = seats[k];
          var sourceKey = k;
          if (layout.seats && !layout.seats[sourceKey]) {
            sourceKey = k.replace(/-/g, ':');
          }
          if (layout.seats && layout.seats[sourceKey]) {
            var src = layout.seats[sourceKey];
            s._srcKey = sourceKey;
            s.meta = src.meta ? deepClone(src.meta) : (src.meta || {});
            if (s.meta && s.meta.color) s.color = s.meta.color;
            if (s.meta && s.meta.stroke) s.stroke = s.meta.stroke;
            if (s.meta && s.meta.label) s.label = s.meta.label;
          } else {
            s.meta = {};
          }
        });

        return { seats: seats, segments: segments, anchorsPx: anchorsPx, canvas: { width: cw, height: ch }, baseSeat: baseSeat, gapX: gapX, gapY: gapY };
      }

      function clear() {
        // очищаем используя backing store size (device pixels)
        ctx.save();
        var ratio = window.devicePixelRatio || 1;
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        ctx.setTransform(ratio * fit.scale, 0, 0, ratio * fit.scale, ratio * fit.offsetX, ratio * fit.offsetY);
        ctx.clearRect(0, 0, internal._logicalWidth / fit.scale, internal._logicalHeight / fit.scale);
        ctx.restore();
      }

      function draw() {
        // гарантируем соответствие backing store CSS размеру
        resizeCanvasForHiDPI();

        // используем логические размеры для рисования
        var computed = compute(internal.layout);
        internal._computed = computed;

        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };

        // фон — очищаем всю канвас-область в экранных координатах
        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.fillStyle = '#f8fbff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.restore();

        // опциональная сетка
        if (internal.showGrid) {
          var step = Math.max(1, Math.round(internal.seatSize / 8));
          ctx.save();
          ctx.strokeStyle = '#e6eef6';
          ctx.lineWidth = 1;
          for (var x = 0; x < internal._logicalWidth; x += step) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, internal._logicalHeight); ctx.stroke(); }
          for (var y = 0; y < internal._logicalHeight; y += step) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(internal._logicalWidth, y); ctx.stroke(); }
          ctx.restore();
        }

        // рисуем элементы (сцена, декорации и т.д.)
        (internal.layout.elements || []).forEach(function (el) {
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
          ctx.restore();
        });

        // рисуем места
        var seatsMap = computed.seats || {};
        ctx.font = (internal._visual.fontSize || 12) + 'px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        Object.keys(seatsMap).forEach(function (k) {
          var s = seatsMap[k];
          ctx.save();

          // базовые цвета из meta или дефолта
          var fillColor = (s.meta && s.meta.color) ? s.meta.color : (s.color || internal._visual.defaultColor);
          var strokeColor = (s.meta && s.meta.stroke) ? s.meta.stroke : (s.stroke || internal._visual.defaultStroke);

          // переопределение по карте цен (если применена)
          if (internal.pricedSeats && internal.pricedSeats[k] !== undefined) {
            var p = internal.pricedSeats[k];
            if (internal.pricedColors && internal.pricedColors[p]) {
              fillColor = internal.pricedColors[p];
            }
          }

          var unavailable = !!(
            (internal.soldSeats && internal.soldSeats[k]) ||
            (internal.reservedSeats && internal.reservedSeats[k])
          );

          // В виджете занятые места не выделяем цветом, в кассе сохраняем
          // отдельные цвета проданных и зарезервированных мест.
          if (unavailable && internal.plainUnavailableSeats) {
            fillColor = internal._visual.defaultColor;
            strokeColor = internal._visual.defaultStroke;
          } else if (internal.soldSeats && internal.soldSeats[k]) {
            fillColor = internal.soldColor || fillColor;
            strokeColor = internal.soldColor || strokeColor;
          } else if (internal.reservedSeats && internal.reservedSeats[k]) {
            var reserved = internal.reservedSeats[k];
            if (reserved && reserved.owner === 'me') {
              fillColor = internal.ownedReservedColor || internal.reservedColor || fillColor;
            } else {
              fillColor = internal.reservedColor || fillColor;
            }
          }

          ctx.fillStyle = fillColor;
          ctx.strokeStyle = strokeColor;
          ctx.lineWidth = 1;
          roundRect(ctx, Math.round(s.x), Math.round(s.y), Math.round(s.w), Math.round(s.h), s._borderRadius || internal._visual.borderRadius, true, true);

          // определяем метку (лейбл)
          var label = (s.meta && s.meta.label) ? s.meta.label : (s.label || String(s.col || ''));

          // логика показа метки: если включён режим скрывать метки без цены (только для кассы),
          // то проверяем наличие цены сначала в карте цен, затем в s.meta
          var showLabel = !(unavailable && internal.plainUnavailableSeats);
          if (showLabel && internal.hideLabelsWhenNoPrice) {
            var hasPrice = false;
            // 1) проверяем карту цен (applyPriceMap)
            if (internal.pricedSeats && typeof internal.pricedSeats === 'object') {
              if (internal.pricedSeats[k] !== undefined && internal.pricedSeats[k] !== null && internal.pricedSeats[k] !== '') {
                // По умолчанию любое заданное значение считается ценой (включая 0).
                // Если нужно считать 0 как "нет цены", замените условие на Number(internal.pricedSeats[k]) > 0
                hasPrice = true;
              }
            }
            // 2) fallback: проверяем meta у места
            if (!hasPrice && s.meta && typeof s.meta === 'object') {
              if (s.meta.price !== undefined && s.meta.price !== null && s.meta.price !== '') hasPrice = true;
              else if (s.meta.p !== undefined && s.meta.p !== null && s.meta.p !== '') hasPrice = true;
              else if (s.meta.price_cents !== undefined && s.meta.price_cents !== null && s.meta.price_cents !== '') hasPrice = true;
            }
            showLabel = !!hasPrice;
          }

          // рисуем метку только если разрешено
          if (showLabel) {
            ctx.fillStyle = s.textColor || internal._visual.textColor;
            ctx.fillText(label, Math.round(s.x + s.w / 2), Math.round(s.y + s.h / 2));
          }

          if (internal.selectedSeats && internal.selectedSeats[k]) {
            ctx.save();
            ctx.strokeStyle = '#ff8a00';
            ctx.lineWidth = 2;
            ctx.setLineDash([5, 3]);
            ctx.strokeRect(Math.round(s.x) - 2, Math.round(s.y) - 2, Math.round(s.w) + 4, Math.round(s.h) + 4);
            ctx.restore();
          }

          ctx.restore();
        });

        // рисуем контуры сегментов
        (computed.segments || []).forEach(function (seg) {
          ctx.save();
          ctx.strokeStyle = 'rgba(120,120,120,0.12)';
          ctx.lineWidth = 1;
          ctx.setLineDash([4, 4]);
          ctx.strokeRect(Math.round(seg.x), Math.round(seg.y), Math.round(seg.w), Math.round(seg.h));
          ctx.restore();
        });

        // рисуем номера рядов с учётом fit-сдвига и масштаба
        ctx.save();
        ctx.fillStyle = internal._visual.textColor;
        ctx.font = '13px system-ui';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        (internal.layout.rows || []).forEach(function (row) {
          var y = null;
          for (var key in computed.seats) {
            if (!computed.seats.hasOwnProperty(key)) continue;
            var s = computed.seats[key];
            if (s.row === row.number) { y = s.y + (s.h || internal.seatSize) / 2; break; }
          }
          if (y === null) {
            y = (internal.layout.anchors && internal.layout.anchors.layout_origin && internal.layout.anchors.layout_origin._px ? internal.layout.anchors.layout_origin._px.y : 40) + ((row.number || 1) - 1) * (internal.seatSize + internal.gapY) + internal.seatSize / 2;
          }
          // позиция слева от самого левого места ряда с учётом fit
          var rowMinX = Infinity;
          for (var key in computed.seats) {
            if (!computed.seats.hasOwnProperty(key)) continue;
            var s = computed.seats[key];
            if (s.row === row.number && s.x < rowMinX) rowMinX = s.x;
          }
          if (!isFinite(rowMinX)) rowMinX = 40;
          ctx.fillText(String(row.number), Math.round(rowMinX - 10), Math.round(y));
        });
        ctx.restore();
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
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        var scale = fit.scale || 1;
        // переводим координаты клиента в логические координаты схемы с учётом fit-сдвига
        var x = (clientX - rect.left - fit.offsetX) / scale;
        var y = (clientY - rect.top - fit.offsetY) / scale;
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

      function setLayout(layout) {
        internal.layout = layout || { meta: {}, anchors: {}, rows: [], elements: [], seats: {} };
        internal.seatSize = Number(internal.layout.meta && internal.layout.meta.baseSeatSize) || internal.seatSize;
        // reset fit state so next fitToCanvas computes fresh bounds for the new layout
        internal._fit = { scale: 1, offsetX: 0, offsetY: 0 };
        internal.scale = 1;
        draw();
      }

      function getSeatAt(clientX, clientY) {
        // Пересчитываем layout и fit, чтобы координаты всегда соответствовали текущему рендеру
        var computed = compute(internal.layout);
        internal._computed = computed;
        var seatsMap = computed.seats || {};
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        var p = clientToLogical(clientX, clientY);
        var x = p.x;
        var y = p.y;

        for (var key in seatsMap) {
          if (!seatsMap.hasOwnProperty(key)) continue;
          var seat = seatsMap[key];
          if (x >= seat.x && x <= seat.x + seat.w && y >= seat.y && y <= seat.y + seat.h) {
            return {
              key: seat.key || key,
              rawKey: key,
              seat: seat,
              row: seat.row,
              col: seat.col,
              x: x,
              y: y
            };
          }
        }
        return null;
      }

      function setSelectedSeats(keys) {
        internal.selectedSeats = {};
        if (Array.isArray(keys)) {
          keys.forEach(function (key) {
            var hy = normalizeKey(key);
            if (hy) internal.selectedSeats[hy] = true;
          });
        }
        draw();
      }

      function exportLayout() { return deepClone(internal.layout); }

      function fitContentToView() {
        if (!internal.layout) return;
        var computed = compute(internal.layout);
        var seats = computed.seats || {};
        var keys = Object.keys(seats);
        if (keys.length === 0) { draw(); return; }

        var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        keys.forEach(function (k) {
          var s = seats[k];
          if (s.x < minX) minX = s.x;
          if (s.y < minY) minY = s.y;
          if (s.x + s.w > maxX) maxX = s.x + s.w;
          if (s.y + s.h > maxY) maxY = s.y + s.h;
        });
        // include decorative elements in bounding box
        (internal.layout.elements || []).forEach(function (el) {
          var ex = el.x || 0, ey = el.y || 0;
          var ew = el.width || el.w || 60, eh = el.height || el.h || 30;
          if (el.type === 'circle') {
            var r = el.radius || Math.round((ew || eh || 40) / 2);
            ex -= r; ey -= r; ew = r * 2; eh = r * 2;
          }
          if (ex < minX) minX = ex;
          if (ey < minY) minY = ey;
          if (ex + ew > maxX) maxX = ex + ew;
          if (ey + eh > maxY) maxY = ey + eh;
        });

        // Увеличенный отступ слева, чтобы номера рядов не уходили за край canvas
        var padding = 24;
        var leftPadding = 60;
        minX -= leftPadding; minY -= padding;
        maxX += padding; maxY += padding;

        var contentW = maxX - minX;
        var contentH = maxY - minY;
        var canvasW = internal._logicalWidth || canvas.clientWidth || 1200;
        var canvasH = internal._logicalHeight || canvas.clientHeight || 760;

        var scaleX = contentW > 0 ? canvasW / contentW : 1;
        var scaleY = contentH > 0 ? canvasH / contentH : 1;
        var scale = Math.min(scaleX, scaleY, 1);

        // Apply pan/scale transform so content fits and stays centered
        internal._fit = {
          scale: scale,
          offsetX: -minX * scale + (canvasW - contentW * scale) / 2,
          offsetY: -minY * scale + (canvasH - contentH * scale) / 2
        };
        internal.scale = scale;
        draw();
      }

      function fitToCanvas() { fitContentToView(); }

      function resize(w, h) {
        if (typeof w === 'number' && typeof h === 'number') {
          // устанавливаем CSS размер; не меняем его в других местах
          canvas.style.width = Math.max(1, Math.round(w)) + 'px';
          canvas.style.height = Math.max(1, Math.round(h)) + 'px';
          // обновляем сохранённые логические размеры
          internal._logicalWidth = Math.max(1, Math.round(w));
          internal._logicalHeight = Math.max(1, Math.round(h));
        }
        draw();
      }

      function getInternal() { return internal; }

      function renderCanvas() { draw(); }

      function render() { draw(); }

      function load(layout) { setLayout(layout && layout.layout ? layout.layout : layout); }

      function on(name, fn) { bus.on(name, fn); }

      function off(name, fn) { bus.off(name, fn); }

      canvas.addEventListener('click', function (event) {
        var hit = getSeatAt(event.clientX, event.clientY);
        if (!hit) return;
        var seatKey = hit.seat && hit.seat.key ? hit.seat.key : hit.key;
        bus.emit('seat:click', seatKey, hit);
      });

      function normalizeKey(k) {
        if (k === null || k === undefined) return '';
        var s = String(k).trim();
        s = s.replace(/\s+/g, '');
        s = s.replace(/[:;,.—–]+/g, '-');
        s = s.replace(/-+/g, '-');
        s = s.replace(/^-+|-+$/g, '');
        return s;
      }

      // --- публичные методы для кассы/динамики ---
      function applyPriceMap(pricedSeats, pricedColors) {
        internal.pricedSeats = {};
        if (pricedSeats && typeof pricedSeats === 'object') {
          Object.keys(pricedSeats).forEach(function (k) {
            var hy = normalizeKey(k);
            if (hy) internal.pricedSeats[hy] = pricedSeats[k];
          });
        }
        internal.pricedColors = (pricedColors && typeof pricedColors === 'object') ? pricedColors : {};
        draw();
      }

      function setSoldSeats(soldKeys, soldColor) {
        internal.soldSeats = {};
        if (Array.isArray(soldKeys)) {
          soldKeys.forEach(function (k) {
            var hy = normalizeKey(k);
            if (hy) internal.soldSeats[hy] = true;
          });
        }
        if (soldColor) internal.soldColor = soldColor;
        draw();
      }

      function setReservedSeats(reservedKeys, ownedKeys, reservedColor) {
        internal.reservedSeats = {};
        if (Array.isArray(reservedKeys)) {
          reservedKeys.forEach(function (k) {
            var hy = normalizeKey(k);
            if (hy) internal.reservedSeats[hy] = { owner: null };
          });
        }
        if (Array.isArray(ownedKeys)) {
          ownedKeys.forEach(function (k) {
            var hy = normalizeKey(k);
            if (hy) {
              internal.reservedSeats[hy] = internal.reservedSeats[hy] || {};
              internal.reservedSeats[hy].owner = 'me';
            }
          });
        }
        if (reservedColor) internal.reservedColor = reservedColor;
        draw();
      }

      function setPlainUnavailableSeats(enabled) {
        internal.plainUnavailableSeats = !!enabled;
        draw();
      }

      // --- pan / zoom state ---
      var panState = {
        isDragging: false,
        startX: 0,
        startY: 0,
        startOffsetX: 0,
        startOffsetY: 0
      };

      function panBy(dx, dy) {
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        fit.offsetX += dx;
        fit.offsetY += dy;
        internal._fit = fit;
        draw();
      }

      function panTo(x, y) {
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        fit.offsetX = x;
        fit.offsetY = y;
        internal._fit = fit;
        draw();
      }

      function resetView() {
        fitContentToView();
      }

      function zoomTo(newScale, centerX, centerY) {
        var fit = internal._fit || { scale: internal.scale || 1, offsetX: 0, offsetY: 0 };
        var rect = canvas.getBoundingClientRect();
        var cx = (typeof centerX === 'number') ? centerX : rect.width / 2;
        var cy = (typeof centerY === 'number') ? centerY : rect.height / 2;
        var oldScale = fit.scale || 1;
        var scaleRatio = newScale / oldScale;
        fit.offsetX = cx - (cx - fit.offsetX) * scaleRatio;
        fit.offsetY = cy - (cy - fit.offsetY) * scaleRatio;
        fit.scale = newScale;
        internal.scale = newScale;
        internal._fit = fit;
        draw();
      }

      canvas.addEventListener('mousedown', function (e) {
        if (e.button !== 0) return;
        var hit = getSeatAt(e.clientX, e.clientY);
        if (hit) return; // let click handler work
        panState.isDragging = true;
        panState.startX = e.clientX;
        panState.startY = e.clientY;
        var fit = internal._fit || { offsetX: 0, offsetY: 0 };
        panState.startOffsetX = fit.offsetX || 0;
        panState.startOffsetY = fit.offsetY || 0;
        canvas.style.cursor = 'grabbing';
      });

      window.addEventListener('mousemove', function (e) {
        if (!panState.isDragging) return;
        var dx = e.clientX - panState.startX;
        var dy = e.clientY - panState.startY;
        panTo(panState.startOffsetX + dx, panState.startOffsetY + dy);
      });

      window.addEventListener('mouseup', function () {
        if (panState.isDragging) {
          panState.isDragging = false;
          canvas.style.cursor = 'grab';
        }
      });

      canvas.addEventListener('wheel', function (e) {
        if (internal.disableWheelZoom) return;
        e.preventDefault();
        var fit = internal._fit || { scale: 1, offsetX: 0, offsetY: 0 };
        var delta = -e.deltaY || 0;
        var step = delta > 0 ? 0.1 : -0.1;
        var newScale = Math.max(0.3, Math.min(4, (fit.scale || 1) + step));
        zoomTo(newScale, e.clientX - canvas.getBoundingClientRect().left, e.clientY - canvas.getBoundingClientRect().top);
      }, { passive: false });

      // инициализация
      resizeCanvasForHiDPI();
      draw();

      var api = {
        load: load,
        setLayout: setLayout,
        exportLayout: exportLayout,
        fitContentToView: fitContentToView,
        fitToCanvas: fitToCanvas,
        resize: resize,
        getInternal: getInternal,
        getSeatAt: getSeatAt,
        selectSeats: setSelectedSeats,
        renderCanvas: renderCanvas,
        render: render,
        on: on,
        off: off,
        applyPriceMap: applyPriceMap,
        setSoldSeats: setSoldSeats,
        setReservedSeats: setReservedSeats,
        setPlainUnavailableSeats: setPlainUnavailableSeats,
        panBy: panBy,
        panTo: panTo,
        zoomTo: zoomTo,
        resetView: resetView,
        _internal: internal
      };

      instances[canvasId] = api;
      return api;
    }

    function getInstance(canvasId) { return instances[canvasId] || null; }

    return { init: init, getInstance: getInstance };
  })();

  global.SeatingCanvasRender = SeatingCanvasRender;
  if (!global.SeatmapRenderer) {
    global.SeatmapRenderer = {
      create: function (opts) {
        opts = opts || {};
        var canvasId = opts.canvasId || opts.id || 'cash-seatmap';
        return SeatingCanvasRender.init(canvasId, opts) || null;      },
      init: function (opts) {
        opts = opts || {};
        var canvasId = opts.canvasId || opts.id || 'cash-seatmap';
        return SeatingCanvasRender.init(canvasId, opts) || null;      }
    };
  }
})(window);
