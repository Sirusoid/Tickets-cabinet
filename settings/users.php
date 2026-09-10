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

$use_sidebar = true;
$active_menu = 'settings';
$page_title_meta = $pageConfig['title'];
$panel_title = $pageConfig['title'];
$panel_subtitle = $pageConfig['subtitle'];

$roles = [
		'admin' => 'Администратор',
		'manager' => 'Менеджер',
		'cashier' => 'Кассир',
];

$saveSuccess = null;
$saveErrors = [];

if (!isset($pdo) || !($pdo instanceof PDO)) {
		$saveErrors[] = 'Подключение к базе данных недоступно.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$action = trim((string)($_POST['action'] ?? ''));

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
										$saveSuccess = 'Пользователь создан.';
								}
						}
				} elseif ($action === 'update_user') {
						$userId = (int)($_POST['user_id'] ?? 0);
						$fullName = trim((string)($_POST['full_name'] ?? ''));
						$email = trim((string)($_POST['email'] ?? ''));
						$role = trim((string)($_POST['role'] ?? 'manager'));
						$isActive = !empty($_POST['is_active']) ? 1 : 0;
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

						if (empty($saveErrors)) {
								$dup = db_fetch_one('SELECT id FROM users WHERE id <> ? AND email = ? LIMIT 1', [$userId, $email]);
								if ($email !== '' && $dup) {
										$saveErrors[] = 'Этот email уже используется другим пользователем.';
								} else {
										$stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email, role = :role, is_active = :is_active, updated_at = NOW() WHERE id = :id');
										$stmt->execute([
												':full_name' => $fullName,
												':email' => $email !== '' ? $email : null,
												':role' => $role,
												':is_active' => $isActive,
												':id' => $userId,
										]);
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
								$stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id');
								$stmt->execute([
										':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
										':id' => $userId,
								]);
								$saveSuccess = 'Пароль обновлен.';
						}
				}
		} catch (Throwable $e) {
				$saveErrors[] = 'Ошибка при сохранении: ' . $e->getMessage();
		}
}

$users = [];
if (isset($pdo) && $pdo instanceof PDO) {
		$users = db_fetch_all('SELECT id, username, full_name, email, role, is_active, last_login_at, created_at, password_changed_at FROM users ORDER BY id ASC');
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

	<section class="form-block">
		<h2 class="settings-block__title">Добавить пользователя</h2>
		<form method="post" class="settings-users-create">
			<input type="hidden" name="action" value="create_user">
			<div class="settings-users-create__grid">
				<div class="form-group">
					<label for="create_username">Логин</label>
					<input id="create_username" type="text" name="username" required>
				</div>
				<div class="form-group">
					<label for="create_full_name">ФИО</label>
					<input id="create_full_name" type="text" name="full_name" required>
				</div>
				<div class="form-group">
					<label for="create_email">Email</label>
					<input id="create_email" type="email" name="email" placeholder="user@example.com">
				</div>
				<div class="form-group">
					<label for="create_role">Роль</label>
					<select id="create_role" name="role">
						<?php foreach ($roles as $roleCode => $roleLabel): ?>
							<option value="<?= h($roleCode) ?>"><?= h($roleLabel) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label for="create_password">Пароль</label>
					<input id="create_password" type="password" name="password" minlength="8" required>
				</div>
				<div class="form-group settings-users-create__check">
					<label class="settings-bool">
						<input type="checkbox" name="is_active" value="1" checked>
						<span>Активный пользователь</span>
					</label>
				</div>
			</div>
			<div class="form-actions-bottom">
				<button type="submit" class="btn btn-primary">Создать пользователя</button>
			</div>
		</form>
	</section>

	<section class="form-block">
		<h2 class="settings-block__title">Пользователи системы</h2>
		<div class="settings-table-wrap">
			<table class="table table--compact settings-table-users">
				<thead>
					<tr>
						<th>ID</th>
						<th>Логин</th>
						<th>ФИО</th>
						<th>Email</th>
						<th>Роль</th>
						<th>Активен</th>
						<th>Последний вход</th>
						<th>Действия</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($users)): ?>
						<tr>
							<td colspan="8">Пользователи не найдены.</td>
						</tr>
					<?php endif; ?>
					<?php foreach ($users as $userRow): ?>
						<tr>
							<td><?= (int)$userRow['id'] ?></td>
							<td><?= h($userRow['username']) ?></td>
							<td>
								<form method="post" class="settings-inline-form">
									<input type="hidden" name="action" value="update_user">
									<input type="hidden" name="user_id" value="<?= (int)$userRow['id'] ?>">
									<input type="text" name="full_name" value="<?= h($userRow['full_name']) ?>" required>
							</td>
							<td>
									<input type="email" name="email" value="<?= h((string)$userRow['email']) ?>">
							</td>
							<td>
									<select name="role">
										<?php foreach ($roles as $roleCode => $roleLabel): ?>
											<option value="<?= h($roleCode) ?>" <?= $roleCode === $userRow['role'] ? 'selected' : '' ?>><?= h($roleLabel) ?></option>
										<?php endforeach; ?>
									</select>
							</td>
							<td>
									<label class="settings-bool settings-bool--compact">
										<input type="checkbox" name="is_active" value="1" <?= (int)$userRow['is_active'] === 1 ? 'checked' : '' ?>>
										<span><?= (int)$userRow['is_active'] === 1 ? 'Да' : 'Нет' ?></span>
									</label>
							</td>
							<td>
								<?= h((string)($userRow['last_login_at'] ?? '')) !== '' ? h((string)$userRow['last_login_at']) : '—' ?>
							</td>
							<td>
									<div class="settings-inline-actions">
										<button type="submit" class="btn btn-secondary btn-sm">Сохранить</button>
									</div>
								</form>

								<form method="post" class="settings-inline-form settings-inline-form--reset">
									<input type="hidden" name="action" value="reset_password">
									<input type="hidden" name="user_id" value="<?= (int)$userRow['id'] ?>">
									<input type="password" name="new_password" minlength="8" placeholder="Новый пароль" required>
									<button type="submit" class="btn btn-ghost btn-sm">Сброс пароля</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
