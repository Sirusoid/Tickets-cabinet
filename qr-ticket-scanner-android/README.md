# QR Ticket Scanner for Android

Нативное Android-приложение для контролёров театра.

## Технологии

- Kotlin
- Jetpack Compose
- CameraX
- Google ML Kit Barcode Scanning
- OkHttp

## Как работает вход

Приложение использует существующую авторизацию кабинета через `/login.php`,
сохраняет сессионные cookies и вызывает защищённый endpoint:

```text
POST /ajax/ticket.php?action=checkin
```

Базовый URL задаётся в `app/build.gradle.kts` через `BuildConfig.API_BASE_URL`.

## Сборка

Откройте папку `qr-ticket-scanner-android/` в Android Studio, дождитесь синхронизации
Gradle и запустите конфигурацию `app` на Android 8.0 (API 26) или новее.

## Релизные файлы

- `releases/zhassahna-ticket-scanner-android.aab` — загрузка в Google Play Console.
- `releases/zhassahna-ticket-scanner-android.apk` — установка и тестирование на Android.

Файл upload-keystore и `keystore.properties` не добавляются в Git. Их необходимо
хранить в защищённом месте: потеря ключа потребует восстановления доступа через Google Play.
