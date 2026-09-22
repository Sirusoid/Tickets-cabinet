<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/settings_manager.php';

if (!function_exists('is_admin') || !is_admin()) {
		http_response_code(403);
		echo 'Доступ запрещен';
		exit;
}

$settingsPageKey = 'users';
$settingsPages = settings_pages_map();
$pageConfig = $settingsPages[$settingsPageKey] ?? [
		'title' => 'Пользователи',
		'subtitle' => 'Управление учетными записями сотрудников',
];

$page_styles = $page_styles ?? [];
$page_styles[] = '/assets/css/forms.css';
$page_styles[] = '/assets/css/settings.css';
$page_scripts = $page_scripts ?? [];
$page_scripts[] = '/assets/js/settings_users.js';

$use_sidebar = true;
$active_menu = 'settings';
$page_title_meta = $pageConfig['title'];
$panel_title = $pageConfig['title'];
$panel_subtitle = $pageConfig['subtitle'];

$roles = [
		'admin' => 'Администратор',
		'manager' => 'Менеджер',
		'cashier' => 'Кассир',
		'scanner' => 'Сканер',
];

$saveSuccess = null;
$saveErrors = [];
$action = trim((string)($_POST['action'] ?? ''));

if (!isset($pdo) || !($pdo instanceof PDO)) {
		$saveErrors[] = 'Подключение к базе данных недоступно.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		try {
				if ($action === 'create_user') {
						$username = trim((string)($_POST['username'] ?? ''));
						$fullName = trim((string)($_POST['full_name'] ?? ''));
						$email = trim((string)($_POST['email'] ?? ''));
						$role = trim((string)($_POST['role'] ?? 'manager'));
						$password = (string)($_POST['password'] ?? '');
						$isActive = !empty($_POST['is_active']) ? 1 : 0;

						if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
								$saveErrors[] = 'Логин должен содержать 3-50 символов: латиница, цифры, ., _, -.';
						}
						if ($fullName === '' || strlen($fullName) < 3) {
								$saveErrors[] = 'ФИО должно содержать минимум 3 символа.';
						}
						if (!isset($roles[$role])) {
								$saveErrors[] = 'Указана недопустимая роль.';
						}
						$saveErrors = array_merge($saveErrors, password_policy_errors($password));
						if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
								$saveErrors[] = 'Некорректный email.';
						}

						if (empty($saveErrors)) {
								$exists = db_fetch_one('SELECT id FROM users WHERE username = ? OR (? <> "" AND email = ?) LIMIT 1', [$username, $email, $email]);
								if ($exists) {
										$saveErrors[] = 'Пользователь с таким логином или email уже существует.';
								} else {
										$stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role, is_active, email, created_at, updated_at, password_changed_at) VALUES (:username, :password_hash, :full_name, :role, :is_active, :email, NOW(), NOW(), NOW())');
										$stmt->execute([
												':username' => $username,
												':password_hash' => password_hash($password, PASSWORD_DEFAULT),
												':full_name' => $fullName,
												':role' => $role,
												':is_active' => $isActive,
												':email' => $email !== '' ? $email : null,
										]);
										if (function_exists('audit_log_event')) {
											audit_log_event($pdo, 'user.create', 'user', (int)$pdo->lastInsertId(), $username, [], ['role' => $role, 'is_active' => $isActive]);
										}
										$saveSuccess = 'Пользователь создан.';
								}
						}
				} elseif ($action === 'update_user') {
						$userId = (int)($_POST['user_id'] ?? 0);
						$fullName = trim((string)($_POST['full_name'] ?? ''));
						$email = trim((string)($_POST['email'] ?? ''));
						$role = trim((string)($_POST['role'] ?? 'manager'));
						$isActive = !empty($_POST['is_active']) ? 1 : 0;
						$newPassword = (string)($_POST['new_password'] ?? '');
						$currentUserId = (int)($_SESSION['user']['id'] ?? 0);

						if ($userId <= 0) {
								$saveErrors[] = 'Некорректный идентификатор пользователя.';
						}
						if ($fullName === '' || strlen($fullName) < 3) {
								$saveErrors[] = 'ФИО должно содержать минимум 3 символа.';
						}
						if (!isset($roles[$role])) {
								$saveErrors[] = 'Указана недопустимая роль.';
						}
						if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
								$saveErrors[] = 'Некорректный email.';
						}
						if ($userId === $currentUserId && $isActive === 0) {
								$saveErrors[] = 'Нельзя деактивировать собственную учетную запись.';
						}
						if ($newPassword !== '') {
								$saveErrors = array_merge($saveErrors, password_policy_errors($newPassword));
						}

						if (empty($saveErrors)) {
								$dup = db_fetch_one('SELECT id FROM users WHERE id <> ? AND email = ? LIMIT 1', [$userId, $email]);
								if ($email !== '' && $dup) {
										$saveErrors[] = 'Этот email уже используется другим пользователем.';
								} else {
										$updateFields = 'full_name = :full_name, email = :email, role = :role, is_active = :is_active, updated_at = NOW()';
										$updateParams = [
												':full_name' => $fullName,
												':email' => $email !== '' ? $email : null,
												':role' => $role,
												':is_active' => $isActive,
												':id' => $userId,
										];
										if ($newPassword !== '') {
												$updateFields .= ', password_hash = :password_hash, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL';
												$updateParams[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
										}
										$stmt = $pdo->prepare('UPDATE users SET ' . $updateFields . ' WHERE id = :id');
										$stmt->execute($updateParams);
										if (function_exists('audit_log_event')) {
											audit_log_event($pdo, 'user.update', 'user', $userId, $fullName, [], ['role' => $role, 'is_active' => $isActive]);
											if ($newPassword !== '') {
												audit_log_event($pdo, 'auth.password_changed', 'user', $userId, $fullName, [], ['password_changed' => true]);
											}
										}
										if ($userId === $currentUserId && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
												$_SESSION['user']['full_name'] = $fullName;
												$_SESSION['user']['role'] = $role;
										}
										$saveSuccess = 'Пользователь обновлен.';
								}
						}
				} elseif ($action === 'reset_password') {
						$userId = (int)($_POST['user_id'] ?? 0);
						$newPassword = (string)($_POST['new_password'] ?? '');

						if ($userId <= 0) {
								$saveErrors[] = 'Некорректный идентификатор пользователя.';
						}
						$saveErrors = array_merge($saveErrors, password_policy_errors($newPassword));

						if (empty($saveErrors)) {
								$targetUser = db_fetch_one('SELECT username FROM users WHERE id = ? LIMIT 1', [$userId]);
								if (!$targetUser) {
										$saveErrors[] = 'Пользователь не найден.';
								} else {
										$stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id');
										$stmt->execute([
												':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
												':id' => $userId,
										]);
										if (function_exists('audit_log_event')) {
												audit_log_event($pdo, 'auth.password_changed', 'user', $userId, (string)$targetUser['username'], [], [
														'password_changed' => true,
												]);
										}
										$saveSuccess = 'Пароль обновлен.';
								}
						}
				} elseif ($action === 'delete_user') {
						$userId = (int)($_POST['user_id'] ?? 0);
						$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
						if ($userId <= 0) {
								$saveErrors[] = 'Некорректный идентификатор пользователя.';
						} elseif ($userId === $currentUserId) {
								$saveErrors[] = 'Нельзя удалить собственную учетную запись.';
						} else {
								$targetUser = db_fetch_one('SELECT username, full_name FROM users WHERE id = ? LIMIT 1', [$userId]);
								if (!$targetUser) {
										$saveErrors[] = 'Пользователь не найден.';
								} else {
										$stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
										$stmt->execute([':id' => $userId]);
										if (function_exists('audit_log_event')) {
												audit_log_event($pdo, 'user.delete', 'user', $userId, (string)$targetUser['username'], [], ['full_name_removed' => true]);
										}
										$saveSuccess = 'Пользователь удален.';
								}
						}
				}
		} catch (Throwable $e) {
				$saveErrors[] = 'Ошибка при сохранении: ' . $e->getMessage();
		}
}

$page_inline_scripts = $page_inline_scripts ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset_password') {
	$toastMessage = $saveSuccess ?: (!empty($saveErrors) ? implode(' ', $saveErrors) : 'Не удалось изменить пароль.');
	$toastType = $saveSuccess ? 'success' : 'error';
	$page_inline_scripts[] = 'if (typeof window.showToast === "function") { window.showToast(' . json_encode($toastMessage, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', ' . json_encode($toastType) . ', { duration: 4500 }); }';
}

$usersPage = max(1, (int)($_GET['page'] ?? 1));
$usersPerPageParam = (int)($_GET['per_page'] ?? 25);
$usersPerPage = in_array($usersPerPageParam, [25, 50, 100, 500], true) ? $usersPerPageParam : 25;
$usersTotal = 0;
$usersTotalPages = 1;
$usersOffset = 0;
$users = [];
if (isset($pdo) && $pdo instanceof PDO) {
		$usersTotalRow = db_fetch_one('SELECT COUNT(*) AS total FROM users');
		$usersTotal = (int)($usersTotalRow['total'] ?? 0);
		$usersTotalPages = max(1, (int)ceil($usersTotal / $usersPerPage));
		$usersPage = min($usersPage, $usersTotalPages);
		$usersOffset = ($usersPage - 1) * $usersPerPage;
		$users = db_fetch_all('SELECT id, username, full_name, email, role, is_active, last_login_at, created_at, password_changed_at FROM users ORDER BY id ASC LIMIT ' . (int)$usersPerPage . ' OFFSET ' . (int)$usersOffset);
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="settings-shell settings-users-page">
	<?php if ($saveSuccess): ?>
		<div class="alert settings-alert settings-alert--success"><?= h($saveSuccess) ?></div>
	<?php endif; ?>

	<?php if (!empty($saveErrors)): ?>
		<div class="alert alert--danger"><?= h(implode(' ', $saveErrors)) ?></div>
	<?php endif; ?>

	<section class="form-block settings-users-create-compact">
		<div>
			<h2 class="settings-block__title">Добавить пользователя</h2>
			<p class="settings-hint">Создайте учётную запись сотрудника в отдельном окне.</p>
		</div>
		<button type="button" class="btn btn-primary" data-create-user-open>Добавить пользователя</button>
	</section>

	<section class="form-block">
		<h2 class="settings-block__title">Пользователи системы</h2>
		<div class="table-shell settings-table-wrap">
			<table class="table table--compact settings-table-users">
				<thead>
					<tr>
						<th>ID</th>
						<th>Логин</th>
						<th>ФИО</th>
						<th>Email</th>
						<th>Роль</th>
						<th>Активен</th>
						<th>Действия</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($users)): ?>
						<tr>
									<td colspan="7">Пользователи не найдены.</td>
						</tr>
					<?php endif; ?>
					<?php foreach ($users as $userRow): ?>
								<tr class="settings-user-row" data-user-id="<?= (int)$userRow['id'] ?>" data-user-username="<?= h($userRow['username']) ?>" data-user-full-name="<?= h($userRow['full_name']) ?>" data-user-email="<?= h((string)$userRow['email']) ?>" data-user-role="<?= h($userRow['role']) ?>" data-user-active="<?= (int)$userRow['is_active'] ?>">
							<td><?= (int)$userRow['id'] ?></td>
							<td><?= h($userRow['username']) ?></td>
									<td><?= h($userRow['full_name']) ?></td>
									<td><?= h((string)$userRow['email']) ?: '—' ?></td>
									<td><?= h($roles[$userRow['role']] ?? $userRow['role']) ?></td>
									<td><span class="settings-user-status <?= (int)$userRow['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int)$userRow['is_active'] === 1 ? 'Да' : 'Нет' ?></span></td>
							<td>
								<form method="post" class="settings-inline-form settings-inline-form--delete" data-confirm-submit="Удалить пользователя?">
									<input type="hidden" name="action" value="delete_user">
									<input type="hidden" name="user_id" value="<?= (int)$userRow['id'] ?>">
									<button type="submit" class="btn btn-danger btn-sm">Удалить</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<nav class="table-pagination" aria-label="Страницы пользователей">
				<div class="table-pagination__summary">Найдено: <strong><?= number_format($usersTotal, 0, '.', ' ') ?></strong></div>
				<label class="table-pagination__size">На странице
					<select class="form-control" data-list-per-page data-list-path="/settings/users.php" aria-label="Количество пользователей на странице">
						<?php foreach ([25, 50, 100, 500] as $pageSize): ?>
							<option value="<?= $pageSize ?>" <?= $usersPerPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<div class="table-pagination__controls">
					<?php $previousPage = max(1, $usersPage - 1); $nextPage = min($usersTotalPages, $usersPage + 1); ?>
					<a class="btn btn-ghost btn-sm" href="/settings/users.php?page=<?= $previousPage ?>&per_page=<?= $usersPerPage ?>" aria-label="Предыдущая страница" <?= $usersPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>
					<span><?= (int)$usersPage ?> / <?= (int)$usersTotalPages ?></span>
					<a class="btn btn-ghost btn-sm" href="/settings/users.php?page=<?= $nextPage ?>&per_page=<?= $usersPerPage ?>" aria-label="Следующая страница" <?= $usersPage >= $usersTotalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
				</div>
			</nav>
		</div>
	</section>
</div>

<div id="settingsUserModal" class="settings-user-modal" aria-hidden="true">
	<div class="settings-user-modal__backdrop" data-user-modal-close></div>
	<section class="settings-user-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="settingsUserModalTitle">
		<div class="settings-user-modal__header">
			<div>
				<h2 id="settingsUserModalTitle">Настройки пользователя</h2>
				<div id="settingsUserModalUsername" class="settings-user-modal__username"></div>
			</div>
			<button type="button" class="btn btn-ghost btn-sm" data-user-modal-close aria-label="Закрыть">✕</button>
		</div>
		<form method="post" class="settings-user-modal__form">
			<input type="hidden" name="action" value="update_user">
			<input type="hidden" name="user_id" id="settingsUserId">
			<div class="settings-user-modal__grid">
				<div class="form-group"><label for="settingsUserFullName">ФИО</label><input id="settingsUserFullName" type="text" name="full_name" required></div>
				<div class="form-group"><label for="settingsUserEmail">Email</label><input id="settingsUserEmail" type="email" name="email"></div>
				<div class="form-group"><label for="settingsUserRole">Роль</label><select id="settingsUserRole" name="role"><?php foreach ($roles as $roleCode => $roleLabel): ?><option value="<?= h($roleCode) ?>"><?= h($roleLabel) ?></option><?php endforeach; ?></select></div>
				<div class="form-group settings-user-modal__active"><label class="settings-bool"><input id="settingsUserActive" type="checkbox" name="is_active" value="1"><span>Активная учетная запись</span></label></div>
				<div class="form-group settings-user-modal__password-field"><label for="settingsUserNewPassword">Новый пароль (необязательно)</label><input id="settingsUserNewPassword" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Оставьте пустым, если менять не нужно"><small>Пароль обновится вместе с кнопкой «Сохранить изменения».</small></div>
			</div>
			<div class="settings-user-modal__actions"><button type="button" class="btn btn-ghost" data-user-modal-close>Отмена</button><button type="submit" class="btn btn-primary">Сохранить изменения</button></div>
		</form>
	</section>
</div>

<div id="settingsCreateUserModal" class="settings-user-modal" aria-hidden="true">
	<div class="settings-user-modal__backdrop" data-create-user-close></div>
	<section class="settings-user-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="settingsCreateUserModalTitle">
		<div class="settings-user-modal__header">
			<div>
				<h2 id="settingsCreateUserModalTitle">Добавить пользователя</h2>
				<div class="settings-user-modal__username">Новая учётная запись сотрудника</div>
			</div>
			<button type="button" class="btn btn-ghost btn-sm" data-create-user-close aria-label="Закрыть">✕</button>
		</div>
		<form method="post" class="settings-user-modal__form">
			<input type="hidden" name="action" value="create_user">
			<div class="settings-user-modal__grid">
				<div class="form-group"><label for="create_username">Логин</label><input id="create_username" type="text" name="username" required></div>
				<div class="form-group"><label for="create_full_name">ФИО</label><input id="create_full_name" type="text" name="full_name" required></div>
				<div class="form-group"><label for="create_email">Email</label><input id="create_email" type="email" name="email" placeholder="user@example.com"></div>
				<div class="form-group"><label for="create_role">Роль</label><select id="create_role" name="role"><?php foreach ($roles as $roleCode => $roleLabel): ?><option value="<?= h($roleCode) ?>"><?= h($roleLabel) ?></option><?php endforeach; ?></select></div>
				<div class="form-group"><label for="create_password">Пароль</label><input id="create_password" type="password" name="password" minlength="8" autocomplete="new-password" required></div>
				<div class="form-group settings-user-modal__active"><label class="settings-bool"><input type="checkbox" name="is_active" value="1" checked><span>Активный пользователь</span></label></div>
			</div>
			<div class="settings-user-modal__actions"><button type="button" class="btn btn-ghost" data-create-user-close>Отмена</button><button type="submit" class="btn btn-primary">Создать пользователя</button></div>
		</form>
	</section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
