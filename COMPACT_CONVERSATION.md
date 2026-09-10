# Compact Conversation

## Проект

PHP 8.4+, PDO, MariaDB/MySQL. Публичный сайт: `https://zhassahna.kz`. Кабинет и backend: `https://cabinet.zhassahna.kz`. Виджет работает внутри iframe на Tilda.

## BCC

Используется BCC e-Commerce WEBVIEW, покупка CARD `TRTYPE=1`, тестовый endpoint `https://test3ds.bcc.kz:5445/cgi-bin/cgi_link`.

`BACKREF`: `https://cabinet.zhassahna.kz/payment/bcc/return.php` в текущей рабочей настройке.

`NOTIFY_URL`: `https://cabinet.zhassahna.kz:443/payment/bcc/notify.php`.

MAC: строка строится как последовательность `длина + значение` в документированном порядке; HEX-ключ переводится в binary через `hex2bin`/`pack("H*", ...)`; затем HMAC-SHA1, uppercase HEX. Документированный пример TRTYPE=14 проверен и совпал.

Тестирование банка остановлено из-за временной недоступности/лимитов BCC. После восстановления продолжить с кейса 2.1 и заполнить таблицу 2.1-2.5 плюс TRTYPE=14.

## Текущая реализация

- `tickets/widget.php`, `assets/js/bcc-payment-modal.js`: iframe-покупка, банк открывается в текущем iframe.
- `payment/bcc/return.php`: публичный возврат без админской шапки, прямое завершение покупки при успешном BACKREF.
- `payment/bcc/notify.php`: обработка покупки и отдельная ветка TRTYPE=14.
- `includes/payment/bcc_finalize.php`: идемпотентное создание билетов и PDF; нормализованная запись online `cash_transactions`.
- `includes/payment/bcc_refund.php`, `ajax/public_refund.php`: самостоятельный возврат через TRTYPE=14.
- `tickets/public.php`: двуязычная страница заказа, QR, PDF, возврат, памятка RU/KZ.
- `reports/sales.php`: продажи считаются по `tickets`; разрезы карта/наличные и возвраты; `cash_transactions` используется для способа оплаты и сверки.

## Важная модель данных

Кассовая запись `cash_transactions` содержит сумму и подробный payload. Онлайн-запись раньше была неполной (`amount_cents=0`, `ticket_uids=NULL`), это исправлено для новых оплат. Старые записи не переписывать без сверки.

Истина для проданных билетов и выручки: `tickets` с `payment_status='paid'`, исключая `status='cancelled'` и `refund_status='refunded'`.

## Не делать без отдельного запроса

Не импортировать полный SQL-дамп в production. Не менять кассовый sale-flow. Не реализовывать TOKEN, P2P, recurring и двухстадийную оплату: они не входят в текущую схему.
