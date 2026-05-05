<?php
require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/includes/Database.php';

$db = getDB();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS subscriber_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        color VARCHAR(7) DEFAULT '#6c757d',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    echo "[OK] Tabla subscriber_groups creada/verificada\n";
} catch (Exception $e) {
    echo "[ERR] subscriber_groups: " . $e->getMessage() . "\n";
}

try {
    $result = $db->query("SHOW COLUMNS FROM subscribers LIKE 'group_id'");
    if ($result->rowCount() === 0) {
        $db->exec("ALTER TABLE subscribers ADD COLUMN group_id INT NULL AFTER name");
        $db->exec("ALTER TABLE subscribers ADD FOREIGN KEY (group_id) REFERENCES subscriber_groups(id) ON DELETE SET NULL");
        echo "[OK] Columna group_id agregada a subscribers\n";
    } else {
        echo "[OK] Columna group_id ya existe en subscribers\n";
    }
} catch (Exception $e) {
    echo "[ERR] subscribers: " . $e->getMessage() . "\n";
}

try {
    $result = $db->query("SHOW COLUMNS FROM campaigns LIKE 'target_groups'");
    if ($result->rowCount() === 0) {
        $db->exec("ALTER TABLE campaigns ADD COLUMN target_groups TEXT NULL AFTER social_enabled");
        echo "[OK] Columna target_groups agregada a campaigns\n";
    } else {
        echo "[OK] Columna target_groups ya existe en campaigns\n";
    }
} catch (Exception $e) {
    echo "[ERR] campaigns: " . $e->getMessage() . "\n";
}

echo "\nMigracion de grupos completada.\n";
