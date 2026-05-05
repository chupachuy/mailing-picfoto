-- ============================================
-- Sistema de Mailing - Estructura de Base de Datos
-- ============================================

CREATE DATABASE IF NOT EXISTS mailing_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE mailing_system;

-- Tabla de Usuarios (Admin y Editor)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Grupos de Suscriptores
CREATE TABLE subscriber_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Suscriptores
CREATE TABLE subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100),
    group_id INT NULL,
    status ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
    token_unsubscribe VARCHAR(64) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES subscriber_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de Campañas
CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    title VARCHAR(200),
    banner_url VARCHAR(500),
    promotional_text TEXT,
    link_url VARCHAR(500),
    link_text VARCHAR(100),
    accent_color VARCHAR(7) DEFAULT '#007bff',
    subtitle VARCHAR(300),
    logo_url VARCHAR(500),
    section2_title VARCHAR(200),
    section2_text TEXT,
    secondary_link_url VARCHAR(500),
    secondary_link_text VARCHAR(100),
    secondary_image_url VARCHAR(500),
    bg_color VARCHAR(7) DEFAULT '#f4f4f4',
    footer_text TEXT,
    social_enabled BOOLEAN DEFAULT TRUE,
    target_groups TEXT NULL,
    html_body LONGTEXT,
    id_editor INT NOT NULL,
    status ENUM('draft', 'queued', 'sending', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (id_editor) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla de Cola de Correos
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_campaign INT NOT NULL,
    id_subscriber INT NOT NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    attempts INT DEFAULT 0,
    date_queued TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_processed TIMESTAMP NULL,
    last_attempt_at TIMESTAMP NULL,
    FOREIGN KEY (id_campaign) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (id_subscriber) REFERENCES subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_queue (id_campaign, id_subscriber)
) ENGINE=InnoDB;

-- Tabla de Configuración de Redes Sociales
CREATE TABLE social_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    social_name VARCHAR(50) NOT NULL,
    social_url VARCHAR(500) NOT NULL DEFAULT '',
    social_icon VARCHAR(10) DEFAULT '',
    social_color VARCHAR(7) DEFAULT '#000000',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- Insertar redes sociales por defecto
INSERT INTO social_config (social_name, social_url, social_icon, social_color, sort_order) VALUES
('Facebook', '', 'f', '#1877F2', 1),
('TikTok', '', '♫', '#000000', 2),
('Instagram', '', '★', '#E4405F', 3),
('YouTube', '', '▶', '#FF0000', 4);

-- Tabla de Configuración SMTP
CREATE TABLE smtp_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') NOT NULL DEFAULT 'tls',
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Logs de Envío
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_campaign INT NOT NULL,
    id_subscriber INT NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_campaign) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (id_subscriber) REFERENCES subscribers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Datos Iniciales
-- ============================================

-- Usuario Admin por defecto (password: admin123)
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Configuración SMTP vacía (se llena desde el panel)
INSERT INTO smtp_config (smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name) VALUES 
('', 587, '', '', 'tls', '', '');

-- ============================================
-- Vistas Útiles
-- ============================================

-- Vista: Estadísticas de Campaña
CREATE VIEW campaign_stats AS
SELECT 
    c.id,
    c.name,
    c.subject,
    c.status,
    c.total_recipients,
    c.sent_count,
    c.failed_count,
    (c.sent_count + c.failed_count) AS processed_count,
    ROUND((c.sent_count / NULLIF(c.total_recipients, 0)) * 100, 2) AS success_rate
FROM campaigns c;

-- Vista: Resumen de Suscriptores
CREATE VIEW subscriber_summary AS
SELECT 
    status,
    COUNT(*) AS total
FROM subscribers
GROUP BY status;

-- ============================================
-- Índices para Optimización
-- ============================================

CREATE INDEX idx_subscribers_status ON subscribers(status);
CREATE INDEX idx_email_queue_status ON email_queue(status);
CREATE INDEX idx_email_queue_campaign ON email_queue(id_campaign);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_email_logs_campaign ON email_logs(id_campaign);
