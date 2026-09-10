<?php
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function customer_phone_digits($phone): string
{
    return preg_replace('/\D+/', '', trim((string)$phone));
}

function normalize_customer_phone($phone): string
{
    $digits = customer_phone_digits($phone);
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    } elseif (strlen($digits) === 10 && $digits[0] !== '7') {
        $digits = '7' . $digits;
    }

    return '+' . $digits;
}

function format_customer_phone($phone): string
{
    $normalized = normalize_customer_phone($phone);
    if ($normalized === '') {
        return '';
    }

    $digits = customer_phone_digits($normalized);
    if (strlen($digits) === 11 && $digits[0] === '7') {
        return '+7 ' . substr($digits, 1, 3) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 2) . ' ' . substr($digits, 9, 2);
    }

    return $normalized;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function asset_url($path)
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function flash_set($key, $message)
{
    $_SESSION['flash_messages'][$key] = $message;
}

function flash_get($key)
{
    if (!empty($_SESSION['flash_messages'][$key])) {
        $message = $_SESSION['flash_messages'][$key];
        unset($_SESSION['flash_messages'][$key]);
        return $message;
    }
    return null;
}

function flash_all()
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function generate_uuid()
{
    return bin2hex(random_bytes(16));
}

function export_html_table_to_excel($filename, $html)
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />";
    echo $html;
    exit;
}

function generate_order_number()
{
    return date('YmdHis') . rand(1000, 9999);
}

function get_dashboard_metrics()
{
    return [
        'events' => db_fetch_one('SELECT COUNT(*) AS total FROM events')['total'] ?? 0,
        'schedules' => db_fetch_one('SELECT COUNT(*) AS total FROM schedules')['total'] ?? 0,
        'tickets' => db_fetch_one('SELECT COUNT(*) AS total FROM tickets')['total'] ?? 0,
        'sales' => db_fetch_one('SELECT IFNULL(SUM(price), 0) AS total FROM tickets WHERE payment_status = ?', ['paid'])['total'] ?? 0,
    ];
}

function process_bank_acquirer_payment(array $paymentData)
{
    $endpoint = $paymentData['endpoint'] ?? '';
    $payload = $paymentData['payload'] ?? [];
    $headers = $paymentData['headers'] ?? [];

    if (empty($endpoint)) {
        return ['success' => false, 'message' => 'Bank acquirer endpoint not configured'];
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => 'Bank gateway error: ' . $error];
    }

    return ['success' => true, 'response' => $response];
}

function schedule_compute_auto_status(array $schedule, ?DateTimeInterface $now = null)
{
    $nowTime = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now');
    $startRaw = trim((string)($schedule['start_time'] ?? ''));
    if ($startRaw === '') {
        return 'draft';
    }

    try {
        $startTime = new DateTimeImmutable($startRaw);
    } catch (Throwable $e) {
        return 'draft';
    }

    $durationMinutes = isset($schedule['duration_minutes']) ? (int)$schedule['duration_minutes'] : 0;
    $archiveAt = null;

    if ($durationMinutes > 0) {
        $archiveAt = $startTime->modify('+' . $durationMinutes . ' minutes');
    } else {
        $endRaw = trim((string)($schedule['end_time'] ?? ''));
        if ($endRaw !== '') {
            try {
                $archiveAt = new DateTimeImmutable($endRaw);
            } catch (Throwable $e) {
                $archiveAt = null;
            }
        }
    }

    if ($archiveAt instanceof DateTimeImmutable && $nowTime >= $archiveAt) {
        return 'archive';
    }

    if ($nowTime >= $startTime) {
        return 'active';
    }

    return 'upcoming';
}

function schedule_resolved_status(array $schedule, ?DateTimeInterface $now = null)
{
    $dbStatus = isset($schedule['status']) && $schedule['status'] !== null
        ? trim((string)$schedule['status'])
        : '';

    if ($dbStatus === 'draft' || $dbStatus === 'cancelled') {
        return $dbStatus;
    }

    if ($dbStatus === 'archive') {
        return 'archive';
    }

    return schedule_compute_auto_status($schedule, $now);
}

function ticket_public_secret(): string
{
    if (defined('TICKET_PUBLIC_SECRET')) {
        $secret = trim((string)constant('TICKET_PUBLIC_SECRET'));
        if ($secret !== '') {
            return $secret;
        }
    }
    return '';
}

function ticket_public_token(string $subject): string
{
    $secret = ticket_public_secret();
    if ($secret === '' || $subject === '') {
        return '';
    }
    return substr(hash_hmac('sha256', $subject, $secret), 0, 32);
}

function ticket_public_token_verify(string $subject, string $token): bool
{
    if ($token === '' || $subject === '') {
        return false;
    }
    $expected = ticket_public_token($subject);
    if ($expected === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

function schedule_refresh_statuses(PDO $pdo, ?int $scheduleId = null)
{
    $sql = "SELECT s.id, s.start_time, s.end_time, s.status, COALESCE(e.duration_minutes, 0) AS duration_minutes
            FROM schedules s
            LEFT JOIN events e ON e.id = s.event_id
            WHERE (s.status IS NULL OR s.status = '' OR s.status IN ('upcoming', 'active'))";
    $params = [];

    if ($scheduleId !== null && $scheduleId > 0) {
        $sql .= " AND s.id = :schedule_id";
        $params[':schedule_id'] = $scheduleId;
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return 0;
    }

    $updateStmt = $pdo->prepare("UPDATE schedules SET status = :status WHERE id = :id");
    $updated = 0;
    foreach ($rows as $row) {
        $nextStatus = schedule_compute_auto_status($row);
        $currentStatus = isset($row['status']) ? trim((string)$row['status']) : '';
        if ($nextStatus === $currentStatus) {
            continue;
        }
        $updateStmt->bindValue(':status', $nextStatus, PDO::PARAM_STR);
        $updateStmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        if ($updateStmt->execute()) {
            $updated++;
        }
    }

    return $updated;
}

