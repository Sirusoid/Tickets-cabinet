// QR ticket scanner for the mobile/iPhone-friendly admin page.
(function () {
  'use strict';

  var scanner = null;
  var scannerRunning = false;
  var scannerStarting = false;
  var sessionsLoading = true;
  var lastCode = '';
  var lastCodeAt = 0;
  var deviceUid = getDeviceUid();

  var reader = document.getElementById('qr-reader');
  var status = document.getElementById('scanner-status');
  var startButton = document.getElementById('start-camera');
  var stopButton = document.getElementById('stop-camera');
  var manualForm = document.getElementById('manual-scan-form');
  var manualCode = document.getElementById('manual-code');
  var imageInput = document.getElementById('qr-image');
  var result = document.getElementById('scan-result');
  var sessionSelect = document.getElementById('scanner-session');
  var donut = document.getElementById('scanner-donut');
  var totalCount = document.getElementById('scanner-total-count');
  var checkedCount = document.getElementById('scanner-checked-count');
  var checkedLabel = document.getElementById('scanner-checked-label');
  var remainingCount = document.getElementById('scanner-remaining-count');
  var statsUpdated = document.getElementById('scanner-stats-updated');

  function getDeviceUid() {
    var key = 'zhassahna_scanner_device_uid';
    try {
      var stored = localStorage.getItem(key);
      if (stored) return stored;
      var generated = (window.crypto && typeof window.crypto.randomUUID === 'function')
        ? window.crypto.randomUUID()
        : 'scanner-' + Date.now() + '-' + Math.random().toString(16).slice(2);
      localStorage.setItem(key, generated);
      return generated;
    } catch (error) {
      return 'scanner-' + Date.now();
    }
  }

  function setStatus(message, type) {
    status.textContent = message;
    status.className = 'scanner-status scanner-status--' + (type || 'idle');
  }

  function setCameraButtons() {
    startButton.disabled = scannerRunning || scannerStarting || sessionsLoading || !sessionSelect.value;
    stopButton.disabled = !scannerRunning;
    sessionSelect.disabled = sessionsLoading || scannerRunning || scannerStarting || sessionSelect.options.length < 2;
  }

  function formatSessionDate(value) {
    var date = new Date(String(value || '').replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat('ru-RU', {
      timeZone: 'Asia/Almaty',
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date).replace('.', '');
  }

  function renderStats(total, checked) {
    total = Math.max(0, Number(total) || 0);
    checked = Math.min(total, Math.max(0, Number(checked) || 0));
    var remaining = Math.max(0, total - checked);
    var progress = total > 0 ? (checked / total) * 100 : 0;
    donut.style.setProperty('--scan-progress', progress + '%');
    donut.setAttribute('aria-label', 'Отсканировано ' + checked + ' из ' + total + ' билетов');
    totalCount.textContent = total.toLocaleString('ru-RU');
    checkedCount.textContent = checked.toLocaleString('ru-RU');
    checkedLabel.textContent = checked.toLocaleString('ru-RU');
    remainingCount.textContent = remaining.toLocaleString('ru-RU');
    statsUpdated.textContent = new Intl.DateTimeFormat('ru-RU', {
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date());
  }

  function loadSessionStats(scheduleId) {
    if (!scheduleId) return Promise.resolve();
    statsUpdated.textContent = 'Обновление…';
    return fetch('/ajax/ticket.php?action=scanner_stats&schedule_id=' + encodeURIComponent(scheduleId), {
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || 'Не удалось обновить статистику.');
        }
        renderStats(payload.data.total_tickets, payload.data.checked_in_tickets);
      });
    }).catch(function (error) {
      statsUpdated.textContent = 'Нет связи';
      console.error('Ticket scanner stats error:', error);
    });
  }

  function loadSessions() {
    fetch('/ajax/ticket.php?action=scanner_sessions', { credentials: 'same-origin' })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Не удалось загрузить расписание.');
          }
          return payload.data || [];
        });
      })
      .then(function (sessions) {
        sessionSelect.innerHTML = '';
        if (!sessions.length) {
          sessionSelect.add(new Option('Нет действующих сеансов', ''));
          setStatus('В расписании нет действующих сеансов для сканирования.', 'error');
          return;
        }

        sessions.forEach(function (session) {
          var title = String(session.event_title || 'Спектакль');
          var date = formatSessionDate(session.start_time);
          var option = new Option(title + (date ? ' · ' + date : ''), String(session.id));
          option.dataset.total = session.total_tickets;
          option.dataset.checked = session.checked_in_tickets;
          sessionSelect.add(option);
        });
        sessionSelect.selectedIndex = 0;
        renderStats(sessions[0].total_tickets, sessions[0].checked_in_tickets);
        setStatus('Выбран ближайший сеанс. Включите камеру для проверки билетов.', 'idle');
      })
      .catch(function (error) {
        sessionSelect.innerHTML = '';
        sessionSelect.add(new Option('Не удалось загрузить расписание', ''));
        setStatus(error.message || 'Не удалось загрузить расписание для сканера.', 'error');
        console.error('Ticket scanner sessions error:', error);
      })
      .finally(function () {
        sessionsLoading = false;
        setCameraButtons();
      });
  }

  function cameraErrorMessage(error) {
    var messages = {
      NotAllowedError: 'Chrome заблокировал доступ к камере. Проверьте разрешение сайта и системное разрешение камеры.',
      NotFoundError: 'Камера не найдена на устройстве.',
      NotReadableError: 'Камера занята другим приложением. Закройте его и попробуйте снова.',
      OverconstrainedError: 'Не удалось выбрать камеру устройства.',
      SecurityError: 'Браузер запретил доступ к камере. Откройте сайт по HTTPS и проверьте настройки безопасности.'
    };
    var name = error && error.name ? error.name : '';
    var details = error && error.message ? String(error.message) : String(error || '');
    if (details.length > 180) details = details.slice(0, 177) + '...';
    var message = messages[name] || 'Не удалось включить камеру. Проверьте настройки устройства и Chrome.';
    return details ? message + ' (' + (name ? name + ': ' : '') + details + ')' : message;
  }

  function startCamera() {
    if (scannerRunning || scannerStarting) return;
    if (window.isSecureContext !== true || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Камера в Chrome доступна только на защищённой странице HTTPS. Откройте защищённый адрес сайта.', 'error');
      return;
    }
    if (!window.Html5Qrcode) {
      setStatus('Модуль камеры не загрузился. Используйте UID или загрузку изображения.', 'error');
      return;
    }

    if (!scanner) {
      scanner = new window.Html5Qrcode('qr-reader');
    }

    scannerStarting = true;
    setCameraButtons();
    setStatus('Запрашиваем доступ к камере…', 'idle');
    window.Html5Qrcode.getCameras().catch(function (error) {
      console.warn('Ticket scanner camera enumeration failed:', error);
      return [];
    }).then(function (devices) {
      var cameras = (devices || []).filter(function (device) {
        return device && device.id;
      });
      cameras.sort(function (first, second) {
        var firstRear = /back|rear|environment|задн/i.test(first.label || '');
        var secondRear = /back|rear|environment|задн/i.test(second.label || '');
        return Number(secondRear) - Number(firstRear);
      });
      var camera = cameras.length ? cameras[0].id : { facingMode: 'environment' };
      return scanner.start(
        camera,
        { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
        onScanSuccess,
        function () {}
      );
    }).then(function () {
      scannerStarting = false;
      scannerRunning = true;
      setCameraButtons();
      setStatus('Наведите камеру на QR-код билета', 'active');
    }).catch(function (error) {
      scannerStarting = false;
      scannerRunning = false;
      scanner = null;
      setCameraButtons();
      setStatus(cameraErrorMessage(error), 'error');
      console.error('Ticket scanner camera error:', error);
    });
  }

  function stopCamera() {
    if (!scanner || !scannerRunning) return Promise.resolve();
    return scanner.stop().then(function () {
      scannerRunning = false;
      setCameraButtons();
      setStatus('Камера остановлена', 'idle');
    }).catch(function (error) {
      scannerRunning = false;
      setCameraButtons();
      console.error('Ticket scanner stop error:', error);
    });
  }

  function onScanSuccess(decodedText) {
    var code = String(decodedText || '').trim();
    if (!code) return;

    var now = Date.now();
    if (code === lastCode && now - lastCodeAt < 2500) return;
    lastCode = code;
    lastCodeAt = now;

    stopCamera().then(function () {
      checkTicket(code);
    });
  }

  function checkTicket(code) {
    setStatus('Проверяем билет…', 'loading');
    var body = [
      'action=checkin',
      'code=' + encodeURIComponent(code),
      'schedule_id=' + encodeURIComponent(sessionSelect.value),
      'device_uid=' + encodeURIComponent(deviceUid),
      'csrf_token=' + encodeURIComponent(window.APP_CSRF_TOKEN || '')
    ].join('&');

    fetch('/ajax/ticket.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (response) {
      return response.json().then(function (payload) {
        return { httpOk: response.ok, payload: payload };
      });
    }).then(function (response) {
      var payload = response.payload || {};
      renderResult(payload, response.httpOk);
      setStatus(payload.message || (response.httpOk ? 'Билет принят' : 'Проверка не пройдена'), response.httpOk ? 'success' : 'error');
      if (response.httpOk) loadSessionStats(sessionSelect.value);
    }).catch(function (error) {
      setStatus('Ошибка связи с сервером. Повторите проверку.', 'error');
      console.error('Ticket scanner request error:', error);
    });
  }

  function renderResult(payload, httpOk) {
    var data = payload.data || {};
    var accepted = payload.success === true && httpOk;
    var duplicate = payload.duplicate === true;
    var stateClass = accepted ? 'success' : (duplicate ? 'duplicate' : 'invalid');
    var stateTitle = accepted ? 'Билет принят' : (duplicate ? 'Билет уже использован' : 'Билет не принят');

    result.className = 'card scanner-result scanner-result--' + stateClass;
    result.innerHTML = '';

    var title = document.createElement('h2');
    title.textContent = stateTitle;
    result.appendChild(title);

    var message = document.createElement('p');
    message.className = 'scanner-result__message';
    message.textContent = payload.message || '';
    result.appendChild(message);

    var details = [
      ['UID', data.ticket_uid],
      ['Событие', data.event_title],
      ['Сеанс', data.session_start],
      ['Место', data.seat_identifier],
      ['Клиент', data.customer_name]
    ];
    details.forEach(function (item) {
      if (!item[1]) return;
      var row = document.createElement('div');
      row.className = 'scanner-result__row';
      var label = document.createElement('span');
      label.textContent = item[0];
      var value = document.createElement('strong');
      value.textContent = item[1];
      row.appendChild(label);
      row.appendChild(value);
      result.appendChild(row);
    });

    var again = document.createElement('button');
    again.type = 'button';
    again.className = 'btn btn-primary scanner-result__again';
    again.textContent = 'Сканировать следующий';
    again.addEventListener('click', function () {
      result.className = 'card scanner-result scanner-result--empty';
      result.innerHTML = '<div class="scanner-result__placeholder"><strong>Ожидание следующего билета</strong><span>Наведите камеру на QR-код.</span></div>';
      startCamera();
    });
    result.appendChild(again);
  }

  startButton.addEventListener('click', startCamera);
  stopButton.addEventListener('click', stopCamera);
  sessionSelect.addEventListener('change', function () {
    var selected = sessionSelect.options[sessionSelect.selectedIndex];
    if (selected) renderStats(selected.dataset.total, selected.dataset.checked);
    loadSessionStats(sessionSelect.value);
  });
  if (manualForm && manualCode) {
    manualForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var code = manualCode.value.trim();
      if (!code) {
        setStatus('Введите UID билета', 'error');
        manualCode.focus();
        return;
      }
      stopCamera().then(function () {
        checkTicket(code);
      });
    });
  }

  imageInput.addEventListener('change', function () {
    var file = imageInput.files && imageInput.files[0];
    if (!file) return;
    if (!window.Html5Qrcode) {
      setStatus('Модуль QR не загрузился. Введите UID вручную.', 'error');
      return;
    }
    if (!scanner) scanner = new window.Html5Qrcode('qr-reader');
    setStatus('Распознаём изображение…', 'loading');
    scanner.scanFile(file, true).then(onScanSuccess).catch(function () {
      setStatus('QR-код на изображении не найден', 'error');
    }).finally(function () {
      imageInput.value = '';
    });
  });

  setCameraButtons();
  loadSessions();
})();
