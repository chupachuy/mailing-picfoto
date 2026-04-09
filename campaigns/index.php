<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin', 'editor']);

$db = getDB();
$stmt = $db->query("SELECT c.*, u.username as editor_name FROM campaigns c LEFT JOIN users u ON c.id_editor = u.id ORDER BY c.created_at DESC");
$campaigns = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campañas - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-send"></i> Campañas</h2>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus"></i> Nueva Campaña
            </a>
        </div>

        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if (empty($campaigns)): ?>
                    <p class="text-muted text-center">No hay campañas creadas.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Asunto</th>
                                    <th>Editor</th>
                                    <th>Estado</th>
                                    <th>Progreso</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                        <td><?= htmlspecialchars($c['subject']) ?></td>
                                        <td><?= htmlspecialchars($c['editor_name']) ?></td>
                                        <td>
                                            <?php
                                            $statusMap = [
                                                'draft' => 'secondary',
                                                'queued' => 'info',
                                                'sending' => 'warning',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $statusClass = $statusMap[$c['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($c['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $progress = $c['total_recipients'] > 0 
                                                ? round(($c['sent_count'] + $c['failed_count']) / $c['total_recipients'] * 100) 
                                                : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" style="width: <?= $progress ?>%"><?= $progress ?>%</div>
                                            </div>
                                        </td>
                                        <td><?= formatDate($c['created_at']) ?></td>
                                        <td>
                                            <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
