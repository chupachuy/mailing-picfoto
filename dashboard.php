<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

// Estadísticas
$stats = [];

// Suscriptores
$stmt = $db->query("SELECT status, COUNT(*) as count FROM subscribers GROUP BY status");
$stats['subscribers'] = [];
while ($row = $stmt->fetch()) {
    $stats['subscribers'][$row['status']] = $row['count'];
}
$stats['subscribers']['total'] = array_sum($stats['subscribers'] ?? []);

// Campañas
$stmt = $db->query("SELECT status, COUNT(*) as count FROM campaigns GROUP BY status");
$stats['campaigns'] = [];
while ($row = $stmt->fetch()) {
    $stats['campaigns'][$row['status']] = $row['count'];
}
$stats['campaigns']['total'] = array_sum($stats['campaigns'] ?? []);

// Cola pendiente
$stmt = $db->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'");
$stats['queue_pending'] = $stmt->fetch()['count'];

// Últimas campañas
$stmt = $db->query("SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 5");
$recent_campaigns = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PICFOTO - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <?php if ($flash = getFlash()): ?>
            <div
                class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h2 class="mb-4">Dashboard</h2>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-people"></i> Suscriptores</h5>
                        <h2><?= $stats['subscribers']['active'] ?? 0 ?></h2>
                        <small>Activos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-envelope-check"></i> Campañas</h5>
                        <h2><?= $stats['campaigns']['total'] ?? 0 ?></h2>
                        <small>Totales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-clock"></i> En Cola</h5>
                        <h2><?= $stats['queue_pending'] ?></h2>
                        <small>Pendientes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-x-circle"></i> Desuscritos</h5>
                        <h2><?= $stats['subscribers']['unsubscribed'] ?? 0 ?></h2>
                        <small>Total</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Últimas Campañas</h5>
                        <a href="campaigns/create.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus"></i> Nueva Campaña
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_campaigns)): ?>
                            <p class="text-muted">No hay campañas creadas aún.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Asunto</th>
                                            <th>Estado</th>
                                            <th>Progreso</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_campaigns as $c): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($c['name']) ?></td>
                                                <td><?= htmlspecialchars($c['subject']) ?></td>
                                                <td>
                                                    <?php
                                                    $statusMapping = [
                                                        'draft' => 'secondary',
                                                        'queued' => 'info',
                                                        'sending' => 'warning',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $statusClass = $statusMapping[$c['status']] ?? 'secondary';
                                                    ?>
                                                    <span
                                                        class="badge bg-<?= $statusClass ?>"><?= ucfirst($c['status']) ?></span>
                                                </td>
                                                <td>
                                                    <?= $c['sent_count'] + $c['failed_count'] ?> / <?= $c['total_recipients'] ?>
                                                </td>
                                                <td><?= formatDate($c['created_at']) ?></td>
                                                <td>
                                                    <a href="campaigns/view.php?id=<?= $c['id'] ?>"
                                                        class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>