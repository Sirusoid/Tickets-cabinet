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

  var bccResponseMap = {
    '-1': ['Не заполнено обязательное поле запроса.', 'Проверьте данные операции и повторите запрос.'],
    '-2': ['Запрос не прошёл проверку BCC.', 'Не повторяйте операцию сразу; проверьте параметры запроса.'],
    '-3': ['Хост эквайера вернул ответ в неверном формате.', 'Повторите позже; если ошибка повторяется, обратитесь к администратору.'],
    '-4': ['Нет соединения с хостом эквайера.', 'Проверьте доступ сервера к BCC и повторите позже.'],
    '-5': ['Ошибка соединения с банком во время операции.', 'Проверьте статус операции перед повтором.'],
    '-6': ['Ошибка настройки модуля e-Gateway.', 'Проверьте настройки терминала и обратитесь к администратору.'],
    '-7': ['Ответ банка неполный или некорректный.', 'Не повторяйте операцию вслепую; проверьте журнал BCC.'],
    '-8': ['Ошибка номера карты.', 'Проверьте данные исходной операции; для возврата не вводите карту вручную.'],
    '-9': ['Ошибка срока действия карты.', 'Проверьте исходную операцию и повторите только после исправления данных.'],
    '-10': ['Ошибка суммы операции.', 'Сверьте сумму возврата с исходной банковской операцией.'],
    '-11': ['Ошибка валюты операции.', 'Проверьте, что используется KZT/CURRENCY=398.'],
    '-12': ['Ошибка идентификатора продавца.', 'Проверьте MERCHANT в настройках эквайринга.'],
    '-13': ['IP-адрес источника не разрешён банком.', 'Проверьте разрешённый IP сервера в BCC.'],
    '-14': ['Нет соединения с терминалом.', 'Проверьте доступность терминала и повторите позже.'],
    '-15': ['Ошибка идентификатора RRN.', 'Проверьте RRN исходной операции; не отправляйте повторный возврат без сверки.'],
    '-16': ['На терминале выполняется другая транзакция.', 'Дождитесь её завершения и повторите позже.'],
    '-17': ['Терминалу запрещён доступ к e-Gateway.', 'Проверьте права терминала в BCC.'],
    '-18': ['Ошибка CVC2.', 'Для возврата проверьте исходные банковские идентификаторы.'],
    '-19': ['Ошибка аутентификации операции.', 'Проверьте исходный платёж и дождитесь окончательного статуса.'],
    '-20': ['Превышен допустимый интервал времени запроса.', 'Проверьте UTC/TIMESTAMP и MERCH_GMT, затем создайте новый запрос.'],
    '-21': ['Транзакция уже выполнена.', 'Не отправляйте повторно; обновите билет и проверьте статус возврата.'],
    '-22': ['Операция содержит ошибочную аутентификацию.', 'Проверьте исходный ответ банка и настройки подписи.'],
    '-23': ['Ошибка контекста транзакции.', 'Сверьте ORDER и исходные данные операции.'],
    '-24': ['Контекст транзакции не совпадает.', 'Не повторяйте запрос; проверьте ORDER, RRN и INT_REF.'],
    '-25': ['Операция прервана пользователем.', 'Создайте новый запрос, если возврат действительно нужен.'],
    '-26': ['Неверный BIN карты.', 'Проверьте исходную банковскую операцию.'],
    '-27': ['Ошибка имени продавца.', 'Проверьте MERCH_NAME в настройках BCC.'],
    '-28': ['Ошибка дополнительных данных.', 'Проверьте параметры запроса и повторите после исправления.'],
    '-29': ['Ошибка ссылки аутентификации.', 'Проверьте настройки BACKREF и повторите операцию позже.'],
    '-30': ['Транзакция отклонена как мошенническая.', 'Не повторяйте автоматически; проверьте операцию у банка.'],
    '-31': ['Транзакция ещё выполняется.', 'Подождите и проверьте статус, не отправляйте дубль.'],
    '-32': ['Повторная транзакция отклонена.', 'Проверьте результат первой попытки перед повтором.'],
    '-33': ['Транзакция ожидает аутентификацию клиента.', 'Дождитесь завершения аутентификации.'],
    '-34': ['Транзакция Installment ожидает выбора способа оплаты.', 'Дождитесь завершения выбора пользователем.'],
    '-35': ['Транзакция Installment отклонена по тайм-ауту.', 'Создайте новый запрос при необходимости.'],
    '-36': ['Транзакция Installment отклонена пользователем.', 'Создайте новый запрос при необходимости.'],
    '-37': ['Ошибка даты окончания периодических платежей.', 'Проверьте параметры периодического платежа.'],
    '-38': ['Ошибка сервера UPI.', 'Повторите позже после проверки статуса операции.'],
    '-39': ['Транзакция ожидает подтверждения.', 'Дождитесь callback банка и не отправляйте дубль.'],
    '-40': ['Данные карты ещё обрабатываются.', 'Подождите и проверьте статус операции.'],
    '-41': ['Ошибка PAReq для MPASS Wallet.', 'Повторите аутентификацию по инструкции банка.'],
    '-42': ['Для MPASS Wallet сначала требуется PAReq.', 'Повторите операцию с этапом PAReq.'],
    '-43': ['Выполняется 3D-Secure аутентификация.', 'Дождитесь завершения операции.'],
    '-44': ['3D-Secure аутентификация недоступна.', 'Повторите позже или используйте другой способ оплаты.'],
    '-45': ['Карта не зарегистрирована в 3D-Secure.', 'Используйте другую карту или обратитесь в банк.'],
    '-46': ['Некритическая ошибка банка.', 'Операцию можно повторить после проверки её текущего статуса.'],
    '-47': ['Транзакция UPI обрабатывается.', 'Дождитесь окончательного статуса.'],
    '03': ['Недействительный продавец.', 'Проверьте Merchant ID и настройки терминала.'],
    '05': ['Банк запретил операцию.', 'Не повторяйте автоматически; уточните причину в банке.'],
    '06': ['Банк вернул общую ошибку.', 'Проверьте статус операции и повторите позже.'],
    '07': ['Карту требуется изъять.', 'Не повторяйте операцию этой картой; обратитесь в банк.'],
    '12': ['Недействительная транзакция.', 'Проверьте тип операции и исходные банковские данные.'],
    '51': ['Недостаточно средств.', 'Возврат не подтверждён; уточните статус у банка.'],
    '57': ['Операция запрещена держателю карты.', 'Обратитесь в банк держателя карты.'],
    '58': ['Операция запрещена.', 'Проверьте ограничения терминала и обратитесь в BCC.'],
    '59': ['Подозрение на мошенничество.', 'Не повторяйте автоматически; проверьте операцию у банка.'],
    '61': ['Превышен лимит суммы.', 'Проверьте сумму и лимиты банковской операции.'],
    '62': ['Карта запрещена.', 'Используйте другой способ или обратитесь в банк.'],
    '65': ['Превышен лимит частоты операций.', 'Подождите или обратитесь в банк.'],
    '78': ['Запись операции не найдена.', 'Проверьте ORDER, RRN и INT_REF исходного платежа.'],
    '82': ['Банк клиента недоступен.', 'Повторите операцию позже.'],
    '93': ['Операция отклонена из-за ограничений законодательства.', 'Обратитесь в банк для уточнения причины.'],
    '95': ['Ошибка согласования операции.', 'Не повторяйте возврат; сначала сверьте статус в BCC.'],
    '96': ['Системная неисправность банка.', 'Повторите позже после проверки статуса операции.'],
    'TRANSPORT_ERROR': ['Сервер не получил ответ от BCC.', 'Возврат не подтверждён; проверьте статус и повторите позже.']
  };

  function bccOperationLabel(operation) {
    if (operation === 'payment') return 'Оплата';
    if (operation === 'refund') return 'Возврат';
    return 'Операция BCC';
  }

  function bccBankToast(data, operation) {
    data = data || {};
    operation = operation || 'bcc';
    var bank = data.bank_response && typeof data.bank_response === 'object' ? data.bank_response : data;
    var action = String(bank.ACTION || '').trim();
    var rc = String(bank.RC || data.code || '').trim();
    var rcText = String(bank.RC_TEXT || '').trim();
    var label = bccOperationLabel(operation);
    var isProcessing = data.state === 'processing';
    var isSuccess = data.success === true && !isProcessing && (action === '' || action === '0') && (rc === '' || rc === '00');

    if (isSuccess) {
      var successText = label + ' подтверждён банком';
      if (action || rc) successText += ' (ACTION=' + (action || '0') + ', RC=' + (rc || '00') + ')';
      if (operation === 'refund') successText += '. Билет отменён, место освобождено.';
      return showToast(successText + '.', 'success', { duration: 6000 });
    }

    if (isProcessing) {
      return showToast(label + ' принят банком и ожидает окончательного подтверждения. Не отправляйте повторный запрос.', 'info', { duration: 8000 });
    }

    var info = bccResponseMap[rc] || null;
    var text = label + ': ';
    if (rc !== '') text += 'код ' + rc + '. ';
    text += info ? info[0] : (rcText || data.message || 'Банк не подтвердил операцию.');
    if (rcText && info && rcText !== info[0]) text += ' Ответ банка: ' + rcText + '.';
    if (action !== '') text += ' ACTION=' + action + '.';
    if (info) text += ' Действие: ' + info[1];
    else if (data.message) text += ' Действие: проверьте статус операции и журнал банка перед повтором.';
    return showToast(text, 'error', { duration: 11000 });
  }

  window.showBccToast = bccBankToast;

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
