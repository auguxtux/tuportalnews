<?php
declare(strict_types=1);


/**
 * FUNCIONES PARA MÓDULO PRIVADO
 */

/**
 * Verificar si el usuario actual tiene acceso a contenido privado
 * @return bool
 */
function usuarioEsPrivado() {
    if (!estaLogueado()) return false;
    
    // Admin siempre tiene acceso
    if ($_SESSION['usuario_rol'] === 'admin') return true;
    
    // Verificar en tabla usuarios_privados
    static $cache = null;
    if ($cache === null) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT 1 FROM usuarios_privados WHERE id_usuario = ? AND activo = 1");
        $stmt->execute([$_SESSION['usuario_id']]);
        $cache = $stmt->fetch() ? true : false;
    }
    
    return $cache;
}

/**
 * Obtener la condición SQL utilizada por los listados públicos.
 * Las noticias privadas se consultan exclusivamente desde el módulo privado.
 *
 * @param string $alias Alias opcional de la tabla noticias
 * @return string
 */
function getCondicionNoticias(string $alias = ''): string {
    $prefijo = $alias !== '' ? $alias . '.' : '';

    return $prefijo . 'privada = 0';
}

/**
 * Retira el acceso de colaborador y conserva la cuenta como articulista.
 *
 * Elimina todas sus noticias privadas, las dependencias de esas noticias y
 * todos los comentarios escritos por el usuario. Sus noticias públicas se
 * conservan. La parte de base de datos es atómica.
 *
 * @return array{success:bool,message:string,noticias?:int,comentarios?:int,archivos_no_eliminados?:int}
 */
function reasignarColaboradorAArticulista(PDO $pdo, int $idUsuario): array
{
    if ($idUsuario <= 0) {
        return ['success' => false, 'message' => 'Colaborador no válido.'];
    }

    $stmt = $pdo->prepare(
        "SELECT u.id_usuario
         FROM usuarios u
         INNER JOIN usuarios_privados up ON up.id_usuario = u.id_usuario
         WHERE u.id_usuario = ? AND u.rol = 'periodista'"
    );
    $stmt->execute([$idUsuario]);

    if (!$stmt->fetchColumn()) {
        return ['success' => false, 'message' => 'El colaborador no existe o ya no tiene acceso privado.'];
    }

    $stmt = $pdo->prepare(
        "SELECT id_noticia, contenido, imagen_principal, imagen_2, imagen_3,
                imagen_4, imagen_5, imagen_6, video_nombre
         FROM noticias
         WHERE id_autor = ? AND privada = 1"
    );
    $stmt->execute([$idUsuario]);
    $noticiasPrivadas = $stmt->fetchAll();
    $totalNoticias = count($noticiasPrivadas);

    $archivos = [];
    foreach ($noticiasPrivadas as $noticia) {
        foreach (obtenerArchivosLocalesNoticia($noticia) as $archivo) {
            $archivos[$archivo['ruta']] = $archivo;
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comentarios WHERE id_usuario = ?');
    $stmt->execute([$idUsuario]);
    $totalComentarios = (int) $stmt->fetchColumn();

    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM comentarios WHERE id_usuario = ?')
            ->execute([$idUsuario]);
        $pdo->prepare('DELETE FROM noticias WHERE id_autor = ? AND privada = 1')
            ->execute([$idUsuario]);
        $pdo->prepare('DELETE FROM usuarios_privados WHERE id_usuario = ?')
            ->execute([$idUsuario]);
        $pdo->prepare("UPDATE usuarios SET rol = 'periodista' WHERE id_usuario = ?")
            ->execute([$idUsuario]);

        $pdo->prepare(
            "INSERT INTO notificaciones (id_usuario, tipo, mensaje, enlace)
             VALUES (?, 'sistema', ?, ?)"
        )->execute([
            $idUsuario,
            'Tu perfil ha sido reasignado a Articulista. Se han eliminado tus noticias privadas y tus comentarios; tus noticias públicas se conservan.',
            route('periodista_dashboard'),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        registrarErrorInterno('PRIVADO.COLABORADOR.REASIGNAR', $e);
        return ['success' => false, 'message' => 'No se pudo reasignar el colaborador.'];
    }

    $archivosNoEliminados = 0;
    foreach ($archivos as $archivo) {
        if ($archivo['tipo'] === 'editor') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE contenido LIKE ?');
            $stmt->execute(['%/uploads/editor/' . $archivo['nombre'] . '%']);
            if ((int) $stmt->fetchColumn() > 0) {
                continue;
            }
        }

        if (is_file($archivo['ruta']) && !unlink($archivo['ruta'])) {
            $archivosNoEliminados++;
        }
    }

    return [
        'success' => true,
        'message' => "Articulista reasignado: {$totalNoticias} noticias privadas y {$totalComentarios} comentarios eliminados; las noticias públicas se conservan.",
        'noticias' => $totalNoticias,
        'comentarios' => $totalComentarios,
        'archivos_no_eliminados' => $archivosNoEliminados,
    ];
}
