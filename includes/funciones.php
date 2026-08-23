<?php
declare(strict_types=1);


/**
 * FUNCIONES GLOBALES - VERSIÓN SIMPLIFICADA
 * Carga los helpers necesarios
 */

// ============================================
// CARGAR TODOS LOS HELPERS
// ============================================
$helpers_dir = __DIR__ . '/helpers/';
$helpers = [
    'fechas.php',
    'texto.php',
    'validacion.php',
    'seguridad.php',
    'slug.php',
    'flash.php',
    'url.php',
    'login-attempts.php',
    'reportes.php',
    'perfil.php',
    'clasificacion.php',
];

foreach ($helpers as $helper) {
    $file = $helpers_dir . $helper;
    if (file_exists($file)) {
        require_once $file;
    }
}

// ============================================
// FUNCIONES QUE DEPENDEN DE OTROS ARCHIVOS
// ============================================

/**
 * Redirige al usuario según su rol y permisos
 */
function redirigirSegunRol() {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol'])) {
        redireccionar(base_url('public/login'));
        return;
    }
    
    $rol = $_SESSION['usuario_rol'];
    $usuario_id = $_SESSION['usuario_id'];
    
    try {
        $pdo = db();
        
        switch ($rol) {
            case 'admin':
                redireccionar(base_url('admin/dashboard'));
                break;
                
            case 'periodista':
                $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios_privados WHERE id_usuario = ? AND activo = 1");
                $stmt->execute([$usuario_id]);
                if ($stmt->fetch()) {
                    redireccionar(base_url('privado/dashboard'));
                } else {
                    redireccionar(base_url('periodista/dashboard'));
                }
                break;
                
            case 'usuario':
            default:
                redireccionar(base_url('usuario/dashboard'));
                break;
        }
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.REDIRECCION_ROL', $e);
        redireccionar(base_url(''));
    }
}

/**
 * Iniciar sesión si no está iniciada
 */
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Verificar si el usuario está logueado
 */
function estaLogueado() {
    static $usuarioValidado = null;
    static $autenticado = false;

    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }

    $usuarioActual = (int) $_SESSION['usuario_id'];
    if ($usuarioValidado === $usuarioActual) {
        return $autenticado;
    }

    if (!class_exists('Auth', false)) {
        require_once __DIR__ . '/auth.php';
    }

    $autenticado = auth()->getCurrentUser() !== null;
    $usuarioValidado = $autenticado ? $usuarioActual : null;
    return $autenticado;
}

/**
 * Obtener visitante ID (para valoraciones)
 */
function getVisitorId() {
    if (estaLogueado()) {
        return ['tipo' => 'usuario', 'id' => $_SESSION['usuario_id']];
    }
    if (!isset($_SESSION['visitor_id'])) {
        $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
    }
    return ['tipo' => 'visitante', 'id' => $_SESSION['visitor_id']];
}

// ============================================
// FUNCIONES DE RECUPERACIÓN DE CONTRASEÑA
// ============================================

/**
 * Generar token único para recuperación de contraseña
 * @param int $length Longitud del token
 * @return string Token generado
 */
function generarTokenRecuperacion($length = 64) {
    return bin2hex(random_bytes($length));
}

/**
 * Enviar email de recuperación usando SMTP de Brevo
 * @param string $email Email del destinatario
 * @param string $nombre Nombre del usuario
 * @param string $token Token de recuperación
 * @return bool True si se envió correctamente
 */
function enviarEmailRecuperacion($email, $nombre, $token) {
    // Cargar PHPMailer (usando Composer o manual)
    $phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    
    if (!file_exists($phpmailer_path)) {
        error_log('PHPMailer no disponible para recuperación de contraseña');
        return false;
    }
    
    require_once $phpmailer_path;
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    
    $enlace = SITE_URL . '/resetear_password?token=' . $token;
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'quoted-printable';
        
        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $nombre);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = "🔐 Recuperación de contraseña - " . SITE_NAME;
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Recuperación de contraseña</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
                .header h1 { color: #2563eb; margin: 0; }
                .content { padding: 20px 0; }
                .button { display: inline-block; background-color: #2563eb; color: white; text-decoration: none; padding: 12px 24px; border-radius: 4px; margin: 10px 0; }
                .button:hover { background-color: #1d4ed8; }
                .footer { text-align: center; padding-top: 20px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                .warning { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 12px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 ' . SITE_NAME . '</h1>
                    <p>Recuperación de contraseña</p>
                </div>
                <div class="content">
                    <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
                    <p>Hemos recibido una solicitud para restablecer tu contraseña. Si no la solicitaste, ignora este mensaje.</p>
                    <div style="text-align: center;">
                        <a href="' . $enlace . '" class="button">🔑 Restablecer contraseña</a>
                    </div>
                    <div class="warning">
                        ⚠️ Este enlace expirará en <strong>1 hora</strong>.
                    </div>
                    <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                    <p style="word-break: break-all; font-size: 12px; background: #f4f4f4; padding: 8px; border-radius: 4px;">' . $enlace . '</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. Todos los derechos reservados.</p>
                    <p>Este es un mensaje automático, por favor no responder.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hola $nombre,\n\nHemos recibido una solicitud para restablecer tu contraseña.\n\nRestablece tu contraseña en: " . $enlace . "\n\nEste enlace expirará en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.";
        
        $mail->send();
        error_log("Email de recuperación aceptado por SMTP");
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar email de recuperación");
        return false;
    }
}

/**
 * Envía un mensaje del formulario de contacto mediante Brevo SMTP.
 */
function enviarEmailContacto(string $nombre, string $email, string $asunto, string $mensaje): bool {
    $phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    if (!is_file($phpmailerPath)) {
        error_log('PHPMailer no disponible para contacto');
        return false;
    }

    require_once $phpmailerPath;
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = 'Contacto web: ' . $asunto;
        $mail->Body = '<h2>Nuevo mensaje de contacto</h2>'
            . '<p><strong>Nombre:</strong> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Asunto:</strong> ' . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Mensaje:</strong></p><p>'
            . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '</p>';
        $mail->AltBody = "Nombre: {$nombre}\nEmail: {$email}\nAsunto: {$asunto}\n\n{$mensaje}";
        $mail->send();
        error_log('Email de contacto aceptado por SMTP');
        return true;
    } catch (Throwable $e) {
        error_log('Error al enviar email de contacto');
        return false;
    }
}

/**
 * Verificar si un token de recuperación es válido
 */
function verificarTokenRecuperacion($token) {
    $token = trim((string) $token);
    if (!validarFormatoTokenRecuperacion($token)) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id_usuario, email, nombre, token_recuperacion, token_expiracion 
            FROM usuarios 
            WHERE token_recuperacion = :token 
            AND token_expiracion > NOW()
        ");
        $stmt->execute([':token' => hashTokenRecuperacion($token)]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            error_log("Token de recuperación válido");
            return $usuario;
        }
        
        error_log("❌ Token inválido o expirado");
        return false;
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.RECUPERACION.VERIFICAR_TOKEN', $e);
        return false;
    }
}

/**
 * Obtener noticias relacionadas por categoría
 * @param int $id_noticia ID de la noticia actual
 * @param int $limite Máximo de noticias a devolver
 * @return array Noticias relacionadas
 */
function getNoticiasRelacionadas($id_noticia, $limite = 4, $privada = 0) {
    try {
        $pdo = db();
        
        // Obtener la categoría de la noticia actual
        $stmt = $pdo->prepare("SELECT id_categoria FROM noticias WHERE id_noticia = ?");
        $stmt->execute([$id_noticia]);
        $id_categoria = $stmt->fetchColumn();
        
        if (!$id_categoria) return [];
        
        // Buscar noticias de la misma categoría
        $stmt = $pdo->prepare("
            SELECT n.*, u.nombre as autor_nombre, c.nombre_categoria
            FROM noticias n
            JOIN usuarios u ON n.id_autor = u.id_usuario
            JOIN categorias c ON n.id_categoria = c.id_categoria
            WHERE n.id_categoria = ? 
              AND n.id_noticia != ? 
              AND n.estado = 'publicada'
              AND n.privada = ?
            ORDER BY n.fecha_publicacion DESC
            LIMIT ?
        ");
        $stmt->execute([$id_categoria, $id_noticia, (int) $privada, $limite]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.NOTICIAS_RELACIONADAS', $e);
        return [];
    }
}

/**
 * Generar token CSRF
 */
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificarTokenCSRF($token) {
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function limpiarTokenCSRF() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['csrf_token']);
}

/**
 * Registra acciones de administrador en el log
 * 
 * @param string $accion Acción realizada (ej: 'aprobar_periodista', 'bloquear_usuario')
 * @param int $id_usuario_afectado ID del usuario afectado
 * @param string $email_afectado Email del usuario afectado
 * @param string $detalles Detalles adicionales
 * @return bool
 */
function registrarAdminAccionUsuario($accion, $id_usuario_afectado, $email_afectado, $detalles = '') {
    try {
        $pdo = db();
        
        // Obtener ID del administrador que realiza la acción
        $id_admin = $_SESSION['usuario_id'] ?? 0;
        
        // Insertar en la tabla log_acciones
        $stmt = $pdo->prepare("
            INSERT INTO log_acciones (accion, ip_afectada, email_afectado, detalles, realizado_por, fecha)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        // Obtener IP del visitante
        $ip = obtenerIP();
        
        $realizado_por = $_SESSION['usuario_nombre'] ?? 'admin_' . $id_admin;
        
        $stmt->execute([
            $accion,
            $ip,
            $email_afectado,
            $detalles,
            $realizado_por
        ]);
        
        return true;
        
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.ADMIN.ACCION_USUARIO', $e);
        return false;
    }
}

/**
 * Envía email de APROBACIÓN al periodista
 * 
 * @param string $email Email del periodista
 * @param string $nombre Nombre del periodista
 * @return bool True si se envió correctamente
 */
function enviarEmailAprobacion($email, $nombre, string $rol = 'periodista') {
    // Cargar configuración SMTP si no está definida
    if (!defined('SMTP_HOST') && file_exists(__DIR__ . '/mail-config.php')) {
        require_once __DIR__ . '/mail-config.php';
    }
    
    // Verificar que las constantes existen
    if (!defined('SMTP_HOST')) {
        error_log("ERROR: Constantes SMTP no definidas en enviarEmailAprobacion");
        return false;
    }
    
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    
    $enlace_login = route('login');
    $perfil = $rol === 'usuario' ? 'Comentarista' : 'Articulista';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $nombre);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = "✅ ¡Cuenta de {$perfil} aprobada! - " . SITE_NAME;
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; padding-bottom: 15px; border-bottom: 2px solid #10b981; }
                .header h1 { color: #10b981; margin: 0; font-size: 1.3rem; }
                .content { padding: 20px 0; line-height: 1.6; }
                .button { display: inline-block; background: #10b981; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; margin: 15px 0; font-weight: bold; }
                .button:hover { background: #059669; }
                .footer { text-align: center; padding-top: 15px; font-size: 11px; color: #999; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ ¡Cuenta Aprobada!</h1>
                    <p>' . SITE_NAME . '</p>
                </div>
                <div class="content">
                    <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
                    <p>Tu solicitud de registro como <strong>' . htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') . '</strong> ha sido <strong style="color: #10b981;">APROBADA</strong>.</p>
                    <p>Ya puedes iniciar sesión y acceder a tu cuenta.</p>
                    <div style="text-align: center;">
                        <a href="' . $enlace_login . '" class="button">🔑 Iniciar Sesión</a>
                    </div>
                    <p style="font-size: 12px; color: #666;">Si el botón no funciona, copia este enlace en tu navegador:<br>' . $enlace_login . '</p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' ' . SITE_NAME . '</p>
                    <p>Este es un mensaje automático, por favor no responder.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hola $nombre,\n\nTu solicitud de registro como $perfil ha sido APROBADA.\n\nYa puedes iniciar sesión en: $enlace_login\n\n© " . SITE_NAME;
        
        $mail->send();
        error_log('Email de aprobación aceptado por SMTP');
        return true;
        
    } catch (Exception $e) {
        error_log('No se pudo enviar el email de aprobación');
        return false;
    }
}

/**
 * Envía email de RECHAZO al periodista
 * 
 * @param string $email Email del periodista
 * @param string $nombre Nombre del periodista
 * @param string $motivo Motivo del rechazo
 * @return bool True si se envió correctamente
 */
function enviarEmailRechazo($email, $nombre, $motivo = '') {
    // Cargar configuración SMTP si no está definida
    if (!defined('SMTP_HOST') && file_exists(__DIR__ . '/mail-config.php')) {
        require_once __DIR__ . '/mail-config.php';
    }
    
    if (!defined('SMTP_HOST')) {
        error_log("ERROR: Constantes SMTP no definidas en enviarEmailRechazo");
        return false;
    }
    
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $nombre);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = "❌ Solicitud de periodista rechazada - " . SITE_NAME;
        
        $motivo_html = '';
        if (!empty($motivo)) {
            $motivo_html = '<p><strong>📝 Motivo del rechazo:</strong><br>' . nl2br(htmlspecialchars($motivo)) . '</p>';
        }
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; padding-bottom: 15px; border-bottom: 2px solid #ef4444; }
                .header h1 { color: #ef4444; margin: 0; font-size: 1.3rem; }
                .content { padding: 20px 0; line-height: 1.6; }
                .footer { text-align: center; padding-top: 15px; font-size: 11px; color: #999; border-top: 1px solid #eee; }
                .contacto { background: #f3f4f6; padding: 10px; border-radius: 6px; margin-top: 15px; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>❌ Solicitud Rechazada</h1>
                    <p>' . SITE_NAME . '</p>
                </div>
                <div class="content">
                    <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
                    <p>Tu solicitud de registro como <strong>periodista</strong> ha sido <strong style="color: #ef4444;">RECHAZADA</strong>.</p>
                    ' . $motivo_html . '
                    <div class="contacto">
                        <p>📧 Si crees que es un error, contacta con nosotros:<br>
                        <a href="mailto:' . SMTP_FROM_EMAIL . '">' . SMTP_FROM_EMAIL . '</a></p>
                    </div>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' ' . SITE_NAME . '</p>
                    <p>Este es un mensaje automático, por favor no responder.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hola $nombre,\n\nTu solicitud de registro como periodista ha sido RECHAZADA.\n\n" . ($motivo ? "Motivo: $motivo\n\n" : "") . "Si crees que es un error, contacta con nosotros en " . SMTP_FROM_EMAIL . "\n\n© " . SITE_NAME;
        
        $mail->send();
        error_log('Email de rechazo aceptado por SMTP');
        return true;
        
    } catch (Exception $e) {
        error_log('No se pudo enviar el email de rechazo');
        return false;
    }
}

// ============================================
// FUNCIONES DE ALMACENAMIENTO Y LÍMITES
// ============================================

/**
 * Obtiene el límite de almacenamiento de un usuario según su rol
 * @param int $id_usuario ID del usuario
 * @return int Límite en MB (0 = sin límite)
 */
function obtenerLimiteAlmacenamientoUsuario($id_usuario) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) return 0;
        
        $rol = $usuario['rol'];
        
        // Obtener configuración según el rol
        if ($rol === 'admin') {
            $clave = 'limite_admin_mb';
        } elseif ($rol === 'periodista') {
            $clave = 'limite_periodista_mb';
        } else {
            $clave = 'limite_usuario_mb';
        }
        
        $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $limite = $stmt->fetchColumn();
        
        return ($limite === false) ? 0 : (int)$limite;
        
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.ALMACENAMIENTO.LIMITE_USUARIO', $e);
        return 0;
    }
}

/**
 * Calcula el espacio usado por un usuario en MB
 * @param int $id_usuario ID del usuario
 * @return float Espacio usado en MB
 */
function calcularEspacioUsadoUsuario($id_usuario) {
    $total_bytes = 0;
    $pdo = db();
    
    try {
        // 1. Calcular imágenes y videos de noticias del usuario
        $stmt = $pdo->prepare("
            SELECT imagen_principal, imagen_2, imagen_3, imagen_4, imagen_5, imagen_6,
                   video_nombre, contenido
            FROM noticias 
            WHERE id_autor = ?
        ");
        $stmt->execute([$id_usuario]);
        $noticias = $stmt->fetchAll();
        
        // Definir los campos de imagen una sola vez (fuera del bucle, mejor rendimiento)
$campos_imagen = ['imagen_principal', 'imagen_2', 'imagen_3', 'imagen_4', 'imagen_5', 'imagen_6'];
$archivos_contabilizados = [];

foreach ($noticias as $noticia) {
    // Recorrer todos los campos de imagen
    foreach ($campos_imagen as $campo) {
        $valor = $noticia[$campo] ?? null;
        
        // Verificar si hay imagen y es archivo local (no URL externa)
        if (!empty($valor) && !filter_var($valor, FILTER_VALIDATE_URL)) {
            $ruta = ROOT_PATH . 'uploads/noticias/' . $valor;
            if (file_exists($ruta) && !isset($archivos_contabilizados[$ruta])) {
                $archivos_contabilizados[$ruta] = true;
                $total_bytes += filesize($ruta);
            }
        }
    }
    
    // Video local
    if (!empty($noticia['video_nombre']) && !filter_var($noticia['video_nombre'], FILTER_VALIDATE_URL)) {
        $ruta = ROOT_PATH . 'uploads/noticias/' . $noticia['video_nombre'];
        if (file_exists($ruta) && !isset($archivos_contabilizados[$ruta])) {
            $archivos_contabilizados[$ruta] = true;
            $total_bytes += filesize($ruta);
        }
    }

    // Imágenes locales insertadas en el contenido mediante TinyMCE.
    foreach (obtenerArchivosEditorNoticia((string) ($noticia['contenido'] ?? '')) as $archivoEditor) {
        $ruta = UPLOADS_PATH . 'editor' . DIRECTORY_SEPARATOR . $archivoEditor;
        if (is_file($ruta) && !isset($archivos_contabilizados[$ruta])) {
            $archivos_contabilizados[$ruta] = true;
            $total_bytes += filesize($ruta);
        }
    }
}
        
        // 2. Calcular avatar del usuario
        $stmt = $pdo->prepare("SELECT avatar FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $avatar = $stmt->fetchColumn();
        
        if ($avatar && $avatar !== 'default-avatar.png' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
            $ruta = ROOT_PATH . 'uploads/perfiles/' . $avatar;
            if (file_exists($ruta)) {
                $total_bytes += filesize($ruta);
            }
        }
        
        // Convertir a MB con 2 decimales
        return round($total_bytes / (1024 * 1024), 2);
        
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.ALMACENAMIENTO.USO_USUARIO', $e);
        return 0;
    }
}

/**
 * Verifica si un usuario puede subir un archivo según su límite
 * @param int $id_usuario ID del usuario
 * @param int $tamaño_bytes Tamaño del archivo en bytes
 * @return array ['permitido' => bool, 'mensaje' => string, 'usado' => float, 'limite' => int, 'restante' => float]
 */
function verificarLimiteAlmacenamiento($id_usuario, $tamaño_bytes) {
    $limite_mb = obtenerLimiteAlmacenamientoUsuario($id_usuario);
    $usado_mb = calcularEspacioUsadoUsuario($id_usuario);
    $tamaño_mb = $tamaño_bytes / (1024 * 1024);
    $nuevo_total_mb = $usado_mb + $tamaño_mb;
    
    if ($limite_mb == 0) {
        return [
            'permitido' => true, 
            'mensaje' => 'Sin límite de almacenamiento',
            'usado' => $usado_mb, 
            'limite' => 0,
            'restante' => 0,
            'porcentaje' => 0
        ];
    }
    
    $porcentaje = round(($usado_mb / $limite_mb) * 100, 1);
    $restante_mb = round($limite_mb - $usado_mb, 2);
    
    if ($nuevo_total_mb > $limite_mb) {
        return [
            'permitido' => false,
            'mensaje' => "❌ No puedes subir este archivo. Has usado {$usado_mb} MB de {$limite_mb} MB disponibles. Espacio restante: {$restante_mb} MB.",
            'usado' => $usado_mb,
            'limite' => $limite_mb,
            'restante' => $restante_mb,
            'porcentaje' => $porcentaje
        ];
    }
    
    // Notificar si supera el 80% (opcional)
    if ($porcentaje >= 80 && $porcentaje < 100) {
        $_SESSION['alerta_almacenamiento'] = "⚠️ Has usado el {$porcentaje}% de tu límite de almacenamiento ({$usado_mb} MB de {$limite_mb} MB).";
    }
    
    return [
        'permitido' => true,
        'mensaje' => "✅ Espacio disponible: " . round($limite_mb - $nuevo_total_mb, 2) . " MB restantes",
        'usado' => $usado_mb,
        'limite' => $limite_mb,
        'restante' => round($limite_mb - $nuevo_total_mb, 2),
        'porcentaje' => $porcentaje
    ];
}

/**
 * Obtiene estadísticas de almacenamiento para el panel de admin
 * @return array Estadísticas globales
 */
function obtenerEstadisticasAlmacenamientoGlobal() {
    try {
        $pdo = db();
        
        // Total de archivos en uploads/noticias
        $dir_noticias = ROOT_PATH . 'uploads/noticias/';
        $total_noticias_bytes = 0;
        $total_noticias_archivos = 0;
        
        if (is_dir($dir_noticias)) {
            $files = scandir($dir_noticias);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $ruta = $dir_noticias . $file;
                    if (is_file($ruta)) {
                        $total_noticias_bytes += filesize($ruta);
                        $total_noticias_archivos++;
                    }
                }
            }
        }
        
        // Total de archivos en uploads/perfiles
        $dir_perfiles = ROOT_PATH . 'uploads/perfiles/';
        $total_perfiles_bytes = 0;
        $total_perfiles_archivos = 0;
        
        if (is_dir($dir_perfiles)) {
            $files = scandir($dir_perfiles);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== 'default-avatar.png') {
                    $ruta = $dir_perfiles . $file;
                    if (is_file($ruta)) {
                        $total_perfiles_bytes += filesize($ruta);
                        $total_perfiles_archivos++;
                    }
                }
            }
        }
        
        return [
            'noticias' => [
                'archivos' => $total_noticias_archivos,
                'mb' => round($total_noticias_bytes / (1024 * 1024), 2)
            ],
            'perfiles' => [
                'archivos' => $total_perfiles_archivos,
                'mb' => round($total_perfiles_bytes / (1024 * 1024), 2)
            ],
            'total_mb' => round(($total_noticias_bytes + $total_perfiles_bytes) / (1024 * 1024), 2),
            'total_archivos' => $total_noticias_archivos + $total_perfiles_archivos
        ];
        
    } catch (Exception $e) {
        registrarErrorInterno('FUNCIONES.ALMACENAMIENTO.ESTADISTICAS', $e);
        return [
            'noticias' => ['archivos' => 0, 'mb' => 0],
            'perfiles' => ['archivos' => 0, 'mb' => 0],
            'total_mb' => 0,
            'total_archivos' => 0
        ];
    }
}

/**
 * Convierte URLs de servicios en la nube a enlaces directos
 * Soporta: Dropbox (formatos antiguo y nuevo)
 * 
 * @param string $url URL original
 * @return string URL convertida (o la misma si no es convertible)
 */
function convertirUrlNubeDirecta($url) {
    if (empty($url)) {
        return $url;
    }
    
    // ============================================
    // DROPBOX (nuevo formato /scl/fi/ con rlkey)
    // ============================================
    if (strpos($url, 'dropbox.com/scl/fi/') !== false) {
        // Extraer la ruta completa después de /scl/fi/
        if (preg_match('/dropbox\.com\/scl\/fi\/([^?]+)/', $url, $matches)) {
            $path = $matches[1];
            // Extraer el rlkey si existe
            preg_match('/rlkey=([^&]+)/', $url, $keyMatches);
            $rlkey = $keyMatches[1] ?? '';
            
            // Construir URL directa con dl.dropboxusercontent.com
            $converted = 'https://dl.dropboxusercontent.com/scl/fi/' . $path;
            if ($rlkey) {
                $converted .= '?rlkey=' . $rlkey . '&dl=0';
            }
            return $converted;
        }
    }
    
    // ============================================
    // DROPBOX (formato antiguo /s/)
    // ============================================
    if (preg_match('/dropbox\.com\/s\/([^\/]+)\/([^?]+)/', $url, $matches)) {
        return 'https://dl.dropboxusercontent.com/s/' . $matches[1] . '/' . $matches[2] . '?raw=1';
    }
    
    // Si no es convertible, devolver la URL original
    return $url;
}
