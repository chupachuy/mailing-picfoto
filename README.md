# Sistema de Mailing - Guía de Instalación

## Requisitos
- PHP 7.4+
- MySQL 5.7+
- Servidor web (Apache/Nginx/XAMPP)

## Instalación

### 1. Configurar Base de Datos

Edita `config/config.php` con tus credenciales MySQL:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mailing_system');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

Crea la base de datos y ejecuta el setup:
```bash
# Desde la terminal
php setup.php
```

### 2. Credenciales Iniciales

Después de ejecutar `php setup.php`, se creará un usuario admin.
Las credenciales se mostrarán en la terminal después del setup.

### 3. Configurar SMTP

1. Accede al panel como admin
2. Ve a **Configuración > SMTP**
3. Ingresa los datos de HostGator:
   - Servidor SMTP
   - Puerto (587 para TLS, 465 para SSL)
   - Usuario y contraseña
   - Correo y nombre remitente

### 4. Configurar CRON (Envío Automático)

El CRON procesa la cola de correos cada 5 minutos:

**En HostGator (cPanel):**
1. Ve a **Cron Jobs** en cPanel
2. Añade un nuevo cron:
   ```
   */5 * * * * /usr/bin/php /home/usuario/public_html/picfotomailing/cron/cron_worker.php
   ```

**Alternativa local (XAMPP en Windows):**
Usa el Programador de Tareas de Windows para ejecutar:
```
php C:\xampp\htdocs\picfotomailing\cron\cron_worker.php
```

## Estructura del Proyecto

```
picfotomailing/
├── config/
│   └── config.php          # Configuración general
├── includes/
│   ├── Database.php        # Conexión a BD
│   ├── Mailer.php          # Envío de correos
│   └── functions.php       # Funciones auxiliares
├── admin/
│   ├── smtp.php            # Configuración SMTP
│   └── users.php           # Gestión de usuarios
├── campaigns/
│   ├── index.php           # Lista de campañas
│   ├── create.php          # Crear campaña
│   └── view.php            # Ver/enviar campaña
├── subscribers/
│   └── index.php           # Gestión de suscriptores
├── cron/
│   └── cron_worker.php     # Worker de envíos
├── public/
│   └── unsubscribe.php     # Página de baja
├── database/
│   └── schema.sql          # Estructura de BD
├── login.php              # Login
├── dashboard.php           # Panel principal
└── setup.php              # Setup inicial
```

## Roles de Usuario

- **Admin:** Acceso total (SMTP, usuarios, campañas)
- **Editor:** Solo campañas y suscriptores

## Flujo de Trabajo

1. **Crear campaña** → Campaigns > Nueva
2. **Enviar prueba** → Ver campaña > Enviar Prueba
3. **Lanzar** → Encolar campaña (se envía por CRON)
4. **Monitorear** → Dashboard para ver progreso

## Notas Importantes

- Cambia la contraseña del admin inmediatamente
- No uses la contraseña por defecto en producción
- El CRON debe estar configurado para enviar correos
- Los suscriptores dados de baja no reciben correos
