<?php
require_once __DIR__ . '/../init.php';
if (function_exists('require_login')) require_login();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$use_sidebar = true;
$active_menu = 'actors';
$page_title_meta = 'Редактирование профиля актёра';
$panel_title = 'Редактирование профиля';
$panel_subtitle = 'Изменение профиля актёра';
$panel_actions = [
  ['href'=>'/actors/list.php','label'=>'К списку актёров','class'=>'btn btn-primary btn-sm']
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="panel panel--content"><div class="panel__inner"><div class="alert alert--danger">Неверный ID актёра</div></div></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch actor
try {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT * FROM event_actors WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $actor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('actors/edit.php DB error: ' . $e->getMessage());
    echo '<div class="panel panel--content"><div class="panel__inner"><div class="alert alert--danger">Ошибка при подключении к базе данных</div></div></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if (!$actor) {
    echo '<div class="panel panel--content"><div class="panel__inner"><div class="alert alert--danger">Актёр не найден</div></div></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$csrf = $_SESSION['csrf_token'] ?? '';
$links = json_decode($actor['social_links'] ?? '{}', true);
if (!is_array($links)) $links = [];

$birth = explode('-', $actor['birth_date'] ?? '');
$bd = $birth[2] ?? '';
$bm = $birth[1] ?? '';
$by = $birth[0] ?? '';

?>
<link rel="stylesheet" href="/assets/css/actor_form.css">

<div class="panel panel--content">
  <div class="panel__inner">

    <form id="actor-form" enctype="multipart/form-data" class="profile-layout" method="post" action="edit.php?id=<?= h($actor['id']) ?>">

      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= h($actor['id']) ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

      <!-- LEFT SIDE — PHOTO -->
      <aside class="profile-photo">

        <div class="photo-box">
          <?php if (!empty($actor['photo_url'])): ?>
            <img id="photo-preview" src="<?= h($actor['photo_url']) ?>" class="photo-preview">
          <?php else: ?>
            <img id="photo-preview" src="" class="photo-preview" style="display:none;">
          <?php endif; ?>
        </div>

        <div class="photo-actions">
            <label class="btn btn-ghost btn-sm">
                Обновить фото
                <input type="file" name="photo" id="photo-input" accept="image/*" hidden>
            </label>

            <?php if (!empty($actor['photo_url'])): ?>
                <button type="button" class="btn btn-danger btn-sm" id="delete-photo-btn">
                    Удалить фото
                </button>
                <input type="hidden" name="delete_photo" id="delete-photo-flag" value="0">
            <?php else: ?>
                <input type="hidden" name="delete_photo" id="delete-photo-flag" value="0">
            <?php endif; ?>
        </div>

      </aside>

      <!-- RIGHT SIDE — FIELDS -->
      <div class="profile-content">

        <!-- CARD: Основная информация -->
        <div class="profile-card">
          <div class="card-title">Основная информация</div>

          <label class="form-label">Имя актёра</label>
          <input type="text" name="actor_name" class="input" required value="<?= h($actor['actor_name']) ?>">

          <label class="form-label" style="margin-top:10px;">Дата рождения</label>
          <div class="dob-grid">
            <select name="birth_day" class="input">
              <option value="">День</option>
              <?php for ($d=1; $d<=31; $d++): ?>
                <option value="<?= $d ?>" <?= ($d == intval($bd)) ? 'selected' : '' ?>><?= $d ?></option>
              <?php endfor; ?>
            </select>

            <select name="birth_month" class="input">
              <option value="">Месяц</option>
              <?php
              $months = [
                1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',
                7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'
              ];
              foreach ($months as $num=>$title): ?>
                <option value="<?= $num ?>" <?= ($num == intval($bm)) ? 'selected' : '' ?>><?= $title ?></option>
              <?php endforeach; ?>
            </select>

            <select name="birth_year" class="input">
              <option value="">Год</option>
              <?php for ($y = date('Y'); $y >= 1900; $y--): ?>
                <option value="<?= $y ?>" <?= ($y == intval($by)) ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <!-- CARD: Контакты -->
        <div class="profile-card">
          <div class="card-title">Контакты</div>

          <label class="form-label">E-mail</label>
          <input type="email" name="email" class="input" value="<?= h($links['email'] ?? '') ?>">

          <label class="form-label" style="margin-top:10px;">Телефон</label>
          <input type="text" name="phone" class="input" value="<?= h($links['phone'] ?? '') ?>">
        </div>

        <!-- CARD: Соцсети -->
        <div class="profile-card">
          <div class="card-title">Социальные сети</div>

          <label class="form-label">Instagram</label>
          <input type="text" name="instagram" class="input" value="<?= h($links['instagram'] ?? '') ?>">

          <label class="form-label" style="margin-top:10px;">Facebook</label>
          <input type="text" name="facebook" class="input" value="<?= h($links['facebook'] ?? '') ?>">
        </div>

        <!-- CARD: Биография (на всю ширину) -->
        <div class="profile-card profile-bio-full">
          <div class="card-title">Биография</div>
          <textarea name="bio" class="input" rows="6"><?= h($actor['bio']) ?></textarea>
        </div>

        <div id="messages" style="margin-top:12px;"></div>

        <div style="margin-top:16px;">
          <button class="btn btn-primary" id="save-btn">Сохранить изменения</button>
        </div>

      </div>
    </form>

  </div>
</div>

<!-- Global modal using admin.css modal classes -->
<div id="global-modal" class="modal" aria-hidden="true">
  <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="global-modal-title" tabindex="-1">
    <div class="modal-header">
      <h3 id="global-modal-title"></h3>
    </div>
    <div id="global-modal-body" class="modal-body"></div>
    <div id="global-modal-actions" class="modal-footer"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

  // Modal helpers using existing admin.css classes and accessibility attributes
  (function () {
    function escHtml(s) {
      if (s === null || s === undefined) return '';
      return String(s).replace(/[&<>"']/g, function (m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
      });
    }

    var modal = document.getElementById('global-modal');
    var panel = modal ? modal.querySelector('.modal-panel') : null;
    var titleEl = document.getElementById('global-modal-title');
    var bodyEl = document.getElementById('global-modal-body');
    var actionsEl = document.getElementById('global-modal-actions');
    var lastFocused = null;

    function openModal() {
      if (!modal) return;
      lastFocused = document.activeElement;
      modal.setAttribute('aria-hidden', 'false');
      if (panel) panel.focus();
      document.addEventListener('focus', trapFocus, true);
      document.addEventListener('keydown', onKeyDown, true);
    }

    function closeModal() {
      if (!modal) return;
      modal.setAttribute('aria-hidden', 'true');
      document.removeEventListener('focus', trapFocus, true);
      document.removeEventListener('keydown', onKeyDown, true);
      if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    function trapFocus(e) {
      if (!panel) return;
      if (!panel.contains(e.target)) {
        e.stopPropagation();
        panel.focus();
      }
    }

    function onKeyDown(e) {
      if (e.key === 'Escape') {
        if (modal && modal.getAttribute('aria-hidden') === 'false') {
          var cancelBtn = actionsEl && actionsEl.querySelector('[data-action-id="cancel"]');
          if (cancelBtn) cancelBtn.click();
          else closeModal();
        }
      }
    }

    function showModal(options) {
      options = options || {};
      var title = options.title || '';
      var message = options.message || '';
      var buttons = options.buttons || [{ id: 'ok', label: 'OK', className: 'btn btn-primary' }];

      if (!modal) return Promise.resolve({ action: 'ok' });

      titleEl.textContent = title;
      bodyEl.innerHTML = escHtml(message).replace(/\n/g, '<br>');
      actionsEl.innerHTML = '';

      buttons.forEach(function (btn) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = btn.label || btn.id;
        b.className = btn.className || 'btn';
        b.dataset.actionId = btn.id;
        actionsEl.appendChild(b);
      });

      openModal();

      return new Promise(function (resolve) {
        function onClick(e) {
          var target = e.target;
          if (!target || !target.dataset) return;
          var id = target.dataset.actionId;
          if (!id) return;
          actionsEl.removeEventListener('click', onClick);
          closeModal();
          resolve({ action: id });
        }
        actionsEl.addEventListener('click', onClick);
      });
    }

    window.showConfirmModal = function (message, title, buttons) {
      if (Array.isArray(buttons) && buttons.length) {
        return showModal({ title: title || 'Подтвердите действие', message: message || '', buttons: buttons });
      }
      return showModal({
        title: title || 'Подтвердите действие',
        message: message || '',
        buttons: [
          { id: 'cancel', label: 'Отмена', className: 'btn btn-ghost' },
          { id: 'yes', label: 'Удалить', className: 'btn btn-danger' }
        ]
      });
    };

    window.showInfoModal = function (message, title) {
      return showModal({
        title: title || 'Информация',
        message: message || '',
        buttons: [
          { id: 'ok', label: 'ОК', className: 'btn btn-primary' }
        ]
      });
    };
  })();

  var form = document.getElementById('actor-form');
  var preview = document.getElementById('photo-preview');
  var input = document.getElementById('photo-input');
  var messages = document.getElementById('messages');
  var deleteBtn = document.getElementById('delete-photo-btn');
  var saveBtn = document.getElementById('save-btn');

  // Preview selected image
  if (input) {
    input.addEventListener('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      if (!f.type.match(/^image\//)) {
        showInfoModal('Выбранный файл не является изображением. Пожалуйста, выберите JPG/PNG/WebP/GIF.', 'Неверный файл');
        this.value = '';
        return;
      }
      try {
        preview.src = URL.createObjectURL(f);
        preview.style.display = 'block';
      } catch (e) {
        var reader = new FileReader();
        reader.onload = function (ev) {
          preview.src = ev.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(f);
      }
    });
  }

  // Delete photo (AJAX) — use confirm modal instead of native confirm
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      showConfirmModal('Удалить фото?').then(function (res) {
        if (!res || res.action !== 'yes') return;
        messages.innerHTML = '<div class="alert">Удаление...</div>';

        fetch('/ajax/actor.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'update',
            remove_photo: '1',
            id: '<?= h($actor['id']) ?>',
            csrf_token: '<?= h($csrf) ?>'
          })
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success) {
            preview.style.display = 'none';
            preview.src = '';
            if (deleteBtn && deleteBtn.parentNode) deleteBtn.parentNode.removeChild(deleteBtn);
            messages.innerHTML = '<div class="alert alert--success">Фото удалено</div>';
            showInfoModal('Фото удалено', 'Успех');
          } else {
            var msg = (resp && resp.message) ? resp.message : 'Ошибка удаления фото';
            messages.innerHTML = '<div class="alert alert--danger">' + msg + '</div>';
            showInfoModal(msg, 'Ошибка');
          }
        })
        .catch(function (err) {
          console.error(err);
          messages.innerHTML = '<div class="alert alert--danger">Ошибка сети при удалении фото</div>';
          showInfoModal('Ошибка сети при удалении фото', 'Ошибка');
        });
      });
    });
  }

  // Confirm navigation back to list — use modal with "Ок" button
  var backBtn = document.querySelector('.panel__actions a[href="/actors/list.php"]');
  if (backBtn) {
    backBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var buttons = [
        { id: 'cancel', label: 'Отмена', className: 'btn btn-ghost' },
        { id: 'ok', label: 'Ок', className: 'btn btn-danger' }
      ];
      showConfirmModal('Отменить редактирование профиля? Несохранённые изменения будут потеряны.', 'Отмена редактирования', buttons)
        .then(function (res) {
          if (res && res.action === 'ok') {
            window.location.href = '/actors/list.php';
          }
        });
    });
  }

  // Helper: build birth_date string from selects, return null if incomplete/invalid
  function buildBirthDateFromForm(formEl) {
    if (!formEl) return null;
    var dayEl = formEl.querySelector('[name="birth_day"]');
    var monthEl = formEl.querySelector('[name="birth_month"]');
    var yearEl = formEl.querySelector('[name="birth_year"]');
    if (!dayEl || !monthEl || !yearEl) return null;
    var d = parseInt(dayEl.value || '', 10);
    var m = parseInt(monthEl.value || '', 10);
    var y = parseInt(yearEl.value || '', 10);
    if (!d || !m || !y) return null;
    // basic validation using JS Date / checkdate equivalent
    // create Date object and compare components
    var dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() + 1 !== m || dt.getDate() !== d) return null;
    // format YYYY-MM-DD
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    return y + '-' + pad(m) + '-' + pad(d);
  }

  // Submit handler: upload-first approach
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      messages.innerHTML = '<div class="alert">Отправка...</div>';

      var file = input && input.files && input.files[0];
      var actorId = '<?= h($actor['id']) ?>';
      var csrf = '<?= h($csrf) ?>';

      function sendActorData(photoUrl) {
        var data = new FormData(form);

        // Ensure birth_date is sent as single field expected by server
        var birthDate = buildBirthDateFromForm(form);
        if (birthDate) {
          data.set('birth_date', birthDate);
        } else {
          // If user cleared parts, send empty value to clear on server or omit
          // Here we set empty string so server can interpret as NULL/empty
          data.set('birth_date', '');
        }

        // Build social_links JSON explicitly so instagram/facebook/email/phone are saved
        var linksObj = {
          email: (form.querySelector('[name="email"]') && form.querySelector('[name="email"]').value) || '',
          phone: (form.querySelector('[name="phone"]') && form.querySelector('[name="phone"]').value) || '',
          instagram: (form.querySelector('[name="instagram"]') && form.querySelector('[name="instagram"]').value) || '',
          facebook: (form.querySelector('[name="facebook"]') && form.querySelector('[name="facebook"]').value) || ''
        };
        data.set('social_links', JSON.stringify(linksObj));

        // Include delete_photo flag if present
        var deleteFlagEl = form.querySelector('[name="delete_photo"]');
        if (deleteFlagEl) {
          data.set('delete_photo', deleteFlagEl.value || '0');
        }

        if (photoUrl) data.set('photo_url', photoUrl);
        data.set('id', actorId);
        data.set('csrf_token', csrf);
        data.set('action', 'update');

        fetch('/ajax/actor.php', {
          method: 'POST',
          body: data,
          credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success) {
            messages.innerHTML = '<div class="alert alert--success">Сохранено</div>';
            if (typeof window.showToast === 'function') {
              window.showToast('Сохранено', 'success', { duration: 1200 });
            }
            // After showing toast, redirect to list
            setTimeout(function () {
              window.location.href = '/actors/list.php';
            }, 900);
          } else {
            var msg = (resp && resp.message) ? resp.message : 'Ошибка при сохранении';
            messages.innerHTML = '<div class="alert alert--danger">' + msg + '</div>';
            showInfoModal(msg, 'Ошибка');
            saveBtn.disabled = false;
          }
        })
        .catch(function (err) {
          console.error(err);
          messages.innerHTML = '<div class="alert alert--danger">Ошибка сети при сохранении профиля</div>';
          showInfoModal('Ошибка сети при сохранении профиля', 'Ошибка');
          saveBtn.disabled = false;
        });
      }

      // If a file is selected, upload it first to ajax/image.php
      if (file) {
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('target', 'actors');
        fd.append('image_file', file);
        var oldUrl = '<?= h($actor['photo_url']) ?>';
        if (oldUrl) {
          fd.append('remove_old', '1');
          fd.append('old_url', oldUrl);
        }
        fd.append('csrf_token', csrf);

        fetch('/ajax/image.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.url) {
            sendActorData(resp.data.url);
          } else {
            var msg = (resp && resp.message) ? resp.message : 'Ошибка загрузки изображения';
            messages.innerHTML = '<div class="alert alert--danger">' + msg + '</div>';
            showInfoModal(msg, 'Ошибка загрузки');
            saveBtn.disabled = false;
          }
        })
        .catch(function (err) {
          console.error(err);
          messages.innerHTML = '<div class="alert alert--danger">Ошибка сети при загрузке изображения</div>';
          showInfoModal('Ошибка сети при загрузке изображения', 'Ошибка');
          saveBtn.disabled = false;
        });

      } else {
        // No file selected — just send actor data
        sendActorData(null);
      }

      // Prevent double submit
      saveBtn.disabled = true;
    });
  }

});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
