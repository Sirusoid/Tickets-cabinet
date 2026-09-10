<?php
if (!function_exists('excel_autoload')) {
	function excel_autoload() {
		static $loaded = null;
		if ($loaded !== null) {
			return $loaded;
		}

		$autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
		if (!is_file($autoload)) {
			return $loaded = false;
		}

		require_once $autoload;
		return $loaded = class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet');
	}
}
