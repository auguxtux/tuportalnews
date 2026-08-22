<?php
declare(strict_types=1);

/**
 * Sirve miniaturas RSS previamente generadas en la caché privada.
 */

define('SKIP_SESSION_START', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers/rss.php';

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($metodo, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$id = (string) ($_GET['id'] ?? '');
if (preg_match('/^[a-f0-9]{64}$/D', $id) !== 1) {
    http_response_code(404);
    exit;
}

$directorio = realpath(directorioMiniaturasRss());
if ($directorio === false || !is_dir($directorio)) {
    http_response_code(404);
    exit;
}

$ruta = $directorio . DIRECTORY_SEPARATOR . 'rss_img_' . $id . '.webp';
$rutaReal = realpath($ruta);
if (
    $rutaReal === false
    || dirname($rutaReal) !== $directorio
    || !is_file($rutaReal)
    || !is_readable($rutaReal)
) {
    http_response_code(404);
    exit;
}

$tamano = filesize($rutaReal);
$modificado = filemtime($rutaReal);

header('Content-Type: image/webp');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=2592000, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
if ($tamano !== false) {
    header('Content-Length: ' . $tamano);
}
if ($modificado !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modificado) . ' GMT');
}

if ($metodo === 'HEAD') {
    exit;
}

readfile($rutaReal);
