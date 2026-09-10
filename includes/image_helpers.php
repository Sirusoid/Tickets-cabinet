<?php
// includes/image_helpers.php
// Общие хелперы для работы с изображениями (устойчивые к разным версиям PHP)

if (!function_exists('transliterate_filename')) {
    function transliterate_filename($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $trans = [
            // Russian uppercase
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y',
            'К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F',
            'Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
            // Russian lowercase
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            // Kazakh uppercase
            'Ә'=>'A','Ғ'=>'Gh','Қ'=>'Q','Ң'=>'Ng','Ө'=>'O','Ұ'=>'U','Ү'=>'U','Һ'=>'H','І'=>'I',
            // Kazakh lowercase
            'ә'=>'a','ғ'=>'gh','қ'=>'q','ң'=>'ng','ө'=>'o','ұ'=>'u','ү'=>'u','һ'=>'h','і'=>'i'
        ];

        $name = strtr($name, $trans);
        // Replace non-word characters with underscore (unicode-aware)
        $name = preg_replace('/[^\p{L}\p{N}\-_\.]+/u', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_-');
        if (function_exists('mb_strlen') && mb_strlen($name) > 120) $name = mb_substr($name, 0, 120);
        elseif (strlen($name) > 120) $name = substr($name, 0, 120);

        $ext = strtolower($ext);
        return $ext === '' ? $name : ($name . '.' . $ext);
    }
}

if (!function_exists('project_to_fs')) {
    function project_to_fs($publicUrl) {
        $projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
        // Normalize slashes
        $publicUrl = str_replace('\\', '/', $publicUrl);
        if (strpos($publicUrl, '/') === 0) {
            return rtrim($projectRoot, DIRECTORY_SEPARATOR) . $publicUrl;
        }
        return rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($publicUrl, '/');
    }
}

if (!function_exists('safe_unlink_in_dir')) {
    function safe_unlink_in_dir($publicUrl, $allowedPublicDir) {
        if (empty($publicUrl)) return false;
        $fs = project_to_fs($publicUrl);
        $allowedFs = realpath(__DIR__ . '/..' . $allowedPublicDir);
        if ($allowedFs === false) return false;
        $allowedFs = rtrim($allowedFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $real = realpath($fs);
        if ($real === false) return false;
        // Ensure the real path is inside allowed directory
        if (strpos($real, $allowedFs) !== 0) {
            error_log('safe_unlink_in_dir: attempt to delete outside allowed dir: ' . $real);
            return false;
        }
        if (is_file($real)) return @unlink($real);
        return false;
    }
}

if (!function_exists('rand_hex')) {
    function rand_hex($len = 6) {
        // Try random_bytes (PHP7+), then openssl, then mt_rand fallback
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes((int)$len));
            } catch (Exception $e) {
                // fallthrough
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes((int)$len);
            if ($bytes !== false) return bin2hex($bytes);
        }
        // fallback
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= sprintf('%02x', mt_rand(0, 255));
        }
        return $out;
    }
}

if (!function_exists('dbg_log')) {
    function dbg_log($msg) {
        $projectLogsDir = __DIR__ . '/../logs';
        if (!is_dir($projectLogsDir)) @mkdir($projectLogsDir, 0755, true);
        $logFile = rtrim($projectLogsDir, '/') . '/image_uploads_debug.log';
        @file_put_contents($logFile, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
    }
}
