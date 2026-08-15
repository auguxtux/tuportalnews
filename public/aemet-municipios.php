<?php
declare(strict_types=1);

/**
 * Devuelve los municipios de una provincia desde la caché local de AEMET.
 */

define('SKIP_SESSION_START', true);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/helpers/aemet.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$provincia = isset($_GET['provincia'])
    ? trim((string) $_GET['provincia'])
    : '';

try {
    $provincias = obtenerMunicipiosEspanaAemet();

    if (
        $provincia === ''
        || !array_key_exists($provincia, $provincias)
        || !is_array($provincias[$provincia])
    ) {
        http_response_code(400);

        echo json_encode(
            [
                'ok' => false,
                'municipios' => [],
                'mensaje' => 'Provincia no válida.',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    header('Cache-Control: public, max-age=3600');

    echo json_encode(
        [
            'ok' => true,
            'municipios' => $provincias[$provincia],
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (Throwable $e) {
    if (!esLimitacionTemporalAemet($e)) {
        registrarErrorInterno('AEMET.MUNICIPIOS.ENDPOINT', $e);
    }

    http_response_code(503);

    echo json_encode(
        [
            'ok' => false,
            'municipios' => [],
            'mensaje' => 'No se pudieron cargar los municipios.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
}
