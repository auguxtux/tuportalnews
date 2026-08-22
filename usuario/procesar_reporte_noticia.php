<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/privado.php';

header('Content-Type: application/json; charset=UTF-8');
$respuesta = ['success' => false, 'mensaje' => ''];
$reportePrivado = defined('REPORTE_NOTICIA_PRIVADA') && REPORTE_NOTICIA_PRIVADA === true;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $respuesta['mensaje'] = 'Método no permitido';
    echo json_encode($respuesta);
    exit;
}

$auth = new Auth();
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

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    $respuesta['mensaje'] = 'Error de seguridad. Recarga la página.';
    echo json_encode($respuesta);
    exit;
}

$noticiaId = (int)($_POST['noticia_id'] ?? 0);
$datosReporte = normalizarDatosReporte($_POST);
$motivo = $datosReporte['motivo'];
$descripcion = $datosReporte['descripcion'];
$usuarioId = (int)$_SESSION['usuario_id'];

if ($noticiaId <= 0 || !motivoReporteValido($motivo)) {
    $respuesta['mensaje'] = 'Datos de reporte inválidos';
    echo json_encode($respuesta);
    exit;
}

if ($motivo === 'otro' && $descripcion === '') {
    $respuesta['mensaje'] = 'Debes especificar el motivo del reporte.';
    echo json_encode($respuesta);
    exit;
}

$pdo = db();
$noticia = obtenerNoticiaReportable($pdo, $noticiaId, $reportePrivado);

if ($noticia === false) {
    $respuesta['mensaje'] = 'Contenido no disponible';
    echo json_encode($respuesta);
    exit;
}

if ((int)$noticia['id_autor'] === $usuarioId) {
    $respuesta['mensaje'] = 'No puedes reportar tu propia noticia';
    echo json_encode($respuesta);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO reportes_noticias (noticia_id, usuario_id, ip, motivo, descripcion)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$noticiaId, $usuarioId, obtenerIP(), $motivo, $descripcion ?: null]);
    $respuesta['success'] = true;
    $respuesta['mensaje'] = 'Gracias por reportar. El equipo revisará la noticia.';
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        $respuesta['mensaje'] = 'Ya has reportado esta noticia';
    } else {
        registrarErrorInterno('USUARIO.REPORTE_NOTICIA', $e);
        $respuesta['mensaje'] = 'No se pudo procesar el reporte';
    }
}

echo json_encode($respuesta);
