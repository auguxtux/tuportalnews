<?php
declare(strict_types=1);



/**
 * Funciones auxiliares para la lectura de fuentes RSS.
 *
 * Este archivo no genera salida HTML. Descarga, valida, almacena en caché
 * y transforma fuentes RSS en arrays reutilizables desde cualquier página.
 */

/**
 * Comprueba si una URL de imagen RSS es utilizable.
 */
function esImagenRssValida(string $url): bool
{
    if ($url === '') {
        return false;
    }

    $urlMinuscula = strtolower($url);
    $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return $esquema === 'https'
        && filter_var($url, FILTER_VALIDATE_URL) !== false
        && !str_contains($urlMinuscula, 'google.com')
        && !str_contains($urlMinuscula, 'favicon')
        && !str_starts_with($urlMinuscula, 'data:image');
}

/**
 * Extrae la primera imagen válida encontrada en un elemento RSS o Atom.
 */
function extraerImagenRSS(SimpleXMLElement $item): ?string
{
    // 1. Imagen definida mediante <enclosure>.
    if (isset($item->enclosure)) {
        $atributos = $item->enclosure->attributes();
        $url = isset($atributos['url']) ? trim((string) $atributos['url']) : '';

        if (esImagenRssValida($url)) {
            return $url;
        }
    }

    // 2. Extensión Media RSS: <media:content> o <media:thumbnail>.
    $media = $item->children('http://search.yahoo.com/mrss/');

    if (isset($media->content)) {
        $atributos = $media->content->attributes();
        $url = isset($atributos['url']) ? trim((string) $atributos['url']) : '';

        if (esImagenRssValida($url)) {
            return $url;
        }
    }

    if (isset($media->thumbnail)) {
        $atributos = $media->thumbnail->attributes();
        $url = isset($atributos['url']) ? trim((string) $atributos['url']) : '';

        if (esImagenRssValida($url)) {
            return $url;
        }
    }

    // 3. Primera imagen encontrada en content:encoded, description,
    // summary o content.
    $contenido = $item->children(
        'http://purl.org/rss/1.0/modules/content/'
    );

    $html = isset($contenido->encoded)
        ? (string) $contenido->encoded
        : (string) (
            $item->description
            ?? $item->summary
            ?? $item->content
            ?? ''
        );

    if (
        preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $coincidencias) === 1
        && isset($coincidencias[1])
    ) {
        $url = trim(
            html_entity_decode(
                $coincidencias[1],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        if (esImagenRssValida($url)) {
            return $url;
        }
    }

    return null;
}

/**
 * Recorta un texto RSS sin romper caracteres multibyte.
 */
function resumirTextoRSS(string $texto, int $longitud = 100): string
{
    $longitud = max(1, $longitud);

    $textoLimpio = trim(
        preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? ''
    );

    if ($textoLimpio === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($textoLimpio, 'UTF-8') <= $longitud) {
            return $textoLimpio;
        }

        return rtrim(
            mb_substr($textoLimpio, 0, $longitud, 'UTF-8')
        ) . '…';
    }

    if (strlen($textoLimpio) <= $longitud) {
        return $textoLimpio;
    }

    return rtrim(substr($textoLimpio, 0, $longitud)) . '…';
}

/**
 * Convierte una fecha RSS al formato indicado.
 */
function formatearFechaRSS(
    string $fecha,
    string $formato = 'd/m/Y'
): string {
    if ($fecha === '') {
        return '';
    }

    $timestamp = strtotime($fecha);

    return $timestamp === false ? '' : date($formato, $timestamp);
}

/**
 * Comprueba que una dirección IP pertenece a Internet pública.
 */
function esDireccionRssPublica(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

/**
 * Resuelve un host RSS y rechaza el destino si alguna IP no es pública.
 */
function resolverHostRssPublico(string $host): string|false
{
    $host = trim($host, '[]');

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return esDireccionRssPublica($host) ? $host : false;
    }

    $registros = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($registros) || $registros === []) {
        return false;
    }

    $ips = [];
    foreach ($registros as $registro) {
        $ip = $registro['ip'] ?? $registro['ipv6'] ?? null;
        if (is_string($ip)) {
            $ips[] = $ip;
        }
    }

    if ($ips === []) {
        return false;
    }

    foreach ($ips as $ip) {
        if (!esDireccionRssPublica($ip)) {
            return false;
        }
    }

    return $ips[0];
}

/**
 * Valida una URL RSS y devuelve el destino público fijado para cURL.
 */
function validarUrlRssExterna(string $url): array|false
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048) {
        return false;
    }

    $partes = parse_url($url);
    if (!is_array($partes)) {
        return false;
    }

    $esquema = strtolower((string) ($partes['scheme'] ?? ''));
    $host = trim((string) ($partes['host'] ?? ''), '[]');
    $puerto = isset($partes['port'])
        ? (int) $partes['port']
        : ($esquema === 'https' ? 443 : 80);

    if (
        !in_array($esquema, ['http', 'https'], true)
        || $host === ''
        || isset($partes['user'])
        || isset($partes['pass'])
        || $puerto < 1
        || $puerto > 65535
    ) {
        return false;
    }

    $ip = resolverHostRssPublico($host);
    if ($ip === false) {
        return false;
    }

    return [
        'url' => $url,
        'host' => $host,
        'port' => $puerto,
        'ip' => $ip,
    ];
}

/**
 * Convierte una cabecera Location relativa en una URL absoluta.
 */
function resolverRedireccionRss(string $urlBase, string $destino): string|false
{
    $destino = trim($destino);
    if ($destino === '') {
        return false;
    }

    if (parse_url($destino, PHP_URL_SCHEME) !== null) {
        return $destino;
    }

    $base = parse_url($urlBase);
    if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
        return false;
    }

    $autoridad = $base['scheme'] . '://' . $base['host'];
    if (isset($base['port'])) {
        $autoridad .= ':' . $base['port'];
    }

    if (str_starts_with($destino, '//')) {
        return $base['scheme'] . ':' . $destino;
    }

    if (str_starts_with($destino, '/')) {
        return $autoridad . $destino;
    }

    $rutaBase = (string) ($base['path'] ?? '/');
    $directorio = rtrim(str_replace('\\', '/', dirname($rutaBase)), '/');

    return $autoridad . ($directorio === '' ? '' : $directorio) . '/' . $destino;
}

/**
 * Descarga una URL RSS pública mediante cURL con límites de seguridad.
 *
 * @return string|false
 */
function descargarContenidoRSS(
    string $url,
    int $maxBytes = 2097152,
    string $accept = 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8'
): string|false
{
    if (!function_exists('curl_init')) {
        return false;
    }

    $maxRedirecciones = 3;
    $maxBytes = min(8 * 1024 * 1024, max(1024, $maxBytes));
    $urlActual = trim($url);

    for ($redireccion = 0; $redireccion <= $maxRedirecciones; $redireccion++) {
        $destino = validarUrlRssExterna($urlActual);
        if ($destino === false) {
            return false;
        }

        $contenido = '';
        $location = null;
        $limiteSuperado = false;
        $ipCurl = str_contains($destino['ip'], ':')
            ? '[' . $destino['ip'] . ']'
            : $destino['ip'];

        $curl = curl_init($destino['url']);
        if ($curl === false) {
            return false;
        }

        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'TuPortalNews RSS Reader/2.1 (+https://erun.es)',
            CURLOPT_RESOLVE => [
                $destino['host'] . ':' . $destino['port'] . ':' . $ipCurl,
            ],
            CURLOPT_HTTPHEADER => [
                'Accept: ' . $accept,
            ],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $cabecera) use (&$location): int {
                if (stripos($cabecera, 'Location:') === 0) {
                    $location = trim(substr($cabecera, 9));
                }
                return strlen($cabecera);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $datos) use (&$contenido, &$limiteSuperado, $maxBytes): int {
                if (strlen($contenido) + strlen($datos) > $maxBytes) {
                    $limiteSuperado = true;
                    return 0;
                }
                $contenido .= $datos;
                return strlen($datos);
            },
        ]);

        $resultado = curl_exec($curl);
        $codigoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($resultado === false || $limiteSuperado) {
            return false;
        }

        if ($codigoHttp >= 200 && $codigoHttp < 300) {
            return trim($contenido) !== '' ? $contenido : false;
        }

        if ($codigoHttp < 300 || $codigoHttp >= 400 || $location === null) {
            return false;
        }

        if ($redireccion === $maxRedirecciones) {
            return false;
        }

        $urlActual = resolverRedireccionRss($urlActual, $location);
        if ($urlActual === false) {
            return false;
        }
    }

    return false;
}

/**
 * Devuelve el directorio privado de miniaturas RSS.
 */
function directorioMiniaturasRss(): string
{
    return dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR
        . 'storage'
        . DIRECTORY_SEPARATOR
        . 'cache'
        . DIRECTORY_SEPARATOR
        . 'rss'
        . DIRECTORY_SEPARATOR
        . 'images';
}

function claveMiniaturaRss(
    string $url,
    int $ancho = 320,
    int $alto = 180
): string
{
    return hash('sha256', 'v2|' . $ancho . 'x' . $alto . '|' . $url);
}

function rutaMiniaturaRss(
    string $url,
    int $ancho = 320,
    int $alto = 180
): string
{
    return directorioMiniaturasRss()
        . DIRECTORY_SEPARATOR
        . 'rss_img_'
        . claveMiniaturaRss($url, $ancho, $alto)
        . '.webp';
}

/**
 * Devuelve la URL pública de una miniatura existente sin descargar recursos.
 */
function obtenerUrlMiniaturaRss(
    string $url,
    int $ancho = 320,
    int $alto = 180
): ?string
{
    if (!esImagenRssValida($url)) {
        return null;
    }

    $ruta = rutaMiniaturaRss($url, $ancho, $alto);
    if (!is_file($ruta) || !is_readable($ruta)) {
        return null;
    }

    $version = filemtime($ruta);

    return route('rss_image', [
        'id' => claveMiniaturaRss($url, $ancho, $alto),
        'v' => $version === false ? 1 : $version,
    ]);
}

/**
 * Devuelve una imagen externa optimizada y sus variantes disponibles.
 *
 * @return array{src: string, srcset: string}
 */
function obtenerImagenExternaOptimizadaRss(string $url): array
{
    $variantes = [];
    foreach ([320, 480, 640, 960] as $ancho) {
        $miniatura = obtenerUrlMiniaturaRss($url, $ancho, (int) ($ancho * 9 / 16));
        if ($miniatura !== null) {
            $variantes[] = $miniatura . ' ' . $ancho . 'w';
        }
    }

    return [
        'src' => obtenerUrlMiniaturaRss($url, 640, 360) ?? $url,
        'srcset' => implode(', ', $variantes),
    ];
}

/**
 * Devuelve la clave estable de una variante local sin exponer su nombre.
 */
function claveMiniaturaNoticiaLocal(
    string $archivo,
    int $ancho,
    int $alto
): string {
    $ruta = UPLOAD_NOTICIAS . $archivo;
    $version = is_file($ruta) ? (int) filemtime($ruta) : 0;

    return hash(
        'sha256',
        'local|' . $archivo . '|' . $version . '|' . $ancho . 'x' . $alto
    );
}

function rutaMiniaturaNoticiaLocal(
    string $archivo,
    int $ancho,
    int $alto
): string {
    return directorioMiniaturasRss()
        . DIRECTORY_SEPARATOR
        . 'rss_img_'
        . claveMiniaturaNoticiaLocal($archivo, $ancho, $alto)
        . '.webp';
}

/**
 * Genera una variante WebP de una imagen subida sin modificar el original.
 */
function generarMiniaturaNoticiaLocal(
    string $archivo,
    int $ancho,
    int $alto
): bool {
    if (
        $archivo === ''
        || basename($archivo) !== $archivo
        || !class_exists(Imagick::class)
        || !in_array($ancho, [320, 480, 640], true)
        || !in_array($alto, [180, 270, 360], true)
    ) {
        return false;
    }

    $origen = realpath(UPLOAD_NOTICIAS . $archivo);
    $directorioOrigen = realpath(UPLOAD_NOTICIAS);
    if (
        $origen === false
        || $directorioOrigen === false
        || dirname($origen) !== $directorioOrigen
        || !is_file($origen)
        || !is_readable($origen)
    ) {
        return false;
    }

    $directorio = directorioMiniaturasRss();
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
        return false;
    }

    $ruta = rutaMiniaturaNoticiaLocal($archivo, $ancho, $alto);
    if (is_file($ruta)) {
        return true;
    }

    $temporal = null;
    $imagen = null;
    try {
        $imagen = new Imagick($origen);
        $imagen->setIteratorIndex(0);
        $fotograma = $imagen->getImage();
        $imagen->clear();
        $imagen = $fotograma;
        if (method_exists($imagen, 'autoOrient')) {
            $imagen->autoOrient();
        } elseif (method_exists($imagen, 'autoOrientImage')) {
            $imagen->autoOrientImage();
        }
        $imagen->cropThumbnailImage($ancho, $alto);
        $imagen->stripImage();
        $imagen->setImageFormat('webp');
        $imagen->setOption('webp:method', '6');
        $imagen->setImageCompressionQuality(78);

        $temporal = tempnam($directorio, '.news-img-');
        if ($temporal === false || !$imagen->writeImage($temporal)) {
            return false;
        }
        if (!chmod($temporal, 0644) || !rename($temporal, $ruta)) {
            return false;
        }
        $temporal = null;
        return true;
    } catch (Throwable $e) {
        error_log('NEWS IMAGE ERROR: no se pudo generar una miniatura local.');
        return false;
    } finally {
        if ($imagen instanceof Imagick) {
            $imagen->clear();
        }
        if (is_string($temporal) && is_file($temporal)) {
            unlink($temporal);
        }
    }
}

/**
 * @return array{src: string, srcset: string}
 */
function obtenerImagenLocalOptimizadaRss(string $archivo): array
{
    if ($archivo === '' || basename($archivo) !== $archivo) {
        return ['src' => '', 'srcset' => ''];
    }

    $variantes = [];
    foreach ([320 => 180, 480 => 270, 640 => 360] as $ancho => $alto) {
        $ruta = rutaMiniaturaNoticiaLocal($archivo, $ancho, $alto);
        if (is_file($ruta) && is_readable($ruta)) {
            $version = filemtime($ruta) ?: 1;
            $url = route('rss_image', [
                'id' => claveMiniaturaNoticiaLocal($archivo, $ancho, $alto),
                'v' => $version,
            ]);
            $variantes[$ancho] = $url;
        }
    }

    return [
        'src' => $variantes[640] ?? base_url('uploads/noticias/' . $archivo),
        'srcset' => implode(', ', array_map(
            static fn (int $ancho, string $url): string => $url . ' ' . $ancho . 'w',
            array_keys($variantes),
            array_values($variantes)
        )),
    ];
}

/**
 * Genera una miniatura WebP 16:9 limitada y validada.
 */
function generarMiniaturaRss(
    string $url,
    int $duracionCache = 86400,
    int $ancho = 320,
    int $alto = 180,
    ?string $contenidoDescargado = null
): bool {
    if (
        !esImagenRssValida($url)
        || !class_exists(Imagick::class)
        || $ancho < 80
        || $alto < 45
        || $ancho > 1920
        || $alto > 1080
    ) {
        return false;
    }

    $directorio = directorioMiniaturasRss();
    if (
        !is_dir($directorio)
        && !mkdir($directorio, 0755, true)
        && !is_dir($directorio)
    ) {
        return false;
    }

    $ruta = rutaMiniaturaRss($url, $ancho, $alto);
    if (
        is_file($ruta)
        && (time() - (int) filemtime($ruta)) < max(0, $duracionCache)
    ) {
        return true;
    }

    $bloqueo = fopen($ruta . '.lock', 'c');
    if ($bloqueo === false || !flock($bloqueo, LOCK_EX)) {
        if (is_resource($bloqueo)) {
            fclose($bloqueo);
        }
        return false;
    }

    $temporal = null;
    $imagen = null;

    try {
        clearstatcache(true, $ruta);
        if (
            is_file($ruta)
            && (time() - (int) filemtime($ruta)) < max(0, $duracionCache)
        ) {
            return true;
        }

        $contenido = $contenidoDescargado ?? descargarContenidoRSS(
            $url,
            8 * 1024 * 1024,
            'image/webp,image/jpeg,image/png,image/*;q=0.8'
        );
        if ($contenido === false) {
            return false;
        }

        $info = @getimagesizefromstring($contenido);
        $mimePermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $anchoOriginal = is_array($info) ? (int) ($info[0] ?? 0) : 0;
        $altoOriginal = is_array($info) ? (int) ($info[1] ?? 0) : 0;
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';

        if (
            $anchoOriginal < 1
            || $altoOriginal < 1
            || $anchoOriginal > 12000
            || $altoOriginal > 12000
            || ($anchoOriginal * $altoOriginal) > 40000000
            || !in_array($mime, $mimePermitidos, true)
        ) {
            return false;
        }

        $imagen = new Imagick();
        $imagen->readImageBlob($contenido);
        $imagen->setIteratorIndex(0);
        $primerFotograma = $imagen->getImage();
        $imagen->clear();
        $imagen = $primerFotograma;
        if (method_exists($imagen, 'autoOrient')) {
            $imagen->autoOrient();
        } elseif (method_exists($imagen, 'autoOrientImage')) {
            $imagen->autoOrientImage();
        }
        $imagen->cropThumbnailImage($ancho, $alto);
        $imagen->stripImage();
        $imagen->setImageFormat('webp');
        $imagen->setOption('webp:method', '6');
        $imagen->setImageCompressionQuality(68);

        $temporal = tempnam($directorio, '.rss-img-');
        if ($temporal === false || !$imagen->writeImage($temporal)) {
            return false;
        }
        if (!chmod($temporal, 0644) || !rename($temporal, $ruta)) {
            return false;
        }
        $temporal = null;

        return true;
    } catch (Throwable $e) {
        error_log('RSS ERROR: no se pudo generar una miniatura externa.');
        return false;
    } finally {
        if ($imagen instanceof Imagick) {
            $imagen->clear();
        }
        if (is_string($temporal) && is_file($temporal)) {
            unlink($temporal);
        }
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
        if (is_file($ruta . '.lock')) {
            unlink($ruta . '.lock');
        }
    }
}

/**
 * Prepara variantes responsive para imágenes externas de noticias públicas.
 *
 * @param array<int, string> $urls
 * @return array{generadas: int, fallidas: int}
 */
function actualizarMiniaturasNoticiasExternas(array $urls): array
{
    $resultado = ['generadas' => 0, 'fallidas' => 0];

    foreach (array_unique($urls) as $url) {
        $url = trim((string) $url);
        if (!esImagenRssValida($url)) {
            continue;
        }

        $variantesPendientes = [];
        foreach ([320, 480, 640, 960] as $ancho) {
            $alto = (int) ($ancho * 9 / 16);
            $ruta = rutaMiniaturaRss($url, $ancho, $alto);
            if (
                !is_file($ruta)
                || (time() - (int) filemtime($ruta)) >= 86400
            ) {
                $variantesPendientes[$ancho] = $alto;
            } else {
                $resultado['generadas']++;
            }
        }

        if ($variantesPendientes === []) {
            continue;
        }

        $contenido = descargarContenidoRSS(
            $url,
            8 * 1024 * 1024,
            'image/webp,image/jpeg,image/png,image/*;q=0.8'
        );
        if ($contenido === false) {
            $resultado['fallidas'] += count($variantesPendientes);
            continue;
        }

        foreach ($variantesPendientes as $ancho => $alto) {
            if (generarMiniaturaRss($url, 86400, $ancho, $alto, $contenido)) {
                $resultado['generadas']++;
            } else {
                $resultado['fallidas']++;
            }
        }
    }

    return $resultado;
}

/**
 * @param array<int, array<string, mixed>> $noticias
 * @return array{generadas: int, fallidas: int}
 */
function actualizarMiniaturasRss(array $noticias): array
{
    $resultado = ['generadas' => 0, 'fallidas' => 0];

    foreach ($noticias as $noticia) {
        $url = trim((string) ($noticia['imagen_original'] ?? ''));
        if ($url === '') {
            continue;
        }

        if (generarMiniaturaRss($url)) {
            $resultado['generadas']++;
        } else {
            $resultado['fallidas']++;
        }
    }

    return $resultado;
}

/**
 * Obtiene un RSS mediante caché local.
 *
 * Si la descarga falla, reutiliza una copia antigua cuando exista.
 *
 * @return string|false
 */
function obtenerContenidoRSSConCache(
    string $url,
    string $directorioCache,
    int $duracionCache = 900,
    bool $permitirDescarga = true
): string|false {
    $duracionCache = max(0, $duracionCache);
    $directorioCache = rtrim($directorioCache, DIRECTORY_SEPARATOR);

    if (
        !is_dir($directorioCache)
        && !mkdir($directorioCache, 0755, true)
        && !is_dir($directorioCache)
    ) {
        error_log('RSS ERROR: no se pudo crear el directorio de caché.');

        return $permitirDescarga ? descargarContenidoRSS($url) : false;
    }

    $archivoCache = $directorioCache
        . DIRECTORY_SEPARATOR
        . 'rss_'
        . md5($url)
        . '.xml';

    if (
        is_file($archivoCache)
        && is_readable($archivoCache)
        && (time() - (int) filemtime($archivoCache)) < $duracionCache
    ) {
        $contenidoCache = file_get_contents($archivoCache);

        if ($contenidoCache !== false && trim($contenidoCache) !== '') {
            return $contenidoCache;
        }
    }

    if (!$permitirDescarga) {
        if (is_file($archivoCache) && is_readable($archivoCache)) {
            $contenidoCache = file_get_contents($archivoCache);

            if ($contenidoCache !== false && trim($contenidoCache) !== '') {
                return $contenidoCache;
            }
        }

        return false;
    }

    $contenidoNuevo = descargarContenidoRSS($url);

    if ($contenidoNuevo !== false) {
        if (
            file_put_contents(
                $archivoCache,
                $contenidoNuevo,
                LOCK_EX
            ) === false
        ) {
            error_log('RSS ERROR: no se pudo escribir la caché.');
        }

        return $contenidoNuevo;
    }

    // Respaldo: una caché antigua es preferible a mostrar el bloque vacío.
    if (is_file($archivoCache) && is_readable($archivoCache)) {
        $contenidoAntiguo = file_get_contents($archivoCache);

        if ($contenidoAntiguo !== false && trim($contenidoAntiguo) !== '') {
            return $contenidoAntiguo;
        }
    }

    return false;
}

/**
 * Convierte XML RSS o Atom en una colección de noticias.
 *
 * @return array<int, array{
 *     titulo: string,
 *     link: string,
 *     fecha: string,
 *     descripcion: string,
 *     imagen: string|null,
 *     imagen_original: string|null
 * }>
 */
function procesarContenidoRSS(
    string $contenidoXml,
    int $limite = 5,
    int $longitudDescripcion = 100,
    string $formatoFecha = 'd/m/Y'
): array {
    if ($limite < 1 || trim($contenidoXml) === '') {
        return [];
    }

    $estadoAnteriorLibxml = libxml_use_internal_errors(true);

    try {
        $rss = simplexml_load_string(
            $contenidoXml,
            SimpleXMLElement::class,
            LIBXML_NOCDATA | LIBXML_NONET
        );

        if ($rss === false) {
            error_log('RSS ERROR: no se pudo procesar el XML recibido.');

            return [];
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($estadoAnteriorLibxml);
    }

    if (isset($rss->channel->item)) {
        $elementos = $rss->channel->item;
    } elseif (isset($rss->entry)) {
        $elementos = $rss->entry;
    } else {
        error_log('RSS ERROR: formato RSS o Atom no reconocido');
        return [];
    }

    $noticias = [];

    foreach ($elementos as $item) {
        if (count($noticias) >= $limite) {
            break;
        }

        $titulo = trim((string) ($item->title ?? ''));

        $descripcionOriginal = (string) (
            $item->description
            ?? $item->summary
            ?? $item->content
            ?? ''
        );

        // RSS suele incluir el enlace como texto.
        $link = trim((string) ($item->link ?? ''));

        // Atom suele incluirlo en el atributo href.
        if ($link === '' && isset($item->link)) {
            $atributosEnlace = $item->link->attributes();
            $link = isset($atributosEnlace['href'])
                ? trim((string) $atributosEnlace['href'])
                : '';
        }

        $fechaOriginal = trim((string) (
            $item->pubDate
            ?? $item->published
            ?? $item->updated
            ?? ''
        ));

        if ($titulo === '') {
            $titulo = 'Sin título';
        }

        $partesLink = parse_url($link);
        $esquemaLink = is_array($partesLink)
            ? strtolower((string) ($partesLink['scheme'] ?? ''))
            : '';

        if (
            filter_var($link, FILTER_VALIDATE_URL) === false
            || !in_array($esquemaLink, ['http', 'https'], true)
            || isset($partesLink['user'])
            || isset($partesLink['pass'])
        ) {
            continue;
        }

        $imagenOriginal = extraerImagenRSS($item);
        $noticias[] = [
            'titulo' => $titulo,
            'link' => $link,
            'fecha' => formatearFechaRSS($fechaOriginal, $formatoFecha),
            'descripcion' => resumirTextoRSS(
                $descripcionOriginal,
                $longitudDescripcion
            ),
            'imagen' => $imagenOriginal === null
                ? null
                : (
                    obtenerUrlMiniaturaRss($imagenOriginal, 256, 144)
                    ?? obtenerUrlMiniaturaRss($imagenOriginal)
                    ?? $imagenOriginal
                ),
            'imagen_original' => $imagenOriginal,
        ];
    }

    return $noticias;
}

/**
 * Descarga y procesa una fuente RSS utilizando caché local.
 *
 * Se conserva esta firma por compatibilidad con portada.php.
 *
 * @return array<int, array{
 *     titulo: string,
 *     link: string,
 *     fecha: string,
 *     descripcion: string,
 *     imagen: string|null,
 *     imagen_original: string|null
 * }>
 */
function cargarFeedRSS(
    string $url,
    int $limite = 5,
    bool $permitirDescarga = true
): array
{
    $directorioCache = dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR
        . 'storage'
        . DIRECTORY_SEPARATOR
        . 'cache'
        . DIRECTORY_SEPARATOR
        . 'rss';

    $contenido = obtenerContenidoRSSConCache(
        $url,
        $directorioCache,
        900,
        $permitirDescarga
    );

    if ($contenido === false) {
        return [];
    }

    return procesarContenidoRSS(
        $contenido,
        $limite,
        100,
        'd/m/Y'
    );
}

/**
 * Devuelve los medios activos elegidos para los bloques RSS externos.
 * Los elementos de sus feeds no se importan ni se cruzan con noticias.
 *
 * @return array<int, array{
 *     nombre: string,
 *     url: string,
 *     color: string,
 *     icono: string,
 *     limite: int
 * }>
 */
function obtenerFuentesRssExternas(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT nombre, url FROM fuentes_rss '
        . 'WHERE activa = 1 AND mostrar_externas = 1 '
        . 'ORDER BY nombre ASC'
    );

    $colores = ['#2563eb', '#0f766e', '#b45309', '#7c3aed', '#be123c'];
    $fuentes = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $indice => $fuente) {
        $fuentes[] = [
            'nombre' => (string) $fuente['nombre'],
            'url' => (string) $fuente['url'],
            'color' => $colores[$indice % count($colores)],
            'icono' => '📰',
            'limite' => 4,
        ];
    }

    return $fuentes;
}

/**
 * Prepara inmediatamente la caché de un medio elegido para bloques externos.
 */
function actualizarCacheRssExterna(string $url): bool
{
    $directorioCache = dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR
        . 'storage'
        . DIRECTORY_SEPARATOR
        . 'cache'
        . DIRECTORY_SEPARATOR
        . 'rss';

    $contenido = obtenerContenidoRSSConCache(
        $url,
        $directorioCache,
        0,
        true
    );
    if ($contenido === false) {
        return false;
    }

    actualizarMiniaturasRss(procesarContenidoRSS($contenido, 4));

    return true;
}

/**
 * Procesa una fuente RSS usando caché.
 *
 * @return array{
 *     error: bool,
 *     titulo: string,
 *     noticias: array<int, array{
 *         titulo: string,
 *         link: string,
 *         fecha: string,
 *         descripcion: string,
 *         imagen: string|null
 *     }>
 * }
 */
function procesarFeedRSS(
    string $nombre,
    string $url,
    int $limite,
    string $directorioCache,
    int $duracionCache = 900,
    bool $permitirDescarga = true
): array {
    $contenido = obtenerContenidoRSSConCache(
        $url,
        $directorioCache,
        $duracionCache,
        $permitirDescarga
    );

    if ($contenido === false) {
        return [
            'error' => true,
            'titulo' => $nombre,
            'noticias' => [],
        ];
    }

    $noticias = procesarContenidoRSS(
        $contenido,
        $limite,
        120,
        'd/m/Y H:i'
    );

    return [
        'error' => false,
        'titulo' => $nombre,
        'noticias' => $noticias,
    ];
}

/**
 * Procesa una lista completa de fuentes RSS.
 *
 * @param array<int, array{
 *     nombre: string,
 *     url: string,
 *     color: string,
 *     icono: string,
 *     limite: int
 * }> $feeds
 *
 * @return array<int, array{
 *     config: array{
 *         nombre: string,
 *         url: string,
 *         color: string,
 *         icono: string,
 *         limite: int
 *     },
 *     datos: array{
 *         error: bool,
 *         titulo: string,
 *         noticias: array<int, array{
 *             titulo: string,
 *             link: string,
 *             fecha: string,
 *             descripcion: string,
 *             imagen: string|null
 *         }>
 *     }
 * }>
 */
function cargarFeedsRSS(
    array $feeds,
    string $directorioCache,
    int $duracionCache = 900,
    bool $permitirDescarga = true
): array {
    $resultados = [];

    foreach ($feeds as $feed) {
        $nombre = trim((string) ($feed['nombre'] ?? 'Fuente RSS'));
        $url = trim((string) ($feed['url'] ?? ''));
        $limite = max(1, (int) ($feed['limite'] ?? 5));

        $config = [
            'nombre' => $nombre,
            'url' => $url,
            'color' => (string) ($feed['color'] ?? '#2a5298'),
            'icono' => (string) ($feed['icono'] ?? '📰'),
            'limite' => $limite,
        ];

        $resultados[] = [
            'config' => $config,
            'datos' => procesarFeedRSS(
                $nombre,
                $url,
                $limite,
                $directorioCache,
                $duracionCache,
                $permitirDescarga
            ),
        ];
    }

    return $resultados;
}
