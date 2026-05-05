<?php
require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/includes/Database.php';

$db = getDB();

$columns = [
    'accent_color'        => "VARCHAR(7) DEFAULT '#007bff' AFTER link_text",
    'subtitle'            => "VARCHAR(300) AFTER accent_color",
    'logo_url'            => "VARCHAR(500) AFTER subtitle",
    'section2_title'      => "VARCHAR(200) AFTER logo_url",
    'section2_text'       => "TEXT AFTER section2_title",
    'secondary_link_url'  => "VARCHAR(500) AFTER section2_text",
    'secondary_link_text' => "VARCHAR(100) AFTER secondary_link_url",
    'secondary_image_url' => "VARCHAR(500) AFTER secondary_link_text",
    'bg_color'            => "VARCHAR(7) DEFAULT '#f4f4f4' AFTER secondary_image_url",
    'footer_text'         => "TEXT AFTER bg_color",
];

foreach ($columns as $col => $def) {
    try {
        $result = $db->query("SHOW COLUMNS FROM campaigns LIKE '$col'");
        if ($result->rowCount() === 0) {
            $db->exec("ALTER TABLE campaigns ADD COLUMN $col $def");
            echo "[OK] Columna $col agregada\n";
        } else {
            echo "[SKIP] $col ya existe\n";
        }
    } catch (Exception $e) {
        echo "[ERR] $col: " . $e->getMessage() . "\n";
    }
}

echo "\nMigracion de diseno completada.\n";
