<?php
declare(strict_types=1);

/**
 * Catálogo público de NASA Images. No descarga ni almacena los archivos.
 */

const NASA_CATALOGO_CACHE_TTL = 3600;
const NASA_CATALOGO_CACHE_MAXIMA = 604800;
const NASA_CATALOGO_MAX_BYTES = 2097152;
const NASA_CATALOGO_RESULTADOS = 24;
const NASA_TRADUCCION_CACHE_TTL = 2592000;
const NASA_TRADUCCION_MAX_PARRAFOS = 5;
const NASA_TRADUCCION_MAX_BYTES = 2500;

/**
 * @return array{items:list<array<string,mixed>>,total:int,cache:bool}
 */
function buscarCatalogoNasa(string $consulta, string $tipo, int $pagina, ?int $desde = null): array
{
    $consulta = trim($consulta);
    if ($consulta === '' || mb_strlen($consulta, 'UTF-8') > 80) {
        throw new InvalidArgumentException('La búsqueda debe contener entre 1 y 80 caracteres.');
    }

    $tipos = ['image', 'video', 'image,video'];
    $tipo = in_array($tipo, $tipos, true) ? $tipo : 'image,video';
    $pagina = max(1, min(100, $pagina));
    $anyoActual = (int) date('Y');
    $desde = $desde !== null && $desde >= 1920 && $desde <= $anyoActual ? $desde : null;

    $directorio = ROOT_PATH . 'storage/cache/nasa';
    $archivo = $directorio . '/' . hash('sha256', $consulta . '|' . $tipo . '|' . $pagina . '|' . ($desde ?? '')) . '.json';
    $cache = leerCacheCatalogoNasa($archivo);

    if ($cache !== null && time() - $cache['actualizado'] <= NASA_CATALOGO_CACHE_TTL) {
        return ['items' => $cache['items'], 'total' => $cache['total'], 'cache' => true];
    }

    try {
        $json = descargarCatalogoNasa($consulta, $tipo, $pagina, $desde);
        $resultado = normalizarCatalogoNasa($json);
        guardarCacheCatalogoNasa($archivo, $resultado);
        return ['items' => $resultado['items'], 'total' => $resultado['total'], 'cache' => false];
    } catch (Throwable $error) {
        if ($cache !== null && time() - $cache['actualizado'] <= NASA_CATALOGO_CACHE_MAXIMA) {
            return ['items' => $cache['items'], 'total' => $cache['total'], 'cache' => true];
        }
        throw $error;
    }
}

function descargarCatalogoNasa(string $consulta, string $tipo, int $pagina, ?int $desde = null): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL no está disponible.');
    }

    $parametrosApi = [
        'q' => $consulta,
        'media_type' => $tipo,
        'page' => $pagina,
        'page_size' => NASA_CATALOGO_RESULTADOS,
    ];
    if ($desde !== null) {
        $parametrosApi['year_start'] = $desde;
    }
    $parametros = http_build_query($parametrosApi, '', '&', PHP_QUERY_RFC3986);
    $contenido = '';
    $curl = curl_init('https://images-api.nasa.gov/search?' . $parametros);
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la consulta a NASA.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'TuPortalNews catalogo NASA/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $fragmento) use (&$contenido): int {
            if (strlen($contenido) + strlen($fragmento) > NASA_CATALOGO_MAX_BYTES) {
                return 0;
            }
            $contenido .= $fragmento;
            return strlen($fragmento);
        },
    ]);

    $correcto = curl_exec($curl);
    $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($correcto === false || $estado !== 200 || $contenido === '') {
        throw new RuntimeException('NASA no devolvió datos válidos.');
    }

    return $contenido;
}

/**
 * @return array{items:list<array<string,mixed>>,total:int,actualizado:int}
 */
function normalizarCatalogoNasa(string $json): array
{
    $datos = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    $coleccion = $datos['collection'] ?? null;
    if (!is_array($coleccion)) {
        throw new RuntimeException('El formato recibido de NASA no es válido.');
    }

    $items = [];
    foreach (($coleccion['items'] ?? []) as $item) {
        $dato = is_array($item['data'][0] ?? null) ? $item['data'][0] : [];
        $id = trim((string) ($dato['nasa_id'] ?? ''));
        $tipo = (string) ($dato['media_type'] ?? '');
        if ($id === '' || !in_array($tipo, ['image', 'video'], true)) {
            continue;
        }

        $miniatura = null;
        foreach (($item['links'] ?? []) as $enlace) {
            $url = (string) ($enlace['href'] ?? '');
            if (($enlace['render'] ?? '') === 'image' && esUrlMultimediaNasa($url)) {
                $miniatura = $url;
                if (($enlace['rel'] ?? '') === 'preview') {
                    break;
                }
            }
        }
        if ($miniatura === null) {
            continue;
        }

        $descripcion = trim(strip_tags((string) ($dato['description_508'] ?? $dato['description'] ?? '')));
        $items[] = [
            'id' => $id,
            'titulo' => trim((string) ($dato['title'] ?? 'Contenido NASA')),
            'descripcion' => mb_strimwidth($descripcion, 0, 260, '…', 'UTF-8'),
            'tipo' => $tipo,
            'fecha' => (string) ($dato['date_created'] ?? ''),
            'centro' => trim((string) ($dato['center'] ?? 'NASA')),
            'miniatura' => $miniatura,
            'detalle' => 'https://images.nasa.gov/details/' . rawurlencode($id),
        ];
    }

    $total = max(0, (int) ($coleccion['metadata']['total_hits'] ?? count($items)));
    return ['items' => $items, 'total' => $total, 'actualizado' => time()];
}

function esUrlMultimediaNasa(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
        && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'images-assets.nasa.gov';
}

/** @return array{url:string}|null */
function obtenerVideoCatalogoNasa(string $id): ?array
{
    if (!esIdentificadorNasaValido($id)) {
        return null;
    }

    $contenido = descargarJsonNasa(
        'https://images-api.nasa.gov/asset/' . rawurlencode($id),
        1048576
    );
    $datos = json_decode($contenido, true, 32, JSON_THROW_ON_ERROR);
    $candidatos = [];
    foreach (($datos['collection']['items'] ?? []) as $item) {
        $url = (string) ($item['href'] ?? '');
        if (str_starts_with($url, 'http://images-assets.nasa.gov/')) {
            $url = 'https://' . substr($url, strlen('http://'));
        }
        $url = str_replace(' ', '%20', $url);
        if (!esUrlMultimediaNasa($url) || !preg_match('/\.mp4(?:\?|$)/i', $url)) {
            continue;
        }
        $candidatos[] = $url;
    }

    if ($candidatos === []) {
        return null;
    }
    usort($candidatos, static function (string $a, string $b): int {
        $prioridad = static fn(string $url): int => match (true) {
            str_contains($url, '~medium.') => 0,
            str_contains($url, '~small.') => 1,
            str_contains($url, '~large.') => 2,
            str_contains($url, '~orig.') => 4,
            default => 3,
        };
        return $prioridad($a) <=> $prioridad($b);
    });

    return ['url' => $candidatos[0]];
}

/** @return array{url:string,tipo:string}|null */
function obtenerRecursoVisorNasa(string $id, string $tipo): ?array
{
    if (!esIdentificadorNasaValido($id) || !in_array($tipo, ['image', 'video'], true)) {
        return null;
    }
    if ($tipo === 'video') {
        $video = obtenerVideoCatalogoNasa($id);
        return $video === null ? null : ['url' => $video['url'], 'tipo' => 'video'];
    }

    $contenido = descargarJsonNasa('https://images-api.nasa.gov/asset/' . rawurlencode($id), 1048576);
    $datos = json_decode($contenido, true, 32, JSON_THROW_ON_ERROR);
    $candidatos = [];
    foreach (($datos['collection']['items'] ?? []) as $item) {
        $url = (string) ($item['href'] ?? '');
        if (str_starts_with($url, 'http://images-assets.nasa.gov/')) {
            $url = 'https://' . substr($url, strlen('http://'));
        }
        $url = str_replace(' ', '%20', $url);
        if (!esUrlMultimediaNasa($url) || !preg_match('/\.(?:jpe?g|png|webp)(?:\?|$)/i', $url)) {
            continue;
        }
        $candidatos[] = $url;
    }
    if ($candidatos === []) {
        return null;
    }
    usort($candidatos, static function (string $a, string $b): int {
        $prioridad = static fn(string $url): int => match (true) {
            str_contains($url, '~large.') => 0,
            str_contains($url, '~medium.') => 1,
            str_contains($url, '~small.') => 2,
            str_contains($url, '~orig.') => 4,
            default => 3,
        };
        return $prioridad($a) <=> $prioridad($b);
    });
    return ['url' => $candidatos[0], 'tipo' => 'image'];
}

/** Devuelve únicamente la descripción oficial asociada al identificador NASA. */
function obtenerDescripcionCatalogoNasa(string $id): string
{
    if (!esIdentificadorNasaValido($id)) {
        throw new InvalidArgumentException('Identificador NASA no válido.');
    }
    $json = descargarJsonNasa(
        'https://images-api.nasa.gov/search?' . http_build_query(
            ['nasa_id' => $id, 'page_size' => 1], '', '&', PHP_QUERY_RFC3986
        ),
        262144
    );
    $datos = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    $dato = $datos['collection']['items'][0]['data'][0] ?? null;
    if (!is_array($dato) || (string) ($dato['nasa_id'] ?? '') !== $id) {
        throw new RuntimeException('NASA no devolvió la descripción solicitada.');
    }
    $descripcion = (string) ($dato['description_508'] ?? $dato['description'] ?? '');
    $descripcion = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $descripcion) ?? $descripcion;
    $descripcion = preg_replace('/<\s*\/p\s*>/i', "\n\n", $descripcion) ?? $descripcion;
    $descripcion = html_entity_decode(strip_tags($descripcion), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descripcion = preg_replace("/\r\n?|\x{2028}|\x{2029}/u", "\n", $descripcion) ?? $descripcion;
    $descripcion = preg_replace('/[ \t]+/u', ' ', $descripcion) ?? $descripcion;
    $descripcion = preg_replace('/\n[ \t]+|[ \t]+\n/u', "\n", $descripcion) ?? $descripcion;
    $descripcion = preg_replace('/\n{3,}/u', "\n\n", $descripcion) ?? $descripcion;
    return trim(mb_strcut($descripcion, 0, NASA_TRADUCCION_MAX_BYTES, 'UTF-8'));
}

function esIdentificadorNasaValido(string $id): bool
{
    return $id !== ''
        && mb_check_encoding($id, 'UTF-8')
        && mb_strlen($id, 'UTF-8') <= 180
        && preg_match('/^[^\x00-\x1F\x7F]+$/u', $id) === 1;
}

/** @return array{texto:string,cache:bool} */
function traducirDescripcionNasa(string $id, int $numeroParrafos): array
{
    $numeroParrafos = max(1, min(NASA_TRADUCCION_MAX_PARRAFOS, $numeroParrafos));
    $descripcion = obtenerDescripcionCatalogoNasa($id);
    if ($descripcion === '') {
        throw new RuntimeException('Este contenido NASA no incluye una descripción para traducir.');
    }
    $parrafos = preg_split('/\n\s*\n/u', $descripcion, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parrafos) === 1) {
        $frases = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $descripcion, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parrafos = array_map(
            static fn(array $grupo): string => implode(' ', $grupo),
            array_chunk($frases, 3)
        );
    }
    $parrafos = array_slice(array_values(array_filter(array_map('trim', $parrafos))), 0, $numeroParrafos);
    if ($parrafos === []) {
        throw new RuntimeException('Este contenido NASA no incluye párrafos traducibles.');
    }
    $texto = implode("\n\n", $parrafos);
    $directorio = ROOT_PATH . 'storage/cache/nasa-traducciones';
    $archivo = $directorio . '/' . hash('sha256', $id . '|' . $numeroParrafos . '|' . $texto) . '.json';
    $cache = leerCacheTraduccionNasa($archivo);
    if ($cache !== null) {
        return ['texto' => $cache, 'cache' => true];
    }
    $traducidos = [];
    foreach ($parrafos as $parrafo) {
        $fragmentosTraducidos = [];
        foreach (dividirTextoNasa($parrafo, 450) as $fragmento) {
            $fragmentosTraducidos[] = traducirFragmentoNasa($fragmento);
        }
        $traducidos[] = implode(' ', $fragmentosTraducidos);
    }
    $traduccion = trim(implode("\n\n", $traducidos));
    if ($traduccion === '') {
        throw new RuntimeException('El servicio de traducción no devolvió contenido válido.');
    }
    guardarCacheTraduccionNasa($archivo, $traduccion);
    return ['texto' => $traduccion, 'cache' => false];
}

/** @return array{titulo:string,descripcion:string,cache:bool} */
function traducirTarjetaNasa(string $id): array
{
    if (!esIdentificadorNasaValido($id)) {
        throw new InvalidArgumentException('Identificador NASA no válido.');
    }
    $json = descargarJsonNasa(
        'https://images-api.nasa.gov/search?' . http_build_query(
            ['nasa_id' => $id, 'page_size' => 1], '', '&', PHP_QUERY_RFC3986
        ),
        262144
    );
    $datos = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    $dato = $datos['collection']['items'][0]['data'][0] ?? null;
    if (!is_array($dato) || (string) ($dato['nasa_id'] ?? '') !== $id) {
        throw new RuntimeException('NASA no devolvió el contenido solicitado.');
    }
    $titulo = trim(strip_tags((string) ($dato['title'] ?? '')));
    $descripcion = trim(strip_tags((string) ($dato['description_508'] ?? $dato['description'] ?? '')));
    $descripcion = html_entity_decode($descripcion, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descripcion = preg_replace('/\s+/u', ' ', $descripcion) ?? $descripcion;
    $descripcion = trim(mb_strimwidth($descripcion, 0, 260, '', 'UTF-8'));
    if ($titulo === '') {
        throw new RuntimeException('Esta tarjeta no contiene un título traducible.');
    }

    $directorio = ROOT_PATH . 'storage/cache/nasa-traducciones';
    $archivo = $directorio . '/' . hash('sha256', 'tarjeta|' . $id . '|' . $titulo . '|' . $descripcion) . '.json';
    $cache = leerCacheTarjetaNasa($archivo);
    if ($cache !== null) {
        return ['titulo' => $cache['titulo'], 'descripcion' => $cache['descripcion'], 'cache' => true];
    }

    $tituloTraducido = traducirFragmentoNasa(mb_strcut($titulo, 0, 450, 'UTF-8'));
    $descripcionTraducida = '';
    if ($descripcion !== '') {
        $partes = [];
        foreach (dividirTextoNasa($descripcion, 450) as $fragmento) {
            $partes[] = traducirFragmentoNasa($fragmento);
        }
        $descripcionTraducida = implode(' ', $partes);
    }
    guardarCacheTarjetaNasa($archivo, $tituloTraducido, $descripcionTraducida);
    return ['titulo' => $tituloTraducido, 'descripcion' => $descripcionTraducida, 'cache' => false];
}

/** @return list<string> */
function dividirTextoNasa(string $texto, int $maximoBytes): array
{
    $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $fragmentos = [];
    $actual = '';
    foreach ($palabras as $palabra) {
        $candidato = $actual === '' ? $palabra : $actual . ' ' . $palabra;
        if (strlen($candidato) <= $maximoBytes) {
            $actual = $candidato;
            continue;
        }
        if ($actual !== '') {
            $fragmentos[] = $actual;
        }
        while (strlen($palabra) > $maximoBytes) {
            $fragmentos[] = mb_strcut($palabra, 0, $maximoBytes, 'UTF-8');
            $palabra = mb_strcut($palabra, $maximoBytes, null, 'UTF-8');
        }
        $actual = $palabra;
    }
    if ($actual !== '') {
        $fragmentos[] = $actual;
    }
    return $fragmentos;
}

function traducirFragmentoNasa(string $texto): string
{
    $url = 'https://api.mymemory.translated.net/get?' . http_build_query(
        ['q' => $texto, 'langpair' => 'en|es', 'mt' => 1], '', '&', PHP_QUERY_RFC3986
    );
    $contenido = '';
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la traducción.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'TuPortalNews NASA translator/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $fragmento) use (&$contenido): int {
            if (strlen($contenido) + strlen($fragmento) > 262144) {
                return 0;
            }
            $contenido .= $fragmento;
            return strlen($fragmento);
        },
    ]);
    $correcto = curl_exec($curl);
    $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($correcto === false || $estado !== 200 || $contenido === '') {
        throw new RuntimeException('El traductor no está disponible temporalmente.');
    }
    $datos = json_decode($contenido, true, 16, JSON_THROW_ON_ERROR);
    $traduccion = html_entity_decode(
        trim((string) ($datos['responseData']['translatedText'] ?? '')),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
    if ($traduccion === '') {
        throw new RuntimeException('El traductor no devolvió una respuesta válida.');
    }
    return $traduccion;
}

function leerCacheTraduccionNasa(string $archivo): ?string
{
    if (!is_file($archivo) || filesize($archivo) > 32768 || time() - filemtime($archivo) > NASA_TRADUCCION_CACHE_TTL) {
        return null;
    }
    $datos = json_decode((string) file_get_contents($archivo), true);
    return is_array($datos) && is_string($datos['texto'] ?? null) ? $datos['texto'] : null;
}

function guardarCacheTraduccionNasa(string $archivo, string $texto): void
{
    $directorio = dirname($archivo);
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        return;
    }
    $json = json_encode(['texto' => $texto], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return;
    }
    $temporal = tempnam($directorio, '.traduccion-');
    if ($temporal !== false && file_put_contents($temporal, $json, LOCK_EX) === strlen($json)) {
        chmod($temporal, 0640);
        rename($temporal, $archivo);
        return;
    }
    if ($temporal !== false) {
        @unlink($temporal);
    }
}

/** @return array{titulo:string,descripcion:string}|null */
function leerCacheTarjetaNasa(string $archivo): ?array
{
    if (!is_file($archivo) || filesize($archivo) > 32768 || time() - filemtime($archivo) > NASA_TRADUCCION_CACHE_TTL) {
        return null;
    }
    $datos = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($datos) || !is_string($datos['titulo'] ?? null) || !is_string($datos['descripcion'] ?? null)) {
        return null;
    }
    return ['titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion']];
}

function guardarCacheTarjetaNasa(string $archivo, string $titulo, string $descripcion): void
{
    $directorio = dirname($archivo);
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        return;
    }
    $json = json_encode(
        ['titulo' => $titulo, 'descripcion' => $descripcion],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($json)) {
        return;
    }
    $temporal = tempnam($directorio, '.tarjeta-');
    if ($temporal !== false && file_put_contents($temporal, $json, LOCK_EX) === strlen($json)) {
        chmod($temporal, 0640);
        rename($temporal, $archivo);
        return;
    }
    if ($temporal !== false) {
        @unlink($temporal);
    }
}

function descargarJsonNasa(string $url, int $maximoBytes): string
{
    if (!str_starts_with($url, 'https://images-api.nasa.gov/')) {
        throw new InvalidArgumentException('Destino NASA no permitido.');
    }
    $contenido = '';
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la consulta a NASA.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'TuPortalNews catalogo NASA/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $fragmento) use (&$contenido, $maximoBytes): int {
            if (strlen($contenido) + strlen($fragmento) > $maximoBytes) {
                return 0;
            }
            $contenido .= $fragmento;
            return strlen($fragmento);
        },
    ]);
    $correcto = curl_exec($curl);
    $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($correcto === false || $estado !== 200 || $contenido === '') {
        throw new RuntimeException('NASA no devolvió el recurso solicitado.');
    }
    return $contenido;
}

/** @return array{items:list<array<string,mixed>>,total:int,actualizado:int}|null */
function leerCacheCatalogoNasa(string $archivo): ?array
{
    if (!is_file($archivo) || filesize($archivo) > NASA_CATALOGO_MAX_BYTES) {
        return null;
    }
    try {
        $datos = json_decode((string) file_get_contents($archivo), true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }
    if (!is_array($datos['items'] ?? null) || !is_int($datos['total'] ?? null) || !is_int($datos['actualizado'] ?? null)) {
        return null;
    }
    return $datos;
}

/** @param array{items:list<array<string,mixed>>,total:int,actualizado:int} $datos */
function guardarCacheCatalogoNasa(string $archivo, array $datos): void
{
    $directorio = dirname($archivo);
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        return;
    }
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return;
    }
    $temporal = tempnam($directorio, '.nasa-');
    if ($temporal === false) {
        return;
    }
    if (file_put_contents($temporal, $json, LOCK_EX) === strlen($json)) {
        chmod($temporal, 0640);
        rename($temporal, $archivo);
        return;
    }
    @unlink($temporal);
}
