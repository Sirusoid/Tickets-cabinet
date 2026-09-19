(function () {
  'use strict';

  var form = document.getElementById('auditFilters');
  var tbody = document.getElementById('auditTbody');
  var summary = document.getElementById('auditSummary');
  var pageLabel = document.getElementById('auditPageLabel');
  var prev = document.getElementById('auditPrev');
  var next = document.getElementById('auditNext');
  var pagerPerPage = document.getElementById('auditPagerPerPage');
  var timer = null;
  var page = 1;
  var totalPages = 1;

  if (!form || !tbody) return;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }

  function formatDate(value) {
    var date = new Date(String(value || '').replace(' ', 'T'));
    if (isNaN(date.getTime())) return value || '—';
    return date.toLocaleString('ru-RU');
  }

  function detailsHtml(details) {
    if (!details || !Object.keys(details).length) return '—';
    return '<details><summary>Показать</summary><pre>' + escapeHtml(JSON.stringify(details, null, 2)) + '</pre></details>';
  }

  function renderRows(rows) {
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="audit-empty">Записей за выбранный период нет.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (row) {
      return '<tr>' +
        '<td>' + escapeHtml(formatDate(row.created_at)) + '</td>' +
        '<td>' + escapeHtml(row.source) + '</td>' +
        '<td>' + escapeHtml(row.actor_name) + '</td>' +
        '<td><span class="audit-action">' + escapeHtml(row.action_label) + '</span><small class="audit-action-code">' + escapeHtml(row.action) + '</small></td>' +
        '<td>' + escapeHtml(row.entity) + '</td>' +
        '<td>' + escapeHtml(row.ip_address) + '</td>' +
        '<td>' + detailsHtml(row.details) + '</td>' +
        '</tr>';
    }).join('');
  }

  function loadAudit() {
    var data = new FormData(form);
    data.set('format', 'json');
    data.set('page', String(page));
    data.set('per_page', pagerPerPage ? String(pagerPerPage.value) : '25');
    var params = new URLSearchParams();
    data.forEach(function (value, key) { params.set(key, value); });
    tbody.classList.add('is-loading');
    fetch('/admin/audit.php?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) throw new Error(payload && payload.message ? payload.message : 'Ошибка загрузки журнала');
        page = Number(payload.page || 1);
        totalPages = Number(payload.total_pages || 1);
        if (pagerPerPage) pagerPerPage.value = String(payload.per_page || 25);
        renderRows(payload.rows || []);
        if (summary) summary.innerHTML = 'Найдено записей: <strong>' + Number(payload.total || 0).toLocaleString('ru-RU') + '</strong>';
        if (pageLabel) pageLabel.textContent = page + ' / ' + totalPages;
        if (prev) prev.disabled = page <= 1;
        if (next) next.disabled = page >= totalPages;
      })
      .catch(function (error) { tbody.innerHTML = '<tr><td colspan="7" class="audit-empty">' + escapeHtml(error.message) + '</td></tr>'; })
      .finally(function () { tbody.classList.remove('is-loading'); });
  }

  form.addEventListener('submit', function (event) { event.preventDefault(); page = 1; loadAudit(); });
  if (pagerPerPage) pagerPerPage.addEventListener('change', function () {
    page = 1;
    loadAudit();
  });
  Array.prototype.forEach.call(form.querySelectorAll('input, select'), function (field) {
    field.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { page = 1; loadAudit(); }, 300); });
    field.addEventListener('change', function () { page = 1; loadAudit(); });
  });
  if (prev) prev.addEventListener('click', function () { if (page > 1) { page--; loadAudit(); } });
  if (next) next.addEventListener('click', function () { if (page < totalPages) { page++; loadAudit(); } });
})();