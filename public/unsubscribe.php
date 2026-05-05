<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Token no proporcionado');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM subscribers WHERE token_unsubscribe = ? AND status = 'active'");
$stmt->execute([$token]);
$subscriber = $stmt->fetch();

if (!$subscriber) {
    die('Enlace inválido o ya te has desuscrito');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        die('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }

    $stmt = $db->prepare("UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?");
    $stmt->execute([$subscriber['id']]);
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container text-center py-5">
            <div class="card d-inline-block p-4">
                <h2 class="text-success"><i class="bi bi-check-circle"></i></h2>
                <h4>¡Desuscripción exitosa!</h4>
                <p>Ya no recibirás correos de nosotros.</p>
                <a href="' . BASE_URL . '" class="btn btn-primary">Volver al inicio</a>
            </div>
            ' . socialFooterWeb() . '
        </div>
    </body>
    </html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Darse de Baja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container text-center py-5">
        <div class="card d-inline-block p-4" style="max-width: 400px;">
            <h2 class="text-warning"><i class="bi bi-envelope-x"></i></h2>
            <h4>¿Darte de baja?</h4>
            <p>El correo <strong><?= htmlspecialchars($subscriber['email']) ?></strong> será removido de nuestra lista de suscriptores.</p>
            <form method="POST">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger w-100">Confirmar baja</button>
            </form>
            <a href="<?= BASE_URL ?>" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a>
            <?= socialFooterWeb() ?>
        </div>
    </div>
</body>
</html>
