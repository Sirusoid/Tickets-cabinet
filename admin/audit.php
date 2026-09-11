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
$panel_subtitle = 'История операций сотрудников и системных изменений';
$page_styles = ['/assets/css/audit.css'];

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$action = trim((string)($_GET['action'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

$where = ['a.created_at BETWEEN :date_from AND :date_to'];
$params = [
    ':date_from' => $dateFrom . ' 00:00:00',
    ':date_to' => $dateTo . ' 23:59:59',
];
if ($action !== '') {
    $where[] = 'a.action = :action';
    $params[':action'] = $action;
}
if ($search !== '') {
    $where[] = '(a.entity_type LIKE :search OR a.entity_name LIKE :search OR a.ip_address LIKE :search OR u.username LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$total = 0;
$rows = [];
$actions = [];
$errorText = '';
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT a.*, COALESCE(NULLIF(u.full_name, ''), u.username, 'Система') AS actor_name
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE {$whereSql}
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $actionStmt = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action');
    $actions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $errorText = 'Не удалось загрузить журнал действий.';
    error_log('[AUDIT] Ошибка загрузки журнала: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($total / $perPage));
$queryParams = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'action' => $action,
    'search' => $search,
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full audit-page">
    <div class="card audit-filters">
        <form method="get" class="audit-filters__form">
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
                        <option value="<?= h($actionOption) ?>" <?= $action === $actionOption ? 'selected' : '' ?>><?= h($actionOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="audit-search">Поиск</label>
                <input id="audit-search" type="search" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Пользователь, объект, IP">
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
        <div class="audit-summary">Записей: <strong><?= number_format($total, 0, '.', ' ') ?></strong></div>
        <div class="card audit-table-wrap">
            <table class="admin-table audit-table">
                <thead>
                    <tr>
                        <th>Дата и время</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Объект</th>
                        <th>IP</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="audit-empty">Записей за выбранный период нет.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $details = [];
                            foreach (['before_data', 'after_data'] as $field) {
                                if (!empty($row[$field])) {
                                    $decoded = json_decode((string)$row[$field], true);
                                    $details[$field === 'before_data' ? 'До' : 'После'] = is_array($decoded) ? $decoded : $row[$field];
                                }
                            }
                        ?>
                            <tr>
                                <td><?= h(date('d.m.Y H:i:s', strtotime((string)$row['created_at']))) ?></td>
                                <td><?= h($row['actor_name'] ?? 'Система') ?></td>
                                <td><span class="audit-action"><?= h($row['action']) ?></span></td>
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

        <?php if ($totalPages > 1): ?>
            <nav class="audit-pagination" aria-label="Страницы журнала">
                <?php if ($page > 1): $queryParams['page'] = $page - 1; ?>
                    <a class="btn btn-ghost btn-sm" href="/admin/audit.php?<?= h(http_build_query($queryParams)) ?>">Назад</a>
                <?php endif; ?>
                <span>Страница <?= $page ?> из <?= $totalPages ?></span>
                <?php if ($page < $totalPages): $queryParams['page'] = $page + 1; ?>
                    <a class="btn btn-ghost btn-sm" href="/admin/audit.php?<?= h(http_build_query($queryParams)) ?>">Вперёд</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
