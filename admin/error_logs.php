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
$requestedPerPage = (int)($_GET['per_page'] ?? 50);
$perPage = in_array($requestedPerPage, [50, 100, 250, 500], true) ? $requestedPerPage : 50;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');

$actionMessage = '';
$actionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!validate_csrf($csrfToken)) {
        $actionError = 'Не удалось подтвердить действие. Обновите страницу и повторите попытку.';
    } else {
        $bulkAction = trim((string)($_POST['action'] ?? ''));
        try {
            if ($bulkAction === 'delete_selected') {
                $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
                $ids = array_values(array_filter(array_unique($ids), static function (int $id): bool {
                    return $id > 0;
                }));
                if (empty($ids)) {
                    $actionError = 'Выберите хотя бы одну запись.';
                } else {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("DELETE FROM error_logs WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $deleted = $stmt->rowCount();
                    $actionMessage = 'Удалено записей: ' . (int)$deleted . '.';
                    if (function_exists('audit_log_event')) {
                        audit_log_event($pdo, 'error_logs.deleted', 'error_logs', null, null, [], [
                            'mode' => 'selected',
                            'count' => (int)$deleted,
                        ]);
                    }
                }
            } elseif ($bulkAction === 'purge_retention') {
                $retentionDays = function_exists('settings_get_value')
                    ? (int)settings_get_value($pdo, 'system.error_log_retention_days', 90)
                    : 90;
                $retentionDays = max(1, min(3650, $retentionDays));
                $cutoff = (new DateTimeImmutable('now'))->modify('-' . $retentionDays . ' days')->format('Y-m-d H:i:s');
                $stmt = $pdo->prepare('DELETE FROM error_logs WHERE created_at < :cutoff');
                $stmt->execute([':cutoff' => $cutoff]);
                $deleted = $stmt->rowCount();
                $actionMessage = 'Удалено старых записей: ' . (int)$deleted . '. Срок хранения: ' . $retentionDays . ' дн.';
                if (function_exists('audit_log_event')) {
                    audit_log_event($pdo, 'error_logs.deleted', 'error_logs', null, null, [], [
                        'mode' => 'retention',
                        'count' => (int)$deleted,
                        'retention_days' => $retentionDays,
                    ]);
                }
            } elseif (in_array($bulkAction, ['purge_1_day', 'purge_3_days', 'purge_7_days'], true)) {
                $daysByAction = [
                    'purge_1_day' => 1,
                    'purge_3_days' => 3,
                    'purge_7_days' => 7,
                ];
                $days = $daysByAction[$bulkAction];
                $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
                $stmt = $pdo->prepare('DELETE FROM error_logs WHERE created_at < :cutoff');
                $stmt->execute([':cutoff' => $cutoff]);
                $deleted = $stmt->rowCount();
                $actionMessage = 'Удалено записей старше ' . $days . ' дн.: ' . (int)$deleted . '.';
                if (function_exists('audit_log_event')) {
                    audit_log_event($pdo, 'error_logs.deleted', 'error_logs', null, null, [], [
                        'mode' => 'period',
                        'count' => (int)$deleted,
                        'days' => $days,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            $actionError = 'Не удалось очистить логи ошибок.';
            error_log('[ERROR LOGS] Ошибка очистки: ' . $exception->getMessage());
        }
    }
}

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
    'per_page' => $perPage,
];

$levelLabels = [
    'critical' => 'Критическая',
    'error' => 'Ошибка',
    'warning' => 'Предупреждение',
    'info' => 'Информация',
];
$levelClasses = [
    'critical' => 'error-level-badge--critical',
    'error' => 'error-level-badge--error',
    'warning' => 'error-level-badge--warning',
    'info' => 'error-level-badge--info',
];
$retentionDays = function_exists('settings_get_value')
    ? max(1, min(3650, (int)settings_get_value($pdo, 'system.error_log_retention_days', 90)))
    : 90;

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
    <?php if ($actionMessage !== ''): ?>
        <div class="card alert settings-alert settings-alert--success"><?= h($actionMessage) ?></div>
    <?php endif; ?>
    <?php if ($actionError !== ''): ?>
        <div class="card alert alert--danger"><?= h($actionError) ?></div>
    <?php endif; ?>
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
                <label for="error-per-page">Ошибок на странице</label>
                <select id="error-per-page" name="per_page" class="form-control">
                    <?php foreach ([50, 100, 250, 500] as $pageSize): ?>
                        <option value="<?= $pageSize ?>" <?= $perPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="error-level">Уровень</label>
                <div class="error-level-picker">
                    <select id="error-level" name="level" class="form-control">
                        <option value="">Все уровни</option>
                        <?php foreach ($levelLabels as $code => $label): ?>
                            <option value="<?= h($code) ?>" <?= $level === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="error-level-badge" class="error-level-badge <?= $level !== '' ? h($levelClasses[$level] ?? '') : 'error-level-badge--all' ?>">
                        <?= h($level !== '' ? ($levelLabels[$level] ?? $level) : 'Все') ?>
                    </span>
                </div>
            </div>
            <div class="error-log-presets" aria-label="Быстрый период">
                <span class="error-log-presets__label">Период:</span>
                <button type="button" class="btn btn-ghost btn-sm error-log-preset" data-date-preset="today">Сегодня</button>
                <button type="button" class="btn btn-ghost btn-sm error-log-preset" data-date-preset="week">За неделю</button>
                <button type="button" class="btn btn-ghost btn-sm error-log-preset" data-date-preset="month">За месяц</button>
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
            <div class="audit-filters__actions error-log-actions">
                <a class="btn btn-ghost" href="/admin/error_logs.php">Сбросить</a>
                <button type="submit" form="errorLogsBulkForm" name="action" value="delete_selected" class="btn btn-danger" onclick="return confirm('Удалить выбранные записи логов?');">Удалить выбранные</button>
                <button type="submit" form="errorLogsBulkForm" name="action" value="purge_retention" class="btn btn-secondary" onclick="return confirm('Удалить все логи старше установленного срока хранения?');">Очистить старше <?= (int)$retentionDays ?> дн.</button>
                <button type="submit" form="errorLogsBulkForm" name="action" value="purge_1_day" class="btn btn-secondary" onclick="return confirm('Удалить логи старше 1 суток?');">Удалить за сутки</button>
                <button type="submit" form="errorLogsBulkForm" name="action" value="purge_3_days" class="btn btn-secondary" onclick="return confirm('Удалить логи старше 3 суток?');">Удалить за 3 суток</button>
                <button type="submit" form="errorLogsBulkForm" name="action" value="purge_7_days" class="btn btn-secondary" onclick="return confirm('Удалить логи старше недели?');">Удалить за неделю</button>
            </div>
        </form>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card alert alert--danger"><?= h($errorText) ?></div>
    <?php else: ?>
        <form id="errorLogsBulkForm" method="post" action="/admin/error_logs.php">
            <input type="hidden" name="csrf_token" value="<?= h((string)($_SESSION['csrf_token'] ?? '')) ?>">
        <div class="card audit-table-wrap">
            <table class="admin-table audit-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="error-log-select-all" aria-label="Выбрать все"></th>
                        <th>Дата и время</th>
                        <th>Уровень</th>
                        <th>Источник</th>
                        <th>Сообщение</th>
                        <th>Краткое описание</th>
                        <th>Заказ</th>
                        <th>Код ответа</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="audit-empty">Ошибок за выбранный период нет.</td></tr>
                <?php else: foreach ($rows as $row):
                    $rowLevel = (string)($row['level'] ?? 'error');
                    $context = $formatContext($row['context'] ?? '');
                    $description = system_error_short_description($row);
                ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>" class="error-log-select" aria-label="Выбрать ошибку"></td>
                        <td><?= h(date('d.m.Y H:i:s', strtotime((string)$row['created_at']))) ?></td>
                        <td><span class="error-level-badge <?= h($levelClasses[$rowLevel] ?? 'error-level-badge--error') ?>"><?= h($levelLabels[$rowLevel] ?? $rowLevel) ?></span></td>
                        <td><?= h($row['source']) ?></td>
                        <td>
                            <button type="button" class="error-log-open" data-error-level="<?= h($levelLabels[$rowLevel] ?? $rowLevel) ?>" data-error-source="<?= h($row['source']) ?>" data-error-message="<?= h($row['message']) ?>" data-error-description="<?= h($description) ?>" data-error-order="<?= h($row['order_number'] ?: '—') ?>" data-error-code="<?= h($row['response_code'] ?: '—') ?>" data-error-context="<?= h($context) ?>">
                                <?= h($row['message']) ?>
                            </button>
                        </td>
                        <td><?= h($description) ?></td>
                        <td><?= h($row['order_number'] ?: '—') ?></td>
                        <td><?= h($row['response_code'] ?: '—') ?></td>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm error-log-open" data-error-level="<?= h($levelLabels[$rowLevel] ?? $rowLevel) ?>" data-error-source="<?= h($row['source']) ?>" data-error-message="<?= h($row['message']) ?>" data-error-description="<?= h($description) ?>" data-error-order="<?= h($row['order_number'] ?: '—') ?>" data-error-code="<?= h($row['response_code'] ?: '—') ?>" data-error-context="<?= h($context) ?>">Открыть</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $previousQuery = $queryParams;
        $previousQuery['page'] = max(1, $page - 1);
        $nextQuery = $queryParams;
        $nextQuery['page'] = min($totalPages, $page + 1);
        ?>
        <div class="error-logs-pager" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
            <div class="audit-summary">Найдено ошибок: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
            <div style="display:flex; align-items:center; gap:8px;">
                <?php if ($page > 1): ?>
                    <a class="btn btn-ghost" href="/admin/error_logs.php?<?= h(http_build_query($previousQuery)) ?>" aria-label="Предыдущая страница">←</a>
                <?php else: ?>
                    <button class="btn btn-ghost" type="button" disabled aria-label="Предыдущая страница">←</button>
                <?php endif; ?>
                <span><?= (int)$page ?> / <?= (int)$totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-ghost" href="/admin/error_logs.php?<?= h(http_build_query($nextQuery)) ?>" aria-label="Следующая страница">→</a>
                <?php else: ?>
                    <button class="btn btn-ghost" type="button" disabled aria-label="Следующая страница">→</button>
                <?php endif; ?>
            </div>
        </div>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<div id="errorLogModal" class="error-log-modal" aria-hidden="true">
    <div class="error-log-modal__backdrop" data-error-modal-close></div>
    <section class="error-log-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="errorLogModalTitle">
        <div class="error-log-modal__header">
            <h2 id="errorLogModalTitle">Детали ошибки</h2>
            <button type="button" class="btn btn-ghost btn-sm" data-error-modal-close aria-label="Закрыть">Закрыть</button>
        </div>
        <div class="error-log-modal__body">
            <div class="error-log-modal__summary" id="errorLogModalDescription"></div>
            <dl class="error-log-modal__meta">
                <div><dt>Уровень</dt><dd id="errorLogModalLevel"></dd></div>
                <div><dt>Источник</dt><dd id="errorLogModalSource"></dd></div>
                <div><dt>Заказ</dt><dd id="errorLogModalOrder"></dd></div>
                <div><dt>Код ответа</dt><dd id="errorLogModalCode"></dd></div>
            </dl>
            <h3>Техническое сообщение</h3>
            <p id="errorLogModalMessage" class="error-log-modal__message"></p>
            <h3>Полный контекст и комментарии сервера</h3>
            <pre id="errorLogModalContext" class="error-log-modal__context"></pre>
        </div>
    </section>
</div>

<script>
(function () {
    var form = document.getElementById('errorLogsFilters');
    if (!form) return;

    var modal = document.getElementById('errorLogModal');
    var levelLabels = {
        '': 'Все',
        critical: 'Критическая',
        error: 'Ошибка',
        warning: 'Предупреждение',
        info: 'Информация'
    };
    var levelClasses = {
        '': 'error-level-badge--all',
        critical: 'error-level-badge--critical',
        error: 'error-level-badge--error',
        warning: 'error-level-badge--warning',
        info: 'error-level-badge--info'
    };
    function updateLevelBadge() {
        var select = document.getElementById('error-level');
        var badge = document.getElementById('error-level-badge');
        if (!select || !badge) return;
        var value = select.value || '';
        badge.className = 'error-level-badge ' + levelClasses[value];
        badge.textContent = levelLabels[value];
    }
    updateLevelBadge();

    function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function applyDatePreset(preset) {
        var from = form.querySelector('[name="date_from"]');
        var to = form.querySelector('[name="date_to"]');
        if (!from || !to) return;
        var today = new Date();
        var start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        if (preset === 'week') {
            start.setDate(start.getDate() - 6);
        } else if (preset === 'month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        }
        from.value = formatDate(start);
        to.value = formatDate(today);
        refreshTable();
    }

    function closeErrorModal() {
        if (!modal) return;
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
    }
    function openErrorModal(button) {
        if (!modal) return;
        var fields = {
            errorLogModalDescription: button.dataset.errorDescription || 'Описание отсутствует.',
            errorLogModalLevel: button.dataset.errorLevel || '—',
            errorLogModalSource: button.dataset.errorSource || '—',
            errorLogModalOrder: button.dataset.errorOrder || '—',
            errorLogModalCode: button.dataset.errorCode || '—',
            errorLogModalMessage: button.dataset.errorMessage || '—',
            errorLogModalContext: button.dataset.errorContext || 'Контекст отсутствует.'
        };
        Object.keys(fields).forEach(function (id) {
            var element = document.getElementById(id);
            if (element) element.textContent = fields[id];
        });
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
    }
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('.error-log-open');
        if (button) {
            openErrorModal(button);
            return;
        }
        if (event.target.closest && event.target.closest('[data-error-modal-close]')) closeErrorModal();
    });
    document.addEventListener('change', function (event) {
        if (event.target.id === 'error-log-select-all') {
            Array.prototype.forEach.call(document.querySelectorAll('#errorLogsBulkForm .error-log-select'), function (checkbox) {
                checkbox.checked = event.target.checked;
            });
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeErrorModal();
    });

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
                var currentTable = document.querySelector('.audit-table-wrap');
                var nextTable = parsed.querySelector('.audit-table-wrap');
                var currentPagination = document.querySelector('.error-logs-pager');
                var nextPagination = parsed.querySelector('.error-logs-pager');
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
            if (control.id === 'error-level') {
                control.addEventListener('change', updateLevelBadge);
            }
            control.addEventListener('change', function () {
                hideSuggestions();
                refreshTable();
            });
        }
    });
    document.addEventListener('mousedown', function (event) {
        if (!form.contains(event.target)) hideSuggestions();
    });
    Array.prototype.forEach.call(form.querySelectorAll('[data-date-preset]'), function (button) {
        button.addEventListener('click', function () {
            applyDatePreset(button.getAttribute('data-date-preset') || 'today');
        });
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
    .error-log-presets { grid-column:1 / -1; display:flex; align-items:center; flex-wrap:wrap; gap:7px; }
    .error-log-presets__label { color:#52647e; font-size:12px; font-weight:600; margin-right:2px; }
    .error-log-actions { grid-column:1 / -1; flex-wrap:wrap; }
    .error-log-preset.is-active { background:#e0ecff; border-color:#2563eb; color:#1d4ed8; }
    .error-level-picker { display:flex; align-items:center; gap:8px; }
    .error-level-picker select { min-width:0; flex:1; }
    .error-level-badge { display:inline-flex; align-items:center; gap:5px; min-height:24px; padding:3px 8px; border-radius:999px; white-space:nowrap; font-size:11px; font-weight:700; }
    .error-level-badge::before { content:''; width:7px; height:7px; border-radius:50%; background:currentColor; }
    .error-level-badge--all { background:#f1f5f9; color:#64748b; }
    .error-level-badge--critical { background:#fee2e2; color:#991b1b; }
    .error-level-badge--error { background:#fee2e2; color:#b91c1c; }
    .error-level-badge--warning { background:#fef3c7; color:#b45309; }
    .error-level-badge--info { background:#dbeafe; color:#1d4ed8; }
    .error-log-open { max-width:360px; padding:0; border:0; background:transparent; color:#1d4ed8; text-align:left; cursor:pointer; font:inherit; text-decoration:underline; }
    .error-log-modal { position:fixed; inset:0; z-index:2400; display:none; align-items:center; justify-content:center; padding:20px; }
    .error-log-modal.is-visible { display:flex; }
    .error-log-modal__backdrop { position:absolute; inset:0; background:rgba(15,23,42,.52); }
    .error-log-modal__dialog { position:relative; z-index:1; width:min(820px, 100%); max-height:90vh; overflow:hidden; border-radius:14px; background:#fff; box-shadow:0 20px 70px rgba(15,23,42,.28); }
    .error-log-modal__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e7eb; }
    .error-log-modal__header h2 { margin:0; font-size:20px; }
    .error-log-modal__body { max-height:calc(90vh - 70px); overflow:auto; padding:20px; }
    .error-log-modal__summary { padding:14px 16px; border-left:4px solid #b91c1c; border-radius:6px; background:#fef2f2; color:#7f1d1d; font-weight:600; line-height:1.5; }
    .error-log-modal__meta { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px 18px; margin:18px 0; }
    .error-log-modal__meta div { padding:10px 12px; border-radius:8px; background:#f8fafc; }
    .error-log-modal__meta dt { color:#64748b; font-size:12px; }
    .error-log-modal__meta dd { margin:3px 0 0; color:#172b4d; font-weight:600; word-break:break-word; }
    .error-log-modal__body h3 { margin:18px 0 8px; font-size:14px; color:#334155; }
    .error-log-modal__message { margin:0; color:#334155; white-space:pre-wrap; }
    .error-log-modal__context { max-height:330px; margin:0; padding:14px; overflow:auto; border-radius:8px; background:#0f172a; color:#e2e8f0; font-size:12px; line-height:1.5; white-space:pre-wrap; word-break:break-word; }
    @media (max-width:600px) { .error-log-modal__meta { grid-template-columns:1fr; } .error-log-modal__dialog { max-height:94vh; } .error-log-modal__body { max-height:calc(94vh - 70px); } }
</style>
