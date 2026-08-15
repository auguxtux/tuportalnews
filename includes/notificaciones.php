<?php
declare(strict_types=1);


/**
 * SISTEMA DE NOTIFICACIONES
 * Gestiona notificaciones para usuarios (comentarios, respuestas, likes, etc.)
 */

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Crear una nueva notificación
 * @param int $id_usuario ID del usuario destinatario
 * @param string $tipo Tipo de notificación (comentario, respuesta, like, sistema)
 * @param string $mensaje Mensaje de la notificación
 * @param string|null $enlace Enlace opcional (URL completa o relativa)
 * @return bool True si se creó correctamente
 */
function crearNotificacion($id_usuario, $tipo, $mensaje, $enlace = null) {
    if (!$id_usuario || $id_usuario <= 0) return false;
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (id_usuario, tipo, mensaje, enlace) 
            VALUES (:id_usuario, :tipo, :mensaje, :enlace)
        ");
        return $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':tipo' => $tipo,
            ':mensaje' => $mensaje,
            ':enlace' => $enlace
        ]);
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.CREAR', $e);
        return false;
    }
}

/**
 * Obtener notificaciones no leídas de un usuario
 * @param int $id_usuario ID del usuario
 * @param int $limite Número máximo de notificaciones (por defecto 20)
 * @return array Lista de notificaciones
 */
function obtenerNotificacionesNoLeidas($id_usuario, $limite = 20) {
    if (!$id_usuario || $id_usuario <= 0) return [];
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE id_usuario = :id_usuario AND leida = 0 
            ORDER BY fecha DESC 
            LIMIT :limite
        ");
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.NO_LEIDAS', $e);
        return [];
    }
}

/**
 * Obtener todas las notificaciones de un usuario (con paginación)
 * @param int $id_usuario ID del usuario
 * @param int $pagina Número de página
 * @param int $por_pagina Elementos por página
 * @return array Lista de notificaciones y total
 */
function obtenerNotificaciones($id_usuario, $pagina = 1, $por_pagina = 20) {
    if (!$id_usuario || $id_usuario <= 0) return ['notificaciones' => [], 'total' => 0];
    
    try {
        $pdo = db();
        $offset = ($pagina - 1) * $por_pagina;
        
        // Total
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id_usuario");
        $stmt->execute([':id_usuario' => $id_usuario]);
        $total = $stmt->fetchColumn();
        
        // Notificaciones
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE id_usuario = :id_usuario 
            ORDER BY fecha DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notificaciones = $stmt->fetchAll();
        
        return ['notificaciones' => $notificaciones, 'total' => $total];
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.LISTAR', $e);
        return ['notificaciones' => [], 'total' => 0];
    }
}

/**
 * Marcar una notificación como leída
 * @param int $id_notificacion ID de la notificación
 * @param int $id_usuario ID del usuario (para verificar propiedad)
 * @return bool True si se marcó correctamente
 */
function marcarNotificacionLeida($id_notificacion, $id_usuario) {
    if (!$id_notificacion || !$id_usuario) return false;
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leida = 1 
            WHERE id_notificacion = :id_notificacion AND id_usuario = :id_usuario
        ");
        return $stmt->execute([
            ':id_notificacion' => $id_notificacion,
            ':id_usuario' => $id_usuario
        ]);
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.MARCAR_LEIDA', $e);
        return false;
    }
}

/**
 * Marcar todas las notificaciones de un usuario como leídas
 * @param int $id_usuario ID del usuario
 * @return bool True si se marcaron correctamente
 */
function marcarTodasNotificacionesLeidas($id_usuario) {
    if (!$id_usuario || $id_usuario <= 0) return false;
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leida = 1 
            WHERE id_usuario = :id_usuario AND leida = 0
        ");
        return $stmt->execute([':id_usuario' => $id_usuario]);
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.MARCAR_TODAS', $e);
        return false;
    }
}

/**
 * Contar notificaciones no leídas de un usuario
 * @param int $id_usuario ID del usuario
 * @return int Número de notificaciones no leídas
 */
function contarNotificacionesNoLeidas($id_usuario) {
    if (!$id_usuario || $id_usuario <= 0) return 0;
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notificaciones 
            WHERE id_usuario = :id_usuario AND leida = 0
        ");
        $stmt->execute([':id_usuario' => $id_usuario]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.CONTAR_NO_LEIDAS', $e);
        return 0;
    }
}

/**
 * Eliminar notificaciones antiguas (más de 30 días)
 * @return int Número de notificaciones eliminadas
 */
function limpiarNotificacionesAntiguas() {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            DELETE FROM notificaciones 
            WHERE fecha < DATE_SUB(NOW(), INTERVAL 30 DAY) AND leida = 1
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        registrarErrorInterno('NOTIFICACIONES.LIMPIAR_ANTIGUAS', $e);
        return 0;
    }
}
