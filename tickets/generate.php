<?php
// tickets/generate.php
// Генерация и выдача PDF-файла билета по ticket_id или ticket_uid.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/pdf_helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$uid = isset($_GET['uid']) ? trim((string)$_GET['uid']) : '';
$download = isset($_GET['download']) && ((string)$_GET['download'] === '1');
$force = isset($_GET['force']) && ((string)$_GET['force'] === '1');
$publicToken = isset($_GET['t']) ? trim((string)$_GET['t']) : '';

$isPublicAccess = ($uid !== '' && $publicToken !== '' && ticket_public_token_verify('pdf:' . $uid, $publicToken));
$isLoggedIn = !empty($_SESSION['user']);

if (!$isPublicAccess && !$isLoggedIn) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Доступ ограничен</h1><p>Для просмотра билета необходимо авторизоваться или использовать корректную публичную ссылку.</p>';
    echo '<p><a href="/login.php">Войти</a> | <a href="/">На главную</a></p>';
    exit;
}

if ($id <= 0 && $uid === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Билет не найден</h1><p>Укажите идентификатор билета (ticket_id или ticket_uid).</p>';
    echo '<p><a href="/">Вернуться на главную</a></p>';
    exit;
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT t.*, s.start_time AS schedule_start, e.title AS event_title, c.full_name AS customer_name, tx.payload AS tx_payload
            FROM tickets t
            LEFT JOIN schedules s ON s.id = t.schedule_id
            LEFT JOIN events e ON e.id = t.event_id
            LEFT JOIN customers c ON c.id = t.customer_id
            LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
            WHERE t.id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare("SELECT t.*, s.start_time AS schedule_start, e.title AS event_title, c.full_name AS customer_name, tx.payload AS tx_payload
            FROM tickets t
            LEFT JOIN schedules s ON s.id = t.schedule_id
            LEFT JOIN events e ON e.id = t.event_id
            LEFT JOIN customers c ON c.id = t.customer_id
            LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
            WHERE t.ticket_uid = :uid LIMIT 1");
        $stmt->execute([':uid' => $uid]);
    }

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Билет не найден</h1><p>Указанный билет отсутствует в базе.</p>';
        echo '<p><a href="/">Вернуться на главную</a></p>';
        exit;
    }

    $ticket_uid = trim((string)$ticket['ticket_uid']);
    if ($ticket_uid === '') {
        throw new RuntimeException('Ticket UID missing.');
    }

    $pdfPath = ticket_pdf_file_path($ticket_uid);
    if ($force || !is_file($pdfPath) || filesize($pdfPath) === 0) {
        $error = null;
        if (!ticket_pdf_generate_from_ticket($ticket, $force, $error)) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>Не удалось создать PDF</h1>';
            echo '<p>' . htmlspecialchars($error ?: 'Ошибка генерации файла.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            echo '<p>Убедитесь, что Composer установлен, пакет <code>dompdf/dompdf</code> доступен в <code>/vendor</code>, и что папка <code>/uploads/tickets</code> доступна для записи.</p>';
            exit;
        }
    }

    if (!is_file($pdfPath)) {
        throw new RuntimeException('PDF файл не найден после генерации.');
    }

    header('Content-Type: application/pdf');
    $disposition = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($ticket_uid) . '.pdf"');
    header('Content-Length: ' . filesize($pdfPath));
    readfile($pdfPath);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Серверная ошибка</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    exit;
}
