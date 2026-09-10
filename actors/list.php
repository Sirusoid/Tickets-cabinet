<?php
// actors/list.php
require_once __DIR__ . '/../init.php';
if (function_exists('require_login')) require_login();

$use_sidebar = true;
$active_menu = 'actors';
$page_title_meta = 'Актёры';
$panel_title = 'Актёры';
$panel_subtitle = 'Список всех актёров театра';
$panel_actions = [
  ['href'=>'/actors/add.php','label'=>'Добавить актёра','class'=>'btn btn-primary btn-sm']
];
require __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/panel.php';

// Параметры
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Определяем, какой маркер используется для профилей: IS NULL или = 0 или другой
$params = [];
$marker = null;
try {
    $hasNull = db_fetch_one("SELECT 1 AS x FROM event_actors WHERE event_id IS NULL LIMIT 1");
    if (!empty($hasNull)) {
        $marker = 'IS NULL';
    } else {
        $hasZero = db_fetch_one("SELECT 1 AS x FROM event_actors WHERE event_id = 0 LIMIT 1");
        if (!empty($hasZero)) {
            $marker = '= 0';
        } else {
            // fallback: попробуем найти записи, которые выглядят как профили
            $maybe = db_fetch_one("SELECT 1 AS x FROM event_actors WHERE role_name IS NULL AND actor_uid IS NOT NULL LIMIT 1");
            if (!empty($maybe)) {
                $marker = 'IS NULL';
            } else {
                // безопасный fallback
                $marker = '= 0';
            }
        }
    }
} catch (Throwable $e) {
    // если что-то пошло не так с проверками — используем = 0 как fallback
    $marker = '= 0';
}

// Формируем WHERE
$whereSql = "event_id {$marker}";

// Общее количество
$totalRow = db_fetch_one("SELECT COUNT(*) AS cnt FROM event_actors WHERE {$whereSql}", $params);
$total = (int)($totalRow['cnt'] ?? 0);

// Получаем профили с корректным биндингом offset/limit
$sql = "SELECT * FROM event_actors WHERE {$whereSql} ORDER BY sort_order ASC, actor_name ASC LIMIT :offset, :limit";
$stmt = db_connect()->prepare($sql);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->execute();
$actors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Вспомогательная функция для извлечения email из social_links JSON
function actor_email_from_row($row) {
    if (empty($row['social_links'])) return '';
    $tmp = json_decode($row['social_links'], true);
    if (!is_array($tmp)) return '';
    return $tmp['email'] ?? '';
}

// Вспомогательная функция для извлечения телефона
function actor_phone_from_row($row) {
    if (empty($row['social_links'])) return '';
    $tmp = json_decode($row['social_links'], true);
    if (!is_array($tmp)) return '';
    return $tmp['phone'] ?? '';
}
?>

<!-- Подключаем внешний CSS для карточек -->
<link rel="stylesheet" href="/assets/css/actors_grid.css">

<div class="panel panel--content">
  <div class="panel__inner">

    <?php if (empty($actors)): ?>
      <div style="padding:28px; text-align:center; color:#666;">Профилей не найдено.</div>
    <?php else: ?>
      <div class="cards-grid" id="actors-cards">
        <?php foreach ($actors as $a): 
          $email = actor_email_from_row($a);
          $phone = actor_phone_from_row($a);
        ?>
          <div class="actor-card" data-id="<?= h($a['id']) ?>">
            <a class="card-link" href="/actors/edit.php?id=<?= h($a['id']) ?>" aria-label="Редактировать <?= h($a['actor_name']) ?>">
              <div class="card-photo">
                <?php if (!empty($a['photo_url'])): ?>
                  <img src="<?= h($a['photo_url']) ?>" alt="<?= h($a['actor_name']) ?>">
                <?php else: ?>
                  <div class="photo-placeholder">Нет фото</div>
                <?php endif; ?>
              </div>

              <div class="card-info">
                <h3><?= h($a['actor_name']) ?></h3>
                <div class="meta"><?= $email ? h($email) : '<span style="color:#9aa">E-mail не указан</span>' ?></div>
                <div class="meta" style="margin-top:6px;"><?= $phone ? h($phone) : '<span style="color:#9aa">Телефон не указан</span>' ?></div>
              </div>
            </a>

            <div class="card-actions">
              <a href="/actors/edit.php?id=<?= h($a['id']) ?>" class="btn btn-ghost btn-sm">Редактировать</a>
              <button class="btn btn-danger btn-sm js-delete-actor" data-id="<?= h($a['id']) ?>" data-name="<?= h($a['actor_name']) ?>">Удалить</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top:18px; display:flex; justify-content:space-between; align-items:center;">
      <div class="u-muted">Всего: <?= $total ?></div>
      <div>
        <?php
          $pages = max(1, ceil($total / $perPage));
          for ($p = 1; $p <= $pages; $p++):
            $cls = $p === $page ? 'btn btn-ghost btn-sm is-active' : 'btn btn-ghost btn-sm';
        ?>
          <a href="/actors/list.php?page=<?= $p ?>" class="<?= h($cls) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
