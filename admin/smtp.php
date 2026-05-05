<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

requireRole(['admin']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { redirect('smtp.php'); }

    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = (int)($_POST['smtp_port'] ?? 587);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
    $from_email = trim($_POST['from_email'] ?? '');
    $from_name = trim($_POST['from_name'] ?? '');

    $stmt = $db->prepare("SELECT smtp_password FROM smtp_config WHERE id = 1");
    $stmt->execute();
    $existing = $stmt->fetch();
    $password_value = !empty($smtp_password) ? encryptSmtpPassword($smtp_password) : $existing['smtp_password'];
    
    $stmt = $db->prepare("UPDATE smtp_config SET 
        smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, 
        smtp_encryption = ?, from_email = ?, from_name = ?
        WHERE id = 1");
    $stmt->execute([$smtp_host, $smtp_port, $smtp_username, $password_value, $smtp_encryption, $from_email, $from_name]);
    
    setFlash('success', 'Configuración SMTP guardada');
    redirect('smtp.php');
}

$stmt = $db->query("SELECT * FROM smtp_config WHERE id = 1");
$config = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración SMTP - Sistema de Mailing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Configuración SMTP</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($flash = getFlash()): ?>
                            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                                <?= $flash['message'] ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Servidor SMTP</label>
                                    <input type="text" name="smtp_host" class="form-control" 
                                           value="<?= htmlspecialchars($config['smtp_host']) ?>" 
                                           placeholder="smtp.hostgator.com" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Puerto</label>
                                    <input type="number" name="smtp_port" class="form-control" 
                                           value="<?= $config['smtp_port'] ?: 587 ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Encriptación</label>
                                    <select name="smtp_encryption" class="form-select">
                                        <option value="tls" <?= ($config['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= ($config['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="none" <?= ($config['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>Ninguna</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Usuario SMTP</label>
                                    <input type="text" name="smtp_username" class="form-control" 
                                           value="<?= htmlspecialchars($config['smtp_username']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contraseña SMTP</label>
                                    <input type="password" name="smtp_password" class="form-control" 
                                           value="">
                                    <small class="text-muted">Dejar en blanco para no cambiar</small>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Correo Remitente</label>
                                    <input type="email" name="from_email" class="form-control" 
                                           value="<?= htmlspecialchars($config['from_email']) ?>" 
                                           placeholder="noreply@tudominio.com" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre Remitente</label>
                                    <input type="text" name="from_name" class="form-control" 
                                           value="<?= htmlspecialchars($config['from_name']) ?>" 
                                           placeholder="Mi Empresa" required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Guardar Configuración
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
