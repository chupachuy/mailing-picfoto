<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

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

if ($campaign['status'] !== 'draft') {
    setFlash('error', 'Solo se pueden editar campañas en estado draft');
    redirect('view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $banner_url = trim($_POST['banner_url'] ?? '');
    $promotional_text = trim($_POST['promotional_text'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $link_text = trim($_POST['link_text'] ?? '');

    if (empty($name) || empty($subject)) {
        setFlash('error', 'Nombre y asunto son obligatorios');
        redirect('edit.php?id=' . $id);
    }

    $html_body = generateEmailTemplate([
        'title' => $title ?: $name,
        'banner_url' => $banner_url,
        'promotional_text' => $promotional_text,
        'link_url' => $link_url,
        'link_text' => $link_text
    ]);

    $stmt = $db->prepare("UPDATE campaigns SET name = ?, subject = ?, title = ?, banner_url = ?, promotional_text = ?, link_url = ?, link_text = ?, html_body = ? WHERE id = ?");
    $stmt->execute([$name, $subject, $title, $banner_url, $promotional_text, $link_url, $link_text, $html_body, $id]);
    
    setFlash('success', 'Campaña actualizada exitosamente');
    redirect('view.php?id=' . $id);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Campaña - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar Campaña</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($flash = getFlash()): ?>
                            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                                <?= $flash['message'] ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre de Campaña *</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($campaign['name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Asunto del Correo *</label>
                                    <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($campaign['subject']) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Título del Encabezado</label>
                                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($campaign['title'] ?? '') ?>" placeholder="Título principal del correo">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">URL del Banner</label>
                                <input type="url" name="banner_url" class="form-control" value="<?= htmlspecialchars($campaign['banner_url'] ?? '') ?>" placeholder="https://ejemplo.com/banner.jpg">
                                <small class="text-muted">URL de la imagen del banner</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Texto Promocional</label>
                                <textarea name="promotional_text" class="form-control" rows="4" placeholder="Escribe el contenido de tu correo..."><?= htmlspecialchars($campaign['promotional_text'] ?? '') ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">URL del Enlace</label>
                                    <input type="url" name="link_url" class="form-control" value="<?= htmlspecialchars($campaign['link_url'] ?? '') ?>" placeholder="https://ejemplo.com/promo">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Texto del Botón</label>
                                    <input type="text" name="link_text" class="form-control" value="<?= htmlspecialchars($campaign['link_text'] ?? '') ?>" placeholder="Ver más">
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>