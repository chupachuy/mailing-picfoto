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

// CSRF Protection
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        setFlash('error', 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
        return false;
    }
    return true;
}

// SMTP Password Encryption
function encryptSmtpPassword($plaintext) {
    if (empty($plaintext) || empty(ENCRYPTION_KEY)) return $plaintext;
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptSmtpPassword($ciphertext) {
    if (empty($ciphertext) || empty(ENCRYPTION_KEY)) return $ciphertext;
    $data = base64_decode($ciphertext);
    if (strlen($data) < 17) return $ciphertext;
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $result = openssl_decrypt($encrypted, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return $result !== false ? $result : $ciphertext;
}

// Obtener configuración de redes sociales de la BD o fallback a constantes
function getSocialConfig() {
    try {
        $db = getDB();
        if ($db) {
            $stmt = $db->query("SELECT social_name, social_url, social_icon, social_color FROM social_config WHERE is_active = 1 AND social_url != '' ORDER BY sort_order ASC");
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return array_map(function($r) {
                    return [
                        'name'  => $r['social_name'],
                        'url'   => $r['social_url'],
                        'icon'  => $r['social_icon'],
                        'color' => $r['social_color']
                    ];
                }, $rows);
            }
        }
    } catch (Exception $e) {}

    $socials = [];
    if (!empty(SOCIAL_FACEBOOK) && SOCIAL_FACEBOOK !== '#')    $socials[] = ['name' => 'Facebook',  'url' => SOCIAL_FACEBOOK,  'icon' => 'f',  'color' => '#1877F2'];
    if (!empty(SOCIAL_TIKTOK) && SOCIAL_TIKTOK !== '#')        $socials[] = ['name' => 'TikTok',    'url' => SOCIAL_TIKTOK,    'icon' => "\u{266B}", 'color' => '#000000'];
    if (!empty(SOCIAL_INSTAGRAM) && SOCIAL_INSTAGRAM !== '#')  $socials[] = ['name' => 'Instagram', 'url' => SOCIAL_INSTAGRAM, 'icon' => "\u{2605}", 'color' => '#E4405F'];
    if (!empty(SOCIAL_YOUTUBE) && SOCIAL_YOUTUBE !== '#')      $socials[] = ['name' => 'YouTube',   'url' => SOCIAL_YOUTUBE,   'icon' => "\u{25B6}", 'color' => '#FF0000'];
    return $socials;
}

// Footer de Redes Sociales para Email (inline styles)
function socialFooterEmail() {
    $socials = getSocialConfig();
    if (empty($socials)) return '';

    $html = '<div style="background:#f0f2f5;padding:20px;text-align:center;border-top:1px solid #e0e0e0;margin-top:20px;">
        <p style="margin:0 0 15px;font-size:14px;color:#444;font-weight:bold;">Siguenos en redes sociales</p>
        <table align="center" cellpadding="0" cellspacing="0" style="margin:0 auto;">';
    
    $i = 0;
    foreach ($socials as $s) {
        $spacer = $i > 0 ? '<td width="12"></td>' : '';
        $html .= "$spacer<td align=\"center\" valign=\"middle\">
            <a href=\"{$s['url']}\" target=\"_blank\" style=\"display:inline-block;width:40px;height:40px;border-radius:50%;background:{$s['color']};color:#fff;text-align:center;line-height:40px;text-decoration:none;font-size:18px;font-weight:bold;font-family:Arial,sans-serif;\">
                {$s['icon']}
            </a>
            <br><span style=\"font-size:10px;color:#888;\">{$s['name']}</span>
        </td>";
        $i++;
    }
    
    $html .= '</table></div>';
    return $html;
}

// Footer de Redes Sociales para Web (Bootstrap)
function socialFooterWeb() {
    $socials = getSocialConfig();
    if (empty($socials)) return '';

    $html = '<div class="text-center mt-4 pt-3 border-top">
        <p class="text-muted small fw-bold mb-2">Siguenos en redes sociales</p>';
    foreach ($socials as $s) {
        $html .= "<a href=\"{$s['url']}\" target=\"_blank\" rel=\"noopener\" class=\"btn btn-sm mx-1 rounded-circle\" style=\"background:{$s['color']};color:#fff;width:38px;height:38px;line-height:24px;\" title=\"{$s['name']}\">
            {$s['icon']}
        </a>";
    }
    $html .= '</div>';
    return $html;
}

// Generar HTML de plantilla de correo
function generateEmailTemplate($data) {
    $socialFooter = '';
    if (!isset($data['social_enabled']) || $data['social_enabled']) {
        $socialFooter = socialFooterEmail();
    }

    $accentColor = !empty($data['accent_color']) ? $data['accent_color'] : '#007bff';
    $bgColor = !empty($data['bg_color']) ? $data['bg_color'] : '#f4f4f4';
    $logoUrl = !empty($data['logo_url']) ? $data['logo_url'] : 'https://picfoto.mx/wp-content/uploads/2023/11/Logo-Pic-foto-_Final.png';
    $title = $data['title'] ?? 'Titulo';
    $subtitle = $data['subtitle'] ?? '';
    $bannerUrl = $data['banner_url'] ?? '';
    $promoText = $data['promotional_text'] ?? '';
    $linkUrl = $data['link_url'] ?? '';
    $linkText = $data['link_text'] ?? 'Ver mas';
    $section2Title = $data['section2_title'] ?? '';
    $section2Text = $data['section2_text'] ?? '';
    $secondaryLinkUrl = $data['secondary_link_url'] ?? '';
    $secondaryLinkText = $data['secondary_link_text'] ?? 'Mas informacion';
    $secondaryImageUrl = $data['secondary_image_url'] ?? '';
    $footerText = $data['footer_text'] ?? '';

    $darkerAccent = adjustBrightness($accentColor, -20);

    $headerSection = '
    <div style="background:' . $accentColor . '; padding: 40px 30px 30px; text-align: center;">
        <img src="' . $logoUrl . '" alt="Logo" style="max-width: 180px; height: auto; display: block; margin: 0 auto 20px;">
        <h1 style="margin: 0; font-size: 26px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">' . htmlspecialchars($title) . '</h1>';
    
    if (!empty($subtitle)) {
        $headerSection .= '<p style="margin: 10px 0 0; font-size: 16px; color: rgba(255,255,255,0.85); font-weight: 400;">' . htmlspecialchars($subtitle) . '</p>';
    }
    
    $headerSection .= '</div>';

    $bannerSection = '';
    if (!empty($bannerUrl)) {
        $bannerSection = '<img src="' . $bannerUrl . '" alt="Banner" style="width: 100%; height: auto; display: block; border: 0;">';
    }

    $mainSection = '';
    if (!empty($promoText) || !empty($linkUrl)) {
        $mainSection = '<div style="padding: 30px 30px 10px;">';
        
        if (!empty($promoText)) {
            $mainSection .= '<p style="font-size: 16px; line-height: 1.7; color: #333333; margin: 0 0 20px; font-family: Arial, Helvetica, sans-serif;">' . nl2br($promoText) . '</p>';
        }
        
        if (!empty($linkUrl)) {
            $mainSection .= '<table align="center" cellpadding="0" cellspacing="0" style="margin: 20px auto 10px;">
                <tr>
                    <td align="center" style="background:' . $accentColor . '; border-radius: 30px; box-shadow: 0 4px 12px ' . $darkerAccent . '44;">
                        <a href="' . $linkUrl . '" target="_blank" style="display: inline-block; padding: 14px 40px; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 30px; font-family: Arial, Helvetica, sans-serif; letter-spacing: 0.5px;">
                            ' . htmlspecialchars($linkText) . '
                        </a>
                    </td>
                </tr>
            </table>';
        }
        
        $mainSection .= '</div>';
    }

    $divider = '<div style="height: 1px; background: linear-gradient(to right, transparent, #e0e0e0, transparent); margin: 10px 30px;"></div>';

    $section2 = '';
    $hasSection2 = !empty($section2Title) || !empty($section2Text) || !empty($secondaryImageUrl) || !empty($secondaryLinkUrl);
    
    if ($hasSection2) {
        $section2 = '<div style="padding: 0 30px 10px;">';
        
        if (!empty($secondaryImageUrl)) {
            $section2 .= '<img src="' . $secondaryImageUrl . '" alt="" style="width: 100%; height: auto; display: block; border-radius: 8px; margin-bottom: 20px; border: 0;">';
        }
        
        if (!empty($section2Title)) {
            $section2 .= '<h2 style="font-size: 20px; font-weight: 700; color: #222222; margin: 0 0 12px; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($section2Title) . '</h2>';
        }
        
        if (!empty($section2Text)) {
            $section2 .= '<p style="font-size: 15px; line-height: 1.65; color: #555555; margin: 0 0 20px; font-family: Arial, Helvetica, sans-serif;">' . nl2br($section2Text) . '</p>';
        }
        
        if (!empty($secondaryLinkUrl)) {
            $section2 .= '<table cellpadding="0" cellspacing="0" style="margin: 10px 0 5px;">
                <tr>
                    <td align="center" style="border: 2px solid ' . $accentColor . '; border-radius: 30px;">
                        <a href="' . $secondaryLinkUrl . '" target="_blank" style="display: inline-block; padding: 10px 30px; font-size: 14px; font-weight: 600; color: ' . $accentColor . '; text-decoration: none; border-radius: 30px; font-family: Arial, Helvetica, sans-serif;">
                            ' . htmlspecialchars($secondaryLinkText) . '
                        </a>
                    </td>
                </tr>
            </table>';
        }
        
        $section2 .= '</div>';
    }

    $footerSection = '';
    if (!empty($footerText) || !empty($socialFooter)) {
        $footerSection = '<div style="background: #f0f2f5; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">';
        
        if (!empty($footerText)) {
            $footerSection .= '<p style="margin: 0 0 12px; font-size: 12px; color: #888; line-height: 1.6; font-family: Arial, Helvetica, sans-serif;">' . nl2br($footerText) . '</p>';
        }
        
        $footerSection .= '</div>';
    }

    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; background-color: ' . $bgColor . '; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
            a { color: ' . $accentColor . '; }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: ' . $bgColor . ';">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
            <tr>
                <td style="padding: 0;">
                    ' . $headerSection . '
                    ' . $bannerSection . '
                    ' . $mainSection . '
                    ' . (!empty($hasSection2) && (!empty($promoText) || !empty($linkUrl)) ? $divider : '') . '
                    ' . $section2 . '
                    ' . $footerSection . '
                    ' . $socialFooter . '
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $template;
}

function adjustBrightness($hex, $steps) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $steps));
    $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $steps));
    $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $steps));
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
