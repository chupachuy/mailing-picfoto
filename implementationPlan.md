# Plan de Proyecto: Sistema de Mailing

Este documento detalla la arquitectura, tecnologías y el funcionamiento del sistema de envíos de correo masivo solicitado, cumpliendo con los requisitos de roles, colas y desuscripciones.

## User Review Required

> [!WARNING]
> **Sobre el Sistema de Colas:** Al ser un proyecto con PHP clásico, la forma correcta de implementar una cola en lugar de un "bucle simple" (que haría que el servidor colapse por "Timeout") es apoyarse en una tabla de base de datos (`email_queue`) y configurar una "Tarea Programada" en tu servidor (**CRON Job**) que tome y envíe lotes de correos de forma automática en segundo plano.

> [!IMPORTANT]
> **Elección de Librería de Correo:** Mencionas PHPMailer. Es una excelente opción y muy probada. Sin embargo, su alternativa moderna oficial es **Symfony Mailer**. Para este plan, seguiremos con **PHPMailer** por su facilidad e integración en XAMPP, a menos que prefieras que usemos otra.

## 1. Arquitectura y Stack Tecnológico Propuesto

*   **Backend:** PHP Nativo (PHP puro sin frameworks grandes).
*   **Base de Datos:** MySQL.
*   **Frontend (UI):** HTML5, Bootstrap 5 y Vanilla JS.
*   **Editor de Correos:** Plantilla base en HTML/CSS pre-diseñada. El editor sólo llenará en un formulario los campos dinámicos (Título principal, Imagen del banner, Texto promocional, y Enlace).
*   **Envíos SMTP:** PHPMailer usando el servicio SMTP de HostGator.

## 2. Roles de Usuario

*   **Admin:** Acceso total. Puede gestionar usuarios (crear editores), configurar las credenciales del servidor SMTP, importar base de datos de clientes, ver métricas generales y ejecutar campañas.
*   **Editor:** Rol enfocado en contenido. Puede diseñar plantillas de correos, gestionar las campañas y lanzar las campañas de envío, pero no puede modificar las configuraciones del sistema SMTP ni agregar otros usuarios.
*   **Diseño de Mail:** En el panel de creación de campañas, los usuarios verán un formulario simple para inyectar datos (Título, Imagen, etc.) sobre una plantilla HTML dura ya estructurada.
*   **Monitor/Visor de Actividad:** El panel de administración tendrá un "Dashboard" para monitorear el estado exacto de la cola (correos Totales, Pendientes, Enviados o Fallidos), lo cual es ideal para listas de ~500 contactos para ver en tiempo real qué correos salieron y evitar spam.

## 3. Modelo de Base de Datos (Core)

1.  `users`: Gestiona los accesos (id, username, password_hash, rol).
2.  `subscribers`: Clientes (id, email, name, status [active/unsubscribed], token_unsubscribe).
3.  `campaigns`: Correos a enviar (id, name, subject, html_body, id_editor, status [draft/queued/completed]).
4.  `email_queue`: **La Cola**. (id, id_campaign, id_subscriber, status [pending/sent/failed], date_queued, date_processed).

## 4. El Flujo de Trabajo (Workflows)

### A. Diseño y Envío de Prueba
El `Editor` crea su campaña llenando un formulario con los campos dinámicos. Antes de encolar masivamente, cuenta con un botón **"Enviar Prueba"**. 
*El sistema toma la plantilla rellenada y manda la prueba inmediata mediante PHPMailer al buzón del usuario.*

### B. El Sistema de Colas (Evitando el Bucle Simple)
Cuando el usuario presiona "Enviar Campaña Masiva":
1.  **NO se manda ningún correo** en ese instante.
2.  El sistema buscará a todos los `subscribers` con status 'active'.
3.  Poblará la tabla `email_queue` con un registro por cada suscriptor. (Esto toma un segundo). El usuario ve una pantalla de éxito inmediatamente.
4.  **El Cron Job (Worker):** Un script especial (ej: `cron_worker.php`) se mandará a llamar por el SO cada 1 o 5 minutos. Este script pregunta a MySQL: *"Dame 50 correos pendientes"*. Envía esos 50, los marca como enviados y se apaga. Esto cuida la memoria RAM y respeta los límites de tu servidor de correos, evitando bloqueos por Spam.

### C. Sistema de Baja (Unsubscribe)
1.  Todo suscriptor al insertarse en la DB recibe un hash único (`token_unsubscribe`).
2.  El maquetador de correos o el sistema inyectará al final del HTML de todas las campañas un footer: *"Si no deseas recibir más correos, [haz clic aquí]"*.
3.  Ese enlace apuntará a la ruta: `tusistema.com/unsubscribe.php?token=XXX`.
4.  Cuando el cliente da clic, el script marca su status en la tabla `subscribers` como `unsubscribed`. Al generar futuras colas, el sistema lo ignorará matemáticamente en el query.

---

## Open Questions (Preguntas para ti antes de programar)

*Todas las preguntas técnicas fueron resueltas y el plan fue aprobado para proceder con la ejecución en PHP puro, Bootstrap y HostGator.*
