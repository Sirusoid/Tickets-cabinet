<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/settings_manager.php';

$settingsPageKey = $settingsPageKey ?? 'general';
$settingsPages = settings_pages_map();
$pageConfig = $settingsPages[$settingsPageKey] ?? null;

if (!$pageConfig) {
    http_response_code(404);
    echo 'Страница настроек не найдена';
    exit;
}

$page_styles = $page_styles ?? [];
$page_styles[] = '/assets/css/forms.css';
$page_styles[] = '/assets/css/settings.css';

$use_sidebar = true;
$active_menu = 'settings';
$page_title_meta = $pageConfig['title'] ?? 'Настройки';
$panel_title = $pageConfig['title'] ?? 'Настройки';
$panel_subtitle = $pageConfig['subtitle'] ?? '';
$panel_actions = [];

$saveSuccess = null;
$saveErrors = [];
$rows = [];
$categories = $pageConfig['categories'] ?? [];
$settingKeys = $pageConfig['keys'] ?? [];

if (!isset($pdo) || !($pdo instanceof PDO)) {
  $saveErrors[] = 'Подключение к базе данных недоступно.';
}

if (($pageConfig['mode'] ?? 'settings') === 'settings' && empty($saveErrors) && $pdo instanceof PDO) {
  $rows = settings_fetch_rows_by_categories($pdo, $categories, $settingKeys);
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    list($ok, $saveErrors, $updatedCount) = settings_save_rows($pdo, $rows, $_POST, $_FILES);
    if ($ok) {
      $rows = settings_fetch_rows_by_categories($pdo, $categories, $settingKeys);
      $saveSuccess = $updatedCount > 0
        ? 'Настройки сохранены: ' . $updatedCount . ' знач.'
        : 'Изменений для сохранения нет.';
        }
    }
}

$categoryTitles = settings_category_titles();
$groupedRows = [];
foreach ($rows as $row) {
    $groupedRows[$row['category']][] = $row;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>
<div class="settings-shell">
  <?php if ($saveSuccess): ?>
    <div class="alert settings-alert settings-alert--success"><?= h($saveSuccess) ?></div>
  <?php endif; ?>

  <?php if (!empty($saveErrors)): ?>
    <div class="alert alert--danger">
      <?= h(implode(' ', $saveErrors)) ?>
    </div>
  <?php endif; ?>

  <?php if (($pageConfig['mode'] ?? 'settings') === 'stub'): ?>
    <section class="form-block settings-stub">
      <h2><?= h($pageConfig['title']) ?></h2>
      <p><?= h($pageConfig['stub_text'] ?? 'Раздел находится в подготовке.') ?></p>
    </section>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="form-grid settings-form">
      <?php foreach ($groupedRows as $category => $items): ?>
        <section class="form-block settings-block">
          <h2 class="settings-block__title"><?= h($categoryTitles[$category] ?? $category) ?></h2>
          <div class="settings-fields">
            <?php foreach ($items as $row): ?>
              <?php
                $type = $row['type'] ?? 'string';
                $value = settings_value_for_form($row);
                $inputName = settings_input_name($row);
                $fileInputName = settings_file_input_name($row);
                $removeInputName = settings_remove_input_name($row);
                $options = settings_decode_options($row['options'] ?? null);
                $isEditable = (int)($row['is_editable'] ?? 0) === 1;
              ?>
              <div class="form-group settings-field<?= !$isEditable ? ' is-readonly' : '' ?>">
                <label for="setting_<?= (int)$row['id'] ?>"><?= h($row['label']) ?></label>

                <?php if ($type === 'bool'): ?>
                  <label class="settings-bool">
                    <input id="setting_<?= (int)$row['id'] ?>" type="checkbox" name="<?= h($inputName) ?>" value="1" <?= $value ? 'checked' : '' ?> <?= !$isEditable ? 'disabled' : '' ?>>
                    <span><?= $value ? 'Включено' : 'Выключено' ?></span>
                  </label>
                <?php elseif ($type === 'text' || $type === 'json'): ?>
                  <textarea id="setting_<?= (int)$row['id'] ?>" name="<?= h($inputName) ?>" rows="<?= $type === 'json' ? '7' : '4' ?>" <?= !$isEditable ? 'readonly' : '' ?>><?= h($value) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                  <select id="setting_<?= (int)$row['id'] ?>" name="<?= h($inputName) ?>" <?= !$isEditable ? 'disabled' : '' ?>>
                    <?php foreach ($options as $option): ?>
                      <option value="<?= h($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= h($option) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'color'): ?>
                  <input id="setting_<?= (int)$row['id'] ?>" type="color" name="<?= h($inputName) ?>" value="<?= h($value !== '' ? $value : '#000000') ?>" <?= !$isEditable ? 'disabled' : '' ?>>
                <?php elseif ($type === 'image' || $type === 'file'): ?>
                  <?php if (!empty($row['value'])): ?>
                    <div class="settings-file-current">
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
                      <input type="checkbox" name="<?= h($removeInputName) ?>" value="1"> Удалить текущее значение
                    </label>
                  <?php endif; ?>
                <?php else: ?>
                  <input id="setting_<?= (int)$row['id'] ?>" type="<?= $type === 'int' || $type === 'float' ? 'number' : 'text' ?>" name="<?= h($inputName) ?>" value="<?= h($value) ?>" <?= $type === 'float' ? 'step="0.01"' : '' ?> <?= !$isEditable ? 'readonly' : '' ?>>
                <?php endif; ?>

                <?php if (!empty($row['description'])): ?>
                  <small class="settings-field__description"><?= h($row['description']) ?></small>
                <?php endif; ?>
                <small class="settings-field__meta">Ключ: <?= h($row['key']) ?> | Тип: <?= h($type) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <div class="form-actions-bottom">
        <button type="submit" class="btn btn-primary">Сохранить настройки</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>