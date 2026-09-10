<?php
// Diagnostic script - safe to remove after use
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<h1>Diagnostics — Жас Сахна Cabinet</h1>';
echo '<p><strong>PHP version:</strong> ' . PHP_VERSION . ' (' . PHP_SAPI . ')</p>';

echo '<h2>Loaded extensions</h2>';
$ext = get_loaded_extensions();
sort($ext);
echo '<pre>' . implode(', ', $ext) . '</pre>';

$files = [
    'config.php',
    'init.php',
    'includes/db.php',
    'includes/functions.php',
    'includes/auth.php',
    'login.php',
    'install.php',
];

echo '<h2>File checks</h2>';
echo '<ul>';
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo '<li>' . htmlspecialchars($f) . ' — exists, size: ' . filesize($path) . ' bytes</li>';
    } else {
        echo '<li>' . htmlspecialchars($f) . ' — <strong>missing</strong></li>';
    }
}
echo '</ul>';

echo '<h2>Attempt to include files</h2>';
try {
    require_once __DIR__ . '/config.php';
    echo '<p>Included config.php OK</p>';
} catch (Throwable $e) {
    echo '<p style="color:red">Error including config.php: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

try {
    require_once __DIR__ . '/includes/functions.php';
    echo '<p>Included includes/functions.php OK</p>';
} catch (Throwable $e) {
    echo '<p style="color:red">Error including includes/functions.php: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>Database connection test</h2>';
try {
    require_once __DIR__ . '/includes/db.php';
    // attempt connection (this will exit with message on failure in db_connect)
    $ok = false;
    try {
        $pdo = db_connect();
        if ($pdo) {
            echo '<p>Database connection: OK</p>';
            $ok = true;
        }
    } catch (Throwable $e) {
        echo '<p style="color:red">DB connect threw: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    if (!$ok) {
        echo '<p style="color:orange">If DB connection failed, check credentials in config.php and hosting DB user.</p>';
    }
} catch (Throwable $e) {
    echo '<p style="color:red">Error including includes/db.php: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>Server error log hint</h2>';
echo '<p>Check hosting control panel -> Logs or /var/log/apache2, /var/log/nginx or error_log file for PHP fatal errors.</p>';

echo '<h2>Next steps</h2>';
echo '<ol>';
echo '<li>Open this file: /diagnose.php in browser and copy output here.</li>';
echo '<li>If shows DB connection error, verify DB credentials and that MySQL user has access.</li>';
echo '<li>If files missing, re-upload project files.</li>';
echo '</ol>';
