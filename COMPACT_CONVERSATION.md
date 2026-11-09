# Compact Conversation

## Проект

PHP 8.4+, PDO, MariaDB/MySQL. Публичный сайт: `https://zhassahna.kz`. Кабинет и backend: `https://cabinet.zhassahna.kz`. Виджет работает внутри iframe на Tilda.

## BCC

Используется BCC e-Commerce WEBVIEW, покупка CARD `TRTYPE=1`, тестовый endpoint `https://test3ds.bcc.kz:5445/cgi-bin/cgi_link`.

`BACKREF`: `https://cabinet.zhassahna.kz/payment/bcc/return.php` в текущей рабочей настройке.

`NOTIFY_URL`: `https://cabinet.zhassahna.kz:443/payment/bcc/notify.php`.

MAC: строка строится как последовательность `длина + значение` в документированном порядке; HEX-ключ переводится в binary через `hex2bin`/`pack("H*", ...)`; затем HMAC-SHA1, uppercase HEX. Документированный пример TRTYPE=14 проверен и совпал.

Тестирование банка после восстановления завершено: проверены кейсы покупки 2.1–2.5, `TRTYPE=14`, BACKREF/NOTIFY, создание билетов и PDF, `cash_transactions`, отчёты и освобождение мест.

## Текущая реализация

- `tickets/widget.php`, `assets/js/bcc-payment-modal.js`: iframe-покупка, банк открывается в текущем iframe.
- `payment/bcc/return.php`: публичный возврат без админской шапки, прямое завершение покупки при успешном BACKREF.
- `payment/bcc/notify.php`: обработка покупки и отдельная ветка TRTYPE=14.
- `includes/payment/bcc_finalize.php`: идемпотентное создание билетов и PDF; нормализованная запись online `cash_transactions`.
- `includes/payment/bcc_refund.php`, `ajax/public_refund.php`: самостоятельный возврат через TRTYPE=14.
- `tickets/public.php`: двуязычная страница заказа, QR, PDF, возврат, памятка RU/KZ.
- `reports/sales.php`: продажи считаются по `tickets`; разрезы карта/наличные и возвраты; `cash_transactions` используется для способа оплаты и сверки.

Текущий фокус завершён: BCC, самостоятельный возврат, публичная страница заказа, касса/виджет и production-деплой проверены.

## UI изменения

- `assets/css/admin.css`: заголовок sticky, кнопка сайдбара внутри header, видна при свернутом сайдбаре.
- `assets/js/tickets_list.js` (строка 299): кнопка «Возврат» скрыта для билетов со статусом `used` («Использован»). Условие: `if (!isRefunded && t.status !== 'used')`. Для `cancelled` — disabled, но видна.

## BCC e-Commerce WEBVIEW — текущее состояние

- Production credentials настроены через `settings/payment.php` и `config.php`.
- Исходящий MAC (подпись запроса к банку): корректно реализован в `includes/payment/bcc.php` (`bcc_build_mac_string` / `bcc_sign`). Порядок полей фиксирован, формат `<len><value>`, HMAC-SHA1 → uppercase HEX.
- Входящий MAC (валидация ответа от банка): **TODO**. Функция `bcc_validate_response_signature` — placeholder. Требуется для production безопасности.

## Важная модель данных

Кассовая запись `cash_transactions` содержит сумму и подробный payload. Онлайн-запись раньше была неполной (`amount_cents=0`, `ticket_uids=NULL`), это исправлено для новых оплат. Старые записи не переписывать без сверки.

Истина для проданных билетов и выручки: `tickets` с `payment_status='paid'`, исключая `status='cancelled'` и `refund_status='refunded'`.

## Не делать без отдельного запроса

Не импортировать полный SQL-дамп в production. Не менять кассовый sale-flow. Не реализовывать TOKEN, P2P, recurring и двухстадийную оплату: они не входят в текущую схему.
