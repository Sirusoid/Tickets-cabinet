<?php
// includes/payment/bcc.php
// Хелперы для интеграции с BCC e-Commerce WEBVIEW

if (!function_exists('settings_get_value') && file_exists(__DIR__ . '/../settings_manager.php')) {
    require_once __DIR__ . '/../settings_manager.php';
}

if (!function_exists('bcc_config')) {
    function bcc_config(?PDO $pdo = null): array
    {
        $defaults = [
            'enabled' => false,
            'mode' => 'test',
            'merchant' => '00000001',
            'terminal' => '88888881',
            'merch_name' => 'ZHAS SAHNA THEATER',
            'mac_key' => '',
            'test_url' => 'https://test3ds.bcc.kz:5445/cgi-bin/cgi_link',
            'prod_url' => 'https://3dsecure.bcc.kz/webview',
            'backref_url' => defined('TILDA_WIDGET_ORIGIN') ? (string)TILDA_WIDGET_ORIGIN : '',
            'backref_path' => '/payment/bcc/return.php',
            'notify_url' => defined('PUBLIC_BASE_URL') ? (string)PUBLIC_BASE_URL : '',
            'notify_port' => '443',
            'notify_method' => 'POST',
            'notify_basic_auth_enabled' => false,
            'notify_tls12' => true,
            'notify_virtual_host' => true,
            'notify_path' => '/payment/bcc/notify.php',
            'notify_login' => '',
            'notify_password' => '',
        ];

        if (!$pdo instanceof PDO) {
            return $defaults;
        }

        $dbValues = null;
        if (!function_exists('settings_get_value')) {
            try {
                $stmt = $pdo->prepare('SELECT `key`, value FROM settings WHERE `key` LIKE :prefix');
                $stmt->execute([':prefix' => 'payments.bcc_%']);
                $dbValues = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $dbValues[$row['key']] = $row['value'];
                }
            } catch (Throwable $e) {
                return $defaults;
            }
        }

        $map = [
            'enabled' => 'payments.bcc_enabled',
            'mode' => 'payments.bcc_mode',
            'merchant' => 'payments.bcc_merchant',
            'terminal' => 'payments.bcc_terminal',
            'merch_name' => 'payments.bcc_merch_name',
            'mac_key' => 'payments.bcc_mac_key',
            'test_url' => 'payments.bcc_test_url',
            'prod_url' => 'payments.bcc_prod_url',
            'backref_url' => 'payments.bcc_backref_url',
            'backref_path' => 'payments.bcc_backref_path',
            'notify_url' => 'payments.bcc_notify_url',
            'notify_path' => 'payments.bcc_notify_path',
            'notify_login' => 'payments.bcc_notify_login',
            'notify_password' => 'payments.bcc_notify_password',
        ];

        foreach ($map as $key => $settingKey) {
            if ($dbValues !== null) {
                $val = $dbValues[$settingKey] ?? null;
            } else {
                $val = settings_get_value($pdo, $settingKey, null);
            }
            if ($val !== null && (string)$val !== '') {
                if ($key === 'enabled') {
                    $defaults[$key] = in_array(strtolower(trim((string)$val)), ['1', 'true', 'yes', 'on'], true);
                } else {
                    $defaults[$key] = $val;
                }
            }
        }

        // Notification-specific settings live in Settings -> Notifications.
        // Keep the old payments.bcc_* values as fallback for compatibility.
        $notificationMap = [
            'notify_url' => 'notifications.bcc_notify_url',
            'notify_port' => 'notifications.bcc_notify_port',
            'notify_method' => 'notifications.bcc_notify_method',
            'notify_basic_auth_enabled' => 'notifications.bcc_basic_auth_enabled',
            'notify_login' => 'notifications.bcc_notify_login',
            'notify_password' => 'notifications.bcc_notify_password',
            'notify_tls12' => 'notifications.bcc_tls12',
            'notify_virtual_host' => 'notifications.bcc_virtual_host',
        ];
        foreach ($notificationMap as $key => $settingKey) {
            $val = function_exists('settings_get_value')
                ? settings_get_value($pdo, $settingKey, null)
                : ($dbValues[$settingKey] ?? null);
            if ($val !== null && (string)$val !== '') {
                if (in_array($key, ['notify_basic_auth_enabled', 'notify_tls12', 'notify_virtual_host'], true)) {
                    $defaults[$key] = in_array(strtolower(trim((string)$val)), ['1', 'true', 'yes', 'on'], true);
                } else {
                    $defaults[$key] = $val;
                }
            }
        }

        return $defaults;
    }
}

if (!function_exists('bcc_is_enabled')) {
    function bcc_is_enabled(?PDO $pdo = null): bool
    {
        $cfg = bcc_config($pdo);
        return !empty($cfg['enabled']) && in_array(strtolower(trim((string)$cfg['mode'])), ['test', 'production', 'prod'], true);
    }
}

if (!function_exists('bcc_endpoint')) {
    function bcc_endpoint(array $cfg): string
    {
        $mode = strtolower(trim((string)($cfg['mode'] ?? 'test')));
        if (in_array($mode, ['production', 'prod'], true)) {
            return trim((string)($cfg['prod_url'] ?? 'https://3dsecure.bcc.kz/webview'));
        }
        return trim((string)($cfg['test_url'] ?? 'https://test3ds.bcc.kz:5445/cgi-bin/cgi_link'));
    }
}

if (!function_exists('bcc_base_url')) {
    function bcc_base_url(): string
    {
        // Публичные URL-ы для эквайринга должны вести на домен, доступный покупателям
        if (defined('PUBLIC_BASE_URL')) {
            $url = trim((string)PUBLIC_BASE_URL);
            if ($url !== '') return $url;
        }
        if (defined('BASE_URL')) {
            $url = trim((string)BASE_URL);
            if ($url !== '') return $url;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('bcc_backref_url')) {
    function bcc_backref_url(array $cfg): string
    {
        $path = defined('PUBLIC_BCC_BACKREF_PATH') ? (string)PUBLIC_BCC_BACKREF_PATH : trim((string)($cfg['backref_path'] ?? '/payment/bcc/return.php'));
        $url = trim((string)($cfg['backref_url'] ?? ''));
        if ($url === '' && defined('PUBLIC_BCC_BACKREF_URL')) {
            $url = trim((string)PUBLIC_BCC_BACKREF_URL);
        }
        if ($url === '') {
            $url = bcc_base_url();
        }
        if (str_starts_with($url, 'https://') && !str_contains($url, ':')) {
            $url .= ':443';
        }
        return $url . '/' . ltrim($path, '/');
    }
}

if (!function_exists('bcc_notify_url')) {
    function bcc_notify_url(array $cfg): string
    {
        $path = trim((string)($cfg['notify_path'] ?? '/payment/bcc/notify.php'));
        $url = trim((string)($cfg['notify_url'] ?? ''));
        if ($url === '' && defined('PUBLIC_BCC_NOTIFY_URL')) {
            $url = trim((string)PUBLIC_BCC_NOTIFY_URL);
        }
        if ($url === '') {
            $url = bcc_base_url();
        }
        $url = rtrim($url, '/');
        $parts = parse_url($url);
        if (!isset($parts['port'])) {
            $configuredPort = (int)($cfg['notify_port'] ?? 0);
            if ($configuredPort > 0) {
                $url .= ':' . $configuredPort;
            } elseif (($parts['scheme'] ?? '') === 'https') {
                $url .= ':443';
            }
        }
        return $url . '/' . ltrim($path, '/');
    }
}

if (!function_exists('bcc_generate_order')) {
    function bcc_generate_order(): string
    {
        // ORDER: 6-32 цифр. Используем timestamp + случайный суффикс.
        return date('YmdHis') . substr(str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT), -4);
    }
}

if (!function_exists('bcc_generate_merch_rn_id')) {
    function bcc_generate_merch_rn_id(int $length = 16): string
    {
        $chars = 'ABCDEF0123456789';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('bcc_generate_nonce')) {
    function bcc_generate_nonce(int $length = 32): string
    {
        $chars = 'ABCDEF0123456789';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('bcc_timestamp')) {
    function bcc_timestamp(): string
    {
        return gmdate('YmdHis');
    }
}

if (!function_exists('bcc_build_mac_string')) {
    /**
     * Строка источника для MAC TRTYPE=1.
     * Формат: <len>AMOUNT<len>CURRENCY<len>ORDER<len>MERCHANT<len>TERMINAL<len>MERCH_GMT<len>TIMESTAMP<len>TRTYPE<len>NONCE
     */
    function bcc_build_mac_string(array $fields): string
    {
        $keys = ['AMOUNT', 'CURRENCY', 'ORDER', 'MERCHANT', 'TERMINAL', 'MERCH_GMT', 'TIMESTAMP', 'TRTYPE', 'NONCE'];
        $out = '';
        foreach ($keys as $k) {
            $val = (string)($fields[$k] ?? '');
            $out .= strlen($val) . $val;
        }
        return $out;
    }
}

if (!function_exists('bcc_sign')) {
    function bcc_sign(string $macString, string $key): string
    {
        if ($key === '') {
            return '';
        }
        return strtoupper(hash_hmac('sha1', $macString, hex2bin($key)));
    }
}

if (!function_exists('bcc_build_payment_form')) {
    /**
     * Формирует параметры формы для отправки в BCC.
     *
     * @param array $cfg конфигурация из bcc_config
     * @param string $order ORDER (уникальный)
     * @param string $merchRnId MERCH_RN_ID
     * @param int $amountCents сумма в тиынах (1 тенге = 100 тиын)
     * @param string $desc описание транзакции
     * @param string $clientIp IP клиента
     * @return array поля формы, готовые к отправке
     */
    function bcc_build_payment_form(array $cfg, string $order, string $merchRnId, int $amountCents, string $desc = '', string $clientIp = '0.0.0.0', string $customerPhone = ''): array
    {
        $merchant = trim((string)($cfg['merchant'] ?? '00000001'));
        $terminal = trim((string)($cfg['terminal'] ?? '88888881'));
        $merchName = trim((string)($cfg['merch_name'] ?? 'ZHAS SAHNA THEATER'));
        $macKey = trim((string)($cfg['mac_key'] ?? ''));

        $amount = number_format($amountCents / 100, 2, '.', '');
        $currency = '398';
        $merchGmt = '0';
        $trtype = '1';
        $timestamp = bcc_timestamp();
        $nonce = bcc_generate_nonce();
        $backref = bcc_backref_url($cfg);
        $notifyUrl = bcc_notify_url($cfg);
        $lang = 'ru';

        $desc = trim($desc) !== '' ? trim($desc) : 'Ticket purchase';

        $macFields = [
            'AMOUNT' => $amount,
            'CURRENCY' => $currency,
            'ORDER' => $order,
            'MERCHANT' => $merchant,
            'TERMINAL' => $terminal,
            'MERCH_GMT' => $merchGmt,
            'TIMESTAMP' => $timestamp,
            'TRTYPE' => $trtype,
            'NONCE' => $nonce,
        ];

        $macString = bcc_build_mac_string($macFields);
        $pSign = bcc_sign($macString, $macKey);

        $subscriber = '7000000000';
        if ($customerPhone !== '') {
            $phoneDigitsOnly = preg_replace('/\D+/', '', $customerPhone);
            if (strlen($phoneDigitsOnly) >= 10) {
                if (str_starts_with($phoneDigitsOnly, '7') || str_starts_with($phoneDigitsOnly, '8')) {
                    $subscriber = substr($phoneDigitsOnly, 1);
                } else {
                    $subscriber = $phoneDigitsOnly;
                }
                $subscriber = substr($subscriber, 0, 10);
                $subscriber = str_pad($subscriber, 10, '0', STR_PAD_LEFT);
            }
        }

        $mInfo = base64_encode(json_encode([
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'mobilePhone' => [
                'cc' => '7',
                'subscriber' => $subscriber,
            ],
            'billAddrLine1' => 'Almaty',
        ], JSON_UNESCAPED_UNICODE));

        return [
            'action' => bcc_endpoint($cfg),
            'fields' => [
                'AMOUNT' => $amount,
                'CURRENCY' => $currency,
                'ORDER' => $order,
                'MERCH_RN_ID' => $merchRnId,
                'DESC' => $desc,
                'MERCHANT' => $merchant,
                'MERCH_NAME' => $merchName,
                'TERMINAL' => $terminal,
                'TIMESTAMP' => $timestamp,
                'MERCH_GMT' => $merchGmt,
                'TRTYPE' => $trtype,
                'BACKREF' => $backref,
                'LANG' => $lang,
                'NONCE' => $nonce,
                'P_SIGN' => $pSign,
                'MK_TOKEN' => 'MERCH',
                'NOTIFY_URL' => $notifyUrl,
                'CLIENT_IP' => $clientIp,
                'M_INFO' => $mInfo,
            ],
            'mac_string' => $macString,
        ];
    }
}

if (!function_exists('bcc_validate_response_signature')) {
    /**
     * Проверяет подпись ответа от BCC (BACKREF/NOTIFY).
     * Важно: точный набор полей для ответа нужно уточнить у банка.
     * Здесь используем безопасную заглушку, которая логирует и возвращает true,
     * пока нет точного описания ответа.
     */
    function bcc_validate_response_signature(array $response, array $cfg): bool
    {
        $macKey = trim((string)($cfg['mac_key'] ?? ''));
        if ($macKey === '') {
            return false;
        }

        // TODO: уточнить у банка порядок полей и алгоритм MAC для ответа.
        // Пока считаем ответ валидным, если есть подписуемые поля.
        if (isset($response['P_SIGN']) && $response['P_SIGN'] !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('bcc_parse_response')) {
    function bcc_parse_response(array $data): array
    {
        $action = isset($data['ACTION']) ? trim((string)$data['ACTION']) : '';
        $rc = isset($data['RC']) ? trim((string)$data['RC']) : '';
        $order = isset($data['ORDER']) ? trim((string)$data['ORDER']) : '';
        $rrn = isset($data['RRN']) ? trim((string)$data['RRN']) : '';
        $approval = isset($data['APPROVAL_CODE']) ? trim((string)$data['APPROVAL_CODE']) : '';

        // ACTION=0 => успешная транзакция; RC должен отсутствовать или быть 00
        $isSuccess = ($action === '0');
        if ($isSuccess && $rc !== '' && $rc !== '00') {
            $isSuccess = false;
        }

        return [
            'success' => $isSuccess,
            'action' => $action,
            'rc' => $rc,
            'order' => $order,
            'rrn' => $rrn,
            'approval_code' => $approval,
            'raw' => $data,
        ];
    }
}
