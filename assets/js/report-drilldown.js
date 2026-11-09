(function () {
    'use strict';

    document.querySelectorAll('[data-report-url], [data-ticket-url]').forEach(function (row) {
        var navigate = function () {
            var url = row.getAttribute('data-ticket-url') || row.getAttribute('data-report-url');
            if (url) {
                window.location.href = url;
            }
        };

        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, textarea')) {
                return;
            }
            navigate();
        });

        row.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            navigate();
        });
    });
})();
