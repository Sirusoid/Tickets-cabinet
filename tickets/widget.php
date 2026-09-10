<?php
// tickets/widget.php
// Публичная страница покупки билетов для встраивания в Тильду через iframe.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/payment/bcc.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = db_connect();
    }
}

$cfg = bcc_config($pdo);
$enabled = bcc_is_enabled($pdo);

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$openSession = null;

if ($session_id > 0) {
    $stmt = $pdo->prepare("SELECT s.*, e.title AS event_title, e.image AS event_image, h.name AS hall_name
        FROM schedules s
        LEFT JOIN events e ON s.event_id = e.id
        LEFT JOIN halls h ON s.hall_id = h.id
        WHERE s.id = :id AND s.status IN ('upcoming','active') AND s.start_time >= NOW()
        LIMIT 1");
    $stmt->execute([':id' => $session_id]);
    $openSession = $stmt->fetch(PDO::FETCH_ASSOC);
}

$siteUrl = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
$pageTitle = 'Покупка билетов';
$skip_require_login = true;
$use_sidebar = false;
$hide_admin_header = true;
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/bcc-payment-modal.css">
<style>
    .widget-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 0;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 16px;
    }
    .widget-header h1 {
        font-size: 22px;
        margin: 0;
    }
    .widget-back {
        font-size: 14px;
        color: #6b7280;
        text-decoration: none;
    }
    .widget-back:hover {
        color: #111827;
    }
    .afisha-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 20px;
    }
    .afisha-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .afisha-image {
        height: 180px;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .afisha-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .afisha-body {
        padding: 16px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .afisha-body h3 {
        font-size: 18px;
        margin: 0 0 8px;
    }
    .afisha-meta {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 16px;
        flex: 1;
    }
    .afisha-card .btn {
        width: 100%;
    }
    .widget-iframe-mode .mt-4 {
        margin-top: 0 !important;
    }
</style>

<div class="container mt-4">
    <div class="widget-header">
        <h1>Афиша</h1>
        <a href="<?= h($siteUrl) ?>" class="widget-back" id="widgetBackLink">← Назад на сайт</a>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-warning">Онлайн-оплата временно недоступна. Попробуйте позже.</div>
    <?php endif; ?>

    <?php if ($openSession): ?>
        <div class="alert alert-info">
            Выбран спектакль: <strong><?= h($openSession['event_title']) ?></strong>,
            <?= h(date('d.m.Y H:i', strtotime($openSession['start_time']))) ?>
        </div>
    <?php endif; ?>

    <div class="afisha-grid" id="afishaGrid">
        <?php if ($openSession): ?>
            <div class="afisha-card" data-session-id="<?= (int)$openSession['id'] ?>">
                <div class="afisha-image">
                    <?php if (!empty($openSession['event_image']) && file_exists(__DIR__ . '/../uploads/images/' . $openSession['event_image'])): ?>
                        <img src="/uploads/images/<?= htmlspecialchars($openSession['event_image']) ?>" alt="">
                    <?php else: ?>
                        <div class="afisha-no-image">Нет изображения</div>
                    <?php endif; ?>
                </div>
                <div class="afisha-body">
                    <h3><?= htmlspecialchars($openSession['event_title'] ?? 'Без названия') ?></h3>
                    <p class="afisha-meta">
                        <?= date('d.m.Y H:i', strtotime($openSession['start_time'])) ?><br>
                        <?= htmlspecialchars($openSession['hall_name'] ?? '') ?>
                    </p>
                    <button class="btn btn-primary btn-buy" data-session-id="<?= (int)$openSession['id'] ?>">
                        Купить билет
                    </button>
                </div>
            </div>
        <?php else: ?>
            <p>Не выбран спектакль. Вернитесь на сайт и выберите сеанс.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="bcc-modal" id="bccPaymentModal" style="display:none;">
    <div class="bcc-modal-backdrop"></div>
    <div class="bcc-modal-dialog">
        <div class="bcc-modal-header">
            <h3 class="bcc-modal-title">
                <?php if ($openSession): ?>
                    «<?= h($openSession['event_title']) ?>»
                    <span class="bcc-modal-subtitle" data-session-datetime="<?= h($openSession['start_time']) ?>"></span>
                <?php else: ?>
                    Покупка билетов
                <?php endif; ?>
            </h3>
            <button type="button" class="bcc-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="bcc-modal-body">
            <!-- Step 1: Seat selection -->
            <div class="row bcc-step active" id="bccStepSeats">
                <div class="col-md-8">
                    <div class="seatmap-wrapper">
                        <div class="seatmap-zoom-controls">
                            <button type="button" id="bccZoomIn" aria-label="Увеличить">+</button>
                            <button type="button" id="bccResetZoom" aria-label="Сбросить">⟲</button>
                            <button type="button" id="bccZoomOut" aria-label="Уменьшить">−</button>
                        </div>
                        <canvas id="bccSeatmapCanvas"></canvas>
                        <div id="bccLegend" class="legend-inline"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bcc-side-panel">
                        <div class="bcc-form">
                            <div class="bcc-cart">
                                <h6>Выбранные места</h6>
                                <div id="bccCartList"></div>
                            </div>
                            <div id="bccPurchaseLimitNote" class="bcc-cart-empty" style="margin-bottom:10px; display:none;"></div>
                            <div id="bccClientReservationPanel" style="display:none;">
                                <button type="button" class="bcc-btn-secondary w-100" id="bccReserveBtn" disabled>Зарезервировать места</button>
                                <div id="bccReservationHint" style="margin-top:8px; color:#6b7280; font-size:12px; line-height:1.4;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Customer details -->
            <div class="row bcc-step" id="bccStepCustomer" style="display: none;">
                <div class="col-md-8" style="max-width: 600px; margin: 0 auto; padding-top: 16px;">
                    <div class="bcc-step-header">
                        <button type="button" class="bcc-step-back" id="bccBackToSeats">← Назад к выбору мест</button>
                    </div>
                    <h2 class="bcc-step-title">Контактные данные</h2>
                    <p style="color:#6b7280; margin-bottom: 20px;">Введите данные для получения билетов.</p>

                    <div class="bcc-customer-form">
                        <div class="bcc-form-group">
                            <label class="bcc-form-label" for="bccCustomerName">Имя</label>
                            <input type="text" class="bcc-form-input" id="bccCustomerName" placeholder="Введите имя">
                        </div>
                        <div class="bcc-form-group">
                            <label class="bcc-form-label" for="bccCustomerPhone">Телефон <span style="color:#ef4444;">*</span></label>
                            <input type="tel" class="bcc-form-input" id="bccCustomerPhone" placeholder="+7 (XXX) XXX-XX-XX">
                        </div>
                        <div class="bcc-form-group">
                            <label class="bcc-form-label" for="bccCustomerEmail">Email</label>
                            <input type="email" class="bcc-form-input" id="bccCustomerEmail" placeholder="email@example.com">
                        </div>
                    </div>

                    <div class="bcc-cart" style="margin-bottom: 16px;">
                        <h6>Ваши билеты</h6>
                        <div id="bccCustomerCartList"></div>
                    </div>

                    <button type="button" class="bcc-btn-buy w-100" id="bccPayBtn" disabled>Перейти к оплате</button>
                    <div id="bccError" class="alert alert-danger mt-3 small" style="display:none;"></div>
                    <div id="bccDebugOutput" class="bcc-debug-output mt-3 small" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Bottom total bar -->
        <div class="bcc-total-bar" id="bccTotalBar">
            <div class="bcc-total-amount">
                <span id="bccTotal">0 ₸</span>
                <button type="button" class="bcc-btn-buy" id="bccBuyBtn" disabled>Купить</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var widgetOrigin = '<?= h($siteUrl) ?>';
        window.bccPaymentConfig = {
            csrfToken: '',
            ajaxUrl: '/ajax/public_payment.php',
            seatsAjaxUrl: '/ajax/public_cash.php',
            currencySymbol: '₸',
            onClose: function () {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'zhassahna:widget:close' }, widgetOrigin);
                }
            }
        };

        var backLink = document.getElementById('widgetBackLink');
        if (backLink) {
            backLink.addEventListener('click', function (e) {
                e.preventDefault();
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'zhassahna:widget:close' }, widgetOrigin);
                }
            });
        }
    })();
    <?php if ($openSession): ?>
    window.bccWidgetAutoOpen = <?= (int)$openSession['id'] ?>;
    <?php endif; ?>
</script>

<script src="/assets/js/schedule-seating-canvas.js"></script>
<script src="/assets/js/legend-canvas.js"></script>
<script src="/assets/js/bcc-payment-modal.js"></script>
<script>
(function () {
    'use strict';
    // Если передан session_id, сразу открываем модалку с выбранным сеансом
    if (window.bccWidgetAutoOpen && typeof window.openBccModal === 'function') {
        window.openBccModal(window.bccWidgetAutoOpen);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
