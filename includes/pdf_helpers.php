<?php
// includes/pdf_helpers.php
// Хэлперы для генерации PDF билета, сохранения и очистки архива.

// -----------------------------
// Путь и утилиты
// -----------------------------
if (!function_exists('ticket_pdf_dir')) {
    function ticket_pdf_dir() {
        $projectRoot = dirname(__DIR__);
        return $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tickets';
    }
}

if (!function_exists('ticket_pdf_file_path')) {
    function ticket_pdf_file_path($ticket_uid) {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$ticket_uid);
        $safe = trim($safe, '_-');
        if ($safe === '') {
            $safe = 'ticket';
        }
        return ticket_pdf_dir() . DIRECTORY_SEPARATOR . $safe . '.pdf';
    }
}

if (!function_exists('ticket_pdf_public_url')) {
    function ticket_pdf_public_url($ticket_uid) {
        $safe = rawurlencode(preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$ticket_uid));
        return '/uploads/tickets/' . $safe . '.pdf';
    }
}

if (!function_exists('ensure_ticket_pdf_directory')) {
    function ensure_ticket_pdf_directory() {
        $dir = ticket_pdf_dir();
        if (is_dir($dir)) {
            return is_writable($dir);
        }
        return @mkdir($dir, 0755, true) && is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('find_vendor_autoload_path')) {
    function find_vendor_autoload_path() {
        $paths = [];

        if (defined('APP_ROOT') && APP_ROOT) {
            $paths[] = rtrim(APP_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        }

        $paths[] = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $paths[] = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        $base = realpath(__DIR__);
        for ($i = 0; $i < 5 && $base; $i++) {
            $paths[] = $base . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            $parent = dirname($base);
            if ($parent === $base) {
                break;
            }
            $base = $parent;
        }

        foreach (array_unique($paths) as $path) {
            if ($path && is_file($path)) {
                return realpath($path) ?: $path;
            }
        }

        return null;
    }
}

if (!function_exists('ticket_pdf_autoload')) {
    function ticket_pdf_autoload() {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }

        $autoloadPath = find_vendor_autoload_path();
        if (!$autoloadPath) {
            return $loaded = false;
        }

        try {
            require_once $autoloadPath;
        } catch (Throwable $e) {
            error_log('ticket_pdf_autoload require_once failed: ' . $e->getMessage());
            return $loaded = false;
        }

        $loaded = class_exists('\Dompdf\Dompdf', true);
        return $loaded;
    }
}

if (!function_exists('ticket_pdf_backend_binary')) {
    function ticket_pdf_backend_binary() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!function_exists('shell_exec')) {
            return $cached = false;
        }
        if (stripos(PHP_OS, 'WIN') === 0) {
            $check = 'where wkhtmltopdf 2>nul';
        } else {
            $check = 'command -v wkhtmltopdf 2>/dev/null';
        }
        $out = trim(@shell_exec($check));
        if ($out !== '') {
            $lines = preg_split('/\r?\n/', trim($out));
            return $cached = $lines[0];
        }
        return $cached = false;
    }
}

if (!function_exists('ticket_pdf_backend_available')) {
    function ticket_pdf_backend_available() {
        return ticket_pdf_autoload() || ticket_pdf_backend_binary() !== false;
    }
}

// -----------------------------
// QR код: Endroid / legacy
// -----------------------------
if (!function_exists('ticket_qr_library_path')) {
    function ticket_qr_library_path() {
        if (defined('APP_ROOT') && APP_ROOT) {
            $rootPath = rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR);
            $fromAppRoot = $rootPath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'phpqrcode-master' . DIRECTORY_SEPARATOR . 'qrlib.php';
            if (is_file($fromAppRoot)) {
                return $fromAppRoot;
            }
        }
        return __DIR__ . DIRECTORY_SEPARATOR . 'phpqrcode-master' . DIRECTORY_SEPARATOR . 'qrlib.php';
    }
}

if (!function_exists('ticket_qr_endroid_png_data')) {
    function ticket_qr_endroid_png_data($payload, $size = 4, $margin = 2) {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return null;
        }

        $autoloadPath = find_vendor_autoload_path();
        if ($autoloadPath && !class_exists('\\Endroid\\QrCode\\Writer\\PngWriter', false)) {
            try {
                require_once $autoloadPath;
            } catch (Throwable $e) {
                error_log('ticket_qr_endroid_png_data autoload failed: ' . $e->getMessage());
                return null;
            }
        }

        if (!class_exists('\\Endroid\\QrCode\\Writer\\PngWriter')) {
            return null;
        }

        $sizePx = max(120, (int)$size * 35);
        $marginPx = max(0, (int)$margin);

        try {
            if (!class_exists('\\Endroid\\QrCode\\QrCode')) {
                return null;
            }

            $writerClass = '\\Endroid\\QrCode\\Writer\\PngWriter';
            $writer = new $writerClass();

            $qrCodeClass = '\\Endroid\\QrCode\\QrCode';
            $encodingClass = '\\Endroid\\QrCode\\Encoding\\Encoding';
            $errorLevel = defined('\\Endroid\\QrCode\\ErrorCorrectionLevel::Low')
                ? constant('\\Endroid\\QrCode\\ErrorCorrectionLevel::Low')
                : null;

            if (class_exists($encodingClass) && $errorLevel !== null) {
                $encoding = new $encodingClass('UTF-8');
                $qrCode = new $qrCodeClass($payload, $encoding, $errorLevel, $sizePx, $marginPx);
            } else {
                $qrCode = new $qrCodeClass($payload);
            }

            $result = $writer->write($qrCode);

            if (is_object($result) && method_exists($result, 'getString')) {
                $png = $result->getString();
            } elseif (is_string($result)) {
                $png = $result;
            } else {
                $png = null;
            }

            if (!is_string($png) || $png === '') {
                return null;
            }

            return $png;
        } catch (Throwable $e) {
            error_log('ticket_qr_endroid_png_data error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('ticket_qr_legacy_png_data')) {
    function ticket_qr_legacy_png_data($payload, $size = 4, $margin = 2) {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return null;
        }

        $qrLib = ticket_qr_library_path();
        if (!is_file($qrLib)) {
            return null;
        }

        $prevReporting = error_reporting();
        $prevDisplay = ini_get('display_errors');

        // Legacy phpqrcode can emit deprecations on modern PHP; suppress non-fatal noise.
        error_reporting($prevReporting & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
        @ini_set('display_errors', '0');
        set_error_handler(function ($severity) {
            if (($severity & (E_DEPRECATED | E_USER_DEPRECATED | E_WARNING | E_NOTICE | E_STRICT)) !== 0) {
                return true;
            }
            return false;
        });

        try {
            if (!class_exists('QRcode', false)) {
                require_once $qrLib;
            }
            if (!class_exists('QRcode', false)) {
                return null;
            }

            $legacyEcLevel = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 'M';
            ob_start();
            QRcode::png($payload, null, $legacyEcLevel, max(2, (int)$size), max(0, (int)$margin));
            $png = ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('ticket_qr_legacy_png_data error: ' . $e->getMessage());
            return null;
        } finally {
            restore_error_handler();
            error_reporting($prevReporting);
            if ($prevDisplay !== false) {
                @ini_set('display_errors', (string)$prevDisplay);
            }
        }

        if (!is_string($png) || $png === '') {
            return null;
        }

        return $png;
    }
}

if (!function_exists('ticket_qr_file_path')) {
    function ticket_qr_file_path($ticket_uid) {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$ticket_uid);
        $safe = trim($safe, '_-');
        if ($safe === '') {
            $safe = 'ticket';
        }
        return ticket_pdf_dir() . DIRECTORY_SEPARATOR . $safe . '.qr.png';
    }
}

if (!function_exists('ticket_qr_generate_png_file')) {
    function ticket_qr_generate_png_file($payload, $size = 4, $margin = 2) {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return null;
        }
        if (!ensure_ticket_pdf_directory()) {
            return null;
        }

        $png = ticket_qr_endroid_png_data($payload, $size, $margin);
        if (!$png) {
            $png = ticket_qr_legacy_png_data($payload, $size, $margin);
        }
        if (!$png) {
            return null;
        }

        $path = ticket_qr_file_path($payload);
        try {
            if (@file_put_contents($path, $png) === false) {
                return null;
            }
            if (!is_file($path) || filesize($path) <= 0) {
                return null;
            }
            return $path;
        } catch (Throwable $e) {
            error_log('ticket_qr_generate_png_file error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('ticket_qr_png_data_uri')) {
    function ticket_qr_png_data_uri($payload, $size = 4, $margin = 2) {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return null;
        }

        $png = ticket_qr_endroid_png_data($payload, $size, $margin);
        if (!$png) {
            $png = ticket_qr_legacy_png_data($payload, $size, $margin);
        }

        if (!is_string($png) || $png === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }
}

if (!function_exists('ticket_logo_data_uri')) {
    function ticket_logo_data_uri() {
        $roots = [];
        if (defined('APP_ROOT') && APP_ROOT) {
            $roots[] = rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR);
        }
        $roots[] = dirname(__DIR__);

        $candidates = [];
        foreach (array_unique($roots) as $base) {
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.jpg';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.jpeg';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.webp';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.jpg';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.jpeg';
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.webp';
        }

        foreach ($candidates as $path) {
            if (!is_file($path) || filesize($path) <= 0) {
                continue;
            }

            $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
            $mime = 'image/png';
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $mime = 'image/jpeg';
            } elseif ($ext === 'webp') {
                $mime = 'image/webp';
            }

            $raw = @file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($raw);
        }

        return null;
    }
}

// -----------------------------
// Очистка PDF для события / сеанса
// -----------------------------
if (!function_exists('tickets_pdf_cleanup_for_event')) {
    function tickets_pdf_cleanup_for_event($event_id) {
        global $pdo;
        if (empty($event_id) || !isset($pdo) || !($pdo instanceof PDO)) {
            return [];
        }
        $deleted = [];
        try {
            $stmt = $pdo->prepare("SELECT ticket_uid FROM tickets WHERE event_id = :event_id");
            $stmt->execute([':event_id' => $event_id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = $row['ticket_uid'] ?? '';
                if ($uid === '') {
                    continue;
                }
                $file = ticket_pdf_file_path($uid);
                if (is_file($file) && @unlink($file)) {
                    $deleted[] = $file;
                }
                $qr = ticket_qr_file_path($uid);
                if (is_file($qr)) {
                    @unlink($qr);
                }
            }
        } catch (Throwable $e) {
            error_log('tickets_pdf_cleanup_for_event error: ' . $e->getMessage());
        }
        return $deleted;
    }
}

if (!function_exists('tickets_pdf_cleanup_for_schedule')) {
    function tickets_pdf_cleanup_for_schedule($schedule_id) {
        global $pdo;
        if (empty($schedule_id) || !isset($pdo) || !($pdo instanceof PDO)) {
            return [];
        }
        $deleted = [];
        try {
            $stmt = $pdo->prepare("SELECT ticket_uid FROM tickets WHERE schedule_id = :schedule_id");
            $stmt->execute([':schedule_id' => $schedule_id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = $row['ticket_uid'] ?? '';
                if ($uid === '') {
                    continue;
                }
                $file = ticket_pdf_file_path($uid);
                if (is_file($file) && @unlink($file)) {
                    $deleted[] = $file;
                }
            }
        } catch (Throwable $e) {
            error_log('tickets_pdf_cleanup_for_schedule error: ' . $e->getMessage());
        }
        return $deleted;
    }
}

// -----------------------------
// Вспомогательные тексты скидок
// -----------------------------
if (!function_exists('ticket_discount_summary_text')) {
    function ticket_discount_summary_text(array $ticket) {
        $payloadRaw = isset($ticket['tx_payload']) ? (string)$ticket['tx_payload'] : '';
        if ($payloadRaw === '') {
            return '';
        }
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            return '';
        }

        // First, try to find per-seat discount in payload->seats_final
        if (isset($payload['seats_final']) && is_array($payload['seats_final'])) {
            $seatIdentifier = isset($ticket['seat_identifier']) ? (string)$ticket['seat_identifier'] : '';
            $found = null;
            foreach ($payload['seats_final'] as $s) {
                if (!is_array($s)) continue;
                // match by identifier if present
                if ($seatIdentifier !== '' && isset($s['identifier']) && (string)$s['identifier'] === $seatIdentifier) {
                    $found = $s;
                    break;
                }
            }
            // If not found by identifier, try to match by seat_id if available in ticket and payload
            if ($found === null && isset($ticket['seat_id'])) {
                foreach ($payload['seats_final'] as $s) {
                    if (!is_array($s)) continue;
                    if (isset($s['seat_id']) && (string)$s['seat_id'] === (string)$ticket['seat_id']) {
                        $found = $s;
                        break;
                    }
                }
            }
            if ($found !== null) {
                $disc = 0.0;
                if (isset($found['discount']) && is_numeric($found['discount'])) {
                    $disc = (float)$found['discount'];
                } elseif (isset($found['discount_cents']) && is_numeric($found['discount_cents'])) {
                    $disc = ((int)$found['discount_cents']) / 100.0;
                } else {
                    // fallback compute from original and final if present
                    if (isset($found['original_price']) && isset($found['final_price']) && is_numeric($found['original_price']) && is_numeric($found['final_price'])) {
                        $disc = max(0.0, round((float)$found['original_price'] - (float)$found['final_price'], 2));
                    }
                }
                if ($disc > 0) {
                    $parts = [];
                    if (isset($payload['discount']) && is_array($payload['discount']) && isset($payload['discount']['auto_percent']) && is_numeric($payload['discount']['auto_percent']) && (float)$payload['discount']['auto_percent'] > 0) {
                        // only percent value (without "по типу билета")
                        $parts[] = number_format((float)$payload['discount']['auto_percent'], 0, '.', '') . '%';
                    }
                    if (isset($payload['discount']) && is_array($payload['discount']) && !empty($payload['discount']['custom_type']) && $payload['discount']['custom_type'] !== 'none') {
                        if ($payload['discount']['custom_type'] === 'percent' && isset($payload['discount']['custom_value']) && is_numeric($payload['discount']['custom_value'])) {
                            $parts[] = 'ручная ' . number_format((float)$payload['discount']['custom_value'], 0, '.', '') . '%';
                        } elseif ($payload['discount']['custom_type'] === 'fixed' && isset($payload['discount']['custom_value']) && is_numeric($payload['discount']['custom_value'])) {
                            $parts[] = 'ручная ' . number_format((float)$payload['discount']['custom_value'], 0, '.', '') . ' тг';
                        }
                    }
                    $tail = !empty($parts) ? (' (' . implode(', ', $parts) . ')') : '';
                    return 'СКИДКА / ЖЕҢІЛДІК: ' . number_format($disc, 0, '.', '') . ' тг' . $tail;
                }
            }
        }

        // Fallback: use overall discount from payload->discount if present
        if (!isset($payload['discount']) || !is_array($payload['discount'])) {
            return '';
        }
        $discount = $payload['discount'];
        $total = isset($discount['total_discount']) && is_numeric($discount['total_discount'])
            ? (float)$discount['total_discount']
            : 0.0;
        if ($total <= 0) {
            return '';
        }
        $parts = [];
        if (isset($discount['auto_percent']) && is_numeric($discount['auto_percent']) && (float)$discount['auto_percent'] > 0) {
            // only percent value (without "по типу билета")
            $parts[] = number_format((float)$discount['auto_percent'], 0, '.', '') . '%';
        }
        if (!empty($discount['custom_type']) && $discount['custom_type'] !== 'none') {
            if ($discount['custom_type'] === 'percent' && isset($discount['custom_value']) && is_numeric($discount['custom_value'])) {
                $parts[] = 'ручная ' . number_format((float)$discount['custom_value'], 0, '.', '') . '%';
            } elseif ($discount['custom_type'] === 'fixed' && isset($discount['custom_value']) && is_numeric($discount['custom_value'])) {
                $parts[] = 'ручная ' . number_format((float)$discount['custom_value'], 0, '.', '') . ' тг';
            }
        }
        $tail = !empty($parts) ? (' (' . implode(', ', $parts) . ')') : '';
        return 'СКИДКА / ЖЕҢІЛДІК: ' . number_format($total, 0, '.', '') . ' тг' . $tail;
    }
}

// -----------------------------
// Хелпер для рендера: создаёт HTML для генерации билета, использует в приоритете полученные pdf_field, а потом только tx_payload, если pdf_fields не существует
// -----------------------------
if (!function_exists('ticket_pdf_render_html')) {
    function ticket_pdf_render_html(array $ticket) {
        // Basic sanitization
        $event_title = htmlspecialchars($ticket['event_title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $session_time = !empty($ticket['schedule_start']) ? date('d.m.Y H:i', strtotime($ticket['schedule_start'])) : '';
        $seat_raw = trim((string)($ticket['seat_label'] ?? $ticket['seat_identifier'] ?? ''));
        $seat = htmlspecialchars($seat_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ticket_uid = htmlspecialchars($ticket['ticket_uid'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // paid price (final) — stored in ticket.price (decimal)
        $paid_price_value = null;
        if (isset($ticket['price']) && is_numeric($ticket['price'])) {
            $paid_price_value = (float)$ticket['price'];
        }

        // tx_payload raw (may be present)
        $tx_payload_raw = isset($ticket['tx_payload']) ? (string)$ticket['tx_payload'] : '';
        $tx_payload = null;
        if ($tx_payload_raw !== '') {
            $decoded = json_decode($tx_payload_raw, true);
            if (is_array($decoded)) $tx_payload = $decoded;
        }

        // --- PRIORITIZE pdf_fields ---
        // pdf_fields expected keys: price_label, discount_label_percent, payment_label
        $pdf_price_label = null;
        $pdf_discount_percent = null;
        $pdf_payment_label = null;

        if (isset($ticket['pdf_fields']) && is_array($ticket['pdf_fields'])) {
            $pf = $ticket['pdf_fields'];
            if (isset($pf['price_label'])) $pdf_price_label = is_numeric($pf['price_label']) ? (float)$pf['price_label'] : null;
            if (isset($pf['discount_label_percent'])) $pdf_discount_percent = is_numeric($pf['discount_label_percent']) ? (int)$pf['discount_label_percent'] : null;
            if (isset($pf['payment_label'])) $pdf_payment_label = is_numeric($pf['payment_label']) ? (float)$pf['payment_label'] : null;

            // Support alternate meta keys that may be present in meta map
            if ($pdf_price_label === null && isset($pf['pdf_price'])) $pdf_price_label = is_numeric($pf['pdf_price']) ? (float)$pf['pdf_price'] : null;
            if ($pdf_discount_percent === null && isset($pf['pdf_discount_percent'])) $pdf_discount_percent = is_numeric($pf['pdf_discount_percent']) ? (int)$pf['pdf_discount_percent'] : null;
            if ($pdf_payment_label === null && isset($pf['pdf_payment'])) $pdf_payment_label = is_numeric($pf['pdf_payment']) ? (float)$pf['pdf_payment'] : null;

            // Also accept 'price' and 'discount_percent' as fallbacks inside pdf_fields
            if ($pdf_price_label === null && isset($pf['price'])) $pdf_price_label = is_numeric($pf['price']) ? (float)$pf['price'] : null;
            if ($pdf_discount_percent === null && isset($pf['discount_percent'])) $pdf_discount_percent = is_numeric($pf['discount_percent']) ? (int)$pf['discount_percent'] : null;
        }

        // If pdf_fields provided, we DO NOT override them with tx_payload values.
        // Only if pdf_fields are absent, fall back to tx_payload.
        if ($pdf_price_label === null && is_array($tx_payload) && isset($tx_payload['base_total'])) {
            $pdf_price_label = is_numeric($tx_payload['base_total']) ? (float)$tx_payload['base_total'] : null;
        }
        if ($pdf_discount_percent === null && is_array($tx_payload) && isset($tx_payload['discount']['auto_percent'])) {
            $pdf_discount_percent = is_numeric($tx_payload['discount']['auto_percent']) ? (int)$tx_payload['discount']['auto_percent'] : null;
        }
        if ($pdf_payment_label === null && is_array($tx_payload) && isset($tx_payload['final_total'])) {
            $pdf_payment_label = is_numeric($tx_payload['final_total']) ? (float)$tx_payload['final_total'] : null;
        }

        // Final fallbacks to ticket.price and ticket.discount
        if ($pdf_price_label === null) $pdf_price_label = $paid_price_value !== null ? (float)$paid_price_value : 0.0;
        if ($pdf_payment_label === null) $pdf_payment_label = $paid_price_value !== null ? (float)$paid_price_value : 0.0;
        if ($pdf_discount_percent === null) $pdf_discount_percent = isset($ticket['discount']) && is_numeric($ticket['discount']) ? (int)$ticket['discount'] : 0;

        // Compute discount amount display
        $discount_amount_display = 0.0;
        if ($pdf_price_label > 0 && $pdf_discount_percent > 0) {
            $discount_amount_display = round($pdf_price_label * ($pdf_discount_percent / 100.0), 2);
        } elseif ($pdf_price_label !== null && $paid_price_value !== null) {
            $discount_amount_display = round(max(0.0, $pdf_price_label - $paid_price_value), 2);
        }

        // Try to extract original price and paid price from tx_payload->seats_final if available (only if pdf_fields not present)
        $orig_price_value = null;
        if ($tx_payload !== null && isset($tx_payload['seats_final']) && is_array($tx_payload['seats_final'])) {
            $seatIdentifier = isset($ticket['seat_identifier']) ? (string)$ticket['seat_identifier'] : '';
            $found = null;
            foreach ($tx_payload['seats_final'] as $s) {
                if (!is_array($s)) continue;
                if ($seatIdentifier !== '' && isset($s['identifier']) && (string)$s['identifier'] === $seatIdentifier) {
                    $found = $s;
                    break;
                }
            }
            if ($found === null && isset($ticket['seat_id'])) {
                foreach ($tx_payload['seats_final'] as $s) {
                    if (!is_array($s)) continue;
                    if (isset($s['seat_id']) && (string)$s['seat_id'] === (string)$ticket['seat_id']) {
                        $found = $s;
                        break;
                    }
                }
            }
            if ($found !== null) {
                if (isset($found['original_price']) && is_numeric($found['original_price'])) {
                    $orig_price_value = (float)$found['original_price'];
                } elseif (isset($found['original_price_cents']) && is_numeric($found['original_price_cents'])) {
                    $orig_price_value = ((int)$found['original_price_cents']) / 100.0;
                }
                if (isset($found['final_price']) && is_numeric($found['final_price'])) {
                    $paid_price_value = (float)$found['final_price'];
                } elseif (isset($found['final_price_cents']) && is_numeric($found['final_price_cents'])) {
                    $paid_price_value = ((int)$found['final_price_cents']) / 100.0;
                }
            }
        }

        // If original price not found, try to use tx_payload base_total as last resort
        if ($orig_price_value === null && is_array($tx_payload) && isset($tx_payload['base_total']) && isset($tx_payload['seats_final']) && is_array($tx_payload['seats_final'])) {
            if (count($tx_payload['seats_final']) === 1) {
                $single = $tx_payload['seats_final'][0];
                if (isset($single['original_price']) && is_numeric($single['original_price'])) {
                    $orig_price_value = (float)$single['original_price'];
                }
            }
        }

        // Format displays
        $orig_price_display = $orig_price_value !== null ? number_format((float)$orig_price_value, 0, '.', '') . ' тг' : '—';
        $paid_price_display = $paid_price_value !== null ? number_format((float)$paid_price_value, 0, '.', '') . ' тг' : '—';

        // Discount summary text (uses tx_payload)
        $discountLine = ticket_discount_summary_text(['tx_payload' => $tx_payload_raw, 'seat_identifier' => $ticket['seat_identifier'] ?? null, 'seat_id' => $ticket['seat_id'] ?? null]);

        $channel_raw = trim((string)($ticket['channel'] ?? ''));
        $segment_raw = trim((string)($ticket['customer_segment'] ?? ''));
        $purchased_at = !empty($ticket['purchased_at']) ? date('d.m.Y H:i', strtotime($ticket['purchased_at'])) : '';

        // QR and logo
        $qrDataUri = ticket_qr_png_data_uri($ticket['ticket_uid'] ?? '');
        $logoDataUri = ticket_logo_data_uri();
        $qrFilePath = null;
        if (!$qrDataUri) {
            $qrFilePath = ticket_qr_generate_png_file($ticket['ticket_uid'] ?? '');
        }

        $event_title_quoted = '"' . $event_title . '"';

        // Category and channel maps
        $categoryMap = [
            'adult' => 'Взрослый / Ересек',
            'child' => 'Детский / Балалар',
            'children' => 'Детский / Балалар',
            'student' => 'Студенческий / Студенттік',
            'pensioner' => 'Пенсионный / Зейнеткерлік',
            'senior' => 'Пенсионный / Зейнеткерлік',
            'retired' => 'Пенсионный / Зейнеткерлік',
            'vip' => 'VIP'
        ];
        $channelMap = [
            'kassa' => 'Касса',
            'cash' => 'Касса',
            'online' => 'Онлайн',
			'web' => 'Онлайн',
            'card' => 'Карта',
            'terminal' => 'Терминал',
            'admin' => 'Админ / Әкімші'
        ];

        $segmentKey = mb_strtolower($segment_raw, 'UTF-8');
        $channelKey = mb_strtolower($channel_raw, 'UTF-8');
        $segmentRu = htmlspecialchars($categoryMap[$segmentKey] ?? ($segment_raw !== '' ? $segment_raw : '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $channelRu = htmlspecialchars($channelMap[$channelKey] ?? ($channel_raw !== '' ? $channel_raw : '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Row/seat parsing
        $rowValue = '—';
        $seatValue = '—';
        if ($seat_raw !== '') {
            if (preg_match('/(\d+)\s*[:\-\/]\s*(\d+)/u', $seat_raw, $m)) {
                $rowValue = $m[1];
                $seatValue = $m[2];
            } elseif (preg_match('/ряд\s*(\d+).*?место\s*(\d+)/ui', $seat_raw, $m)) {
                $rowValue = $m[1];
                $seatValue = $m[2];
            } elseif (preg_match('/(\d+)\D+(\d+)/u', $seat_raw, $m)) {
                $rowValue = $m[1];
                $seatValue = $m[2];
            } else {
                $seatValue = $seat;
            }
        }
        $rowValueEsc = htmlspecialchars((string)$rowValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $seatValueEsc = htmlspecialchars((string)$seatValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Rules and texts (unchanged)
        $rulesIntroRu = 'Вход на спектакль возможен только один раз и по одному билету. Не допускайте копирования электронного билета. При посещении мероприятия администрация вправе потребовать любой удостоверяющий документ для проверки возможности посещения и принадлежности билета.';
        $rulesIntroKz = 'Спектакльге кіру бір рет қана және бір билет бойынша мүмкін. Электрондық билетті көшіруге болмайды. Іс-шараға келген кезде әкімшілік келуге мүмкіндік бар-жоғын және билеттің тиесілігін тексеру үшін кез келген жеке басын куәландыратын құжатты талап етуге құқылы.';
        $rulesRu = [
            'Настоящий электронный билет является документом, подтверждающим ваше право на посещение указанного спектакля. Пожалуйста, храните его в безопасности и не передавайте его посторонним лицам и не публикуйте фотографии с видимым штрих-кодом билета в социальных сетях.',
            'Электронный билет является действительным на одного предъявителя.',
            'Электронный билет распечатывать не обязательно.',
            'При посещении спектакля вы обязаны соблюдать все правила и положения, установленные администрацией театра. Это включает в себя соблюдение правил безопасности, запрет на внос алкоголя и наркотиков, а также запрет на насилие, дискриминацию и любое другое неприемлемое поведение.',
            'Администрация театра оставляет за собой право вносить изменения в программу, дату, время проведения спектакля без предварительного уведомления. В случае таких изменений, администрация обязуется предоставить вам информацию о новых условиях посещения.',
            'В случае отмены, замены или переноса спектакля, решение о возврате денег принимает администрация театра. В случае таких изменений, администрация обязуется предоставить вам всю необходимую информацию в кратчайшие сроки.',
            'Администрация театра имеет право запретить съемку во время мероприятия или конфисковать оборудование в случае нарушения этого правила.',
            'Администрация театра вправе ограничить доступ на спектакль предъявителя билета, не соответствующих возрастным ограничениям без возмещения его стоимости.',
            'Для получения консультации по любым вопросам включая возврат и обмен билетов просим обращаться в поддержку клиентов: info@zhassahna.kz или +7 727 259 65 98, +7 776 711 78 78.',
            'Подробные правила, ограничения по срокам возврата и обмена билетов также изложены на нашем сайте и в публичной оферте: https://zhassahna.kz/agreement.'
        ];
        $rulesKz = [
            'Осы электрондық билет аталған спектакльге қатысу құқығыңызды растайтын құжат болып табылады. Оны қауіпсіз сақтаңыз, бөгде адамдарға бермеңіз және билет штрих-коды көрінетін фотосуреттерді әлеуметтік желілерге жарияламаңыз.',
            'Электрондық билет бір ғана ұстаушыға жарамды.',
            'Электрондық билетті басып шығару міндетті емес.',
            'Спектакльге барған кезде театр әкімшілігі белгілеген барлық қағидалар мен талаптарды сақтауға міндеттісіз. Бұған қауіпсіздік қағидаларын сақтау, алкоголь мен есірткіні кіргізуге тыйым салу, сондай-ақ зорлық-зомбылыққа, кемсітушілікке және кез келген өзге де қолайсыз мінез-құлыққа тыйым салу кіреді.',
            'Театр әкімшілігі спектакльдің бағдарламасына, күніне және басталу уақытына алдын ала ескертусіз өзгерістер енгізу құқығын өзінде қалдырады. Мұндай өзгерістер болған жағдайда әкімшілік сізге келудің жаңа шарттары туралы ақпарат беруге міндеттенеді.',
            'Спектакль тоқтатылған, ауыстырылған немесе кейінге қалдырылған жағдайда ақша қайтару туралы шешімді театр әкімшілігі қабылдайды. Мұндай өзгерістер болған жағдайда әкімшілік сізге барлық қажетті ақпаратты мүмкіндігінше қысқа мерзімде беруге міндеттенеді.',
            'Театр әкімшілігі іс-шара кезінде түсірілімге тыйым салуға немесе осы қағида бұзылған жағдайда жабдықты тәркілеуге құқылы.',
            'Театр әкімшілігі жасы бойынша шектеулерге сәйкес келмейтін билет ұстаушысының спектакльге кіруін билет құнын қайтармай шектеуге құқылы.',
            'Билеттерді қайтару және айырбастау, сондай-ақ кез келген басқа сұрақтар бойынша кеңес алу үшін клиенттерді қолдау қызметіне хабарласыңыз: info@zhassahna.kz немесе +7 727 259 65 98, +7 776 711 78 78.',
            'Толық қағидалар, қайтару және айырбастау мерзімдеріне қатысты шектеулер біздің сайтта және жария офертада да көрсетілген: https://zhassahna.kz/agreement.'
        ];

        // Build HTML (same layout as before, using pdf_price_label, pdf_discount_percent, pdf_payment_label)
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Билет ' . $ticket_uid . '</title>';
        $html .= '<style>';
        $html .= '@page { margin: 18px; }';
        $html .= 'body{margin:0;padding:0;font-family:"DejaVu Sans","DejaVu Sans Condensed",sans-serif;color:#1e2430;background:#fff;}';
        $html .= '.ticket-page{max-width:860px;margin:0 auto;background:#fff;border:1px solid #d7dee8;border-radius:14px;}';
        $html .= '.ticket-top{padding:16px 22px;background:#fff;border-bottom:1px solid #d7dee8;}';
        $html .= '.ticket-head{display:block;}';
        $html .= '.brand-line{display:block;}';
        $html .= '.brand-logo-wrap{display:block;}';
        $html .= '.ticket-logo{display:block;max-width:152px;max-height:152px;}';
        $html .= '.ticket-sub{margin-top:8px;font-size:12px;line-height:1.4;color:#4f647f;}';
        $html .= '.ticket-content{padding:18px 22px 20px 22px;}';
        $html .= '.ticket-main{display:table;width:100%;table-layout:fixed;border-spacing:0;}';
        $html .= '.ticket-main-left,.ticket-main-right{display:table-cell;vertical-align:top;}';
        $html .= '.ticket-main-left{width:68%;padding-right:14px;}';
        $html .= '.ticket-main-right{width:32%;padding-left:14px;border-left:1px dashed #cfd8e3;}';
        $html .= '.ticket-section{margin-bottom:12px;padding:12px 14px;border:1px solid #e1e8f0;border-radius:10px;background:#fff;}';
        $html .= '.event-title{font-size:24px;line-height:1.2;font-weight:700;color:#1a2f4f;margin-bottom:10px;}';
        $html .= '.event-time{font-size:15px;color:#334862;margin-bottom:8px;}';
        $html .= '.inline-meta{font-size:15px;color:#24364f;line-height:1.45;}';
        $html .= '.inline-meta-second{margin-top:6px;}';
        $html .= '.inline-meta strong{color:#173056;}';
        $html .= '.customer-line{font-size:15px;color:#24364f;line-height:1.5;}';
        $html .= '.ticket-qr-uid{margin-top:10px;font-size:13px;letter-spacing:.03em;word-break:break-all;color:#24364f;}';
        $html .= '.qr-panel{padding:10px;border:1px solid #d9e1ec;border-radius:10px;background:#fff;}';
        $html .= '.ticket-qr{width:150px;height:150px;border:1px solid #d7dee8;border-radius:8px;background:#fff;padding:6px;box-sizing:border-box;}';
        $html .= '.ticket-legend{margin-top:8px;font-size:11px;color:#526983;line-height:1.4;}';
        $html .= '.ticket-bottom-line{width:100%;border-top:1px dashed #8ea1b7;height:0;margin-top:8px;}';
        $html .= '.ticket-rules{margin-top:8px;padding-top:6px;font-size:8px;line-height:1.22;color:#111;}';
        $html .= '.ticket-rules-title{margin:0 0 6px 0;padding:4px 0;border-top:2px solid #222;border-bottom:2px solid #222;font-size:9px;font-weight:700;color:#111;letter-spacing:.04em;text-transform:uppercase;text-align:center;}';
        $html .= '.ticket-rules-table{width:100%;border-collapse:collapse;table-layout:fixed;}';
        $html .= '.ticket-rules-table td{vertical-align:top;padding:0 6px 0 0;word-wrap:break-word;text-align:justify;}';
        $html .= '.ticket-rules-table td:last-child{padding-right:0;padding-left:6px;border-left:1px solid #bfc7d1;}';
        $html .= '.ticket-rules-row td{padding-top:2px;padding-bottom:2px;}';
        $html .= '.ticket-rules-block{margin-bottom:4px;}';
        $html .= '.ticket-rules-intro{margin:0 0 4px 0;font-weight:700;text-align:justify;}';
        $html .= '.ticket-rules-item-num{font-weight:700;white-space:nowrap;}';
        $html .= '.ticket-rules-item-text{display:inline;}';
        $html .= '@media print { body{background:#fff;} .ticket-page{border-color:#b9c5d4;} }';
        $html .= '</style></head><body><div class="ticket-page">';
        $html .= '<div class="ticket-top"><div class="ticket-head"><div class="brand-line">';
        if ($logoDataUri) {
            $html .= '<div class="brand-logo-wrap"><img class="ticket-logo" src="' . $logoDataUri . '" alt="Логотип"></div>';
        }
        $html .= '<div class="ticket-sub">Электронный билет. Для прохода предъявите QR или UID / Электронды билет. Кіру үшін QR немесе UID көрсетіңіз.</div></div></div></div>';
        $html .= '<div class="ticket-content"><div class="ticket-main"><div class="ticket-main-left">';
        $html .= '<div class="ticket-section"><div class="event-title">' . $event_title_quoted . '</div><div class="event-time">' . $session_time . '</div><div class="inline-meta"><strong>РЯД / ҚАТАР:</strong> ' . $rowValueEsc . ' &nbsp;&nbsp; <strong>МЕСТО / ОРЫН:</strong> ' . $seatValueEsc . '</div><div class="inline-meta inline-meta-second"><strong>КАТЕГОРИЯ / САНАТ:</strong> ' . $segmentRu . '</div></div>';
        $html .= '<div class="ticket-section">';
        $html .= '<div class="customer-line"><strong>ЦЕНА / БАҒА:</strong> ' . (is_numeric($pdf_price_label) ? number_format((float)$pdf_price_label, 0, '.', '') . ' тг' : '—') . '</div>';
        $html .= '<div class="customer-line"><strong>ОПЛАТА / ТӨЛЕМ:</strong> ' . (is_numeric($pdf_payment_label) ? number_format((float)$pdf_payment_label, 0, '.', '') . ' тг' : $paid_price_display) . ' (' . $channelRu . ')</div>';

		// Раскомментировать, если нужно показывать строку со скидкой в билете вида "СКИДКА / ЖЕҢІЛДІК: 50%"
		/*        if ($discountLine !== '') {
            $html .= '<div class="customer-line"><strong>' . htmlspecialchars($discountLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></div>';
        } else {
            $html .= '<div class="customer-line"><strong>СКИДКА / ЖЕҢІЛДІК:</strong> ' . ((int)$pdf_discount_percent) . '%' . '</div>';
        } */
		
        $html .= '</div>';
        $html .= '</div><div class="ticket-main-right"><div class="qr-panel">';
        if ($qrDataUri) {
            $html .= '<img class="ticket-qr" src="' . $qrDataUri . '" alt="QR ' . $ticket_uid . '">';
        } else if ($qrFilePath && is_file($qrFilePath)) {
            $qrSrc = 'file://' . str_replace(DIRECTORY_SEPARATOR, '/', $qrFilePath);
            $html .= '<img class="ticket-qr" src="' . htmlspecialchars($qrSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="QR ' . $ticket_uid . '">';
        } else {
            $html .= '<div class="ticket-legend">QR временно недоступен / QR уақытша қолжетімсіз</div>';
        }
        $html .= '<div class="ticket-qr-uid">' . $ticket_uid . '</div>';
        $html .= '<div class="ticket-legend">Покупка / Сатып алу: ' . $purchased_at . '</div>';
        $html .= '</div></div></div>';
        $html .= '<div class="ticket-bottom-line"></div>';
        $html .= '<div class="ticket-rules">';
        $html .= '<div class="ticket-rules-title">Правила пользования билетом / Билетті пайдалану ережелері</div>';
        $html .= '<table class="ticket-rules-table" role="presentation">';
        $html .= '<tr class="ticket-rules-row"><td><div class="ticket-rules-intro">' . htmlspecialchars($rulesIntroRu, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></td><td><div class="ticket-rules-intro">' . htmlspecialchars($rulesIntroKz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></td></tr>';
        for ($i = 0; $i < count($rulesRu); $i++) {
            $ruleNumber = $i + 1;
            $html .= '<tr class="ticket-rules-row"><td><span class="ticket-rules-item-num">' . $ruleNumber . '.</span> <span class="ticket-rules-item-text">' . htmlspecialchars($rulesRu[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></td><td><span class="ticket-rules-item-num">' . $ruleNumber . '.</span> <span class="ticket-rules-item-text">' . htmlspecialchars($rulesKz[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div></body></html>';

        return $html;
    }
}

// -----------------------------
// Генерация PDF из массива билета (использует ticket_pdf_render_html)
// -----------------------------
if (!function_exists('ticket_pdf_generate_from_ticket')) {
    function ticket_pdf_generate_from_ticket(array $ticket, $force = false, &$error = null) {
        $error = null;
        if (empty($ticket['ticket_uid'])) {
            $error = 'ticket_uid missing';
            return false;
        }
        if (!ensure_ticket_pdf_directory()) {
            $error = 'Не удалось создать папку для PDF-файлов';
            return false;
        }
        $pdfPath = ticket_pdf_file_path($ticket['ticket_uid']);
        if (!$force && is_file($pdfPath) && filesize($pdfPath) > 0) {
            return true;
        }

        // Ensure dompdf available (we prefer dompdf for consistent rendering)
        if (!ticket_pdf_autoload()) {
            $error = 'Composer autoload.php или пакет dompdf/dompdf не найдены в /vendor. Проверьте путь к автозагрузчику.';
            return false;
        }
        if (!class_exists('\Dompdf\Dompdf')) {
            $error = 'Класс Dompdf не найден после подключения autoload.php. Проверьте установку пакета dompdf/dompdf через Composer.';
            return false;
        }

        // Render HTML and generate PDF
        $html = ticket_pdf_render_html($ticket);
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html);
            $dompdf->render();
            $output = $dompdf->output();
            if ($output === false || $output === null) {
                $error = 'Dompdf не смог сгенерировать PDF.';
                return false;
            }
            if (@file_put_contents($pdfPath, $output) === false) {
                $error = 'Не удалось записать PDF-файл в каталог uploads/tickets.';
                return false;
            }
            if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
                @unlink($pdfPath);
                $error = 'Генерация PDF не удалась: файл не создан.';
                return false;
            }
            return true;
        } catch (Throwable $e) {
            $error = 'Ошибка при генерации PDF: ' . $e->getMessage();
            return false;
        }
    }
}

// -----------------------------
// Генерация PDF по ticket_uid (поддерживает опциональную $meta)
// Если $meta передан, он будет добавлен в массив билета как pdf_fields,
// чтобы ticket_pdf_render_html использовал base_total/auto_percent/final_total из meta.
// -----------------------------
if (!function_exists('ticket_pdf_generate_by_ticket_uid')) {
    /**
     * @param string $ticket_uid
     * @param bool $force
     * @param string|null &$error
     * @param array|null $meta Optional meta (e.g., from $ticket_meta_map)
     * @return bool
     */
    function ticket_pdf_generate_by_ticket_uid($ticket_uid, $force = false, &$error = null, $meta = null) {
        global $pdo;
        $error = null;
        $ticket_uid = trim((string)$ticket_uid);
        if ($ticket_uid === '') {
            $error = 'empty ticket uid';
            return false;
        }
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $error = 'DB not available';
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT t.*, s.start_time AS schedule_start, e.title AS event_title, c.full_name AS customer_name, tx.payload AS tx_payload
                FROM tickets t
                LEFT JOIN schedules s ON s.id = t.schedule_id
                LEFT JOIN events e ON e.id = t.event_id
                LEFT JOIN customers c ON c.id = t.customer_id
                LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
                WHERE t.ticket_uid = :uid LIMIT 1");
            $stmt->execute([':uid' => $ticket_uid]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) {
                $error = 'Билет не найден';
                return false;
            }

            // --- START: normalize meta and/or build per-ticket pdf_fields using ticket row ---
            $pdf_fields = [];

            // 1) Map known keys from $meta (if provided)
            if (is_array($meta)) {
                // support both pdf_* and legacy keys
                if (isset($meta['pdf_price'])) $pdf_fields['price_label'] = (float)$meta['pdf_price'];
                if (isset($meta['price'])) $pdf_fields['price_label'] = $pdf_fields['price_label'] ?? (float)$meta['price'];

                if (isset($meta['pdf_discount_percent'])) $pdf_fields['discount_label_percent'] = (int)$meta['pdf_discount_percent'];
                if (isset($meta['discount_percent'])) $pdf_fields['discount_label_percent'] = $pdf_fields['discount_label_percent'] ?? (int)$meta['discount_percent'];

                if (isset($meta['pdf_discount_amount'])) $pdf_fields['discount_label_amount'] = (float)$meta['pdf_discount_amount'];
                if (isset($meta['discount_amount'])) $pdf_fields['discount_label_amount'] = $pdf_fields['discount_label_amount'] ?? (float)$meta['discount_amount'];

                if (isset($meta['pdf_payment'])) $pdf_fields['payment_label'] = (float)$meta['pdf_payment'];
                if (isset($meta['price_per_seat'])) $pdf_fields['payment_label'] = $pdf_fields['payment_label'] ?? (float)$meta['price_per_seat'];

                if (isset($meta['pdf_payment_type'])) $pdf_fields['payment_type'] = $meta['pdf_payment_type'];
                if (isset($meta['payment_type'])) $pdf_fields['payment_type'] = $pdf_fields['payment_type'] ?? $meta['payment_type'];
            }

            // 2) Prefer authoritative per-ticket DB values when present
            // ticket.price in DB may be transaction-level or original_price depending on your flow,
            // but we still use it as a reliable fallback for payment_label if nothing else provided.
            $ticket_price_db = isset($ticket['price']) ? (float)$ticket['price'] : null;
            $ticket_discount_db = null;
            if (isset($ticket['discount']) && $ticket['discount'] !== '') {
                $ticket_discount_db = is_numeric($ticket['discount']) ? (int)$ticket['discount'] : null;
            }

            // If DB has discount percent, set it (overrides meta if present but meta should be preferred)
            if ($ticket_discount_db !== null && !isset($pdf_fields['discount_label_percent'])) {
                $pdf_fields['discount_label_percent'] = $ticket_discount_db;
            }

            // If DB has ticket price and payment_label missing, set payment_label from DB
            if ($ticket_price_db !== null && !isset($pdf_fields['payment_label'])) {
                $pdf_fields['payment_label'] = $ticket_price_db;
            }

            // 3) If meta provided included price_label (base price), keep it.
            // If not, try to infer price_label from DB or tx_payload later.

            // 4) Compute payment_label from price_label and discount percent when appropriate
            // If we have price_label and discount percent but payment_label is missing or clearly inconsistent,
            // compute payment_label = round(price_label * (1 - percent/100), 2)
            if (isset($pdf_fields['price_label']) && isset($pdf_fields['discount_label_percent'])) {
                $p = (int)$pdf_fields['discount_label_percent'];
                if ($p < 0) $p = 0;
                if ($p > 100) $p = 100;

                $computed_payment = $p >= 100 ? 0.0 : round((float)$pdf_fields['price_label'] * (1 - ($p / 100.0)), 2);

                // If payment_label missing, set computed value
                if (!isset($pdf_fields['payment_label'])) {
                    $pdf_fields['payment_label'] = $computed_payment;
                } else {
                    // If payment_label exists but equals price_label (no discount applied) while percent > 0,
                    // or differs significantly from computed value, prefer computed value (to reflect percent).
                    $existing_payment = (float)$pdf_fields['payment_label'];
                    if ($p > 0) {
                        // difference threshold 0.01
                        if (abs($existing_payment - $computed_payment) > 0.01) {
                            $pdf_fields['payment_label'] = $computed_payment;
                        }
                    }
                }

                // Ensure discount_label_amount is consistent
                $pdf_fields['discount_label_amount'] = round(max(0.0, (float)$pdf_fields['price_label'] - (float)$pdf_fields['payment_label']), 2);
            }

            // 5) If discount amount present but percent missing, compute percent
            if (!isset($pdf_fields['discount_label_percent']) && isset($pdf_fields['price_label']) && isset($pdf_fields['payment_label'])) {
                $base = (float)$pdf_fields['price_label'];
                $paid = (float)$pdf_fields['payment_label'];
                if ($base > 0.000001) {
                    $pdf_fields['discount_label_percent'] = (int) round((($base - $paid) / $base) * 100);
                } else {
                    $pdf_fields['discount_label_percent'] = 0;
                }
                if (!isset($pdf_fields['discount_label_amount'])) {
                    $pdf_fields['discount_label_amount'] = round(max(0.0, $base - $paid), 2);
                }
            }

            // 6) If still missing values, try to extract from transaction payload (tx_payload) if present
            if ((!isset($pdf_fields['price_label']) || !isset($pdf_fields['payment_label']) || !isset($pdf_fields['discount_label_percent'])) && !empty($ticket['tx_payload'])) {
                $txp = json_decode($ticket['tx_payload'], true);
                if (is_array($txp)) {
                    if (isset($txp['seats_final']) && is_array($txp['seats_final']) && isset($ticket['seat_identifier'])) {
                        foreach ($txp['seats_final'] as $sf) {
                            $sid = isset($sf['identifier']) ? str_replace(':','-',$sf['identifier']) : null;
                            if ($sid && $sid === $ticket['seat_identifier']) {
                                if (!isset($pdf_fields['payment_label']) && isset($sf['final_price'])) $pdf_fields['payment_label'] = (float)$sf['final_price'];
                                if (!isset($pdf_fields['price_label']) && isset($sf['original_price'])) $pdf_fields['price_label'] = (float)$sf['original_price'];
                                if (!isset($pdf_fields['discount_label_amount']) && isset($sf['discount'])) $pdf_fields['discount_label_amount'] = (float)$sf['discount'];
                                if (!isset($pdf_fields['discount_label_percent']) && isset($sf['discount_percent'])) $pdf_fields['discount_label_percent'] = (int)$sf['discount_percent'];
                                break;
                            }
                        }
                    }
                    // fallback: if tx payload has base_total/final_total and we still lack values, try to use ticket.price as payment
                    if ((!isset($pdf_fields['price_label']) || !isset($pdf_fields['payment_label'])) && isset($txp['base_total']) && isset($txp['final_total'])) {
                        if (!isset($pdf_fields['payment_label']) && isset($ticket['price'])) $pdf_fields['payment_label'] = (float)$ticket['price'];
                        if (!isset($pdf_fields['price_label']) && isset($txp['base_total'])) $pdf_fields['price_label'] = (float)$txp['base_total'];
                    }
                }
            }

            // 7) Final safety defaults: ensure payment_label exists and price_label exists
            if (!isset($pdf_fields['payment_label']) && isset($ticket['price'])) {
                $pdf_fields['payment_label'] = (float)$ticket['price'];
            }
            if (!isset($pdf_fields['price_label'])) {
                // fallback: set price_label = payment_label
                $pdf_fields['price_label'] = isset($pdf_fields['payment_label']) ? (float)$pdf_fields['payment_label'] : null;
            }
            if (!isset($pdf_fields['discount_label_amount'])) {
                $pdf_fields['discount_label_amount'] = round(max(0.0, (float)$pdf_fields['price_label'] - (float)$pdf_fields['payment_label']), 2);
            }
            if (!isset($pdf_fields['discount_label_percent'])) {
                // compute percent if possible
                $base = (float)$pdf_fields['price_label'];
                $paid = (float)$pdf_fields['payment_label'];
                if ($base > 0.000001) {
                    $pdf_fields['discount_label_percent'] = (int) round((($base - $paid) / $base) * 100);
                } else {
                    $pdf_fields['discount_label_percent'] = 0;
                }
            }

            // Ensure types and rounding
            $pdf_fields['price_label'] = isset($pdf_fields['price_label']) ? round((float)$pdf_fields['price_label'], 2) : null;
            $pdf_fields['payment_label'] = isset($pdf_fields['payment_label']) ? round((float)$pdf_fields['payment_label'], 2) : null;
            $pdf_fields['discount_label_amount'] = isset($pdf_fields['discount_label_amount']) ? round((float)$pdf_fields['discount_label_amount'], 2) : 0.0;
            $pdf_fields['discount_label_percent'] = isset($pdf_fields['discount_label_percent']) ? (int)$pdf_fields['discount_label_percent'] : 0;
            if (!isset($pdf_fields['payment_type']) && isset($ticket['payment_provider'])) {
                $pdf_fields['payment_type'] = $ticket['payment_provider'];
            }

            // Attach to ticket for renderer
            $ticket['pdf_fields'] = $pdf_fields;
            // --- END: normalize meta and/or build per-ticket pdf_fields using ticket row ---

            return ticket_pdf_generate_from_ticket($ticket, $force, $error);
        } catch (Throwable $e) {
            $error = 'Ошибка при поиске билета: ' . $e->getMessage();
            return false;
        }
    }
}

// -----------------------------
// Утилиты удаления / проверки
// -----------------------------
if (!function_exists('ticket_pdf_delete_by_uid')) {
    function ticket_pdf_delete_by_uid($ticket_uid) {
        $path = ticket_pdf_file_path($ticket_uid);
        if (is_file($path)) {
            @unlink($path);
        }
        $qr = ticket_qr_file_path($ticket_uid);
        if (is_file($qr)) {
            @unlink($qr);
        }
        return true;
    }
}

if (!function_exists('ticket_pdf_exists')) {
    function ticket_pdf_exists($ticket_uid) {
        $path = ticket_pdf_file_path($ticket_uid);
        return is_file($path) && filesize($path) > 0;
    }
}
