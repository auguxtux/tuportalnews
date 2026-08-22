<?php
declare(strict_types=1);


/**
 * Procesa el reporte de un comentario
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/privado.php';

header('Content-Type: application/json; charset=UTF-8');

$auth = new Auth();
$respuesta = ['success' => false, 'mensaje' => ''];
$reportePrivado = defined('REPORTE_COMENTARIO_PRIVADO') && REPORTE_COMENTARIO_PRIVADO === true;

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $respuesta['mensaje'] = 'Método no permitido';
    echo json_encode($respuesta);
    exit;
}

// Verificar token CSRF
if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    $respuesta['mensaje'] = 'Error de seguridad. Recarga la página.';
    echo json_encode($respuesta);
    exit;
}

if ($auth->getCurrentUser() === null) {
    http_response_code(403);
    $respuesta['mensaje'] = 'Debes iniciar sesión para reportar contenido';
    echo json_encode($respuesta);
    exit;
}

if ($reportePrivado && !usuarioEsPrivado()) {
    http_response_code(404);
    $respuesta['mensaje'] = 'Contenido no disponible';
    echo json_encode($respuesta);
    exit;
}

// Obtener datos
$comentario_id = intval($_POST['comentario_id'] ?? 0);
$datosReporte = normalizarDatosReporte($_POST);
$motivo = $datosReporte['motivo'];
$descripcion = $datosReporte['descripcion'];
$ip = obtenerIP();

// Validaciones
if ($comentario_id <= 0) {
    $respuesta['mensaje'] = 'Comentario inválido';
    echo json_encode($respuesta);
    exit;
}

if (!motivoReporteValido($motivo)) {
    $respuesta['mensaje'] = 'Motivo de reporte inválido';
    echo json_encode($respuesta);
    exit;
}

if ($motivo === 'otro' && $descripcion === '') {
    $respuesta['mensaje'] = 'Debes especificar el motivo del reporte.';
    echo json_encode($respuesta);
    exit;
}

// Verificar que el comentario existe
$pdo = db();
$comentario = obtenerComentarioReportable($pdo, $comentario_id, $reportePrivado);
if (!$comentario) {
    $respuesta['mensaje'] = 'Contenido no disponible';
    echo json_encode($respuesta);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
if ((int)$comentario['id_usuario'] === $usuario_id) {
    $respuesta['mensaje'] = 'No puedes reportar tu propio comentario';
    echo json_encode($respuesta);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO reportes_comentarios
                           (comentario_id, usuario_id, ip, motivo, descripcion, estado)
                           VALUES (?, ?, ?, ?, ?, 'pendiente')");
    $stmt->execute([$comentario_id, $usuario_id, $ip, $motivo, $descripcion ?: null]);
    $pdo->prepare("UPDATE comentarios
                   SET reportes_total = (SELECT COUNT(*) FROM reportes_comentarios WHERE comentario_id = ?)
                   WHERE id_comentario = ?")
        ->execute([$comentario_id, $comentario_id]);
    $pdo->commit();
    $respuesta['success'] = true;
    $respuesta['mensaje'] = 'Gracias por reportar. El equipo revisará este comentario.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string)$e->getCode() === '23000') {
        $respuesta['mensaje'] = 'Ya has reportado este comentario';
    } else {
        registrarErrorInterno('USUARIO.REPORTE_COMENTARIO', $e);
        $respuesta['mensaje'] = 'No se pudo procesar el reporte';
    }
}

echo json_encode($respuesta);
exit;
