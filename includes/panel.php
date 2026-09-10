<?php
// includes/panel.php
// Универсальная панель заголовка для страниц админки.
// Перед подключением можно задать:
//   $panel_title (string) — заголовок панели
//   $panel_subtitle (string) — подзаголовок
//   $panel_actions (array) — массив действий: ['href'=>'/path','label'=>'Создать','class'=>'btn ...']
// Пример использования в странице:
//   $panel_title = 'Мероприятия';
//   $panel_subtitle = 'Список всех мероприятий';
//   $panel_actions = [['href'=>'/events/add.php','label'=>'Создать','class'=>'btn btn-primary btn-sm']];
//   require __DIR__ . '/panel.php';

$panel_title    = isset($panel_title) ? $panel_title : '';
$panel_subtitle = isset($panel_subtitle) ? $panel_subtitle : '';
$panel_actions  = isset($panel_actions) && is_array($panel_actions) ? $panel_actions : [];
?>
<div class="panel panel--header">
  <div class="panel__inner">
    <div class="panel__meta">
      <?php if ($panel_title !== ''): ?>
        <h2 class="panel__title"><?= h($panel_title) ?></h2>
      <?php endif; ?>
      <?php if ($panel_subtitle !== ''): ?>
        <div class="panel__subtitle"><?= h($panel_subtitle) ?></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($panel_actions)): ?>
      <div class="panel__actions">
        <?php foreach ($panel_actions as $act): 
            $href = isset($act['href']) ? $act['href'] : '#';
            $label = isset($act['label']) ? $act['label'] : 'Действие';
            $cls = isset($act['class']) ? $act['class'] : 'btn btn-primary btn-sm';
        ?>
          <a href="<?= h($href) ?>" class="<?= h($cls) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="panel__actions" aria-hidden="true"></div>
    <?php endif; ?>
  </div>
</div>
