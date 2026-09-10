<?php
// tests/bcc_payment.php
// Тестовая страница онлайн-оплаты через BCC

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/payment/bcc.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = db_connect();
    }
}

$pageTitle = 'Тест BCC оплаты';
$use_sidebar = true;
$active_menu = 'tests';
require_once __DIR__ . '/../includes/header.php';

$now = (new DateTime())->format('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT s.*, e.title AS event_title, e.image AS event_image, h.name AS hall_name
    FROM schedules s
    LEFT JOIN events e ON s.event_id = e.id
    LEFT JOIN halls h ON s.hall_id = h.id
    WHERE s.start_time >= :now AND s.status IN ('upcoming','active')
    ORDER BY s.start_time ASC
    LIMIT 30");
$stmt->execute([':now' => $now]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cfg = bcc_config($pdo);
$enabled = bcc_is_enabled($pdo);
?>

<link rel="stylesheet" href="/assets/css/bcc-payment-modal.css">

<div class="container mt-4">
    <h1>Тест BCC оплаты</h1>
    <p class="text-muted">Это тестовая афиша для проверки онлайн-оплаты на сайте.</p>

    <?php if (!$enabled): ?>
        <div class="alert alert-warning">BCC acquiring отключён. Проверьте настройки в разделе «Настройки → Оплата».</div>
    <?php endif; ?>

    <div class="afisha-grid" id="afishaGrid">
        <?php foreach ($sessions as $session): ?>
            <div class="afisha-card" data-session-id="<?php echo (int)$session['id']; ?>">
                <div class="afisha-image">
                    <?php if (!empty($session['event_image']) && file_exists(__DIR__ . '/../uploads/images/' . $session['event_image'])): ?>
                        <img src="/uploads/images/<?php echo htmlspecialchars($session['event_image']); ?>" alt="">
                    <?php else: ?>
                        <div class="afisha-no-image">Нет изображения</div>
                    <?php endif; ?>
                </div>
                <div class="afisha-body">
                    <h3><?php echo htmlspecialchars($session['event_title'] ?? 'Без названия'); ?></h3>
                    <p class="afisha-meta">
                        <?php echo date('d.m.Y H:i', strtotime($session['start_time'])); ?><br>
                        <?php echo htmlspecialchars($session['hall_name'] ?? ''); ?>
                    </p>
                    <button class="btn btn-primary btn-buy" data-session-id="<?php echo (int)$session['id']; ?>">
                        Купить билет
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
            <p>Нет предстоящих мероприятий.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="bcc-modal" id="bccPaymentModal" style="display:none;">
    <div class="bcc-modal-backdrop"></div>
    <div class="bcc-modal-dialog">
        <div class="bcc-modal-header">
            <h3 class="bcc-modal-title">Покупка билетов</h3>
            <button type="button" class="bcc-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="bcc-modal-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="seatmap-wrapper">
                        <canvas id="bccSeatmapCanvas"></canvas>
                        <div id="bccLegend" class="legend-inline mt-2"></div>

                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bcc-form">
                        <h5>Данные для входа</h5>
                        <div class="mb-3">
                            <label class="form-label">Имя</label>
                            <input type="text" class="form-control" id="bccCustomerName" placeholder="Имя">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="bccCustomerPhone" placeholder="+7 (XXX) XXX-XX-XX">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="bccCustomerEmail" placeholder="email@example.com">
                        </div>

                        <div class="bcc-cart">
                            <h6>Выбранные места</h6>
                            <ul id="bccCartList" class="list-unstyled small"></ul>
                            <div class="bcc-total">Итого: <span id="bccTotal">0</span> ₸</div>
                        </div>

                        <button type="button" class="btn btn-success w-100" id="bccPayBtn" disabled>Оплатить</button>
                        <div id="bccError" class="alert alert-danger mt-3 small" style="display:none;"></div>
                        <div id="bccDebugOutput" class="bcc-debug-output mt-3 small"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.bccPaymentConfig = {
        csrfToken: <?php echo json_encode($_SESSION['csrf_token']); ?>,
        ajaxUrl: '/ajax/payment.php',
        seatsAjaxUrl: '/ajax/cash.php',
        currencySymbol: '₸'
    };
</script>

<script src="/assets/js/schedule-seating-canvas.js"></script>
<script src="/assets/js/legend-canvas.js"></script>
<script src="/assets/js/bcc-payment-modal.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
