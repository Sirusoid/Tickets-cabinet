<?php
// includes/footer.php
$page_scripts = $page_scripts ?? [];
?>
  </main>

  <!-- Глобальные модалки (вставлены в footer, доступны на всех страницах) -->
  <div id="modal-delete" class="modal" aria-hidden="true" style="display:none;">
    <div class="modal-backdrop" id="modal-delete-backdrop" style="position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div class="modal-box" id="modal-delete-box" role="dialog" aria-modal="true" style="position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:420px; max-width:94%; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:1201; padding:18px; display:flex; flex-direction:column; gap:12px;">
      <div style="font-weight:600; font-size:16px;">Подтвердите действие</div>
      <div id="modal-delete-text" style="color:#333; line-height:1.3;">Вы уверены?</div>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
        <button id="modal-delete-cancel" class="btn btn-ghost">Отмена</button>
        <button id="modal-delete-confirm" class="btn btn-danger">Подтвердить</button>
      </div>
    </div>
  </div>

  <div id="modal-logout" class="modal" aria-hidden="true" style="display:none;">
    <div class="modal-backdrop" id="modal-logout-backdrop" style="position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div class="modal-box" id="modal-logout-box" role="dialog" aria-modal="true" aria-labelledby="modal-logout-title" style="position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:420px; max-width:94%; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:1201; padding:18px; display:flex; flex-direction:column; gap:12px;">
      <div id="modal-logout-title" style="font-weight:600; font-size:16px;">Выход из системы</div>
      <div style="color:#333; line-height:1.3;">Вы действительно хотите выйти?</div>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
        <button id="modal-logout-cancel" type="button" class="btn btn-ghost">Отмена</button>
        <button id="modal-logout-confirm" type="button" class="btn btn-danger">Выйти</button>
      </div>
    </div>
  </div>

  <!-- Контейнер для тостов (app_ui.js создаёт его при необходимости, но оставляем резервный элемент) -->
  <div id="toast-wrap" aria-live="polite" aria-atomic="true" style="position:fixed; right:16px; bottom:20px; z-index:1200; display:flex; flex-direction:column; gap:8px; pointer-events:none; max-width:360px;"></div>

  <script>
    // Глобальная переменная CSRF для footer-скриптов
    window.APP_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  </script>
	
  <script src="/assets/js/jquery.min.js"></script>
  <script src="/assets/js/app_ui.js"></script>

  <?php
  // Подключаем только скрипты, явно указанные страницей
  foreach ($page_scripts as $s): ?>
    <script src="<?= h($s) ?>"></script>
  <?php endforeach; ?>

  <script>
  (function(){
    'use strict';

    // Утилиты
    function $(sel, ctx){ return (ctx || document).querySelector(sel); }
    function $all(sel, ctx){ return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    // === МОДАЛЬНЫЕ ФУНКЦИИ (доступны глобально) ===

    function _showModalDelete_internal(text, meta){
      var wrap = document.getElementById('modal-delete');
      if (!wrap) return;
      var txt = document.getElementById('modal-delete-text');
      txt.textContent = text || 'Подтвердите действие';
      try {
        wrap.dataset.meta = JSON.stringify(meta || {});
      } catch(e){
        wrap.dataset.meta = '{}';
      }
      wrap.style.display = 'block';
      wrap.setAttribute('aria-hidden', 'false');
    }

    function _hideModalDelete_internal(){
      var wrap = document.getElementById('modal-delete');
      if (!wrap) return;
      wrap.style.display = 'none';
      wrap.setAttribute('aria-hidden', 'true');
      try { delete wrap.dataset.meta; } catch(e){}
    }

    function _getModalMeta_internal(){
      var wrap = document.getElementById('modal-delete');
      if (!wrap || !wrap.dataset.meta) return {};
      try { return JSON.parse(wrap.dataset.meta); } catch(e){ return {}; }
    }

    // Экспортируем в глобальную область, чтобы другие скрипты могли вызывать
    window.showModalDelete = _showModalDelete_internal;
    window.hideModalDelete = _hideModalDelete_internal;
    window.getModalMeta = _getModalMeta_internal;

    function showLogoutModal(){
      var wrap = document.getElementById('modal-logout');
      if (!wrap) return;
      wrap.style.display = 'block';
      wrap.setAttribute('aria-hidden', 'false');
    }

    function hideLogoutModal(){
      var wrap = document.getElementById('modal-logout');
      if (!wrap) return;
      wrap.style.display = 'none';
      wrap.setAttribute('aria-hidden', 'true');
    }

    var logoutButton = document.getElementById('logoutButton');
    var logoutForm = document.getElementById('logoutForm');
    var logoutCancel = document.getElementById('modal-logout-cancel');
    var logoutConfirm = document.getElementById('modal-logout-confirm');
    var logoutBackdrop = document.getElementById('modal-logout-backdrop');
    if (logoutButton) logoutButton.addEventListener('click', showLogoutModal, false);
    if (logoutCancel) logoutCancel.addEventListener('click', hideLogoutModal, false);
    if (logoutBackdrop) logoutBackdrop.addEventListener('click', hideLogoutModal, false);
    if (logoutConfirm) logoutConfirm.addEventListener('click', function(){
      if (logoutForm) logoutForm.submit();
    }, false);

    // Делегируем клик по элементам с классом .js-delete-actor
    document.addEventListener('click', function(e){
      var btn = e.target.closest && e.target.closest('.js-delete-actor');
      var customerBtn = e.target.closest && e.target.closest('.js-delete-customer');
      if (btn) {
        e.preventDefault();
        var id = btn.getAttribute('data-id') || '';
        var name = btn.getAttribute('data-name') || '';
        if (!id) {
          console.warn('delete button without data-id');
          return;
        }
        // meta.action и meta.id используются для автоматического удаления в этом файле
        showModalDelete('Удалить профиль "' + name + '" и все связанные участия?', { action: 'delete', id: id });
        return;
      }
      if (customerBtn) {
        e.preventDefault();
        var customerId = customerBtn.getAttribute('data-id') || '';
        var customerName = customerBtn.getAttribute('data-name') || '';
        if (!customerId) return;
        showModalDelete('Удалить клиента "' + customerName + '"?', {
          action: 'delete',
          id: customerId,
          endpoint: '/ajax/customer.php',
          silentToast: true
        });
        return;
      }

      // Универсальная навигация по клику на строку таблицы актёров или карточку
      // Игнорируем клики по интерактивным элементам внутри строки/карточки
      var interactive = e.target.closest && e.target.closest('a, button, input, select, .no-row-nav');
      if (interactive) return;

      // Ищем ближайшую строку с классом actor-row или карточку actor-card или data-type="actor"
      var container = e.target.closest && (e.target.closest('tr.actor-row, tr[data-type="actor"], .actor-card[data-id], [data-type="actor"]'));
      if (!container) return;

      var id = container.getAttribute('data-id');
      if (!id) return;

      // Навигация на страницу редактирования актёра
      try {
        window.location.href = '/actors/edit.php?id=' + encodeURIComponent(id);
      } catch (err) {
        console.error('Row/card navigation failed', err);
      }
    }, false);

    // Кнопки модалки
    var btnCancel = document.getElementById('modal-delete-cancel');
    var btnConfirm = document.getElementById('modal-delete-confirm');
    var backdrop = document.getElementById('modal-delete-backdrop');

    if (btnCancel) btnCancel.addEventListener('click', function(){ hideModalDelete(); }, false);
    if (backdrop) backdrop.addEventListener('click', function(){ hideModalDelete(); }, false);

    // Обработчик подтверждения удаления — универсальный:
    // - Если meta содержит action+id => выполняем AJAX и удаляем элемент по data-id
    // - Если meta пустой => ничего не делаем (позволяем внешним скриптам назначать confirmBtn.onclick)
    if (btnConfirm) btnConfirm.addEventListener('click', function(evt){
      var meta = getModalMeta();
      // Если meta не содержит action/id — не выполняем удаление здесь.
      // Это позволяет внешним скриптам (например, showConfirm) назначать собственный onclick на кнопку подтверждения.
      if (!meta || !meta.action || !meta.id) {
        // Не закрываем модалку здесь — внешний обработчик должен закрыть её сам.
        return;
      }

      btnConfirm.disabled = true;
      var prevText = btnConfirm.textContent;
      btnConfirm.textContent = 'Удаление...';

      var body = 'action=' + encodeURIComponent(meta.action) + '&id=' + encodeURIComponent(meta.id) + '&csrf_token=' + encodeURIComponent(window.APP_CSRF_TOKEN || '');
      var endpoint = meta.endpoint || '/ajax/actor.php';

      fetch(endpoint, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: body,
        credentials: 'same-origin'
      }).then(function(r){
        return r.json();
      }).then(function(resp){
        btnConfirm.disabled = false;
        btnConfirm.textContent = prevText;
        hideModalDelete();

        if (resp && resp.success) {
          document.dispatchEvent(new CustomEvent('modal-delete-success', { detail: { meta: meta, response: resp } }));
          // Универсальное удаление элементов с data-id
          try {
            var selectors = ['tr[data-id]', '.actor-card[data-id]'];
            var removed = false;
            for (var s = 0; s < selectors.length; s++) {
              var nodes = document.querySelectorAll(selectors[s]);
              for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                if (String(el.getAttribute('data-id')) === String(meta.id)) {
                  // Плавное скрытие, затем удаление
                  try {
                    el.style.transition = 'opacity .18s ease, transform .18s ease';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-6px)';
                    (function(node){
                      setTimeout(function(){
                        if (node && node.parentNode) node.parentNode.removeChild(node);
                      }, 180);
                    })(el);
                  } catch (e) {
                    // fallback: мгновенно удалить
                    el.parentNode && el.parentNode.removeChild(el);
                  }
                  removed = true;
                }
              }
              if (removed) break;
            }

            // Если не нашли в основных селекторах, попробуем удалить любой элемент с data-id
            if (!removed) {
              var anyNodes = document.querySelectorAll('[data-id]');
              for (var j = 0; j < anyNodes.length; j++) {
                var el2 = anyNodes[j];
                if (String(el2.getAttribute('data-id')) === String(meta.id)) {
                  el2.parentNode && el2.parentNode.removeChild(el2);
                  removed = true;
                  break;
                }
              }
            }
          } catch (e) {
            console.warn('Remove element by data-id failed', e);
          }

          // Обновляем счётчик "Всего", если он есть
          try {
            var totalEl = document.querySelector('.u-muted');
            if (totalEl) {
              var m = totalEl.textContent.match(/\d+/);
              if (m) {
                var cur = parseInt(m[0], 10);
                if (!isNaN(cur) && cur > 0) {
                  totalEl.textContent = totalEl.textContent.replace(m[0], String(cur - 1));
                }
              }
            }
          } catch (e){}

          if (!meta.silentToast && typeof window.showToast === 'function') {
            window.showToast('Удалено', 'success', { duration: 1500 });
          } else if (!meta.silentToast) {
            alert('Удалено');
          }
        } else {
          var msg = (resp && resp.message) ? resp.message : 'Ошибка удаления';
          if (typeof window.showToast === 'function') {
            window.showToast(msg, 'error', { duration: 3000 });
          } else {
            alert(msg);
          }
        }
      }).catch(function(err){
        console.error('Delete request failed', err);
        btnConfirm.disabled = false;
        btnConfirm.textContent = prevText;
        hideModalDelete();
        if (typeof window.showToast === 'function') {
          window.showToast('Ошибка сети', 'error', { duration: 3000 });
        } else {
          alert('Ошибка сети');
        }
      });
    }, false);

    // Закрытие модалки по Escape
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') hideModalDelete();
      if (e.key === 'Escape') hideLogoutModal();
    }, false);

  })();
  </script>

  <?php foreach ($page_inline_scripts ?? [] as $inline): ?>
    <script><?= $inline ?></script>
  <?php endforeach; ?>

  </body>
</html>
