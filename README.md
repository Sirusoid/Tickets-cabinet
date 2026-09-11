# Tickets Cabinet

Административный кабинет Алматинского театра «Жас сахна» им. Б. Омарова.

Система управляет спектаклями, расписанием, залами, местами, билетами, клиентами, кассой, онлайн-оплатой BCC, PDF-билетами и отчётами.

## Возможности

- авторизация сотрудников и роли `admin`, `manager`, `cashier`;
- защищённые сессии, CSRF, timeout и парольная политика;
- dashboard с компактной сводкой продаж и отчётами по спектаклям/сеансам;
- фильтруемый отчёт продаж с корректным вычитанием скидок и XLSX со сводкой и деталями;
- журнал действий сотрудников, доступный администратору;
- управление спектаклями, расписанием и залами;
- кассовая продажа и резервирование мест;
- онлайн-покупка через BCC `TRTYPE=1`;
- самостоятельный возврат через BCC `TRTYPE=14`;
- публичная страница заказа с QR и PDF;
- клиенты с живым поиском, редактированием и удалением;
- экспорт клиентов, билетов и отчётов в XLSX;
- режим обслуживания публичной витрины;
- двуязычные публичные сообщения RU/KZ.

## Технологии

- PHP 8.4+;
- PDO;
- MariaDB/MySQL;
- Composer;
- Dompdf 3.x;
- Endroid QR Code 6.x;
- PhpSpreadsheet 5.x;
- vanilla JavaScript/jQuery;
- Apache/Plesk-compatible `.htaccess`.

## Установка

```powershell
composer install
```

Создайте `.env` на основе `.env.example` и заполните:

```dotenv
ZHASSAHNA_APP_ENV=production
ZHASSAHNA_DB_HOST=localhost
ZHASSAHNA_DB_PORT=3306
ZHASSAHNA_DB_NAME=your_database_name
ZHASSAHNA_DB_USER=your_database_user
ZHASSAHNA_DB_PASS=your_database_password
ZHASSAHNA_TICKET_PUBLIC_SECRET=long-random-secret
```

Не публикуйте `.env` и не добавляйте его в Git.

## Запуск локально

```powershell
php -S localhost:8080
```

Открыть:

```text
http://localhost:8080/
```

Корень перенаправляет гостей на `/login.php`, авторизованных пользователей — на `/dashboard.php`.

## Production

Проект рассчитан на:

```text
https://cabinet.zhassahna.kz
```

Публичная интеграция:

```text
https://zhassahna.kz
```

После загрузки файлов:

1. Задать переменные окружения или загрузить защищённый `.env`.
2. Выполнить `composer install --no-dev --optimize-autoloader`.
3. Проверить права на `uploads/` и `uploads/tickets/`.
4. Перезапустить PHP-FPM/очистить OPcache.
5. Проверить `.htaccess`.
6. Проверить `/`, `/login.php`, `/dashboard.php`.
7. Проверить Tilda widget, BCC callback, PDF и XLSX.

Не импортируйте полный SQL-дамп в рабочую базу. Используйте точечные миграции и настройки кабинета.

## Безопасность

В корневом `.htaccess` закрыты:

- `.env`, `.git`, `.vscode`;
- `config.php`, `init.php`;
- `vendor/`, `includes/`, `sql/`, `logs/`, `tools/`, `tests/`, `cgi-bin/`;
- резервные файлы, SQL, логи и конфигурации;
- выполнение скриптов в `uploads/`.

Секреты БД и подписи публичных ссылок задаются только через окружение/`.env`. После раскрытия старого пароля БД его нужно сменить.

## Документация

- [INSTRUCTIONS.md](INSTRUCTIONS.md) — рабочие инструкции, деплой и проверки;
- [FILE_STRUCTURE.md](FILE_STRUCTURE.md) — структура файлов, связи и потоки данных;
- [DEPLOY_NOTES.md](DEPLOY_NOTES.md) — заметки по BCC и production-деплою;
- [TODO.md](TODO.md) — текущие задачи;
- [COMPACT_CONVERSATION.md](COMPACT_CONVERSATION.md) — технический контекст разработки.

Для существующей базы журнал действий проверяется точечной миграцией
`sql/migration_audit_logs.sql`; полный SQL-дамп в production не импортировать.

## Проверка PHP

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Лицензия

Проект является внутренним программным обеспечением театра. Лицензия и правила распространения определяются владельцем проекта.
