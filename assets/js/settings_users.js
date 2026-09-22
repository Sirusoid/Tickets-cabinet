(function () {
  'use strict';

  var filterForm = document.getElementById('settingsUsersFilters');
  var searchInput = document.getElementById('settings-users-search');
  var roleFilter = document.getElementById('settings-users-role-filter');
  var activeFilter = document.getElementById('settings-users-active-filter');
  var perPageSelect = document.getElementById('settingsUsersPerPage');
  var createForm = document.getElementById('settingsUserCreateForm');
  var tbody = document.getElementById('settingsUsersTbody');
  var summary = document.getElementById('settingsUsersSummary');
  var currentPageLabel = document.getElementById('settingsUsersCurrentPage');
  var previousButton = document.getElementById('settingsUsersPrevPage');
  var nextButton = document.getElementById('settingsUsersNextPage');
  var suggestions = document.getElementById('settings-users-suggestions');
  var editModal = document.getElementById('settingsUserModal');
  var editForm = document.getElementById('settingsUserEditForm');
  var editError = document.getElementById('settingsUserEditError');
  var resetButton = document.getElementById('settingsUsersReset');
  var debounceTimer = null;
  var suggestionTimer = null;
  var page = 1;
  var perPage = perPageSelect ? Number(perPageSelect.value || 25) : 25;
  var totalPages = 1;

  if (!filterForm || !tbody) return;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }

  function showToast(message, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type || 'info', { duration: 2800 });
    }
  }

  function roleLabel(role) {
    return {
      admin: 'Администратор',
      manager: 'Менеджер',
      cashier: 'Кассир',
      scanner: 'Сканер'
    }[role] || role || '—';
  }

  function activeLabel(active) {
    return Number(active) === 1
      ? '<span class="settings-user-status is-active">Да</span>'
      : '<span class="settings-user-status is-inactive">Нет</span>';
  }

  function formatDate(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T'));
    if (isNaN(date.getTime())) return String(value);
    return date.toLocaleString('ru-RU');
  }

  function getFilters() {
    return {
      search: searchInput ? searchInput.value.trim() : '',
      role: roleFilter ? roleFilter.value : '',
      active: activeFilter ? activeFilter.value : ''
    };
  }

  function renderRows(rows) {
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="customers-empty">Пользователи по заданным условиям не найдены</td></tr>';
      return;
    }
    var offset = (page - 1) * perPage;
    tbody.innerHTML = rows.map(function (user, index) {
      return '<tr data-id="' + escapeHtml(user.id) + '">' +
        '<td>' + (offset + index + 1) + '</td>' +
        '<td>' + escapeHtml(user.id) + '</td>' +
        '<td><strong>' + escapeHtml(user.username) + '</strong></td>' +
        '<td>' + escapeHtml(user.full_name) + '</td>' +
        '<td>' + (user.email ? escapeHtml(user.email) : '—') + '</td>' +
        '<td>' + escapeHtml(roleLabel(user.role)) + '</td>' +
        '<td>' + activeLabel(user.is_active) + '</td>' +
        '<td class="actions-col"><div class="action-buttons">' +
        '<button type="button" class="btn btn-ghost btn-sm js-edit-user" data-id="' + escapeHtml(user.id) + '">Редактировать</button>' +
        '<button type="button" class="btn btn-danger btn-sm js-delete-user" data-id="' + escapeHtml(user.id) + '" data-name="' + escapeHtml(user.full_name || user.username) + '">Удалить</button>' +
        '</div></td></tr>';
    }).join('');
  }

  function renderPagination(currentPage, pages, total) {
    page = Number(currentPage || 1);
    totalPages = Math.max(1, Number(pages || 1));
    if (summary) summary.innerHTML = 'Пользователей: <strong>' + Number(total || 0).toLocaleString('ru-RU') + '</strong>';
    if (currentPageLabel) currentPageLabel.textContent = page + ' / ' + totalPages;
    if (previousButton) previousButton.disabled = page <= 1;
    if (nextButton) nextButton.disabled = page >= totalPages;
  }

  function loadUsers() {
    var filters = getFilters();
    var params = new URLSearchParams({
      action: 'list',
      search: filters.search,
      role: filters.role,
      active: filters.active,
      page: String(page),
      per_page: String(perPage)
    });
    tbody.classList.add('is-loading');
    fetch('/ajax/users.php?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) throw new Error((payload && payload.message) || 'Ошибка загрузки пользователей');
        renderRows(payload.data || []);
        renderPagination(payload.page, payload.total_pages, payload.total);
      })
      .catch(function (error) {
        tbody.innerHTML = '<tr><td colspan="8" class="customers-empty customers-empty--error">' + escapeHtml(error.message || 'Не удалось загрузить пользователей') + '</td></tr>';
      })
      .finally(function () { tbody.classList.remove('is-loading'); });
  }

  function hideSuggestions() {
    if (!suggestions) return;
    suggestions.style.display = 'none';
    suggestions.innerHTML = '';
  }

  function loadSuggestions() {
    if (!suggestions || !searchInput) return;
    var query = searchInput.value.trim();
    if (query.length < 1) {
      hideSuggestions();
      return;
    }
    var params = new URLSearchParams({
      action: 'list',
      search: query,
      role: roleFilter ? roleFilter.value : '',
      active: activeFilter ? activeFilter.value : '',
      page: '1',
      per_page: '8'
    });
    fetch('/ajax/users.php?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        suggestions.innerHTML = '';
        if (!payload || !payload.success || !payload.data || !payload.data.length) {
          hideSuggestions();
          return;
        }
        payload.data.forEach(function (user) {
          var item = document.createElement('div');
          item.className = 'typeahead-item';
          item.innerHTML = '<strong>' + escapeHtml(user.username) + '</strong> — ' + escapeHtml(user.full_name || '') + (user.email ? ' <span>' + escapeHtml(user.email) + '</span>' : '');
          item.addEventListener('mousedown', function (event) { event.preventDefault(); });
          item.addEventListener('click', function () {
            searchInput.value = user.username || user.full_name || '';
            hideSuggestions();
            page = 1;
            loadUsers();
          });
          suggestions.appendChild(item);
        });
        suggestions.style.display = 'block';
      })
      .catch(function () { hideSuggestions(); });
  }

  function scheduleLoad() {
    clearTimeout(debounceTimer);
    clearTimeout(suggestionTimer);
    page = 1;
    suggestionTimer = setTimeout(loadSuggestions, 220);
    debounceTimer = setTimeout(loadUsers, 300);
  }

  function closeEditModal() {
    if (!editModal) return;
    editModal.style.display = 'none';
    editModal.setAttribute('aria-hidden', 'true');
    if (editError) editError.textContent = '';
  }

  function openEditModal(id) {
    if (!editModal) return;
    fetch('/ajax/users.php?action=get&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) throw new Error((payload && payload.message) || 'Не удалось загрузить пользователя');
        var user = payload.data;
        document.getElementById('settingsUserModalUsername').textContent = user.username || '';
        document.getElementById('settingsUserId').value = user.id || '';
        document.getElementById('settingsUserFullName').value = user.full_name || '';
        document.getElementById('settingsUserEmail').value = user.email || '';
        document.getElementById('settingsUserRole').value = user.role || 'manager';
        document.getElementById('settingsUserActive').checked = Number(user.is_active) === 1;
        document.getElementById('settingsUserNewPassword').value = '';
        editModal.style.display = 'flex';
        editModal.setAttribute('aria-hidden', 'false');
        document.getElementById('settingsUserFullName').focus();
      })
      .catch(function (error) { showToast(error.message || 'Ошибка загрузки пользователя', 'error'); });
  }

  function submitCreate(event) {
    event.preventDefault();
    var formData = new FormData(createForm);
    formData.set('action', 'create');
    formData.set('csrf_token', window.APP_CSRF_TOKEN || '');
    var button = createForm.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    fetch('/ajax/users.php', { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) throw new Error((payload && payload.message) || 'Не удалось создать пользователя');
        createForm.reset();
        createForm.querySelector('input[name="is_active"]').checked = true;
        showToast('Пользователь создан', 'success');
        page = 1;
        loadUsers();
      })
      .catch(function (error) { showToast(error.message || 'Ошибка создания пользователя', 'error'); })
      .finally(function () { if (button) button.disabled = false; });
  }

  function submitEdit(event) {
    event.preventDefault();
    if (editError) editError.textContent = '';
    var formData = new FormData(editForm);
    formData.set('action', 'update');
    formData.set('csrf_token', window.APP_CSRF_TOKEN || '');
    var button = editForm.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    fetch('/ajax/users.php', { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) throw new Error((payload && payload.message) || 'Не удалось сохранить пользователя');
        closeEditModal();
        showToast(payload.message || 'Данные пользователя сохранены', 'success');
        loadUsers();
      })
      .catch(function (error) {
        if (editError) editError.textContent = error.message || 'Ошибка сохранения';
        showToast(error.message || 'Ошибка сохранения пользователя', 'error');
      })
      .finally(function () { if (button) button.disabled = false; });
  }

  searchInput.addEventListener('input', scheduleLoad);
  searchInput.addEventListener('focus', loadSuggestions);
  searchInput.addEventListener('blur', function () { setTimeout(hideSuggestions, 150); });
  roleFilter.addEventListener('change', function () { page = 1; loadUsers(); });
  activeFilter.addEventListener('change', function () { page = 1; loadUsers(); });
  filterForm.addEventListener('submit', function (event) { event.preventDefault(); hideSuggestions(); page = 1; loadUsers(); });
  if (resetButton) resetButton.addEventListener('click', function () {
    searchInput.value = '';
    roleFilter.value = '';
    activeFilter.value = '';
    page = 1;
    hideSuggestions();
    loadUsers();
  });
  if (perPageSelect) perPageSelect.addEventListener('change', function () { perPage = Number(this.value) || 25; page = 1; loadUsers(); });
  if (previousButton) previousButton.addEventListener('click', function () { if (page > 1) { page -= 1; loadUsers(); } });
  if (nextButton) nextButton.addEventListener('click', function () { if (page < totalPages) { page += 1; loadUsers(); } });
  if (createForm) createForm.addEventListener('submit', submitCreate);
  if (editForm) editForm.addEventListener('submit', submitEdit);

  document.addEventListener('click', function (event) {
    var editButton = event.target.closest && event.target.closest('.js-edit-user');
    if (editButton) {
      event.preventDefault();
      openEditModal(editButton.getAttribute('data-id') || '');
      return;
    }
    var deleteButton = event.target.closest && event.target.closest('.js-delete-user');
    if (deleteButton) {
      event.preventDefault();
      if (typeof window.showModalDelete === 'function') {
        window.showModalDelete('Удалить пользователя "' + (deleteButton.getAttribute('data-name') || '') + '"?', {
          action: 'delete',
          id: deleteButton.getAttribute('data-id') || '',
          endpoint: '/ajax/users.php',
          silentToast: true
        });
      }
      return;
    }
    if (event.target.closest && event.target.closest('[data-user-modal-close]')) closeEditModal();
    if (!event.target.closest('.customers-field-wrap')) hideSuggestions();
  });

  document.addEventListener('modal-delete-success', function (event) {
    var detail = event.detail || {};
    if (detail.meta && detail.meta.endpoint === '/ajax/users.php') {
      showToast('Пользователь удалён', 'success');
      page = 1;
      loadUsers();
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeEditModal();
  });

  loadUsers();
})();
