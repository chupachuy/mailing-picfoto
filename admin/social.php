<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { redirect('social.php'); }

    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $names = $_POST['social_name'] ?? [];
        $urls = $_POST['social_url'] ?? [];
        $icons = $_POST['social_icon'] ?? [];
        $colors = $_POST['social_color'] ?? [];
        $active = $_POST['is_active'] ?? [];

        $existingIds = [];
        $stmt = $db->query("SELECT id FROM social_config");
        while ($row = $stmt->fetch()) { $existingIds[] = $row['id']; }

        $db->beginTransaction();
        try {
            $submittedIds = [];
            foreach ($names as $i => $name) {
                $name = trim($name);
                $url = trim($urls[$i] ?? '');
                $icon = trim($icons[$i] ?? '');
                $color = trim($colors[$i] ?? '#000000');
                $isActive = isset($active[$i]) ? 1 : 0;
                $sortOrder = $i + 1;
                $id = $_POST['social_id'][$i] ?? null;

                if (empty($name)) continue;

                if (!empty($id) && is_numeric($id)) {
                    $stmt = $db->prepare("UPDATE social_config SET social_name=?, social_url=?, social_icon=?, social_color=?, sort_order=?, is_active=? WHERE id=?");
                    $stmt->execute([$name, $url, $icon, $color, $sortOrder, $isActive, $id]);
                    $submittedIds[] = (int)$id;
                } else {
                    $stmt = $db->prepare("INSERT INTO social_config (social_name, social_url, social_icon, social_color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $url, $icon, $color, $sortOrder, $isActive]);
                    $submittedIds[] = (int)$db->lastInsertId();
                }
            }

            $toDelete = array_diff($existingIds, $submittedIds);
            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $stmt = $db->prepare("DELETE FROM social_config WHERE id IN ($placeholders)");
                $stmt->execute(array_values($toDelete));
            }

            $db->commit();
            setFlash('success', 'Redes sociales guardadas correctamente');
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Error al guardar: ' . $e->getMessage());
        }
        redirect('social.php');
    }
}

$stmt = $db->query("SELECT * FROM social_config ORDER BY sort_order ASC");
$socials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Redes Sociales - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .social-row { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; position: relative; }
        .social-preview { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-share"></i> Configuración de Redes Sociales</h5>
                        <button type="button" class="btn btn-sm btn-success" id="addSocialBtn">
                            <i class="bi bi-plus-circle"></i> Agregar Red
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if ($flash = getFlash()): ?>
                            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                                <?= $flash['message'] ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="socialForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save">
                            <div id="socialContainer">
                                <?php foreach ($socials as $index => $s): ?>
                                <div class="social-row">
                                    <input type="hidden" name="social_id[]" value="<?= $s['id'] ?>">
                                    <div class="row align-items-end">
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label small">Nombre</label>
                                            <input type="text" name="social_name[]" class="form-control" value="<?= htmlspecialchars($s['social_name']) ?>" placeholder="Facebook">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label small">URL</label>
                                            <input type="url" name="social_url[]" class="form-control social-url" value="<?= htmlspecialchars($s['social_url']) ?>" placeholder="https://facebook.com/...">
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <label class="form-label small">Ícono</label>
                                            <input type="text" name="social_icon[]" class="form-control" value="<?= htmlspecialchars($s['social_icon']) ?>" maxlength="5">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label small">Color</label>
                                            <div class="input-group">
                                                <input type="color" name="social_color[]" class="form-control form-control-color" value="<?= htmlspecialchars($s['social_color']) ?>" title="Color">
                                                <input type="text" class="form-control color-hex" value="<?= htmlspecialchars($s['social_color']) ?>" maxlength="7" placeholder="#1877F2">
                                            </div>
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <label class="form-label small">Activo</label>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input" type="checkbox" name="is_active[<?= $index ?>]" <?= $s['is_active'] ? 'checked' : '' ?>>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mb-2 text-end">
                                            <label class="form-label small d-block">Acciones</label>
                                            <div class="social-preview" style="background:<?= htmlspecialchars($s['social_color']) ?>" title="Previsualización">
                                                <?= htmlspecialchars($s['social_icon']) ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-social" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Guardar Redes Sociales
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template id="socialTemplate">
        <div class="social-row">
            <input type="hidden" name="social_id[]" value="">
            <div class="row align-items-end">
                <div class="col-md-2 mb-2">
                    <label class="form-label small">Nombre</label>
                    <input type="text" name="social_name[]" class="form-control" placeholder="Facebook">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label small">URL</label>
                    <input type="url" name="social_url[]" class="form-control social-url" placeholder="https://facebook.com/...">
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label small">Ícono</label>
                    <input type="text" name="social_icon[]" class="form-control" value="" maxlength="5" placeholder="f">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label small">Color</label>
                    <div class="input-group">
                        <input type="color" name="social_color[]" class="form-control form-control-color" value="#1877F2" title="Color">
                        <input type="text" class="form-control color-hex" value="#1877F2" maxlength="7" placeholder="#1877F2">
                    </div>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label small">Activo</label>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" name="is_active[]" checked>
                    </div>
                </div>
                <div class="col-md-2 mb-2 text-end">
                    <label class="form-label small d-block">Acciones</label>
                    <div class="social-preview" style="background:#1877F2">
                        f
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-social" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('socialContainer');
        const template = document.getElementById('socialTemplate');
        const addBtn = document.getElementById('addSocialBtn');

        function bindRowEvents(row) {
            const colorInput = row.querySelector('input[type="color"]');
            const hexInput = row.querySelector('.color-hex');
            const preview = row.querySelector('.social-preview');
            const iconInput = row.querySelector('input[name="social_icon[]"]');

            colorInput.addEventListener('input', function() {
                hexInput.value = this.value;
                if (preview) preview.style.background = this.value;
            });

            hexInput.addEventListener('input', function() {
                let val = this.value;
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                    colorInput.value = val;
                    if (preview) preview.style.background = val;
                }
            });

            if (iconInput && preview) {
                iconInput.addEventListener('input', function() {
                    preview.textContent = this.value || '?';
                });
            }

            row.querySelector('.remove-social').addEventListener('click', function() {
                row.remove();
            });
        }

        document.querySelectorAll('.social-row').forEach(bindRowEvents);

        addBtn.addEventListener('click', function() {
            const clone = template.content.cloneNode(true);
            const newRow = clone.querySelector('.social-row');
            container.appendChild(newRow);
            bindRowEvents(newRow);
        });
    });
    </script>
</body>
</html>
