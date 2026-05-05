<?php
require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/includes/Database.php';

$db = getDB();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS social_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        social_name VARCHAR(50) NOT NULL,
        social_url VARCHAR(500) NOT NULL DEFAULT '',
        social_icon VARCHAR(10) DEFAULT '',
        social_color VARCHAR(7) DEFAULT '#000000',
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB");
    echo "[OK] Tabla social_config creada/verificada\n";
} catch (Exception $e) {
    echo "[ERR] social_config: " . $e->getMessage() . "\n";
}

try {
    $result = $db->query("SHOW COLUMNS FROM campaigns LIKE 'social_enabled'");
    if ($result->rowCount() === 0) {
        $db->exec("ALTER TABLE campaigns ADD COLUMN social_enabled BOOLEAN DEFAULT TRUE AFTER link_text");
        echo "[OK] Columna social_enabled agregada a campaigns\n";
    } else {
        echo "[OK] Columna social_enabled ya existe en campaigns\n";
    }
} catch (Exception $e) {
    echo "[ERR] campaigns: " . $e->getMessage() . "\n";
}

try {
    $stmt = $db->query("SELECT COUNT(*) FROM social_config");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO social_config (social_name, social_url, social_icon, social_color, sort_order) VALUES
            ('Facebook', '', 'f', '#1877F2', 1),
            ('TikTok', '', '♫', '#000000', 2),
            ('Instagram', '', '★', '#E4405F', 3),
            ('YouTube', '', '▶', '#FF0000', 4)");
        echo "[OK] Datos iniciales insertados en social_config\n";
    } else {
        echo "[OK] social_config ya tiene datos\n";
    }
} catch (Exception $e) {
    echo "[ERR] insert: " . $e->getMessage() . "\n";
}

echo "\nMigracion completada.\n";
