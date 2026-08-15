<?php
declare(strict_types=1);


/**
 * SISTEMA DE VALORACIONES UNIFICADO
 * Versión adaptada a la tabla megusta_noticias con session_id
 */

require_once __DIR__ . '/conexion.php';

class Valoraciones {

    /**
     * Obtener identificador del visitante (basado en sesión)
     */
    public static function getVisitorIdentifier() {
        if (!isset($_SESSION['visitor_id'])) {
            $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['visitor_id'];
    }

    /**
     * Verificar si un visitante puede votar (SIEMPRE TRUE)
     */
    public static function puedeVotarVisitante($id_noticia, $session_id) {
        return ['puede' => true, 'mensaje' => 'Puedes votar'];
    }

    /**
     * Registrar voto de visitante
     */
    public static function registrarVotoVisitante($id_noticia, $valoracion, $session_id) {
        $pdo = db();

        try {
            $stmt = $pdo->prepare("
                SELECT id_megusta
                FROM megusta_noticias
                WHERE id_noticia = ?
                AND session_id = ?
                AND tipo_usuario = 'visitante'
            ");
            $stmt->execute([$id_noticia, $session_id]);
            $voto_existente = $stmt->fetch();

            if ($voto_existente) {
                $stmt = $pdo->prepare("
                    UPDATE megusta_noticias
                    SET valoracion = ?,
                        fecha_megusta = NOW()
                    WHERE id_megusta = ?
                ");
                $stmt->execute([$valoracion, $voto_existente['id_megusta']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO megusta_noticias (
                        id_noticia,
                        tipo_usuario,
                        session_id,
                        valoracion
                    ) VALUES (?, 'visitante', ?, ?)
                ");
                $stmt->execute([$id_noticia, $session_id, $valoracion]);
            }

            self::actualizarEstadisticas($id_noticia);
            return true;

        } catch (Exception $e) {
            registrarErrorInterno('VALORACIONES.VISITANTE.REGISTRAR', $e);
            return false;
        }
    }

    /**
     * Verificar si un usuario registrado puede votar (SIEMPRE TRUE)
     */
    public static function puedeVotarUsuario($id_noticia, $id_usuario) {
        return ['puede' => true, 'mensaje' => 'Puedes votar'];
    }

    /**
     * Registrar voto de usuario registrado
     */
    public static function registrarVotoUsuario($id_noticia, $id_usuario, $valoracion) {
        $pdo = db();

        try {
            $stmt = $pdo->prepare("
                SELECT id_megusta
                FROM megusta_noticias
                WHERE id_noticia = ?
                AND id_usuario = ?
                AND tipo_usuario = 'registrado'
            ");
            $stmt->execute([$id_noticia, $id_usuario]);
            $voto_existente = $stmt->fetch();

            if ($voto_existente) {
                $stmt = $pdo->prepare("
                    UPDATE megusta_noticias
                    SET valoracion = ?,
                        fecha_megusta = NOW()
                    WHERE id_megusta = ?
                ");
                $stmt->execute([$valoracion, $voto_existente['id_megusta']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO megusta_noticias (
                        id_noticia,
                        id_usuario,
                        tipo_usuario,
                        valoracion
                    ) VALUES (?, ?, 'registrado', ?)
                ");
                $stmt->execute([$id_noticia, $id_usuario, $valoracion]);
            }

            self::actualizarEstadisticas($id_noticia);
            return true;

        } catch (Exception $e) {
            registrarErrorInterno('VALORACIONES.USUARIO.REGISTRAR', $e);
            return false;
        }
    }

    /**
     * Actualizar estadísticas de la noticia
     */
    public static function actualizarEstadisticas($id_noticia) {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_votos,
                AVG(valoracion) as media,
                SUM(CASE WHEN valoracion = 1 THEN 1 ELSE 0 END) as votos_1,
                SUM(CASE WHEN valoracion = 2 THEN 1 ELSE 0 END) as votos_2,
                SUM(CASE WHEN valoracion = 3 THEN 1 ELSE 0 END) as votos_3
            FROM megusta_noticias
            WHERE id_noticia = ?
        ");
        $stmt->execute([$id_noticia]);
        $stats = $stmt->fetch();

        $stmt = $pdo->prepare("
            UPDATE noticias
            SET valoracion_promedio = ?,
                total_valoraciones = ?
            WHERE id_noticia = ?
        ");
        $stmt->execute([
            $stats['media'] ?? 0,
            $stats['total_votos'] ?? 0,
            $id_noticia
        ]);

        $stmt = $pdo->prepare(
            'SELECT privada
             FROM noticias
             WHERE id_noticia = ?
             LIMIT 1'
        );
        $stmt->execute([$id_noticia]);

        if ((int) $stmt->fetchColumn() === 1) {
            $stmt = $pdo->prepare(
                'INSERT INTO estadisticas_privadas
                    (id_noticia, visitas_privadas, megusta_privados, ultima_actualizacion)
                 VALUES (?, 0, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    megusta_privados = VALUES(megusta_privados),
                    ultima_actualizacion = NOW()'
            );
            $stmt->execute([$id_noticia, (int) ($stats['total_votos'] ?? 0)]);
        }
    }

    /**
     * Obtener estadísticas completas de una noticia
     */
    public static function getEstadisticas($id_noticia) {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                AVG(valoracion) as media,
                SUM(CASE WHEN valoracion = 1 THEN 1 ELSE 0 END) as votos_1,
                SUM(CASE WHEN valoracion = 2 THEN 1 ELSE 0 END) as votos_2,
                SUM(CASE WHEN valoracion = 3 THEN 1 ELSE 0 END) as votos_3
            FROM megusta_noticias
            WHERE id_noticia = ? AND tipo_usuario = 'registrado'
        ");
        $stmt->execute([$id_noticia]);
        $registrados = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                AVG(valoracion) as media,
                SUM(CASE WHEN valoracion = 1 THEN 1 ELSE 0 END) as votos_1,
                SUM(CASE WHEN valoracion = 2 THEN 1 ELSE 0 END) as votos_2,
                SUM(CASE WHEN valoracion = 3 THEN 1 ELSE 0 END) as votos_3
            FROM megusta_noticias
            WHERE id_noticia = ? AND tipo_usuario = 'visitante'
        ");
        $stmt->execute([$id_noticia]);
        $visitantes = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                AVG(valoracion) as media,
                SUM(CASE WHEN valoracion = 1 THEN 1 ELSE 0 END) as votos_1,
                SUM(CASE WHEN valoracion = 2 THEN 1 ELSE 0 END) as votos_2,
                SUM(CASE WHEN valoracion = 3 THEN 1 ELSE 0 END) as votos_3
            FROM megusta_noticias
            WHERE id_noticia = ?
        ");
        $stmt->execute([$id_noticia]);
        $totales = $stmt->fetch();

        $voto_usuario = self::getVotoActual($id_noticia);

        return [
            'registrados' => $registrados,
            'visitantes' => $visitantes,
            'totales' => $totales,
            'voto_usuario' => $voto_usuario,
            'clasificacion' => self::getClasificacion($totales['media'] ?? 0)
        ];
    }

    /**
     * Obtener voto actual del usuario/visitante
     */
    public static function getVotoActual($id_noticia) {
        $pdo = db();

        if (isset($_SESSION['usuario_id'])) {
            $stmt = $pdo->prepare("
                SELECT valoracion
                FROM megusta_noticias
                WHERE id_noticia = ?
                AND id_usuario = ?
                AND tipo_usuario = 'registrado'
            ");
            $stmt->execute([$id_noticia, $_SESSION['usuario_id']]);
        } else {
            $session_id = self::getVisitorIdentifier();
            $stmt = $pdo->prepare("
                SELECT valoracion
                FROM megusta_noticias
                WHERE id_noticia = ?
                AND session_id = ?
                AND tipo_usuario = 'visitante'
                ORDER BY fecha_megusta DESC
                LIMIT 1
            ");
            $stmt->execute([$id_noticia, $session_id]);
        }

        $voto = $stmt->fetch();
        return $voto ? (int)$voto['valoracion'] : null;
    }

    /**
     * Obtener clasificación según la media
     */
    public static function getClasificacion($media) {
        if ($media <= 1.4) {
            return ['texto' => '❌ Mala noticia', 'clase' => 'mala'];
        } elseif ($media <= 2.0) {
            return ['texto' => '⚠️ No está mal', 'clase' => 'regular'];
        } else {
            return ['texto' => '✅ Buena noticia', 'clase' => 'buena'];
        }
    }
}
?>
