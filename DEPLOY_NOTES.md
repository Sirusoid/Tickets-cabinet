# Что нужно залить/сделать на сервере, чтобы исправить ошибку 96 BCC

## Проблема
Живой сервер всё ещё возвращает в форме BCC:
```
BACKREF=https://cabinet.zhassahna.kz/tests/bcc_return.php
```
Этот путь не существует — банк после оплаты не может вернуть покупателя, поэтому возникает **ошибка 96**.

## Файлы, которые обязательно нужно загрузить на сервер

1. `config.php`
2. `ajax/public_payment.php`
3. `includes/payment/bcc.php`
4. `payment/bcc/return.php`
5. `payment/bcc/notify.php`
6. `tickets/public.php`
7. `assets/js/widget.js`
8. `tickets/widget.php`

## Очистить кэш
После загрузки перезапустить PHP / очистить OPcache (через панель хостинга или `killall lsphp`).

## Исправить значение в базе
На сервере в таблице `settings` поле `payments.bcc_backref_path` сейчас, скорее всего, равно `/tests/bcc_return.php`.  
Нужно заменить на `/payment/bcc/return.php`.

Два способа:

### Способ A — через временный скрипт
Загрузить `tools/fix_bcc_backref_db.php` в корень сайта (`/var/www/vhosts/zhassahna.kz/cabinet.zhassahna.kz/`), открыть в браузере:
```
https://cabinet.zhassahna.kz/fix_bcc_backref_db.php
```
Затем **удалить файл с сервера**.

### Способ B — SQL-запрос
Выполнить в phpMyAdmin или консоли MySQL:
```sql
UPDATE settings SET value = '/payment/bcc/return.php' WHERE `key` = 'payments.bcc_backref_path';
```

## Проверка
Открыть в браузере (POST JSON):
```
https://cabinet.zhassahna.kz/ajax/public_payment.php
```
с телом:
```json
{
  "action": "create_bcc_session",
  "session_id": 67,
  "seats": [{"identifier":"12-22","price":5000}],
  "customer_phone": "+77772979723"
}
```
В ответе `fields.BACKREF` должен быть:
```
https://cabinet.zhassahna.kz/payment/bcc/return.php
```

После этого можно повторить оплату на:
```
https://zhassahna.kz/widget-test
```
