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
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/helpers/rss.php';

$directorioCache = ROOT_PATH
    . 'storage'
    . DIRECTORY_SEPARATOR
    . 'cache'
    . DIRECTORY_SEPARATOR
    . 'rss';

$fuentes = obtenerFuentesRssExternas(db());

$fallos = 0;

foreach ($fuentes as $fuente) {
    $nombre = $fuente['nombre'];
    $url = $fuente['url'];
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

    $noticias = procesarContenidoRSS(
        $contenido,
        (int) ($fuente['limite'] ?? 4)
    );
    $miniaturas = actualizarMiniaturasRss($noticias);

    fwrite(
        STDOUT,
        '[OK] ' . $nombre
        . ' · miniaturas: ' . $miniaturas['generadas']
        . ' · fallidas: ' . $miniaturas['fallidas']
        . PHP_EOL
    );
}

$stmtImagenes = db()->query(
    "SELECT DISTINCT imagen_externa
     FROM noticias
     WHERE estado = 'publicada'
       AND privada = 0
       AND imagen_externa IS NOT NULL
       AND imagen_externa != ''
     LIMIT 500"
);
$urlsNoticias = $stmtImagenes->fetchAll(PDO::FETCH_COLUMN);
$miniaturasNoticias = actualizarMiniaturasNoticiasExternas($urlsNoticias);

fwrite(
    STDOUT,
    '[OK] Noticias externas · variantes: '
    . $miniaturasNoticias['generadas']
    . ' · fallidas: ' . $miniaturasNoticias['fallidas']
    . PHP_EOL
);

$stmtLocales = db()->query(
    "SELECT DISTINCT imagen_principal
     FROM noticias
     WHERE estado = 'publicada'
       AND privada = 0
       AND imagen_principal IS NOT NULL
       AND imagen_principal != ''
     LIMIT 500"
);
$imagenesLocales = $stmtLocales->fetchAll(PDO::FETCH_COLUMN);
$localesGeneradas = 0;
$localesFallidas = 0;
foreach ($imagenesLocales as $archivo) {
    foreach ([320 => 180, 640 => 360] as $ancho => $alto) {
        if (generarMiniaturaNoticiaLocal((string) $archivo, $ancho, $alto)) {
            $localesGeneradas++;
        } else {
            $localesFallidas++;
        }
    }
}

fwrite(
    STDOUT,
    '[OK] Noticias locales · variantes: ' . $localesGeneradas
    . ' · fallidas: ' . $localesFallidas
    . PHP_EOL
);

exit($fallos === 0 ? 0 : 1);
