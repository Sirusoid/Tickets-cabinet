<?php
// halls/list.php - страница со списком залов

require_once __DIR__ . '/../init.php';
require_login();

$sql = "
    SELECT id, name, created_at
    FROM halls
    ORDER BY name ASC
";
$stmt = $pdo->query($sql);
$use_sidebar = true;
$active_menu = 'halls'; // dashboard, schedule, events, tickets, halls, reports
$page_title_meta = 'Залы - Админка';
$panel_title = 'Залы';
$panel_subtitle = 'Список всех залов';
$panel_actions = [
  ['href'=>'/halls/add.php','label'=>'Создать','class'=>'btn btn-primary btn-sm']
];
?>

<?php
require __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/panel.php';
?>

<div class="card halls-list">
  <?php
  $useArray = isset($halls) && is_array($halls);
  $hasRows = $useArray ? !empty($halls) : ($stmt && $stmt->rowCount() > 0);
  ?>
  <?php if (!$hasRows): ?>
    <div class="no-data">Залов не найдено.</div>
  <?php else: ?>
    <table class="table admin-table table--compact" aria-describedby="halls-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Название</th>
          <th class="actions-col">Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($useArray) {
          $i = 1;
          foreach ($halls as $h): ?>
            <tr class="hall-row" data-id="<?= h($h['id']) ?>">
              <td><?= $i++ ?></td>
              <td><?= h($h['name']) ?></td>
              <td class="actions actions--center">
                <a href="/halls/edit.php?id=<?= h($h['id']) ?>" class="btn btn-ghost btn-sm" title="Редактировать">Редактировать</a>
                <button class="btn btn-danger btn-sm js-delete-hall" data-id="<?= h($h['id']) ?>" data-name="<?= h($h['name']) ?>" title="Удалить">Удалить</button>
              </td>
            </tr>
          <?php endforeach;
        } else {
          $i = 1;
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <tr class="hall-row" data-id="<?= h($row['id']) ?>">
              <td><?= $i++ ?></td>
              <td><?= h($row['name']) ?></td>
              <td class="actions actions--center">
                <a href="/halls/edit.php?id=<?= h($row['id']) ?>" class="btn btn-ghost btn-sm" title="Редактировать">Редактировать</a>
                <button class="btn btn-danger btn-sm js-delete-hall" data-id="<?= h($row['id']) ?>" data-name="<?= h($row['name']) ?>" title="Удалить">Удалить</button>
              </td>
            </tr>
          <?php endwhile;
        }
        ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php
// footer подтягивает глобальные модалки, в том числе #modal-delete и скрипт, который экспортирует
// window.showModalDelete, window.hideModalDelete, window.getModalMeta и т.д.
// Также footer подключает assets/js/app_ui.js, который предоставляет showToast.
require __DIR__ . '/../includes/footer.php';
?>

<script>
(function () {
  'use strict';

  // Утилиты
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  var table = document.querySelector('.halls-list table');
  if (!table) return;

  // Навигация по клику на строку (игнорируем клики по интерактивным элементам)
  table.addEventListener('click', function (ev) {
    var target = ev.target;
    if (target.closest('.js-delete-hall')) return;
    if (target.closest('a, button, input, select, .no-row-nav')) return;

    var row = target.closest('.hall-row');
    if (!row) return;

    var editLink = row.querySelector('a[href*="/halls/edit.php"]');
    if (editLink && editLink.href) {
      window.location.href = editLink.href;
      return;
    }

    var id = row.getAttribute('data-id');
    if (id) {
      window.location.href = '/halls/edit.php?id=' + encodeURIComponent(id);
    }
  }, false);

  // Обработчик клика по кнопке удаления — открываем глобальную модалку из футера
  table.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.js-delete-hall');
    if (!btn) return;

    ev.stopPropagation();

    var hallId = btn.getAttribute('data-id');
    var hallName = btn.getAttribute('data-name') || '';

    if (!hallId) {
      console.warn('delete button without data-id');
      return;
    }

    // Передаём в meta только свои поля (hallId, hallName), чтобы глобальный универсальный обработчик в футере не выполнял AJAX
    if (typeof window.showModalDelete === 'function') {
      var text = 'Удалить зал "' + hallName + '"? Это действие нельзя отменить.';
      window.showModalDelete(text, { hallId: hallId, hallName: hallName });
    } else {
      // fallback: нативное подтверждение
      if (confirm('Удалить зал "' + hallName + '"?')) {
        performDelete(hallId, btn);
      }
    }
  }, false);

  // Локальный обработчик кнопки подтверждения модалки
  var modalConfirmBtn = document.getElementById('modal-delete-confirm');
  var modalCancelBtn = document.getElementById('modal-delete-cancel');

  if (modalConfirmBtn) {
    modalConfirmBtn.addEventListener('click', function () {
      if (typeof window.getModalMeta !== 'function') {
        if (typeof window.hideModalDelete === 'function') window.hideModalDelete();
        return;
      }
      var meta = window.getModalMeta() || {};
      if (!meta.hallId) {
        // не наша модалка — ничего не делаем (глобальный обработчик может взять на себя)
        return;
      }

      // Выполняем удаление
      performDelete(meta.hallId, null);

      // Закрываем модалку
      if (typeof window.hideModalDelete === 'function') window.hideModalDelete();
    }, false);
  }

  if (modalCancelBtn) {
    modalCancelBtn.addEventListener('click', function () {
      if (typeof window.hideModalDelete === 'function') window.hideModalDelete();
    }, false);
  }

  // Уведомление (использует глобальную showToast из app_ui.js)
  function notifySuccess(msg) {
    if (typeof window.showToast === 'function') window.showToast(msg, 'success', { duration: 3000 });
    else alert(msg);
  }
  function notifyError(msg) {
    if (typeof window.showToast === 'function') window.showToast(msg, 'error', { duration: 4000 });
    else alert(msg);
  }

  // Функция удаления через ajax/hall.php?action=delete
  function performDelete(hallId, btnElement) {
    var btn = btnElement || null;
    if (btn) {
      btn.disabled = true;
      btn._origHtml = btn._origHtml || btn.innerHTML;
      btn.innerHTML = 'Удаление...';
    }

    var params = new URLSearchParams();
    params.append('action', 'delete');
    params.append('id', hallId);

    fetch('/ajax/hall.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params.toString(),
      credentials: 'same-origin'
    }).then(function (resp) {
      return resp.json().catch(function () {
        throw new Error('Неверный ответ сервера');
      });
    }).then(function (data) {
      if (data && data.success) {
        // удаляем строку из DOM с плавным эффектом
        var row = table.querySelector('.hall-row[data-id="' + hallId + '"]');
        if (row) {
          try {
            row.style.transition = 'opacity .18s ease, transform .18s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateY(-6px)';
            setTimeout(function () {
              if (row && row.parentNode) row.parentNode.removeChild(row);
            }, 180);
          } catch (e) {
            row.parentNode && row.parentNode.removeChild(row);
          }
        }
        notifySuccess('Зал удалён');
      } else {
        var msg = (data && data.message) ? data.message : 'Не удалось удалить зал';
        notifyError(msg);
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = btn._origHtml || 'Удалить';
        }
      }
    }).catch(function (err) {
      console.error('Delete hall error', err);
      notifyError('Ошибка при удалении. Смотрите консоль.');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = btn._origHtml || 'Удалить';
      }
    });
  }

})();
</script>
