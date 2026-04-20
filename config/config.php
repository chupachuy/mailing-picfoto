<?php
/**
 * Configuración General del Sistema
 */

session_start();

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://localhost/picfotomailing');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_DIR', BASE_PATH . '/uploads');

// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'mailing_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de Envíos
define('BATCH_SIZE', 50);
define('BATCH_DELAY', 20);

// Zona Horaria
date_default_timezone_set('America/Mexico_City');

// Error Reporting (desactivar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Timezone de MySQL
ini_set('date.timezone', 'America/Mexico_City');
