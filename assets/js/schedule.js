// assets/js/schedule.js
// Frontend for schedule module: list, form, and integration with SessionPriceEditor.
// Confirmations are shown only for main bottom buttons: Save, Cancel, К расписанию.
// Other actions use toast notifications (no modal).

(function () {
  'use strict';

  // Toast deduplicator + DOM fallback with configurable duration
  var _nativeShowToast = (typeof window !== 'undefined' && typeof window.showToast === 'function') ? window.showToast : null;
  var _lastToast = { msg: null, time: 0 };
  var _toastCount = 0;
  function showToast(msg, type, durationMs) {
    durationMs = typeof durationMs === 'number' ? durationMs : 8000; // longer default so message can be read
    try {
      var now = Date.now();
      if (msg === _lastToast.msg && (now - _lastToast.time) < 800) return;
      _lastToast.msg = msg; _lastToast.time = now;
    } catch (e) {}
    if (typeof _nativeShowToast === 'function') {
      try { _nativeShowToast(msg, type || 'info'); return; } catch (e) {}
    }
    // DOM fallback toast
    try {
      var bg = (type === 'success') ? '#2d9c2d' : ((type === 'error') ? '#c0392b' : '#2d6fa3');
      var el = document.createElement('div');
      el.className = 'sa-toast';
      el.textContent = msg;
      el.style.position = 'fixed';
      el.style.right = '20px';
      el.style.top = (20 + (_toastCount * 64)) + 'px';
      el.style.background = bg;
      el.style.color = '#fff';
      el.style.padding = '10px 14px';
      el.style.borderRadius = '8px';
      el.style.boxShadow = '0 6px 18px rgba(0,0,0,0.12)';
      el.style.zIndex = 99999;
      el.style.maxWidth = '360px';
      el.style.lineHeight = '1.3';
      el.style.fontSize = '13px';
      el.style.opacity = '0';
      el.style.transition = 'opacity 180ms ease, transform 180ms ease';
      el.style.transform = 'translateY(-6px)';
      document.body.appendChild(el);
      // force reflow
      void el.offsetWidth;
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
      _toastCount++;
      setTimeout(function () {
        try {
          el.style.opacity = '0';
          el.style.transform = 'translateY(-6px)';
          setTimeout(function () {
            try { el.remove(); } catch (e) {}
            _toastCount = Math.max(0, _toastCount - 1);
          }, 200);
        } catch (e) {}
      }, durationMs);
    } catch (e) {
      // last resort
      console.log((type || 'info') + ': ' + msg);
    }
  }

  function showAlertAsToast(message) {
    showToast(message, 'error', 8000);
    return Promise.resolve(true);
  }

  function confirmAction(message, meta) {
    meta = meta || {};
    return new Promise(function (resolve) {
      if (typeof window.showModalDelete === 'function') {
        try { window.showModalDelete(message || '', meta || {}); } catch (e) {}
      } else {
        showToast(message, 'info', 6000);
      }
      var resolved = false;
      function resolver(result) {
        if (resolved) return;
        resolved = true;
        try { delete window._modalDeleteResolver; } catch (e) {}
        resolve(Boolean(result));
      }
      try { window._modalDeleteResolver = resolver; } catch (e) {}
      setTimeout(function () { if (!resolved) resolver(false); }, 10000);
    });
  }

  function escapeHtml(s) {
    if (s === null || typeof s === 'undefined') return '';
    return String(s).replace(/[&<>"']/g, function (m) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]; });
  }
  function escapeHtmlAttr(s) {
    return escapeHtml(s).replace(/"/g, '&quot;');
  }

  // Helper: sanitize incoming layout before passing to renderer
  function sanitizeIncomingLayout(layout) {
    if (!layout || typeof layout !== 'object') return layout;
    try {
      var copy = JSON.parse(JSON.stringify(layout));
      copy.seats = copy.seats || {};
      if (Array.isArray(copy.seats)) {
        var map = {};
        copy.seats.forEach(function (el) {
          if (!el || typeof el !== 'object') return;
          var keys = Object.keys(el || {});
          if (keys.length === 1) {
            var k = keys[0];
            map[k] = el[k];
          }
        });
        if (Object.keys(map).length) copy.seats = map;
        else copy.seats = {};
      } else {
        Object.keys(copy.seats).forEach(function (k) {
          var s = copy.seats[k];
          if (!s || typeof s !== 'object' || Array.isArray(s)) {
            copy.seats[k] = {};
          } else {
            if (s.meta && (typeof s.meta !== 'object' || Array.isArray(s.meta))) {
              delete s.meta;
            }
          }
        });
      }
      return copy;
    } catch (e) {
      return layout;
    }
  }

  // --- ListModule ---
  var ListModule = (function () {
    var cfg = { listContainer: null, csrf: '' };

    function init(options) {
      cfg.listContainer = document.querySelector(options.containerSelector || '#schedulesTbody');
      cfg.csrf = options.csrf || '';
      bindEvents();
      loadList();
    }

    function bindEvents() {
      var btnFilter = document.getElementById('btnFilter');
      var btnClear = document.getElementById('btnClear');
      if (btnFilter) btnFilter.addEventListener('click', loadList);
      if (btnClear) {
        btnClear.addEventListener('click', function () {
          var fh = document.getElementById('filter_hall');
          var fe = document.getElementById('filter_event');
          var fd = document.getElementById('filter_date');
          if (fh) fh.value = '';
          if (fe) fe.value = '';
          if (fd) fd.value = '';
          loadList();
        });
      }

      document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.js-delete-schedule');
        if (!btn) return;
        e.stopPropagation();
        var id = btn.getAttribute('data-id');
        doDelete(id, btn);
      }, false);
    }

    function loadList() {
      var hall = (document.getElementById('filter_hall') || {}).value || '';
      var eventId = (document.getElementById('filter_event') || {}).value || '';
      var date = (document.getElementById('filter_date') || {}).value || '';

      var params = new URLSearchParams();
      params.append('action', 'list');
      if (hall) params.append('hall_id', hall);
      if (eventId) params.append('event_id', eventId);
      if (date) params.append('date', date);

      fetch('/ajax/schedule.php?' + params.toString(), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (!resp || !resp.success) {
            if (resp && resp.message) showToast(resp.message, 'error', 8000);
            return;
          }
          renderList(resp.data || []);
        }).catch(function (err) {
          console.error('Load schedules error', err);
          showToast('Ошибка загрузки списка', 'error', 8000);
        });
    }

    function renderList(rows) {
      var tbody = cfg.listContainer;
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="9">Сеансов не найдено.</td></tr>';
        return;
      }
      rows.forEach(function (r, idx) {
        var tr = document.createElement('tr');
        tr.className = 'hall-row';
        tr.setAttribute('data-id', r.id);
        tr.innerHTML = '<td>' + (idx + 1) + '</td>' +
          '<td>' + escapeHtml(r.event_title || '') + '</td>' +
          '<td>' + escapeHtml(r.hall_name || '') + '</td>' +
          '<td>' + (r.start_time || '') + '</td>' +
          '<td>' + (r.end_time || '') + '</td>' +
          '<td>' + (r.base_price !== null ? r.base_price : '') + '</td>' +
          '<td>' + (r.price_ranges_count || 0) + '</td>' +
          '<td>' + (r.status || '') + '</td>' +
          '<td class="actions actions--center">' +
            '<a class="btn btn-ghost btn-sm" href="/schedule/edit.php?id=' + encodeURIComponent(r.id) + '">Редактировать</a> ' +
            '<button class="btn btn-danger btn-sm js-delete-schedule" data-id="' + encodeURIComponent(r.id) + '" data-title="' + escapeHtmlAttr(r.event_title || '') + '">Удалить</button>' +
          '</td>';
        tbody.appendChild(tr);
      });
    }

    function doDelete(id, btn) {
      var body = new URLSearchParams();
      body.append('action', 'delete');
      body.append('id', id);
      body.append('csrf_token', cfg.csrf || window.APP_CSRF_TOKEN || '');

      if (btn) { btn.disabled = true; btn._orig = btn.innerHTML; btn.innerHTML = 'Удаление...'; }

      fetch('/ajax/schedule.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); })
        .then(function (resp) {
          if (btn) { btn.disabled = false; btn.innerHTML = btn._orig || 'Удалить'; }
          if (resp && resp.success) {
            showToast('Сеанс удалён', 'success', 8000);
            loadList();
          } else {
            showToast((resp && resp.message) ? resp.message : 'Ошибка удаления', 'error', 8000);
          }
        }).catch(function (err) {
          console.error('Delete schedule error', err);
          if (btn) { btn.disabled = false; btn.innerHTML = btn._orig || 'Удалить'; }
          showToast('Ошибка сети', 'error', 8000);
        });
    }

    return { init: init };
  })();

  // --- FormModule ---
  var FormModule = (function () {
    var cfg = { csrf: '' };
    // track last overlap state
    var _lastOverlap = { overlap: false, conflicting: null };
    var _overlapTimer = null;
    var _overlapDebounceMs = 300;

    // track sales validation state
    var _salesError = { hasError: false, fields: [], message: '' };

    function init(options) {
      cfg.csrf = options.csrf || cfg.csrf || window.APP_CSRF_TOKEN || '';
      bindUI();
    }

    function bindUI() {
      document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.js-remove-pr');
        if (!btn) return;
        var tr = btn.closest('tr');
        if (tr) tr.parentNode.removeChild(tr);
      }, false);

      var saveBtn = document.getElementById('saveBtn');
      if (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
          e.preventDefault();
          confirmAction('Сохранить изменения сеанса?', { action: 'save-schedule' }).then(function (ok) {
            if (!ok) return;
            // Block save if overlap detected
            if (_lastOverlap && _lastOverlap.overlap) {
              var msg = 'Невозможно сохранить: обнаружено пересечение с другим сеансом';
              if (_lastOverlap.conflicting && _lastOverlap.conflicting.id) {
                msg += ' #' + _lastOverlap.conflicting.id;
              }
              showToast(msg, 'error', 8000);
              var startEl = document.getElementById('start_time');
              if (startEl) startEl.focus();
              return;
            }
            // Block save if sales validation error
            if (_salesError && _salesError.hasError) {
              var msg2 = _salesError.message || 'Ошибка в полях "Начало продаж"/"Окончание продаж"';
              showToast(msg2, 'error', 8000);
              // focus first erroneous field
              if (_salesError.fields && _salesError.fields.length) {
                var f = document.getElementById(_salesError.fields[0]);
                if (f) f.focus();
              }
              return;
            }
            submitForm();
          });
        }, false);
      }

      var basePriceEl = document.getElementById('base_price');
      if (basePriceEl) {
        basePriceEl.addEventListener('input', function () {
          if (this.value === '') return;
          var v = this.value;
          if (v.indexOf('.') !== -1) v = Math.floor(parseFloat(v));
          else v = Math.floor(Number(v));
          if (!isFinite(v)) v = '';
          this.value = v;
        });
        basePriceEl.addEventListener('blur', function () {
          if (this.value === '') return;
          this.value = String(Math.max(0, Math.floor(Number(this.value) || 0)));
        });
      }

      // Listen for changes to start_time, end_time, hall_id to trigger overlap check
      var startEl = document.getElementById('start_time');
      var endEl = document.getElementById('end_time');
      var hallEl = document.getElementById('hall_id');

      // Sales fields
      var salesStartEl = document.getElementById('sales_start_time') || document.getElementById('sales_start');
      var salesEndEl = document.getElementById('sales_end_time') || document.getElementById('sales_end');

      // immediate clear highlight on user edit so highlight doesn't linger while typing
      function clearHighlightImmediate() {
        try {
          var cls = 'has-overlap';
          if (startEl) startEl.classList.remove(cls);
          if (endEl) endEl.classList.remove(cls);
          if (hallEl) hallEl.classList.remove(cls);
          if (salesStartEl) salesStartEl.classList.remove(cls);
          if (salesEndEl) salesEndEl.classList.remove(cls);
        } catch (e) {}
      }

      function scheduleOverlapDebounced() {
        if (_overlapTimer) clearTimeout(_overlapTimer);
        _overlapTimer = setTimeout(function () {
          var start = startEl ? (startEl.value || '') : '';
          var end = endEl ? (endEl.value || '') : '';
          var hall = hallEl ? (hallEl.value || '') : '';
          var form = document.getElementById('scheduleForm');
          var currentId = null;
          if (form) {
            var idField = form.querySelector('input[name="id"]');
            if (idField && idField.value) currentId = idField.value;
          }
          if (!start || !end || !hall) {
            // not enough data to check
            _lastOverlap = { overlap: false, conflicting: null };
            removeFieldHighlight(startEl, endEl, hallEl);
            return;
          }
          checkOverlap(start, end, hall, currentId).then(function (res) {
            if (res && res.success && res.overlap) {
              _lastOverlap = { overlap: true, conflicting: res.conflicting || null };
              // show requested toast message (longer duration)
              showToast('На выбранную дату и время уже есть существующий сеанс в текущем зале', 'error', 8000);
              // light red highlight on fields that need correction
              addFieldHighlight(startEl, endEl, hallEl);
            } else {
              _lastOverlap = { overlap: false, conflicting: null };
              removeFieldHighlight(startEl, endEl, hallEl);
            }
          }).catch(function (err) {
            // network or server error — do not block user, but log
            console.error('Overlap check error', err);
          });
        }, _overlapDebounceMs);
      }

      if (startEl) {
        startEl.addEventListener('input', clearHighlightImmediate);
        startEl.addEventListener('change', scheduleOverlapDebounced);
        startEl.addEventListener('blur', scheduleOverlapDebounced);
      }
      if (endEl) {
        endEl.addEventListener('input', clearHighlightImmediate);
        endEl.addEventListener('change', scheduleOverlapDebounced);
        endEl.addEventListener('blur', scheduleOverlapDebounced);
      }
      if (hallEl) {
        hallEl.addEventListener('change', scheduleOverlapDebounced);
        hallEl.addEventListener('input', clearHighlightImmediate);
      }

      // Sales validation logic
      function clearSalesErrorImmediate() {
        try {
          var cls = 'has-overlap';
          if (salesStartEl) salesStartEl.classList.remove(cls);
          if (salesEndEl) salesEndEl.classList.remove(cls);
          _salesError = { hasError: false, fields: [], message: '' };
        } catch (e) {}
      }

      function validateSalesDebounced() {
        // run after small debounce to avoid flicker while typing
        if (_overlapTimer) clearTimeout(_overlapTimer);
        _overlapTimer = setTimeout(function () {
          validateSalesTimes();
        }, 250);
      }

      function validateSalesTimes() {
        try {
          _salesError = { hasError: false, fields: [], message: '' };
          var form = document.getElementById('scheduleForm');
          if (!form) return;
          var startVal = (startEl ? (startEl.value || '') : '');
          var endVal = (endEl ? (endEl.value || '') : '');
          var salesStartVal = (salesStartEl ? (salesStartEl.value || '') : '');
          var salesEndVal = (salesEndEl ? (salesEndEl.value || '') : '');

          // If sales start not provided -> nothing to validate (sales start may be empty)
          if (!salesStartVal) {
            // clear any previous sales highlights
            clearSalesErrorImmediate();
            return;
          }

          // parse dates
          var salesStartDate = new Date(salesStartVal);
          if (isNaN(salesStartDate.getTime())) {
            _salesError.hasError = true;
            _salesError.fields.push(salesStartEl && salesStartEl.id ? salesStartEl.id : 'sales_start_time');
            _salesError.message = 'Неверный формат "Начало продаж"';
            addFieldHighlight(salesStartEl, null, null);
            showToast(_salesError.message, 'error', 8000);
            return;
          }

          // session end must exist to compare; if not present, we cannot check salesStart <= sessionEnd
          if (!endVal) {
            // no session end — only ensure sales start format is valid (already done)
            removeFieldHighlight(salesStartEl, salesEndEl, null);
            _salesError = { hasError: false, fields: [], message: '' };
            return;
          }

          var sessionEndDate = new Date(endVal);
          if (isNaN(sessionEndDate.getTime())) {
            // invalid session end — highlight session end
            _salesError.hasError = true;
            _salesError.fields.push(endEl && endEl.id ? endEl.id : 'end_time');
            _salesError.message = 'Неверный формат "Окончание сеанса"';
            addFieldHighlight(null, endEl, null);
            showToast(_salesError.message, 'error', 8000);
            return;
          }

          // sales start cannot be after session end
          if (salesStartDate.getTime() > sessionEndDate.getTime()) {
            _salesError.hasError = true;
            _salesError.fields.push(salesStartEl && salesStartEl.id ? salesStartEl.id : 'sales_start_time');
            _salesError.fields.push(endEl && endEl.id ? endEl.id : 'end_time');
            _salesError.message = 'Начало продаж не может быть позже окончания сеанса';
            addFieldHighlight(salesStartEl, endEl, null);
            showToast(_salesError.message, 'error', 8000);
            return;
          }

          // if sales end provided, validate it
          if (salesEndVal) {
            var salesEndDate = new Date(salesEndVal);
            if (isNaN(salesEndDate.getTime())) {
              _salesError.hasError = true;
              _salesError.fields.push(salesEndEl && salesEndEl.id ? salesEndEl.id : 'sales_end_time');
              _salesError.message = 'Неверный формат "Окончание продаж"';
              addFieldHighlight(null, null, salesEndEl);
              showToast(_salesError.message, 'error', 8000);
              return;
            }

            // sales end cannot be before sales start
            if (salesEndDate.getTime() < salesStartDate.getTime()) {
              _salesError.hasError = true;
              _salesError.fields.push(salesStartEl && salesStartEl.id ? salesStartEl.id : 'sales_start_time');
              _salesError.fields.push(salesEndEl && salesEndEl.id ? salesEndEl.id : 'sales_end_time');
              _salesError.message = 'Окончание продаж не может быть раньше начала продаж';
              addFieldHighlight(salesStartEl, salesEndEl, null);
              showToast(_salesError.message, 'error', 8000);
              return;
            }

            // sales end cannot be after session end
            if (salesEndDate.getTime() > sessionEndDate.getTime()) {
              _salesError.hasError = true;
              _salesError.fields.push(salesEndEl && salesEndEl.id ? salesEndEl.id : 'sales_end_time');
              _salesError.fields.push(endEl && endEl.id ? endEl.id : 'end_time');
              _salesError.message = 'Окончание продаж не может быть позже окончания сеанса';
              addFieldHighlight(salesEndEl, endEl, null);
              showToast(_salesError.message, 'error', 8000);
              return;
            }
          }

          // all checks passed
          _salesError = { hasError: false, fields: [], message: '' };
          removeFieldHighlight(salesStartEl, salesEndEl, null);
        } catch (e) {
          console.error('Sales validation error', e);
        }
      }

      // attach listeners for sales fields
      if (salesStartEl) {
        salesStartEl.addEventListener('input', clearSalesErrorImmediate);
        salesStartEl.addEventListener('change', validateSalesDebounced);
        salesStartEl.addEventListener('blur', validateSalesDebounced);
      }
      if (salesEndEl) {
        salesEndEl.addEventListener('input', clearSalesErrorImmediate);
        salesEndEl.addEventListener('change', validateSalesDebounced);
        salesEndEl.addEventListener('blur', validateSalesDebounced);
      }
    }

    function addFieldHighlight(a, b, c) {
      // a,b,c are elements or null. Use same class as overlap highlight for consistency.
      try {
        var cls = 'has-overlap';
        if (a && a.classList) a.classList.add(cls);
        if (b && b.classList) b.classList.add(cls);
        if (c && c.classList) c.classList.add(cls);
      } catch (e) {}
    }
    function removeFieldHighlight(a, b, c) {
      try {
        var cls = 'has-overlap';
        if (a && a.classList) a.classList.remove(cls);
        if (b && b.classList) b.classList.remove(cls);
        if (c && c.classList) c.classList.remove(cls);
      } catch (e) {}
    }

    // AJAX check overlap
    function checkOverlap(start, end, hallId, excludeId) {
      return new Promise(function (resolve, reject) {
        try {
          var fd = new URLSearchParams();
          fd.append('action', 'check_overlap');
          fd.append('start_time', start);
          fd.append('end_time', end);
          fd.append('hall_id', hallId);
          if (excludeId) fd.append('exclude_id', excludeId);
          fd.append('csrf_token', cfg.csrf || window.APP_CSRF_TOKEN || '');

          fetch('/ajax/schedule.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd.toString()
          }).then(function (r) { return r.json(); })
            .then(function (resp) {
              if (!resp) return resolve({ success: false });
              resolve(resp);
            }).catch(function (err) {
              reject(err);
            });
        } catch (e) {
          reject(e);
        }
      });
    }

    function collectPriceRangesFromTable() {
      var prRows = document.querySelectorAll('#priceGroupsTbody tr');
      var priceRanges = [];
      prRows.forEach(function (tr) {
        var priceText = tr.querySelector('.pg-price-text');
        var colorEl = tr.querySelector('.pg-color');
        var id = tr.dataset.groupId || ('g' + Date.now() + Math.floor(Math.random() * 1000));
        var price = priceText ? (priceText.textContent || priceText.innerText || '').trim() : '';
        var color = colorEl ? colorEl.value : '';
        priceRanges.push({ id: id, price: price, color: color });
      });
      return priceRanges;
    }

    // Helper: produce compact layout for server — include only seats that have price set
    function compactLayoutWithPricedSeats(layout) {
      if (!layout || typeof layout !== 'object') return layout;
      try {
        var copy = JSON.parse(JSON.stringify(layout));
        copy.seats = copy.seats || {};
        var outSeats = {};
        Object.keys(copy.seats).forEach(function (k) {
          var s = copy.seats[k];
          if (!s || typeof s !== 'object' || Array.isArray(s)) return;
          var meta = s.meta && typeof s.meta === 'object' ? s.meta : null;
          if (meta && (typeof meta.price !== 'undefined') && meta.price !== null && String(meta.price).trim() !== '') {
            outSeats[k] = { meta: {} };
            if (typeof meta.price !== 'undefined') outSeats[k].meta.price = meta.price;
            if (typeof meta.color !== 'undefined') outSeats[k].meta.color = meta.color;
            Object.keys(meta).forEach(function (mk) {
              if (mk === 'price' || mk === 'color') return;
              var mv = meta[mk];
              if (mv !== null && typeof mv !== 'undefined' && !(typeof mv === 'string' && mv.trim() === '')) {
                outSeats[k].meta[mk] = mv;
              }
            });
          }
        });
        copy.seats = outSeats;
        return copy;
      } catch (e) {
        return layout;
      }
    }

		function submitForm() {
		  var form = document.getElementById('scheduleForm');
		  if (!form) return;

		  // Надёжное чтение event_id (учитываем disabled select + hidden fallback)
		  var eventEl = form.querySelector('[name="event_id"]:not([type="hidden"])');
		  var hiddenEvent = form.querySelector('input[name="event_id"][type="hidden"]');
		  var eventId = '';
		  if (eventEl) {
			// если элемент существует и не disabled — берём его value, иначе fallback на hidden
			eventId = (!eventEl.disabled) ? (eventEl.value || '') : ((hiddenEvent && hiddenEvent.value) || '');
		  } else {
			eventId = (hiddenEvent && hiddenEvent.value) || '';
		  }

		  // Аналогично для hall_id
		  var hallEl = form.querySelector('[name="hall_id"]:not([type="hidden"])');
		  var hiddenHall = form.querySelector('input[name="hall_id"][type="hidden"]');
		  var hallId = '';
		  if (hallEl) {
			hallId = (!hallEl.disabled) ? (hallEl.value || '') : ((hiddenHall && hiddenHall.value) || '');
		  } else {
			hallId = (hiddenHall && hiddenHall.value) || '';
		  }

		  // start/end читаем как раньше
		  var start = (form.start_time && form.start_time.value) ? form.start_time.value : '';
		  var end = (form.end_time && form.end_time.value) ? form.end_time.value : '';

		  if (!eventId || !hallId || !start || !end) {
			// Для отладки можно временно логировать, какие поля пусты:
			console.warn('submitForm: missing fields', { eventId:eventId, hallId:hallId, start:start, end:end });
			showToast('Заполните обязательные поля', 'error');
			return;
		  }

      // Block save if overlap detected (defensive check before submit)
      if (_lastOverlap && _lastOverlap.overlap) {
        var msg = 'Невозможно сохранить: обнаружено пересечение с другим сеансом';
        if (_lastOverlap.conflicting && _lastOverlap.conflicting.id) msg += ' #' + _lastOverlap.conflicting.id;
        showToast(msg, 'error', 8000);
        var startEl = document.getElementById('start_time');
        if (startEl) startEl.focus();
        return;
      }

      // Block save if sales validation error
      if (_salesError && _salesError.hasError) {
        var msg2 = _salesError.message || 'Ошибка в полях "Начало продаж"/"Окончание продаж"';
        showToast(msg2, 'error', 8000);
        if (_salesError.fields && _salesError.fields.length) {
          var f = document.getElementById(_salesError.fields[0]);
          if (f) f.focus();
        }
        return;
      }

      var priceRanges = collectPriceRangesFromTable();

      var formData = new FormData(form);

      // --- CRITICAL: ensure action is present for server-side handler ---
      try {
        var actionField = form.querySelector('input[name="action"]');
        if (actionField && actionField.value) {
          formData.set('action', actionField.value);
        } else {
          formData.set('action', 'create');
        }
      } catch (e) {
        formData.set('action', 'create');
      }

      formData.set('price_ranges', JSON.stringify(priceRanges));
      formData.set('csrf_token', cfg.csrf || window.APP_CSRF_TOKEN || '');

      var sc = (window.SeatingCanvasRender && typeof window.SeatingCanvasRender.getInstance === 'function') ? window.SeatingCanvasRender.getInstance('editor-canvas') : null;
      if (sc && typeof sc.exportLayout === 'function') {
        try {
          var layout = sc.exportLayout();
          try { layout = sanitizeIncomingLayout(layout); } catch (e) {}
          try { layout = (typeof compactLayoutWithPricedSeats === 'function') ? compactLayoutWithPricedSeats(layout) : layout; } catch (e) {}
          formData.set('seat_map', JSON.stringify(layout));
        } catch (e) {
          formData.set('seat_map', '');
        }
      } else {
        var seatMapField = document.getElementById('seat_map');
        if (seatMapField) formData.set('seat_map', seatMapField.value || '');
      }

      var notes = document.getElementById('notes');
      if (notes) formData.set('notes', notes.value || '');

      fetch('/ajax/schedule.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      }).then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success) {
            showToast(resp.message || 'Сохранено', 'success', 8000);
            window.location.href = '/schedule/list.php';
          } else {
            showToast((resp && resp.message) ? resp.message : 'Ошибка сохранения', 'error', 8000);
          }
        }).catch(function (err) {
          console.error('Save schedule error', err);
          showToast('Ошибка сети', 'error', 8000);
        });
    }

    return { init: init };
  })();

  // --- Price editor / seating renderer integration ---
  function initPriceEditorUI() {
    try {
      if (window.SeatingCanvasRender && typeof window.SeatingCanvasRender.init === 'function') {
        window.SeatingCanvasRender.init('editor-canvas', {
          seatSize: 28,
          gapX: 8,
          gapY: 12,
          showGrid: true,
          fitToCanvas: true,
          disableWheelZoom: true
        });
      }
    } catch (e) {}
    try {
      if (window.SessionPriceEditor && typeof window.SessionPriceEditor.init === 'function') {
        window.SessionPriceEditor.init({
          canvasId: 'editor-canvas',
          seatingOptions: { seatSize: 28, gapX: 8, gapY: 12, showGrid: true },
          selectedCountId: 'selectedCount'
        });
      }
    } catch (e) {}

    function getEditor() {
      try {
        if (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') {
          return window.SessionPriceEditor.getInstance();
        }
      } catch (e) {}
      return null;
    }
    function getRenderer() {
      try {
        if (window.SeatingCanvasRender && typeof window.SeatingCanvasRender.getInstance === 'function') {
          return window.SeatingCanvasRender.getInstance('editor-canvas');
        }
      } catch (e) {}
      return null;
    }

    function enhanceExistingPriceRows() {
      var tbody = document.getElementById('priceGroupsTbody');
      if (!tbody) return;
      var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
      rows.forEach(function (tr) {
        try {
          var priceCell = tr.querySelector('td:first-child');
          if (priceCell) {
            var existing = priceCell.querySelector('.pg-price-text');
            if (!existing) {
              var txt = (priceCell.textContent || '').trim();
              priceCell.innerHTML = '';
              var wrap = document.createElement('div');
              wrap.className = 'pg-price-text';
              wrap.style.padding = '6px 8px';
              wrap.textContent = txt;
              priceCell.appendChild(wrap);
            } else {
              existing.style.padding = existing.style.padding || '6px 8px';
            }
            priceCell.style.width = '20%';
          }

          var colorCell = tr.querySelector('td:nth-child(2)');
          if (colorCell) {
            var colorInput = colorCell.querySelector('input[type="color"]');
            if (!colorInput) {
              var val = (colorCell.textContent || '').trim();
              colorCell.innerHTML = '';
              colorInput = document.createElement('input');
              colorInput.type = 'color';
              colorInput.className = 'pg-color form-control';
              colorInput.value = val || '#cccccc';
              colorInput.style.width = '48px';
              colorInput.style.height = '28px';
              colorInput.style.padding = '0';
              colorInput.style.border = 'none';
              colorCell.appendChild(colorInput);
            } else {
              colorInput.classList.add('pg-color');
              colorInput.classList.add('form-control');
              colorInput.style.width = colorInput.style.width || '48px';
              colorInput.style.height = colorInput.style.height || '28px';
              colorInput.style.padding = colorInput.style.padding || '0';
              colorInput.style.border = colorInput.style.border || 'none';
            }
            colorCell.style.width = '30%';
          }

          var actionCell = tr.querySelector('td:last-child');
          if (actionCell) {
            actionCell.style.width = '50%';
            actionCell.style.display = 'flex';
            actionCell.style.gap = '8px';
            actionCell.style.alignItems = 'center';

            var applyBtn = actionCell.querySelector('.js-pg-apply, .js-apply-group');
            if (!applyBtn) {
              var newApply = document.createElement('button');
              newApply.type = 'button';
              newApply.className = 'btn btn-primary btn-sm js-pg-apply';
              newApply.textContent = 'Применить';
              actionCell.insertBefore(newApply, actionCell.firstChild || null);
            } else {
              applyBtn.classList.add('btn', 'btn-primary', 'btn-sm');
              applyBtn.classList.remove('js-apply-group');
              applyBtn.classList.add('js-pg-apply');
              applyBtn.type = 'button';
            }

            var removeBtn = actionCell.querySelector('.js-pg-remove, .js-remove-group');
            if (!removeBtn) {
              var newRem = document.createElement('button');
              newRem.type = 'button';
              newRem.className = 'btn btn-danger btn-sm js-pg-remove';
              newRem.textContent = 'Удалить';
              actionCell.appendChild(newRem);
            } else {
              removeBtn.classList.add('btn', 'btn-danger', 'btn-sm');
              removeBtn.classList.remove('js-remove-group');
              removeBtn.classList.add('js-pg-remove');
              removeBtn.type = 'button';
            }
          }
        } catch (e) {}
      });
    }

    function syncPriceGroupsTable(groups) {
      var tbody = document.getElementById('priceGroupsTbody');
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!Array.isArray(groups)) return;
      groups.forEach(function (g) {
        var tr = document.createElement('tr');
        if (g.id) tr.dataset.groupId = g.id;
        var priceTd = document.createElement('td');
        priceTd.style.width = '20%';
        var priceWrap = document.createElement('div');
        priceWrap.className = 'pg-price-text';
        priceWrap.style.padding = '6px 8px';
        priceWrap.textContent = g.price || '';
        priceTd.appendChild(priceWrap);

        var colorTd = document.createElement('td');
        colorTd.style.width = '30%';
        var colorInput = document.createElement('input');
        colorInput.type = 'color';
        colorInput.className = 'pg-color form-control';
        colorInput.value = g.color || '#cccccc';
        colorInput.style.width = '48px';
        colorInput.style.height = '28px';
        colorInput.style.padding = '0';
        colorInput.style.border = 'none';
        colorTd.appendChild(colorInput);

        var actionTd = document.createElement('td');
        actionTd.style.width = '50%';
        actionTd.style.display = 'flex';
        actionTd.style.gap = '8px';
        actionTd.style.alignItems = 'center';
        var applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.className = 'btn btn-primary btn-sm js-pg-apply';
        applyBtn.textContent = 'Применить';
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger btn-sm js-pg-remove';
        removeBtn.textContent = 'Удалить';
        actionTd.appendChild(applyBtn);
        actionTd.appendChild(removeBtn);

        tr.appendChild(priceTd);
        tr.appendChild(colorTd);
        tr.appendChild(actionTd);
        tbody.appendChild(tr);
      });
    }

    // Delegated click handler for apply/remove buttons
    document.addEventListener('click', function (e) {
      var apply = e.target.closest && e.target.closest('.js-pg-apply');
      var remove = e.target.closest && e.target.closest('.js-pg-remove');
      if (!apply && !remove) return;

      var tr = (apply || remove).closest('tr');
      if (!tr) return;
      var price = (tr.querySelector('.pg-price-text') || {}).textContent || '';
      var colorEl = tr.querySelector('.pg-color');
      var color = colorEl ? (colorEl.value || '') : '';

      var editor = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null;
      if (!editor) { showToast('Редактор схемы недоступен', 'error', 6000); return; }

      if (apply) {
        var selection = [];
        try { selection = (editor && typeof editor.getSelection === 'function') ? editor.getSelection() : (window._lastSeatingSelection || []); } catch (e) { selection = (window._lastSeatingSelection || []); }
        if (!selection || !selection.length) { showToast('Выберите места', 'error', 6000); return; }

        try {
          var res = editor.assignPriceToSelection({ price: price, color: color, overwrite: true });
          if (res && res.success) {
            if (typeof editor.renderOverlay === 'function') editor.renderOverlay();
            showToast('Группа применена к выбранным местам', 'success', 6000);
            try { if (typeof editor.getPriceGroups === 'function') syncPriceGroupsTable(editor.getPriceGroups()); } catch (e) {}
          } else {
            showToast('Ошибка применения', 'error', 6000);
          }
        } catch (e) { showToast('Ошибка применения', 'error', 6000); }
      } else if (remove) {
        var removed = false;
        try {
          var gid = tr.dataset.groupId;
          if (gid && typeof editor.removePriceGroupById === 'function') {
            var r = editor.removePriceGroupById(gid);
            removed = r && r.success;
          } else if (typeof editor.removePriceGroup === 'function') {
            var r2 = editor.removePriceGroup(price);
            removed = r2 && r2.success;
          } else if (typeof editor.removePriceGroupByPrice === 'function') {
            var r3 = editor.removePriceGroupByPrice(price);
            removed = r3 && r3.success;
          } else {
            try { tr.parentNode.removeChild(tr); removed = true; } catch (e) { removed = false; }
          }

          if (removed) {
            try { if (tr.parentNode) tr.parentNode.removeChild(tr); } catch (e) {}
            showToast('Группа удалена', 'success', 6000);
            try { if (typeof editor.getPriceGroups === 'function') syncPriceGroupsTable(editor.getPriceGroups()); } catch (e) {}
          } else {
            showToast('Ошибка удаления', 'error', 6000);
          }
        } catch (e) {
          showToast('Ошибка удаления', 'error', 6000);
        }
      }
    }, false);

    // Delegated input handler for color inputs
    document.addEventListener('input', function (e) {
      var colorInput = e.target && e.target.matches && e.target.matches('.pg-color') ? e.target : null;
      if (!colorInput) return;
      var tr = colorInput.closest('tr');
      if (!tr) return;
      var price = (tr.querySelector('.pg-price-text') || {}).textContent || '';
      var editor = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null;
      try {
        if (editor && typeof editor.updateGroupColor === 'function') {
          editor.updateGroupColor(price, colorInput.value);
          if (typeof editor.renderOverlay === 'function') editor.renderOverlay();
        } else if (editor && typeof editor.updatePriceGroupById === 'function') {
          var gid = tr.dataset.groupId;
          if (gid) editor.updatePriceGroupById(gid, { color: colorInput.value });
          if (typeof editor.renderOverlay === 'function') editor.renderOverlay();
        }
      } catch (e) {}
    }, false);

    // Helper: robust selection getter (used by assign button)
    function getSelectionFromEditor(editor) {
      try {
        if (editor && typeof editor.getSelection === 'function') {
          var s = editor.getSelection();
          if (Array.isArray(s)) return s;
        }
      } catch (e) {}
      try {
        if (window._lastSeatingSelection && Array.isArray(window._lastSeatingSelection)) return window._lastSeatingSelection;
      } catch (e) {}
      return [];
    }

    // Wire controls (assign/unassign/auto/tools)
    function wireControls() {
      var toolSelect = document.getElementById('tool-select');
      var toolRect = document.getElementById('tool-rect');
      var toolClear = document.getElementById('tool-clear-selection');

      if (toolSelect) toolSelect.addEventListener('click', function () { var ed = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null; if (ed && typeof ed.setTool === 'function') ed.setTool('select'); });
      if (toolRect) toolRect.addEventListener('click', function () { var ed = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null; if (ed && typeof ed.setTool === 'function') ed.setTool('rect'); });
      if (toolClear) toolClear.addEventListener('click', function () { var ed = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null; if (ed && typeof ed.clearSelection === 'function') ed.clearSelection(); });

      var assignBtn = document.getElementById('assignBtn');
      var unassignBtn = document.getElementById('unassignBtn');
      var assignPrice = document.getElementById('assignPrice');
      var assignColor = document.getElementById('assignColor');
      var optOverwrite = document.getElementById('optOverwrite');

      if (assignBtn) {
        assignBtn.addEventListener('click', function () {
          var editor = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null;
          if (!editor) { showToast('Редактор схемы недоступен', 'error', 6000); return; }
          var price = assignPrice ? assignPrice.value.trim() : '';
          var color = assignColor ? assignColor.value : '';
          var overwrite = !!(optOverwrite && optOverwrite.checked);

          var existingGroups = [];
          try {
            existingGroups = (editor && typeof editor.getPriceGroups === 'function') ? (editor.getPriceGroups() || []) : (function () {
              var rows = document.querySelectorAll('#priceGroupsTbody tr');
              var out = [];
              rows.forEach(function (tr) {
                var p = (tr.querySelector('.pg-price-text') || {}).textContent || '';
                var c = (tr.querySelector('.pg-color') || {}).value || '';
                if (p) out.push({ price: p, color: c });
              });
              return out;
            })();
          } catch (e) { existingGroups = []; }

          if (price && existingGroups.find(function (g) { return String(g.price) === String(price); })) {
            showToast('Цена уже существует, используйте её из таблицы', 'error', 6000);
            return;
          }

          if (!price || price === '') { showAlertAsToast('Укажите цену'); return; }
          if (!color || color === '') { showAlertAsToast('Выберите цвет'); return; }

          var selection = getSelectionFromEditor(editor);
          if (!selection || !selection.length) { showAlertAsToast('Выберите места для назначения цены'); return; }

          var res = null;
          try { res = editor.assignPriceToSelection({ price: price, color: color, overwrite: overwrite }); } catch (e) { res = { success: false }; }
          if (res && res.success) {
            showToast('Цена назначена', 'success', 6000);
            try { if (typeof editor.renderOverlay === 'function') editor.renderOverlay(); } catch (e) {}
            try { if (typeof editor.getPriceGroups === 'function') syncPriceGroupsTable(editor.getPriceGroups()); } catch (e) {}
          } else {
            showToast('Не удалось назначить цену', 'error', 6000);
          }
        });
      }

      if (unassignBtn) {
        unassignBtn.addEventListener('click', function () {
          var editor = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null;
          if (!editor) { showToast('Редактор схемы недоступен', 'error', 6000); return; }
          var res = null;
          try { res = editor.unassignPriceFromSelection(); } catch (e) { res = { success: false }; }
          if (res && res.success) {
            showToast('Цена снята', 'success', 6000);
            try { if (typeof editor.renderOverlay === 'function') editor.renderOverlay(); } catch (e) {}
            try { if (typeof editor.getPriceGroups === 'function') syncPriceGroupsTable(editor.getPriceGroups()); } catch (e) {}
          } else {
            showToast('Не удалось снять цену', 'error', 6000);
          }
        });
      }

      var autoColorBtn = document.getElementById('autoColor');
      if (autoColorBtn && assignPrice && assignColor) {
        autoColorBtn.addEventListener('click', function () {
          var p = (assignPrice.value || '').trim();
          if (!p) { assignColor.value = '#cccccc'; return; }
          var hash = 0;
          for (var i = 0; i < p.length; i++) hash = ((hash << 5) - hash) + p.charCodeAt(i);
          var r = (hash >> 0) & 0xFF;
          var g = (hash >> 8) & 0xFF;
          var b = (hash >> 16) & 0xFF;
          function toHex(n) { return ('0' + (n & 0xFF).toString(16)).slice(-2); }
          assignColor.value = '#' + toHex(r) + toHex(g) + toHex(b);
        });
      }

      var selectedCountEl = document.getElementById('selectedCount');
      if (selectedCountEl) {
        window.addEventListener('seating:selection-changed', function (ev) {
          try {
            var sel = ev && ev.detail && Array.isArray(ev.detail.selection) ? ev.detail.selection : null;
            if (sel !== null) selectedCountEl.textContent = String(sel.length || 0);
            try { window._lastSeatingSelection = sel; } catch (e) {}
          } catch (e) {}
        });
      }
    }

    // Apply initial seat_map and price_ranges when editor/renderer ready
    function applyInitialDataWhenReady() {
      var attempts = 0;
      var maxAttempts = 60;
      function tryApply() {
        attempts++;
        var editor = (window.SessionPriceEditor && typeof window.SessionPriceEditor.getInstance === 'function') ? window.SessionPriceEditor.getInstance() : null;
        var sc = (window.SeatingCanvasRender && typeof window.SeatingCanvasRender.getInstance === 'function') ? window.SeatingCanvasRender.getInstance('editor-canvas') : null;

        if ((!sc || typeof sc.setLayout === 'function') === false && (!editor || typeof editor.applyPriceRanges === 'function') === false) {
          if (attempts < maxAttempts) setTimeout(tryApply, 100);
          return;
        }

        var seatMapField = document.getElementById('seat_map');
        var priceRangesField = document.getElementById('price_ranges');

        if (seatMapField && seatMapField.value) {
          try {
            var layout = JSON.parse(seatMapField.value);
            try { layout = sanitizeIncomingLayout(layout); } catch (e) {}
            if (sc && typeof sc.setLayout === 'function') sc.setLayout(layout);
          } catch (e) {}
        }

        if (priceRangesField && priceRangesField.value) {
          try {
            var pr = JSON.parse(priceRangesField.value);
            if (pr && editor && typeof editor.applyPriceRanges === 'function') {
              editor.applyPriceRanges(pr);
              if (typeof editor.renderOverlay === 'function') editor.renderOverlay();
              syncPriceGroupsTable(pr);
            } else if (pr) {
              var tbody = document.getElementById('priceGroupsTbody');
              if (tbody && (!tbody.children || tbody.children.length === 0)) {
                syncPriceGroupsTable(pr);
              } else {
                enhanceExistingPriceRows();
              }
            }
          } catch (e) {}
        } else {
          try {
            if (editor && typeof editor.getPriceGroups === 'function') {
              var groups = editor.getPriceGroups() || [];
              if (groups && groups.length) {
                if (typeof editor.applyPriceRanges === 'function') {
                  try { editor.applyPriceRanges(groups); if (typeof editor.renderOverlay === 'function') editor.renderOverlay(); } catch (e) {}
                }
                syncPriceGroupsTable(groups);
              } else {
                enhanceExistingPriceRows();
              }
            } else {
              enhanceExistingPriceRows();
            }
          } catch (e) { enhanceExistingPriceRows(); }
        }
      }
      tryApply();
    }

    // Public init: normalize rows, wire controls, then apply initial data
    return {
      init: function () {
        enhanceExistingPriceRows();
        wireControls();
        setTimeout(applyInitialDataWhenReady, 50);
      }
    };
  }

  // --- Page init ---
  function initAll() {
    // modal footer handlers
    document.addEventListener('click', function (e) {
      var target = e.target;
      if (!target) return;
      if (target.id === 'modal-delete-confirm') {
        if (window._modalDeleteResolver && typeof window._modalDeleteResolver === 'function') {
          try { window._modalDeleteResolver(true); } catch (err) {}
        }
        try {
          var meta = (typeof window.getModalMeta === 'function') ? window.getModalMeta() : {};
          var ev = new CustomEvent('modal:ok', { detail: meta });
          document.dispatchEvent(ev);
        } catch (err) {}
        if (typeof window.hideModalDelete === 'function') window.hideModalDelete();
      }
      if (target.id === 'modal-delete-cancel') {
        if (window._modalDeleteResolver && typeof window._modalDeleteResolver === 'function') {
          try { window._modalDeleteResolver(false); } catch (err) {}
        }
        try {
          var meta2 = (typeof window.getModalMeta === 'function') ? window.getModalMeta() : {};
          var ev2 = new CustomEvent('modal:cancel', { detail: meta2 });
          document.dispatchEvent(ev2);
        } catch (err) {}
        if (typeof window.hideModalDelete === 'function') window.hideModalDelete();
      }
    }, false);

    var schedulesTbody = document.getElementById('schedulesTbody');
    if (schedulesTbody) {
      var csrf = (window.APP_CSRF_TOKEN || (document.querySelector('input[name="csrf_token"]') || {}).value || '');
      ListModule.init({ containerSelector: '#schedulesTbody', csrf: csrf });
    }

    var scheduleForm = document.getElementById('scheduleForm');
    if (scheduleForm) {
      var csrf = (window.APP_CSRF_TOKEN || (document.querySelector('input[name="csrf_token"]') || {}).value || '');
      FormModule.init({ csrf: csrf });

      // init editor UI and wire controls
      var editorUI = initPriceEditorUI();
      if (editorUI && typeof editorUI.init === 'function') editorUI.init();
      else {
        try {
          if (typeof window.ScheduleApp !== 'undefined' && window.ScheduleApp._initPriceEditorUI) {
            try { window.ScheduleApp._initPriceEditorUI(); } catch (e) {}
          }
        } catch (e) {}
      }
    }

    document.querySelectorAll('.js-panel-back').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var href = el.getAttribute('href') || '/schedule/list.php';
        confirmAction('Вернуться к расписанию? Несохранённые изменения будут потеряны.', { action: 'navigate', href: href }).then(function (ok) {
          if (ok) window.location.href = href;
        });
      });
    });

    var cancelLink = document.getElementById('cancelBtn');
    if (cancelLink) {
      cancelLink.addEventListener('click', function (e) {
        e.preventDefault();
        var href = cancelLink.getAttribute('href') || '/schedule/list.php';
        confirmAction('Отменить изменения и вернуться к списку? Несохранённые данные будут потеряны.', { action: 'navigate', href: href }).then(function (ok) {
          if (ok) window.location.href = href;
        });
      });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll); else initAll();

  // Expose API
  window.ScheduleApp = window.ScheduleApp || {};
  window.ScheduleApp.syncPriceGroupsTable = function (groups) { try { if (typeof groups !== 'undefined' && groups) { var tbody = document.getElementById('priceGroupsTbody'); if (tbody) { tbody.innerHTML = ''; groups.forEach(function (g) { var tr = document.createElement('tr'); if (g.id) tr.dataset.groupId = g.id; var priceTd = document.createElement('td'); priceTd.style.width = '20%'; var priceWrap = document.createElement('div'); priceWrap.className = 'pg-price-text'; priceWrap.style.padding = '6px 8px'; priceWrap.textContent = g.price || ''; priceTd.appendChild(priceWrap); var colorTd = document.createElement('td'); colorTd.style.width = '30%'; var colorInput = document.createElement('input'); colorInput.type = 'color'; colorInput.className = 'pg-color form-control'; colorInput.value = g.color || '#cccccc'; colorInput.style.width = '48px'; colorInput.style.height = '28px'; colorInput.style.padding = '0'; colorInput.style.border = 'none'; colorTd.appendChild(colorInput); var actionTd = document.createElement('td'); actionTd.style.width = '50%'; actionTd.style.display = 'flex'; actionTd.style.gap = '8px'; actionTd.style.alignItems = 'center'; var applyBtn = document.createElement('button'); applyBtn.type = 'button'; applyBtn.className = 'btn btn-primary btn-sm js-pg-apply'; applyBtn.textContent = 'Применить'; var removeBtn = document.createElement('button'); removeBtn.type = 'button'; removeBtn.className = 'btn btn-danger btn-sm js-pg-remove'; removeBtn.textContent = 'Удалить'; actionTd.appendChild(applyBtn); actionTd.appendChild(removeBtn); tr.appendChild(priceTd); tr.appendChild(colorTd); tr.appendChild(actionTd); tbody.appendChild(tr); }); } } } catch (e) {} };

})();
