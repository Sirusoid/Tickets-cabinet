<?php
// actors/add.php
require __DIR__ . '/../init.php';
if (function_exists('require_login')) require_login();

require_once __DIR__ . '/../includes/image_helpers.php';

$use_sidebar = true;
$active_menu = 'actors';
$page_title_meta = 'Добавить актёра';
$panel_title = 'Добавить актёра';
$panel_subtitle = 'Добавление профиля актёра';
$panel_actions = [
    ['href'=>'/actors/list.php','label'=>'К списку актёров','class'=>'btn btn-primary btn-sm']
];

if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
    } else {
        $_SESSION['csrf_token'] = bin2hex(mt_rand() . time());
    }
}
$csrf = $_SESSION['csrf_token'];

$error = null;
$actor = [
    'id' => 0,
    'actor_name' => '',
    'bio' => '',
    'photo_url' => '',
    'sort_order' => 0,
    'social_links' => ''
];
$links = [];
$bd = $bm = $by = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (empty($postedCsrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
        $error = 'Неверный CSRF токен.';
    } else {
        $actor_name = trim($_POST['actor_name'] ?? '');
        $birth_day = trim($_POST['birth_day'] ?? '');
        $birth_month = trim($_POST['birth_month'] ?? '');
        $birth_year = trim($_POST['birth_year'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $facebook = trim($_POST['facebook'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);

        $birth_date = null;
        if ($birth_year !== '' && $birth_month !== '' && $birth_day !== '') {
            $y = intval($birth_year);
            $m = intval($birth_month);
            $d = intval($birth_day);
            if (checkdate($m, $d, $y)) {
                $birth_date = sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        // Photo upload (input name "photo")
        $photo_url = '';
        if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $f = $_FILES['photo'];
            $allowedExt = ['jpg','jpeg','png','gif','webp'];
            $mimeMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
            $mime = $f['type'] ?? '';
            if (isset($mimeMap[$mime])) $ext = $mimeMap[$mime];
            if (!in_array($ext, $allowedExt, true)) {
                $error = 'Недопустимый тип файла изображения.';
            } else {
                $projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
                $uploadBaseFs = rtrim($projectRoot, '/') . '/uploads/images';
                $targetFsDir = $uploadBaseFs . '/actors';
                if (!is_dir($targetFsDir)) @mkdir($targetFsDir, 0755, true);
                if (!is_writable($targetFsDir)) {
                    $error = 'Папка для загрузки изображений недоступна для записи.';
                } else {
                    $safeName = function_exists('transliterate_filename')
                        ? transliterate_filename($f['name'] ?? ('photo.' . $ext))
                        : preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($f['name'] ?? ('photo.' . $ext)));
                    $rand = function_exists('rand_hex') ? rand_hex(6) : bin2hex(random_bytes(3));
                    $basename = time() . '_' . $rand . '_' . $safeName;
                    $destFs = rtrim($targetFsDir, '/') . '/' . $basename;
                    if (@move_uploaded_file($f['tmp_name'], $destFs)) {
                        @chmod($destFs, 0644);
                        $photo_url = '/uploads/images/actors/' . $basename;
                    } else {
                        $error = 'Не удалось сохранить загруженное изображение.';
                    }
                }
            }
        }

        $links = [
            'email' => $email,
            'phone' => $phone,
            'instagram' => $instagram,
            'facebook' => $facebook
        ];

        if (!$error && $actor_name === '') {
            $error = 'Укажите имя актёра.';
        }

        if (!$error) {
            try {
                db_query_safe('INSERT INTO event_actors (actor_name, bio, photo_url, birth_date, social_links, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [
                    $actor_name,
                    $bio,
                    $photo_url ?: null,
                    $birth_date,
                    json_encode($links, JSON_UNESCAPED_UNICODE),
                    $sort_order
                ]);
                redirect('actors/list.php');
            } catch (Exception $e) {
                error_log('actors/add.php insert error: ' . $e->getMessage());
                $error = 'Ошибка при сохранении актёра.';
            }
        } else {
            // repopulate form values
            $actor['actor_name'] = $actor_name;
            $actor['bio'] = $bio;
            $actor['photo_url'] = $photo_url;
            $actor['sort_order'] = $sort_order;
            $links = [
              'email' => $email,
              'phone' => $phone,
              'instagram' => $instagram,
              'facebook' => $facebook
            ];
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>
<link rel="stylesheet" href="/assets/css/actor_form.css">

<div class="panel panel--content">
  <div class="panel__inner">

    <form id="actor-form" enctype="multipart/form-data" class="profile-layout" method="post" action="add.php">

      <input type="hidden" name="action" value="create">
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
              Добавить фото
              <input type="file" name="photo" id="photo-input" accept="image/*" hidden>
            </label>

            <div class="photo-hint">JPG/PNG/WebP, до 3 МБ</div>
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

        <div id="messages" style="margin-top:12px;">
          <?php if ($error): ?>
            <div class="alert alert--danger"><?= h($error) ?></div>
          <?php endif; ?>
        </div>

        <div style="margin-top:16px;">
          <button class="btn btn-primary" id="save-btn">Создать</button>
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

  // Modal helpers using admin.css classes
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
    var dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() + 1 !== m || dt.getDate() !== d) return null;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    return y + '-' + pad(m) + '-' + pad(d);
  }

  // Confirm navigation back to list — show modal with Ok/Cancel
  var backBtn = document.querySelector('.panel__actions a[href="/actors/list.php"]');
  if (backBtn) {
    backBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var buttons = [
        { id: 'cancel', label: 'Отмена', className: 'btn btn-ghost' },
        { id: 'ok', label: 'Ок', className: 'btn btn-danger' }
      ];
      showConfirmModal('Отменить добавление актёра? Несохранённые изменения будут потеряны.', 'Отмена', buttons)
        .then(function (res) {
          if (res && res.action === 'ok') {
            window.location.href = '/actors/list.php';
          }
        });
    });
  }

  // Submit handler: upload-first approach (create actor)
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      messages.innerHTML = '<div class="alert">Отправка...</div>';

      var file = input && input.files && input.files[0];
      var csrf = '<?= h($csrf) ?>';

      function sendActorData(photoUrl) {
        var data = new FormData(form);

        // Ensure birth_date is sent as single field expected by server
        var birthDate = buildBirthDateFromForm(form);
        if (birthDate) {
          data.set('birth_date', birthDate);
        } else {
          data.set('birth_date', '');
        }

        // Build social_links JSON from form fields and include it explicitly
        var linksObj = {
          email: (form.querySelector('[name="email"]') && form.querySelector('[name="email"]').value) || '',
          phone: (form.querySelector('[name="phone"]') && form.querySelector('[name="phone"]').value) || '',
          instagram: (form.querySelector('[name="instagram"]') && form.querySelector('[name="instagram"]').value) || '',
          facebook: (form.querySelector('[name="facebook"]') && form.querySelector('[name="facebook"]').value) || ''
        };
        data.set('social_links', JSON.stringify(linksObj));

        if (photoUrl) data.set('photo_url', photoUrl);
        data.set('csrf_token', csrf);
        data.set('action', 'create');

        fetch('/ajax/actor.php', {
          method: 'POST',
          body: data,
          credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success) {
            messages.innerHTML = '<div class="alert alert--success">Создано</div>';
            if (typeof window.showToast === 'function') {
              window.showToast('Создано', 'success', { duration: 1200 });
            }
            setTimeout(function () {
              window.location.href = '/actors/list.php';
            }, 900);
          } else {
            var msg = (resp && resp.message) ? resp.message : 'Ошибка при создании';
            messages.innerHTML = '<div class="alert alert--danger">' + msg + '</div>';
            showInfoModal(msg, 'Ошибка');
            saveBtn.disabled = false;
          }
        })
        .catch(function (err) {
          console.error(err);
          messages.innerHTML = '<div class="alert alert--danger">Ошибка сети при создании актёра</div>';
          showInfoModal('Ошибка сети при создании актёра', 'Ошибка');
          saveBtn.disabled = false;
        });
      }

      // If a file is selected, upload it first to ajax/image.php
      if (file) {
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('target', 'actors');
        fd.append('image_file', file);
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
