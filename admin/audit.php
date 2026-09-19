<?php
require_once __DIR__ . '/../init.php';
require_login();

if (!is_admin()) {
    http_response_code(403);
    exit('Доступ запрещён.');
}

$use_sidebar = true;
$active_menu = 'audit';
$page_title_meta = 'Журнал действий';
$panel_title = 'Журнал действий';
$panel_subtitle = 'Продажи, возвраты, входы, выгрузки и изменения в кабинете';
$page_styles = ['/assets/css/audit.css'];

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$action = trim((string)($_GET['action'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$requestedPerPage = (int)($_GET['per_page'] ?? 50);
$perPage = in_array($requestedPerPage, [50, 100, 250, 500], true) ? $requestedPerPage : 50;
$cleanupSuccess = null;
$cleanupError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'cleanup_audit') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $cleanupError = 'Неверный CSRF-токен. Обновите страницу и повторите действие.';
    } else {
        $cleanupPeriod = (string)($_POST['cleanup_period'] ?? '');
        $cleanupDays = [
            '1' => 1,
            '3' => 3,
            '7' => 7,
            '30' => 30,
        ][$cleanupPeriod] ?? null;
        try {
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                throw new RuntimeException('Database connection unavailable.');
            }
            $pdo->beginTransaction();
            foreach (['audit_logs', 'cash_audit_log'] as $table) {
                $tableStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
                $tableStmt->execute([':table' => $table]);
                if (!(bool)$tableStmt->fetchColumn()) {
                    continue;
                }
                if ($cleanupPeriod === 'all') {
                    $pdo->exec('DELETE FROM `' . $table . '`');
                } elseif ($cleanupDays !== null) {
                    $pdo->exec('DELETE FROM `' . $table . '` WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . (int)$cleanupDays . ' DAY)');
                } else {
                    throw new InvalidArgumentException('Не выбран период очистки.');
                }
            }
            $pdo->commit();
            $cleanupSuccess = $cleanupPeriod === 'all'
                ? 'Журнал действий полностью очищен.'
                : 'Удалены записи журнала старше ' . (int)$cleanupDays . ' ' . ($cleanupDays === 1 ? 'суток' : 'суток') . '.';
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $cleanupError = 'Не удалось очистить журнал действий.';
            error_log('[AUDIT] Ошибка очистки журнала: ' . $exception->getMessage());
        }
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

$auditConditions = ['a.created_at BETWEEN :audit_date_from AND :audit_date_to'];
$cashConditions = ['c.created_at BETWEEN :cash_date_from AND :cash_date_to'];
$params = [
    ':audit_date_from' => $dateFrom . ' 00:00:00',
    ':audit_date_to' => $dateTo . ' 23:59:59',
    ':cash_date_from' => $dateFrom . ' 00:00:00',
    ':cash_date_to' => $dateTo . ' 23:59:59',
];
if ($action !== '') {
    $auditConditions[] = 'a.action = :audit_action';
    $cashConditions[] = 'c.action = :cash_action';
    $params[':audit_action'] = $action;
    $params[':cash_action'] = $action;
}
if ($search !== '') {
    $auditConditions[] = '(a.entity_type LIKE :audit_search OR a.entity_name LIKE :audit_search OR a.ip_address LIKE :audit_search OR u.username LIKE :audit_search)';
    $cashConditions[] = '(c.target_type LIKE :cash_search OR c.target_id LIKE :cash_search OR cu.username LIKE :cash_search)';
    $params[':audit_search'] = '%' . $search . '%';
    $params[':cash_search'] = '%' . $search . '%';
}
$auditWhere = implode(' AND ', $auditConditions);
$cashWhere = implode(' AND ', $cashConditions);

$rows = [];
$total = 0;
$actions = [];
$errorText = '';
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }
    $unionSql = "SELECT
            'audit' AS source,
            a.id,
            a.user_id,
            a.performed_by_type,
            a.action,
            a.entity_type,
            a.entity_name,
            a.entity_id,
            a.before_data,
            a.after_data,
            a.ip_address,
            a.user_agent,
            a.created_at,
            COALESCE(NULLIF(u.full_name, ''), u.username, 'Система') AS actor_name
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE {$auditWhere}
        UNION ALL
        SELECT
            'cash' AS source,
            c.id,
            c.user_id,
            'staff' AS performed_by_type,
            c.action,
            c.target_type AS entity_type,
            NULL AS entity_name,
            CAST(c.target_id AS UNSIGNED) AS entity_id,
            NULL AS before_data,
            c.details AS after_data,
            NULL AS ip_address,
            NULL AS user_agent,
            c.created_at,
            COALESCE(NULLIF(cu.full_name, ''), cu.username, 'Система') AS actor_name
        FROM cash_audit_log c
        LEFT JOIN users cu ON cu.id = c.user_id
        WHERE {$cashWhere}";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$unionSql}) AS audit_union");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT * FROM ({$unionSql}) AS audit_union
        ORDER BY created_at DESC, id DESC
        LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $actionsStmt = $pdo->query("SELECT action FROM audit_logs
        UNION
        SELECT action FROM cash_audit_log
        ORDER BY action");
    $actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $errorText = 'Не удалось загрузить журнал действий.';
    error_log('[AUDIT] Ошибка загрузки журнала: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($total / $perPage));
$jsonRows = [];
foreach ($rows as $row) {
    $details = [];
    if (!empty($row['before_data'])) $details['До'] = json_decode((string)$row['before_data'], true) ?: $row['before_data'];
    if (!empty($row['after_data'])) $details['После'] = json_decode((string)$row['after_data'], true) ?: $row['after_data'];
    $jsonRows[] = [
        'created_at' => $row['created_at'],
        'source' => $row['source'] === 'cash' ? 'Касса' : 'Система',
        'actor_name' => $row['actor_name'] ?? 'Система',
        'action' => $row['action'],
        'action_label' => audit_action_label($row['action']),
        'entity' => trim((string)($row['entity_type'] ?? '') . ($row['entity_name'] ? ': ' . $row['entity_name'] : '') . ($row['entity_id'] ? ' #' . $row['entity_id'] : '')) ?: '—',
        'ip_address' => $row['ip_address'] ?? '—',
        'details' => $details,
    ];
}
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $errorText === '',
        'message' => $errorText,
        'rows' => $jsonRows,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$queryParams = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'action' => $action,
    'search' => $search,
    'per_page' => $perPage,
];
$page_scripts = ['/assets/js/audit.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full audit-page">
    <?php if ($cleanupSuccess !== null): ?>
        <div class="alert settings-alert settings-alert--success"><?= h($cleanupSuccess) ?></div>
    <?php endif; ?>
    <?php if ($cleanupError !== null): ?>
        <div class="alert alert--danger"><?= h($cleanupError) ?></div>
    <?php endif; ?>

    <div class="card audit-cleanup">
        <div class="audit-cleanup__title">Очистка журнала</div>
        <div class="audit-cleanup__actions">
            <?php foreach ([['1', 'За 1 сутки'], ['3', 'За 3 суток'], ['7', 'За неделю'], ['30', 'За 1 месяц']] as $cleanupOption): ?>
                <form method="post" onsubmit="return confirm('Удалить выбранные старые записи журнала?');">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="cleanup_audit">
                    <input type="hidden" name="cleanup_period" value="<?= h($cleanupOption[0]) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= h($cleanupOption[1]) ?></button>
                </form>
            <?php endforeach; ?>
            <form method="post" onsubmit="return confirm('Полностью очистить журнал действий? Это действие нельзя отменить.');">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="cleanup_audit">
                <input type="hidden" name="cleanup_period" value="all">
                <button class="btn btn-danger btn-sm" type="submit">Очистить полностью</button>
            </form>
        </div>
    </div>

    <div class="card audit-filters">
        <form id="auditFilters" method="get" class="audit-filters__form">
            <div>
                <label for="audit-date-from">Дата от</label>
                <input id="audit-date-from" type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
            </div>
            <div>
                <label for="audit-date-to">Дата до</label>
                <input id="audit-date-to" type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
            </div>
            <div>
                <label for="audit-action">Действие</label>
                <select id="audit-action" name="action" class="form-control">
                    <option value="">Все действия</option>
                    <?php foreach ($actions as $actionOption): ?>
                        <option value="<?= h($actionOption) ?>" <?= $action === $actionOption ? 'selected' : '' ?>><?= h(audit_action_label($actionOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="audit-search">Поиск</label>
                <input id="audit-search" type="search" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Пользователь, объект, ID, IP">
            </div>
            <div>
                <label for="audit-per-page">Записей на странице</label>
                <select id="audit-per-page" name="per_page" class="form-control">
                    <?php foreach ([50, 100, 250, 500] as $pageSize): ?>
                        <option value="<?= $pageSize ?>" <?= $perPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="audit-filters__actions">
                <a class="btn btn-ghost" href="/admin/audit.php">Сбросить</a>
                <button class="btn btn-primary" type="submit">Показать</button>
            </div>
        </form>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card alert alert--danger"><?= h($errorText) ?></div>
    <?php else: ?>
        <div id="auditSummary" class="audit-summary">Найдено записей: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
        <div class="card audit-table-wrap">
            <table class="admin-table audit-table">
                <thead>
                    <tr>
                        <th>Дата и время</th>
                        <th>Источник</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Объект</th>
                        <th>IP</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody id="auditTbody">
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="audit-empty">Записей за выбранный период нет.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $details = [];
                            if (!empty($row['before_data'])) {
                                $details['До'] = json_decode((string)$row['before_data'], true) ?: $row['before_data'];
                            }
                            if (!empty($row['after_data'])) {
                                $details['После'] = json_decode((string)$row['after_data'], true) ?: $row['after_data'];
                            }
                        ?>
                            <tr>
                                <td><?= h(date('d.m.Y H:i:s', strtotime((string)$row['created_at']))) ?></td>
                                <td><?= $row['source'] === 'cash' ? 'Касса' : 'Система' ?></td>
                                <td><?= h($row['actor_name'] ?? 'Система') ?></td>
                                <td><span class="audit-action"><?= h(audit_action_label($row['action'])) ?></span><small class="audit-action-code"><?= h($row['action']) ?></small></td>
                                <td><?= h(trim((string)($row['entity_type'] ?? '') . ($row['entity_name'] ? ': ' . $row['entity_name'] : '') . ($row['entity_id'] ? ' #' . $row['entity_id'] : '')) ?: '—') ?></td>
                                <td><?= h($row['ip_address'] ?? '—') ?></td>
                                <td>
                                    <?php if ($details): ?>
                                        <details>
                                            <summary>Показать</summary>
                                            <pre><?= h(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                                        </details>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <nav id="auditPagination" class="audit-pagination" aria-label="Страницы журнала<?= $totalPages <= 1 ? ' is-empty' : '' ?>">
                <button id="auditPrev" type="button" class="btn btn-ghost btn-sm" <?= $page <= 1 ? 'disabled' : '' ?>>←</button>
                <span id="auditPageLabel">Страница <?= $page ?> из <?= $totalPages ?></span>
                <button id="auditNext" type="button" class="btn btn-ghost btn-sm" <?= $page >= $totalPages ? 'disabled' : '' ?>>→</button>
        </nav>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
