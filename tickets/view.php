<?php
// tickets/view.php
// Страница просмотра одного или нескольких купленных билетов.

require_once __DIR__ . '/../init.php';
require_login();

$page_scripts = ['/assets/js/ticket_viewer.js'];

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$uids = isset($_GET['uids']) ? trim((string)$_GET['uids']) : '';

$where = [];
$params = [];

if ($ticket_id > 0) {
    $where[] = 't.id = :id';
    $params[':id'] = $ticket_id;
} elseif ($uids !== '') {
    $raw = array_filter(array_map('trim', explode(',', $uids)));
    $uids_clean = [];
    foreach ($raw as $u) {
        if ($u !== '') {
            $uids_clean[] = $u;
        }
    }
    if (empty($uids_clean)) {
        redirect('/tickets/list.php');
    }
    $placeholders = implode(',', array_fill(0, count($uids_clean), '?'));
    $where[] = "t.ticket_uid IN ($placeholders)";
    $params = $uids_clean;
} else {
    redirect('/tickets/list.php');
}

$sql = "SELECT t.id, t.ticket_uid, t.seat_identifier, t.customer_segment, t.price, t.channel, t.status, t.payment_status, t.purchased_at,
               COALESCE(c.full_name, '') AS customer_name,
               COALESCE(e.title, '') AS event_title,
               s.start_time AS schedule_start
        FROM tickets t
        LEFT JOIN customers c ON c.id = t.customer_id
        LEFT JOIN schedules s ON s.id = t.schedule_id
        LEFT JOIN events e ON e.id = t.event_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.purchased_at DESC, t.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('tickets/view.php query failed: ' . $e->getMessage());
    $tickets = [];
}

if (empty($tickets)) {
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/panel.php';
    ?>
    <div class="page container-full">
      <div class="profile-card" style="padding:18px;">
        <h2>Билеты не найдены</h2>
        <p>По указанным параметрам билеты не найдены. <a href="/tickets/list.php">Вернуться к списку билетов</a>.</p>
      </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$batch = count($tickets) > 1;
$uids_for_js = [];
foreach ($tickets as $ticket) {
    $uids_for_js[] = rawurlencode($ticket['ticket_uid']);
}
$uids_joined = implode(',', $uids_for_js);

$page_title_meta = $batch ? 'Просмотр билетов' : ('Билет ' . ($tickets[0]['ticket_uid'] ?? ''));
$panel_title = $batch ? 'Просмотр билетов' : 'Просмотр билета';
$panel_subtitle = $batch ? 'Список PDF для сохранения и печати' : 'PDF версия билета';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full">
  <div class="card" style="margin-bottom:16px;">
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:14px;">
      <a href="/tickets/list.php" class="btn btn-ghost btn-sm">Вернуться к списку билетов</a>
      <button id="printTicketsBtn" type="button" class="btn btn-primary btn-sm">Печать</button>
      <?php if ($batch): ?>
        <button id="openAllTabsBtn" type="button" class="btn btn-secondary btn-sm" data-uids="<?= h($uids_joined) ?>">Открыть все в новых вкладках</button>
      <?php else: ?>
        <a href="/tickets/generate.php?uid=<?= h(rawurlencode($tickets[0]['ticket_uid'])) ?>&download=1" class="btn btn-secondary btn-sm">Скачать PDF</a>
      <?php endif; ?>
    </div>

    <?php foreach ($tickets as $ticket): ?>
      <?php $previewUrl = '/tickets/generate.php?uid=' . rawurlencode($ticket['ticket_uid']); ?>
      <div class="card" style="margin-bottom:20px; padding:18px; border:1px solid #e0e0e0; background:#fff;">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:12px;">
          <div>
            <div style="font-size:16px; font-weight:700; margin-bottom:6px;">UID: <?= h($ticket['ticket_uid']) ?></div>
            <div style="color:#555;">Сеанс: <?= h($ticket['event_title']) ?>, <?= h($ticket['schedule_start']) ?></div>
            <div style="color:#555;">Место: <?= h($ticket['seat_identifier']) ?></div>
            <div style="color:#555;">Клиент: <?= h($ticket['customer_name']) ?></div>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="<?= h($previewUrl) ?>" target="_blank" class="btn btn-ghost btn-sm">Открыть PDF</a>
            <a href="<?= h($previewUrl . '&download=1') ?>" class="btn btn-secondary btn-sm">Скачать</a>
          </div>
        </div>
        <div style="border:1px solid #ddd; min-height:520px;">
          <iframe src="<?= h($previewUrl) ?>" style="width:100%; min-height:520px; border:none;" title="Просмотр билета <?= h($ticket['ticket_uid']) ?>"></iframe>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
