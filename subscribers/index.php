<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin', 'editor']);

$db = getDB();

if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
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
                    $stmt = $db->prepare("INSERT INTO subscribers (email, name, token_unsubscribe) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $name ?: null, $token]);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        fclose($file);
        setFlash('success', "Importados: $imported. Errores: $errors");
        redirect('index.php');
    }
}

if (isset($_POST['add_subscriber'])) {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Correo inválido');
        redirect('index.php');
    }
    
    try {
        $token = generateToken();
        $stmt = $db->prepare("INSERT INTO subscribers (email, name, token_unsubscribe) VALUES (?, ?, ?)");
        $stmt->execute([$email, $name ?: null, $token]);
        setFlash('success', 'Suscriptor agregado');
    } catch (Exception $e) {
        setFlash('error', 'El correo ya existe');
    }
    redirect('index.php');
}

if (isset($_POST['delete_subscribers'])) {
    $ids = $_POST['selected'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM subscribers WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        setFlash('success', 'Suscriptores eliminados');
    }
    redirect('index.php');
}

if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $stmt = $db->prepare("UPDATE subscribers SET status = IF(status = 'active', 'unsubscribed', 'active') WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Estado actualizado');
    redirect('index.php');
}

$stmt = $db->query("SELECT * FROM subscribers ORDER BY created_at DESC");
$subscribers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscriptores - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Agregar Suscriptor</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Correo *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="name" class="form-control">
                            </div>
                            <button type="submit" name="add_subscriber" class="btn btn-primary w-100">
                                <i class="bi bi-plus"></i> Agregar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Importar CSV</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <p class="small text-muted">Formato: email, nombre (opcional)</p>
                            <input type="file" name="csv_file" class="form-control mb-3" accept=".csv" required>
                            <button type="submit" name="import_csv" class="btn btn-success w-100">
                                <i class="bi bi-upload"></i> Importar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($subscribers)): ?>
                            <p class="text-muted text-center">No hay suscriptores.</p>
                        <?php else: ?>
                            <form method="POST" id="bulkForm">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll"></th>
                                                <th>Email</th>
                                                <th>Nombre</th>
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
                                                        <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= ucfirst($s['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= formatDate($s['created_at']) ?></td>
                                                    <td>
                                                        <a href="?toggle_status=<?= $s['id'] ?>" class="btn btn-sm btn-outline-<?= $s['status'] === 'active' ? 'warning' : 'success' ?>">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" name="delete_subscribers" class="btn btn-danger" onclick="return confirm('¿Eliminar seleccionados?')">
                                    <i class="bi bi-trash"></i> Eliminar Seleccionados
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>
