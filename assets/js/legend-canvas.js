// legend-canvas.js
// Легенда для seatmap — рисуется в DOM внутри заголовка h3.card-title "Схема рассадки"
// API: LegendCanvas.init(canvasId, opts) -> instance
// instance.attachToRenderer(renderer)
// instance.setPricedColors(map)
// instance.setManualCounts({ free, sold, reserved, total })
// instance.renderLegend()

(function (global) {
  'use strict';

  var LegendCanvas = (function () {
    var instances = {};

    function findHeaderElement() {
      // Ищем h3.card-title с текстом "Схема рассадки" (учитываем возможные пробелы/варианты)
      var headers = document.querySelectorAll('h3.card-title');
      for (var i = 0; i < headers.length; i++) {
        try {
          var txt = (headers[i].textContent || '').trim();
          if (!txt) continue;
          if (txt.indexOf('Схема рассадки') !== -1) return headers[i];
        } catch (e) {}
      }
      // fallback: первый h3.card-title
      return headers.length ? headers[0] : null;
    }

    function Legend(canvasId, opts) {
      opts = opts || {};
      this.canvasId = canvasId;
      this.boxSize = Number(opts.boxSize || 12);
      this.gap = Number(opts.gap || 8);
      this.itemGap = Number(opts.itemGap || 12);
      this.fontSize = Number(opts.fontSize || 13);
      this.textColor = opts.textColor || '#222';
      this.pricedColors = {}; // price -> color
      this.renderer = null;
      this._manualCounts = null;
      this._container = opts.container || null; // optional explicit DOM container
      this._containerWasPassed = !!opts.container;
      this._headerEl = null;
      this._initDom();
      // keep responsive
      var self = this;
      window.addEventListener('resize', function () { try { self._layout(); } catch (e) {} });
    }

    Legend.prototype._initDom = function () {
      try {
        var header = findHeaderElement();
        this._headerEl = header;
        if (!header) {
          // create a small fallback container near canvas if header not found
          var canvas = document.getElementById(this.canvasId);
          if (canvas && canvas.parentElement) {
            header = document.createElement('div');
            header.className = 'legend-header-fallback';
            header.style.display = 'flex';
            header.style.alignItems = 'center';
            header.style.marginTop = '6px';
            canvas.parentElement.insertBefore(header, canvas);
            this._headerEl = header;
          } else {
            // last resort: body
            this._headerEl = document.body;
          }
        }

        // Ensure header is flex so we can push legend to the end of the row
        try {
          var cs = window.getComputedStyle(this._headerEl);
          if (cs && cs.display !== 'flex') {
            this._headerEl.style.display = 'flex';
            this._headerEl.style.alignItems = 'center';
          }
        } catch (e) {}

        // Create legend container (inline, at end) or use explicit container
        var container = this._container;
        if (!container) {
          container = document.createElement('div');
          container.className = 'legend-inline';
        }
        container.innerHTML = '';
        container.style.display = 'inline-flex';
        container.style.alignItems = 'center';
        container.style.marginLeft = 'auto'; // push to end of header row
        container.style.gap = (this.itemGap) + 'px';
        container.style.fontSize = (this.fontSize) + 'px';
        container.style.color = this.textColor;
        container.style.lineHeight = '1';
        container.style.whiteSpace = 'nowrap';
        container.style.userSelect = 'none';
        container.style.pointerEvents = 'auto';
        container.style.paddingLeft = '6px';
        container.style.paddingRight = '6px';

        // remove previous legend if exists
        try {
          var existing = this._headerEl.querySelector('.legend-inline[data-legend-for="' + this.canvasId + '"]');
          if (existing) existing.remove();
        } catch (e) {}

        container.setAttribute('data-legend-for', this.canvasId);
        this._container = container;
        // append to header only if we created it ourselves
        if (!this._containerWasPassed) {
          try { this._headerEl.appendChild(container); } catch (e) { document.body.appendChild(container); }
        }
        this._layout();
      } catch (e) {
        // silent
      }
    };

    Legend.prototype._layout = function () {
      // ensure container stays at end of header row; if header is not flex, set margin-left:auto on container
      try {
        if (!this._headerEl || !this._container) return;
        var cs = window.getComputedStyle(this._headerEl);
        if (cs && cs.display !== 'flex') {
          this._headerEl.style.display = 'flex';
          this._headerEl.style.alignItems = 'center';
        }
        // ensure container has margin-left:auto to push it to the end
        this._container.style.marginLeft = 'auto';
      } catch (e) {}
    };

    Legend.prototype.attachToRenderer = function (renderer) {
      try {
        this.renderer = renderer;
        // try to update counts immediately
        this.renderLegend();
      } catch (e) {}
    };

    Legend.prototype.setPricedColors = function (map) {
      try {
        this.pricedColors = map || {};
        this.renderLegend();
      } catch (e) {}
    };

    Legend.prototype.setManualCounts = function (countsObj) {
      try {
        this._manualCounts = countsObj || null;
        this.renderLegend();
      } catch (e) {}
    };

    Legend.prototype._computeCountsFromRenderer = function () {
      // If manual counts provided, use them
      try {
        if (this._manualCounts && typeof this._manualCounts === 'object') {
          var mc = this._manualCounts;
          return {
            free: (mc.free === null || mc.free === undefined) ? null : Number(mc.free),
            sold: (mc.sold === null || mc.sold === undefined) ? null : Number(mc.sold),
            reserved: (mc.reserved === null || mc.reserved === undefined) ? null : Number(mc.reserved),
            total: (mc.total === null || mc.total === undefined) ? null : Number(mc.total)
          };
        }

        if (!this.renderer || typeof this.renderer.getInternal !== 'function') return null;
        var internal = this.renderer.getInternal();
        if (!internal) return null;

        // Prefer explicit lists if renderer exposes them
        var soldList = null, reservedList = null;
        try {
          if (Array.isArray(internal.soldSeats)) soldList = internal.soldSeats.slice();
          if (Array.isArray(internal.reservedSeats)) reservedList = internal.reservedSeats.slice();
          if (!soldList && Array.isArray(internal._soldSeats)) soldList = internal._soldSeats.slice();
          if (!reservedList && Array.isArray(internal._reservedSeats)) reservedList = internal._reservedSeats.slice();
        } catch (e) {}

        var seatsMap = internal._computed && internal._computed.seats ? internal._computed.seats : null;
        var total = 0, sold = 0, reserved = 0, priced = 0;
        var pricedSeats = internal.pricedSeats || {};
        // Карта цен в кассе заполняется только для мест с ценой > 0. Чтобы не ломать
        // страницы без карты цен (расписание, касса со старым seatmap), считаем
        // «доступными» по цене только если карта содержит хотя бы одно значение > 0.
        var hasPositivePriceMap = false;
        Object.keys(pricedSeats).forEach(function (k) {
          if (Number(pricedSeats[k]) > 0) hasPositivePriceMap = true;
        });
        if (seatsMap && typeof seatsMap === 'object') {
          Object.keys(seatsMap).forEach(function (k) {
            var s = seatsMap[k];
            if (!s) return;
            total++;

            var hasPrice = !hasPositivePriceMap;
            if (hasPositivePriceMap) {
              var hy = k.replace(/[:;,.—–]+/g, '-').replace(/^-+|-+$/g, '');
              var price = pricedSeats[k];
              if (price === undefined) price = pricedSeats[hy];
              if (price === undefined) price = pricedSeats[k.replace(/-/g, ':')];
              hasPrice = price !== undefined && Number(price) > 0;
            }
            if (!hasPrice) return;
            priced++;

            var isSold = false, isReserved = false;
            // Проверяем sold/reserved в самом seat-объекте, а также в картах internal
            try {
              isSold = !!(s.sold || s.isSold || s._sold || s.status === 'sold' || s.state === 'sold');
              isReserved = !!(s.reserved || s.isReserved || s._reserved || s.status === 'reserved' || s.state === 'reserved');
            } catch (e) {}
            if (!isSold && internal.soldSeats && internal.soldSeats[k]) isSold = true;
            if (!isReserved && internal.reservedSeats && internal.reservedSeats[k]) isReserved = true;
            if (!isSold && soldList && soldList.indexOf(k) !== -1) isSold = true;
            if (!isReserved && reservedList && reservedList.indexOf(k) !== -1) isReserved = true;
            if (isSold) sold++;
            else if (isReserved) reserved++;
          });
          var free = Math.max(0, priced - sold - reserved);
          return { free: free, sold: sold, reserved: reserved, total: total };
        }

        // If no seats map but explicit lists exist, return counts with unknown total/free
        if (soldList || reservedList) {
          sold = soldList ? soldList.length : 0;
          reserved = reservedList ? reservedList.length : 0;
          return { free: null, sold: sold, reserved: reserved, total: null };
        }

        return null;
      } catch (e) {
        return null;
      }
    };

    Legend.prototype._clearContainer = function () {
      try {
        if (!this._container) return;
        while (this._container.firstChild) this._container.removeChild(this._container.firstChild);
      } catch (e) {}
    };

    Legend.prototype.renderLegend = function () {
      try {
        if (!this._container) this._initDom();
        if (!this._container) return;
        this._layout();
        this._clearContainer();

        // compute counts
        var counts = this._computeCountsFromRenderer();
        var freeText = '—';
        if (counts && typeof counts.free === 'number') freeText = String(counts.free);

        // build entries: "Доступно" (text only, no color box), then priced colors sorted by numeric price
        var entries = [];
        try {
          Object.keys(this.pricedColors || {}).forEach(function (p) {
            entries.push({ price: p, color: (this.pricedColors[p] || this.pricedColors[String(p)] || '#999') });
          }.bind(this));
          entries.sort(function (a, b) {
            var na = Number(a.price), nb = Number(b.price);
            if (!isNaN(na) && !isNaN(nb)) return na - nb;
            return String(a.price).localeCompare(String(b.price));
          });
        } catch (e) { entries = []; }

        // helper to create item DOM with color box
        var createItem = function (label, color, stroke) {
          var wrap = document.createElement('span');
          wrap.style.display = 'inline-flex';
          wrap.style.alignItems = 'center';
          wrap.style.gap = (this.gap) + 'px';
          wrap.style.marginRight = '0px';
          wrap.style.whiteSpace = 'nowrap';
          // box
          var box = document.createElement('span');
          box.style.display = 'inline-block';
          box.style.width = this.boxSize + 'px';
          box.style.height = this.boxSize + 'px';
          box.style.background = color || '#ccc';
          box.style.border = '1px solid ' + (stroke || '#ddd');
          box.style.borderRadius = '3px';
          box.style.flex = '0 0 auto';
          // label
          var lbl = document.createElement('span');
          lbl.textContent = label;
          lbl.style.fontSize = (this.fontSize) + 'px';
          lbl.style.color = this.textColor;
          lbl.style.marginLeft = '0px';
          lbl.style.flex = '0 0 auto';
          wrap.appendChild(box);
          wrap.appendChild(lbl);
          return wrap;
        }.bind(this);

        // "Доступно" item: text only, no color box
        var availableWrap = document.createElement('span');
        availableWrap.style.display = 'inline-flex';
        availableWrap.style.alignItems = 'center';
        availableWrap.style.gap = (this.gap) + 'px';
        availableWrap.style.whiteSpace = 'nowrap';
        var availableLabel = document.createElement('span');
        availableLabel.textContent = 'Доступно: ' + freeText;
        availableLabel.style.fontSize = (this.fontSize) + 'px';
        availableLabel.style.color = this.textColor;
        availableLabel.style.fontWeight = '600';
        availableWrap.appendChild(availableLabel);
        this._container.appendChild(availableWrap);

        // price items
        entries.forEach(function (it) {
          var num = Number(it.price);
          var label = (!isNaN(num)) ? ((Math.round(num) === num) ? (num + ' тг') : (num.toFixed(2) + ' тг')) : String(it.price);
          var item = createItem(label, it.color || '#ccc', '#ddd');
          this._container.appendChild(item);
        }.bind(this));

        // ensure vertical centering
        try {
          this._container.style.alignItems = 'center';
        } catch (e) {}

      } catch (e) {
        // silent
      }
    };

    return {
      init: function (canvasId, opts) {
        if (!canvasId) canvasId = '';
        if (!instances[canvasId]) {
          try { instances[canvasId] = new Legend(canvasId, opts || {}); } catch (e) { instances[canvasId] = null; }
        }
        return instances[canvasId];
      },
      getInstance: function (canvasId) {
        return instances[canvasId] || null;
      }
    };
  })();

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = LegendCanvas;
  } else {
    global.LegendCanvas = LegendCanvas;
  }
})(window);
