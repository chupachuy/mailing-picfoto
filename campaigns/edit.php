<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin', 'editor']);

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('error', 'ID de campana no especificado');
    redirect('index.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->execute([$id]);
$campaign = $stmt->fetch();

if (!$campaign) {
    setFlash('error', 'Campana no encontrada');
    redirect('index.php');
}

if ($campaign['status'] !== 'draft') {
    setFlash('error', 'Solo se pueden editar campanas en estado draft');
    redirect('view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { redirect('index.php'); }

    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $banner_url = trim($_POST['banner_url'] ?? '');
    $promotional_text = trim($_POST['promotional_text'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $link_text = trim($_POST['link_text'] ?? '');
    $section2_title = trim($_POST['section2_title'] ?? '');
    $section2_text = trim($_POST['section2_text'] ?? '');
    $secondary_image_url = trim($_POST['secondary_image_url'] ?? '');
    $secondary_link_url = trim($_POST['secondary_link_url'] ?? '');
    $secondary_link_text = trim($_POST['secondary_link_text'] ?? '');
    $accent_color = trim($_POST['accent_color'] ?? '#007bff');
    $bg_color = trim($_POST['bg_color'] ?? '#f4f4f4');
    $footer_text = trim($_POST['footer_text'] ?? '');
    $social_enabled = isset($_POST['social_enabled']) ? 1 : 0;
    $target_groups = isset($_POST['target_groups']) ? json_encode($_POST['target_groups']) : null;

    if (empty($name) || empty($subject)) {
        setFlash('error', 'Nombre y asunto son obligatorios');
        redirect('edit.php?id=' . $id);
    }

    $html_body = generateEmailTemplate([
        'title' => $title ?: $name,
        'subtitle' => $subtitle,
        'logo_url' => $logo_url,
        'banner_url' => $banner_url,
        'promotional_text' => $promotional_text,
        'link_url' => $link_url,
        'link_text' => $link_text,
        'section2_title' => $section2_title,
        'section2_text' => $section2_text,
        'secondary_image_url' => $secondary_image_url,
        'secondary_link_url' => $secondary_link_url,
        'secondary_link_text' => $secondary_link_text,
        'accent_color' => $accent_color,
        'bg_color' => $bg_color,
        'footer_text' => $footer_text,
        'social_enabled' => $social_enabled
    ]);

    $stmt = $db->prepare("UPDATE campaigns SET name=?, subject=?, title=?, subtitle=?, logo_url=?, banner_url=?, promotional_text=?, link_url=?, link_text=?, section2_title=?, section2_text=?, secondary_image_url=?, secondary_link_url=?, secondary_link_text=?, accent_color=?, bg_color=?, footer_text=?, html_body=?, social_enabled=?, target_groups=? WHERE id=?");
    $stmt->execute([$name, $subject, $title, $subtitle, $logo_url, $banner_url, $promotional_text, $link_url, $link_text, $section2_title, $section2_text, $secondary_image_url, $secondary_link_url, $secondary_link_text, $accent_color, $bg_color, $footer_text, $html_body, $social_enabled, $target_groups, $id]);
    
    setFlash('success', 'Campana actualizada exitosamente');
    redirect('view.php?id=' . $id);
}

$groups = $db->query("SELECT * FROM subscriber_groups ORDER BY name ASC")->fetchAll();
$selectedGroups = !empty($campaign['target_groups']) ? json_decode($campaign['target_groups'], true) : [];
if (!is_array($selectedGroups)) $selectedGroups = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Campaña - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e9ecef; }
        .section-title i { margin-right: 6px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar Campana</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($flash = getFlash()): ?>
                            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                                <?= $flash['message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrfField() ?>

                            <div class="section-title"><i class="bi bi-info-circle"></i> Datos Basicos</div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre de Campana *</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($campaign['name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Asunto del Correo *</label>
                                    <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($campaign['subject']) ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Titulo del Encabezado</label>
                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($campaign['title'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subtitulo</label>
                                    <input type="text" name="subtitle" class="form-control" value="<?= htmlspecialchars($campaign['subtitle'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="section-title"><i class="bi bi-palette"></i> Diseno</div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Color de Acento</label>
                                    <div class="input-group">
                                        <input type="color" name="accent_color" class="form-control form-control-color" value="<?= htmlspecialchars($campaign['accent_color'] ?? '#007bff') ?>">
                                        <input type="text" class="form-control accent-hex" value="<?= htmlspecialchars($campaign['accent_color'] ?? '#007bff') ?>" maxlength="7">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Color de Fondo</label>
                                    <div class="input-group">
                                        <input type="color" name="bg_color" class="form-control form-control-color" value="<?= htmlspecialchars($campaign['bg_color'] ?? '#f4f4f4') ?>">
                                        <input type="text" class="form-control bg-hex" value="<?= htmlspecialchars($campaign['bg_color'] ?? '#f4f4f4') ?>" maxlength="7">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">URL del Logo</label>
                                    <input type="url" name="logo_url" class="form-control" value="<?= htmlspecialchars($campaign['logo_url'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="section-title"><i class="bi bi-image"></i> Contenido Principal</div>
                            <div class="mb-3">
                                <label class="form-label">URL del Banner</label>
                                <input type="url" name="banner_url" class="form-control" value="<?= htmlspecialchars($campaign['banner_url'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Texto Promocional</label>
                                <textarea name="promotional_text" class="form-control" rows="4"><?= htmlspecialchars($campaign['promotional_text'] ?? '') ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">URL del Enlace Principal</label>
                                    <input type="url" name="link_url" class="form-control" value="<?= htmlspecialchars($campaign['link_url'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Texto del Boton</label>
                                    <input type="text" name="link_text" class="form-control" value="<?= htmlspecialchars($campaign['link_text'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="section-title"><i class="bi bi-layers"></i> Contenido Secundario <small class="fw-normal text-muted">(opcional)</small></div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Titulo Seccion 2</label>
                                    <input type="text" name="section2_title" class="form-control" value="<?= htmlspecialchars($campaign['section2_title'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Imagen Secundaria</label>
                                    <input type="url" name="secondary_image_url" class="form-control" value="<?= htmlspecialchars($campaign['secondary_image_url'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Texto Seccion 2</label>
                                <textarea name="section2_text" class="form-control" rows="3"><?= htmlspecialchars($campaign['section2_text'] ?? '') ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">URL del Enlace Secundario</label>
                                    <input type="url" name="secondary_link_url" class="form-control" value="<?= htmlspecialchars($campaign['secondary_link_url'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Texto del Boton 2</label>
                                    <input type="text" name="secondary_link_text" class="form-control" value="<?= htmlspecialchars($campaign['secondary_link_text'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="section-title"><i class="bi bi-envelope"></i> Pie de Pagina</div>
                            <div class="mb-3">
                                <label class="form-label">Texto del Footer</label>
                                <textarea name="footer_text" class="form-control" rows="2"><?= htmlspecialchars($campaign['footer_text'] ?? '') ?></textarea>
                                <small class="text-muted">Informacion de contacto, direccion, telefono, etc.</small>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="social_enabled" id="socialEnabled" <?= !isset($campaign['social_enabled']) || $campaign['social_enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="socialEnabled">
                                    <i class="bi bi-share"></i> Incluir iconos de redes sociales
                                </label>
                            </div>

                            <?php if (!empty($groups)): ?>
                            <div class="section-title"><i class="bi bi-people-fill"></i> Grupos Destino</div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-2">Si no seleccionas ninguno, se enviara a <strong>todos</strong> los suscriptores activos.</small>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($groups as $g): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="target_groups[]" value="<?= $g['id'] ?>" id="tg<?= $g['id'] ?>" <?= in_array($g['id'], $selectedGroups) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="tg<?= $g['id'] ?>">
                                            <span style="background:<?= htmlspecialchars($g['color']) ?>;display:inline-block;padding:3px 12px;border-radius:12px;font-size:0.8rem;color:#fff;">
                                                <?= htmlspecialchars($g['name']) ?>
                                            </span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <hr>
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary px-4">
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
    <script>
    (function() {
        document.querySelectorAll('input[type="color"]').forEach(function(picker) {
            var hex = picker.nextElementSibling;
            picker.addEventListener('input', function() { hex.value = this.value; });
            hex.addEventListener('input', function() {
                var v = this.value;
                if (!v.startsWith('#')) v = '#' + v;
                if (/^#[0-9A-Fa-f]{6}$/.test(v)) picker.value = v;
            });
        });
    })();
    </script>
</body>
</html>
