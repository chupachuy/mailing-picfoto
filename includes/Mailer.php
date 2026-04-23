<?php
/**
 * Clase de Envío de Correos con PHPMailer
 */

require_once BASE_PATH . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $config;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->loadConfig();
    }

    private function loadConfig() {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM smtp_config WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $this->config = $stmt->fetch();
        
        if (empty($this->config)) {
            error_log("Mailer: No hay configuración SMTP activa en la base de datos");
        } else {
            error_log("Mailer: Config cargada - Host: {$this->config['smtp_host']}, User: {$this->config['smtp_username']}, Port: {$this->config['smtp_port']}");
        }
    }

    public function configure() {
        if (empty($this->config)) {
            error_log("Mailer Error: No hay configuración SMTP activa");
            throw new Exception("No hay configuración SMTP activa");
        }

        $this->mail->SMTPDebug = 0;
        $this->mail->isSMTP();
        $this->mail->Host = $this->config['smtp_host'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->config['smtp_username'];
        $this->mail->Password = $this->config['smtp_password'];
        $this->mail->SMTPSecure = $this->config['smtp_encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = (int)$this->config['smtp_port'];
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
        
        // Evitar el error de certificado SSL/TLS (común en Hostgator/cPanel)
        $this->mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $this->mail->Timeout = 15;
        $this->mail->SMTPKeepAlive = false;
        
        error_log("Mailer: Configurado para {$this->config['smtp_host']}:{$this->config['smtp_port']} ({$this->config['smtp_encryption']})");
    }

    public function send($to, $subject, $htmlBody, $name = '') {
        try {
            $this->configure();
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = strip_tags($htmlBody);

            error_log("Mailer: Enviando a $to con SMTP {$this->config['smtp_host']}:{$this->config['smtp_port']}");
            
            $sent = $this->mail->send();
            $this->mail->smtpClose();
            
            if (!$sent) {
                throw new Exception($this->mail->ErrorInfo);
            }
            return ['success' => true];
        } catch (Exception $e) {
            $this->mail->smtpClose();
            error_log("Mailer Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendToSubscriber($subscriber, $campaign) {
        $unsubscribeUrl = BASE_URL . '/public/unsubscribe.php?token=' . $subscriber['token_unsubscribe'];
        
        $htmlBody = $campaign['html_body'];
        $htmlBody = str_replace('</body>', $this->getUnsubscribeFooter($unsubscribeUrl) . '</body>', $htmlBody);
        
        return $this->send(
            $subscriber['email'],
            $campaign['subject'],
            $htmlBody,
            $subscriber['name']
        );
    }

    public function getUnsubscribeFooter($url) {
        return '<div style="background:#f8f9fa;padding:20px;text-align:center;font-size:12px;color:#666;border-top:1px solid #ddd;margin-top:30px;">
            <p style="margin:0 0 10px;">Si no deseas recibir más correos, <a href="' . $url . '" style="color:#007bff;">haz clic aquí para darte de baja</a>.</p>
        </div>';
    }

    public function sendTest($to, $subject, $htmlBody) {
        return $this->send($to, $subject, $htmlBody);
    }
}
