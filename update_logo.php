<?php
require 'config/config.php';
require 'includes/Database.php';

$db = getDB();
$stmt = $db->query("SELECT id, html_body FROM campaigns");
$campaigns = $stmt->fetchAll();

foreach ($campaigns as $c) {
    $new_html = str_replace(
        'https://picfotomailing.xsrv.es/images/Logo-Pic-foto-_Final.png',
        'https://picfoto.mx/wp-content/uploads/2023/11/Logo-Pic-foto-_Final.png',
        $c['html_body']
    );
    $stmt = $db->prepare('UPDATE campaigns SET html_body = ? WHERE id = ?');
    $stmt->execute([$new_html, $c['id']]);
}

echo "Actualizadas: " . count($campaigns) . " campañas";