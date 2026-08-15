<?php
declare(strict_types=1);


/**
 * PROCESAR COMENTARIO - Versión segura con notificaciones y logs
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificaciones.php';
require_once __DIR__ . '/../includes/logs.php';
require_once __DIR__ . '/../includes/privado.php';

$vistaPrivada = defined('PROCESAR_COMENTARIO_PRIVADO') && PROCESAR_COMENTARIO_PRIVADO === true;
$rutaNoticia = $vistaPrivada ? 'privado_noticia' : 'noticia';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido');
}

if ($vistaPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    exit('Contenido no disponible');
}

// 1. Verificar sesión
if (!estaLogueado()) {
    mensajeFlash('error', 'Debes iniciar sesión para comentar.');
    header('Location: ' . route('login'));
    exit;
}

// 2. Verificar CSRF
if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    error_log('[COMENTARIOS] Solicitud rechazada por token CSRF no válido.');
    mensajeFlash('error', 'Error de seguridad. Inténtalo de nuevo.');
    header('Location: ' . route('home'));
    exit;
}

// 3. Validar datos
$id_noticia_parametro = $_POST['id_noticia'] ?? null;
$contenido_parametro = $_POST['contenido'] ?? null;
$id_noticia = is_scalar($id_noticia_parametro) ? (int) $id_noticia_parametro : 0;
$contenido = is_string($contenido_parametro) ? trim($contenido_parametro) : '';

if (!$id_noticia) {
    mensajeFlash('error', 'ID de noticia no válido.');
    header('Location: ' . route('home'));
    exit;
}

if (empty($contenido)) {
    mensajeFlash('error', 'El comentario no puede estar vacío.');
    header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
    exit;
}

// 4. Limitar longitud
if (strlen($contenido) > 5000) {
    mensajeFlash('error', 'El comentario es demasiado largo (máximo 5000 caracteres).');
    header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
    exit;
}

// 5. SANITIZAR contenido HTML
$contenido = sanitizarHtmlComentario($contenido);

if (empty($contenido)) {
    mensajeFlash('error', 'El comentario no puede estar vacío después de limpiar el formato.');
    header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
    exit;
}

try {
    $pdo = db();
    
    // ✅ Verificar límite de comentarios por usuario (anti-spam)
    $limite_tiempo = 10; // minutos
    $limite_comentarios = 3; // máximo de comentarios en ese tiempo
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM comentarios 
        WHERE id_usuario = ? 
        AND fecha_comentario > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$_SESSION['usuario_id'], $limite_tiempo]);
    $total_recientes = $stmt->fetchColumn();
    
    if ($total_recientes >= $limite_comentarios) {
        mensajeFlash('warning', 'Has hecho demasiados comentarios en poco tiempo. Espera ' . $limite_tiempo . ' minutos.');
        header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
        exit;
    }
    
    // 6. Verificar noticia existe y permite comentarios
    $stmt = $pdo->prepare(
        "SELECT id_noticia, id_autor, titulo, permitir_comentarios
         FROM noticias
         WHERE id_noticia = ?
           AND estado = 'publicada'
           AND privada = ?
         LIMIT 1"
    );
    $stmt->execute([$id_noticia, $vistaPrivada ? 1 : 0]);
    $noticia = $stmt->fetch();
    
    if (!$noticia) {
        mensajeFlash('error', 'La noticia no existe.');
        header('Location: ' . route('home'));
        exit;
    }
    
    if ($noticia['permitir_comentarios'] == 0) {
        mensajeFlash('error', 'Los comentarios están desactivados para esta noticia.');
        header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
        exit;
    }
    
    // 7. Prevenir duplicados
    $stmt = $pdo->prepare("
        SELECT id_comentario FROM comentarios 
        WHERE id_noticia = ? AND id_usuario = ? AND contenido = ? 
        AND fecha_comentario > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$id_noticia, $_SESSION['usuario_id'], $contenido]);
    if ($stmt->fetch()) {
        mensajeFlash('warning', 'Ya has publicado este comentario recientemente.');
        header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
        exit;
    }
    
    // 8. Verificar moderación
    $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'comentarios_aprobacion'");
    $config = $stmt->fetchColumn();
    $estado = ($config == '1') ? 'pendiente' : 'aprobado';
    
    // 9. Insertar comentario
    $stmt = $pdo->prepare("
        INSERT INTO comentarios (id_noticia, id_usuario, contenido, estado, fecha_comentario) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$id_noticia, $_SESSION['usuario_id'], $contenido, $estado]);
    
    // 🆕 Registrar comentario en logs
    $id_comentario = $pdo->lastInsertId();
    registrarComentario($id_comentario, $id_noticia);
    
    // ============================================
    // 🆕 CREAR NOTIFICACIÓN PARA EL AUTOR DE LA NOTICIA
    // ============================================
    // Solo notificar si el autor es diferente al que comenta
    if ($noticia['id_autor'] != $_SESSION['usuario_id']) {
        $nombre_comentador = $_SESSION['usuario_nombre'] ?? 'Un usuario';
        $titulo_corto = mb_substr($noticia['titulo'], 0, 50);
        $mensaje = $nombre_comentador . ' ha comentado en tu noticia: "' . $titulo_corto . '"';
        $enlace = route($rutaNoticia, ['id' => $id_noticia]) . '#comentarios';
        
        crearNotificacion(
            $noticia['id_autor'],
            'comentario',
            $mensaje,
            $enlace
        );
    }
    // ============================================
    
    if ($estado == 'pendiente') {
        mensajeFlash('warning', 'Comentario enviado. Esperando moderación.');
    } else {
        mensajeFlash('success', 'Comentario publicado correctamente.');
    }
    
} catch (Exception $e) {
    registrarErrorInterno('PUBLIC.COMENTARIO.PROCESAR', $e);
    mensajeFlash('error', 'Error al publicar el comentario. Por favor, inténtalo de nuevo.');
}

header('Location: ' . route($rutaNoticia, ['id' => $id_noticia]));
exit;
?>
