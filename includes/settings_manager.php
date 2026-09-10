<?php

if (!function_exists('settings_pages_map')) {
    function settings_pages_map()
    {
        return [
            'general' => [
                'path' => '/settings/general.php',
                'label' => 'Общие',
                'title' => 'Общие',
                'subtitle' => 'Основные параметры кабинета и публичной витрины',
                'categories' => ['system'],
                'mode' => 'settings',
            ],
            'interface' => [
                'path' => '/settings/interface.php',
                'label' => 'Интерфейс',
                'title' => 'Интерфейс',
                'subtitle' => 'Настройки отображения кабинета и публичных страниц',
                'categories' => ['ui'],
                'mode' => 'settings',
            ],
            'tickets' => [
                'path' => '/settings/tickets.php',
                'label' => 'Билеты',
                'title' => 'Настройки билетов',
                'subtitle' => 'Правила продажи, возвратов и ограничений',
                'categories' => ['tickets'],
                'mode' => 'settings',
            ],
            'discounts' => [
                'path' => '/settings/discounts.php',
                'label' => 'Скидки',
                'title' => 'Настройки скидок',
                'subtitle' => 'Типы билетов и размеры скидок для кассы',
                'categories' => ['discounts'],
                'mode' => 'settings',
            ],
            'payment' => [
                'path' => '/settings/payment.php',
                'label' => 'Эквайр',
                'title' => 'Настройки онлайн эквайринга',
                'subtitle' => 'Платежные провайдеры, валюта и ключи доступа',
                'categories' => ['payments'],
                'mode' => 'settings',
            ],
            'notifications' => [
                'path' => '/settings/notifications.php',
                'label' => 'Уведомления',
                'title' => 'Настройки уведомлений',
                'subtitle' => 'Email, SMS и Telegram-уведомления',
                'categories' => ['notifications'],
                'mode' => 'settings',
            ],
            'security' => [
                'path' => '/settings/security.php',
                'label' => 'Безопасность',
                'title' => 'Безопасность',
                'subtitle' => 'Пароли, сессии, защита входа и контроль доступа',
                'categories' => ['security'],
                'mode' => 'settings',
            ],
            'users' => [
                'path' => '/settings/users.php',
                'label' => 'Пользователи',
                'title' => 'Пользователи',
                'subtitle' => 'Управление учетными записями сотрудников',
                'categories' => [],
                'mode' => 'custom',
            ],
            'permissions' => [
                'path' => '/settings/permissions.php',
                'label' => 'Роли и права',
                'title' => 'Роли и права доступа',
                'subtitle' => 'Матрица разрешений для модулей системы',
                'categories' => [],
                'mode' => 'custom',
            ],
        ];
    }
}

if (!function_exists('settings_category_titles')) {
    function settings_category_titles()
    {
        return [
            'system' => 'Система',
            'ui' => 'Интерфейс',
            'tickets' => 'Билеты',
            'discounts' => 'Скидки',
            'payments' => 'Платежи',
            'notifications' => 'Уведомления',
            'security' => 'Безопасность',
        ];
    }
}

if (!function_exists('settings_decode_options')) {
    function settings_decode_options($raw)
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('settings_input_name')) {
    function settings_input_name(array $row)
    {
        return 'settings[' . (int)$row['id'] . ']';
    }
}

if (!function_exists('settings_file_input_name')) {
    function settings_file_input_name(array $row)
    {
        return 'setting_file_' . (int)$row['id'];
    }
}

if (!function_exists('settings_remove_input_name')) {
    function settings_remove_input_name(array $row)
    {
        return 'setting_remove_' . (int)$row['id'];
    }
}

if (!function_exists('settings_value_for_form')) {
    function settings_value_for_form(array $row)
    {
        $value = $row['value'] ?? null;
        $type = $row['type'] ?? 'string';
        if ($type === 'bool') {
            return (string)$value === '1';
        }
        if ($type === 'json') {
            if ($value === null || $value === '') {
                return '';
            }
            $decoded = json_decode((string)$value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
        }
        return (string)($value ?? '');
    }
}

if (!function_exists('settings_fetch_rows_by_categories')) {
    function settings_fetch_rows_by_categories(PDO $pdo, array $categories)
    {
        if (empty($categories)) {
            return [];
        }

        $categories = array_values(array_unique(array_filter($categories, function ($item) {
            return is_string($item) && $item !== '';
        })));
        if (empty($categories)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $orderExpr = implode(',', array_fill(0, count($categories), '?'));
        $sql = "SELECT * FROM settings WHERE category IN ($placeholders) ORDER BY FIELD(category, $orderExpr), sort_order ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($categories, $categories));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('settings_upload_base_dir')) {
    function settings_upload_base_dir($type)
    {
        $projectRoot = dirname(__DIR__);
        if ($type === 'image') {
            return $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'settings';
        }
        return $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'settings';
    }
}

if (!function_exists('settings_upload_public_path')) {
    function settings_upload_public_path($type, $filename)
    {
        if ($type === 'image') {
            return '/uploads/images/settings/' . $filename;
        }
        return '/uploads/settings/' . $filename;
    }
}

if (!function_exists('settings_store_uploaded_file')) {
    function settings_store_uploaded_file(array $row, array $file)
    {
        $type = $row['type'] ?? 'file';
        if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return [false, $row['value'] ?? null, null];
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return [false, null, 'Ошибка загрузки файла для настройки "' . ($row['label'] ?? $row['key']) . '".'];
        }

        $targetDir = settings_upload_base_dir($type);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            return [false, null, 'Не удалось создать каталог для загрузки файлов настроек.'];
        }

        $extension = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $safeBase = preg_replace('/[^a-z0-9_\-]+/i', '_', (string)($row['key'] ?? 'setting'));
        $safeBase = trim((string)$safeBase, '_');
        if ($safeBase === '') {
            $safeBase = 'setting';
        }
        $generated = $safeBase . '_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
        $filename = $generated . ($extension !== '' ? '.' . $extension : '');
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [false, null, 'Не удалось сохранить загруженный файл.'];
        }

        return [true, settings_upload_public_path($type, $filename), null];
    }
}

if (!function_exists('settings_prepare_updates')) {
    function settings_prepare_updates(array $rows, array $post, array $files)
    {
        $submitted = isset($post['settings']) && is_array($post['settings']) ? $post['settings'] : [];
        $updates = [];
        $errors = [];

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $type = (string)($row['type'] ?? 'string');
            $label = (string)($row['label'] ?? $row['key'] ?? ('ID ' . $id));
            $currentValue = $row['value'] ?? null;
            $newValue = $currentValue;

            if (!(int)($row['is_editable'] ?? 0)) {
                continue;
            }

            if ($type === 'bool') {
                $newValue = array_key_exists((string)$id, $submitted) ? '1' : '0';
            } elseif ($type === 'image' || $type === 'file') {
                $removeKey = settings_remove_input_name($row);
                if (!empty($post[$removeKey])) {
                    $newValue = null;
                }
                $fileKey = settings_file_input_name($row);
                if (isset($files[$fileKey])) {
                    list($changed, $storedValue, $errorMessage) = settings_store_uploaded_file($row, $files[$fileKey]);
                    if ($errorMessage !== null) {
                        $errors[] = $errorMessage;
                        continue;
                    }
                    if ($changed) {
                        $newValue = $storedValue;
                    }
                }
            } else {
                $raw = array_key_exists((string)$id, $submitted) ? $submitted[(string)$id] : null;
                if (is_string($raw)) {
                    $raw = trim($raw);
                }

                switch ($type) {
                    case 'int':
                        if ($raw === '' || $raw === null) {
                            $newValue = null;
                        } elseif (filter_var($raw, FILTER_VALIDATE_INT) !== false) {
                            $newValue = (string)(int)$raw;
                        } else {
                            $errors[] = 'Поле "' . $label . '" должно быть целым числом.';
                            continue 2;
                        }
                        break;
                    case 'float':
                        if ($raw === '' || $raw === null) {
                            $newValue = null;
                        } elseif (is_numeric($raw)) {
                            $newValue = (string)(float)$raw;
                        } else {
                            $errors[] = 'Поле "' . $label . '" должно быть числом.';
                            continue 2;
                        }
                        break;
                    case 'json':
                        if ($raw === '' || $raw === null) {
                            $newValue = null;
                        } else {
                            $decoded = json_decode((string)$raw, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $errors[] = 'Поле "' . $label . '" содержит некорректный JSON.';
                                continue 2;
                            }
                            $newValue = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        break;
                    case 'select':
                        $options = settings_decode_options($row['options'] ?? null);
                        if (!in_array($raw, $options, true)) {
                            $errors[] = 'Для поля "' . $label . '" выбрано недопустимое значение.';
                            continue 2;
                        }
                        $newValue = $raw;
                        break;
                    case 'color':
                        if ($raw === '' || !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string)$raw)) {
                            $errors[] = 'Поле "' . $label . '" должно содержать корректный HEX-цвет.';
                            continue 2;
                        }
                        $newValue = $raw;
                        break;
                    case 'text':
                    case 'string':
                    default:
                        $newValue = $raw;
                        break;
                }
            }

            $currentComparable = $currentValue === null ? null : (string)$currentValue;
            $newComparable = $newValue === null ? null : (string)$newValue;
            if ($currentComparable !== $newComparable) {
                $updates[] = ['id' => $id, 'value' => $newValue];
            }
        }

        return [$updates, $errors];
    }
}

if (!function_exists('settings_save_rows')) {
    function settings_save_rows(PDO $pdo, array $rows, array $post, array $files)
    {
        list($updates, $errors) = settings_prepare_updates($rows, $post, $files);
        if (!empty($errors)) {
            return [false, $errors, 0];
        }
        if (empty($updates)) {
            return [true, [], 0];
        }

        $stmt = $pdo->prepare('UPDATE settings SET value = :value WHERE id = :id');
        $pdo->beginTransaction();
        try {
            foreach ($updates as $update) {
                if ($update['value'] === null || $update['value'] === '') {
                    $stmt->bindValue(':value', $update['value'] === '' ? '' : null, $update['value'] === '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':value', $update['value'], PDO::PARAM_STR);
                }
                $stmt->bindValue(':id', (int)$update['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            $pdo->commit();
            return [true, [], count($updates)];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return [false, ['Не удалось сохранить настройки: ' . $e->getMessage()], 0];
        }
    }
}

if (!function_exists('settings_get_value')) {
    function settings_get_value(PDO $pdo, $key, $default = null)
    {
        static $cache = [];
        $cacheKey = (string)$key;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :key LIMIT 1');
        $stmt->execute([':key' => $cacheKey]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $cache[$cacheKey] = $default;
        }
        return $cache[$cacheKey] = $value;
    }
}

if (!function_exists('settings_rows_by_key')) {
    function settings_rows_by_key(array $rows)
    {
        $map = [];
        foreach ($rows as $row) {
            $key = isset($row['key']) ? (string)$row['key'] : '';
            if ($key !== '') {
                $map[$key] = $row;
            }
        }
        return $map;
    }
}

if (!function_exists('settings_decode_json_value')) {
    function settings_decode_json_value($raw, array $fallback = [])
    {
        if ($raw === null || $raw === '') {
            return $fallback;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string)$raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $fallback;
        }
        return $decoded;
    }
}

if (!function_exists('settings_upsert_value')) {
    function settings_upsert_value(PDO $pdo, $key, $label, $value, $type = 'string', $category = 'system', $description = null, $isEditable = 1, $sortOrder = 100)
    {
        $existing = db_fetch_one('SELECT id FROM settings WHERE `key` = ? LIMIT 1', [$key]);
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE settings SET label = :label, type = :type, category = :category, description = :description, is_editable = :is_editable, sort_order = :sort_order WHERE id = :id');
            $stmt->bindValue(':label', (string)$label, PDO::PARAM_STR);
            $stmt->bindValue(':type', (string)$type, PDO::PARAM_STR);
            $stmt->bindValue(':category', (string)$category, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':is_editable', (int)$isEditable, PDO::PARAM_INT);
            $stmt->bindValue(':sort_order', (int)$sortOrder, PDO::PARAM_INT);
            $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
            return $stmt->execute();
        }

        $stmt = $pdo->prepare('INSERT INTO settings (`key`, label, value, type, options, category, description, is_editable, sort_order) VALUES (:key, :label, :value, :type, NULL, :category, :description, :is_editable, :sort_order)');
        $stmt->bindValue(':key', (string)$key, PDO::PARAM_STR);
        $stmt->bindValue(':label', (string)$label, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':type', (string)$type, PDO::PARAM_STR);
        $stmt->bindValue(':category', (string)$category, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_editable', (int)$isEditable, PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', (int)$sortOrder, PDO::PARAM_INT);
        return $stmt->execute();
    }
}