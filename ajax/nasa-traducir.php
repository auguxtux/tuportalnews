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
if (!verificarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'Error de seguridad.']);
    exit;
}

$ahora = time();
$solicitudes = array_values(array_filter(
    is_array($_SESSION['nasa_traducciones'] ?? null) ? $_SESSION['nasa_traducciones'] : [],
    static fn($momento): bool => is_int($momento) && $momento > $ahora - 60
));
if (count($solicitudes) >= 4) {
    http_response_code(429);
    echo json_encode(['error' => 'Espera un minuto antes de solicitar otra traducción.']);
    exit;
}
$_SESSION['nasa_traducciones'] = [...$solicitudes, $ahora];

$id = trim((string) ($_POST['id'] ?? ''));
$accion = (string) ($_POST['accion'] ?? 'descripcion');
$esTarjeta = $accion === 'tarjeta';
if (!$esTarjeta && !Permisos::puedeAccederPeriodista()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}
$parrafos = filter_input(INPUT_POST, 'parrafos', FILTER_VALIDATE_INT);
if (!$esTarjeta && (!is_int($parrafos) || $parrafos < 1 || $parrafos > NASA_TRADUCCION_MAX_PARRAFOS)) {
    http_response_code(422);
    echo json_encode(['error' => 'Selecciona entre 1 y 5 párrafos.']);
    exit;
}

try {
    $resultado = $esTarjeta
        ? traducirTarjetaNasa($id)
        : traducirDescripcionNasa($id, $parrafos);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    registrarErrorInterno('NASA_TRADUCCION', $error);
    http_response_code(502);
    echo json_encode([
        'error' => 'No se pudo traducir la descripción. El contenido original no se ha modificado.',
    ], JSON_UNESCAPED_UNICODE);
}
