<?php
/**
 * CRON Worker - Procesa la cola de correos
 * Ejecutar cada 1-5 minutos via CRON
 * 
 * Configurar en CRON:
 * */15 * * * * /usr/bin/php /path/to/cron_worker.php > /dev/null 2>&1
 * */5 * * * * /usr/bin/php /path/to/cron_worker.php > /dev/null 2>&1
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';

$db = getDB();
$mailer = new Mailer();

$batch_size = BATCH_SIZE;
$max_attempts = 3;

$stmt = $db->prepare("
    SELECT eq.id as queue_id, eq.id_campaign, eq.id_subscriber,
           c.subject, c.html_body,
           s.email, s.name, s.token_unsubscribe
    FROM email_queue eq
    JOIN campaigns c ON eq.id_campaign = c.id
    JOIN subscribers s ON eq.id_subscriber = s.id
    WHERE eq.status = 'pending' AND eq.attempts < ?
    ORDER BY eq.date_queued ASC
    LIMIT ?
");

$stmt->execute([$max_attempts, $batch_size]);
$items = $stmt->fetchAll();

if (empty($items)) {
    echo "No hay correos pendientes.\n";
    exit;
}

echo "Procesando " . count($items) . " correos...\n";

foreach ($items as $item) {
    $unsubscribeUrl = BASE_URL . '/public/unsubscribe.php?token=' . $item['token_unsubscribe'];
    $htmlBody = str_replace('</body>', $mailer->getUnsubscribeFooter($unsubscribeUrl) . '</body>', $item['html_body']);
    
    $result = $mailer->send($item['email'], $item['subject'], $htmlBody, $item['name']);
    
    if ($result['success']) {
        $update = $db->prepare("UPDATE email_queue SET status = 'sent', date_processed = NOW(), last_attempt_at = NOW() WHERE id = ?");
        $update->execute([$item['queue_id']]);
        
        $update2 = $db->prepare("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = ?");
        $update2->execute([$item['id_campaign']]);
        
        $log = $db->prepare("INSERT INTO email_logs (id_campaign, id_subscriber, status) VALUES (?, ?, 'sent')");
        $log->execute([$item['id_campaign'], $item['id_subscriber']]);
        
        echo "[OK] {$item['email']}\n";
    } else {
        $update = $db->prepare("UPDATE email_queue SET status = 'failed', error_message = ?, attempts = attempts + 1, last_attempt_at = NOW() WHERE id = ?");
        $update->execute([$result['error'], $item['queue_id']]);
        
        $update2 = $db->prepare("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = ?");
        $update2->execute([$item['id_campaign']]);
        
        $log = $db->prepare("INSERT INTO email_logs (id_campaign, id_subscriber, status, error_message) VALUES (?, ?, 'failed', ?)");
        $log->execute([$item['id_campaign'], $item['id_subscriber'], $result['error']]);
        
        echo "[FAIL] {$item['email']}: {$result['error']}\n";
    }
    
    sleep(BATCH_DELAY);
}

$check = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'");
$pending = $check->fetchColumn();

if ($pending == 0) {
    $db->query("UPDATE campaigns SET status = 'completed', sent_at = NOW() WHERE status = 'queued'");
    echo "Campañas completadas.\n";
}

echo "Procesamiento finalizado. Pendientes: $pending\n";
