<?php
declare(strict_types=1);


/**
 * CONFIGURACIÓN RSS - SimplePie
 * Para importar feeds externos (solo URL externa, no descarga)
 * 
 * ESTRATEGIA: Extracto + botón externo + imagen obligatoria
 * 
 * @version 2.2 - Revisado y optimizado
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/rss.php';

use SimplePie\SimplePie;

// ============================================
// RATE LIMITING (archivos temporales)
// ============================================

define('RSS_RATE_DIR', sys_get_temp_dir() . '/rss_rate_limit');
define('RSS_RATE_MAX_VALIDACIONES', 5);    // máx validaciones de fuente
define('RSS_RATE_MAX_PREVIEWS', 10);       // máx previsualizaciones
define('RSS_RATE_VENTANA', 60);            // ventana de 60 segundos

function verificarRateLimitRss(string $clave, int $maximo, int $ventanaSegundos = RSS_RATE_VENTANA): bool
{
    if (!is_dir(RSS_RATE_DIR)) {
        @mkdir(RSS_RATE_DIR, 0700, true);
    }

    $archivo = RSS_RATE_DIR . '/rss_' . md5($clave) . '.json';
    $ahora = time();

    $fp = @fopen($archivo, 'c');
    if ($fp === false) {
        return true;
    }

    flock($fp, LOCK_EX);

    $contenido = @stream_get_contents($fp);
    $datos = json_decode($contenido, true);

    if (is_array($datos) && isset($datos['intentos'])) {
        $datos['intentos'] = array_values(array_filter(
            $datos['intentos'],
            fn($t) => ($ahora - $t) < $ventanaSegundos
        ));
    } else {
        $datos = ['intentos' => []];
    }

    if (count($datos['intentos']) >= $maximo) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    $datos['intentos'][] = $ahora;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($datos));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

// ============================================
// CONSTANTES Y CONFIGURACIÓN
// ============================================

define('RSS_CACHE_DIR', __DIR__ . '/feeds/');
define('RSS_EXTRACTO_LONGITUD', 1000); // Caracteres del extracto

// ============================================
// FUNCIONES DE UTILIDAD (NULL SAFE)
// ============================================

/**
 * Versión segura de strip_tags que acepta null
 */
function strip_tags_safe($string, $allowable_tags = null) {
    if ($string === null || $string === '') {
        return '';
    }
    if ($allowable_tags === null) {
        return strip_tags($string);
    }
    return strip_tags($string, $allowable_tags);
}

/**
 * Limpia texto de forma segura para extracto
 */
function limpiarTextoSeguro($texto) {
    if ($texto === null) {
        return '';
    }
    return trim(html_entity_decode(strip_tags_safe($texto), ENT_QUOTES, 'UTF-8'));
}

/**
 * Genera slug a partir de un título (null safe)
 */
function generarSlugSeguro($titulo) {
    if (empty($titulo)) {
        return 'noticia-' . time();
    }
    // Asumiendo que tienes una función generarSlug() en tu proyecto
    if (function_exists('generarSlug')) {
        return generarSlug($titulo);
    }
    // Fallback simple
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($titulo)));
    return trim($slug, '-');
}

// ============================================
// FUNCIONES PRINCIPALES RSS
// ============================================

/**
 * Obtiene y procesa un feed RSS
 */
function obtenerFeed($urls, $cache_minutos = 60) {
    try {
        if (!is_string($urls) || trim($urls) === '') {
            throw new Exception("URL del feed vacía");
        }

        if (!is_dir(RSS_CACHE_DIR)) {
            @mkdir(RSS_CACHE_DIR, 0750, true);
        }

        $contenido = descargarContenidoRSS($urls);
        if ($contenido === false) {
            throw new Exception("No se pudo descargar el feed de forma segura");
        }

        $feed = new SimplePie();
        $feed->set_raw_data($contenido);
        $feed->set_cache_location(RSS_CACHE_DIR);
        $feed->set_cache_duration($cache_minutos * 60);
        $feed->set_item_limit(50);
        $feed->enable_order_by_date(true);
        $feed->set_favicon_handler(false);
        $feed->init();
        
        if ($feed->error()) {
            throw new Exception("Error en feed: " . $feed->error());
        }
        
        $feed->handle_content_type();
        return $feed;
        
    } catch (Exception $e) {
        registrarErrorInterno('RSS.FEED.OBTENER', $e);
        return null;
    }
}

/**
 * Descarta píxeles de seguimiento y valida una imagen RSS externa.
 */
function esImagenItemRssUtilizable(
    string $url,
    ?int $ancho = null,
    ?int $alto = null
): bool {
    $url = trim($url);
    $normalizada = normalizarUrlItemRss($url);
    if (
        $url === ''
        || $normalizada === null
        || strtolower((string) parse_url($normalizada, PHP_URL_SCHEME)) !== 'https'
    ) {
        return false;
    }

    if (($ancho !== null && $ancho > 0 && $ancho <= 2)
        || ($alto !== null && $alto > 0 && $alto <= 2)) {
        return false;
    }

    $urlMinuscula = strtolower($url);
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $dominiosMedicion = [
        'imrworldwide.com',
        'doubleclick.net',
        'google-analytics.com',
        'scorecardresearch.com',
    ];

    foreach ($dominiosMedicion as $dominio) {
        if ($host === $dominio || str_ends_with($host, '.' . $dominio)) {
            return false;
        }
    }

    foreach (['/pixel', '1x1.', 'spacer.gif', 'blank.gif'] as $patron) {
        if (str_contains($urlMinuscula, $patron)) {
            return false;
        }
    }

    return true;
}

/**
 * Extraer imagen de un item RSS desde múltiples fuentes
 * @return string|null URL de la imagen o null
 */
function extraerImagenItem($item) {
    if ($item === null) {
        return null;
    }
    
    // 1. Enclosures: priorizar archivos editoriales con dimensiones reales.
    $enclosures = method_exists($item, 'get_enclosures')
        ? $item->get_enclosures()
        : [];

    if (empty($enclosures)) {
        $enclosure = $item->get_enclosure();
        $enclosures = $enclosure ? [$enclosure] : [];
    }

    foreach ($enclosures as $enclosure) {
        $link = trim((string) $enclosure->get_link());
        $tipo = strtolower((string) $enclosure->get_type());
        $medio = method_exists($enclosure, 'get_medium')
            ? strtolower((string) $enclosure->get_medium())
            : '';
        $ancho = method_exists($enclosure, 'get_width')
            ? (int) $enclosure->get_width()
            : 0;
        $alto = method_exists($enclosure, 'get_height')
            ? (int) $enclosure->get_height()
            : 0;
        $pareceImagen = $medio === 'image'
            || str_starts_with($tipo, 'image/')
            || ($ancho > 2 && $alto > 2);

        if ($pareceImagen && esImagenItemRssUtilizable($link, $ancho, $alto)) {
            return $link;
        }
    }

    // 2. media:content (Yahoo Media RSS)
    $media_content = $item->get_item_tags('http://search.yahoo.com/mrss/', 'content');

    foreach ($media_content ?: [] as $media) {
        $atributos = $media['attribs']['']
            ?? $media['attributes']
            ?? [];
        $url = trim((string) ($atributos['url'] ?? ''));
        $tipo = strtolower((string) ($atributos['type'] ?? ''));
        $medio = strtolower((string) ($atributos['medium'] ?? ''));
        $ancho = isset($atributos['width']) ? (int) $atributos['width'] : 0;
        $alto = isset($atributos['height']) ? (int) $atributos['height'] : 0;
        $pareceImagen = $medio === 'image'
            || str_starts_with($tipo, 'image/')
            || ($ancho > 2 && $alto > 2);

        if ($pareceImagen && esImagenItemRssUtilizable($url, $ancho, $alto)) {
            return $url;
        }
    }

    // 3. media:thumbnail
    $thumbnail = $item->get_item_tags('http://search.yahoo.com/mrss/', 'thumbnail');

    foreach ($thumbnail ?: [] as $media) {
        $atributos = $media['attribs']['']
            ?? $media['attributes']
            ?? [];
        $url = trim((string) ($atributos['url'] ?? ''));
        $ancho = isset($atributos['width']) ? (int) $atributos['width'] : 0;
        $alto = isset($atributos['height']) ? (int) $atributos['height'] : 0;

        if (esImagenItemRssUtilizable($url, $ancho, $alto)) {
            return $url;
        }
    }

    // 4. Buscar en el contenido (último recurso)
    $contenido = (string) ($item->get_content() ?? '');
    if (preg_match_all(
        '/<img[^>]+src=["\']([^"\']+)["\']/i',
        $contenido,
        $coincidencias
    )) {
        foreach ($coincidencias[1] as $candidata) {
            $url = trim(html_entity_decode(
                (string) $candidata,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            if (esImagenItemRssUtilizable($url)) {
                return $url;
            }
        }
    }

    $descripcion = (string) ($item->get_description() ?? '');
    if (preg_match_all(
        '/<img[^>]+src=["\']([^"\']+)["\']/i',
        $descripcion,
        $coincidencias
    )) {
        foreach ($coincidencias[1] as $candidata) {
            $url = trim(html_entity_decode(
                (string) $candidata,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            if (esImagenItemRssUtilizable($url)) {
                return $url;
            }
        }
    }

    return null;
}

/**
 * Extrae una URL de vídeo compatible desde un elemento RSS.
 *
 * @return string|null URL del vídeo o null si el elemento no contiene uno
 */
function extraerVideoItem($item) {
    if ($item === null) {
        return null;
    }

    $enclosures = method_exists($item, 'get_enclosures')
        ? $item->get_enclosures()
        : [];

    if (empty($enclosures)) {
        $enclosure = $item->get_enclosure();
        $enclosures = $enclosure ? [$enclosure] : [];
    }

    foreach ($enclosures as $enclosure) {
        $tipo = strtolower((string) $enclosure->get_type());
        $medio = method_exists($enclosure, 'get_medium')
            ? strtolower((string) $enclosure->get_medium())
            : '';
        $url = trim((string) $enclosure->get_link());

        if (
            ($medio === 'video' || str_starts_with($tipo, 'video/'))
            && normalizarUrlMultimediaRss($url) !== null
        ) {
            return $url;
        }
    }

    $mediaContent = $item->get_item_tags(
        'http://search.yahoo.com/mrss/',
        'content'
    );

    foreach ($mediaContent ?: [] as $media) {
        $atributos = $media['attribs']['']
            ?? $media['attributes']
            ?? [];
        $url = trim((string) ($atributos['url'] ?? ''));
        $tipo = strtolower((string) ($atributos['type'] ?? ''));
        $medio = strtolower((string) ($atributos['medium'] ?? ''));

        if (
            ($medio === 'video' || str_starts_with($tipo, 'video/'))
            && normalizarUrlMultimediaRss($url) !== null
        ) {
            return $url;
        }
    }

    $contenido = (string) ($item->get_content() ?? '');
    if (
        preg_match(
            '/<(?:video|source|iframe)[^>]+src=["\']([^"\']+)["\']/i',
            $contenido,
            $coincidencias
        ) === 1
    ) {
        $url = trim(html_entity_decode(
            $coincidencias[1],
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (normalizarUrlMultimediaRss($url) !== null) {
            return $url;
        }
    }

    return null;
}

/**
 * Indica si un elemento RSS contiene una imagen o un vídeo compatible.
 */
function itemTieneMultimediaRss($item): bool {
    return extraerImagenItem($item) !== null
        || extraerVideoItem($item) !== null;
}

/**
 * Acepta únicamente multimedia RSS HTTPS para evitar contenido mixto y el
 * envío de navegación a servidores sin cifrado.
 */
function normalizarUrlMultimediaRss(string $url): ?string {
    $normalizada = normalizarUrlItemRss($url);
    if (
        $normalizada === null
        || strtolower((string) parse_url($normalizada, PHP_URL_SCHEME)) !== 'https'
    ) {
        return null;
    }

    return $normalizada;
}

/**
 * Cuenta los elementos con imagen o vídeo entre los primeros del feed.
 */
function contarItemsRssConMultimedia($feed, int $limite = 50): int {
    if ($feed === null) {
        return 0;
    }

    $total = 0;
    foreach ($feed->get_items(0, max(1, $limite)) as $item) {
        if (itemTieneMultimediaRss($item)) {
            $total++;
        }
    }

    return $total;
}

/**
 * Valida el nombre y la URL de una fuente RSS antes de almacenarla.
 *
 * @return array{
 *     datos: array{nombre: string, url: string}|null,
 *     errores: array<int, string>
 * }
 */
function validarConfiguracionFuenteRss(string $nombre, string $url): array {
    $errores = [];

    $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($idUsuario > 0 && !verificarRateLimitRss("validar_{$idUsuario}", RSS_RATE_MAX_VALIDACIONES)) {
        $errores[] = 'Has realizado demasiadas validaciones recientemente. Espera un minuto.';
        return [
            'datos' => null,
            'errores' => $errores,
        ];
    }

    $nombre = trim(strip_tags($nombre));
    $url = trim($url);
    $longitudNombre = function_exists('mb_strlen')
        ? mb_strlen($nombre, 'UTF-8')
        : strlen($nombre);

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio';
    } elseif ($longitudNombre > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres';
    }

    if (
        $url === ''
        || strlen($url) > 500
        || filter_var($url, FILTER_VALIDATE_URL) === false
        || validarUrlRssExterna($url) === false
    ) {
        $errores[] = 'La URL RSS no es válida o no es un destino público permitido';
    } else {
        $feed = obtenerFeed($url, 5);

        if ($feed === null) {
            $errores[] = 'No se pudo leer un feed RSS válido desde la URL indicada';
        } elseif (contarItemsRssConMultimedia($feed, 50) === 0) {
            $errores[] = 'El feed no contiene noticias con imágenes o vídeos compatibles';
        }
    }

    return [
        'datos' => $errores === []
            ? ['nombre' => $nombre, 'url' => $url]
            : null,
        'errores' => $errores,
    ];
}

/**
 * Normaliza una URL HTTP(S) de noticia para identificarla de forma estable.
 */
function normalizarUrlItemRss(string $url): ?string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $partes = parse_url($url);

    if (!is_array($partes)) {
        return null;
    }

    $esquema = strtolower((string) ($partes['scheme'] ?? ''));
    $host = strtolower((string) ($partes['host'] ?? ''));
    if (!in_array($esquema, ['http', 'https'], true) || $host === '') {
        return null;
    }

    $normalizada = $esquema . '://' . $host;
    if (isset($partes['port'])) {
        $puerto = (int) $partes['port'];
        if (!(($esquema === 'http' && $puerto === 80)
            || ($esquema === 'https' && $puerto === 443))) {
            $normalizada .= ':' . $puerto;
        }
    }

    $normalizada .= (string) ($partes['path'] ?? '/');
    if (isset($partes['query']) && $partes['query'] !== '') {
        $normalizada .= '?' . $partes['query'];
    }

    return $normalizada;
}

/**
 * Genera el identificador global utilizado para impedir duplicados RSS.
 */
function generarHashItemRss($item): ?string {
    if ($item === null) {
        return null;
    }

    $enlace = normalizarUrlItemRss((string) $item->get_permalink());
    if ($enlace !== null) {
        return hash('sha256', 'url:' . $enlace);
    }

    $guid = trim((string) $item->get_id());
    return $guid !== '' ? hash('sha256', 'guid:' . $guid) : null;
}

/**
 * Transforma un elemento del feed en datos seguros para vista previa/importación.
 *
 * @return array<string, string|null>|null
 */
function prepararItemRss($item): ?array {
    $hash = generarHashItemRss($item);
    $titulo = obtenerTituloItem($item);
    $enlace = normalizarUrlItemRss((string) $item->get_permalink());
    $imagen = extraerImagenItem($item);
    $video = extraerVideoItem($item);

    if (function_exists('mb_substr')) {
        $titulo = mb_substr($titulo, 0, 255, 'UTF-8');
    } else {
        $titulo = substr($titulo, 0, 255);
    }

    if (
        $hash === null
        || $titulo === ''
        || $enlace === null
        || strlen($enlace) > 255
        || ($imagen === null && $video === null)
    ) {
        return null;
    }

    if ($imagen !== null && normalizarUrlItemRss($imagen) === null) {
        $imagen = null;
    }
    if ($imagen !== null && strlen($imagen) > 500) {
        $imagen = null;
    }
    if ($video !== null && normalizarUrlItemRss($video) === null) {
        $video = null;
    }
    if ($imagen === null && $video === null) {
        return null;
    }

    $extracto = obtenerExtracto($item);

    return [
        'hash' => $hash,
        'titulo' => $titulo,
        'enlace' => $enlace,
        'fecha' => obtenerFechaItem($item),
        'extracto' => $extracto,
        'imagen' => $imagen,
        'video' => $video,
        'contenido' => generarContenidoConBoton($item, $extracto, $video),
    ];
}

/**
 * Obtiene los elementos seleccionables de un feed, indexados por su hash.
 *
 * @return array<string, array<string, string|null>>
 */
function obtenerItemsSeleccionablesRss($feed, int $limite = 50): array {
    if ($feed === null) {
        return [];
    }

    $items = [];
    foreach ($feed->get_items(0, max(1, min(100, $limite))) as $item) {
        $preparado = prepararItemRss($item);
        if ($preparado !== null) {
            $items[$preparado['hash']] = $preparado;
        }
    }

    return $items;
}

/**
 * Valida en origen una noticia elegida desde el selector RSS.
 *
 * @return array{fuente:array<string,mixed>,item:array<string,mixed>}|null
 */
function validarItemSeleccionadoRss(PDO $pdo, int $idFuente, string $hash, ?int $excluirNoticia = null): ?array
{
    if ($idFuente <= 0 || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id_fuente, nombre, url FROM fuentes_rss WHERE id_fuente = ? AND activa = 1 LIMIT 1'
    );
    $stmt->execute([$idFuente]);
    $fuente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fuente) {
        return null;
    }
    $feed = obtenerFeed((string) $fuente['url'], 5);
    if (!is_object($feed) || !method_exists($feed, 'get_items')) {
        return null;
    }
    $items = obtenerItemsSeleccionablesRss($feed, 50);
    if (!isset($items[$hash])) {
        return null;
    }
    $sql = 'SELECT id_noticia FROM noticias WHERE rss_item_hash = ?';
    $parametros = [$hash];
    if ($excluirNoticia !== null) {
        $sql .= ' AND id_noticia != ?';
        $parametros[] = $excluirNoticia;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($parametros);
    if ($stmt->fetchColumn()) {
        throw new DomainException('Noticia ya seleccionada');
    }
    return ['fuente' => $fuente, 'item' => $items[$hash]];
}

/**
 * Obtiene extracto del contenido - Usa lo que sea MÁS LARGO (descripción o contenido)
 */
function obtenerExtracto($item, $longitud = RSS_EXTRACTO_LONGITUD) {
    // Obtener descripción y contenido
    $descripcion = $item->get_description();
    $contenido = $item->get_content();
    
    // Limpiar ambos
    $descripcion_limpia = strip_tags_safe($descripcion ?? '');
    $contenido_limpio = strip_tags_safe($contenido ?? '');
    
    // Calcular longitudes
    $len_desc = strlen($descripcion_limpia);
    $len_cont = strlen($contenido_limpio);
    
    // Elegir el que tenga MÁS caracteres (CORREGIDO)
    if ($len_cont > $len_desc) {
        $texto = $contenido_limpio;  // ← CORREGIDO: antes ponía $contenido_limpia
        $longitud_usada = $len_cont;
    } else {
        $texto = $descripcion_limpia;
        $longitud_usada = $len_desc;
    }
    
    // Si ambos están vacíos, usar título
    if (empty($texto) || $longitud_usada < 20) {
        $texto = $item->get_title() ?? 'Noticia importada';
        $longitud_usada = strlen($texto);
    }
    
    // Decodificar entidades HTML
    $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
    
    // Eliminar espacios múltiples
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = trim($texto);
    
    // Cortar a la longitud deseada
    if (strlen($texto) > $longitud) {
        $texto = substr($texto, 0, $longitud);
        // Cortar en la última palabra completa
        $ultimo_espacio = strrpos($texto, ' ');
        if ($ultimo_espacio !== false && $ultimo_espacio > $longitud * 0.7) {
            $texto = substr($texto, 0, $ultimo_espacio);
        }
        $texto .= '...';
    }
    
    return $texto;
}

/**
 * Genera el contenido HTML con extracto + botón externo
 * CORREGIDA - Asegura que siempre haya contenido visible
 */
/**
 * Extrae el nombre de dominio principal de una URL.
 * Ej: https://eldiario.opennemas.com/... → eldiario
 */
function extraerDominioFuente(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST) ?? '';
    // Quitar subdominios comunes
    $host = preg_replace('/^(www|feeds|rss)\./i', '', $host);
    $partes = explode('.', $host);
    // Para CDN tipo e00-elmundo.uecdn.es, extraer el nombre de marca
    $primero = $partes[0] ?? '';
    if (preg_match('/^e00-([a-z]+)/i', $primero, $m)) {
        return strtolower($m[1]);
    }
    // Para uecdn.es u otros CDN, usar el segundo nivel
    $cdn = ['uecdn', 'cdn', 'static', 'media', 'img'];
    if (in_array(strtolower($primero), $cdn) && isset($partes[1])) {
        return strtolower($partes[1]);
    }
    return strtolower($primero);
}

function generarContenidoConBoton($item, $extracto, ?string $video = null) {
    $link = $item->get_permalink();
    $titulo = htmlspecialchars($item->get_title() ?? 'Noticia');
    
    // Si el extracto está vacío o es muy corto, usar una versión mejorada
    if (empty($extracto) || strlen($extracto) < 50) {
        // Intentar obtener más datos
        $descripcion = $item->get_description();
        $contenido = $item->get_content();
        
        if (!empty($descripcion)) {
            $extracto = strip_tags_safe($descripcion);
        } elseif (!empty($contenido)) {
            $extracto = strip_tags_safe($contenido);
        } else {
            $extracto = 'Lee esta noticia completa en la fuente original.';
        }
        
        // Limpiar y acortar
        $extracto = html_entity_decode($extracto, ENT_QUOTES, 'UTF-8');
        $extracto = preg_replace('/\s+/', ' ', trim($extracto));
        
        if (empty($extracto)) {
            $extracto = 'Noticia importada desde RSS. Haz clic en el botón para leer el contenido completo.';
        }
        
        if (strlen($extracto) > RSS_EXTRACTO_LONGITUD) {
            $extracto = substr($extracto, 0, RSS_EXTRACTO_LONGITUD);
            $ultimo_espacio = strrpos($extracto, ' ');
            if ($ultimo_espacio !== false) {
                $extracto = substr($extracto, 0, $ultimo_espacio);
            }
            $extracto .= '...';
        }
    }
    
    if (empty($link)) {
        return '<p>' . nl2br(htmlspecialchars($extracto)) . '</p>';
    }
    
    // Obtener nombre del dominio para la fuente
    $dominio = parse_url($link, PHP_URL_HOST);
    $nombre_fuente = $dominio ? str_replace('www.', '', $dominio) : 'Fuente externa';
    
    $htmlVideo = '';
    if ($video !== null && normalizarUrlItemRss($video) !== null) {
        $videoSeguro = htmlspecialchars($video, ENT_QUOTES, 'UTF-8');
        $htmlVideo = '
        <div class="rss-video" style="margin: 20px 0; text-align: center;">
            <video controls preload="metadata" style="max-width: 100%; height: auto;">
                <source src="' . $videoSeguro . '">
            </video>
        </div>';
    }

    // HTML del extracto + vídeo opcional + botón
    $html = '
    <div class="noticia-rss-importada">
        <div class="rss-extracto" style="margin-bottom: 20px; line-height: 1.6;">
            ' . nl2br(htmlspecialchars($extracto)) . '
        </div>
        ' . $htmlVideo . '
        <div class="rss-boton-externo" style="margin: 25px 0; text-align: center;">
            <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" 
               target="_blank" 
               rel="noopener noreferrer nofollow"
               class="btn-rss-externo"
               style="display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                📖 Leer noticia completa en ' . htmlspecialchars($nombre_fuente, ENT_QUOTES, 'UTF-8') . '
            </a>
            <p style="font-size: 12px; color: #6b7280; margin-top: 10px;">
                🔗 Serás redirigido al sitio original
            </p>
        </div>
        <div class="rss-fuente" style="font-size: 11px; color: #9ca3af; text-align: right; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 10px;">
            📎 Fuente original: <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" style="color: #6b7280; text-decoration: none;">' . htmlspecialchars(extraerDominioFuente($link)) . '</a>
        </div>
    </div>';
    
    return $html;
}

/**
 * Obtiene título seguro del item
 */
function obtenerTituloItem($item) {
    $titulo = $item->get_title();
    return limpiarTextoSeguro($titulo);
}

/**
 * Obtiene fecha segura del item
 */
function obtenerFechaItem($item) {
    $fecha = $item->get_date('Y-m-d H:i:s');
    if (empty($fecha)) {
        return date('Y-m-d H:i:s');
    }
    return $fecha;
}
