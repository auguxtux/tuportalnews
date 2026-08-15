<?php
declare(strict_types=1);

/**
 * Actualiza por CLI la caché RSS utilizada por la portada.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('SKIP_SESSION_START', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers/rss.php';

$directorioCache = ROOT_PATH
    . 'storage'
    . DIRECTORY_SEPARATOR
    . 'cache'
    . DIRECTORY_SEPARATOR
    . 'rss';

$fuentes = [
    'Fuerteventura Digital' => 'https://www.fuerteventuradigital.com/rss/',
    'Radio Sintonía' => 'https://radiosintonia.com/feed/',
];

$fallos = 0;

foreach ($fuentes as $nombre => $url) {
    $contenido = obtenerContenidoRSSConCache(
        $url,
        $directorioCache,
        0,
        true
    );

    if ($contenido === false) {
        $fallos++;
        fwrite(STDERR, '[ERROR] ' . $nombre . PHP_EOL);
        continue;
    }

    fwrite(STDOUT, '[OK] ' . $nombre . PHP_EOL);
}

exit($fallos === 0 ? 0 : 1);
