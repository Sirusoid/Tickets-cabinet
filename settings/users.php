<?php
require_once __DIR__ . '/../init.php';
require_login();

if (!function_exists('is_admin') || !is_admin()) {
    http_response_code(403);
    echo 'Доступ запрещён.';
    exit;
}

$roles = [
    'admin' => 'Администратор',
    'manager' => 'Менеджер',
    'cashier' => 'Кассир',
    'scanner' => 'Сканер',
];
$initialSearch = trim((string)($_GET['search'] ?? ''));
$initialRole = trim((string)($_GET['role'] ?? ''));
$initialActive = trim((string)($_GET['active'] ?? ''));
$initialPerPage = (int)($_GET['per_page'] ?? 25);
$initialPerPage = in_array($initialPerPage, [25, 50, 100, 500], true) ? $initialPerPage : 25;

$use_sidebar = true;
$active_menu = 'settings';
$page_title_meta = 'Пользователи';
$panel_title = 'Пользователи';
$panel_subtitle = 'Управление учётными записями сотрудников';
$settingsCssPath = __DIR__ . '/../assets/css/settings.css';
$settingsUsersJsPath = __DIR__ . '/../assets/js/settings_users.js';
$page_styles = [
    '/assets/css/forms.css',
    '/assets/css/settings.css?v=' . (is_file($settingsCssPath) ? (string)filemtime($settingsCssPath) : '1'),
    '/assets/css/customers.css',
];
$page_scripts = [
    '/assets/js/settings_users.js?v=' . (is_file($settingsUsersJsPath) ? (string)filemtime($settingsUsersJsPath) : '1'),
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full customers-page settings-users-page" style="padding:0;">
    <section class="form-block settings-users-create-compact">
        <div>
            <h2 class="settings-block__title">Добавить пользователя</h2>
            <p class="settings-hint">Заполните поля и нажмите «Создать пользователя».</p>
        </div>
        <form id="settingsUserCreateForm" class="settings-user-create-inline">
            <label class="settings-user-create-field">Логин
                <input type="text" name="username" class="form-control" placeholder="Например, kassir_2" aria-label="Логин" required>
            </label>
            <label class="settings-user-create-field">ФИО
                <input type="text" name="full_name" class="form-control" placeholder="Имя и фамилия" aria-label="ФИО" required>
            </label>
            <label class="settings-user-create-field">Email
                <input type="email" name="email" class="form-control" placeholder="user@example.com" aria-label="Email">
            </label>
            <label class="settings-user-create-field">Роль
                <select name="role" class="form-control" aria-label="Роль">
                    <?php foreach ($roles as $roleCode => $roleLabel): ?>
                        <option value="<?= h($roleCode) ?>"><?= h($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="settings-user-create-field">Пароль
                <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" placeholder="Минимум 8 символов" aria-label="Пароль" required>
            </label>
            <label class="settings-bool settings-user-create-active"><input type="checkbox" name="is_active" value="1" checked><span>Активная запись</span></label>
            <button type="submit" class="btn btn-primary">Создать пользователя</button>
        </form>
    </section>

    <div class="schedule-panel compact customers-filter-panel">
        <div class="card-title">Фильтры</div>
        <form id="settingsUsersFilters" class="compact-grid customers-filters" method="get">
            <div>
                <div class="customers-field-wrap">
                    <input id="settings-users-search" name="search" type="search" class="form-control" value="<?= h($initialSearch) ?>" placeholder="Логин, ФИО или Email" aria-label="Поиск пользователя" autocomplete="off">
                    <div id="settings-users-suggestions" class="typeahead-dropdown customers-suggestions" role="listbox"></div>
                </div>
            </div>
            <div>
                <select id="settings-users-role-filter" name="role" class="form-control" aria-label="Роль">
                    <option value="">Все роли</option>
                    <?php foreach ($roles as $roleCode => $roleLabel): ?>
                        <option value="<?= h($roleCode) ?>" <?= $initialRole === $roleCode ? 'selected' : '' ?>><?= h($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select id="settings-users-active-filter" name="active" class="form-control" aria-label="Статус">
                    <option value="">Все статусы</option>
                    <option value="1" <?= $initialActive === '1' ? 'selected' : '' ?>>Активные</option>
                    <option value="0" <?= $initialActive === '0' ? 'selected' : '' ?>>Неактивные</option>
                </select>
            </div>
            <div class="customers-filter-bottom">
                <div class="customers-per-page">
                    <label for="settingsUsersPerPage">На странице</label>
                    <select id="settingsUsersPerPage" name="per_page" class="form-control">
                        <?php foreach ([25, 50, 100, 500] as $pageSize): ?>
                            <option value="<?= $pageSize ?>" <?= $initialPerPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="customers-filters__actions">
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <button type="button" id="settingsUsersReset" class="btn btn-ghost">Сброс</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card customers-results-card" id="settingsUsersResultsCard">
        <div class="table-shell customers-table-wrap">
            <table class="table admin-table table--compact customers-table settings-users-table">
                <thead>
                    <tr>
                        <th style="width:8%;">№</th>
                        <th style="width:8%;">ID</th>
                        <th>Логин</th>
                        <th>ФИО</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Активен</th>
                        <th class="actions-col">Действия</th>
                    </tr>
                </thead>
                <tbody id="settingsUsersTbody">
                    <tr><td colspan="8" class="customers-empty">Загрузка...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="settingsUsersPager" class="table-pagination customers-pager">
            <div id="settingsUsersSummary" class="table-pagination__summary customers-summary">Пользователей: —</div>
            <div class="table-pagination__controls customers-pager__controls">
                <button id="settingsUsersPrevPage" type="button" class="btn btn-ghost" aria-label="Предыдущая страница" disabled>←</button>
                <span id="settingsUsersCurrentPage">1 / 1</span>
                <button id="settingsUsersNextPage" type="button" class="btn btn-ghost" aria-label="Следующая страница" disabled>→</button>
            </div>
        </div>
    </div>
</div>

<?php
$render_settings_user_modal = true;
$settings_user_roles = $roles;
?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
