<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/settings_manager.php';

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        settings_upsert_value(
            $pdo,
            'tickets.discount_adult_percent',
            'Скидка для взрослого билета (%)',
            '0',
            'float',
            'discounts',
            'Процент скидки для типа билета "Взрослый"',
            1,
            10
        );
        settings_upsert_value(
            $pdo,
            'tickets.discount_child_percent',
            'Скидка для детского билета (%)',
            '0',
            'float',
            'discounts',
            'Процент скидки для типа билета "Детский"',
            1,
            11
        );
        settings_upsert_value(
            $pdo,
            'tickets.discount_student_percent',
            'Скидка для студенческого билета (%)',
            '0',
            'float',
            'discounts',
            'Процент скидки для типа билета "Студенческий"',
            1,
            12
        );
        settings_upsert_value(
            $pdo,
            'tickets.discount_senior_percent',
            'Скидка для пенсионного билета (%)',
            '0',
            'float',
            'discounts',
            'Процент скидки для типа билета "Пенсионный"',
            1,
            13
        );
        settings_upsert_value(
            $pdo,
            'tickets.custom_discount_enabled',
            'Разрешить ручную скидку в кассе',
            '1',
            'bool',
            'discounts',
            'Кассир сможет вводить дополнительную скидку при продаже',
            1,
            20
        );
        settings_upsert_value(
            $pdo,
            'tickets.custom_discount_max_percent',
            'Максимальная ручная скидка (%)',
            '30',
            'float',
            'discounts',
            'Ограничение для скидки кассира в процентах',
            1,
            21
        );
        settings_upsert_value(
            $pdo,
            'tickets.custom_discount_max_amount',
            'Максимальная ручная скидка (тг)',
            '0',
            'float',
            'discounts',
            '0 = без ограничения по сумме',
            1,
            22
        );
    } catch (Throwable $e) {
        error_log('settings/discounts.php init error: ' . $e->getMessage());
    }
}

$settingsPageKey = 'discounts';
require __DIR__ . '/_page.php';
