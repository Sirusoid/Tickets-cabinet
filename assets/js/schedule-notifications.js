(function () {
  'use strict';

  var state = {
    scheduleId: '',
    type: 'postponed',
    lastPreview: false,
    defaultTemplate: '',
    eventTitle: '',
    hall: '',
    oldStart: ''
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
        + 'Причина переноса: Технические обстоятельства.\n\n'
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
      + '\nПричина отмены: Технические обстоятельства.\n\n'
      + 'Для оформления возврата, пожалуйста, обратитесь в службу поддержки театра. Мы обязательно поможем решить вопрос.\n\n'
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
      var previewHtml = '<strong>Получатели проверены:</strong><br>'
        + 'Оплаченных билетов: ' + escapeHtml(counts.total_tickets || 0) + '<br>'
        + 'Клиентов с email: ' + escapeHtml(counts.total_recipients || 0) + '<br>'
        + 'Билетов без email: ' + escapeHtml(counts.no_email_tickets || 0)
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
        setResult(payload.message || 'Уведомления обработаны.', payload.status !== 'sent');
        if (typeof window.showToast === 'function') {
          window.showToast(payload.message || 'Уведомления обработаны.', payload.status === 'sent' ? 'success' : 'info');
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

    if (typeof window.showConfirmModal === 'function') {
      window.showConfirmModal(
        'Сеанс будет изменён, а письмо отправится всем клиентам с указанным email. Продолжить?',
        sendRequest
      );
    } else if (window.confirm('Сеанс будет изменён, а письмо отправится клиентам. Продолжить?')) {
      sendRequest();
    }
  }

  document.addEventListener('click', function (event) {
    var notifyButton = event.target.closest && event.target.closest('.js-schedule-notify');
    if (notifyButton) {
      event.preventDefault();
      openModal(notifyButton);
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
})();
