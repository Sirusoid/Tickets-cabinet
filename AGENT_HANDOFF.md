# Handoff для другого агента

Этот файл предназначен для быстрого погружения нового разработчика или AI-агента в проект Tickets Cabinet.

## Проект

Tickets Cabinet — PHP-кабинет Алматинского театра «Жас сахна» им. Б. Омарова.

Основные домены:

- публичный сайт: `https://zhassahna.kz`;
- кабинет/backend: `https://cabinet.zhassahna.kz`;
- публичный iframe-виджет: используется внутри Tilda;
- BCC test endpoint: `https://test3ds.bcc.kz:5445/cgi-bin/cgi_link`.

## Стек

- PHP 8.4+;
- MariaDB/MySQL;
- PDO;
- Apache/Plesk;
- Composer;
- vanilla JavaScript и jQuery;
- Dompdf для PDF;
- Endroid QR Code для QR;
- PhpSpreadsheet для XLSX.

## Перед началом работы

1. Прочитать этот файл и `README.md`.
2. Для архитектуры открыть `FILE_STRUCTURE.md`.
3. Для деплоя открыть `INSTRUCTIONS.md` и `DEPLOY_NOTES.md`.
4. Проверить текущие незакоммиченные изменения перед редактированием.
5. Не откатывать изменения пользователя.
6. Не менять кассовый sale-flow без отдельного запроса.
7. Не импортировать полный SQL-дамп в production.

## Конфигурация

`config.php` читает переменные окружения с префиксом `ZHASSAHNA_`.

Обязательные переменные:

```dotenv
ZHASSAHNA_APP_ENV=production
ZHASSAHNA_DB_HOST=localhost
ZHASSAHNA_DB_PORT=3306
ZHASSAHNA_DB_NAME=...
ZHASSAHNA_DB_USER=...
ZHASSAHNA_DB_PASS=...
ZHASSAHNA_TICKET_PUBLIC_SECRET=...
```

Локальный `.env` находится в корне проекта и не должен попадать в Git, архив релиза или публичный web-доступ.

`ZHASSAHNA_TICKET_PUBLIC_SECRET` используется для подписей публичных ссылок на заказы/PDF. Нельзя менять его без плана миграции: старые ссылки перестанут работать.

## Вход и сессии

- `/index.php` направляет гостя на `/login.php`.
- Авторизованный пользователь попадает на `/dashboard.php`.
- `require_login()` находится в `includes/auth.php`.
- Сессия имеет HttpOnly/SameSite cookie и strict mode.
- ID сессии меняется после успешного входа.
- Проверяется `users.is_active`.
- Таймаут сессии настраивается через `security.session_idle_minutes`.
- Лимит входов настраивается через `security.max_login_attempts`.
- Logout выполняется POST-запросом с CSRF.

## Структура интерфейса

- `includes/header.php` — общий HTML head, верхняя шапка и текущий пользователь.
- `includes/sidebar.php` — основное меню, настройки и выход.
- `includes/footer.php` — общий JS, тосты и глобальные модалки.
- `includes/panel.php` — заголовок раздела.
- `dashboard.php` — основная панель с содержимым отчёта продаж.
- `reports/sales.php` — источник отчётного содержимого, умеет работать во встроенном режиме через `$reportsEmbedded`.

Пункты «Отчёты» и «Тесты» удалены из sidebar.

## Настройки

Страницы настроек:

- `settings/general.php` — общие параметры и режим обслуживания;
- `settings/security.php` — парольная политика и безопасность сессий;
- `settings/interface.php` — интерфейс без preview витрины;
- `settings/tickets.php` — лимиты билетов и резервы;
- `settings/payment.php` — эквайринг;
- `settings/notifications.php` — callback/уведомления;
- `settings/users.php` — сотрудники;
- `settings/permissions.php` — роли и доступы.

Все стандартные настройки сохраняются в таблице `settings` через `includes/settings_manager.php`.

## Режим обслуживания

Ключи:

- `system.maintenance_enabled`;
- `system.maintenance_title`;
- `system.maintenance_message`.

Проверка работает в:

- `widget/afisha.php`;
- `tickets/widget.php`;
- `ajax/public_cash.php`;
- `ajax/public_payment.php`.

При включении публичная часть показывает вежливую страницу с сообщением вместо афиши/покупки, а AJAX не позволяет создавать новую публичную операцию.

## Покупка и BCC

Покупка:

1. `tickets/widget.php` загружает iframe.
2. `assets/js/bcc-payment-modal.js` выбирает места и отправляет данные.
3. `ajax/public_payment.php` проверяет сеанс, лимиты, места и создаёт `payment_sessions`.
4. `includes/payment/bcc.php` формирует запрос BCC.
5. Банк возвращает пользователя в `payment/bcc/return.php`.
6. BCC отправляет callback в `payment/bcc/notify.php`.
7. `includes/payment/bcc_finalize.php` идемпотентно создаёт билеты, транзакцию и PDF.

Возврат:

- `ajax/public_refund.php`;
- `includes/payment/bcc_refund.php`;
- BCC `TRTYPE=14`.

Не менять порядок и идемпотентность финализатора без теста повторного callback.

## Данные о телефонах

Использовать функции из `includes/helper.php`:

- `customer_phone_digits()`;
- `normalize_customer_phone()`;
- `format_customer_phone()`.

Каноническое хранение: `+77772979723`.

Отображение: `+7 777 297 97 23`.

Поддерживаются вводы с `8`, `7`, `+7`, пробелами и скобками.

## Клиенты

- `customers/list.php` — таблица клиентов, фильтры, pagination и действия.
- `assets/js/customers_list.js` — live search, autocomplete, edit modal, delete modal.
- `ajax/customer.php` — list/get/create/update/delete.
- `customers/export_excel.php` — XLSX-файл клиентов.

Удаление использует общую footer-модалку. Редактирование использует модалку страницы. Все изменения клиента защищены CSRF.

## Отчёты и Excel

- `reports/sales.php` — расчёты выручки, скидок, оплат и возвратов.
- `reports/export_excel.php` — экспорт отчёта продаж.
- `tickets/export_excel.php` — экспорт билетов с фильтрами и итогами.
- `includes/excel.php` — loader PhpSpreadsheet.

PhpSpreadsheet установлен через Composer. Для XLSX требуется PHP extension `zip`.

## PDF

PDF хранится в каталоге проекта:

```text
uploads/tickets/
```

Главный helper:

```text
includes/pdf_helpers.php
```

Не возвращать путь PDF к родительскому `DOCUMENT_ROOT`: storage должен оставаться внутри проекта.

## Apache-защита

Корневой `.htaccess` запрещает доступ к:

- `.env`, `.git`, `.vscode`;
- `config.php`, `init.php`;
- `vendor/`, `includes/`, `sql/`, `logs/`, `tools/`, `tests/`, `cgi-bin/`;
- служебным install/diagnostic scripts;
- скриптам внутри `uploads/`.

При переносе на Nginx эти правила нужно заменить эквивалентными правилами конфигурации Nginx.

## Команды проверки

PHP lint:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Composer:

```powershell
composer validate --no-check-publish
composer check-platform-reqs
```

Проверка XLSX:

```powershell
php -r "require 'vendor/autoload.php'; echo class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet') ? 'ok' : 'missing';"
```

## Известные ограничения

- BCC тестирование может быть недоступно из-за банковских лимитов/ошибок провайдера.
- Старые `cash_transactions` могут иметь исторически несовместимые единицы суммы; отчёт продаж считает истиной таблицу `tickets`.
- Старые телефоны клиентов могли быть сохранены без `+`; поиск и отображение поддерживают оба формата.
- Полный SQL dump не является безопасной production-миграцией.
- После изменения кода на сервере может потребоваться очистка OPcache.

## Правила для агента

- Перед изменением читать локальный код и ближайшие вызовы.
- Делать минимальные изменения в существующем стиле.
- После первого изменения выполнять узкую проверку.
- Не редактировать `vendor/` без крайней необходимости.
- Не показывать и не переносить реальные секреты в документацию.
- Не использовать destructive git-команды.
- После изменений сообщать список файлов и проверки.
