# Архитектура проекта (Architecture)

## Структура каталогов
- `cache/`, `logs/`, `sql/` — служебные папки.
- `includes/` — базовые компоненты (auth, db, helper, pdf\_helpers).
- `settings/` — динамические настройки системы (CRUD рендер).
- `cash/` — логика кассового аппарата.
- `customers/` — работа с базой клиентов.
- `tickets/` — генерация и просмотр билетов (включая PDF-логику).
- `widget/` — афиша и интерактивные виджеты для внешних сайтов.
- `qr-ticket-scanner-android/`, `qr-ticket-scanner-ios/` — нативные приложения.

## База данных (Ключевые таблицы)
- `users`, `settings`
- `events` (Спектакли), `schedules` (Сеансы)
- `halls` (Залы), `seats` (Места)
- `tickets` (Билеты), `customers` (Клиенты)
- `cash_holds`, `seat_occupancy`, `payment_sessions`

## Связи
1. **Продажа:** Касса $\rightarrow$ Сеанс $\rightarrow$ Зал/Место $\rightarrow$ Билет.
2. **Оплата:** Виджет $\rightarrow$ BCC Gateway $\rightarrow$ Финализация билета.