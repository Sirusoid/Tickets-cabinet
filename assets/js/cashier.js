// assets/js/cashier.js
// Полный, совместимый с seatmap_renderer.js файл логики кассы.
// Отвечает за: загрузку сеанса, передачу данных в рендерер, обработку кликов по местам,
// управление корзиной, кнопки резерв/продажа, синхронизацию выделений и легенды.

var Cashier = (function () {
  'use strict';

  var cfg = { csrf: '' };

  var DEFAULTS = {
    canvasId: 'cash-seatmap',
    containerId: 'seatmapContainer',
    legendContainerId: 'seatmapLegend',
    cartListId: 'cartList',
    amountInputId: 'amountCents',
    reserveBtnId: 'reserveBtn',
    sellBtnId: 'sellBtn',
    reserveTtlInputId: 'reserveTtl',
    holdInfoId: 'holdInfo',
    paymentMethodId: 'paymentMethod',
    paymentCashId: 'pay_cash',
    paymentCardId: 'pay_card',
    customerSegmentId: 'customer_segment',
    manualDiscountAmountId: 'manualDiscountAmount',
    customDiscountTypeId: 'customDiscountType',
    customDiscountValueId: 'customDiscountValue',
    baseTotalOutputId: 'baseTotalTg',
    finalTotalOutputId: 'finalTotalTg',
    segmentDiscountHintId: 'segmentDiscountHint',
    discountTotalOutputId: 'discountTotalTg',
    customDiscountHintId: 'customDiscountHint'
  };

  var sessionId = 0;
  var renderer = null;
  var viewer = null;
  var pricedSeats = {};
  var pricedColors = {};
  var soldSeats = {};
  var heldSeats = {};
  var cart = [];
  var selectedCartSeatKey = null;
  var legendRenderer = null;

  var canvasEl = null;
  var containerEl = null;
  var legendContainer = null;
  var cartListEl = null;
  var amountInputEl = null;
  var reserveBtnEl = null;
  var sellBtnEl = null;
  var reserveTtlInputEl = null;
  var holdInfoEl = null;
  var paymentMethodEl = null;
  var paymentCashEl = null;
  var paymentCardEl = null;
  var customerSegmentEl = null;
  var manualDiscountAmountEl = null;
  var customDiscountTypeEl = null;
  var customDiscountValueEl = null;
  var baseTotalOutputEl = null;
  var discountTotalOutputEl = null;
  var finalTotalOutputEl = null;
  var segmentDiscountHintEl = null;
  var customDiscountHintEl = null;
  var discountConfig = {
    segment_percent: { adult: 0, child: 0, student: 0, senior: 0 },
    custom_enabled: true,
    custom_max_percent: 30,
    custom_max_amount: 0
  };
  var currentUserId = 0;

  // --- Diagnostic guard: prevent accidental crashes from new helpers ---
  (function(){
    window._cashier_last_error = null;
    function safeExec(fn, name) {
      try { return fn(); }
      catch (e) {
        window._cashier_last_error = { name: name || 'anonymous', message: e.message, stack: e.stack };
        console.error('Cashier runtime error in', name || 'anonymous', e);
        return null;
      }
    }
    window.CashierSafe = { safeExec: safeExec, lastError: function(){ return window._cashier_last_error; } };
  })();

  // --- AJAX helper ---
  function ajax(action, data, cb, method) {
    method = method || 'POST';
    var url = '/ajax/cash.php?action=' + encodeURIComponent(action);
    if (method === 'GET') {
      var q = [];
      for (var k in data) if (data[k] !== undefined && data[k] !== null) q.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
      if (q.length) url += '&' + q.join('&');
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(cb)
        .catch(function (e) { cb({ success: false, message: e.message }); });
      return;
    }
    data.csrf_token = cfg.csrf;
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).then(function (r) { return r.json(); })
      .then(cb)
      .catch(function (e) { cb({ success: false, message: e.message }); });
  }

  // --- Utilities ---
  function pickColorForValue(v) {
    if (isNaN(v)) return '#3498db';
    var vals = Object.keys(pricedSeats).map(function (k) { return Number(pricedSeats[k]); }).filter(function (n) { return !isNaN(n); });
    var min = vals.length ? Math.min.apply(null, vals) : 0;
    var max = vals.length ? Math.max.apply(null, vals) : (min || 1000);
    if (min === max) return '#2d9cdb';
    var t = (v - min) / (max - min);
    t = Math.max(0, Math.min(1, t));
    var c1 = [52, 152, 219];
    var c2 = [142, 68, 173];
    var r = Math.round(c1[0] + (c2[0] - c1[0]) * t);
    var g = Math.round(c1[1] + (c2[1] - c1[1]) * t);
    var b = Math.round(c1[2] + (c2[2] - c1[2]) * t);
    return 'rgb(' + r + ',' + g + ',' + b + ')';
  }

  function showToast(text) {
    var el = document.createElement('div');
    el.textContent = text;
    el.style.position = 'fixed';
    el.style.right = '20px';
    el.style.bottom = (20 + (window._toastCount || 0) * 56) + 'px';
    el.style.background = '#2d6fa3';
    el.style.color = '#fff';
    el.style.padding = '8px 12px';
    el.style.borderRadius = '6px';
    el.style.zIndex = 99999;
    document.body.appendChild(el);
    window._toastCount = (window._toastCount || 0) + 1;
    setTimeout(function () { el.remove(); window._toastCount = Math.max(0, (window._toastCount || 1) - 1); }, 3000);
  }

  // --- Cart management ---
  function toggleCart(key) {
    var idx = cart.indexOf(key);
    if (idx === -1) cart.push(key);
    else cart.splice(idx, 1);
  }

  function getSeatPrice(key) {
    var normalized = normalizeSeatKey(key);
    var parsed = splitSeatKey(normalized);
    var price = pricedSeats[key];
    if (price === undefined) price = pricedSeats[normalized];
    if (price === undefined) price = pricedSeats[normalized.replace(/-/g, ':')];
    if (price === undefined && parsed.row !== null && parsed.seat !== null) {
      price = pricedSeats[parsed.row + ':' + parsed.seat];
      if (price === undefined) price = pricedSeats[parsed.row + '-' + parsed.seat];
    }
    return price;
  }

  function computeCartTotal() {
    var total = 0;
    cart.forEach(function (k) {
      var p = getSeatPrice(k);
      if (p != null && !isNaN(Number(p))) total += Math.floor(Number(p));
    });
    return total;
  }

  function formatMoneyTg(value) {
    var n = Number(value) || 0;
    return n.toLocaleString() + ' тг';
  }

  function getCurrentSegment() {
    return (customerSegmentEl && customerSegmentEl.value) ? customerSegmentEl.value : 'adult';
  }

  function getSegmentDiscountPercent(segment) {
    if (!discountConfig || !discountConfig.segment_percent) return 0;
    var p = Number(discountConfig.segment_percent[segment]);
    if (!isFinite(p)) return 0;
    return Math.max(0, Math.min(100, p));
  }

  function calculateDiscounts(baseTotal) {
    var base = Math.max(0, Number(baseTotal) || 0);
    var segment = getCurrentSegment();
    var manualMode = segment === 'manual';
    var autoPercent = manualMode ? 0 : getSegmentDiscountPercent(segment);
    var autoAmount = manualMode ? 0 : (Math.round(base * autoPercent) / 100);
    var afterAuto = Math.max(0, base - autoAmount);

    var manualAmount = 0;
    if (manualMode && discountConfig.custom_enabled) {
      manualAmount = getManualDiscountInputValue();
      manualAmount = Math.min(manualAmount, Math.floor(afterAuto));
    }

    var finalTotal = Math.max(0, afterAuto - manualAmount);
    var totalDiscount = Math.max(0, base - finalTotal);

    return {
      segment: segment,
      base_total: base,
      auto_percent: autoPercent,
      auto_amount: autoAmount,
      custom_type: manualMode ? 'fixed' : 'none',
      custom_value: manualAmount,
      custom_amount: manualAmount,
      total_discount: totalDiscount,
      final_total: finalTotal
    };
  }

  function distributeSeatPrices(seatsPayload, finalTotal) {
    if (!Array.isArray(seatsPayload) || !seatsPayload.length) return seatsPayload || [];
    var baseValues = seatsPayload.map(function (s) {
      var p = Number(s.price);
      return isFinite(p) && p > 0 ? p : 0;
    });
    var baseSum = baseValues.reduce(function (sum, v) { return sum + v; }, 0);
    if (baseSum <= 0) {
      return seatsPayload.map(function (s) { return Object.assign({}, s, { price: 0 }); });
    }

    var finalCents = Math.max(0, Math.round(Number(finalTotal || 0) * 100));
    var baseCents = baseValues.map(function (v) { return Math.round(v * 100); });
    var baseTotalCents = baseCents.reduce(function (sum, c) { return sum + c; }, 0);
    if (baseTotalCents <= 0) {
      return seatsPayload.map(function (s) { return Object.assign({}, s, { price: 0 }); });
    }

    var distributed = [];
    var allocated = 0;
    for (var i = 0; i < seatsPayload.length; i++) {
      var cents;
      if (i === seatsPayload.length - 1) {
        cents = Math.max(0, finalCents - allocated);
      } else {
        cents = Math.floor((baseCents[i] * finalCents) / baseTotalCents);
        allocated += cents;
      }
      distributed.push(Object.assign({}, seatsPayload[i], { price: cents / 100 }));
    }
    return distributed;
  }

  function getManualDiscountInputValue() {
    var el = manualDiscountAmountEl || document.getElementById('manualDiscountAmount');
    if (!el) return 0;
    var v = parseInt(String(el.value).replace(/\D+/g, '') || '0', 10);
    return isFinite(v) ? Math.max(0, v) : 0;
  }

  function refreshDiscountUi() {
    var baseTotal = computeCartTotal();
    var d = calculateDiscounts(baseTotal);
    if (baseTotalOutputEl) baseTotalOutputEl.textContent = formatMoneyTg(d.base_total);
    if (discountTotalOutputEl) discountTotalOutputEl.textContent = formatMoneyTg(d.total_discount);
    if (finalTotalOutputEl) finalTotalOutputEl.textContent = formatMoneyTg(d.final_total);
    if (amountInputEl) amountInputEl.value = String(Math.round(d.final_total));
    if (segmentDiscountHintEl) segmentDiscountHintEl.textContent = d.segment === 'manual' ? 'Активна ручная скидка' : ('Скидка по типу: ' + d.auto_percent + '%');
    if (manualDiscountAmountEl) {
      var manualEnabled = discountConfig.custom_enabled && d.segment === 'manual';
      manualDiscountAmountEl.disabled = !manualEnabled;
      if (manualEnabled) {
        var currentManual = getManualDiscountInputValue();
        if (currentManual > d.base_total) {
          manualDiscountAmountEl.value = String(Math.floor(d.base_total));
        }
      }
    }
  }

  function renderCart() {
    if (!cartListEl) return;
    cartListEl.innerHTML = '';

    if (!cart.length) {
      cartListEl.innerHTML = '<div style="color:#666;">Корзина пуста</div>';
      selectedCartSeatKey = null;
      if (amountInputEl) amountInputEl.value = '0';
      try { window.dispatchEvent(new CustomEvent('cashier:cart-updated', { detail: { cart: [], selectedSeatKey: null } })); } catch (e) {}
      refreshDiscountUi();
      return;
    }

    if (selectedCartSeatKey && cart.indexOf(selectedCartSeatKey) === -1) {
      selectedCartSeatKey = null;
    }
    if (!selectedCartSeatKey) selectedCartSeatKey = cart[0] || null;

    var total = computeCartTotal();
    var d = calculateDiscounts(total);
    var container = document.createElement('div');
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.gap = '8px';

    cart.forEach(function (k) {
      var parsed = splitSeatKey(k);
      var price = getSeatPrice(k);
      var itemKey = normalizeSeatKey(k);
      var selectedKey = normalizeSeatKey(selectedCartSeatKey);
      var item = document.createElement('div');
      item.className = 'cart-item' + (itemKey === selectedKey ? ' selected' : '');
      item.setAttribute('data-seat-key', itemKey);
      item.setAttribute('data-seat', itemKey);
      item.setAttribute('data-schedule-id', String(sessionId || ''));
      item.setAttribute('data-selected', itemKey === selectedKey ? '1' : '0');
      item.setAttribute('data-reserved', heldSeats[itemKey] || heldSeats[itemKey.replace(/-/g, ':')] ? '1' : '0');
      item.style.display = 'flex';
      item.style.justifyContent = 'space-between';
      item.style.alignItems = 'center';
      item.style.padding = '8px 10px';
      item.style.border = '1px solid ' + (itemKey === selectedKey ? '#cfe2ff' : '#eee');
      item.style.borderRadius = '6px';
      item.style.background = itemKey === selectedKey ? '#f3f8ff' : '#fff';
      item.style.cursor = 'pointer';

      var left = document.createElement('div');
      left.style.display = 'flex';
      left.style.alignItems = 'center';
      left.style.gap = '6px';
      left.style.flexWrap = 'wrap';

      var info = document.createElement('div');
      info.style.fontWeight = '700';
      info.textContent = 'Р: ' + (parsed.row || '—') + ', М: ' + (parsed.seat || '—') + ' - ' + (price ? Number(price).toLocaleString() + ' тг.' : '—');

      left.appendChild(info);

      var rem = document.createElement('button');
      rem.textContent = '×';
      rem.className = 'btn btn-ghost btn-xs';
      rem.style.marginLeft = '12px';
      rem.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        cart = cart.filter(function (x) { return normalizeSeatKey(x) !== itemKey; });
        if (selectedCartSeatKey && normalizeSeatKey(selectedCartSeatKey) === itemKey) selectedCartSeatKey = null;
        renderCart();
        syncSelectionToRenderer();
        redrawLegend();
      });

      item.addEventListener('click', function () {
        selectedCartSeatKey = k;
        renderCart();
      });

      item.appendChild(left);
      item.appendChild(rem);
      container.appendChild(item);
    });

    cartListEl.appendChild(container);

    if (amountInputEl) amountInputEl.value = String(Math.round(d.final_total));
    try { window.dispatchEvent(new CustomEvent('cashier:cart-updated', { detail: { cart: cart.slice(), selectedSeatKey: normalizeSeatKey(selectedCartSeatKey) } })); } catch (e) {}
    refreshDiscountUi();
  }

  // --- Safe build seats payload (protected) ---
  function buildSeatsPayload() {
    try {
      var seats = [];
      if (!Array.isArray(cart)) return seats;
      cart.forEach(function (k) {
        try {
          var parts = (typeof k === 'string') ? k.split(':') : [];
          var row = parts[0] || '';
          var seat = parts[1] || '';
          var seatId = null;
          if (pricedSeats && typeof pricedSeats === 'object' && pricedSeats._ids && typeof pricedSeats._ids === 'object' && pricedSeats._ids[k]) {
            seatId = pricedSeats._ids[k];
          }
          var price = 0;
          if (pricedSeats && typeof pricedSeats === 'object' && typeof pricedSeats[k] !== 'undefined') {
            var p = Number(pricedSeats[k]);
            price = isNaN(p) ? 0 : p;
          }
          seats.push({
            id: seatId,
            identifier: (row && seat) ? (row + ':' + seat) : String(k),
            price: price
          });
        } catch (inner) {
          console.warn('buildSeatsPayload inner error for key', k, inner && inner.message);
        }
      });
      return seats;
    } catch (e) {
      console.error('buildSeatsPayload error', e);
      return [];
    }
  }

  function formatHoldList(keys) {
    if (!Array.isArray(keys) || keys.length === 0) return '';
    if (keys.length <= 6) return keys.join(', ');
    return keys.slice(0, 6).join(', ') + ' и ещё ' + (keys.length - 6);
  }

  function updateHoldInfo() {
    if (!holdInfoEl) return;
    var keys = Object.keys(heldSeats || {});
    if (!keys.length) {
      holdInfoEl.textContent = 'Если билет в резерве, выберите место и нажмите «Продать». Время резерва можно задать выше.';
      return;
    }

    function formatSeatKey(key) {
      if (typeof key !== 'string') return { row: key, seat: key };
      var parts = key.split(':');
      return { row: parts[0] || key, seat: parts[1] || '' };
    }

    function formatExpires(expiresAt) {
      if (!expiresAt) return 'без срока';
      var dt = new Date(expiresAt);
      if (isNaN(dt.getTime())) return 'неизвестно';
      var delta = Math.ceil((dt.getTime() - Date.now()) / 60000);
      if (delta <= 0) return 'менее 1 мин.';
      return delta + ' мин.';
    }

    var myLines = [];
    var otherLines = [];
    var nearest = null;
    keys.forEach(function (k) {
      var info = heldSeats[k] || {};
      var seat = formatSeatKey(k);
      var expires = formatExpires(info.expires_at);
      var line = 'Ряд: ' + seat.row + ', Место: ' + seat.seat + ' | Истекает через: ' + expires;
      if (info.user_id === currentUserId) {
        myLines.push(line);
      } else {
        otherLines.push(line);
      }
      if (info.expires_at) {
        var dt = new Date(info.expires_at);
        if (!isNaN(dt.getTime()) && (!nearest || dt < nearest)) nearest = dt;
      }
    });

    var html = [];
    if (myLines.length) {
      html.push('<div><strong>Ваши резервы:</strong></div>');
      myLines.forEach(function (line) { html.push('<div>' + line + '</div>'); });
    }
    if (otherLines.length) {
      html.push('<div style="margin-top:8px;"><strong>Резерв другими:</strong></div>');
      otherLines.forEach(function (line) { html.push('<div>' + line + '</div>'); });
    }
    if (nearest) {
      var delta = Math.max(0, Math.round((nearest.getTime() - Date.now()) / 60000));
      html.push('<div style="margin-top:8px;color:#555;">Ближайшее истечение: ' + (delta > 0 ? delta + ' мин.' : 'менее 1 мин.') + '</div>');
    }
    holdInfoEl.innerHTML = html.join('');
  }

  function setReservedState(heldArray) {
    heldSeats = {};
    var reservedKeys = [];
    var ownedKeys = [];
    if (Array.isArray(heldArray)) {
      heldArray.forEach(function (item) {
        if (!item || !item.seat_key) return;
        var key = String(item.seat_key);
        heldSeats[key] = {
          user_id: item.user_id !== undefined ? Number(item.user_id) : null,
          expires_at: item.expires_at || null,
          meta: item.meta || null
        };
        reservedKeys.push(key);
        if (heldSeats[key].user_id === currentUserId) ownedKeys.push(key);
      });
    }
    if (renderer && typeof renderer.setReservedSeats === 'function') {
      renderer.setReservedSeats(reservedKeys, ownedKeys);
    } else if (viewer && typeof viewer.setReservedSeats === 'function') {
      viewer.setReservedSeats(reservedKeys, ownedKeys);
    }
    updateHoldInfo();
  }

  function refreshHoldState(cb) {
    ajax('session', { session_id: sessionId }, function (res) {
      if (!res || !res.success) {
        if (typeof cb === 'function') cb(false, res);
        return;
      }
      var sold = res.data && res.data.sold_seats ? res.data.sold_seats : [];
      var held = res.data && res.data.held_seats ? res.data.held_seats : [];
      soldSeats = {};
      sold.forEach(function (k) { if (k) soldSeats[String(k)] = true; });
      if (renderer && typeof renderer.setSoldSeats === 'function') {
        renderer.setSoldSeats(Object.keys(soldSeats));
      }
      setReservedState(held);
      if (typeof cb === 'function') cb(true, res);
    }, 'GET');
  }

  function prepareSalePayload(extra) {
    extra = extra || {};
    var seatsPayload = buildSeatsPayload();
    var total = seatsPayload.reduce(function(sum, s){ return sum + (Number(s.price) || 0); }, 0);

    var data = {
      seats: seatsPayload,
      total_amount: total,
      customer_id: extra.customer_id || (window.currentCustomerId || null),
      schedule_id: extra.schedule_id || sessionId || null,
      event_id: extra.event_id || null,
      hall_id: extra.hall_id || null,
      segment: extra.segment || null,
      channel: extra.channel || 'kassa',
      payment_method: extra.payment_method || (paymentCashEl && paymentCashEl.checked ? 'cash' : 'card')
    };

    if (extra.amount_paid !== undefined) data.amount_paid = extra.amount_paid;
    return data;
  }

  // --- Renderer integration helpers ---
  function syncSelectionToRenderer() {
    if (renderer && typeof renderer.selectSeats === 'function') {
      renderer.selectSeats(cart);
    } else if (viewer && viewer._internal && typeof viewer._internal.selectSeats === 'function') {
      try { viewer._internal.selectSeats(cart, false); } catch (e) { /* ignore */ }
    } else if (viewer && typeof viewer.updateSelection === 'function') {
      try { viewer.updateSelection(cart); } catch (e) { /* ignore */ }
    }
  }

  function redrawLegend() {
    if (legendRenderer && typeof legendRenderer.renderLegend === 'function') {
      try { legendRenderer.renderLegend(); } catch (e) {}
    }
    if (renderer && typeof renderer.render === 'function') renderer.render();
    else if (viewer && viewer._internal && typeof viewer._internal.renderCanvas === 'function') viewer._internal.renderCanvas();
    else if (viewer && typeof viewer.render === 'function') viewer.render();
  }

  function syncLegendRenderer() {
    if (!window.LegendCanvas || typeof window.LegendCanvas.init !== 'function') return;
    try {
      if (!legendRenderer) {
        legendRenderer = window.LegendCanvas.init('cash-seatmap', { boxSize: 12, gap: 8, itemGap: 12, fontSize: 13, textColor: '#222' });
      }
      if (legendRenderer && typeof legendRenderer.attachToRenderer === 'function') {
        legendRenderer.attachToRenderer(renderer || viewer || null);
      }
      if (legendRenderer && typeof legendRenderer.setPricedColors === 'function') {
        legendRenderer.setPricedColors(pricedColors);
      }
      if (legendRenderer && typeof legendRenderer.renderLegend === 'function') {
        legendRenderer.renderLegend();
      }
    } catch (e) {}
  }

  function normalizeSeatKey(key) {
    if (key === null || key === undefined) return '';
    var value = String(key).trim();
    if (!value) return '';
    return value.replace(/:/g, '-');
  }

  function splitSeatKey(key) {
    var normalized = normalizeSeatKey(key);
    if (!normalized) return { row: null, seat: null, key: '' };
    var parts = normalized.split('-');
    if (parts.length < 2) return { row: null, seat: null, key: normalized };
    return { row: parts[0], seat: parts.slice(1).join('-'), key: normalized };
  }

  function isSoldSeatKey(key) {
    var normalized = normalizeSeatKey(key);
    if (!normalized) return false;
    var parsed = splitSeatKey(normalized);
    var colonKey = parsed.row !== null && parsed.seat !== null ? parsed.row + ':' + parsed.seat : '';
    return !!(soldSeats[normalized] || soldSeats[colonKey] || soldSeats[normalized.replace(/-/g, ':')] || soldSeats[normalized.replace(/:/g, '-')]);
  }

  function setPricedSeat(key, price, color) {
    var normalized = normalizeSeatKey(key);
    if (!normalized && normalized !== '0') return;
    var parsed = splitSeatKey(normalized);
    var priceValue = Number(price);
    if (!isFinite(priceValue)) return;
    pricedSeats[normalized] = priceValue;
    pricedSeats[normalized.replace(/-/g, ':')] = priceValue;
    if (parsed.row !== null && parsed.seat !== null) {
      var colonKey = parsed.row + ':' + parsed.seat;
      var hyphenKey = parsed.row + '-' + parsed.seat;
      pricedSeats[colonKey] = priceValue;
      pricedSeats[hyphenKey] = priceValue;
    }
    if (color) pricedColors[priceValue] = color;
  }

  function extractSeatEntries(seatMap) {
    var entries = [];
    if (!seatMap || !seatMap.seats || typeof seatMap.seats !== 'object') return entries;
    Object.keys(seatMap.seats).forEach(function (key) {
      var seat = seatMap.seats[key];
      if (!seat || typeof seat !== 'object') return;
      var meta = seat.meta && typeof seat.meta === 'object' ? seat.meta : null;
      if (!meta || meta.price === undefined || meta.price === null || String(meta.price).trim() === '') return;
      entries.push({ key: key, price: meta.price, color: meta.color || null });
    });
    return entries;
  }

  // --- Build pricedSeats/pricedColors from direct seat_map.seats and price_ranges overlays ---
  function buildPricedFromRanges(priceRanges, seatMap) {
    pricedSeats = {};
    pricedColors = {};
    if (!seatMap) return;

    extractSeatEntries(seatMap).forEach(function (entry) {
      setPricedSeat(entry.key, entry.price, entry.color);
    });

    var directSeats = seatMap.seats && typeof seatMap.seats === 'object' ? seatMap.seats : {};
    var seatKeys = Object.keys(directSeats);
    var groups = [];

    if (Array.isArray(priceRanges)) {
      groups = priceRanges.slice();
    } else if (priceRanges && typeof priceRanges === 'object') {
      Object.keys(priceRanges).forEach(function (key) {
        var group = priceRanges[key] || {};
        groups.push(Object.assign({}, group, {
          price: (group.price !== undefined && group.price !== null && group.price !== '') ? group.price : (isNaN(Number(key)) ? (group.value ?? group.p ?? null) : Number(key))
        }));
      });
    }

    groups.forEach(function (group) {
      if (!group) return;
      var price = group.price !== undefined && group.price !== null ? Number(group.price) : null;
      if (!isFinite(price)) return;
      if (group.color) pricedColors[price] = group.color;

      if (Array.isArray(group.seat_ids)) {
        group.seat_ids.forEach(function (seatId) {
          setPricedSeat(seatId, price, group.color || null);
        });
        return;
      }

      if (group.row_start !== undefined || group.row_end !== undefined) {
        var rowStart = Number(group.row_start);
        var rowEnd = Number(group.row_end);
        if (!isFinite(rowStart) && !isFinite(rowEnd)) return;
        if (!isFinite(rowStart)) rowStart = rowEnd;
        if (!isFinite(rowEnd)) rowEnd = rowStart;
        if (rowEnd < rowStart) { var tmp = rowStart; rowStart = rowEnd; rowEnd = tmp; }
        seatKeys.forEach(function (seatKey) {
          var parsed = splitSeatKey(seatKey);
          var rowNum = Number(parsed.row);
          if (isFinite(rowNum) && rowNum >= rowStart && rowNum <= rowEnd) {
            setPricedSeat(seatKey, price, group.color || null);
          }
        });
        return;
      }

      if (!Array.isArray(group.ranges)) return;
      group.ranges.forEach(function (range) {
        if (!range) return;
        if (Array.isArray(range.seat_ids)) {
          range.seat_ids.forEach(function (seatId) {
            setPricedSeat(seatId, price, group.color || null);
          });
          return;
        }
        if (range.row !== undefined && range.seat !== undefined) {
          setPricedSeat(String(range.row) + '-' + String(range.seat), price, group.color || null);
          return;
        }
        if (range.type === 'seats' && range.row !== undefined) {
          var fromSeat = Number(range.from);
          var toSeat = Number(range.to);
          if (!isFinite(fromSeat) || !isFinite(toSeat)) return;
          if (toSeat < fromSeat) { var swap = fromSeat; fromSeat = toSeat; toSeat = swap; }
          for (var seatNum = fromSeat; seatNum <= toSeat; seatNum++) {
            setPricedSeat(String(range.row) + '-' + String(seatNum), price, group.color || null);
          }
          return;
        }
        if (range.type === 'rows' || range.row_start !== undefined || range.row_end !== undefined) {
          var startRow = Number(range.row_start !== undefined ? range.row_start : range.from);
          var endRow = Number(range.row_end !== undefined ? range.row_end : range.to);
          if (!isFinite(startRow) || !isFinite(endRow)) return;
          if (endRow < startRow) { var swapRow = startRow; startRow = endRow; endRow = swapRow; }
          seatKeys.forEach(function (seatKey) {
            var parsed = splitSeatKey(seatKey);
            var rowNum = Number(parsed.row);
            if (isFinite(rowNum) && rowNum >= startRow && rowNum <= endRow) {
              setPricedSeat(seatKey, price, group.color || null);
            }
          });
        }
      });
    });

    Object.keys(pricedSeats).forEach(function (k) {
      var p = pricedSeats[k];
      if (p == null) return;
      if (!pricedColors[p]) pricedColors[p] = pickColorForValue(p);
    });
  }

  // --- Legacy viewer fallback init ---
  function initLegacyViewerFallback(seatMap, pricedSeatsMap, pricedColorsMap, soldArray) {
    if (typeof window.SeatmapViewer !== 'undefined' && typeof window.SeatmapViewer.create === 'function') {
      try {
        viewer = window.SeatmapViewer.create({
          canvasId: canvasEl.id,
          seatMap: seatMap,
          pricedSeats: pricedSeatsMap,
          pricedColors: pricedColorsMap,
          soldSeats: soldArray || []
        });
        if (viewer && typeof viewer.on === 'function') {
          viewer.on('seat:click', function (key, info) {
            if (!key) return;
            if (soldSeats[key]) return;
            if (typeof pricedSeats[key] === 'undefined') {
              showToast('Место не доступно для продажи (нет цены)');
              return;
            }
            toggleCart(key);
            renderCart();
            if (viewer && typeof viewer.updateSelection === 'function') viewer.updateSelection(cart);
            redrawLegend();
          });
        } else {
          canvasEl.addEventListener('click', function (e) {
            if (!viewer || typeof viewer.getSeatAt !== 'function') return;
            var rect = canvasEl.getBoundingClientRect();
            var cssX = e.clientX - rect.left;
            var cssY = e.clientY - rect.top;
            var hit = viewer.getSeatAt(cssX, cssY);
            if (hit && hit.key) {
              var key = hit.key;
              if (soldSeats[key]) return;
              if (typeof pricedSeats[key] === 'undefined') {
                showToast('Место не доступно для продажи (нет цены)');
                return;
              }
              toggleCart(key);
              renderCart();
              if (viewer && typeof viewer.updateSelection === 'function') viewer.updateSelection(cart);
              redrawLegend();
            }
          });
        }
      } catch (e) {
        console.error('SeatmapViewer fallback error', e);
      }
    } else if (typeof window.seatmapViewer !== 'undefined' && window.seatmapViewer._internal) {
      try {
        if (typeof window.seatmapViewer.init === 'function') {
          window.seatmapViewer.init({ canvasId: canvasEl.id, canvasWrapperId: containerEl ? containerEl.id : null });
        }
        if (window.seatmapViewer._internal && typeof window.seatmapViewer._internal.buildSeatIndex === 'function') {
          window.seatmapViewer._internal.buildSeatIndex(seatMap);
          try { window.seatmapViewer._internal.applyPriceRanges && window.seatmapViewer._internal.applyPriceRanges(pricedSeatsMap); } catch (e) { /* ignore */ }
          if (Array.isArray(soldArray)) {
            var si = window.seatmapViewer._internal.seatIndexRef();
            soldArray.forEach(function (k) { if (si && si[k]) si[k].sold = true; });
          }
          window.seatmapViewer._internal.fitContentToView && window.seatmapViewer._internal.fitContentToView();
          window.seatmapViewer._internal.renderCanvas && window.seatmapViewer._internal.renderCanvas();
        }
        canvasEl.addEventListener('click', function (e) {
          try {
            var hit = null;
            if (window.seatmapViewer._internal && typeof window.seatmapViewer._internal.seatAtCanvasPoint === 'function') {
              hit = window.seatmapViewer._internal.seatAtCanvasPoint(e.clientX, e.clientY);
            }
            if (hit && hit.key) {
              var key = hit.key;
              if (soldSeats[key]) return;
              if (typeof pricedSeats[key] === 'undefined') {
                showToast('Место не доступно для продажи (нет цены)');
                return;
              }
              toggleCart(key);
              renderCart();
              try { window.seatmapViewer._internal.selectSeats(cart, false); } catch (err) { /* ignore */ }
              try { window.seatmapViewer._internal.renderCanvas && window.seatmapViewer._internal.renderCanvas(); } catch (err) { /* ignore */ }
            }
          } catch (err) { /* ignore */ }
        });
      } catch (e) {
        console.error('legacy seatmapViewer integration error', e);
      }
    } else {
      console.warn('No seatmap renderer available. Canvas will remain empty.');
      showToast('seatmap renderer не загружен. Схема не будет отрисована.');
    }
  }

  // --- Payment method helpers (checkbox mutual exclusivity) ---
  function setupPaymentCheckboxes(cashEl, cardEl) {
    if (!cashEl || !cardEl) return;
    cashEl.addEventListener('change', function () {
      if (cashEl.checked) cardEl.checked = false;
    });
    cardEl.addEventListener('change', function () {
      if (cardEl.checked) cashEl.checked = false;
    });
  }

  function getSelectedPaymentMethod() {
    if (paymentMethodEl) {
      if (paymentMethodEl.tagName && paymentMethodEl.tagName.toLowerCase() === 'select') {
        var v = (paymentMethodEl.value || '').trim();
        return v || 'cash';
      }
      if (paymentMethodEl.type === 'checkbox' || paymentMethodEl.type === 'radio') {
        return paymentMethodEl.checked ? (paymentMethodEl.value || '') : '';
      }
      var raw = (paymentMethodEl.value || '').trim();
      return raw;
    }

    if (paymentCashEl && paymentCardEl) {
      if (paymentCashEl.checked) return 'cash';
      if (paymentCardEl.checked) return 'card';
      return '';
    }
    return '';
  }

  // --- Main init function ---
  function initSell(options) {
    options = options || {};
    cfg.csrf = options.csrf || cfg.csrf;
    sessionId = options.sessionId || sessionId;

    var ids = {
      canvasId: options.seatmapCanvasId || DEFAULTS.canvasId,
      containerId: options.containerId || options.seatmapContainerId || DEFAULTS.containerId,
      legendContainerId: options.legendContainerId || DEFAULTS.legendContainerId,
      cartListId: options.cartListId || DEFAULTS.cartListId,
      amountInputId: options.amountInputId || DEFAULTS.amountInputId,
      reserveBtnId: options.reserveBtnId || DEFAULTS.reserveBtnId,
      sellBtnId: options.sellBtnId || DEFAULTS.sellBtnId,
      reserveTtlInputId: options.reserveTtlInputId || DEFAULTS.reserveTtlInputId,
      holdInfoId: options.holdInfoId || DEFAULTS.holdInfoId,
      paymentMethodId: options.paymentMethodId || DEFAULTS.paymentMethodId,
      paymentCashId: options.paymentCashId || DEFAULTS.paymentCashId,
      paymentCardId: options.paymentCardId || DEFAULTS.paymentCardId,
      customerSegmentId: options.customerSegmentId || DEFAULTS.customerSegmentId,
      manualDiscountAmountId: options.manualDiscountAmountId || DEFAULTS.manualDiscountAmountId,
      customDiscountTypeId: options.customDiscountTypeId || DEFAULTS.customDiscountTypeId,
      customDiscountValueId: options.customDiscountValueId || DEFAULTS.customDiscountValueId,
      baseTotalOutputId: options.baseTotalOutputId || DEFAULTS.baseTotalOutputId,
      finalTotalOutputId: options.finalTotalOutputId || DEFAULTS.finalTotalOutputId,
      discountTotalOutputId: options.discountTotalOutputId || DEFAULTS.discountTotalOutputId,
      segmentDiscountHintId: options.segmentDiscountHintId || DEFAULTS.segmentDiscountHintId,
      customDiscountHintId: options.customDiscountHintId || DEFAULTS.customDiscountHintId
    };

    canvasEl = document.getElementById(ids.canvasId);
    containerEl = document.getElementById(ids.containerId) || (canvasEl ? canvasEl.parentElement : null);
    legendContainer = document.getElementById(ids.legendContainerId);
    cartListEl = document.getElementById(ids.cartListId);
    amountInputEl = document.getElementById(ids.amountInputId);
    reserveBtnEl = document.getElementById(ids.reserveBtnId);
    sellBtnEl = document.getElementById(ids.sellBtnId);
    reserveTtlInputEl = document.getElementById(ids.reserveTtlInputId);
    holdInfoEl = document.getElementById(ids.holdInfoId);
    customerSegmentEl = document.getElementById(ids.customerSegmentId);
    manualDiscountAmountEl = document.getElementById(ids.manualDiscountAmountId);
    customDiscountTypeEl = document.getElementById(ids.customDiscountTypeId);
    customDiscountValueEl = document.getElementById(ids.customDiscountValueId);
    baseTotalOutputEl = document.getElementById(ids.baseTotalOutputId);
    finalTotalOutputEl = document.getElementById(ids.finalTotalOutputId);
    segmentDiscountHintEl = document.getElementById(ids.segmentDiscountHintId);
    discountTotalOutputEl = document.getElementById(ids.discountTotalOutputId);
    customDiscountHintEl = document.getElementById(ids.customDiscountHintId);
    var onSessionLoaded = typeof options.onSessionLoaded === 'function' ? options.onSessionLoaded : null;
    currentUserId = Number(options.currentUserId || 0);
    if (options.discountConfig && typeof options.discountConfig === 'object') {
      discountConfig = Object.assign({}, discountConfig, options.discountConfig);
      if (!discountConfig.segment_percent) {
        discountConfig.segment_percent = { adult: 0, child: 0, student: 0, senior: 0 };
      }
    }
    if (!canvasEl) {
      console.error('Canvas element not found: ' + ids.canvasId);
      return;
    }

    paymentMethodEl = document.getElementById(ids.paymentMethodId);
    paymentCashEl = document.getElementById(ids.paymentCashId);
    paymentCardEl = document.getElementById(ids.paymentCardId);

    setupPaymentCheckboxes(paymentCashEl, paymentCardEl);

    if (customerSegmentEl) customerSegmentEl.addEventListener('change', refreshDiscountUi);
    if (manualDiscountAmountEl) manualDiscountAmountEl.addEventListener('input', refreshDiscountUi);
    if (customDiscountTypeEl) customDiscountTypeEl.addEventListener('change', refreshDiscountUi);
    if (customDiscountValueEl) customDiscountValueEl.addEventListener('input', refreshDiscountUi);

    // Load session data
    ajax('session', { session_id: sessionId }, function (res) {
      if (!res || !res.success) {
        alert(res && res.message ? res.message : 'Ошибка загрузки сеанса');
        return;
      }

      var session = res.data && res.data.session ? res.data.session : null;
      var sold = res.data && res.data.sold_seats ? res.data.sold_seats : [];
      var held = res.data && res.data.held_seats ? res.data.held_seats : [];
      try {
        if (onSessionLoaded && session) onSessionLoaded(session);
      } catch (e) {
        console.error('onSessionLoaded callback error', e);
      }

      var seatMap = null, priceRanges = null;
      try { if (session && session.seat_map) seatMap = JSON.parse(session.seat_map); } catch (e) { seatMap = null; }
      try { if (session && session.price_ranges) priceRanges = JSON.parse(session.price_ranges); } catch (e) { priceRanges = null; }

      buildPricedFromRanges(priceRanges, seatMap);

      soldSeats = {};
      if (Array.isArray(sold)) sold.forEach(function (k) { if (k) soldSeats[String(k)] = true; });

      setReservedState(held);

      if (options.seatmapRenderer && typeof options.seatmapRenderer.create === 'function') {
        try {
          renderer = options.seatmapRenderer.create({
            canvasId: ids.canvasId,
            containerId: ids.containerId,
            hideLabelsWhenNoPrice: true
          });

          if (seatMap) renderer.load(seatMap);
          renderer.applyPriceMap(pricedSeats, pricedColors);
          renderer.setSoldSeats(Object.keys(soldSeats));
          if (typeof renderer.setReservedSeats === 'function') {
            var reservedKeys = Object.keys(heldSeats || {});
            var ownedKeys = reservedKeys.filter(function (k) { return heldSeats[k] && heldSeats[k].user_id === currentUserId; });
            renderer.setReservedSeats(reservedKeys, ownedKeys);
          }
          renderer.fitToCanvas();
          renderer.render();
          syncLegendRenderer();

          renderer.on('seat:click', function (key, info) {
            if (!key) return;
            if (isSoldSeatKey(key)) {
              showToast('Место продано, выберите другое место для продажи');
              return;
            }
            if (heldSeats[key] && heldSeats[key].user_id !== currentUserId) {
              showToast('Место занято резервом другого кассира');
              return;
            }
            if (typeof pricedSeats[key] === 'undefined') {
              showToast('Место не доступно для продажи (нет цены)');
              return;
            }
            toggleCart(key);
            selectedCartSeatKey = key;
            renderCart();
            renderer.selectSeats(cart);
            renderer.render();
          });
        } catch (err) {
          console.error('Renderer init error', err);
          initLegacyViewerFallback(seatMap, pricedSeats, pricedColors, sold);
        }
      } else if (typeof window.SeatmapRenderer !== 'undefined' && typeof window.SeatmapRenderer.create === 'function') {
        try {
          renderer = window.SeatmapRenderer.create({ canvasId: ids.canvasId, containerId: ids.containerId, hideLabelsWhenNoPrice: true });
          if (seatMap) renderer.load(seatMap);
          renderer.applyPriceMap(pricedSeats, pricedColors);
          renderer.setSoldSeats(Object.keys(soldSeats));
          if (typeof renderer.setReservedSeats === 'function') {
            var reservedKeys = Object.keys(heldSeats || {});
            var ownedKeys = reservedKeys.filter(function (k) { return heldSeats[k] && heldSeats[k].user_id === currentUserId; });
            renderer.setReservedSeats(reservedKeys, ownedKeys);
          }
          renderer.fitToCanvas();
          renderer.render();
          syncLegendRenderer();
          renderer.on('seat:click', function (key, info) {
            if (!key) return;
            if (isSoldSeatKey(key)) {
              showToast('Место продано, выберите другое место для продажи');
              return;
            }
            if (heldSeats[key] && heldSeats[key].user_id !== currentUserId) {
              showToast('Место занято резервом другого кассира');
              return;
            }
            if (typeof pricedSeats[key] === 'undefined') {
              showToast('Место не доступно для продажи (нет цены)');
              return;
            }
            toggleCart(key);
            selectedCartSeatKey = key;
            renderCart();
            renderer.selectSeats(cart);
            renderer.render();
          });
        } catch (err) {
          console.error('Renderer global init error', err);
          initLegacyViewerFallback(seatMap, pricedSeats, pricedColors, sold);
        }
      } else {
        initLegacyViewerFallback(seatMap, pricedSeats, pricedColors, sold);
      }

      // wire reserve/sell buttons
      if (reserveBtnEl) {
        reserveBtnEl.addEventListener('click', function () {
          if (!cart.length) return alert('Выберите места');
          var ttl = 10;
          if (reserveTtlInputEl) {
            var parsed = parseInt(reserveTtlInputEl.value, 10);
            if (Number.isFinite(parsed) && parsed > 0) ttl = parsed;
          }
          ajax('hold', { session_id: sessionId, seats: cart, ttl_minutes: ttl }, function (resp) {
            if (!resp || !resp.success) return alert(resp && resp.message ? resp.message : 'Ошибка создания резерва');
            showToast('Резерв создан');
            cart = [];
            selectedCartSeatKey = null;
            renderCart();
            syncSelectionToRenderer();
            redrawLegend();
            refreshHoldState();
          });
        });
      }
      if (sellBtnEl) {
        sellBtnEl.addEventListener('click', function () {
          if (!cart.length) return alert('Корзина пуста');
          var pm = getSelectedPaymentMethod();
          if (!pm) {
            showToast('Выберите способ оплаты: Наличные или Картой');
            return;
          }

          // collect customer data and normalize phone
          function normalizePhone(p) { return (p || '').replace(/\D+/g, ''); }
          var customer = {
            full_name: (document.getElementById('custFullName') || {}).value.trim() || '',
            phone: normalizePhone((document.getElementById('custPhone') || {}).value || ''),
            email: (document.getElementById('custEmail') || {}).value.trim() || '',
            gender: (document.getElementById('custGender') || {}).value || '',
            city: (document.getElementById('custCity') || {}).value.trim() || ''
          };
          if (!customer.phone) {
            showToast('Номер телефона обязателен');
            return;
          }
          var customer_segment = (document.getElementById('customer_segment') || {}).value || 'adult';
          var manualDiscountAmount = (customer_segment === 'manual' && discountConfig.custom_enabled) ? getManualDiscountInputValue() : 0;

          // Build payload safely: structured seats + legacy keys
          var seatsPayload = buildSeatsPayload();
          var discountInfo = calculateDiscounts(seatsPayload.reduce(function(sum, s){ return sum + (Number(s.price) || 0); }, 0));
          var discountedSeats = distributeSeatPrices(seatsPayload, discountInfo.final_total);
          var totalAmount = Math.round(discountInfo.final_total);

          var payload = {
            session_id: sessionId,
            seats: discountedSeats,         // structured payload (id, identifier, discounted price)
            seats_keys: cart.slice(),       // legacy: array of "row:seat" strings
            payment_method: pm,
            amount_cents: totalAmount,
            customer: customer,
            customer_segment: customer_segment,
            discount: {
              segment: discountInfo.segment,
              auto_percent: discountInfo.auto_percent,
              auto_amount: discountInfo.auto_amount,
              custom_type: discountInfo.custom_type,
              custom_value: discountInfo.custom_value,
              custom_amount: discountInfo.custom_amount,
              manual_amount: manualDiscountAmount,
              total_discount: discountInfo.total_discount,
              final_total: discountInfo.final_total,
              base_total: discountInfo.base_total
            }
          };

          // debug (temporary)
          // console.log('SELL payload', payload);

          ajax('sell', payload, function (resp) {
            if (!resp || !resp.success) {
              return alert(resp && resp.message ? resp.message : 'Ошибка продажи');
            }
            cart.forEach(function (k) { soldSeats[k] = true; });
            cart = [];
            if (renderer && typeof renderer.setSoldSeats === 'function') {
              renderer.setSoldSeats(Object.keys(soldSeats));
            } else if (viewer && typeof viewer.updateSoldSeats === 'function') {
              viewer.updateSoldSeats(Object.keys(soldSeats));
            }
            renderCart();
            syncSelectionToRenderer();
            redrawLegend();
            showToast('Продано');
            // update header counters if present
            try {
              var soldEl = document.getElementById('soldCountHeader');
              var freeEl = document.getElementById('freeSeatsHeader');
              var delta = resp.ticket_uids ? resp.ticket_uids.length : 1;
              if (soldEl) soldEl.textContent = (parseInt(soldEl.textContent || '0', 10) || 0) + delta;
              if (freeEl && freeEl.textContent !== '—') {
                var freeVal = parseInt(freeEl.textContent || '0', 10) || 0;
                freeEl.textContent = Math.max(0, freeVal - delta);
              }
            } catch (e) { /* ignore */ }

            if (resp.ticket_uids && Array.isArray(resp.ticket_uids) && resp.ticket_uids.length) {
              var ticketUids = resp.ticket_uids;
              var previewUrl = '/tickets/generate.php?uid=' + encodeURIComponent(ticketUids[0]);
              if (window.openTicketPreview && typeof window.openTicketPreview === 'function') {
                window.openTicketPreview(previewUrl, 'Билет ' + ticketUids[0]);
              } else {
                var viewUrl = '/tickets/view.php?uids=' + ticketUids.map(encodeURIComponent).join(',');
                window.open(viewUrl, '_blank');
              }
            }
          });
        });
      }

      renderCart();
      refreshDiscountUi();
      redrawLegend();
    }, 'GET');
  }

  // --- Public API ---
  return {
    initSell: initSell,
    getCart: function () { return cart.slice(); },
    clearCart: function () { cart = []; renderCart(); syncSelectionToRenderer(); redrawLegend(); },
    refreshHoldState: refreshHoldState,
    redrawLegend: redrawLegend
  };
})();
