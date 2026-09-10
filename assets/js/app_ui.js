/* assets/js/app_ui.js
   Универсальная реализация showToast и небольшие UI-утилиты.
   Работает с jQuery если он доступен, иначе использует чистый DOM.
*/

(function(){
  'use strict';

  // Низкоуровневые DOM-утилиты (используются, если jQuery отсутствует)
  function createEl(tag, attrs, text) {
    var el = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (!attrs.hasOwnProperty(k)) continue;
        if (k === 'class') el.className = attrs[k];
        else if (k === 'style') el.style.cssText = attrs[k];
        else el.setAttribute(k, attrs[k]);
      }
    }
    if (typeof text !== 'undefined' && text !== null) el.textContent = text;
    return el;
  }
  function on(el, ev, fn) {
    if (!el) return;
    el.addEventListener(ev, fn, false);
  }
  function append(parent, child) {
    parent.appendChild(child);
  }
  function removeEl(el) {
    if (!el) return;
    if (el.parentNode) el.parentNode.removeChild(el);
  }

  // Создаёт контейнер для тостов, если его нет
  function ensureToastWrap() {
    var wrap = document.getElementById('toast-wrap');
    if (wrap) return wrap;
    wrap = createEl('div', { id: 'toast-wrap', 'aria-live': 'polite', 'aria-atomic': 'true' });
    // Небольшие inline-стили на случай, если CSS ещё не подключён
    wrap.style.position = 'fixed';
    wrap.style.right = '16px';
    wrap.style.bottom = '20px';
    wrap.style.zIndex = '1200';
    wrap.style.display = 'flex';
    wrap.style.flexDirection = 'column';
    wrap.style.gap = '8px';
    wrap.style.pointerEvents = 'none';
    wrap.style.maxWidth = '360px';
    document.body.appendChild(wrap);
    return wrap;
  }

  // Функция создания тоста (DOM-реализация)
  function createToastDOM(text, type, options) {
    type = type || 'info';
    options = options || {};
    var duration = typeof options.duration === 'number' ? options.duration : 3000;

    var wrap = ensureToastWrap();
    var id = 'toast-' + Date.now() + '-' + Math.floor(Math.random()*1000);

    var toast = createEl('div', { id: id, class: 'toast toast--' + type, role: 'status' });
    // базовые inline-стили, если CSS отсутствует
    toast.style.pointerEvents = 'auto';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '10px';
    toast.style.padding = '10px 12px';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 6px 18px rgba(0,0,0,0.12)';
    toast.style.fontSize = '14px';
    toast.style.color = '#fff';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px)';
    toast.style.transition = 'transform .22s ease, opacity .22s ease';
    toast.style.border = '1px solid rgba(0,0,0,0.06)';

    // background by type (fallback)
    if (type === 'success') toast.style.background = '#28a745';
    else if (type === 'error') toast.style.background = '#d9534f';
    else toast.style.background = '#2f86eb';

    var txt = createEl('div', null, text);
    var close = createEl('button', { type: 'button', class: 'toast__close', 'aria-label': 'Закрыть' }, '✕');
    close.style.marginLeft = 'auto';
    close.style.background = 'transparent';
    close.style.border = 'none';
    close.style.color = 'rgba(255,255,255,0.95)';
    close.style.fontWeight = '600';
    close.style.cursor = 'pointer';
    close.style.padding = '4px';
    close.style.borderRadius = '4px';

    append(toast, txt);
    append(toast, close);
    append(wrap, toast);

    // force reflow then show
    window.getComputedStyle(toast).opacity;
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';

    var timer = setTimeout(hide, duration);

    function hide() {
      clearTimeout(timer);
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      setTimeout(function(){ removeEl(toast); }, 260);
    }

    on(close, 'click', hide);
    on(toast, 'click', function(e){
      // если клик по самому тосту (не по кнопке) — закрыть
      if (e.target === toast) hide();
    });

    return {
      id: id,
      close: hide
    };
  }

  // Универсальная обёртка: если jQuery доступен — используем его для создания тоста,
  // иначе — DOM-реализацию. Это позволяет безопасно подключать app_ui.js до/после jQuery.
  function showToast(text, type, options) {
    // prefer jQuery implementation if available and $ is ready
    if (typeof window.jQuery !== 'undefined' && window.jQuery) {
      try {
        var $ = window.jQuery;
        var $wrap = $('#toast-wrap');
        if (!$wrap.length) {
          $wrap = $('<div id="toast-wrap" aria-live="polite" aria-atomic="true"></div>').appendTo('body');
        }
        type = type || 'info';
        options = options || {};
        var duration = typeof options.duration === 'number' ? options.duration : 3000;
        var id = 'toast-' + Date.now() + '-' + Math.floor(Math.random()*1000);
        var $t = $('<div/>', { 'class': 'toast toast--' + type, 'id': id, 'role': 'status' });
        var $text = $('<div/>').text(text);
        var $close = $('<button type="button" class="toast__close" aria-label="Закрыть">✕</button>');
        $close.on('click', function(){ hideToast($t); });
        $t.append($text).append($close);
        $wrap.append($t);
        // force reflow then show
        window.getComputedStyle($t[0]).opacity;
        $t.addClass('toast--show');
        var timer = setTimeout(function(){ hideToast($t); }, duration);
        function hideToast($el) {
          clearTimeout(timer);
          $el.removeClass('toast--show');
          setTimeout(function(){ $el.remove(); }, 260);
        }
        return {
          id: id,
          close: function(){ hideToast($t); }
        };
      } catch (err) {
        // если что-то пошло не так с jQuery-веткой — fallback на DOM
        return createToastDOM(text, type, options);
      }
    } else {
      return createToastDOM(text, type, options);
    }
  }

  // Экспорт в глобальную область
  window.showToast = showToast;
  window.appAlert = function(msg){ showToast(msg, 'info'); };

  // Небольшая утилита: безопасный делегированный обработчик для кликов по строкам таблиц
  // (используется в footer.js / includes/footer.php)
  window.delegateClickToRow = function(selector, callback) {
    document.addEventListener('click', function(e){
      var el = e.target;
      while (el && el !== document) {
        if (el.matches && el.matches(selector)) {
          callback.call(el, e);
          return;
        }
        el = el.parentNode;
      }
    }, false);
  };
document.addEventListener('click', function(e){
  var tr = e.target.closest && e.target.closest('.event-row');
  if (!tr) return;

  // если клик по интерактивному элементу внутри строки — пропускаем
  if (e.target.closest('a, button, input, select, .no-row-nav')) return;

  var id = tr.getAttribute('data-id');
  if (!id) return;

  // Навигация на страницу редактирования
  window.location.href = '/events/edit.php?id=' + encodeURIComponent(id);
});
})();
