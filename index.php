<?php
declare(strict_types=1);

/**
 * FRONT CONTROLLER
 *
 * Punto único de entrada de la aplicación.
 * Gestiona el mantenimiento, las rutas y los errores 404.
 */

// =====================================================
// CARGA PRINCIPAL
// =====================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/routes.php';

/*
 * Obtenemos únicamente la ruta, eliminando:
 *
 * - La cadena de consulta.
 * - La barra inicial.
 */
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = strtok($requestUri, '?');

$request = trim(
    $requestPath !== false ? $requestPath : '',
    '/'
);

// =====================================================
// 1. RUTA EXACTA
// =====================================================

$archivo = get_route_file($request);

if ($archivo !== null) {
    require __DIR__ . '/' . $archivo;
    exit;
}

// =====================================================
// 2. RUTA DINÁMICA
// =====================================================

$partes = $request === ''
    ? []
    : explode('/', $request);

$archivo = process_dynamic_route(
    $request,
    $partes
);

if ($archivo !== null) {
    require __DIR__ . '/' . $archivo;
    exit;
}

// =====================================================
// 3. ERROR 404
// =====================================================

http_response_code(404);

$errorFile = __DIR__ . '/public/404.php';

if (is_file($errorFile)) {
    require $errorFile;
} else {
    echo '<h1>404 - Página no encontrada</h1>';
}

exit;
