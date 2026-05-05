<?php
/**
 * Configuración General del Sistema
 */

session_start();

define('BASE_PATH', dirname(__DIR__));

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_ENV) && !getenv($key)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

define('BASE_URL', env('APP_BASE_URL', 'http://localhost/picfotomailing'));
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_DIR', BASE_PATH . '/uploads');

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'mailing_system'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

define('BATCH_SIZE', (int)env('BATCH_SIZE', '50'));
define('BATCH_DELAY', (int)env('BATCH_DELAY', '20'));

define('SMTP_SSL_VERIFY', strtolower(env('SMTP_SSL_VERIFY', 'false')) === 'true');

define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', ''));

define('SOCIAL_FACEBOOK', env('SOCIAL_FACEBOOK', '#'));
define('SOCIAL_TIKTOK', env('SOCIAL_TIKTOK', '#'));
define('SOCIAL_INSTAGRAM', env('SOCIAL_INSTAGRAM', '#'));
define('SOCIAL_YOUTUBE', env('SOCIAL_YOUTUBE', '#'));

date_default_timezone_set(env('APP_TIMEZONE', 'America/Mexico_City'));

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('date.timezone', env('APP_TIMEZONE', 'America/Mexico_City'));

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }
    return $value !== null && $value !== false ? $value : $default;
}
