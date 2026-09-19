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
$customerSearch = trim((string)($_GET['customer'] ?? ''));
$ticketSearch = trim((string)($_GET['ticket'] ?? ''));
$transactionSearch = trim((string)($_GET['transaction'] ?? ''));
$orderSearch = trim((string)($_GET['order'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$requestedPerPage = (int)($_GET['per_page'] ?? 25);
$perPage = in_array($requestedPerPage, [25, 50, 100, 500], true) ? $requestedPerPage : 25;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');

$suggestType = trim((string)($_GET['suggest'] ?? ''));
if ($suggestType !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $query = trim((string)($_GET['q'] ?? ''));
    if ($query === '') {
        echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $like = '%' . $query . '%';
    $suggestParams = [
        ':date_from' => $dateFrom . ' 00:00:00',
        ':date_to' => $dateTo . ' 23:59:59',
        ':like' => $like,
    ];
    try {
        if ($suggestType === 'customer') {
            $suggestSql = "SELECT DISTINCT c.full_name AS value
                FROM refunds r
                LEFT JOIN tickets t ON t.id = r.ticket_id
                LEFT JOIN customers c ON c.id = t.customer_id
                WHERE r.created_at BETWEEN :date_from AND :date_to
                    AND c.full_name LIKE :like
                ORDER BY c.full_name
                LIMIT 20";
        } elseif ($suggestType === 'ticket') {
            $suggestSql = "SELECT DISTINCT r.ticket_uid AS value
                FROM refunds r
                WHERE r.created_at BETWEEN :date_from AND :date_to
                    AND r.ticket_uid LIKE :like
                ORDER BY r.ticket_uid
                LIMIT 20";
        } elseif ($suggestType === 'transaction') {
            $suggestSql = "SELECT DISTINCT r.refund_transaction_id AS value
                FROM refunds r
                WHERE r.created_at BETWEEN :date_from AND :date_to
                    AND r.refund_transaction_id LIKE :like
                ORDER BY r.refund_transaction_id
                LIMIT 20";
        } elseif ($suggestType === 'order') {
            $suggestSql = "SELECT DISTINCT ps.order_number AS value
                FROM refunds r
                LEFT JOIN tickets t ON t.id = r.ticket_id
                LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
                WHERE r.created_at BETWEEN :date_from AND :date_to
                    AND ps.order_number LIKE :like
                ORDER BY ps.order_number
                LIMIT 20";
        } else {
            echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $suggestStmt = $pdo->prepare($suggestSql);
        $suggestStmt->execute($suggestParams);
        echo json_encode(['success' => true, 'data' => $suggestStmt->fetchAll(PDO::FETCH_COLUMN)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('[REFUNDS] Ошибка подсказок: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'data' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

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
if ($customerSearch !== '') {
    $where[] = 'c.full_name LIKE :customer_search';
    $params[':customer_search'] = '%' . $customerSearch . '%';
}
if ($ticketSearch !== '') {
    $where[] = 'r.ticket_uid LIKE :ticket_search';
    $params[':ticket_search'] = '%' . $ticketSearch . '%';
}
if ($transactionSearch !== '') {
    $where[] = 'r.refund_transaction_id LIKE :transaction_search';
    $params[':transaction_search'] = '%' . $transactionSearch . '%';
}
if ($orderSearch !== '') {
    $where[] = 'ps.order_number LIKE :order_search';
    $params[':order_search'] = '%' . $orderSearch . '%';
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
    'customer' => $customerSearch,
    'ticket' => $ticketSearch,
    'transaction' => $transactionSearch,
    'order' => $orderSearch,
    'per_page' => $perPage,
];
?>

<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/panel.php'; ?>

<div class="page container-full refunds-page">
    <div class="card refunds-filters">
        <form id="refundsFiltersForm" method="get" class="refunds-filters__form">
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
                <label for="refunds-customer">Клиент</label>
                <div class="refunds-filter-field">
                    <input id="refunds-customer" class="form-control" type="search" name="customer" value="<?= h($customerSearch) ?>" placeholder="ФИО клиента" data-refund-suggest="customer">
                    <div id="refunds-customer-suggestions" class="refunds-typeahead"></div>
                </div>
            </div>
            <div>
                <label for="refunds-ticket">Билет</label>
                <div class="refunds-filter-field">
                    <input id="refunds-ticket" class="form-control" type="search" name="ticket" value="<?= h($ticketSearch) ?>" placeholder="UID билета" data-refund-suggest="ticket">
                    <div id="refunds-ticket-suggestions" class="refunds-typeahead"></div>
                </div>
            </div>
            <div>
                <label for="refunds-transaction">Транзакция</label>
                <div class="refunds-filter-field">
                    <input id="refunds-transaction" class="form-control" type="search" name="transaction" value="<?= h($transactionSearch) ?>" placeholder="ID транзакции" data-refund-suggest="transaction">
                    <div id="refunds-transaction-suggestions" class="refunds-typeahead"></div>
                </div>
            </div>
            <div>
                <label for="refunds-order">Заказ</label>
                <div class="refunds-filter-field">
                    <input id="refunds-order" class="form-control" type="search" name="order" value="<?= h($orderSearch) ?>" placeholder="Номер заказа" data-refund-suggest="order">
                    <div id="refunds-order-suggestions" class="refunds-typeahead"></div>
                </div>
            </div>
            <div class="refunds-filters__actions">
                <a class="btn btn-ghost" href="/refunds/list.php">Сбросить</a>
            </div>
        </form>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card alert alert--danger"><?= h($errorText) ?></div>
    <?php else: ?>
        <div class="refunds-summary">Найдено возвратов: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
        <div class="card table-shell refunds-table-wrap">
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
            <nav class="table-pagination refunds-pagination" aria-label="Страницы возвратов">
                <div class="table-pagination__summary">Найдено: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
                <label class="table-pagination__size">На странице
                    <select class="form-control" data-list-per-page data-list-path="/refunds/list.php" aria-label="Количество возвратов на странице">
                        <?php foreach ([25, 50, 100, 500] as $pageSize): ?>
                            <option value="<?= $pageSize ?>" <?= $perPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="table-pagination__controls">
                    <?php $previousPage = max(1, $page - 1); $nextPage = min($totalPages, $page + 1); ?>
                    <a class="btn btn-ghost btn-sm" href="/refunds/list.php?<?= h(http_build_query(array_merge($queryParams, ['page' => $previousPage]))) ?>" aria-label="Предыдущая страница" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>
                    <span><?= (int)$page ?> / <?= (int)$totalPages ?></span>
                    <a class="btn btn-ghost btn-sm" href="/refunds/list.php?<?= h(http_build_query(array_merge($queryParams, ['page' => $nextPage]))) ?>" aria-label="Следующая страница" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
                </div>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    var form = document.getElementById('refundsFiltersForm');
    if (!form) return;

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char];
        });
    }

    function highlightMatch(value, query) {
        var escapedValue = escapeHtml(value);
        var escapedQuery = escapeHtml(query || '').trim();
        if (!escapedQuery) return escapedValue;
        var pattern = escapedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return escapedValue.replace(new RegExp(pattern, 'ig'), '<strong>$&</strong>');
    }

    function hideSuggestions() {
        Array.prototype.forEach.call(form.querySelectorAll('.refunds-typeahead'), function (element) {
            element.innerHTML = '';
            element.classList.remove('is-visible');
        });
    }

    function showSuggestions(type, values, input) {
        var box = document.getElementById('refunds-' + type + '-suggestions');
        if (!box) return;
        box.innerHTML = '';
        if (!values || !values.length) {
            box.classList.remove('is-visible');
            return;
        }
        values.forEach(function (value) {
            var item = document.createElement('div');
            item.className = 'refunds-typeahead__item';
            item.innerHTML = highlightMatch(value, input.value);
            item.addEventListener('mousedown', function (event) {
                event.preventDefault();
                input.value = value;
                hideSuggestions();
                refreshTable();
            });
            box.appendChild(item);
        });
        box.classList.add('is-visible');
    }

    function loadSuggestions(input) {
        var type = input.getAttribute('data-refund-suggest');
        var query = input.value.trim();
        if (!type || query === '') {
            hideSuggestions();
            return;
        }

        var params = new URLSearchParams();
        params.set('suggest', type);
        params.set('q', query);
        var dateFrom = form.querySelector('[name="date_from"]');
        var dateTo = form.querySelector('[name="date_to"]');
        if (dateFrom) params.set('date_from', dateFrom.value);
        if (dateTo) params.set('date_to', dateTo.value);

        fetch('/refunds/list.php?' + params.toString(), {credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                showSuggestions(type, data && data.success ? data.data : [], input);
            })
            .catch(function () { hideSuggestions(); });
    }

    function refreshTable() {
        var params = new URLSearchParams(new FormData(form));
        params.set('page', '1');
        var focused = document.activeElement;
        var focusedName = focused && focused.name ? focused.name : '';
        var focusedValue = focused && typeof focused.selectionStart === 'number' ? focused.selectionStart : null;

        fetch('/refunds/list.php?' + params.toString(), {credentials: 'same-origin'})
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var currentSummary = document.querySelector('.refunds-summary');
                var nextSummary = parsed.querySelector('.refunds-summary');
                var currentTable = document.querySelector('.refunds-table-wrap');
                var nextTable = parsed.querySelector('.refunds-table-wrap');
                var currentPagination = document.querySelector('.refunds-pagination');
                var nextPagination = parsed.querySelector('.refunds-pagination');
                if (currentSummary && nextSummary) currentSummary.replaceWith(nextSummary);
                if (currentTable && nextTable) currentTable.replaceWith(nextTable);
                if (currentPagination && nextPagination) currentPagination.replaceWith(nextPagination);
                if (currentPagination && !nextPagination) currentPagination.remove();
                window.history.replaceState({}, '', '/refunds/list.php?' + params.toString());
                if (focusedName) {
                    var restored = form.querySelector('[name="' + focusedName + '"]');
                    if (restored) {
                        restored.focus();
                        if (focusedValue !== null && typeof restored.setSelectionRange === 'function') {
                            restored.setSelectionRange(focusedValue, focusedValue);
                        }
                    }
                }
            })
            .catch(function () {});
    }

    var controls = form.querySelectorAll('input, select');
    Array.prototype.forEach.call(controls, function (control) {
        if (control.type === 'search' || control.type === 'text') {
            control.addEventListener('input', function () {
                loadSuggestions(control);
                refreshTable();
            });
            control.addEventListener('focus', function () {
                loadSuggestions(control);
            });
            control.addEventListener('blur', function () {
                window.setTimeout(hideSuggestions, 150);
            });
            control.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    hideSuggestions();
                    refreshTable();
                }
            });
            return;
        }
        control.addEventListener('change', function () {
            hideSuggestions();
            refreshTable();
        });
    });

    document.addEventListener('mousedown', function (event) {
        if (!form.contains(event.target)) hideSuggestions();
    });
})();
</script>
