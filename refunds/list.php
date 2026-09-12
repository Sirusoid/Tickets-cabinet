<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reporting.php';
require_login();

$use_sidebar = true;
$active_menu = 'refunds';
$page_title_meta = 'Возвраты';
$panel_title = 'Возвраты';
$panel_subtitle = 'Возвраты клиентов и кассиров с банковским результатом';
$page_styles = ['/assets/css/refunds.css'];

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$origin = trim((string)($_GET['origin'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$method = trim((string)($_GET['method'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');

$originSql = "CASE
    WHEN r.processed_by IS NOT NULL THEN 'cashier'
    WHEN r.reason LIKE '%кассир%' OR r.reason LIKE '%cashier%' THEN 'cashier'
    ELSE 'client'
END";
$where = ['r.created_at BETWEEN :date_from AND :date_to'];
$params = [
    ':date_from' => $dateFrom . ' 00:00:00',
    ':date_to' => $dateTo . ' 23:59:59',
];
if ($origin !== '') {
    $where[] = "{$originSql} = :origin";
    $params[':origin'] = $origin;
}
if ($status !== '') {
    $where[] = 'r.refund_status = :status';
    $params[':status'] = $status;
}
if ($method !== '') {
    $where[] = 'r.refund_method = :method';
    $params[':method'] = $method;
}
if ($search !== '') {
    $where[] = '(r.ticket_uid LIKE :search OR c.full_name LIKE :search OR e.title LIKE :search OR ps.order_number LIKE :search OR r.refund_transaction_id LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$rows = [];
$total = 0;
$errorText = '';
try {
    $baseSql = "FROM refunds r
        LEFT JOIN tickets t ON t.id = r.ticket_id
        LEFT JOIN customers c ON c.id = t.customer_id
        LEFT JOIN schedules s ON s.id = r.schedule_id
        LEFT JOIN events e ON e.id = s.event_id
        LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
        LEFT JOIN users u ON u.id = r.processed_by
        WHERE {$whereSql}";

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$baseSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT
            r.created_at,
            {$originSql} AS origin,
            r.refund_amount,
            r.refund_status,
            r.refund_method,
            r.refund_provider,
            r.refund_transaction_id,
            r.reason,
            r.approved_at,
            r.ticket_uid,
            t.seat_identifier,
            COALESCE(c.full_name, '—') AS customer_name,
            COALESCE(e.title, 'Без названия') AS event_title,
            s.start_time AS session_start,
            ps.order_number,
            COALESCE(NULLIF(u.full_name, ''), u.username, '') AS processed_by_name
        {$baseSql}
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errorText = 'Не удалось загрузить список возвратов.';
    error_log('[REFUNDS] Ошибка загрузки списка: ' . $e->getMessage());
}

$statusLabels = [
    'requested' => 'Ожидает',
    'approved' => 'Подтверждён',
    'refunded' => 'Выполнен',
    'rejected' => 'Отклонён',
];
$methodLabels = [
    'cash' => 'Наличные',
    'noncash' => 'Безналичные',
    'bank' => 'Эквайринг',
];
$totalPages = max(1, (int)ceil($total / $perPage));
$queryParams = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'origin' => $origin,
    'status' => $status,
    'method' => $method,
    'search' => $search,
];
?>

<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/panel.php'; ?>

<div class="page container-full refunds-page">
    <div class="card refunds-filters">
        <form method="get" class="refunds-filters__form">
            <div>
                <label for="refunds-date-from">Дата от</label>
                <input id="refunds-date-from" class="form-control" type="date" name="date_from" value="<?= h($dateFrom) ?>">
            </div>
            <div>
                <label for="refunds-date-to">Дата до</label>
                <input id="refunds-date-to" class="form-control" type="date" name="date_to" value="<?= h($dateTo) ?>">
            </div>
            <div>
                <label for="refunds-origin">Кто оформил</label>
                <select id="refunds-origin" class="form-control" name="origin">
                    <option value="">Все</option>
                    <option value="cashier" <?= $origin === 'cashier' ? 'selected' : '' ?>>Кассир</option>
                    <option value="client" <?= $origin === 'client' ? 'selected' : '' ?>>Клиент</option>
                </select>
            </div>
            <div>
                <label for="refunds-status">Статус</label>
                <select id="refunds-status" class="form-control" name="status">
                    <option value="">Все</option>
                    <?php foreach ($statusLabels as $statusCode => $statusLabel): ?>
                        <option value="<?= h($statusCode) ?>" <?= $status === $statusCode ? 'selected' : '' ?>><?= h($statusLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="refunds-method">Способ</label>
                <select id="refunds-method" class="form-control" name="method">
                    <option value="">Все</option>
                    <?php foreach ($methodLabels as $methodCode => $methodLabel): ?>
                        <option value="<?= h($methodCode) ?>" <?= $method === $methodCode ? 'selected' : '' ?>><?= h($methodLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="refunds-search">Поиск</label>
                <input id="refunds-search" class="form-control" type="search" name="search" value="<?= h($search) ?>" placeholder="Клиент, билет, заказ">
            </div>
            <div class="refunds-filters__actions">
                <a class="btn btn-ghost" href="/refunds/list.php">Сбросить</a>
                <button class="btn btn-primary" type="submit">Показать</button>
            </div>
        </form>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card alert alert--danger"><?= h($errorText) ?></div>
    <?php else: ?>
        <div class="refunds-summary">Найдено возвратов: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
        <div class="card refunds-table-wrap">
            <table class="admin-table refunds-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Источник</th>
                        <th>Клиент</th>
                        <th>Спектакль / сеанс</th>
                        <th>Билет</th>
                        <th>Заказ</th>
                        <th>Сумма</th>
                        <th>Способ / провайдер</th>
                        <th>Статус</th>
                        <th>Транзакция</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" class="refunds-empty">Возвратов за выбранный период нет.</td></tr>
                <?php else: foreach ($rows as $row):
                    $originLabel = $row['origin'] === 'cashier' ? 'Кассир' : 'Клиент';
                    $statusLabel = $statusLabels[$row['refund_status']] ?? $row['refund_status'];
                    $statusClass = $row['refund_status'] === 'refunded' ? 'is-success' : ($row['refund_status'] === 'rejected' ? 'is-danger' : 'is-pending');
                    $sessionLabel = (string)$row['event_title'];
                    if (!empty($row['session_start'])) $sessionLabel .= ' / ' . reporting_format_date($row['session_start'], true);
                ?>
                    <tr>
                        <td><?= h(reporting_format_date($row['created_at'], true)) ?></td>
                        <td><span class="refunds-origin refunds-origin--<?= h($row['origin']) ?>"><?= h($originLabel) ?></span></td>
                        <td><?= h($row['customer_name']) ?></td>
                        <td><?= h($sessionLabel) ?></td>
                        <td><?= h(str_replace(':', ' - ', (string)$row['seat_identifier'])) ?><small><?= h($row['ticket_uid']) ?></small></td>
                        <td><?= h($row['order_number'] ?: '—') ?></td>
                        <td><strong><?= number_format((float)$row['refund_amount'], 2, '.', ' ') ?> тг</strong></td>
                        <td><?= h($methodLabels[$row['refund_method']] ?? $row['refund_method']) ?><small><?= h($row['refund_provider'] ?: '—') ?></small></td>
                        <td><span class="refunds-status <?= $statusClass ?>"><?= h($statusLabel) ?></span></td>
                        <td><?= h($row['refund_transaction_id'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="refunds-pagination" aria-label="Страницы возвратов">
                <?php if ($page > 1): $queryParams['page'] = $page - 1; ?>
                    <a class="btn btn-ghost btn-sm" href="/refunds/list.php?<?= h(http_build_query($queryParams)) ?>">Назад</a>
                <?php endif; ?>
                <span>Страница <?= $page ?> из <?= $totalPages ?></span>
                <?php if ($page < $totalPages): $queryParams['page'] = $page + 1; ?>
                    <a class="btn btn-ghost btn-sm" href="/refunds/list.php?<?= h(http_build_query($queryParams)) ?>">Вперёд</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
