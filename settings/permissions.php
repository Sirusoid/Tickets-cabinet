<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/settings_manager.php';

if (!function_exists('is_admin') || !is_admin()) {
		http_response_code(403);
		echo 'Доступ запрещен';
		exit;
}

$settingsPageKey = 'permissions';
$settingsPages = settings_pages_map();
$pageConfig = $settingsPages[$settingsPageKey] ?? [
		'title' => 'Роли и права доступа',
		'subtitle' => 'Матрица разрешений для модулей системы',
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

$modules = [
		'dashboard' => ['label' => 'Панель', 'path' => '/dashboard.php'],
		'cash' => ['label' => 'Касса', 'path' => '/cash/index.php'],
		'schedule' => ['label' => 'Расписание', 'path' => '/schedule/list.php'],
		'events' => ['label' => 'Мероприятия', 'path' => '/events/list.php'],
		'tickets' => ['label' => 'Билеты', 'path' => '/tickets/list.php'],
		'customers' => ['label' => 'Клиенты', 'path' => '/customers/list.php'],
		'actors' => ['label' => 'Актёры', 'path' => '/actors/list.php'],
		'halls' => ['label' => 'Залы', 'path' => '/halls/list.php'],
		'settings_general' => ['label' => 'Настройки: Общие', 'path' => '/settings/general.php'],
		'settings_interface' => ['label' => 'Настройки: Интерфейс', 'path' => '/settings/interface.php'],
		'settings_tickets' => ['label' => 'Настройки: Билеты', 'path' => '/settings/tickets.php'],
		'settings_discounts' => ['label' => 'Настройки: Скидки', 'path' => '/settings/discounts.php'],
		'settings_payment' => ['label' => 'Настройки: Эквайр', 'path' => '/settings/payment.php'],
		'settings_notifications' => ['label' => 'Настройки: Уведомления', 'path' => '/settings/notifications.php'],
		'settings_security' => ['label' => 'Настройки: Безопасность', 'path' => '/settings/security.php'],
		'settings_users' => ['label' => 'Настройки: Пользователи', 'path' => '/settings/users.php'],
		'settings_permissions' => ['label' => 'Настройки: Роли и права', 'path' => '/settings/permissions.php'],
];

$defaultMatrix = [
		'admin' => [],
		'manager' => [
				'dashboard' => true,
				'cash' => true,
				'schedule' => true,
				'events' => true,
				'tickets' => true,
				'customers' => true,
				'actors' => true,
				'halls' => true,
				'settings_general' => true,
				'settings_interface' => true,
				'settings_tickets' => true,
				'settings_discounts' => true,
				'settings_payment' => false,
				'settings_notifications' => true,
				'settings_security' => false,
				'settings_users' => false,
				'settings_permissions' => false,
		],
		'cashier' => [
				'dashboard' => true,
				'cash' => true,
				'schedule' => true,
				'events' => false,
				'tickets' => true,
				'customers' => true,
				'actors' => false,
				'halls' => false,
				'settings_general' => false,
				'settings_interface' => false,
				'settings_tickets' => false,
				'settings_discounts' => false,
				'settings_payment' => false,
				'settings_notifications' => false,
				'settings_security' => false,
				'settings_users' => false,
				'settings_permissions' => false,
		],
];

foreach (array_keys($modules) as $moduleKey) {
		$defaultMatrix['admin'][$moduleKey] = true;
}

$saveSuccess = null;
$saveErrors = [];
$matrix = $defaultMatrix;

if (!isset($pdo) || !($pdo instanceof PDO)) {
		$saveErrors[] = 'Подключение к базе данных недоступно.';
} else {
		$rawMatrix = (string)settings_get_value($pdo, 'security.role_permissions', '');
		$savedMatrix = settings_decode_json_value($rawMatrix, []);
		if (!empty($savedMatrix)) {
				foreach ($roles as $roleCode => $roleLabel) {
						$roleData = isset($savedMatrix[$roleCode]) && is_array($savedMatrix[$roleCode])
								? $savedMatrix[$roleCode]
								: [];
						foreach ($modules as $moduleKey => $moduleConfig) {
								$matrix[$roleCode][$moduleKey] = array_key_exists($moduleKey, $roleData)
									? !empty($roleData[$moduleKey])
									: !empty($matrix[$roleCode][$moduleKey]);
						}
				}
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				$postedPerms = isset($_POST['perms']) && is_array($_POST['perms']) ? $_POST['perms'] : [];
				foreach ($roles as $roleCode => $roleLabel) {
						$selected = isset($postedPerms[$roleCode]) && is_array($postedPerms[$roleCode])
								? $postedPerms[$roleCode]
								: [];
						$selectedMap = array_fill_keys($selected, true);
						foreach ($modules as $moduleKey => $moduleConfig) {
								$matrix[$roleCode][$moduleKey] = !empty($selectedMap[$moduleKey]);
						}
				}

				foreach (array_keys($modules) as $moduleKey) {
						$matrix['admin'][$moduleKey] = true;
				}

				try {
						$payload = json_encode($matrix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
						settings_upsert_value(
								$pdo,
								'security.role_permissions',
								'Матрица прав доступа',
								$payload,
								'json',
								'security',
								'Управляет доступом ролей к разделам интерфейса и меню',
								1,
								40
						);
						$saveSuccess = 'Права доступа сохранены.';
				} catch (Throwable $e) {
						$saveErrors[] = 'Ошибка при сохранении: ' . $e->getMessage();
				}
		}
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="settings-shell">
	<?php if ($saveSuccess): ?>
		<div class="alert settings-alert settings-alert--success"><?= h($saveSuccess) ?></div>
	<?php endif; ?>

	<?php if (!empty($saveErrors)): ?>
		<div class="alert alert--danger"><?= h(implode(' ', $saveErrors)) ?></div>
	<?php endif; ?>

	<form method="post" class="form-grid settings-form">
		<section class="form-block settings-block">
			<h2 class="settings-block__title">Матрица ролей</h2>
			<p class="settings-hint">Права применяются к отображению разделов в меню и к доступу в страницы настроек. У роли Администратор доступ всегда полный.</p>

			<div class="settings-table-wrap">
				<table class="table table--compact settings-permissions-table">
					<thead>
						<tr>
							<th>Раздел</th>
							<?php foreach ($roles as $roleCode => $roleLabel): ?>
								<th><?= h($roleLabel) ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($modules as $moduleKey => $moduleConfig): ?>
							<tr>
								<td>
									<div class="settings-permission-label">
										<span><?= h($moduleConfig['label']) ?></span>
										<small><?= h($moduleConfig['path']) ?></small>
									</div>
								</td>
								<?php foreach ($roles as $roleCode => $roleLabel): ?>
									<td>
										<?php if ($roleCode === 'admin'): ?>
											<label class="settings-bool settings-bool--compact settings-bool--fixed">
												<input type="checkbox" checked disabled>
												<span>Всегда</span>
											</label>
										<?php else: ?>
											<label class="settings-bool settings-bool--compact">
												<input type="checkbox" name="perms[<?= h($roleCode) ?>][]" value="<?= h($moduleKey) ?>" <?= !empty($matrix[$roleCode][$moduleKey]) ? 'checked' : '' ?>>
												<span><?= !empty($matrix[$roleCode][$moduleKey]) ? 'Да' : 'Нет' ?></span>
											</label>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="form-block settings-block">
			<h2 class="settings-block__title">Результат по ролям</h2>
			<div class="settings-role-cards">
				<?php foreach ($roles as $roleCode => $roleLabel): ?>
					<?php
						$allowed = [];
						foreach ($modules as $moduleKey => $moduleConfig) {
								if (!empty($matrix[$roleCode][$moduleKey])) {
										$allowed[] = $moduleConfig['label'];
								}
						}
					?>
					<article class="settings-role-card">
						<h3><?= h($roleLabel) ?></h3>
						<p>Доступных разделов: <?= count($allowed) ?></p>
						<div class="settings-role-card__list"><?= h(implode(', ', $allowed)) ?></div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<div class="form-actions-bottom">
			<button type="submit" class="btn btn-primary">Сохранить права</button>
		</div>
	</form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
