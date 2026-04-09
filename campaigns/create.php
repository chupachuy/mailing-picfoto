<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin', 'editor']);

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
        redirect('create.php');
    }

    $html_body = generateEmailTemplate([
        'title' => $title ?: $name,
        'banner_url' => $banner_url,
        'promotional_text' => $promotional_text,
        'link_url' => $link_url,
        'link_text' => $link_text
    ]);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO campaigns (name, subject, title, banner_url, promotional_text, link_url, link_text, html_body, id_editor, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
    $stmt->execute([$name, $subject, $title, $banner_url, $promotional_text, $link_url, $link_text, $html_body, $_SESSION['user_id']]);
    
    $campaign_id = $db->lastInsertId();
    
    setFlash('success', 'Campaña creada exitosamente');
    redirect('view.php?id=' . $campaign_id);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Campaña - Sistema de Mailing</title>
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
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nueva Campaña</h5>
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
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Asunto del Correo *</label>
                                    <input type="text" name="subject" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Título del Encabezado</label>
                                <input type="text" name="title" class="form-control" placeholder="Título principal del correo">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">URL del Banner</label>
                                <input type="url" name="banner_url" class="form-control" placeholder="https://ejemplo.com/banner.jpg">
                                <small class="text-muted">URL de la imagen del banner</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Texto Promocional</label>
                                <textarea name="promotional_text" class="form-control" rows="4" placeholder="Escribe el contenido de tu correo..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">URL del Enlace</label>
                                    <input type="url" name="link_url" class="form-control" placeholder="https://ejemplo.com/promo">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Texto del Botón</label>
                                    <input type="text" name="link_text" class="form-control" placeholder="Ver más">
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Crear Campaña
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
