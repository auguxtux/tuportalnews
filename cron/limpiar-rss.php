<?php
declare(strict_types=1);

/**
 * Elimina cachés RSS con más de tres días de antigüedad.
 *
 * Este archivo está destinado exclusivamente a la tarea programada de
 * desarrollo y nunca procesa rutas recibidas desde el exterior.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * @return array{eliminados: int, errores: int}
 */
function limpiarCacheRssAntigua(
    string $directorio,
    int $antiguedadSegundos = 259200
): array {
    $resultado = ['eliminados' => 0, 'errores' => 0];
    $directorioReal = realpath($directorio);

    if ($directorioReal === false || !is_dir($directorioReal)) {
        $resultado['errores']++;
        return $resultado;
    }

    $limite = time() - max(0, $antiguedadSegundos);
    $archivos = glob($directorioReal . DIRECTORY_SEPARATOR . 'rss_*.xml');

    if ($archivos === false) {
        $resultado['errores']++;
        return $resultado;
    }

    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);

        if (preg_match('/^rss_[a-f0-9]{32}\.xml$/D', $nombre) !== 1) {
            continue;
        }

        $rutaReal = realpath($archivo);
        if (
            $rutaReal === false
            || dirname($rutaReal) !== $directorioReal
            || !is_file($rutaReal)
        ) {
            continue;
        }

        $fechaModificacion = filemtime($rutaReal);
        if ($fechaModificacion === false || $fechaModificacion >= $limite) {
            continue;
        }

        if (unlink($rutaReal)) {
            $resultado['eliminados']++;
        } else {
            $resultado['errores']++;
        }
    }

    return $resultado;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $directorioCache = dirname(__DIR__)
        . DIRECTORY_SEPARATOR
        . 'storage'
        . DIRECTORY_SEPARATOR
        . 'cache'
        . DIRECTORY_SEPARATOR
        . 'rss';

    $resultado = limpiarCacheRssAntigua($directorioCache);

    if ($resultado['errores'] > 0) {
        fwrite(
            STDERR,
            'No se pudo completar la limpieza de la caché RSS.' . PHP_EOL
        );
        exit(1);
    }

    exit(0);
}
