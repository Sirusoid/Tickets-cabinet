(function () {
  'use strict';

  var form = document.getElementById('customersFilters');
  var nameInput = document.getElementById('customers-name');
  var phoneInput = document.getElementById('customers-phone');
  var tbody = document.getElementById('customersTbody');
  var count = document.getElementById('customersCount');
  var pager = document.getElementById('customersPager');
  var summary = document.getElementById('customersSummary');
  var prevPageButton = document.getElementById('customersPrevPage');
  var nextPageButton = document.getElementById('customersNextPage');
  var currentPageLabel = document.getElementById('customersCurrentPage');
  var nameSuggestions = document.getElementById('customers-name-suggestions');
  var phoneSuggestions = document.getElementById('customers-phone-suggestions');
  var perPageSelect = document.getElementById('customersPerPage');
  var exportLink = document.getElementById('customersExport');
  var editModal = document.getElementById('customerEditModal');
  var editForm = document.getElementById('customerEditForm');
  var editError = document.getElementById('customerEditError');
  var editPhoneInput = document.getElementById('customerEditPhone');
  var debounceTimer = null;
  var suggestionTimer = null;
  var page = 1;
  var perPage = perPageSelect ? Number(perPageSelect.value || 25) : 25;

  if (!form || !nameInput || !phoneInput || !tbody) return;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }

  function normalizePhone(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function formatPhone(value) {
    var digits = normalizePhone(value);
    if (digits.charAt(0) === '8') digits = '7' + digits.slice(1);
    if (digits.length === 10 && digits.charAt(0) !== '7') digits = '7' + digits;
    if (digits.length === 11 && digits.charAt(0) === '7') {
      return '+7 ' + digits.slice(1, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7, 9) + ' ' + digits.slice(9, 11);
    }
    return digits ? '+' + digits : '—';
  }

  function formatEditablePhone(value) {
    var digits = normalizePhone(value);
    return digits ? formatPhone(digits) : '';
  }

  function canonicalPhone(value) {
    var digits = normalizePhone(formatEditablePhone(value));
    return digits ? '+' + digits : '';
  }

  function showToast(message, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type || 'info', { duration: 2500 });
    }
  }

  function closeEditModal() {
    if (!editModal) return;
    editModal.style.display = 'none';
    editModal.setAttribute('aria-hidden', 'true');
    if (editError) editError.textContent = '';
  }

  function openEditModal(customerId) {
    if (!editModal) return;
    fetch('/ajax/customer.php?action=get&id=' + encodeURIComponent(customerId), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) throw new Error((payload && payload.message) || 'Не удалось загрузить клиента');
        var customer = payload.data;
        document.getElementById('customerEditId').value = customer.id || '';
        document.getElementById('customerEditName').value = customer.full_name || '';
        editPhoneInput.value = formatEditablePhone(customer.phone || '');
        document.getElementById('customerEditEmail').value = customer.email || '';
        document.getElementById('customerEditCity').value = customer.city || '';
        document.getElementById('customerEditBirthDate').value = customer.birth_date || '';
        document.getElementById('customerEditGender').value = customer.gender || '';
        document.getElementById('customerEditNote').value = customer.note || '';
        editModal.style.display = 'flex';
        editModal.setAttribute('aria-hidden', 'false');
        document.getElementById('customerEditName').focus();
      })
      .catch(function (error) { showToast(error.message || 'Ошибка загрузки клиента', 'error'); });
  }

  function getFilters() {
    return {
      name: nameInput.value.trim(),
      phone: normalizePhone(phoneInput.value)
    };
  }

  function updateExportLink() {
    if (!exportLink) return;
    var filters = getFilters();
    var params = new URLSearchParams({ full_name: filters.name, phone: filters.phone });
    exportLink.href = '/customers/export_excel.php?' + params.toString();
  }

  function renderRows(rows, offset) {
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="customers-empty">Клиенты по заданным условиям не найдены</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(function (customer, index) {
      var email = customer.email ? escapeHtml(customer.email) : '—';
      var phone = customer.phone ? escapeHtml(formatPhone(customer.phone)) : '—';
      return '<tr>' +
        '<td>' + (offset + index + 1) + '</td>' +
        '<td>' + escapeHtml(customer.id) + '</td>' +
        '<td><strong>' + escapeHtml(customer.full_name) + '</strong></td>' +
        '<td>' + phone + '</td>' +
        '<td>' + email + '</td>' +
        '<td class="actions-col"><div class="action-buttons">' +
        '<button type="button" class="btn btn-ghost btn-sm js-edit-customer" data-id="' + escapeHtml(customer.id) + '">Редактировать</button>' +
        '<button type="button" class="btn btn-danger btn-sm js-delete-customer" data-id="' + escapeHtml(customer.id) + '" data-name="' + escapeHtml(customer.full_name) + '">Удалить</button>' +
        '</div></td>' +
        '</tr>';
    }).join('');
  }

  function renderPagination(currentPage, totalPages) {
    if (!pager) return;
    pager.classList.toggle('is-empty', !totalPages);
    if (summary) {
      summary.textContent = 'Клиентов: ' + Number(window.customersTotal || 0).toLocaleString('ru-RU');
    }
    if (currentPageLabel) currentPageLabel.textContent = currentPage + ' / ' + totalPages;
    if (prevPageButton) prevPageButton.disabled = currentPage <= 1;
    if (nextPageButton) nextPageButton.disabled = currentPage >= totalPages;
  }

  function updateCount(total) {
    if (count) {
      count.innerHTML = 'Клиентов: <strong>' + Number(total || 0).toLocaleString('ru-RU') + '</strong>';
    }
    window.customersTotal = Number(total || 0);
    if (summary) summary.textContent = 'Клиентов: ' + Number(total || 0).toLocaleString('ru-RU');
  }

  function loadCustomers() {
    var filters = getFilters();
    var params = new URLSearchParams({
      action: 'list',
      name: filters.name,
      phone: filters.phone,
      page: String(page),
      per_page: String(perPage)
    });

    tbody.classList.add('is-loading');
    fetch('/ajax/customer.php?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) throw new Error((payload && payload.message) || 'Ошибка загрузки клиентов');
        page = Number(payload.page || 1);
        renderRows(payload.data || [], (page - 1) * perPage);
        updateCount(payload.total || 0);
        renderPagination(page, Number(payload.total_pages || 1));
      })
      .catch(function () {
        tbody.innerHTML = '<tr><td colspan="6" class="customers-empty customers-empty--error">Не удалось загрузить клиентов</td></tr>';
      })
      .finally(function () { tbody.classList.remove('is-loading'); });
  }

  function showSuggestions(target, rows) {
    var box = target === 'name' ? nameSuggestions : phoneSuggestions;
    if (!box) return;
    box.innerHTML = '';
    if (!rows.length) {
      box.style.display = 'none';
      return;
    }

    rows.forEach(function (customer) {
      var item = document.createElement('div');
      item.className = 'typeahead-item';
      item.setAttribute('role', 'option');
      item.innerHTML = '<strong>' + escapeHtml(customer.full_name) + '</strong>' +
        (customer.phone ? ' <span>' + escapeHtml(formatPhone(customer.phone)) + '</span>' : '');
      item.addEventListener('mousedown', function (event) { event.preventDefault(); });
      item.addEventListener('click', function () {
        if (target === 'name') nameInput.value = customer.full_name || '';
        if (target === 'phone') phoneInput.value = normalizePhone(customer.phone || '');
        hideSuggestions();
        page = 1;
        loadCustomers();
      });
      box.appendChild(item);
    });
    box.style.display = 'block';
  }

  function hideSuggestions() {
    [nameSuggestions, phoneSuggestions].forEach(function (box) {
      if (box) {
        box.style.display = 'none';
        box.innerHTML = '';
      }
    });
  }

  function loadSuggestions(target) {
    var filters = getFilters();
    var query = target === 'name' ? filters.name : filters.phone;
    if (query.length < 1) {
      hideSuggestions();
      return;
    }

    var params = new URLSearchParams({ action: 'list', name: target === 'name' ? query : '', phone: target === 'phone' ? query : '', per_page: '8', page: '1' });
    fetch('/ajax/customer.php?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) { showSuggestions(target, payload && payload.success ? (payload.data || []) : []); })
      .catch(function () { hideSuggestions(); });
  }

  function scheduleLoad(target) {
    clearTimeout(debounceTimer);
    clearTimeout(suggestionTimer);
    page = 1;
    suggestionTimer = setTimeout(function () { loadSuggestions(target); }, 220);
    debounceTimer = setTimeout(loadCustomers, 300);
  }

  nameInput.addEventListener('input', function () { scheduleLoad('name'); });
  phoneInput.addEventListener('input', function () {
    var normalized = normalizePhone(phoneInput.value);
    if (phoneInput.value !== normalized) phoneInput.value = normalized;
    scheduleLoad('phone');
  });
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    page = 1;
    hideSuggestions();
    loadCustomers();
  });
  document.addEventListener('click', function (event) {
    var editButton = event.target.closest && event.target.closest('.js-edit-customer');
    if (editButton) {
      event.preventDefault();
      openEditModal(editButton.getAttribute('data-id') || '');
      return;
    }
    if (event.target.closest && event.target.closest('[data-customer-edit-close]')) {
      closeEditModal();
    }
  });
  document.addEventListener('modal-delete-success', function (event) {
    var detail = event.detail || {};
    if (detail.meta && detail.meta.endpoint === '/ajax/customer.php') {
      showToast('Клиент удалён', 'success');
      page = 1;
      loadCustomers();
    }
  });
  if (editForm) {
    if (editPhoneInput) {
      editPhoneInput.addEventListener('input', function () {
        var formatted = formatEditablePhone(this.value);
        if (this.value !== formatted) {
          this.value = formatted;
          try { this.setSelectionRange(formatted.length, formatted.length); } catch (e) {}
        }
      });
    }
    editForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (editError) editError.textContent = '';
      var formData = new FormData(editForm);
      formData.set('action', 'update');
      formData.set('csrf_token', window.APP_CSRF_TOKEN || '');
      var phone = document.getElementById('customerEditPhone');
      if (phone) formData.set('phone', canonicalPhone(phone.value));
      var saveButton = editForm.querySelector('button[type="submit"]');
      if (saveButton) saveButton.disabled = true;
      fetch('/ajax/customer.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (!payload || !payload.success) throw new Error((payload && payload.message) || 'Не удалось сохранить клиента');
          closeEditModal();
          showToast('Данные клиента сохранены', 'success');
          loadCustomers();
        })
        .catch(function (error) {
          if (editError) editError.textContent = error.message || 'Ошибка сохранения';
          showToast(error.message || 'Ошибка сохранения клиента', 'error');
        })
        .finally(function () { if (saveButton) saveButton.disabled = false; });
    });
  }
  if (perPageSelect) {
    perPageSelect.addEventListener('change', function () {
      perPage = Number(this.value) || 25;
      page = 1;
      loadCustomers();
    });
  }
  if (prevPageButton) {
    prevPageButton.addEventListener('click', function () {
      if (page > 1) {
        page -= 1;
        loadCustomers();
      }
    });
  }
  if (nextPageButton) {
    nextPageButton.addEventListener('click', function () {
      var totalPages = currentPageLabel ? Number(currentPageLabel.textContent.split('/')[1]) || 1 : 1;
      if (page < totalPages) {
        page += 1;
        loadCustomers();
      }
    });
  }
  nameInput.addEventListener('input', updateExportLink);
  phoneInput.addEventListener('input', updateExportLink);
  document.addEventListener('click', function (event) {
    if (!event.target.closest('.customers-field-wrap')) hideSuggestions();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeEditModal();
  });
  updateExportLink();
})();