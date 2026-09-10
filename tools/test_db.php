<?php
require_once __DIR__ . '/../init.php';
echo "<pre>\n";
if (isset($db) && $db instanceof PDO) {
    echo "PDO present: " . get_class($db) . "\n";
    try {
        $r = $db->query("SELECT 1")->fetchColumn();
        echo "SELECT 1 => " . var_export($r, true) . "\n";
    } catch (PDOException $e) {
        echo "PDO exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "No \$db. db_insert exists? " . (function_exists('db_insert') ? 'yes' : 'no') . "\n";
}
echo "</pre>";
