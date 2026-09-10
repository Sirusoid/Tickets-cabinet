<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/settings_manager.php';

$settingsPageKey = 'interface';
$settingsPages = settings_pages_map();
$pageConfig = $settingsPages[$settingsPageKey] ?? [
		'title' => 'Настройки интерфейса',
		'subtitle' => 'Тема, логотип и визуальные элементы',
		'categories' => ['ui'],
];

$page_styles = $page_styles ?? [];
$page_styles[] = '/assets/css/forms.css';
$page_styles[] = '/assets/css/settings.css';

$use_sidebar = true;
$active_menu = 'settings';
$page_title_meta = $pageConfig['title'];
$panel_title = $pageConfig['title'];
$panel_subtitle = $pageConfig['subtitle'];

$saveSuccess = null;
$saveErrors = [];
$rows = [];
$rowsByKey = [];

if (!isset($pdo) || !($pdo instanceof PDO)) {
		$saveErrors[] = 'Подключение к базе данных недоступно.';
} else {
		$rows = settings_fetch_rows_by_categories($pdo, ['ui']);
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				list($ok, $saveErrors, $updatedCount) = settings_save_rows($pdo, $rows, $_POST, $_FILES);
				if ($ok) {
						$rows = settings_fetch_rows_by_categories($pdo, ['ui']);
						$saveSuccess = $updatedCount > 0
								? 'Настройки интерфейса сохранены: ' . $updatedCount . ' знач.'
								: 'Изменений для сохранения нет.';
				}
		}
}

$rowsByKey = settings_rows_by_key($rows);

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

	<form method="post" enctype="multipart/form-data" class="form-grid settings-form interface-settings-form">
		<section class="form-block settings-block">
			<h2 class="settings-block__title">Визуальные параметры</h2>
			<div class="settings-fields settings-fields--single">
				<?php foreach ($rows as $row): ?>
					<?php
						$type = $row['type'] ?? 'string';
						$value = settings_value_for_form($row);
						$inputName = settings_input_name($row);
						$fileInputName = settings_file_input_name($row);
						$removeInputName = settings_remove_input_name($row);
						$isEditable = (int)($row['is_editable'] ?? 0) === 1;
					?>
					<div class="form-group settings-field<?= !$isEditable ? ' is-readonly' : '' ?>">
						<label for="setting_<?= (int)$row['id'] ?>"><?= h($row['label']) ?></label>

						<?php if ($type === 'color'): ?>
							<div class="settings-color-control">
								<input id="setting_<?= (int)$row['id'] ?>" type="color" name="<?= h($inputName) ?>" value="<?= h($value !== '' ? $value : '#000000') ?>" <?= !$isEditable ? 'disabled' : '' ?>>
								<span class="settings-color-control__value"><?= h($value !== '' ? $value : '#000000') ?></span>
							</div>
						<?php elseif ($type === 'image' || $type === 'file'): ?>
							<?php if (!empty($row['value'])): ?>
								<div class="settings-file-current interface-file-current">
									<?php if ($type === 'image'): ?>
										<img src="<?= h((string)$row['value']) ?>" alt="<?= h($row['label']) ?>">
									<?php else: ?>
										<a href="<?= h((string)$row['value']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$row['value']) ?></a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<input id="setting_<?= (int)$row['id'] ?>" type="file" name="<?= h($fileInputName) ?>" <?= !$isEditable ? 'disabled' : '' ?>>
							<?php if ($isEditable && !empty($row['value'])): ?>
								<label class="settings-remove-file">
									<input type="checkbox" name="<?= h($removeInputName) ?>" value="1"> Удалить текущее изображение
								</label>
							<?php endif; ?>
						<?php elseif ($type === 'json' || $type === 'text'): ?>
							<textarea id="setting_<?= (int)$row['id'] ?>" name="<?= h($inputName) ?>" rows="8" <?= !$isEditable ? 'readonly' : '' ?>><?= h($value) ?></textarea>
						<?php else: ?>
							<input id="setting_<?= (int)$row['id'] ?>" type="text" name="<?= h($inputName) ?>" value="<?= h($value) ?>" <?= !$isEditable ? 'readonly' : '' ?>>
						<?php endif; ?>

						<?php if (!empty($row['description'])): ?>
							<small class="settings-field__description"><?= h($row['description']) ?></small>
						<?php endif; ?>
						<small class="settings-field__meta">Ключ: <?= h($row['key']) ?> | Тип: <?= h($type) ?></small>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<div class="form-actions-bottom">
			<button type="submit" class="btn btn-primary">Сохранить интерфейс</button>
		</div>
	</form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>