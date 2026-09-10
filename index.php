<?php
require_once __DIR__ . '/init.php';

if (!empty($_SESSION['user'])) {
	require_login();
	redirect('/dashboard.php');
}

redirect('/login.php');

