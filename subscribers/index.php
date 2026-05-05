<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin', 'editor']);

$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$filterGroup = $_GET['group'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';

// ─── GROUP MANAGEMENT ──────────────────────────────
if (isset($_POST['add_group'])) {
    if (!verifyCsrf()) { redirect('index.php'); }
    $name = trim($_POST['group_name'] ?? '');
    $color = trim($_POST['group_color'] ?? '#6c757d');
    if (!empty($name)) {
        try {
            $db->prepare("INSERT INTO subscriber_groups (name, color) VALUES (?, ?)")->execute([$name, $color]);
            setFlash('success', 'Grupo creado');
        } catch (Exception $e) {
            setFlash('error', 'El grupo ya existe');
        }
    }
    redirect('index.php?group=' . $filterGroup . '&status=' . $filterStatus);
}

if (isset($_POST['edit_group'])) {
    if (!verifyCsrf()) { redirect('index.php'); }
    $id = $_POST['group_id'];
    $name = trim($_POST['group_name'] ?? '');
    $color = trim($_POST['group_color'] ?? '#6c757d');
    if (!empty($name)) {
        try {
            $db->prepare("UPDATE subscriber_groups SET name = ?, color = ? WHERE id = ?")->execute([$name, $color, $id]);
            setFlash('success', 'Grupo actualizado');
        } catch (Exception $e) {
            setFlash('error', 'El nombre ya existe');
        }
    }
    redirect('index.php?group=' . $filterGroup . '&status=' . $filterStatus);
}

if (isset($_POST['delete_group'])) {
    if (!verifyCsrf()) { redirect('index.php'); }
    $id = $_POST['group_id'];
    $db->prepare("DELETE FROM subscriber_groups WHERE id = ?")->execute([$id]);
    setFlash('success', 'Grupo eliminado');
    redirect('index.php?group=' . $filterGroup . '&status=' . $filterStatus);
}

// ─── SUBSCRIBER MANAGEMENT ─────────────────────────
if (isset($_POST['import_csv'])) {
    if (!verifyCsrf()) { redirect('index.php'); }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $group_id = $_POST['import_group'] ?? null;
        if ($group_id === '') $group_id = null;

        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $imported = 0;
        $errors = 0;
        
        fgetcsv($file);
        
        while (($row = fgetcsv($file)) !== false) {
            $email = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors++;
                continue;
            }
            
            try {
                $stmt = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                $stmt->execute([$email]);
                
                if (!$stmt->fetch()) {
                    $token = generateToken();
                    $stmt = $db->prepare("INSERT INTO subscribers (email, name, group_id, token_unsubscribe) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$email, $name ?: null, $group_id, $token]);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        fclose($file);
        setFlash('success', "Importados: $imported. Errores: $errors");
        redirect('index.php?page=' . $page . '&group=' . $filterGroup . '&status=' . $filterStatus);
    }
}

if (isset($_POST['add_subscriber'])) {
    if (!verifyCsrf()) { redirect('index.php'); }

    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $group_id = $_POST['group_id'] ?? null;
    if ($group_id === '') $group_id = null;
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Correo invalido');
        redirect('index.php?page=' . $page . '&group=' . $filterGroup . '&status=' . $filterStatus);
    }
    
    try {
        $token = generateToken();
        $stmt = $db->prepare("INSERT INTO subscribers (email, name, group_id, token_unsubscribe) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $name ?: null, $group_id, $token]);
        setFlash('success', 'Suscriptor agregado');
    } catch (Exception $e) {
        setFlash('error', 'El correo ya existe');
    }
    redirect('index.php?page=' . $page . '&group=' . $filterGroup . '&status=' . $filterStatus);
}

if (isset($_POST['bulk_set_group'])) {
    if (!verifyCsrf()) { redirect('index.php'); }
    $ids = $_POST['selected'] ?? [];
    $group_id = $_POST['bulk_group_id'] ?? null;
    if ($group_id === '') $group_id = null;
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $params[] = $group_id;
        $db->prepare("UPDATE subscribers SET group_id = ? WHERE id IN ($placeholders)")->execute($params);
        setFlash('success', 'Grupo asignado a ' . count($ids) . ' suscriptores');
    }
    redirect('index.php?page=' . $page . '&group=' . $filterGroup . '&status=' . $filterStatus);
}

if (isset($_POST['delete_subscribers'])) {
    if (!verifyCsrf()) { redirect('index.php'); }
    $ids = $_POST['selected'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM subscribers WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        setFlash('success', 'Suscriptores eliminados');
    }
    redirect('index.php?page=' . $page . '&group=' . $filterGroup . '&status=' . $filterStatus);
}

if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $stmt = $db->prepare("UPDATE subscribers SET status = IF(status = 'active', 'unsubscribed', 'active') WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Estado actualizado');
    redirect('index.php?page=' . $page . '&group=' . $filterGroup . '&status=' . $filterStatus);
}

// ─── QUERY BUILDING ─────────────────────────────────
$where = [];
$params = [];

if ($filterGroup !== 'all') {
    if ($filterGroup === 'none') {
        $where[] = "s.group_id IS NULL";
    } else {
        $where[] = "s.group_id = ?";
        $params[] = $filterGroup;
    }
}

if ($filterStatus !== 'all') {
    $where[] = "s.status = ?";
    $params[] = $filterStatus;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $db->prepare("SELECT COUNT(*) FROM subscribers s $whereClause");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;

$stmt = $db->prepare("SELECT s.*, g.name as group_name, g.color as group_color FROM subscribers s LEFT JOIN subscriber_groups g ON s.group_id = g.id $whereClause ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$subscribers = $stmt->fetchAll();

$groups = $db->query("SELECT * FROM subscriber_groups ORDER BY name ASC")->fetchAll();

// ─── STATS ──────────────────────────────────────────
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
        SUM(CASE WHEN group_id IS NULL THEN 1 ELSE 0 END) as sin_grupo
    FROM subscribers
";
if (!empty($where)) {
    $statsStmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed, SUM(CASE WHEN group_id IS NULL THEN 1 ELSE 0 END) as sin_grupo FROM subscribers s $whereClause");
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();
} else {
    $stats = $db->query($statsQuery)->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscriptores - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .group-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.8rem; color: #fff; }
        .group-card-item { transition: background 0.15s; }
        .group-card-item:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <h2><i class="bi bi-people"></i> Suscriptores</h2>

        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <div class="card text-center border-primary">
                    <div class="card-body py-2">
                        <div class="fw-bold text-primary"><?= $stats['total'] ?></div>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-success">
                    <div class="card-body py-2">
                        <div class="fw-bold text-success"><?= $stats['active'] ?></div>
                        <small class="text-muted">Activos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-secondary">
                    <div class="card-body py-2">
                        <div class="fw-bold text-secondary"><?= $stats['unsubscribed'] ?></div>
                        <small class="text-muted">Desuscritos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-warning">
                    <div class="card-body py-2">
                        <div class="fw-bold text-warning"><?= $stats['sin_grupo'] ?></div>
                        <small class="text-muted">Sin grupo</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?php foreach ($groups as $g): ?>
                                <a href="?group=<?= $g['id'] ?>&status=<?= $filterStatus ?>" class="group-badge text-decoration-none" style="background:<?= htmlspecialchars($g['color']) ?>"><?= htmlspecialchars($g['name']) ?></a>
                            <?php endforeach; ?>
                            <a href="?group=none&status=<?= $filterStatus ?>" class="group-badge text-decoration-none bg-secondary">Sin grupo</a>
                            <a href="?group=all&status=<?= $filterStatus ?>" class="group-badge text-decoration-none bg-dark">Todos</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT: Groups + Add Subscriber -->
            <div class="col-md-4">
                <!-- Groups Management -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-folder2"></i> Grupos</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                            <i class="bi bi-plus-circle"></i> Nuevo
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($groups)): ?>
                            <p class="text-muted text-center p-3 mb-0">No hay grupos. Crea uno para organizar tus suscriptores.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($groups as $g): ?>
                                    <div class="list-group-item group-card-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="group-badge me-2" style="background:<?= htmlspecialchars($g['color']) ?>"><?= htmlspecialchars($g['name']) ?></span>
                                            <?php
                                            $countStmt = $db->prepare("SELECT COUNT(*) FROM subscribers WHERE group_id = ?");
                                            $countStmt->execute([$g['id']]);
                                            $gc = $countStmt->fetchColumn();
                                            ?>
                                            <small class="text-muted">(<?= $gc ?>)</small>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-secondary" onclick='editGroup(<?= $g['id'] ?>, <?= json_encode($g['name']) ?>, <?= json_encode($g['color']) ?>)' title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($gc == 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar grupo?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                                <button type="submit" name="delete_group" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add Subscriber -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Agregar Suscriptor</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Correo *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Grupo</label>
                                <select name="group_id" class="form-select">
                                    <option value="">Sin grupo</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="add_subscriber" class="btn btn-primary w-100">
                                <i class="bi bi-plus"></i> Agregar
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Import CSV -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Importar CSV</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <p class="small text-muted">Formato: email, nombre (opcional)</p>
                            <input type="file" name="csv_file" class="form-control mb-3" accept=".csv" required>
                            <div class="mb-3">
                                <label class="form-label">Asignar a grupo</label>
                                <select name="import_group" class="form-select">
                                    <option value="">Sin grupo</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="import_csv" class="btn btn-success w-100">
                                <i class="bi bi-upload"></i> Importar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Subscribers Table -->
            <div class="col-md-8">
                <!-- Filters -->
                <div class="d-flex gap-2 mb-3">
                    <div class="btn-group">
                        <a href="?group=<?= $filterGroup ?>&status=all" class="btn btn-sm <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
                        <a href="?group=<?= $filterGroup ?>&status=active" class="btn btn-sm <?= $filterStatus === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">Activos</a>
                        <a href="?group=<?= $filterGroup ?>&status=unsubscribed" class="btn btn-sm <?= $filterStatus === 'unsubscribed' ? 'btn-primary' : 'btn-outline-primary' ?>">Desuscritos</a>
                    </div>
                    <?php if ($filterGroup !== 'all' || $filterStatus !== 'all'): ?>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">Limpiar filtros</a>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($subscribers)): ?>
                            <p class="text-muted text-center">No hay suscriptores con los filtros actuales.</p>
                        <?php else: ?>
                            <form method="POST" id="bulkForm">
                                <?= csrfField() ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll"></th>
                                                <th>Email</th>
                                                <th>Nombre</th>
                                                <th>Grupo</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subscribers as $s): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="selected[]" value="<?= $s['id'] ?>"></td>
                                                    <td><?= htmlspecialchars($s['email']) ?></td>
                                                    <td><?= htmlspecialchars($s['name'] ?? '-') ?></td>
                                                    <td>
                                                        <?php if ($s['group_name']): ?>
                                                            <span class="group-badge" style="background:<?= htmlspecialchars($s['group_color']) ?>"><?= htmlspecialchars($s['group_name']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= $s['status'] === 'active' ? 'Activo' : 'Desuscrito' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= formatDate($s['created_at']) ?></td>
                                                    <td>
                                                        <a href="?toggle_status=<?= $s['id'] ?>&page=<?= $page ?>&group=<?= $filterGroup ?>&status=<?= $filterStatus ?>" class="btn btn-sm btn-outline-<?= $s['status'] === 'active' ? 'warning' : 'success' ?>">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Bulk Actions -->
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <button type="submit" name="delete_subscribers" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar seleccionados?')">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                    <div class="input-group input-group-sm" style="max-width: 280px;">
                                        <select name="bulk_group_id" class="form-select">
                                            <option value="">Asignar a grupo...</option>
                                            <option value="">Sin grupo</option>
                                            <?php foreach ($groups as $g): ?>
                                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="bulk_set_group" class="btn btn-outline-secondary">Aplicar</button>
                                    </div>
                                </div>
                            </form>
                            <?php if ($totalPages > 1): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&group=<?= $filterGroup ?>&status=<?= $filterStatus ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Group Modal -->
    <div class="modal fade" id="addGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Grupo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="group_name" class="form-control" placeholder="Ej: Externos, Internos, Prueba..." required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Color</label>
                        <input type="color" name="group_color" class="form-control form-control-color" value="#6c757d" style="height: 38px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="add_group" class="btn btn-primary">Crear Grupo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div class="modal fade" id="editGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="group_id" id="editGroupId">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Grupo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="group_name" id="editGroupName" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Color</label>
                        <input type="color" name="group_color" id="editGroupColor" class="form-control form-control-color" style="height: 38px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="edit_group" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = this.checked);
        });

        function editGroup(id, name, color) {
            document.getElementById('editGroupId').value = id;
            document.getElementById('editGroupName').value = name;
            document.getElementById('editGroupColor').value = color;
            new bootstrap.Modal(document.getElementById('editGroupModal')).show();
        }
    </script>
</body>
</html>
