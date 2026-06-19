<?php
if (!function_exists('loadEnv')) {
    function loadEnv($dir) {
        $filePath = $dir . '/.env';
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove surrounding quotes if any
                $value = trim($value, "\"'");
                
                if (getenv($key) === false) {
                    putenv("$key=$value");
                }
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
                if (!isset($_SERVER[$key])) {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

// Automatically load .env from the root folder containing this loader script
loadEnv(__DIR__);
loadEnv(dirname(__DIR__));

