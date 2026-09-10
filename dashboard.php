<?php
// dashboard.php — панель управления с содержимым отчёта продаж
require_once __DIR__ . '/init.php';
require_login();

$use_sidebar = true;
$active_menu = 'dashboard';
$page_title_meta = 'Панель';
$page_styles = ['/assets/css/reports.css'];

require __DIR__ . '/includes/header.php';

$reportsEmbedded = true;
require __DIR__ . '/reports/sales.php';

require __DIR__ . '/includes/footer.php';
