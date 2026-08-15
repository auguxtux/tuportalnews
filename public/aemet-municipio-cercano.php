<?php
declare(strict_types=1);

/**
 * Devuelve el municipio AEMET más próximo a las coordenadas del navegador.
 *
 * No almacena ni registra las coordenadas recibidas.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/helpers/aemet.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => 'Método no permitido.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

$longitudContenido = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($longitudContenido > 2048) {
    http_response_code(413);

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => 'Solicitud demasiado grande.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if (
    !permitirCalculoUbicacionAemetPorSesion()
    || !permitirCalculoUbicacionAemetPorIp()
) {
    http_response_code(429);
    header('Retry-After: 600');

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => 'Has realizado demasiadas solicitudes. Inténtalo más tarde.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

$entrada = file_get_contents('php://input');

if (!is_string($entrada) || $entrada === '') {
    http_response_code(400);

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => 'No se recibieron coordenadas.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    $datos = json_decode($entrada, true, 8, JSON_THROW_ON_ERROR);

    if (!is_array($datos)) {
        throw new InvalidArgumentException(
            'El contenido recibido no es válido.'
        );
    }

    $latitud = $datos['latitud'] ?? null;
    $longitud = $datos['longitud'] ?? null;

    if (!is_numeric($latitud) || !is_numeric($longitud)) {
        throw new InvalidArgumentException(
            'Las coordenadas recibidas no son válidas.'
        );
    }

    $latitud = (float) $latitud;
    $longitud = (float) $longitud;

    $municipio = obtenerMunicipioCercanoAemet(
        $latitud,
        $longitud
    );

    if ($municipio === null || $municipio['codigo'] === '') {
        throw new RuntimeException(
            'No se pudo determinar un municipio próximo.'
        );
    }

    echo json_encode(
        [
            'ok' => true,
            'municipio' => $municipio,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (InvalidArgumentException | JsonException $e) {
    http_response_code(400);

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => 'Las coordenadas recibidas no son válidas.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    registrarErrorInterno('AEMET.MUNICIPIO_CERCANO', $e);

    http_response_code(503);

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => 'No se pudo localizar el municipio en este momento.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
}
