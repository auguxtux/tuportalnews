<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/modules/nasa.php';

header('Content-Type: application/json; charset=UTF-8');
iniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$ahora = time();
$solicitudes = array_values(array_filter(
    is_array($_SESSION['nasa_visor'] ?? null) ? $_SESSION['nasa_visor'] : [],
    static fn($momento): bool => is_int($momento) && $momento > $ahora - 60
));
if (count($solicitudes) >= 12) {
    http_response_code(429);
    echo json_encode(['error' => 'Espera un minuto antes de abrir más recursos.']);
    exit;
}
$_SESSION['nasa_visor'] = [...$solicitudes, $ahora];

$id = trim((string) ($_GET['id'] ?? ''));
$tipo = (string) ($_GET['tipo'] ?? '');
try {
    $recurso = obtenerRecursoVisorNasa($id, $tipo);
    if ($recurso === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Este recurso no está disponible para reproducción.']);
        exit;
    }
    echo json_encode($recurso, JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    registrarErrorInterno('NASA_VISOR', $error);
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo abrir el recurso de NASA.']);
}
