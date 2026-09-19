(function () {
  'use strict';

  var form = document.getElementById('auditFilters');
  if (!form) return;
  var searchTimer = null;
  var search = document.getElementById('audit-search');
  var dateFrom = document.getElementById('audit-date-from');
  var dateTo = document.getElementById('audit-date-to');
  var action = document.getElementById('audit-action');
  var perPage = document.getElementById('audit-per-page');

  function submitLive() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      var page = form.querySelector('input[name="page"]');
      if (page) page.value = '1';
      form.submit();
    }, 250);
  }

  if (search) search.addEventListener('input', submitLive);
  [dateFrom, dateTo].forEach(function (field) {
    if (field) field.addEventListener('change', submitLive);
  });
  [action, perPage].forEach(function (field) {
    if (field) field.addEventListener('change', function () { form.submit(); });
  });
})();