<?php
/**
 * Funciones Auxiliares
 */

// Verificar autenticación
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isEditor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'editor';
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Generar token único
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Redirección
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Mensajes flash
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Formatear fecha
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Sanitizar
function sanitize($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

// Generar HTML de plantilla de correo
function generateEmailTemplate($data) {
    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . ($data['title'] ?? 'Correo') . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; }
            .header img { max-width: 200px; height: auto; display: block; margin: 0 auto 15px; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px 20px; }
            .banner { width: 100%; height: auto; }
            .promo-text { font-size: 16px; line-height: 1.6; color: #333; margin: 20px 0; }
            .cta-button { display: inline-block; background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://picfoto.mx/wp-content/uploads/2023/11/Logo-Pic-foto-_Final.png" alt="Logo">
                <h1>' . ($data['title'] ?? 'Título') . '</h1>
            </div>
            <div class="content">
                ' . (!empty($data['banner_url']) ? '<img src="' . $data['banner_url'] . '" alt="Banner" class="banner">' : '') . '
                <div class="promo-text">
                    ' . nl2br($data['promotional_text'] ?? '') . '
                </div>
                ' . (!empty($data['link_url']) ? '<a href="' . $data['link_url'] . '" class="cta-button">' . ($data['link_text'] ?? 'Ver más') . '</a>' : '') . '
            </div>
        </div>
    </body>
    </html>';
    
    return $template;
}
