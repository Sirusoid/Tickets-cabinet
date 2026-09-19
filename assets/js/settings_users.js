(function () {
  'use strict';

  var modal = document.getElementById('settingsUserModal');
  if (!modal) return;

  var username = document.getElementById('settingsUserModalUsername');
  var userId = document.getElementById('settingsUserId');
  var passwordUserId = document.getElementById('settingsPasswordUserId');
  var fullName = document.getElementById('settingsUserFullName');
  var email = document.getElementById('settingsUserEmail');
  var role = document.getElementById('settingsUserRole');
  var active = document.getElementById('settingsUserActive');

  function closeModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }

  function openModal(row) {
    var id = row.getAttribute('data-user-id') || '';
    username.textContent = row.getAttribute('data-user-username') || '';
    userId.value = id;
    passwordUserId.value = id;
    fullName.value = row.getAttribute('data-user-full-name') || '';
    email.value = row.getAttribute('data-user-email') || '';
    role.value = row.getAttribute('data-user-role') || 'manager';
    active.checked = row.getAttribute('data-user-active') === '1';
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    fullName.focus();
  }

  document.addEventListener('click', function (event) {
    var row = event.target.closest && event.target.closest('.settings-user-row');
    if (!row || event.target.closest('a, button, input, select, label, form')) return;
    openModal(row);
  });

  document.addEventListener('click', function (event) {
    if (event.target.closest && event.target.closest('[data-user-modal-close]')) closeModal();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
  });
})();