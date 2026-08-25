<?php
declare(strict_types=1);


/**
 * FUNCIONES PARA ELIMINACIÓN DE CUENTA DE USUARIO
 * Maneja la eliminación de la cuenta y todo su contenido asociado
 */

/**
 * Eliminar completamente la cuenta de un usuario y todo su contenido
 * @param int $id_usuario ID del usuario a eliminar
 * @param PDO $pdo Conexión a la base de datos
 * @return array Resultado de la operación ['success' => bool, 'message' => string]
 */
function eliminarCuentaCompleta($id_usuario, $pdo) {
    $archivos_a_eliminar = [];
    $stats = [
        'noticias' => 0,
        'comentarios' => 0,
        'likes' => 0,
    ];

    try {
        $pdo->beginTransaction();
        
        // Obtener datos del usuario
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, email, avatar, rol, creado_por_admin FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Usuario no encontrado'];
        }

        $esCuentaPropia = (int) ($_SESSION['usuario_id'] ?? 0) === (int) $id_usuario;
        if (
            Permisos::esUsuarioRoot($usuario)
            || (!$esCuentaPropia && !Permisos::puedeGestionarUsuario($usuario, 'eliminar'))
        ) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta cuenta.',
            ];
        }
        
        // 1. ELIMINAR NOTICIAS (si es periodista)
        if ($usuario['rol'] === 'periodista') {
            // Obtener IDs y archivos de noticias
            $stmt = $pdo->prepare("SELECT id_noticia, contenido, imagen_principal, imagen_2, imagen_3, imagen_4, imagen_5, imagen_6, video_nombre
                                   FROM noticias
                                   WHERE id_autor = ?");
            $stmt->execute([$id_usuario]);
            $noticias = $stmt->fetchAll();
            $ids_noticias = array_map('intval', array_column($noticias, 'id_noticia'));
            $stats['noticias'] = count($ids_noticias);

            foreach ($noticias as $noticia) {
                foreach (obtenerArchivosLocalesNoticia($noticia) as $archivo) {
                    $archivos_a_eliminar[$archivo['ruta']] = $archivo;
                }
            }
            
            if ($stats['noticias'] > 0) {
                $placeholders = implode(',', array_fill(0, $stats['noticias'], '?'));
                
                // Eliminar likes de noticias
                $pdo->prepare("DELETE FROM megusta_noticias WHERE id_noticia IN ($placeholders)")->execute($ids_noticias);
                
                // Eliminar comentarios de noticias
                $pdo->prepare("DELETE FROM comentarios WHERE id_noticia IN ($placeholders)")->execute($ids_noticias);
                
                // Eliminar estadísticas privadas
                $pdo->prepare("DELETE FROM estadisticas_privadas WHERE id_noticia IN ($placeholders)")->execute($ids_noticias);
                
                // Eliminar noticias
                $pdo->prepare("DELETE FROM noticias WHERE id_autor = ?")->execute([$id_usuario]);
            }
        }
        
        // 2. ELIMINAR COMENTARIOS DEL USUARIO
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $stats['comentarios'] = $stmt->fetchColumn();
        
        if ($stats['comentarios'] > 0) {
            // Eliminar comentarios
            $pdo->prepare("DELETE FROM comentarios WHERE id_usuario = ?")->execute([$id_usuario]);
        }
        
        // 3. ELIMINAR LIKES DADOS POR EL USUARIO
        $stmt = $pdo->prepare("SELECT DISTINCT id_noticia FROM megusta_noticias WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $noticias_valoradas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $stats['likes'] = count($noticias_valoradas);
        
        if ($stats['likes'] > 0) {
            $pdo->prepare("DELETE FROM megusta_noticias WHERE id_usuario = ?")->execute([$id_usuario]);

            $stmt_recalcular = $pdo->prepare(
                "UPDATE noticias n
                 LEFT JOIN (
                     SELECT id_noticia, COUNT(*) AS total, AVG(valoracion) AS promedio
                     FROM megusta_noticias
                     WHERE id_noticia = ? AND valoracion IS NOT NULL
                     GROUP BY id_noticia
                 ) v ON v.id_noticia = n.id_noticia
                 SET n.total_valoraciones = COALESCE(v.total, 0),
                     n.valoracion_promedio = COALESCE(v.promedio, 0)
                 WHERE n.id_noticia = ?"
            );

            foreach ($noticias_valoradas as $id_noticia_valorada) {
                $stmt_recalcular->execute([$id_noticia_valorada, $id_noticia_valorada]);
            }
        }

        // Eliminar favoritos, reportes y notificaciones del usuario
        $pdo->prepare("DELETE FROM favoritos WHERE id_usuario = ?")->execute([$id_usuario]);
        $pdo->prepare("DELETE FROM reportes_noticias WHERE usuario_id = ?")->execute([$id_usuario]);
        $pdo->prepare("DELETE FROM reportes_comentarios WHERE usuario_id = ?")->execute([$id_usuario]);
        $pdo->prepare("DELETE FROM notificaciones WHERE id_usuario = ?")->execute([$id_usuario]);

        if ($usuario['rol'] === 'periodista') {
            /*
             * Las fuentes RSS son compartidas por los periodistas. Al eliminar
             * definitivamente a su propietario se conservan bajo administración,
             * aunque todas las noticias creadas por el periodista sí se eliminan.
             */
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM fuentes_rss WHERE id_propietario = ?");
            $stmt->execute([$id_usuario]);
            $totalFuentesRss = (int) $stmt->fetchColumn();

            if ($totalFuentesRss > 0) {
                $idAdminSesion = (
                    ($_SESSION['usuario_rol'] ?? '') === 'admin'
                    && (int) ($_SESSION['usuario_id'] ?? 0) !== (int) $id_usuario
                ) ? (int) $_SESSION['usuario_id'] : 0;

                if ($idAdminSesion > 0) {
                    $stmt = $pdo->prepare(
                        "SELECT id_usuario FROM usuarios
                         WHERE id_usuario = ? AND rol = 'admin' AND estado = 'activo'"
                    );
                    $stmt->execute([$idAdminSesion]);
                    $idAdminDestino = (int) $stmt->fetchColumn();
                } else {
                    $stmt = $pdo->prepare(
                        "SELECT id_usuario FROM usuarios
                         WHERE rol = 'admin' AND estado = 'activo' AND id_usuario <> ?
                         ORDER BY id_usuario LIMIT 1"
                    );
                    $stmt->execute([$id_usuario]);
                    $idAdminDestino = (int) $stmt->fetchColumn();
                }

                if ($idAdminDestino <= 0) {
                    throw new RuntimeException(
                        'No existe un administrador activo para conservar las fuentes RSS.'
                    );
                }

                $stmt = $pdo->prepare(
                    'UPDATE fuentes_rss SET id_propietario = ? WHERE id_propietario = ?'
                );
                $stmt->execute([$idAdminDestino, $id_usuario]);
            }
        } else {
            $pdo->prepare("DELETE FROM fuentes_rss WHERE id_propietario = ?")
                ->execute([$id_usuario]);
        }
        
        // 4. ELIMINAR REGISTRO DE USUARIO PRIVADO (si existe)
        $pdo->prepare("DELETE FROM usuarios_privados WHERE id_usuario = ?")->execute([$id_usuario]);
        
        // 5. ELIMINAR INTENTOS DE LOGIN
        $pdo->prepare("DELETE FROM login_attempts WHERE email = ?")->execute([$usuario['email']]);
        
        // 6. ELIMINAR USUARIO
        $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$id_usuario]);
        
        $pdo->commit();

        foreach ($archivos_a_eliminar as $archivo) {
            if ($archivo['tipo'] === 'editor') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE contenido LIKE ?');
                $stmt->execute(['%/uploads/editor/' . $archivo['nombre'] . '%']);
                if ((int) $stmt->fetchColumn() > 0) {
                    continue;
                }
            }

            if (is_file($archivo['ruta'])) {
                unlink($archivo['ruta']);
            }
        }

        $avatar = $usuario['avatar'] ?? null;
        if (
            is_string($avatar)
            && $avatar !== ''
            && !in_array($avatar, ['default-avatar.png', 'default.jpg'], true)
            && !filter_var($avatar, FILTER_VALIDATE_URL)
            && basename($avatar) === $avatar
        ) {
            $ruta_avatar = UPLOAD_PERFILES . $avatar;
            if (is_file($ruta_avatar)) {
                unlink($ruta_avatar);
            }
        }
        
        error_log(
            "Cuenta eliminada. Noticias:{$stats['noticias']} "
            . "Comentarios:{$stats['comentarios']} Likes:{$stats['likes']}"
        );
        
        return [
            'success' => true,
            'message' => 'Tu cuenta ha sido eliminada correctamente',
            'stats' => $stats
        ];
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        registrarErrorInterno('CUENTA.ELIMINAR_COMPLETAMENTE', $e);
        return ['success' => false, 'message' => 'No se pudo eliminar la cuenta. Inténtalo de nuevo.'];
    }
}

/**
 * Obtener estadísticas del usuario para mostrar antes de eliminar
 * @param int $id_usuario ID del usuario
 * @param PDO $pdo Conexión a la base de datos
 * @return array Estadísticas
 */
function getEstadisticasUsuario($id_usuario, $pdo) {
    $stats = [
        'noticias' => 0,
        'comentarios' => 0,
        'likes_dados' => 0,
        'likes_recibidos' => 0
    ];
    
    // Noticias (si es periodista)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_autor = ?");
    $stmt->execute([$id_usuario]);
    $stats['noticias'] = $stmt->fetchColumn();
    
    // Comentarios propios
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $stats['comentarios'] = $stmt->fetchColumn();
    
    // Likes dados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM megusta_noticias WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $stats['likes_dados'] = $stmt->fetchColumn();
    
    // Likes recibidos en comentarios (opcional)
    $stmt = $pdo->prepare("
        SELECT SUM(megusta) FROM comentarios WHERE id_usuario = ?
    ");
    $stmt->execute([$id_usuario]);
    $stats['likes_recibidos'] = (int)$stmt->fetchColumn();
    
    return $stats;
}
?>
