(function () {
  'use strict';

  var config = window.ZhassahnaShowtimes || {};
  var apiUrl = config.api || 'https://cabinet.zhassahna.kz/widget/showtimes.php';
  var canonicalLink = document.querySelector('link[rel="canonical"]');
  var eventId = config.eventId || document.body.getAttribute('data-event-id') || '';
  var eventTitle = config.eventTitle || document.body.getAttribute('data-event-title') || '';
  var eventUrl = config.eventUrl || (canonicalLink && canonicalLink.href) || window.location.href;
  var target = config.target ? document.querySelector(config.target) : null;

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char];
    });
  }

  function createBlock() {
    var block = document.createElement('section');
    block.className = 'zhassahna-showtimes';
    block.setAttribute('aria-live', 'polite');
    block.innerHTML =
      '<style>' +
      '.zhassahna-showtimes{width:100%;box-sizing:border-box;margin:24px auto;padding:20px 24px;border:1px solid #dfe8f7;border-radius:18px;background:linear-gradient(135deg,#fff,#f3f7ff);font-family:TildaSans,Arial,sans-serif;color:#222831;}' +
      '.zhassahna-showtimes__title{margin:0 0 14px;font-size:22px;font-weight:700;}' +
      '.zhassahna-showtimes__list{display:flex;flex-wrap:wrap;gap:10px;}' +
      '.zhassahna-showtimes__item{display:flex;align-items:center;justify-content:space-between;gap:16px;min-width:260px;flex:1 1 280px;padding:12px 14px;border:1px solid #d9e3f0;border-radius:12px;background:#fff;}' +
      '.zhassahna-showtimes__date{font-weight:700;font-size:15px;}' +
      '.zhassahna-showtimes__time{margin-top:3px;color:#52647e;font-size:13px;}' +
      '.zhassahna-showtimes__price{margin-top:3px;color:#52647e;font-size:13px;}' +
      '.zhassahna-showtimes__buy{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border:0;border-radius:999px;background:#00adb5;color:#fff;text-decoration:none;font-size:13px;font-weight:700;white-space:nowrap;transition:background .15s,transform .15s;}' +
      '.zhassahna-showtimes__buy:hover{background:#008f96;color:#fff;transform:translateY(-1px);}' +
      '.zhassahna-showtimes__empty{margin:0;color:#52647e;font-size:14px;}' +
      '@media(max-width:600px){.zhassahna-showtimes{padding:16px;}.zhassahna-showtimes__item{min-width:0;flex-basis:100%;}.zhassahna-showtimes__buy{padding:9px 13px;}}' +
      '</style>' +
      '<h2 class="zhassahna-showtimes__title">Билеты на спектакль</h2>' +
      '<div class="zhassahna-showtimes__content">Загрузка расписания…</div>';
    return block;
  }

  function mount(block) {
    if (target) {
      target.appendChild(block);
      return;
    }
    var header = document.querySelector('#t-header, header, .t-header, [data-record-type="396"]');
    if (header && header.parentNode) {
      header.parentNode.insertBefore(block, header.nextSibling);
      return;
    }
    var main = document.querySelector('main, .t-records');
    if (main && main.parentNode) {
      main.parentNode.insertBefore(block, main);
      return;
    }
    document.body.insertBefore(block, document.body.firstChild);
  }

  function render(block, payload) {
    var content = block.querySelector('.zhassahna-showtimes__content');
    if (!payload || !payload.success || !payload.available) {
      content.innerHTML = '<p class="zhassahna-showtimes__empty">Билетов на продажу нет</p>';
      return;
    }
    var title = block.querySelector('.zhassahna-showtimes__title');
    if (payload.event && payload.event.title) {
      title.textContent = 'Билеты на «' + payload.event.title + '»';
    }
    content.innerHTML = '<div class="zhassahna-showtimes__list">' + payload.sessions.map(function (session) {
      return '<div class="zhassahna-showtimes__item">' +
        '<div><div class="zhassahna-showtimes__date">' + escapeHtml(session.date_label) + '</div>' +
        '<div class="zhassahna-showtimes__time">Начало: ' + escapeHtml(session.time_label) + '</div>' +
        '<div class="zhassahna-showtimes__price">' + escapeHtml(session.price.label) + '</div></div>' +
        '<a class="zhassahna-showtimes__buy zhassahna-buy-btn" href="' + escapeHtml(session.widget_url) + '" data-widget-url="' + escapeHtml(session.widget_url) + '">Купить билет</a>' +
        '</div>';
    }).join('') + '</div>';
    bindPurchaseButtons(block);
  }

  function loadModalScript(callback) {
    if (typeof window.openZhassahnaTicketModal === 'function') {
      callback();
      return;
    }
    var script = document.createElement('script');
    script.src = 'https://cabinet.zhassahna.kz/assets/js/widget.js?v=2';
    script.onload = callback;
    script.onerror = callback;
    document.head.appendChild(script);
  }

  function bindPurchaseButtons(block) {
    block.addEventListener('click', function (event) {
      var button = event.target.closest('.zhassahna-buy-btn');
      if (!button) return;
      event.preventDefault();
      var url = button.getAttribute('data-widget-url');
      if (!url) return;
      loadModalScript(function () {
        if (typeof window.openZhassahnaTicketModal === 'function') {
          window.openZhassahnaTicketModal(url);
        } else {
          window.location.href = url;
        }
      });
    });
  }

  function load() {
    var params = [];
    if (eventId) params.push('event_id=' + encodeURIComponent(eventId));
    if (eventTitle) params.push('event_title=' + encodeURIComponent(eventTitle));
    if (eventUrl) params.push('event_url=' + encodeURIComponent(eventUrl));
    fetch(apiUrl + '?' + params.join('&'), { credentials: 'omit' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || payload.event_found !== true) return;
        var block = createBlock();
        mount(block);
        render(block, payload);
      })
      .catch(function () {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
