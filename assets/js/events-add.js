// /assets/js/events-add.js
(function () {
  'use strict';

  /* -----------------------
     Small helpers
     ----------------------- */
  function $ (sel, ctx) { return (ctx || document).querySelector(sel); }
  function $all (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function escHtml (s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
  function isFunction(fn){ return typeof fn === 'function'; }
  function safeSplitNames(str) {
    if (!str) return [];
    var re = new RegExp('[,\\n;]+');
    return String(str).split(re).map(function(s){ return s.trim(); }).filter(Boolean);
  }

  /* -----------------------
     DOM elements
     ----------------------- */
  var fileInput = document.getElementById('image-file-input');
  var previewWrap = document.getElementById('image-preview');
  var selectedImageInput = document.getElementById('selected-image');
  var deleteBtn = document.getElementById('delete-image-button');
  var selectBtn = document.getElementById('select-image-button');

  var actorsModal = document.getElementById('actors-modal');
  var actorsListWrap = document.getElementById('actors-list');
  var openActorsBtn = document.getElementById('open-actors-modal');
  var actorsApply = document.getElementById('actors-apply');
  var actorsCancel = document.getElementById('actors-cancel');
  var actorsClose = document.getElementById('actors-modal-close');
  var actorsBackdrop = document.getElementById('actors-modal-backdrop');
  var actorsField = document.getElementById('actors-field'); // textarea (human-readable names)

  /* -----------------------
     Scroll lock helpers
     ----------------------- */
  function lockScroll() { document.documentElement.classList.add('no-scroll'); document.body.classList.add('no-scroll'); }
  function unlockScroll() { document.documentElement.classList.remove('no-scroll'); document.body.classList.remove('no-scroll'); }

  /* -----------------------
     Modal open/close
     ----------------------- */
  function openActorsModal() {
    if (!actorsModal) return;
    actorsModal.setAttribute('aria-hidden', 'false');
    actorsModal.style.display = 'flex';
    lockScroll();
    setTimeout(function(){ if (actorsClose) actorsClose.focus(); }, 50);
  }

  function closeActorsModal() {
    if (!actorsModal) return;
    actorsModal.setAttribute('aria-hidden', 'true');
    actorsModal.style.display = 'none';
    unlockScroll();
    if (openActorsBtn) openActorsBtn.focus();
  }

  window.openActorsModal = openActorsModal;
  window.closeActorsModal = closeActorsModal;

  if (actorsCancel) actorsCancel.addEventListener('click', closeActorsModal, false);
  if (actorsClose) actorsClose.addEventListener('click', closeActorsModal, false);
  if (actorsBackdrop) actorsBackdrop.addEventListener('click', closeActorsModal, false);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && actorsModal && actorsModal.getAttribute('aria-hidden') === 'false') closeActorsModal();
  }, false);

  /* -----------------------
     Footer confirm wrapper
     ----------------------- */
  function openConfirmWithHandler(text, callback) {
    if (typeof window.showModalDelete === 'function') {
      window.showModalDelete(text, {});
      var confirmBtn = document.getElementById('modal-delete-confirm');
      if (!confirmBtn) { if (isFunction(callback)) callback(); return; }
      var handler = function () {
        try { confirmBtn.removeEventListener('click', handler, false); } catch (e) {}
        if (isFunction(callback)) callback();
      };
      confirmBtn.addEventListener('click', handler, false);
      return;
    }
    if (confirm(text)) { if (isFunction(callback)) callback(); }
  }

  /* -----------------------
     Image preview & upload
     ----------------------- */
  function setPreview(url) {
    if (!previewWrap) return;
    if (!url) {
      previewWrap.innerHTML = '<span id="poster-empty">Нет изображения</span>';
      if (selectedImageInput) selectedImageInput.value = '';
      return;
    }
    var finalUrl = url;
    if (finalUrl.indexOf('http') !== 0 && finalUrl.indexOf('/') === 0) {
      finalUrl = window.location.protocol + '//' + window.location.host + finalUrl;
    }
    previewWrap.innerHTML = '<img id="poster-img" src="' + escHtml(finalUrl) + '" alt="Превью">';
    if (selectedImageInput) selectedImageInput.value = url;
  }

  if (selectBtn && fileInput) {
    selectBtn.addEventListener('click', function () { fileInput.click(); }, false);
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      if (!f.type || !f.type.match(/^image\//)) {
        if (typeof window.showInfoModal === 'function') window.showInfoModal('Выбранный файл не является изображением.', 'Неверный файл');
        else if (typeof window.showToast === 'function') window.showToast('Выбранный файл не является изображением', 'error', { duration: 3000 });
        this.value = '';
        return;
      }

      try { setPreview(URL.createObjectURL(f)); } catch (e) {
        var reader = new FileReader();
        reader.onload = function (ev) { setPreview(ev.target.result); };
        reader.readAsDataURL(f);
      }

      var fd = new FormData();
      fd.append('action', 'upload');
      fd.append('target', 'events');
      fd.append('image_file', f);
      var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';
      if (csrf) fd.append('csrf_token', csrf);

      fetch('/ajax/image.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success && resp.data) {
            var url = resp.data.url_full || resp.data.url;
            setPreview(url);
          } else {
            setPreview(null);
            var msg = (resp && resp.message) ? resp.message : 'Ошибка загрузки изображения';
            if (typeof window.showInfoModal === 'function') window.showInfoModal(msg, 'Ошибка загрузки');
            else if (typeof window.showToast === 'function') window.showToast(msg, 'error', { duration: 3000 });
          }
        })
        .catch(function () {
          setPreview(null);
          if (typeof window.showInfoModal === 'function') window.showInfoModal('Ошибка сети при загрузке изображения', 'Ошибка');
          else if (typeof window.showToast === 'function') window.showToast('Ошибка сети при загрузке изображения', 'error', { duration: 3000 });
        });
    }, false);
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      openConfirmWithHandler('Удалить изображение афиши?', function () { performDelete(); });
    }, false);
  }

  function performDelete() {
    var current = (selectedImageInput && selectedImageInput.value) ? selectedImageInput.value : '';
    if (!current) { setPreview(null); if (typeof window.showToast === 'function') window.showToast('Изображение удалено', 'success', { duration: 900 }); return; }

    var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';
    var body = new URLSearchParams();
    body.append('action', 'delete');
    body.append('file_url', current);
    body.append('target', 'events');
    if (csrf) body.append('csrf_token', csrf);

    fetch('/ajax/image.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Invalid JSON response' }; }); })
      .then(function (resp) {
        if (resp && resp.success) {
          setPreview(null);
          if (typeof window.showToast === 'function') window.showToast('Изображение удалено', 'success', { duration: 900 });
        } else {
          var msg = (resp && resp.message) ? resp.message : 'Ошибка при удалении изображения';
          if (typeof window.showInfoModal === 'function') window.showInfoModal(msg, 'Ошибка удаления');
          else if (typeof window.showToast === 'function') window.showToast(msg, 'error', { duration: 3000 });
        }
      })
      .catch(function () {
        if (typeof window.showInfoModal === 'function') window.showInfoModal('Ошибка сети при удалении изображения', 'Ошибка');
        else if (typeof window.showToast === 'function') window.showToast('Ошибка сети при удалении изображения', 'error', { duration: 3000 });
      });
  }

  /* -----------------------
     Actors: load list and render
     ----------------------- */
  function loadActorsList() {
    if (!actorsListWrap) return;
    actorsListWrap.innerHTML = '<div class="loading">Загрузка...</div>';

    fetch('/ajax/actor.php?action=list', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (!resp || !resp.success) {
          actorsListWrap.innerHTML = '<div class="alert alert--danger">Не удалось загрузить список актёров</div>';
          return;
        }
        if (!resp.data || !resp.data.length) {
          actorsListWrap.innerHTML = '<div class="alert alert--warning">Актёров нет</div>';
          return;
        }

        var existingNames = [];
        if (actorsField && actorsField.value) {
          existingNames = safeSplitNames(actorsField.value);
        }

        var html = '<div class="actors-grid">';
        resp.data.forEach(function (a) {
          var id = a.id;
          var name = (a.actor_name || '').trim();
          var photo = a.photo_url || '/assets/img/no-photo.png';
          var checked = existingNames.indexOf(name) !== -1 ? ' checked' : '';
          html += '<label class="actor-item" data-actor-id="' + escHtml(id) + '" data-actor-name="' + escHtml(name) + '">'
               + '<input type="checkbox" class="actor-check" value="' + escHtml(id) + '" data-actor-name="' + escHtml(name) + '"' + checked + '>'
               + '<img src="' + escHtml(photo) + '" class="actor-photo" alt="' + escHtml(name) + '">'
               + '<span class="actor-name">' + escHtml(name) + '</span>'
               + '</label>';
        });
        html += '</div>';
        actorsListWrap.innerHTML = html;
      })
      .catch(function () {
        actorsListWrap.innerHTML = '<div class="alert alert--danger">Ошибка сети</div>';
      });
  }

  if (openActorsBtn) {
    openActorsBtn.addEventListener('click', function () {
      loadActorsList();
      openActorsModal();
    }, false);
  }

  /* -----------------------
     Synchronization helper
     ----------------------- */
  function updateActorsFieldFromModal() {
    if (!actorsListWrap || !actorsField) return;
    var checked = Array.from(actorsListWrap.querySelectorAll('input.actor-check[type="checkbox"]:checked'));
    var names = checked.map(function (ch) {
      var name = ch.getAttribute('data-actor-name') || '';
      if (!name) {
        var parent = ch.closest && ch.closest('.actor-item');
        if (parent) {
          var nameEl = parent.querySelector && parent.querySelector('.actor-name');
          if (nameEl) name = nameEl.textContent.trim();
        }
      }
      return name.trim();
    }).filter(Boolean);

    // Merge with existing non-modal names? We choose to replace with modal selection to reflect current modal state.
    // If you prefer merging, uncomment merge logic below.
    /*
    var existing = safeSplitNames(actorsField.value || '');
    var merged = existing.concat(names).map(function(v){ return v.trim(); }).filter(Boolean);
    var seen = {}; var dedup = [];
    merged.forEach(function(v){ var k = v.toLowerCase(); if(!seen[k]){ seen[k]=true; dedup.push(v); }});
    actorsField.value = dedup.join(', ');
    */
    actorsField.value = names.join(', ');
  }

  /* -----------------------
     Live update when checkbox changes (delegation)
     ----------------------- */
  if (actorsListWrap) {
    actorsListWrap.addEventListener('change', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.matches && t.matches('input.actor-check[type="checkbox"]')) {
        // Update textarea immediately when a checkbox is toggled
        updateActorsFieldFromModal();
      }
    }, false);

    // Also handle clicks on label elements that may toggle checkbox via label click (some browsers fire change, but ensure update)
    actorsListWrap.addEventListener('click', function (e) {
      var t = e.target;
      // if clicked on label or inside label, schedule update after event loop to allow checkbox state to change
      var label = t.closest && t.closest('.actor-item');
      if (label) {
        setTimeout(updateActorsFieldFromModal, 0);
      }
    }, false);
  }

  /* -----------------------
     Apply selected actors (button) — keeps existing behavior
     ----------------------- */
  if (actorsApply) {
    actorsApply.addEventListener('click', function () {
      if (!actorsListWrap) return;
      var checked = Array.from(actorsListWrap.querySelectorAll('input.actor-check[type="checkbox"]:checked'));
      if (!checked.length) {
        if (typeof window.showInfoModal === 'function') window.showInfoModal('Выберите хотя бы одного актёра', 'Внимание');
        else if (typeof window.showToast === 'function') window.showToast('Выберите хотя бы одного актёра', 'info', { duration: 2000 });
        return;
      }

      var selectedNames = checked.map(function(ch){
        var name = ch.getAttribute('data-actor-name') || '';
        if (!name) {
          var parent = ch.closest && ch.closest('.actor-item');
          if (parent) {
            var nameEl = parent.querySelector && parent.querySelector('.actor-name');
            if (nameEl) name = nameEl.textContent.trim();
          }
        }
        return name.trim();
      }).filter(Boolean);

      if (!selectedNames.length) {
        if (typeof window.showInfoModal === 'function') window.showInfoModal('Не удалось определить имена выбранных актёров', 'Ошибка');
        else if (typeof window.showToast === 'function') window.showToast('Не удалось определить имена выбранных актёров', 'error', { duration: 3000 });
        return;
      }

      var existing = [];
      if (actorsField && actorsField.value) {
        existing = safeSplitNames(actorsField.value);
      }

      var merged = existing.concat(selectedNames).map(function(v){ return v.trim(); }).filter(Boolean);
      var seen = {}; var dedup = [];
      merged.forEach(function(v){ var k = v.toLowerCase(); if(!seen[k]){ seen[k]=true; dedup.push(v); }});

      if (actorsField) actorsField.value = dedup.join(', ');

      closeActorsModal();
    }, false);
  }

  /* -----------------------
     Form submit (AJAX) — convert human-readable names to JSON array before sending
     ----------------------- */
  var form = document.getElementById('event-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var submitBtn = form.querySelector('button[type="submit"], button[form="event-form"]');
      if (submitBtn) submitBtn.disabled = true;

      var formData = new FormData(form);

      var idInput = form.querySelector('input[name="id"]');
      var idVal = idInput ? parseInt(idInput.value || '0', 10) : 0;
      var action = (idVal && idVal > 0) ? 'update' : 'create';
      formData.set('action', action);
      if (idVal && idVal > 0) formData.set('id', String(idVal));

      var namesRaw = (actorsField && actorsField.value) ? actorsField.value : '';
      var namesArr = safeSplitNames(namesRaw);

      if (namesArr.length === 1) {
        var single = namesArr[0];
        if (/^\s*\[.*\]\s*$/.test(single)) {
          try {
            var parsed = JSON.parse(single);
            if (Array.isArray(parsed)) {
              namesArr = parsed.map(function (x) { return String(x || '').trim(); }).filter(Boolean);
            }
          } catch (e) {
          }
        }
      }

      formData.set('cast_list', JSON.stringify(namesArr));
      if (formData.has('actors')) formData.delete('actors');

      fetch('/ajax/event.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success) {
            if (typeof window.showToast === 'function') {
              window.showToast(resp.message || 'Сохранено', 'success', { duration: 900 });
              setTimeout(function () { window.location.href = '/events/list.php'; }, 900);
            } else {
              window.location.href = '/events/list.php';
            }
          } else {
            var msg = (resp && resp.message) ? resp.message : 'Ошибка при сохранении мероприятия.';
            if (typeof window.showInfoModal === 'function') window.showInfoModal(msg, 'Ошибка');
            else if (typeof window.showToast === 'function') window.showToast(msg, 'error', { duration: 3000 });
            if (submitBtn) submitBtn.disabled = false;
          }
        })
        .catch(function () {
          if (typeof window.showInfoModal === 'function') window.showInfoModal('Ошибка сети при сохранении мероприятия', 'Ошибка');
          else if (typeof window.showToast === 'function') window.showToast('Ошибка сети при сохранении мероприятия', 'error', { duration: 3000 });
          if (submitBtn) submitBtn.disabled = false;
        });
    }, false);
  }

  /* -----------------------
     Back buttons handler
     ----------------------- */
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t) return;
    if (t.matches('.js-back') || t.matches('.js-panel-back')) {
      e.preventDefault();
      var href = t.getAttribute('href') || '/events/list.php';
      var text = 'Несохранённые данные будут потеряны.';
      openConfirmWithHandler(text, function () {
        if (typeof window.showToast === 'function') {
          window.showToast('Отменено', 'info', { duration: 700 });
          setTimeout(function () { window.location.href = href; }, 700);
        } else {
          window.location.href = href;
        }
      });
    }
  }, true);

})();
