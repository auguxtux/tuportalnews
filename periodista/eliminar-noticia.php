<?php
declare(strict_types=1);


/**
 * ELIMINAR NOTICIA
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirPeriodista();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mensajeFlash('error', 'Método no permitido');
    redireccionar(route('mis_noticias'));
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    mensajeFlash('error', 'Error de seguridad');
    redireccionar(route('mis_noticias'));
}

$id_noticia = isset($_POST['id_noticia']) ? (int) $_POST['id_noticia'] : 0;

if (!$id_noticia) {
    mensajeFlash('error', 'ID de noticia no válido');
    redireccionar(route('mis_noticias'));
}

$pdo = db();

$resultado = eliminarNoticiasCompletamente(
    $pdo,
    [$id_noticia],
    (int) ($_SESSION['usuario_id'] ?? 0),
    Permisos::esAdmin()
);

if ($resultado['success']) {
    $mensaje = $resultado['message'];
    if (($resultado['archivos_no_eliminados'] ?? 0) > 0) {
        $mensaje .= ' Algún archivo no pudo retirarse y requiere revisión.';
    }
    mensajeFlash('success', $mensaje);
} else {
    mensajeFlash('error', $resultado['message']);
}

redireccionar(route('mis_noticias'));
?>
