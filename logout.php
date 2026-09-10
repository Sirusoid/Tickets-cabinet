<?php
require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf($_POST['csrf_token'] ?? '')) {
	http_response_code(403);
	exit('Forbidden');
}

logout_user();
redirect('/login.php');
