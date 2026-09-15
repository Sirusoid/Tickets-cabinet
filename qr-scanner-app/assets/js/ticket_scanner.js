// QR ticket scanner for the mobile/iPhone-friendly admin page.
(function () {
  'use strict';

  var scanner = null;
  var scannerRunning = false;
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
    startButton.disabled = scannerRunning;
    stopButton.disabled = !scannerRunning;
  }

  function startCamera() {
    if (scannerRunning) return;
    if (!window.Html5Qrcode) {
      setStatus('Модуль камеры не загрузился. Используйте UID или загрузку изображения.', 'error');
      return;
    }

    if (!scanner) {
      scanner = new window.Html5Qrcode('qr-reader');
    }

    setStatus('Запрашиваем доступ к камере…', 'idle');
    scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
      onScanSuccess,
      function () {}
    ).then(function () {
      scannerRunning = true;
      setCameraButtons();
      setStatus('Наведите камеру на QR-код билета', 'active');
    }).catch(function (error) {
      scannerRunning = false;
      setCameraButtons();
      setStatus('Не удалось включить камеру. Проверьте разрешение и HTTPS.', 'error');
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
})();
