// /assets/js/tickets_list.js
// Tickets list with cross-filtered typeahead, immediate filtering, per-page selector,
// session suggestion fills visible "Title — date" and hidden raw value (id or title).
// Refund modal: shows ticket info and submits refund details to backend.

(function(){
  'use strict';

  var csrfToken = window.APP_CSRF_TOKEN || (document.getElementById('refund_csrf') ? document.getElementById('refund_csrf').value : '');
  var page = 1, perPage = 25, totalPages = 1, totalCount = 0;
  var sessionSuggestTimer = null;
  var uidSuggestTimer = null;
  var customerSuggestTimer = null;
  var filterDebounceTimer = null;

  try {
    var stored = parseInt(localStorage.getItem('tickets_per_page'), 10);
    if (stored === 25 || stored === 50 || stored === 100 || stored === 500) perPage = stored;
  } catch (e) {}

  function qs(id){ return document.getElementById(id); }

  function getFilters() {
    var sessionRawEl = qs('filter_session_raw');
    var sessionValueToSend = '';
    if (sessionRawEl && sessionRawEl.value && sessionRawEl.value.trim() !== '') {
      sessionValueToSend = sessionRawEl.value.trim();
    } else {
      var vis = qs('filter_session');
      sessionValueToSend = vis ? vis.value.trim() : '';
    }

    return {
      date_from: qs('filter_date_from') ? qs('filter_date_from').value || '' : '',
      date_to: qs('filter_date_to') ? qs('filter_date_to').value || '' : '',
      session: sessionValueToSend || '',
      uid: qs('filter_uid') ? qs('filter_uid').value || '' : '',
      customer: qs('filter_customer') ? qs('filter_customer').value || '' : '',
      status: qs('filter_status') ? qs('filter_status').value || '' : '',
      payment_status: qs('filter_payment') ? qs('filter_payment').value || '' : '',
      channel: qs('filter_channel') ? qs('filter_channel').value || '' : '',
      customer_segment: qs('filter_segment') ? qs('filter_segment').value || '' : '',
      refund: qs('filter_refund') ? qs('filter_refund').value || '' : '',
      include_seat: 1,
      page: page,
      per_page: perPage
    };
  }

  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
  }

  function showToast(msg, type, duration) {
    duration = duration || 3000;
    if (window.showToast && typeof window.showToast === 'function') {
      try { window.showToast(msg, type || 'info', { duration: duration }); return; } catch(e) {}
    }
    var wrap = document.getElementById('toast-wrap');
    if (!wrap) return;
    var el = document.createElement('div');
    el.textContent = msg;
    el.style.background = (type === 'success') ? '#2d9c2d' : ((type === 'error') ? '#c0392b' : '#2d6fa3');
    el.style.color = '#fff';
    el.style.padding = '8px 12px';
    el.style.borderRadius = '8px';
    el.style.marginTop = '6px';
    wrap.appendChild(el);
    setTimeout(function(){ try{ el.remove(); }catch(e){} }, duration);
  }

  function mapSegment(code) {
    var map = { adult: 'Взрослый', child: 'Детский', student: 'Студенческий', senior: 'Пенсионный', vip: 'VIP' };
    return map[code] || (code || '—');
  }

  function mapChannel(code) {
    var map = { web: 'Веб', mobile: 'Мобильный', kassa: 'Касса', agent: 'Агент', qr: 'QR', admin: 'Админ' };
    return map[code] || (code || '—');
  }

  function formatCreatedAt(dt) {
    if (!dt) return '—';
    var d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    var dd = String(d.getDate()).padStart(2,'0');
    var mm = String(d.getMonth()+1).padStart(2,'0');
    var yy = String(d.getFullYear()).slice(-2);
    var hh = String(d.getHours()).padStart(2,'0');
    var min = String(d.getMinutes()).padStart(2,'0');
    var ss = String(d.getSeconds()).padStart(2,'0');
    return dd + '.' + mm + '.' + yy + '\n' + hh + ':' + min + ':' + ss;
  }

  function formatSessionDate(dt) {
    if (!dt) return '—';
    var d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    var dd = String(d.getDate()).padStart(2,'0');
    var mm = String(d.getMonth()+1).padStart(2,'0');
    var yy = String(d.getFullYear()).slice(-2);
    return dd + '.' + mm + '.' + yy + '.';
  }

  function deriveSeatLabel(t) {
    if (!t) return '';
    if (t.seat_label && String(t.seat_label).trim() !== '') return String(t.seat_label).trim();
    if (t.seat_identifier && String(t.seat_identifier).trim() !== '') return String(t.seat_identifier).trim().replace(/:/g, ' - ');
    if (t.seat && String(t.seat).trim() !== '') return String(t.seat).trim();
    if (t.seat_id !== undefined && t.seat_id !== null && String(t.seat_id).trim() !== '') return String(t.seat_id).trim();

    var candidates = ['payload','meta','ticket_meta','data'];
    for (var i = 0; i < candidates.length; i++) {
      var key = candidates[i];
      if (!t.hasOwnProperty(key)) continue;
      var raw = t[key];
      if (!raw) continue;
      if (typeof raw === 'object') {
        var lbl = extractSeatFromObject(raw);
        if (lbl) return lbl;
      } else if (typeof raw === 'string') {
        try {
          var parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            var lbl2 = extractSeatFromObject(parsed);
            if (lbl2) return lbl2;
          }
        } catch (e) {
          var s = String(raw).trim();
          if (s.indexOf(':') !== -1) return s.replace(/:/g, ' - ');
          var m = s.match(/row[:=]\s*([^;,\s]+)[;,\s]*seat[:=]\s*([^;,\s]+)/i);
          if (m) return m[1] + ' - ' + m[2];
        }
      }
    }

    return '';
  }

  function isRefundedTicket(t) {
    if (!t || typeof t !== 'object') return false;

    var rs = String(t.refund_status || '').toLowerCase();
    if (rs && rs !== 'none' && rs !== 'no' && rs !== '0' && rs !== 'false') return true;

    var rv = String(t.refund || t.is_refunded || '').toLowerCase();
    if (rv === 'yes' || rv === 'да' || rv === '1' || rv === 'true') return true;

    return false;
  }

  function extractSeatFromObject(obj) {
    if (!obj || typeof obj !== 'object') return '';
    if (obj.seat_label && String(obj.seat_label).trim() !== '') return String(obj.seat_label).trim();
    if (obj.seat_identifier && String(obj.seat_identifier).trim() !== '') return String(obj.seat_identifier).trim().replace(/:/g, ' - ');
    if (obj.seat && String(obj.seat).trim() !== '') return String(obj.seat).trim();
    if (obj.seat_id !== undefined && obj.seat_id !== null && String(obj.seat_id).trim() !== '') return String(obj.seat_id).trim();
    if (obj.row && obj.seat) return String(obj.row).trim() + ' - ' + String(obj.seat).trim();
    if (obj.row_label && obj.seat_number) return String(obj.row_label).trim() + ' - ' + String(obj.seat_number).trim();
    if (obj.seat && typeof obj.seat === 'object') {
      var s = obj.seat;
      if (s.identifier) return String(s.identifier).replace(/:/g, ' - ');
      if (s.label) return String(s.label);
      if (s.row && s.number) return String(s.row) + ' - ' + String(s.number);
    }
    return '';
  }

  function renderRow(t) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-id', t.id);

    var timeTd = document.createElement('td');
    timeTd.textContent = formatCreatedAt(t.created_at || t.purchased_at || '');
    timeTd.style.whiteSpace = 'pre-line';
    timeTd.style.lineHeight = '1.35';
    tr.appendChild(timeTd);

    var sessionTd = document.createElement('td');
    var sessionHtml = '';
    if (t.event_title) sessionHtml += '<strong>' + escapeHtml(t.event_title) + '</strong><br>';
    sessionHtml += t.session_date_raw ? escapeHtml(formatSessionDate(t.session_date_raw)) : (t.session_date ? escapeHtml(t.session_date) : '—');
    sessionTd.innerHTML = sessionHtml;
    tr.appendChild(sessionTd);

    var clientTd = document.createElement('td');
    clientTd.textContent = t.customer_name || '—';
    tr.appendChild(clientTd);

    var seatTd = document.createElement('td');
    var seatLabel = deriveSeatLabel(t);
    seatTd.innerHTML = seatLabel ? escapeHtml(seatLabel) : '<span style="color:#888">—</span>';
    tr.appendChild(seatTd);

    // Тип: render as element with class "badge" and set only background color inline
    var typeTd = document.createElement('td');
    var segSpan = document.createElement('span');
    segSpan.className = 'badge';
    var segLabel = mapSegment(t.customer_segment);
    segSpan.textContent = segLabel;

    // determine background color only
    var segKey = (t.customer_segment || '').toString().toLowerCase();
    var segLabelLower = segLabel ? segLabel.toString().toLowerCase() : '';

    var bgColor = '#185d2f'; // default adult green
    if (segKey === 'student' || segLabelLower.indexOf('студ') !== -1) {
      bgColor = '#6a1b9a'; // purple
    } else if (segKey === 'child' || segLabelLower.indexOf('дет') !== -1) {
      bgColor = '#2d6fa3'; // blue
    } else if (segKey === 'senior' || segLabelLower.indexOf('пенс') !== -1) {
      bgColor = '#7f8c8d'; // gray
    } else if (segKey === 'vip' || segLabelLower === 'vip') {
      bgColor = '#b8860b'; // gold
    } else {
      bgColor = '#185d2f';
    }
    // set only background color inline; other styling comes from CSS
    segSpan.style.background = bgColor;

    typeTd.appendChild(segSpan);
    tr.appendChild(typeTd);

    var discountTd = document.createElement('td');
    var discountValue = Number(t.discount_amount || 0);
    if (discountValue > 0) {
      var baseValue = Number(t.base_price || 0);
      discountTd.innerHTML = '<div style="font-weight:600; color:#185d2f;">-' + discountValue.toLocaleString() + ' тг</div>' +
        '<div style="font-size:11px; color:#6a7b90;">из ' + baseValue.toLocaleString() + ' тг</div>';
    } else {
      discountTd.innerHTML = '<span style="color:#888">—</span>';
    }
    tr.appendChild(discountTd);

    var priceTd = document.createElement('td');
    priceTd.textContent = (t.price !== null && t.price !== undefined) ? (Number(t.price).toLocaleString() + ' тг') : '—';
    tr.appendChild(priceTd);

    var channelTd = document.createElement('td');
    channelTd.textContent = mapChannel(t.channel);
    tr.appendChild(channelTd);

    var paymentTypeTd = document.createElement('td');
    paymentTypeTd.textContent = t.payment_type || '—';
    tr.appendChild(paymentTypeTd);	  

    var orderTd = document.createElement('td');
    orderTd.textContent = t.order_number || '—';
    tr.appendChild(orderTd);
	  
    var uidTd = document.createElement('td');
    uidTd.textContent = t.ticket_uid || '—';
    tr.appendChild(uidTd);

    // Determine refund state early so payment cell can reflect it
    var isRefunded = isRefundedTicket(t);

    var payTd = document.createElement('td');
    if (isRefunded) {
      // If refunded, show simple dash (прочерк) as requested
      payTd.textContent = '—';
    } else {
      var payBadge = document.createElement('span');
      payBadge.className = 'badge';
      if (t.payment_status === 'paid') { payBadge.style.background = '#1a7f37'; payBadge.textContent = 'Оплачен'; }
      else if (t.payment_status === 'pending') { payBadge.style.background = '#2d6fa3'; payBadge.textContent = 'Ожидает'; }
      else if (t.payment_status === 'failed') { payBadge.style.background = '#a00'; payBadge.textContent = 'Ошибка'; }
      else { payBadge.style.background = '#777'; payBadge.textContent = (t.payment_status || '—'); }
      payTd.appendChild(payBadge);
    }
    tr.appendChild(payTd);

    var statusTd = document.createElement('td');
    var st = document.createElement('span');
    st.className = 'badge';
    if (t.status === 'issued') { st.style.background = '#2d6fa3'; st.textContent = 'Выдан'; }
    else if (t.status === 'used') { st.style.background = '#1a7f37'; st.textContent = 'Использован'; }
    else if (t.status === 'cancelled') { st.style.background = '#a00'; st.textContent = 'Отменён'; }
    else { st.style.background = '#777'; st.textContent = (t.status || '—'); }
    statusTd.appendChild(st);
    tr.appendChild(statusTd);

    var refundTd = document.createElement('td');
    refundTd.textContent = isRefunded ? 'Да' : 'Нет';
    tr.appendChild(refundTd);

    var actionsTd = document.createElement('td');
    actionsTd.className = 'actions-col';
    var wrap = document.createElement('div');
    wrap.className = 'action-buttons';

    if (!isRefunded) {
      var refundBtn = document.createElement('button');
      refundBtn.className = 'btn btn-ghost btn-xs';
      refundBtn.textContent = 'Возврат';
      refundBtn.dataset.ticketId = t.id;
      refundBtn.disabled = (t.status === 'cancelled');
      refundBtn.addEventListener('click', onRefundClick);
      wrap.appendChild(refundBtn);
    }

    var viewBtn = document.createElement('button');
    viewBtn.className = 'btn btn-ghost btn-xs';
    viewBtn.textContent = 'Просмотр';
    viewBtn.addEventListener('click', function(){
      var ticketUid = t.ticket_uid || '';
      if (!ticketUid) {
        window.location.href = '/tickets/view.php?id=' + encodeURIComponent(t.id);
        return;
      }
      var previewUrl = '/tickets/generate.php?uid=' + encodeURIComponent(ticketUid);
      if (window.openTicketPreview && typeof window.openTicketPreview === 'function') {
        window.openTicketPreview(previewUrl, 'Билет ' + ticketUid);
      } else {
        window.open(previewUrl, '_blank');
      }
    });
    wrap.appendChild(viewBtn);

    actionsTd.appendChild(wrap);
    tr.appendChild(actionsTd);

    return tr;
  }

  function loadTickets() {
    var tbody = qs('ticketsTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="15" style="text-align:center; color:#666; padding:18px;">Загрузка...</td></tr>';

    var params = getFilters();
    params.csrf_token = csrfToken;

    var q = Object.keys(params).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');

    fetch('/ajax/ticket.php?action=list', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: q
    }).then(function(r){ return r.json(); })
    .then(function(json){
      if (!json || !json.success) { showToast(json && json.message ? json.message : 'Ошибка загрузки', 'error'); return; }

      var rows = json.data && Array.isArray(json.data.tickets) ? json.data.tickets : [];
      totalCount = json.data && json.data.total ? Number(json.data.total) : 0;
      totalPages = Math.max(1, Math.ceil(totalCount / perPage));
      var currentPageEl = qs('currentPage');
      if (currentPageEl) currentPageEl.textContent = page + ' / ' + totalPages;
      var summaryEl = qs('ticketsSummary');
      if (summaryEl) summaryEl.textContent = 'Найдено: ' + totalCount;

      tbody.innerHTML = '';
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center; color:#666; padding:18px;">Ничего не найдено</td></tr>';
        return;
      }
      rows.forEach(function(t){
        if (t.session_start_raw) t.session_date_raw = t.session_start_raw;
        tbody.appendChild(renderRow(t));
      });
    }).catch(function(err){
      console.error(err);
      showToast('Ошибка сети при загрузке', 'error');
    });
  }

  function prevPage(){ if (page > 1) { page--; loadTickets(); } }
  function nextPage(){ if (page < totalPages) { page++; loadTickets(); } }

  function scheduleFilterApplyDebounced() {
    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(function(){
      page = 1;
      loadTickets();
    }, 350);
  }

  function resetFilters(){
    ['filter_date_from','filter_date_to','filter_session','filter_session_raw','filter_uid','filter_customer','filter_status','filter_payment','filter_channel','filter_segment','filter_refund'].forEach(function(id){ var el = qs(id); if (el) el.value = ''; });
    hideSuggestions('session');
    hideSuggestions('uid');
    hideSuggestions('customer');
    page = 1; loadTickets();
  }

  // Refund modal logic
  function onRefundClick(e) {
    var ticketId = e.currentTarget.dataset.ticketId;
    if (!ticketId) return;
    openRefundModal(ticketId);
  }

  function openRefundModal(ticketId) {
    fetch('/ajax/ticket.php?action=get_ticket&id=' + encodeURIComponent(ticketId), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (!json || !json.success) { showToast(json && json.message ? json.message : 'Ошибка получения билета', 'error'); return; }
        var t = json.data;
        populateRefundModal(t);
        showRefundModal();
      }).catch(function(err){
        console.error(err);
        showToast('Ошибка сети при получении билета', 'error');
      });
  }

  function populateRefundModal(t) {
    qs('refund_ticket_id').value = t.id || '';
    qs('refund_client').textContent = t.customer_name || '—';
    qs('refund_uid').textContent = t.ticket_uid || '—';
    var sessionLabel = (t.event_title ? t.event_title : '') + (t.schedule_id ? ' #' + (t.schedule_id || '') : '');
    qs('refund_session').textContent = sessionLabel || '—';
    qs('refund_session_dt').textContent = t.session_start ? (new Date(t.session_start)).toLocaleString() : '—';
    var seat = t.seat_label || t.seat_identifier || t.seat || '—';
    qs('refund_seat').textContent = seat;
    var amtEl = qs('refund_amount');
    if (amtEl) {
      if (t.price !== null && t.price !== undefined && t.price !== '') {
        amtEl.value = Number(t.price);
      } else {
        amtEl.value = '';
      }
    }
    qs('refund_method').value = 'cash';
    qs('refund_provider').value = '';
    qs('refund_transaction_id').value = '';
    qs('refund_reason').value = '';
  }

  function showRefundModal() {
    var modal = qs('refundModal');
    if (!modal) return;
    modal.style.display = 'flex';
  }
  function hideRefundModal() {
    var modal = qs('refundModal');
    if (!modal) return;
    modal.style.display = 'none';
  }

  function doRefundSubmit() {
    var ticketId = qs('refund_ticket_id').value;
    if (!ticketId) { showToast('Неверный ticket id', 'error'); return; }

    var payload = {
      action: 'refund',
      ticket_id: ticketId,
      csrf_token: csrfToken,
      refund_amount: qs('refund_amount').value || '',
      refund_method: qs('refund_method').value || 'cash',
      refund_provider: qs('refund_provider').value || '',
      refund_transaction_id: qs('refund_transaction_id').value || '',
      reason: qs('refund_reason').value || ''
    };

    var body = Object.keys(payload).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]); }).join('&');

    fetch('/ajax/ticket.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: body
    }).then(function(r){ return r.json(); })
    .then(function(json){
      if (!json) { showToast('Неверный ответ сервера', 'error'); return; }
      if (!json.success) { showToast(json.message || 'Ошибка возврата', 'error'); return; }
      showToast('Возврат выполнен', 'success');
      hideRefundModal();
      setTimeout(function(){ loadTickets(); }, 400);
    }).catch(function(err){
      console.error(err);
      showToast('Ошибка сети при возврате', 'error');
    });
  }

  // --- Typeahead helpers with cross-filtering ---
  function showSuggestions(kind, items) {
    var wrapId = (kind === 'session') ? 'suggestions_session' : (kind === 'uid' ? 'suggestions_uid' : 'suggestions_customer');
    var wrap = qs(wrapId);
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!items || !items.length) { wrap.style.display = 'none'; return; }
    items.forEach(function(it){
      var div = document.createElement('div');
      div.className = 'typeahead-item';
      div.style.padding = '8px 10px';
      div.style.cursor = 'pointer';
      div.style.borderBottom = '1px solid #f0f0f0';
      div.style.background = '#fff';
      div.innerHTML = it.labelHtml || it.label || escapeHtml(it.displayValue || it.value || '');
      div.dataset.display = it.displayValue || it.value || '';
      div.dataset.raw = it.rawValue || it.value || '';
      if (it.extra) div.dataset.extra = it.extra;
      div.addEventListener('click', function(){
        if (kind === 'session') {
          var vis = qs('filter_session');
          var rawEl = qs('filter_session_raw');
          if (vis) vis.value = this.dataset.display || '';
          if (rawEl) rawEl.value = this.dataset.raw || '';
        } else if (kind === 'uid') {
          var el = qs('filter_uid');
          if (el) el.value = this.dataset.raw || this.dataset.display || '';
        } else {
          var el = qs('filter_customer');
          if (el) el.value = this.dataset.raw || this.dataset.display || '';
        }
        hideSuggestions(kind);
        page = 1;
        loadTickets();
      });
      wrap.appendChild(div);
    });
    wrap.style.display = 'block';
  }

  function hideSuggestions(kind) {
    var wrapId = (kind === 'session') ? 'suggestions_session' : (kind === 'uid' ? 'suggestions_uid' : 'suggestions_customer');
    var wrap = qs(wrapId);
    if (!wrap) return;
    wrap.style.display = 'none';
    wrap.innerHTML = '';
  }

  function fetchSessionSuggestions(q) {
    if (!q || q.length < 1) { showSuggestions('session', []); return; }
    var other = getFilters();
    var params = 'q=' + encodeURIComponent(q) + '&uid=' + encodeURIComponent(other.uid || '') + '&customer=' + encodeURIComponent(other.customer || '');
    fetch('/ajax/ticket.php?action=suggest_sessions&' + params, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (!json || !json.success) { showSuggestions('session', []); return; }
        var items = (json.data || []).map(function(it){
          var title = it.title || '';
          var start = it.start_time || '';
          var displayDate = '';
          try { if (start) displayDate = (new Date(start)).toLocaleString(); } catch (e) { displayDate = start; }
          var labelHtml = '<strong>' + escapeHtml(title) + '</strong>';
          if (displayDate) labelHtml += ' — ' + escapeHtml(displayDate);
          var rawValue = (it.id !== undefined && it.id !== null && String(it.id).trim() !== '') ? String(it.id) : title;
          return {
            labelHtml: labelHtml,
            displayValue: title + (displayDate ? ' — ' + displayDate : ''),
            rawValue: rawValue,
            extra: it.start_time
          };
        });
        showSuggestions('session', items);
      }).catch(function(){ showSuggestions('session', []); });
  }

  function fetchUidSuggestions(q) {
    if (!q || q.length < 1) { showSuggestions('uid', []); return; }
    var other = getFilters();
    var params = 'q=' + encodeURIComponent(q) + '&session=' + encodeURIComponent(other.session || '') + '&customer=' + encodeURIComponent(other.customer || '');
    fetch('/ajax/ticket.php?action=suggest_q&' + params, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (!json || !json.success) { showSuggestions('uid', []); return; }
        var items = (json.data || []).filter(function(it){ return it.type === 'uid'; }).map(function(it){
          return {
            labelHtml: '<strong>UID:</strong> ' + escapeHtml(it.value),
            displayValue: it.value,
            rawValue: it.value,
            extra: it.type
          };
        });
        showSuggestions('uid', items);
      }).catch(function(){ showSuggestions('uid', []); });
  }

  function fetchCustomerSuggestions(q) {
    if (!q || q.length < 1) { showSuggestions('customer', []); return; }
    var other = getFilters();
    var params = 'q=' + encodeURIComponent(q) + '&session=' + encodeURIComponent(other.session || '') + '&uid=' + encodeURIComponent(other.uid || '');
    fetch('/ajax/ticket.php?action=suggest_q&' + params, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (!json || !json.success) { showSuggestions('customer', []); return; }
        var items = (json.data || []).filter(function(it){ return it.type === 'customer'; }).map(function(it){
          return {
            labelHtml: '<strong>Клиент:</strong> ' + escapeHtml(it.value),
            displayValue: it.value,
            rawValue: it.value,
            extra: it.type
          };
        });
        showSuggestions('customer', items);
      }).catch(function(){ showSuggestions('customer', []); });
  }

  document.addEventListener('DOMContentLoaded', function(){
    var elPrev = qs('prevPage'), elNext = qs('nextPage'), elReset = qs('btnResetFilters'), elExport = qs('btnExport');
    if (elPrev) elPrev.addEventListener('click', prevPage);
    if (elNext) elNext.addEventListener('click', nextPage);
    if (elReset) elReset.addEventListener('click', resetFilters);
    if (elExport) elExport.addEventListener('click', exportExcel);

    var perPageSelect = qs('selectPerPage');
    if (perPageSelect) {
      perPageSelect.value = String(perPage);
      perPageSelect.addEventListener('change', function(){
        var v = parseInt(this.value, 10);
        if (v === 25 || v === 50 || v === 100 || v === 500) {
          perPage = v;
          try { localStorage.setItem('tickets_per_page', String(perPage)); } catch(e){}
          page = 1;
          loadTickets();
        }
      });
    }

    var immediateFields = [
      'filter_date_from','filter_date_to','filter_session','filter_uid','filter_customer',
      'filter_status','filter_payment','filter_channel','filter_segment','filter_refund'
    ];
    immediateFields.forEach(function(id){
      var el = qs(id);
      if (!el) return;
      if (el.tagName === 'SELECT') {
        el.addEventListener('change', function(){ page = 1; loadTickets(); });
      } else {
        el.addEventListener('input', function(){
          if (id === 'filter_session') {
            var rawEl = qs('filter_session_raw');
            if (rawEl) rawEl.value = '';
          }
          scheduleFilterApplyDebounced();
        });
      }
    });

    var sessionInput = qs('filter_session');
    if (sessionInput) {
      sessionInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); } });
      sessionInput.addEventListener('input', function(){
        clearTimeout(sessionSuggestTimer);
        var v = this.value.trim();
        sessionSuggestTimer = setTimeout(function(){ fetchSessionSuggestions(v); }, 220);
      });
      sessionInput.addEventListener('blur', function(){ setTimeout(function(){ hideSuggestions('session'); }, 180); });
    }

    var uidInput = qs('filter_uid');
    if (uidInput) {
      uidInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); } });
      uidInput.addEventListener('input', function(){
        clearTimeout(uidSuggestTimer);
        var v = this.value.trim();
        uidSuggestTimer = setTimeout(function(){ fetchUidSuggestions(v); }, 180);
      });
      uidInput.addEventListener('blur', function(){ setTimeout(function(){ hideSuggestions('uid'); }, 180); });
    }

    var customerInput = qs('filter_customer');
    if (customerInput) {
      customerInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); } });
      customerInput.addEventListener('input', function(){
        clearTimeout(customerSuggestTimer);
        var v = this.value.trim();
        customerSuggestTimer = setTimeout(function(){ fetchCustomerSuggestions(v); }, 220);
      });
      customerInput.addEventListener('blur', function(){ setTimeout(function(){ hideSuggestions('customer'); }, 180); });
    }

    document.addEventListener('click', function(e){
      var s1 = qs('suggestions_session'), s2 = qs('suggestions_uid'), s3 = qs('suggestions_customer');
      if (s1 && !s1.contains(e.target) && e.target !== qs('filter_session')) hideSuggestions('session');
      if (s2 && !s2.contains(e.target) && e.target !== qs('filter_uid')) hideSuggestions('uid');
      if (s3 && !s3.contains(e.target) && e.target !== qs('filter_customer')) hideSuggestions('customer');
    });

    var refundClose = qs('refundModalClose');
    if (refundClose) refundClose.addEventListener('click', hideRefundModal);
    var refundCancel = qs('refundCancelBtn');
    if (refundCancel) refundCancel.addEventListener('click', hideRefundModal);
    var refundConfirm = qs('refundConfirmBtn');
    if (refundConfirm) refundConfirm.addEventListener('click', doRefundSubmit);

    loadTickets();
  });

  function exportExcel(){
    var params = getFilters();
    delete params.page;
    delete params.per_page;
    var q = Object.keys(params).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    window.open('/tickets/export_excel.php?' + q, '_blank');
  }

})();
