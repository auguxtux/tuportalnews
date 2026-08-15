<?php
declare(strict_types=1);


/**
 * ELIMINAR COMENTARIO
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mensajeFlash('error', 'Método no permitido');
    redireccionar(route('mis_comentarios'));
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    mensajeFlash('error', 'Error de seguridad');
    redireccionar(route('mis_comentarios'));
}

$id_comentario = isset($_POST['id_comentario']) ? (int) $_POST['id_comentario'] : 0;

if (!$id_comentario) {
    mensajeFlash('error', 'Comentario no válido');
    redireccionar(route('mis_comentarios'));
}

$pdo = db();

try {
    // Obtener datos del comentario para verificar propiedad
    $stmt = $pdo->prepare("SELECT id_noticia, id_usuario FROM comentarios WHERE id_comentario = :id");
    $stmt->execute([':id' => $id_comentario]);
    $comentario = $stmt->fetch();
    
    if (!$comentario) {
        mensajeFlash('error', 'Comentario no encontrado');
        redireccionar(route('mis_comentarios'));
    }
    
    // Verificar propiedad (admin o propio usuario)
    if (!Permisos::puedeEditarComentario($comentario['id_usuario'])) {
        mensajeFlash('error', 'No tienes permiso para eliminar este comentario');
        redireccionar(route('mis_comentarios'));
    }
    
    // Eliminar comentario
    $stmt = $pdo->prepare("DELETE FROM comentarios WHERE id_comentario = :id");
    
    if ($stmt->execute([':id' => $id_comentario])) {
        mensajeFlash('success', 'Comentario eliminado correctamente');
    } else {
        mensajeFlash('error', 'Error al eliminar el comentario');
    }
    
    // Redirigir a la noticia donde estaba el comentario
    redireccionar(route('noticia', ['id' => (int) $comentario['id_noticia']]));
    
} catch (Exception $e) {
    registrarErrorInterno('USUARIO.COMENTARIO.ELIMINAR', $e);
    mensajeFlash('error', 'Error al procesar la solicitud');
    redireccionar(route('mis_comentarios'));
}
?>
