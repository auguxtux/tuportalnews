<?php
declare(strict_types=1);


/**
 * SISTEMA DE LOGS DE ACTIVIDAD
 * Registra acciones importantes en el sistema
 */

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/config.php';
}

if (!defined('LOG_RETENTION_DAYS')) {
    define('LOG_RETENTION_DAYS', 90);
}

/**
 * Elimina un lote acotado de logs de actividad que superan la retención.
 */
function limpiarLogsActividadAntiguos(
    PDO $pdo,
    int $dias = LOG_RETENTION_DAYS,
    int $limite = 500
): int {
    $dias = max(1, min($dias, 3650));
    $limite = max(1, min($limite, 5000));

    return $pdo->exec(
        "DELETE FROM log_acciones
         WHERE fecha < DATE_SUB(NOW(), INTERVAL {$dias} DAY)
         ORDER BY fecha ASC
         LIMIT {$limite}"
    );
}

/**
 * Aplica una sola vez por petición el lote preventivo de retención.
 */
function aplicarRetencionLogsActividad(PDO $pdo): int {
    static $aplicada = false;

    if ($aplicada) {
        return 0;
    }

    $aplicada = true;

    try {
        return limpiarLogsActividadAntiguos($pdo);
    } catch (Throwable $e) {
        error_log('[LOGS] No se pudo aplicar la política de retención.');
        return 0;
    }
}

/**
 * Registrar una acción en el log
 * 
 * @param string $accion Acción realizada (ej: 'login', 'crear_noticia', 'bloquear_usuario')
 * @param string|null $ip_afectada IP afectada (opcional)
 * @param string|null $email_afectado Email del usuario afectado (opcional)
 * @param string|null $detalles Detalles adicionales (opcional)
 * @return bool True si se registró correctamente
 */
function registrarLog($accion, $ip_afectada = null, $email_afectado = null, $detalles = null) {
    try {
        $pdo = db();

        aplicarRetencionLogsActividad($pdo);
        
        // Quién realizó la acción
        $realizado_por = $_SESSION['usuario_email'] ?? 'sistema';
        
        $stmt = $pdo->prepare("
            INSERT INTO log_acciones (accion, ip_afectada, email_afectado, detalles, realizado_por, fecha) 
            VALUES (:accion, :ip_afectada, :email_afectado, :detalles, :realizado_por, NOW())
        ");
        
        return $stmt->execute([
            ':accion' => $accion,
            ':ip_afectada' => $ip_afectada,
            ':email_afectado' => $email_afectado,
            ':detalles' => $detalles,
            ':realizado_por' => $realizado_por
        ]);
    } catch (Exception $e) {
        registrarErrorInterno('LOGS.REGISTRAR', $e);
        return false;
    }
}

/**
 * Registrar intento de login fallido
 */
function registrarLoginFallido($email, $ip) {
    return registrarLog('login_fallido', $ip, $email, "Intento de inicio de sesión fallido");
}

/**
 * Registrar login exitoso
 */
function registrarLoginExitoso($email, $ip) {
    return registrarLog('login_exitoso', $ip, $email, "Inicio de sesión exitoso");
}

/**
 * Registrar cierre de sesión
 */
function registrarLogout($email) {
    return registrarLog('logout', null, $email, "Cierre de sesión");
}

/**
 * Registrar creación de noticia
 */
function registrarCreacionNoticia($id_noticia, $titulo) {
    return registrarLog('crear_noticia', null, null, "Noticia ID: {$id_noticia} - Título: " . substr($titulo, 0, 100));
}

/**
 * Registrar edición de noticia
 */
function registrarEdicionNoticia($id_noticia, $titulo) {
    return registrarLog('editar_noticia', null, null, "Noticia ID: {$id_noticia} - Título: " . substr($titulo, 0, 100));
}

/**
 * Registrar eliminación de noticia
 */
function registrarEliminacionNoticia($id_noticia, $titulo) {
    return registrarLog('eliminar_noticia', null, null, "Noticia ID: {$id_noticia} - Título: " . substr($titulo, 0, 100));
}

/**
 * Registrar comentario
 */
function registrarComentario($id_comentario, $id_noticia) {
    return registrarLog('comentario', null, null, "Comentario ID: {$id_comentario} en noticia ID: {$id_noticia}");
}

/**
 * Registrar acción administrativa sobre usuario
 */
function registrarAccionUsuario($accion, $id_usuario, $email_usuario) {
    return registrarLog("usuario_{$accion}", null, $email_usuario, "Usuario ID: {$id_usuario}");
}

/**
 * Registrar cambio de rol de usuario
 */
function registrarCambioRol($id_usuario, $email_usuario, $rol_anterior, $rol_nuevo) {
    return registrarLog('cambiar_rol', null, $email_usuario, "Usuario ID: {$id_usuario} - Rol anterior: {$rol_anterior} → {$rol_nuevo}");
}

/**
 * Registrar bloqueo de IP
 */
function registrarBloqueoIP($ip, $motivo) {
    return registrarLog('bloquear_ip', $ip, null, "Motivo: {$motivo}");
}
