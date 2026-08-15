<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/modules/nasa.php';

header('Content-Type: application/json; charset=UTF-8');
iniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}
if (!Permisos::puedeAccederPeriodista()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}
if (!verificarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'Error de seguridad.']);
    exit;
}

$id = trim((string) ($_POST['id'] ?? ''));
try {
    $video = obtenerVideoCatalogoNasa($id);
    if ($video === null) {
        http_response_code(404);
        echo json_encode(['error' => 'El vídeo no está disponible en formato compatible.']);
        exit;
    }
    echo json_encode($video, JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    registrarErrorInterno('NASA_VIDEO', $error);
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo obtener el vídeo de NASA.']);
}
