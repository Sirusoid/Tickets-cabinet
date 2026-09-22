(function () {
  'use strict';

  var modal = document.getElementById('settingsUserModal');
  if (!modal) return;

  var createModal = document.getElementById('settingsCreateUserModal');
  var username = document.getElementById('settingsUserModalUsername');
  var userId = document.getElementById('settingsUserId');
  var fullName = document.getElementById('settingsUserFullName');
  var email = document.getElementById('settingsUserEmail');
  var role = document.getElementById('settingsUserRole');
  var active = document.getElementById('settingsUserActive');
  var newPassword = document.getElementById('settingsUserNewPassword');

  function closeModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }

  function openModal(row) {
    var id = row.getAttribute('data-user-id') || '';
    username.textContent = row.getAttribute('data-user-username') || '';
    userId.value = id;
    fullName.value = row.getAttribute('data-user-full-name') || '';
    email.value = row.getAttribute('data-user-email') || '';
    role.value = row.getAttribute('data-user-role') || 'manager';
    active.checked = row.getAttribute('data-user-active') === '1';
    if (newPassword) newPassword.value = '';
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

  function closeCreateModal() {
    if (!createModal) return;
    createModal.style.display = 'none';
    createModal.setAttribute('aria-hidden', 'true');
  }

  function openCreateModal() {
    if (!createModal) return;
    createModal.style.display = 'flex';
    createModal.setAttribute('aria-hidden', 'false');
    var firstInput = document.getElementById('create_username');
    if (firstInput) firstInput.focus();
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest && event.target.closest('[data-create-user-open]')) openCreateModal();
    if (event.target.closest && event.target.closest('[data-create-user-close]')) closeCreateModal();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (modal.getAttribute('aria-hidden') === 'false') closeModal();
    if (createModal && createModal.getAttribute('aria-hidden') === 'false') closeCreateModal();
  });
})();