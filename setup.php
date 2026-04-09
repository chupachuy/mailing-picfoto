<?php
/**
 * Script de Configuración Inicial
 * Ejecutar una sola vez para configurar la base de datos y usuario admin
 */

require_once 'config/config.php';
require_once 'includes/Database.php';

echo "=== Configurando Sistema de Mailing ===\n\n";

$db = getDB();

echo "1. Creando tablas...\n";
$sql = file_get_contents('database/schema.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $db->exec($statement);
        } catch (Exception $e) {
            // Ignorar errores de tabla ya existente
        }
    }
}
echo "   [OK] Tablas creadas\n\n";

echo "2. Configurando usuario admin...\n";
$password = 'admin123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$password_hash]);
    echo "   [OK] Usuario: admin / Contraseña: $password\n\n";
} catch (Exception $e) {
    echo "   [ERROR] " . $e->getMessage() . "\n\n";
}

echo "3. Verificando estructura...\n";
$tables = ['users', 'subscribers', 'campaigns', 'email_queue', 'smtp_config', 'email_logs'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   [OK] $table: $count registros\n";
    } catch (Exception $e) {
        echo "   [ERROR] $table: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Configuración Completada ===\n";
echo "Accede a: " . BASE_URL . "/login.php\n";
echo "Usuario: admin\n";
echo "Contraseña: admin123\n";
