(function () {
  'use strict';

  var state = {
    scheduleId: '',
    type: 'postponed',
    lastPreview: false,
    defaultTemplate: '',
    eventTitle: '',
    hall: '',
    oldStart: '',
    cancelRefundsConfirmed: false,
    lastPreviewCounts: null
  };

  function escapeHtml(value) {
    return String(value === null || typeof value === 'undefined' ? '' : value)
      .replace(/[&<>"']/g, function (character) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        })[character];
      });
  }

  function formatDateTime(value) {
    var raw = String(value || '').replace('T', ' ');
    var match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ ](\d{2}):(\d{2})/);
    if (!match) return raw;
    var months = [
      'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
      'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
    ];
    var weekdays = [
      'воскресенье', 'понедельник', 'вторник', 'среда',
      'четверг', 'пятница', 'суббота'
    ];
    var year = Number(match[1]);
    var monthIndex = Number(match[2]) - 1;
    var day = Number(match[3]);
    var weekday = new Date(year, monthIndex, day).getDay();
    return day + ' ' + (months[monthIndex] || match[2]) + ' ' + year
      + ', ' + (weekdays[weekday] || '');
  }

  function formatTime(value) {
    var raw = String(value || '').replace('T', ' ');
    var match = raw.match(/^\d{4}-\d{2}-\d{2}[ ](\d{2}):(\d{2})/);
    return match ? match[1] + ':' + match[2] : '';
  }

  function buildDefaultTemplate(type, title, hall, oldStart, newStart) {
    var hallLabel = hall ? hall + ' (Абая 117)' : '';
    var oldDate = formatDateTime(oldStart);
    var oldTime = formatTime(oldStart);
    var newDate = formatDateTime(newStart);
    var newTime = formatTime(newStart);
    if (type === 'postponed') {
      return 'Уважаемый клиент!\n\n'
        + 'Благодарим Вас за выбор театра «Жас сахна». Сообщаем, что сеанс «' + title + '» был перенесён.\n\n'
        + 'Первоначальные дата и время:\n'
        + 'ДАТА: ' + oldDate + '\n'
        + 'ВРЕМЯ: ' + oldTime + '\n\n'
        + 'Новые дата и время:\n'
        + 'ДАТА: ' + newDate + '\n'
        + 'ВРЕМЯ: ' + newTime + '\n'
        + (hallLabel ? '\nЗАЛ: ' + hallLabel + '\n' : '\n')
        + '\nВаши билеты остаются действительными на новый сеанс. Если новая дата или время Вам не подходят, пожалуйста, свяжитесь с нашей службой поддержки.\n\n'
        + 'Номер заказа: {{ORDER_NUMBERS}}\n\n'
        + 'Если у Вас возникнут вопросы, пожалуйста, напишите нам или позвоните:\n'
        + 'info@zhassahna.kz\n+7 727 259 65 98\n+7 776 711 78 78\n\n'
        + 'С уважением,\nТеатр «Жас сахна»';
    }
    return 'Уважаемый клиент!\n\n'
      + 'К сожалению, вынуждены сообщить, что сеанс «' + title + '» отменён. Приносим искренние извинения за доставленные неудобства.\n\n'
      + 'ДАТА: ' + oldDate + '\n'
      + 'ВРЕМЯ: ' + oldTime + '\n'
      + (hallLabel ? 'ЗАЛ: ' + hallLabel + '\n' : '')
      + '\n'
      + 'Если билеты были оплачены онлайн, мы автоматически направим возврат на счёт или карту, с которых была оплата. Срок зачисления зависит от банка. Если билет был куплен в кассе, пожалуйста, обратитесь в театр для возврата.\n\n'
      + 'Номер заказа: {{ORDER_NUMBERS}}\n\n'
      + 'Если у Вас возникнут вопросы, пожалуйста, напишите нам или позвоните:\n'
      + 'info@zhassahna.kz\n+7 727 259 65 98\n+7 776 711 78 78\n\n'
      + 'С уважением,\nТеатр «Жас сахна»';
  }

  function toDateTimeLocal(value) {
    return String(value || '').replace(' ', 'T').slice(0, 16);
  }

  function ensureModal() {
    var existing = document.getElementById('scheduleNotificationModal');
    if (existing) return existing;

    var modal = document.createElement('div');
    modal.id = 'scheduleNotificationModal';
    modal.className = 'schedule-notification-modal';
    modal.hidden = true;
    modal.innerHTML = ''
      + '<div class="schedule-notification-modal__backdrop"></div>'
      + '<section class="schedule-notification-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="scheduleNotificationTitle">'
      + '  <div class="schedule-notification-modal__header">'
      + '    <h2 class="schedule-notification-modal__title" id="scheduleNotificationTitle">Уведомить клиентов</h2>'
      + '    <button type="button" class="schedule-notification-modal__close" data-notification-close aria-label="Закрыть">&times;</button>'
      + '  </div>'
      + '  <div class="schedule-notification-modal__info" id="scheduleNotificationInfo"></div>'
      + '  <form id="scheduleNotificationForm">'
      + '    <div class="schedule-notification-modal__field">'
      + '      <label for="scheduleNotificationType">Действие</label>'
      + '      <select id="scheduleNotificationType" class="form-control">'
      + '        <option value="postponed">Перенести сеанс</option>'
      + '        <option value="cancelled">Отменить сеанс</option>'
      + '      </select>'
      + '    </div>'
      + '    <div id="scheduleNotificationPostponedFields" class="schedule-notification-modal__grid">'
      + '      <div class="schedule-notification-modal__field">'
      + '        <label for="scheduleNotificationNewStart">Новое начало</label>'
      + '        <input id="scheduleNotificationNewStart" class="form-control" type="datetime-local" required>'
      + '      </div>'
      + '      <div class="schedule-notification-modal__field">'
      + '        <label for="scheduleNotificationNewEnd">Новое окончание</label>'
      + '        <input id="scheduleNotificationNewEnd" class="form-control" type="datetime-local" required>'
      + '      </div>'
      + '    </div>'
      + '    <div class="schedule-notification-modal__field">'
      + '      <label for="scheduleNotificationTemplate">Шаблон письма</label>'
      + '      <textarea id="scheduleNotificationTemplate" class="form-control schedule-notification-template" maxlength="12000" required></textarea>'
      + '    </div>'
      + '    <div id="scheduleNotificationResult" class="schedule-notification-modal__result" hidden></div>'
      + '    <div class="schedule-notification-modal__actions">'
      + '      <button type="button" class="btn btn-ghost" data-notification-close>Закрыть</button>'
      + '      <button type="button" class="btn btn-secondary" id="scheduleNotificationPreview">Проверить получателей</button>'
      + '      <button type="submit" class="btn btn-primary" id="scheduleNotificationSend" disabled>Сохранить и отправить</button>'
      + '    </div>'
      + '  </form>'
      + '</section>';
    document.body.appendChild(modal);
    return modal;
  }

  function ensureCancellationConfirmation() {
    var existing = document.getElementById('scheduleCancellationRefundConfirmation');
    if (existing) return existing;

    var confirmation = document.createElement('div');
    confirmation.id = 'scheduleCancellationRefundConfirmation';
    confirmation.className = 'schedule-cancellation-confirmation';
    confirmation.hidden = true;
    confirmation.innerHTML = ''
      + '<div class="schedule-cancellation-confirmation__backdrop"></div>'
      + '<section class="schedule-cancellation-confirmation__dialog" role="dialog" aria-modal="true" aria-labelledby="scheduleCancellationConfirmTitle">'
      + '  <div class="schedule-cancellation-confirmation__icon" aria-hidden="true">!</div>'
      + '  <h2 id="scheduleCancellationConfirmTitle">Подтвердите отмену сеанса</h2>'
      + '  <p>Сеанс будет отменён. Для онлайн-покупателей система отправит банку запрос на автоматический возврат полной суммы заказа на тот же счёт или карту, с которых прошла оплата.</p>'
      + '  <p class="schedule-cancellation-confirmation__note">Зачисление выполняет банк, поэтому срок поступления средств может отличаться. Возвраты, которые банк не подтвердит сразу, будут показаны отдельно и доступны для повторной отправки.</p>'
      + '  <div class="schedule-cancellation-confirmation__estimate" id="scheduleCancellationRefundEstimate"></div>'
      + '  <label class="schedule-cancellation-confirmation__consent">'
      + '    <input type="checkbox" id="scheduleCancellationRefundConsent">'
      + '    <span>Я согласен(на) отменить сеанс и понимаю, что онлайн-возврат будет направлен на исходный счёт или карту.</span>'
      + '  </label>'
      + '  <div class="schedule-cancellation-confirmation__actions">'
      + '    <button type="button" class="btn btn-ghost" id="scheduleCancellationRefundBack">Вернуться</button>'
      + '    <button type="button" class="btn btn-danger" id="scheduleCancellationRefundProceed" disabled>Отменить сеанс и вернуть средства</button>'
      + '  </div>'
      + '</section>';
    document.body.appendChild(confirmation);
    return confirmation;
  }

  function confirmCancellationAndRefund(onConfirm) {
    var confirmation = ensureCancellationConfirmation();
    var consent = document.getElementById('scheduleCancellationRefundConsent');
    var proceed = document.getElementById('scheduleCancellationRefundProceed');
    var estimate = document.getElementById('scheduleCancellationRefundEstimate');
    var counts = state.lastPreviewCounts || {};
    var orderCount = Number(counts.online_refund_orders) || 0;
    var amount = (Number(counts.online_refund_amount_cents) || 0) / 100;
    if (estimate) {
      estimate.innerHTML = orderCount
        ? 'Онлайн-заказов к возврату: <strong>' + escapeHtml(orderCount) + '</strong><br>'
          + 'Сумма возвратов: <strong>' + escapeHtml(amount.toLocaleString('ru-KZ', { maximumFractionDigits: 2 })) + ' ₸</strong>'
        : 'Оплаченных онлайн-заказов для возврата не найдено.';
    }
    if (consent) {
      consent.checked = false;
      consent.onchange = function () {
        proceed.disabled = !consent.checked;
      };
    }
    proceed.disabled = true;
    proceed.onclick = function () {
      if (!consent || !consent.checked) return;
      state.cancelRefundsConfirmed = true;
      confirmation.hidden = true;
      onConfirm();
    };
    document.getElementById('scheduleCancellationRefundBack').onclick = function () {
      state.cancelRefundsConfirmed = false;
      confirmation.hidden = true;
    };
    confirmation.hidden = false;
    consent.focus();
  }

  function getField(id) {
    return document.getElementById(id);
  }

  function setResult(text, isError, html) {
    var result = getField('scheduleNotificationResult');
    if (!result) return;
    result.hidden = false;
    result.classList.toggle('is-error', Boolean(isError));
    result.innerHTML = html ? html : escapeHtml(text || '');
  }

  function clearPreview() {
    state.lastPreview = false;
    state.lastPreviewCounts = null;
    var send = getField('scheduleNotificationSend');
    var result = getField('scheduleNotificationResult');
    if (send) send.disabled = true;
    if (result) {
      result.hidden = true;
      result.innerHTML = '';
    }
  }

  function updateTypeFields() {
    var type = getField('scheduleNotificationType');
    var fields = getField('scheduleNotificationPostponedFields');
    var start = getField('scheduleNotificationNewStart');
    var end = getField('scheduleNotificationNewEnd');
    var isPostponed = type && type.value === 'postponed';
    if (fields) fields.hidden = false;
    if (start) start.required = isPostponed;
    if (end) end.required = isPostponed;
    if (start) start.disabled = !isPostponed;
    if (end) end.disabled = !isPostponed;
    state.type = isPostponed ? 'postponed' : 'cancelled';
    state.cancelRefundsConfirmed = false;
    refreshDefaultTemplate();
    clearPreview();
  }

  function refreshDefaultTemplate() {
    var templateField = getField('scheduleNotificationTemplate');
    if (!templateField) return;
    if (templateField.value !== '' && templateField.value !== state.defaultTemplate) return;
    var newStart = state.type === 'postponed'
      ? (getField('scheduleNotificationNewStart').value || '')
      : '';
    var template = buildDefaultTemplate(
      state.type,
      state.eventTitle,
      state.hall,
      state.oldStart,
      newStart
    );
    templateField.value = template;
    state.defaultTemplate = template;
  }

  function openModal(button) {
    var modal = ensureModal();
    state.scheduleId = button.getAttribute('data-id') || '';
    state.type = button.getAttribute('data-status') === 'cancelled' ? 'cancelled' : 'postponed';
    state.cancelRefundsConfirmed = false;
    state.lastPreviewCounts = null;
    var title = button.getAttribute('data-event-title') || 'Спектакль';
    var hall = button.getAttribute('data-hall') || '';
    var start = button.getAttribute('data-start') || '';
    var end = button.getAttribute('data-end') || '';
    state.eventTitle = title;
    state.hall = hall;
    state.oldStart = start;
    var type = getField('scheduleNotificationType');
    var newStart = getField('scheduleNotificationNewStart');
    var newEnd = getField('scheduleNotificationNewEnd');
    var info = getField('scheduleNotificationInfo');
    if (type) type.value = state.type;
    if (newStart) newStart.value = toDateTimeLocal(start);
    if (newEnd) newEnd.value = toDateTimeLocal(end);
    var template = buildDefaultTemplate(
      state.type,
      title,
      hall,
      start,
      state.type === 'postponed' ? toDateTimeLocal(start) : ''
    );
    var templateField = getField('scheduleNotificationTemplate');
    if (templateField) {
      templateField.value = template;
      state.defaultTemplate = template;
    }
    if (info) {
      info.innerHTML = '<strong>' + escapeHtml(title) + '</strong><br>'
        + 'Текущий сеанс: ' + escapeHtml(formatDateTime(start))
        + (hall ? '<br>Зал: ' + escapeHtml(hall) : '');
    }
    updateTypeFields();
    modal.hidden = false;
    if (templateField) templateField.focus();
  }

  function closeModal() {
    var modal = document.getElementById('scheduleNotificationModal');
    if (modal) modal.hidden = true;
    var cancellationConfirmation = document.getElementById('scheduleCancellationRefundConfirmation');
    if (cancellationConfirmation) cancellationConfirmation.hidden = true;
  }

  function buildRequest(action) {
    var body = new URLSearchParams();
    body.append('action', action);
    body.append('schedule_id', state.scheduleId);
    body.append('notification_type', state.type);
    body.append('new_start_time', getField('scheduleNotificationNewStart').value || '');
    body.append('new_end_time', getField('scheduleNotificationNewEnd').value || '');
    body.append('template_text', getField('scheduleNotificationTemplate').value || '');
    body.append('csrf_token', window.APP_CSRF_TOKEN || '');
    body.append('cancel_refunds_confirmed', state.cancelRefundsConfirmed ? '1' : '0');
    return body;
  }

  function preview() {
    var previewButton = getField('scheduleNotificationPreview');
    setResult('Проверяем получателей и готовим письмо...', false);
    if (previewButton) {
      previewButton.disabled = true;
      previewButton.textContent = 'Проверка...';
    }
    fetch('/ajax/schedule_notifications.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: buildRequest('preview').toString()
    }).then(function (response) {
      return response.text().then(function (text) {
        var payload = null;
        try {
          payload = JSON.parse(text);
        } catch (error) {
          throw new Error('Сервер вернул некорректный ответ (HTTP ' + response.status + ').');
        }
        if (!response.ok) {
          throw new Error(payload.message || ('Сервер вернул HTTP ' + response.status + '.'));
        }
        return payload;
      });
    }).then(function (payload) {
      if (!payload || !payload.success) {
        throw new Error((payload && payload.message) || 'Не удалось подготовить предпросмотр.');
      }
      var counts = payload.counts || {};
      state.lastPreviewCounts = counts;
      var previewHtml = '<strong>Получатели проверены:</strong><br>'
        + 'Оплаченных билетов: ' + escapeHtml(counts.total_tickets || 0) + '<br>'
        + 'Клиентов с email: ' + escapeHtml(counts.total_recipients || 0) + '<br>'
        + 'Билетов без email: ' + escapeHtml(counts.no_email_tickets || 0)
        + (state.type === 'cancelled'
          ? '<br>Онлайн-заказов к возврату: ' + escapeHtml(counts.online_refund_orders || 0)
            + '<br>Сумма автоматических возвратов: ' + escapeHtml(((Number(counts.online_refund_amount_cents) || 0) / 100).toLocaleString('ru-KZ', { maximumFractionDigits: 2 })) + ' ₸'
          : '')
        + '<br><br><strong>Тема:</strong> ' + escapeHtml(payload.subject || '')
        + '<div class="schedule-notification-modal__preview">' + (payload.preview_html || '') + '</div>';
      setResult('', false, previewHtml);
      state.lastPreview = true;
      var send = getField('scheduleNotificationSend');
      if (send) send.disabled = false;
    }).catch(function (error) {
      setResult(error.message || 'Ошибка предпросмотра.', true);
    }).finally(function () {
      if (previewButton) {
        previewButton.disabled = false;
        previewButton.textContent = 'Проверить получателей';
      }
    });
  }

  function sendNotification(event) {
    event.preventDefault();
    if (!state.lastPreview) {
      preview();
      return;
    }

    var sendRequest = function () {
      var sendButton = getField('scheduleNotificationSend');
      if (sendButton) {
        sendButton.disabled = true;
        sendButton.textContent = 'Отправка...';
      }
      fetch('/ajax/schedule_notifications.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: buildRequest('notify').toString()
      }).then(function (response) {
        return response.text().then(function (text) {
          var payload = null;
          try {
            payload = JSON.parse(text);
          } catch (error) {
            throw new Error('Сервер вернул некорректный ответ (HTTP ' + response.status + ').');
          }
          if (!response.ok) {
            throw new Error(payload.message || ('Сервер вернул HTTP ' + response.status + '.'));
          }
          return payload;
        });
      }).then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.message) || 'Не удалось отправить уведомления.');
        }
        var refunds = payload.refunds;
        var hasRefundFailures = refunds && Number(refunds.failed) > 0;
        var hasRefundsProcessing = refunds && Number(refunds.processing) > 0;
        var resultHtml = '<strong>' + escapeHtml(payload.message || 'Уведомления обработаны.') + '</strong>';
        if (refunds && refunds.details && refunds.details.length) {
          resultHtml += '<ul class="schedule-cancellation-refund-results">';
          refunds.details.forEach(function (refund) {
            var stateLabel = refund.state === 'refunded'
              ? 'Возвращено'
              : (refund.state === 'processing' ? 'Ожидает подтверждения банка' : 'Не завершено');
            resultHtml += '<li><strong>' + escapeHtml(refund.order || 'Заказ') + '</strong>: '
              + escapeHtml(stateLabel) + ' — '
              + escapeHtml(((Number(refund.amount_cents) || 0) / 100).toLocaleString('ru-KZ', { maximumFractionDigits: 2 })) + ' ₸. '
              + escapeHtml(refund.message || '') + '</li>';
          });
          resultHtml += '</ul>';
        }
        if (hasRefundFailures) {
          resultHtml += '<button type="button" class="btn btn-secondary js-retry-schedule-refunds" data-id="'
            + escapeHtml(state.scheduleId) + '">Повторить неудачные возвраты</button>';
        }
        setResult('', payload.status !== 'sent' || hasRefundFailures, resultHtml);
        if (typeof window.showToast === 'function') {
          window.showToast(
            payload.message || 'Уведомления обработаны.',
            payload.status === 'sent' && !hasRefundFailures && !hasRefundsProcessing ? 'success' : 'info'
          );
        }
        if (typeof window.refreshScheduleTable === 'function') {
          window.refreshScheduleTable();
        }
        state.lastPreview = false;
      }).catch(function (error) {
        setResult(error.message || 'Ошибка отправки.', true);
      }).finally(function () {
        if (sendButton) {
          sendButton.disabled = false;
          sendButton.textContent = 'Сохранить и отправить';
        }
      });
    };

    if (state.type === 'cancelled') {
      confirmCancellationAndRefund(sendRequest);
    } else if (typeof window.showConfirmModal === 'function') {
      window.showConfirmModal(
        'Сеанс будет перенесён, а письмо отправится всем клиентам с указанным email. Продолжить?',
        sendRequest
      );
    } else if (window.confirm('Сеанс будет перенесён, а письмо отправится клиентам. Продолжить?')) {
      sendRequest();
    }
  }

  function retryRefunds(button) {
    button.disabled = true;
    button.textContent = 'Повторная отправка…';
    var body = new URLSearchParams();
    body.append('action', 'retry_refunds');
    body.append('schedule_id', button.getAttribute('data-id') || '');
    body.append('csrf_token', window.APP_CSRF_TOKEN || '');
    fetch('/ajax/schedule_notifications.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Не удалось повторить возврат.');
        return payload;
      });
    }).then(function (payload) {
      var refunds = payload.refunds || {};
      var resultHtml = '<strong>' + escapeHtml(payload.message || 'Повторная обработка завершена.') + '</strong>';
      if (refunds.details && refunds.details.length) {
        resultHtml += '<ul class="schedule-cancellation-refund-results">';
        refunds.details.forEach(function (refund) {
          var stateLabel = refund.state === 'refunded'
            ? 'Возвращено'
            : (refund.state === 'processing' ? 'Ожидает подтверждения банка' : 'Не завершено');
          resultHtml += '<li><strong>' + escapeHtml(refund.order || 'Заказ') + '</strong>: '
            + escapeHtml(stateLabel) + ' — '
            + escapeHtml(((Number(refund.amount_cents) || 0) / 100).toLocaleString('ru-KZ', { maximumFractionDigits: 2 })) + ' ₸. '
            + escapeHtml(refund.message || '') + '</li>';
        });
        resultHtml += '</ul>';
      }
      if (Number(refunds.failed) > 0) {
        resultHtml += '<button type="button" class="btn btn-secondary js-retry-schedule-refunds" data-id="'
          + escapeHtml(button.getAttribute('data-id') || '') + '">Повторить неудачные возвраты</button>';
      }
      setResult('', Number(refunds.failed) > 0, resultHtml);
    }).catch(function (error) {
      setResult(error.message || 'Не удалось повторить возврат.', true);
    });
  }

  function retryCancelledScheduleRefunds(button) {
    var scheduleId = button.getAttribute('data-id') || '';
    var status = button.parentNode.querySelector('.schedule-refund-retry-status');
    button.disabled = true;
    button.textContent = 'Повторная отправка...';
    if (status) status.textContent = 'Проверяем онлайн-заказы...';

    var body = new URLSearchParams();
    body.append('action', 'retry_refunds');
    body.append('schedule_id', scheduleId);
    body.append('csrf_token', window.APP_CSRF_TOKEN || '');
    fetch('/ajax/schedule_notifications.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Не удалось повторить возвраты.');
        return payload;
      });
    }).then(function (payload) {
      var refunds = payload.refunds || {};
      var message = 'Заказов проверено: ' + (Number(refunds.orders) || 0)
        + ', возвращено: ' + (Number(refunds.refunded) || 0)
        + ', ожидают ответа банка: ' + (Number(refunds.processing) || 0)
        + ', требуют внимания: ' + (Number(refunds.failed) || 0) + '.';
      var failedOrders = (refunds.details || []).filter(function (item) {
        return item.state === 'failed';
      });
      if (failedOrders.length) {
        message += ' ' + failedOrders.map(function (item) {
          return (item.order || 'Заказ') + ': ' + (item.message || 'не завершён');
        }).join(' ');
      }
      if (status) status.textContent = message;
      if (typeof window.showToast === 'function') {
        window.showToast(message, Number(refunds.failed) > 0 ? 'error' : (Number(refunds.processing) > 0 ? 'info' : 'success'));
      }
    }).catch(function (error) {
      if (status) status.textContent = error.message || 'Не удалось повторить возвраты.';
      if (typeof window.showToast === 'function') {
        window.showToast(error.message || 'Не удалось повторить возвраты.', 'error');
      }
    }).finally(function () {
      button.disabled = false;
      button.textContent = 'Повторить возвраты';
    });
  }

  document.addEventListener('click', function (event) {
    var notifyButton = event.target.closest && event.target.closest('.js-schedule-notify');
    if (notifyButton) {
      event.preventDefault();
      openModal(notifyButton);
      return;
    }
    var retryButton = event.target.closest && event.target.closest('.js-retry-schedule-refunds');
    if (retryButton) {
      event.preventDefault();
      retryRefunds(retryButton);
      return;
    }
    var scheduleRetryButton = event.target.closest && event.target.closest('.js-schedule-refund-retry');
    if (scheduleRetryButton) {
      event.preventDefault();
      retryCancelledScheduleRefunds(scheduleRetryButton);
      return;
    }
    var previewButton = event.target.closest && event.target.closest('#scheduleNotificationPreview');
    if (previewButton) {
      event.preventDefault();
      preview();
      return;
    }
    if (event.target.closest && event.target.closest('[data-notification-close]')) {
      closeModal();
      return;
    }
  });

  document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'scheduleNotificationType') {
      updateTypeFields();
    } else if (event.target && (
      event.target.id === 'scheduleNotificationNewStart'
      || event.target.id === 'scheduleNotificationNewEnd'
    )) {
      refreshDefaultTemplate();
      clearPreview();
    }
  });

  document.addEventListener('input', function (event) {
    if (event.target && event.target.id === 'scheduleNotificationTemplate') {
      clearPreview();
    }
  });

  document.addEventListener('submit', function (event) {
    if (event.target && event.target.id === 'scheduleNotificationForm') {
      sendNotification(event);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var cancellationConfirmation = document.getElementById('scheduleCancellationRefundConfirmation');
    if (cancellationConfirmation && !cancellationConfirmation.hidden) {
      cancellationConfirmation.hidden = true;
      state.cancelRefundsConfirmed = false;
      return;
    }
    closeModal();
  });
})();
