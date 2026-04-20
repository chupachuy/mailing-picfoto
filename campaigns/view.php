<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';
require_once '../includes/Mailer.php';

requireRole(['admin', 'editor']);

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('error', 'ID de campaña no especificado');
    redirect('index.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->execute([$id]);
$campaign = $stmt->fetch();

if (!$campaign) {
    setFlash('error', 'Campaña no encontrada');
    redirect('index.php');
}

$mailer = new Mailer();

if (isset($_POST['send_batch'])) {
    $batch_size = 50;
    
    $stmt = $db->prepare("
        SELECT eq.id as queue_id, eq.id_campaign, eq.id_subscriber,
               c.subject, c.html_body,
               s.email, s.name, s.token_unsubscribe
        FROM email_queue eq
        JOIN campaigns c ON eq.id_campaign = c.id
        JOIN subscribers s ON eq.id_subscriber = s.id
        WHERE eq.id_campaign = ? AND eq.status = 'pending'
        ORDER BY eq.date_queued ASC
        LIMIT ?
    ");
    $stmt->execute([$id, $batch_size]);
    $items = $stmt->fetchAll();
    
    $sent = 0;
    $failed = 0;
    
    foreach ($items as $item) {
        $unsubscribeUrl = BASE_URL . '/public/unsubscribe.php?token=' . $item['token_unsubscribe'];
        $htmlBody = str_replace('</body>', $mailer->getUnsubscribeFooter($unsubscribeUrl) . '</body>', $item['html_body']);
        
        $result = $mailer->send($item['email'], $item['subject'], $htmlBody, $item['name']);
        
        if ($result['success']) {
            $update = $db->prepare("UPDATE email_queue SET status = 'sent', date_processed = NOW() WHERE id = ?");
            $update->execute([$item['queue_id']]);
            $sent++;
        } else {
            $update = $db->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
            $update->execute([$result['error'], $item['queue_id']]);
            $failed++;
        }
        
        usleep(20000000);
    }
    
    $updateCampaign = $db->prepare("UPDATE campaigns SET sent_count = sent_count + ?, failed_count = failed_count + ? WHERE id = ?");
    $updateCampaign->execute([$sent, $failed, $id]);
    
    $checkPending = $db->prepare("SELECT COUNT(*) FROM email_queue WHERE id_campaign = ? AND status = 'pending'");
    $checkPending->execute([$id]);
    $pendingCount = $checkPending->fetchColumn();
    
    if ($pendingCount == 0) {
        $db->prepare("UPDATE campaigns SET status = 'completed', sent_at = NOW() WHERE id = ?")->execute([$id]);
    }
    
    setFlash('success', "Enviados: $sent, Fallidos: $failed");
    redirect("view.php?id=$id");
}

if (isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email'] ?? '');
    
    if (empty($test_email)) {
        setFlash('error', 'Ingresa un correo de prueba');
    } else {
        setFlash('error', 'Configura SMTP para enviar pruebas');
    }
    redirect("view.php?id=$id");
}

if (isset($_POST['send_campaign'])) {
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE status = 'active'");
    $stmt->execute();
    $subscribers = $stmt->fetchAll();
    
    if (empty($subscribers)) {
        setFlash('error', 'No hay suscriptores activos');
        redirect("view.php?id=$id");
    }
    
    $db->beginTransaction();
    
    try {
        foreach ($subscribers as $sub) {
            $check = $db->prepare("SELECT id FROM email_queue WHERE id_campaign = ? AND id_subscriber = ?");
            $check->execute([$id, $sub['id']]);
            
            if (!$check->fetch()) {
                $stmt = $db->prepare("INSERT INTO email_queue (id_campaign, id_subscriber, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$id, $sub['id']]);
            }
        }
        
        $update = $db->prepare("UPDATE campaigns SET status = 'queued', total_recipients = ? WHERE id = ?");
        $update->execute([count($subscribers), $id]);
        
        $db->commit();
        
        setFlash('success', 'Campaña encolada. ' . count($subscribers) . ' correos en cola.');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Error al encolar: ' . $e->getMessage());
    }
    
    redirect("view.php?id=$id");
}

$stmt = $db->prepare("SELECT * FROM email_queue WHERE id_campaign = ? ORDER BY date_queued DESC");
$stmt->execute([$id]);
$queue_items = $stmt->fetchAll();

$pending = $db->prepare("SELECT COUNT(*) FROM email_queue WHERE id_campaign = ? AND status = 'pending'");
$pending->execute([$id]);
$stats = [
    'pending' => $pending->fetchColumn(),
    'sent' => $campaign['sent_count'],
    'failed' => $campaign['failed_count']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaign['name']) ?> - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?= htmlspecialchars($campaign['name']) ?></h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Información</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Asunto:</strong> <?= htmlspecialchars($campaign['subject']) ?></p>
                        <p><strong>Estado:</strong> 
                            <?php
                            $statusMap = [
                                'draft' => 'secondary',
                                'queued' => 'info',
                                'sending' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $statusClass = $statusMap[$campaign['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($campaign['status']) ?></span>
                        </p>
                        <p><strong>Destinatarios:</strong> <?= $campaign['total_recipients'] ?></p>
                        <p><strong>Enviados:</strong> <?= $stats['sent'] ?></p>
                        <p><strong>Fallidos:</strong> <?= $stats['failed'] ?></p>
                        <p><strong>Fecha:</strong> <?= formatDate($campaign['created_at']) ?></p>
                    </div>
                </div>

                <?php if ($campaign['status'] === 'draft'): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Enviar Prueba</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="input-group">
                                <input type="email" name="test_email" class="form-control" placeholder="correo@ejemplo.com" required>
                                <button type="submit" name="send_test" class="btn btn-outline-primary">
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-rocket"></i> Lanzar Campaña</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">La campaña se encolará y se enviará automáticamente mediante el cron job.</p>
                        <form method="POST">
                            <button type="submit" name="send_campaign" class="btn btn-primary w-100">
                                <i class="bi bi-send-fill"></i> Encolar Campaña
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($campaign['status'] === 'queued' || $campaign['status'] === 'sending'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-send"></i> Enviar Siguiente Lote</h5>
                    </div>
                    <div class="card-body">
                        <p class="small">Pendientes: <?= $stats['pending'] ?></p>
                        <form method="POST" onsubmit="return confirm('¿Enviar los siguientes 50 correos?');">
                            <button type="submit" name="send_batch" class="btn btn-success w-100">
                                <i class="bi bi-send-fill"></i> Enviar 50 Correos
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Vista Previa del Correo</h5>
                    </div>
                    <div class="card-body">
                        <iframe srcdoc="<?= htmlspecialchars($campaign['html_body']) ?>" 
                                style="width: 100%; height: 500px; border: 1px solid #ddd;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
