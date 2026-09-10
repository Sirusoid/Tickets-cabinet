(function () {
    'use strict';

    if (!window.bccPaymentConfig) {
        window.bccPaymentConfig = {
            csrfToken: '',
            ajaxUrl: '/ajax/payment.php',
            seatsAjaxUrl: '/ajax/cash.php',
            currencySymbol: '₸'
        };
    }

    const cfg = window.bccPaymentConfig;
    let currentSession = null;
    let selectedSeats = [];
    let renderer = null;
    let legend = null;

    const modal = document.getElementById('bccPaymentModal');
    const canvas = document.getElementById('bccSeatmapCanvas');
    const cartList = document.getElementById('bccCartList');
    const customerCartList = document.getElementById('bccCustomerCartList');
    const totalEl = document.getElementById('bccTotal');
    const payBtn = document.getElementById('bccPayBtn');
    const buyBtn = document.getElementById('bccBuyBtn');
    const reserveBtn = document.getElementById('bccReserveBtn');
    const reservationPanel = document.getElementById('bccClientReservationPanel');
    const reservationHint = document.getElementById('bccReservationHint');
    const purchaseLimitNote = document.getElementById('bccPurchaseLimitNote');
    const errorEl = document.getElementById('bccError');
    const stepSeats = document.getElementById('bccStepSeats');
    const stepCustomer = document.getElementById('bccStepCustomer');
    const totalBar = document.getElementById('bccTotalBar');
    const customerPhoneInput = document.getElementById('bccCustomerPhone');

    let currentZoom = 1;
    const MIN_ZOOM = 0.5;
    const MAX_ZOOM = 3;
    let clientHoldToken = '';
    let clientReservationEnabled = false;
    let clientReservationMinutes = 15;
    let maxTicketsPerUser = 6;
    let clientReserved = false;

    function phoneDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function formatCustomerPhone(value) {
        let digits = phoneDigits(value);
        if (digits.charAt(0) === '8') digits = '7' + digits.slice(1);
        if (digits.length === 10 && digits.charAt(0) !== '7') digits = '7' + digits;
        if (digits.charAt(0) !== '7') return digits ? '+' + digits : '';
        digits = digits.slice(0, 11);
        const rest = digits.slice(1);
        let formatted = '+7';
        if (rest.length > 0) formatted += ' ' + rest.slice(0, 3);
        if (rest.length > 3) formatted += ' ' + rest.slice(3, 6);
        if (rest.length > 6) formatted += ' ' + rest.slice(6, 8);
        if (rest.length > 8) formatted += ' ' + rest.slice(8, 10);
        return formatted;
    }

    function canonicalCustomerPhone(value) {
        const digits = phoneDigits(formatCustomerPhone(value));
        return digits ? '+' + digits : '';
    }

    if (customerPhoneInput) {
        customerPhoneInput.addEventListener('input', function () {
            const formatted = formatCustomerPhone(this.value);
            if (this.value !== formatted) {
                this.value = formatted;
                try { this.setSelectionRange(formatted.length, formatted.length); } catch (e) {}
            }
        });
    }

    function createClientHoldToken() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        return 'widget-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
    }

    function hideError() {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
    }

    function formatMoney(amount) {
        return Number(amount).toLocaleString('ru-KZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function formatSessionDate(datetime) {
        if (!datetime) return '';
        var d;
        try {
            d = new Date(datetime);
            if (isNaN(d.getTime())) d = new Date(datetime.replace(' ', 'T'));
        } catch (e) {
            return '';
        }
        if (isNaN(d.getTime())) return '';
        var weekdays = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        var months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        var wd = weekdays[d.getDay()];
        var day = d.getDate();
        var month = months[d.getMonth()];
        var year = d.getFullYear();
        var hours = String(d.getHours()).padStart(2, '0');
        var minutes = String(d.getMinutes()).padStart(2, '0');
        return wd + ', ' + day + ' ' + month + ' ' + year + ', ' + hours + ':' + minutes;
    }

    function updateHeaderDate(session) {
        var subtitle = document.querySelector('.bcc-modal-subtitle[data-session-datetime]');
        if (subtitle) {
            var dt = subtitle.getAttribute('data-session-datetime');
            if (dt) subtitle.textContent = formatSessionDate(dt);
        } else if (session && session.start_time) {
            var title = document.querySelector('.bcc-modal-title');
            if (title) {
                var existing = title.querySelector('.bcc-modal-subtitle');
                if (!existing) {
                    existing = document.createElement('span');
                    existing.className = 'bcc-modal-subtitle';
                    title.appendChild(existing);
                }
                existing.textContent = formatSessionDate(session.start_time);
            }
        }
    }

    function openModal(sessionId) {
        currentSession = null;
        selectedSeats = [];
        currentZoom = 1;
        clientHoldToken = createClientHoldToken();
        clientReserved = false;
        hideError();
        if (payBtn) payBtn.disabled = true;
        if (payBtn) payBtn.textContent = 'Перейти к оплате';
        if (cartList) cartList.innerHTML = '';
        if (customerCartList) customerCartList.innerHTML = '';
        if (reserveBtn) reserveBtn.textContent = 'Зарезервировать места';
        updateTotal();
        showStep('seats');
        modal.style.display = 'block';
        loadSession(sessionId);
    }

    function closeModal() {
        modal.style.display = 'none';
        currentSession = null;
        selectedSeats = [];
        currentZoom = 1;
        hideError();
        if (typeof cfg.onClose === 'function') {
            cfg.onClose();
        }
    }

    function showStep(step) {
        if (step === 'customer') {
            if (stepSeats) {
                stepSeats.classList.remove('active');
                stepSeats.style.display = 'none';
            }
            if (stepCustomer) {
                stepCustomer.classList.add('active');
                stepCustomer.style.display = 'flex';
            }
            if (totalBar) totalBar.style.display = 'none';
            renderCustomerCart();
        } else {
            if (stepSeats) {
                stepSeats.classList.add('active');
                stepSeats.style.display = 'flex';
            }
            if (stepCustomer) {
                stepCustomer.classList.remove('active');
                stepCustomer.style.display = 'none';
            }
            if (totalBar) totalBar.style.display = 'flex';
        }
    }

    function updateTotal() {
        const total = selectedSeats.reduce(function (sum, s) { return sum + s.price; }, 0);
        const count = selectedSeats.length;
        if (totalEl) totalEl.textContent = formatMoney(total) + ' ' + cfg.currencySymbol;
        if (buyBtn) buyBtn.disabled = count === 0;
        if (payBtn) payBtn.disabled = count === 0;
        if (reserveBtn) {
            reserveBtn.disabled = count === 0 || clientReserved || !clientReservationEnabled;
            if (clientReserved) reserveBtn.textContent = 'Места зарезервированы';
        }
            if (totalBar) totalBar.style.display = 'flex';
    }

    function updateReservationUi() {
        if (reservationPanel) reservationPanel.style.display = clientReservationEnabled ? 'block' : 'none';
        if (purchaseLimitNote) {
            purchaseLimitNote.textContent = 'Можно купить не более ' + maxTicketsPerUser + ' билетов на один сеанс.';
            purchaseLimitNote.style.display = 'block';
        }
        if (reservationHint) {
            reservationHint.textContent = clientReservationEnabled
                ? 'Резерв действует ' + clientReservationMinutes + ' минут.'
                : '';
        }
        updateTotal();
    }

    function reserveSelectedSeats() {
        if (!clientReservationEnabled || clientReserved || selectedSeats.length === 0) return;
        hideError();
        reserveBtn.disabled = true;
        reserveBtn.textContent = 'Резервирование...';
        fetch(cfg.seatsAjaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'client_hold',
                session_id: currentSession.id || currentSession.session_id,
                token: clientHoldToken,
                seats: selectedSeats.map(function (seat) { return { identifier: seat.identifier }; })
            })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Не удалось зарезервировать места');
                clientReserved = true;
                if (reservationHint) reservationHint.textContent = 'Места зарезервированы до ' + formatSessionDate(data.expires_at) + '.';
                updateTotal();
            })
            .catch(function (error) {
                reserveBtn.disabled = false;
                reserveBtn.textContent = 'Зарезервировать места';
                showError(error.message);
            });
    }

    function setZoom(delta) {
        if (!renderer || !renderer.zoomTo) return;
        currentZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, currentZoom + delta));
        const internal = renderer.getInternal();
        const baseScale = (internal && internal._fit && internal._fit._baseScale) || (internal && internal._fit && internal._fit.scale) || 1;
        const newScale = baseScale * currentZoom;
        renderer.zoomTo(newScale);
    }

    function loadSession(sessionId) {
        fetch(cfg.seatsAjaxUrl + '?action=session&session_id=' + encodeURIComponent(sessionId))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showError(data.message || 'Не удалось загрузить сеанс');
                    return;
                }
                const payload = data.data || data;
                currentSession = payload.session || payload;
                currentSession.id = currentSession.id || currentSession.session_id || sessionId;
                currentSession.sold_seats = payload.sold_seats || currentSession.sold_seats || [];
                currentSession.held_seats = payload.held_seats || currentSession.held_seats || [];
                currentSession.price_ranges = payload.price_ranges || currentSession.price_ranges || [];
                currentSession.start_time = currentSession.start_time || payload.start_time || '';
                clientReservationEnabled = payload.client_reservation_enabled === true;
                clientReservationMinutes = Number(payload.client_reservation_minutes || 15);
                maxTicketsPerUser = Number(payload.max_tickets_per_user || 6);
                if (payload.hall_seatmap && !currentSession.hall_seatmap) {
                    currentSession.hall_seatmap = payload.hall_seatmap;
                }
                updateHeaderDate(currentSession);
                updateReservationUi();
                initSeatmap(currentSession);
            })
            .catch(err => {
                console.error(err);
                showError('Ошибка загрузки сеанса');
            });
    }

    function initSeatmap(session) {
        if (!canvas || !window.SeatingCanvasRender) return;

        // Парсим вспомогательные поля
        function tryParse(v) {
            if (!v) return null;
            if (typeof v !== 'string') return v;
            try { return JSON.parse(v); } catch (e) { return null; }
        }

        // Приоритет: session.seat_map (содержит геометрию + цены из редактора),
        // fallback: hall_seatmap / hall.seatmap / seatmap
        let seatmap = tryParse(session.seat_map);
        let fallbackSeatmap = null;
        if (session.hall && session.hall.seatmap) fallbackSeatmap = session.hall.seatmap;
        else if (session.seatmap) fallbackSeatmap = session.seatmap;
        else if (session.hall_seatmap) fallbackSeatmap = session.hall_seatmap;
        fallbackSeatmap = tryParse(fallbackSeatmap);

        // Если session.seat_map не содержит геометрии (rows), используем fallback
        if (!seatmap || !Array.isArray(seatmap.rows) || seatmap.rows.length === 0) {
            seatmap = fallbackSeatmap;
        }

        // Если session.seat_map содержит только цены (seats), мержим их в схему
        if (fallbackSeatmap && fallbackSeatmap !== seatmap) {
            const priceMap = tryParse(session.seat_map);
            if (priceMap && priceMap.seats && Object.keys(priceMap.seats).length) {
                seatmap.seats = Object.assign({}, seatmap.seats || {}, priceMap.seats);
            }
        }

        if (!seatmap) {
            showError('Схема зала не загружена');
            return;
        }

        // Reuse existing canvas element; do NOT remove it from DOM or create new one.
        if (renderer && typeof renderer.setLayout === 'function') {
            renderer.setLayout(seatmap);
        } else {
            renderer = window.SeatingCanvasRender.init('bccSeatmapCanvas', {
                seatmap: seatmap,
                interactive: true,
                hideLabelsWhenNoPrice: true,
                plainUnavailableSeats: true,
                soldColor: '#bdbdbd',
                reservedColor: '#ffd966'
            });
        }
        if (!renderer) {
            showError('Не удалось инициализировать схему зала');
            return;
        }
        if (typeof renderer.setPlainUnavailableSeats === 'function') {
            renderer.setPlainUnavailableSeats(true);
        } else if (renderer._internal) {
            renderer._internal.plainUnavailableSeats = true;
        }

        // Применяем ценовые зоны
        const priceRanges = session.price_ranges || (session.hall && session.hall.price_ranges) || [];
        let pricedSeats = {};
        let pricedColors = {};
        if (seatmap && seatmap.seats && typeof seatmap.seats === 'object') {
            Object.keys(seatmap.seats).forEach(function (k) {
                const s = seatmap.seats[k];
                if (s && s.meta && s.meta.price !== undefined && s.meta.price !== null && String(s.meta.price).trim() !== '') {
                    const p = Number(s.meta.price);
                    if (p > 0) {
                        pricedSeats[k] = p;
                        if (s.meta.color) pricedColors[p] = s.meta.color;
                    }
                }
            });
        }
        if (Array.isArray(priceRanges)) {
            priceRanges.forEach(function (g) {
                if (!g) return;
                const price = Number(g.price !== undefined && g.price !== null && g.price !== '' ? g.price : (g.value ?? g.p ?? null));
                if (!isFinite(price) || price <= 0) return;
                if (g.color) pricedColors[price] = g.color;
                if (Array.isArray(g.seat_ids)) {
                    g.seat_ids.forEach(function (sid) {
                        pricedSeats[sid] = price;
                        pricedSeats[sid.replace(/:/g, '-')] = price;
                    });
                } else if (g.row_start !== undefined || g.row_end !== undefined) {
                    const rowStart = Number(g.row_start);
                    const rowEnd = Number(g.row_end);
                    if (isFinite(rowStart) && isFinite(rowEnd)) {
                        Object.keys(seatmap.seats || {}).forEach(function (k) {
                            const parts = k.split(/[-:]/);
                            const r = Number(parts[0]);
                            if (r >= Math.min(rowStart, rowEnd) && r <= Math.max(rowStart, rowEnd)) {
                                pricedSeats[k] = price;
                            }
                        });
                    }
                }
            });
        }
        renderer.applyPriceMap(pricedSeats, pricedColors);

        // Помечаем проданные/зарезервированные
        const soldKeys = [];
        const heldKeys = [];
        if (Array.isArray(session.sold_seats)) {
            session.sold_seats.forEach(function (k) {
                soldKeys.push(k);
                if (k.indexOf(':') !== -1) soldKeys.push(k.replace(/:/g, '-'));
            });
        }
        if (Array.isArray(session.held_seats)) {
            session.held_seats.forEach(function (h) {
                var hk = typeof h === 'object' ? (h.seat_key || h.key) : h;
                if (hk) {
                    heldKeys.push(hk);
                    if (String(hk).indexOf(':') !== -1) heldKeys.push(String(hk).replace(/:/g, '-'));
                }
            });
        }
        renderer.setSoldSeats(soldKeys);
        renderer.setReservedSeats(heldKeys);

        // Avoid duplicating click listeners on repeated opens
        if (!renderer._bccClickBound) {
            renderer.on('seat:click', function (key, hit) {
                if (!hit || !hit.seat) return;
                toggleSeat(hit.seat, hit.seat.row, hit.seat.col);
            });
            renderer._bccClickBound = true;
        }

        // Приводим схему к размеру контейнера
        renderer.fitToCanvas();
        // Запоминаем базовый scale, чтобы zoom считался от него
        if (renderer.getInternal && renderer.getInternal()._fit) {
            renderer.getInternal()._fit._baseScale = renderer.getInternal()._fit.scale || 1;
        }
        currentZoom = 1;
        renderer.render();

        // Легенда — clear previous inline legend content and render fresh
        const legendContainer = document.getElementById('bccLegend');
        if (window.LegendCanvas && legendContainer) {
            legendContainer.innerHTML = '';
            legend = window.LegendCanvas.init('bccSeatmapCanvas', {
                boxSize: 12,
                gap: 6,
                itemGap: 10,
                fontSize: 13,
                container: legendContainer
            });
            if (legend && typeof legend.attachToRenderer === 'function') {
                legend.attachToRenderer(renderer);
            }
            if (legend && typeof legend.setPricedColors === 'function') {
                legend.setPricedColors(pricedColors);
            }
            if (legend && typeof legend.renderLegend === 'function') {
                legend.renderLegend();
            }
        }
    }

    function getSeatKey(seat, row, col) {
        if (seat && seat.identifier) return seat.identifier;
        return row + '-' + col;
    }

    function getSeatPrice(seat, row, col) {
        if (seat && typeof seat.price === 'number') return seat.price;
        if (seat && typeof seat.price === 'string') return parseFloat(seat.price) || 0;
        const key = row + '-' + col;
        const colonKey = row + ':' + col;
        if (renderer && renderer._internal && renderer._internal.pricedSeats) {
            const map = renderer._internal.pricedSeats;
            if (map[key] !== undefined) return Number(map[key]) || 0;
            if (map[colonKey] !== undefined) return Number(map[colonKey]) || 0;
            if (seat && seat.key && map[seat.key] !== undefined) return Number(map[seat.key]) || 0;
        }
        return 0;
    }

    function isSold(key) {
        if (!renderer || !renderer._internal) return false;
        return !!(renderer._internal.soldSeats[key.replace(':', '-')]
               || renderer._internal.soldSeats[key.replace(/-/g, ':')]);
    }

    function isReserved(key) {
        if (!renderer || !renderer._internal) return false;
        return !!(renderer._internal.reservedSeats[key.replace(':', '-')]
               || renderer._internal.reservedSeats[key.replace(/-/g, ':')]);
    }

    function toggleSeat(seat, row, col) {
        const key = getSeatKey(seat, row, col);
        const normKey = key.replace(':', '-');
        const price = getSeatPrice(seat, row, col);
        if (price <= 0 || isSold(normKey) || isReserved(normKey)) return;
        const idx = selectedSeats.findIndex(s => s.identifier === key);

        if (idx !== -1) {
            selectedSeats.splice(idx, 1);
        } else {
            selectedSeats.push({ identifier: key, price: price, row: row, col: col });
        }
        renderer.selectSeats(selectedSeats.map(function (s) { return s.identifier; }));
        updateCart();
    }

    function updateCart() {
        if (!cartList) return;
        cartList.innerHTML = '';
        if (selectedSeats.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'bcc-cart-empty';
            empty.textContent = 'Выберите места на схеме зала';
            cartList.appendChild(empty);
        } else {
            selectedSeats.forEach(function (s, idx) {
                cartList.appendChild(createSeatCard(s, idx));
            });
        }
        updateTotal();
    }

    function createSeatCard(s, idx) {
        const card = document.createElement('div');
        card.className = 'bcc-seat-card';
        const info = document.createElement('div');
        info.className = 'bcc-seat-card-info';
        const title = document.createElement('span');
        title.className = 'bcc-seat-card-title';
        title.textContent = s.row + ' ряд, ' + s.col + ' место';
        const meta = document.createElement('span');
        meta.className = 'bcc-seat-card-meta';
        meta.textContent = 'Взрослый';
        info.appendChild(title);
        info.appendChild(meta);
        const right = document.createElement('div');
        right.style.display = 'flex';
        right.style.alignItems = 'center';
        const price = document.createElement('span');
        price.className = 'bcc-seat-card-price';
        price.textContent = formatMoney(s.price) + ' ' + cfg.currencySymbol;
        const remove = document.createElement('button');
        remove.className = 'bcc-seat-card-remove';
        remove.setAttribute('aria-label', 'Убрать билет');
        remove.innerHTML = '&times;';
        remove.addEventListener('click', function () {
            selectedSeats.splice(idx, 1);
            renderer.selectSeats(selectedSeats.map(function (seat) { return seat.identifier; }));
            updateCart();
        });
        right.appendChild(price);
        right.appendChild(remove);
        card.appendChild(info);
        card.appendChild(right);
        return card;
    }

    function renderCustomerCart() {
        if (!customerCartList) return;
        customerCartList.innerHTML = '';
        if (selectedSeats.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'bcc-cart-empty';
            empty.textContent = 'Места не выбраны';
            customerCartList.appendChild(empty);
            return;
        }
        selectedSeats.forEach(function (s) {
            const card = document.createElement('div');
            card.className = 'bcc-seat-card';
            const info = document.createElement('div');
            info.className = 'bcc-seat-card-info';
            const title = document.createElement('span');
            title.className = 'bcc-seat-card-title';
            title.textContent = s.row + ' ряд, ' + s.col + ' место';
            const meta = document.createElement('span');
            meta.className = 'bcc-seat-card-meta';
            meta.textContent = 'Взрослый';
            info.appendChild(title);
            info.appendChild(meta);
            const price = document.createElement('span');
            price.className = 'bcc-seat-card-price';
            price.textContent = formatMoney(s.price) + ' ' + cfg.currencySymbol;
            card.appendChild(info);
            card.appendChild(price);
            customerCartList.appendChild(card);
        });
    }

    function validatePhone(phone) {
        const digits = phoneDigits(phone);
        return digits.length >= 10;
    }

    function startPayment() {
        hideError();
        if (!currentSession) {
            showError('Сеанс не загружен');
            return;
        }
        if (selectedSeats.length === 0) {
            showError('Выберите места');
            return;
        }

        const phone = canonicalCustomerPhone(document.getElementById('bccCustomerPhone').value);
        const name = document.getElementById('bccCustomerName').value.trim();
        const email = document.getElementById('bccCustomerEmail').value.trim();

        if (!validatePhone(phone)) {
            showError('Введите корректный номер телефона');
            return;
        }

        payBtn.disabled = true;
        payBtn.textContent = 'Подождите...';

        const payload = {
                action: 'create_bcc_session',
                session_id: currentSession.id || currentSession.session_id,
                seats: selectedSeats.map(function (s) { return { identifier: s.identifier, price: s.price }; }),
                customer_name: name,
                customer_phone: phone,
                customer_email: email,
                client_hold_token: clientHoldToken
            };
        if (cfg.csrfToken) {
            payload.csrf_token = cfg.csrfToken;
        }
        fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(r => r.json())
            .then(data => {
                payBtn.disabled = false;
                payBtn.textContent = 'Оплатить';
                if (!data.success) {
                    showError(data.message || 'Ошибка создания платёжной сессии');
                    return;
                }
                logDebug('Server response', data);
                submitBccForm(data.action, data.fields);
            })
            .catch(err => {
                payBtn.disabled = false;
                payBtn.textContent = 'Оплатить';
                console.error(err);
                showError('Ошибка соединения с сервером');
            });
    }

    function logDebug(label, data) {
        const out = document.getElementById('bccDebugOutput');
        if (!out) return;
        const entry = document.createElement('div');
        entry.className = 'bcc-debug-entry';
        const time = new Date().toLocaleTimeString('ru-KZ');
        entry.innerHTML = '<strong>' + time + ' — ' + label + '</strong><pre>' +
            (typeof data === 'string' ? data : JSON.stringify(data, null, 2)) + '</pre>';
        out.appendChild(entry);
        out.scrollTop = out.scrollHeight;
    }

    function submitBccForm(action, fields) {
        logDebug('BCC request action', action);
        logDebug('BCC request fields', fields);

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        // Открываем банк в текущем iframe, чтобы весь сценарий покупки
        // продолжался внутри виджета.
        form.target = '_self';
        form.style.display = 'none';

        for (const key in fields) {
            if (Object.prototype.hasOwnProperty.call(fields, key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
        // Не удаляем форму сразу, чтобы браузер успел начать навигацию
        setTimeout(function () {
            if (form.parentNode) form.parentNode.removeChild(form);
        }, 5000);
    }

    document.querySelectorAll('.btn-buy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const sessionId = parseInt(this.getAttribute('data-session-id'), 10);
            openModal(sessionId);
        });
    });

    document.querySelector('.bcc-modal-close').addEventListener('click', closeModal);
    document.querySelector('.bcc-modal-backdrop').addEventListener('click', closeModal);

    if (buyBtn) {
        buyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (selectedSeats.length === 0) return;
            showStep('customer');
        });
    }

    if (payBtn) {
        payBtn.addEventListener('click', startPayment);
    }

    if (reserveBtn) {
        reserveBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            reserveSelectedSeats();
        });
    }

    const backToSeats = document.getElementById('bccBackToSeats');
    if (backToSeats) {
        backToSeats.addEventListener('click', function () {
            showStep('seats');
        });
    }

    const zoomIn = document.getElementById('bccZoomIn');
    const zoomOut = document.getElementById('bccZoomOut');
    if (zoomIn) zoomIn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); setZoom(0.2); });
    if (zoomOut) zoomOut.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); setZoom(-0.2); });

    // Кнопка сброса масштаба
    const resetZoomBtn = document.getElementById('bccResetZoom');
    if (resetZoomBtn) {
        resetZoomBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (renderer && renderer.resetView) {
                renderer.resetView();
                currentZoom = 1;
                var internal = renderer.getInternal();
                if (internal && internal._fit) internal._fit._baseScale = internal._fit.scale || 1;
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });

    // Экспортируем для внешних вызовов (например, из iframe-виджета Тильды)
    window.openBccModal = openModal;
})();
