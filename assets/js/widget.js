(function () {
    'use strict';

    var config = window.ZhassahnaWidget || {};
    var apiUrl = config.apiUrl || 'https://cabinet.zhassahna.kz/widget/afisha.php';
    var containerId = config.containerId || 'zhassahna-afisha';
    var modalId = config.modalId || 'zhassahna-modal';

    function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = cb;
        s.onerror = function () {};
        document.head.appendChild(s);
    }

    function init() {
        var container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        container.innerHTML = '<p style="text-align:center; color:#6b7280;">Загрузка афиши...</p>';

        fetch(apiUrl, {
            method: 'GET',
            credentials: 'include'
        })
        .then(function (r) { return r.json(); })
        .then(function (response) {
            if (response.html) {
                container.innerHTML = response.html;
                bindBuyButtons(container);
            } else if (response.error) {
                container.innerHTML = '<h3 style="text-align:center;">Ошибка: ' + escapeHtml(response.error) + '</h3>';
            } else {
                container.innerHTML = '<h3 style="text-align:center;">Неизвестная ошибка</h3>';
            }
        })
        .catch(function (err) {
            container.innerHTML = '<h3 style="text-align:center;">Ошибка загрузки афиши</h3>';
        });
    }

    function bindBuyButtons(container) {
        container.addEventListener('click', function (e) {
            var btn = e.target.closest('.zhassahna-buy-btn');
            if (!btn) return;
            e.preventDefault();
            var url = btn.getAttribute('data-widget-url');
            if (!url) {
                var sessionId = btn.getAttribute('data-session-id');
                if (sessionId) {
                    url = 'https://cabinet.zhassahna.kz/tickets/widget.php?session_id=' + encodeURIComponent(sessionId);
                }
            }
            if (url) {
                openModal(url);
            }
        });
    }

    function openModal(url) {
        var existing = document.getElementById(modalId);
        if (existing) existing.remove();

        var previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        var overlay = document.createElement('div');
        overlay.id = modalId;
        overlay.setAttribute('style',
            'position:fixed; inset:0; width:100vw; height:100vh; background:rgba(0,0,0,0.78); ' +
            'z-index:2147483647; display:block; padding:0; box-sizing:border-box; overflow:hidden;'
        );

        var iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.setAttribute('style',
            'position:absolute; inset:0; width:100%; height:100%; border:none; border-radius:0; background:#fff; margin:0; display:block;'
        );
        iframe.setAttribute('allowfullscreen', 'true');

        overlay.appendChild(iframe);
        document.body.appendChild(overlay);

        overlay._oldBodyOverflow = previousOverflow;

        // Слушаем сообщение из iframe о закрытии виджета
        var messageHandler = function (e) {
            if (e.origin !== 'https://cabinet.zhassahna.kz') return;
            if (e.data && e.data.type === 'zhassahna:widget:close') {
                overlay.remove();
                document.body.style.overflow = overlay._oldBodyOverflow || '';
                window.removeEventListener('message', messageHandler);
            }
        };
        window.addEventListener('message', messageHandler);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                overlay.remove();
                document.body.style.overflow = overlay._oldBodyOverflow || '';
            }
        });

        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape') {
                overlay.remove();
                document.body.style.overflow = overlay._oldBodyOverflow || '';
                document.removeEventListener('keydown', onKey);
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function openSuccessModal(order, amount, tickets) {
        var existing = document.getElementById('zhassahna-success-modal');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.id = 'zhassahna-success-modal';
        overlay.setAttribute('style',
            'position:fixed; inset:0; width:100vw; height:100vh; background:rgba(0,0,0,0.75); ' +
            'z-index:2147483647; display:flex; align-items:center; justify-content:center; padding:0; box-sizing:border-box;'
        );

        var closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Закрыть');
        closeBtn.setAttribute('style',
            'position:absolute; top:16px; right:24px; background:#fff; border:none; border-radius:50%; ' +
            'width:44px; height:44px; font-size:28px; line-height:44px; cursor:pointer; z-index:100002;'
        );
        closeBtn.onclick = function () { overlay.remove(); };

        var content = document.createElement('div');
        content.setAttribute('style',
            'background:#fff; border-radius:12px; padding:28px; max-width:500px; width:100%; max-height:80vh; overflow:auto; position:relative;'
        );

        var ticketsHtml = '';
        if (Array.isArray(tickets) && tickets.length) {
            ticketsHtml = '<h4 style="margin:16px 0 8px;">Билеты</h4><ul style="padding-left:20px; margin:0;">';
            tickets.forEach(function (t) {
                ticketsHtml += '<li>Место ' + escapeHtml(t.seat) + ' — ' + escapeHtml(t.price) + ' ₸</li>';
            });
            ticketsHtml += '</ul>';
        }

        content.innerHTML =
            '<div style="text-align:center; margin-bottom:16px;">' +
            '<div style="font-size:48px; margin-bottom:8px;">✓</div>' +
            '<h2 style="margin:0 0 8px;">Оплата прошла успешно!</h2>' +
            '<p style="color:#6b7280; margin:0;">Заказ <strong>' + escapeHtml(order) + '</strong> · ' + escapeHtml(amount) + ' ₸</p>' +
            '</div>' +
            ticketsHtml +
            '<div style="text-align:center; margin-top:24px;">' +
            '<button class="btn btn-primary" id="zhassahna-success-close">Закрыть</button>' +
            '</div>';

        overlay.appendChild(closeBtn);
        overlay.appendChild(content);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.remove();
        });
        document.getElementById('zhassahna-success-close').onclick = function () {
            overlay.remove();
        };
        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape') {
                overlay.remove();
                document.removeEventListener('keydown', onKey);
            }
        });
    }

    window.addEventListener('message', function (e) {
        var allowedOrigin = 'https://zhassahna.kz';
        if (e.origin !== allowedOrigin) return;
        if (e.data && e.data.type === 'zhassahna:payment:success') {
            openSuccessModal(e.data.order, e.data.amount, e.data.tickets);
        }
    });

    // Загружаем jQuery, если её ещё нет (некоторые блоки Тильды зависят от неё)
    if (window.jQuery) {
        init();
    } else {
        loadScript('https://cabinet.zhassahna.kz/assets/js/jquery.min.js', init);
    }
})();
