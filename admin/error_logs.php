<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_login();

if (!isset($pdo) || !($pdo instanceof PDO) || !user_has_permission($pdo, 'error_logs', false)) {
    http_response_code(403);
    exit('Доступ запрещён.');
}

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$level = trim((string)($_GET['level'] ?? ''));
$source = trim((string)($_GET['source'] ?? ''));
$order = trim((string)($_GET['order'] ?? ''));
$responseCode = trim((string)($_GET['response_code'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

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
    $columns = [
        'source' => 'source',
        'order' => 'order_number',
        'response_code' => 'response_code',
    ];
    if (!isset($columns[$suggestType])) {
        echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $column = $columns[$suggestType];
        $stmt = $pdo->prepare("SELECT DISTINCT {$column}
            FROM error_logs
            WHERE created_at BETWEEN :date_from AND :date_to
                AND {$column} IS NOT NULL
                AND {$column} <> ''
                AND {$column} LIKE :query
            ORDER BY {$column}
            LIMIT 20");
        $stmt->execute([
            ':date_from' => $dateFrom . ' 00:00:00',
            ':date_to' => $dateTo . ' 23:59:59',
            ':query' => $like,
        ]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        error_log('[ERROR LOGS] Ошибка подсказок: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'data' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$where = ['created_at BETWEEN :date_from AND :date_to'];
$params = [
    ':date_from' => $dateFrom . ' 00:00:00',
    ':date_to' => $dateTo . ' 23:59:59',
];
if ($level !== '') {
    $where[] = 'level = :level';
    $params[':level'] = $level;
}
if ($source !== '') {
    $where[] = 'source LIKE :source';
    $params[':source'] = '%' . $source . '%';
}
if ($order !== '') {
    $where[] = 'order_number LIKE :order_number';
    $params[':order_number'] = '%' . $order . '%';
}
if ($responseCode !== '') {
    $where[] = 'response_code LIKE :response_code';
    $params[':response_code'] = '%' . $responseCode . '%';
}
if ($search !== '') {
    $where[] = '(message LIKE :search_message OR context LIKE :search_context OR ticket_uid LIKE :search_ticket)';
    $params[':search_message'] = '%' . $search . '%';
    $params[':search_context'] = '%' . $search . '%';
    $params[':search_ticket'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$rows = [];
$total = 0;
$errorText = '';
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM error_logs WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT id, level, source, message, context, user_id,
            order_number, ticket_uid, response_code, ip_address, user_agent, created_at
        FROM error_logs
        WHERE {$whereSql}
        ORDER BY created_at DESC, id DESC
        LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $errorText = 'Не удалось загрузить логи ошибок. Возможно, ещё не применена миграция error_logs.';
    error_log('[ERROR LOGS] Ошибка загрузки: ' . $exception->getMessage());
}

$totalPages = max(1, (int)ceil($total / $perPage));
$queryParams = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'level' => $level,
    'source' => $source,
    'order' => $order,
    'response_code' => $responseCode,
    'search' => $search,
];

$levelLabels = [
    'critical' => 'Критическая',
    'error' => 'Ошибка',
    'warning' => 'Предупреждение',
    'info' => 'Информация',
];

$formatContext = static function ($raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $decoded = json_decode((string)$raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return (string)$raw;
};

$use_sidebar = true;
$active_menu = 'error_logs';
$page_title_meta = 'Логи ошибок';
$panel_title = 'Логи ошибок';
$panel_subtitle = 'Детали ошибок приложения и ответов внешних сервисов';
$page_styles = ['/assets/css/audit.css'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full audit-page error-logs-page">
    <div class="card audit-filters">
        <form id="errorLogsFilters" method="get" class="audit-filters__form">
            <div>
                <label for="error-date-from">Дата от</label>
                <input id="error-date-from" type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
            </div>
            <div>
                <label for="error-date-to">Дата до</label>
                <input id="error-date-to" type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
            </div>
            <div>
                <label for="error-level">Уровень</label>
                <select id="error-level" name="level" class="form-control">
                    <option value="">Все уровни</option>
                    <?php foreach ($levelLabels as $code => $label): ?>
                        <option value="<?= h($code) ?>" <?= $level === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $suggestFields = [
                ['source', 'Источник', 'Источник ошибки', 'source'],
                ['order', 'Заказ', 'Номер заказа', 'order'],
                ['response_code', 'Код ответа', 'Код ответа банка', 'response_code'],
            ];
            foreach ($suggestFields as [$name, $label, $placeholder, $suggest]): ?>
                <div class="error-log-filter-field">
                    <label for="error-<?= h($name) ?>"><?= h($label) ?></label>
                    <div style="position:relative;">
                        <input id="error-<?= h($name) ?>" type="search" name="<?= h($name) ?>" class="form-control" value="<?= h($$name) ?>" placeholder="<?= h($placeholder) ?>" data-error-suggest="<?= h($suggest) ?>" autocomplete="off">
                        <div class="error-log-typeahead" data-error-suggestions="<?= h($suggest) ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div>
                <label for="error-search">Поиск в сообщении и деталях</label>
                <input id="error-search" type="search" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Текст ошибки, билет, детали">
            </div>
            <div class="audit-filters__actions">
                <a class="btn btn-ghost" href="/admin/error_logs.php">Сбросить</a>
            </div>
        </form>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card alert alert--danger"><?= h($errorText) ?></div>
    <?php else: ?>
        <div class="audit-summary">Найдено ошибок: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
        <div class="card audit-table-wrap">
            <table class="admin-table audit-table">
                <thead>
                    <tr>
                        <th>Дата и время</th>
                        <th>Уровень</th>
                        <th>Источник</th>
                        <th>Сообщение</th>
                        <th>Заказ</th>
                        <th>Код ответа</th>
                        <th>Подробности</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="audit-empty">Ошибок за выбранный период нет.</td></tr>
                <?php else: foreach ($rows as $row):
                    $rowLevel = (string)($row['level'] ?? 'error');
                    $context = $formatContext($row['context'] ?? '');
                ?>
                    <tr>
                        <td><?= h(date('d.m.Y H:i:s', strtotime((string)$row['created_at']))) ?></td>
                        <td><span class="badge" style="background:<?= $rowLevel === 'critical' ? '#991b1b' : ($rowLevel === 'warning' ? '#b45309' : '#b91c1c') ?>; color:#fff;"><?= h($levelLabels[$rowLevel] ?? $rowLevel) ?></span></td>
                        <td><?= h($row['source']) ?></td>
                        <td><?= h($row['message']) ?></td>
                        <td><?= h($row['order_number'] ?: '—') ?></td>
                        <td><?= h($row['response_code'] ?: '—') ?></td>
                        <td>
                            <?php if ($context !== ''): ?>
                                <details>
                                    <summary>Открыть</summary>
                                    <pre style="max-width:520px; max-height:260px; overflow:auto; white-space:pre-wrap;"><?= h($context) ?></pre>
                                </details>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="audit-pagination" aria-label="Страницы логов ошибок">
                <?php if ($page > 1): $queryParams['page'] = $page - 1; ?>
                    <a class="btn btn-ghost btn-sm" href="/admin/error_logs.php?<?= h(http_build_query($queryParams)) ?>">Назад</a>
                <?php endif; ?>
                <span>Страница <?= $page ?> из <?= $totalPages ?></span>
                <?php if ($page < $totalPages): $queryParams['page'] = $page + 1; ?>
                    <a class="btn btn-ghost btn-sm" href="/admin/error_logs.php?<?= h(http_build_query($queryParams)) ?>">Вперёд</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    var form = document.getElementById('errorLogsFilters');
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
        Array.prototype.forEach.call(form.querySelectorAll('.error-log-typeahead'), function (element) {
            element.innerHTML = '';
            element.classList.remove('is-visible');
        });
    }

    function refreshTable() {
        var params = new URLSearchParams(new FormData(form));
        params.set('page', '1');
        var focused = document.activeElement;
        var focusedName = focused && focused.name ? focused.name : '';
        var caret = focused && typeof focused.selectionStart === 'number' ? focused.selectionStart : null;
        fetch('/admin/error_logs.php?' + params.toString(), {credentials: 'same-origin'})
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var currentSummary = document.querySelector('.audit-summary');
                var nextSummary = parsed.querySelector('.audit-summary');
                var currentTable = document.querySelector('.audit-table-wrap');
                var nextTable = parsed.querySelector('.audit-table-wrap');
                var currentPagination = document.querySelector('.audit-pagination');
                var nextPagination = parsed.querySelector('.audit-pagination');
                if (currentSummary && nextSummary) currentSummary.replaceWith(nextSummary);
                if (currentTable && nextTable) currentTable.replaceWith(nextTable);
                if (currentPagination && nextPagination) currentPagination.replaceWith(nextPagination);
                if (currentPagination && !nextPagination) currentPagination.remove();
                window.history.replaceState({}, '', '/admin/error_logs.php?' + params.toString());
                if (focusedName) {
                    var restored = form.querySelector('[name="' + focusedName + '"]');
                    if (restored) {
                        restored.focus();
                        if (caret !== null && typeof restored.setSelectionRange === 'function') restored.setSelectionRange(caret, caret);
                    }
                }
            })
            .catch(function () {});
    }

    function loadSuggestions(input) {
        var type = input.getAttribute('data-error-suggest');
        var query = input.value.trim();
        var box = form.querySelector('[data-error-suggestions="' + type + '"]');
        if (!box || !type || query === '') {
            hideSuggestions();
            return;
        }
        var params = new URLSearchParams();
        params.set('suggest', type);
        params.set('q', query);
        params.set('date_from', form.querySelector('[name="date_from"]').value);
        params.set('date_to', form.querySelector('[name="date_to"]').value);
        fetch('/admin/error_logs.php?' + params.toString(), {credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                box.innerHTML = '';
                if (!data || !data.success || !data.data || !data.data.length) {
                    box.classList.remove('is-visible');
                    return;
                }
                data.data.forEach(function (value) {
                    var item = document.createElement('div');
                    item.className = 'error-log-typeahead__item';
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
            })
            .catch(function () { box.classList.remove('is-visible'); });
    }

    Array.prototype.forEach.call(form.querySelectorAll('input[type="search"], select, input[type="date"]'), function (control) {
        if (control.matches('input[type="search"]')) {
            control.addEventListener('input', function () {
                loadSuggestions(control);
                refreshTable();
            });
            control.addEventListener('focus', function () { loadSuggestions(control); });
            control.addEventListener('blur', function () { window.setTimeout(hideSuggestions, 150); });
            control.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    hideSuggestions();
                    refreshTable();
                }
            });
        } else {
            control.addEventListener('change', function () {
                hideSuggestions();
                refreshTable();
            });
        }
    });
    document.addEventListener('mousedown', function (event) {
        if (!form.contains(event.target)) hideSuggestions();
    });
})();
</script>

<style>
    .error-log-filter-field { position:relative; }
    .error-log-typeahead { position:absolute; left:0; right:0; top:100%; z-index:1400; display:none; max-height:220px; overflow:auto; background:#fff; border:1px solid #dbe3ec; border-radius:6px; box-shadow:0 8px 18px rgba(15,23,42,.12); }
    .error-log-typeahead.is-visible { display:block; }
    .error-log-typeahead__item { padding:8px 10px; cursor:pointer; border-bottom:1px solid #f1f5f9; }
    .error-log-typeahead__item:hover { background:#f8fafc; }
    .error-logs-page .audit-table th, .error-logs-page .audit-table td { vertical-align:top; }
</style>
