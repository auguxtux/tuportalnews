<?php
declare(strict_types=1);

/**
 * Consulta y normaliza la tasa de riesgo de pobreza publicada por el INE.
 */

const POBREZA_INE_TABLA = 9963;
const POBREZA_INE_CACHE_TTL = 86400;
const POBREZA_INE_CACHE_MAXIMA = 2592000;
const POBREZA_INE_MAX_BYTES = 524288;
const POBREZA_EUROSTAT_CACHE_TTL = 86400;
const POBREZA_EUROSTAT_CACHE_MAXIMA = 2592000;
const POBREZA_EUROSTAT_MAX_BYTES = 262144;

/** Descarga HTTPS acotada compartida por los proveedores internacionales. */
function descargarDatosPobrezaSeguros(string $url, int $maxBytes, string $accept): string
{
    if (!function_exists('curl_init') || $maxBytes < 1024) {
        throw new RuntimeException('No se puede iniciar la consulta estadística.');
    }
    $contenido = '';
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('No se pudo iniciar la consulta estadística.');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'ERUN estadisticas/1.0',
        CURLOPT_HTTPHEADER => ['Accept: ' . $accept],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $fragmento) use (&$contenido, $maxBytes): int {
            if (strlen($contenido) + strlen($fragmento) > $maxBytes) return 0;
            $contenido .= $fragmento;
            return strlen($fragmento);
        },
    ]);
    $correcto = curl_exec($curl);
    $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($correcto === false || $estado !== 200 || $contenido === '') {
        throw new RuntimeException('El proveedor estadístico no devolvió datos válidos.');
    }
    return $contenido;
}

/**
 * Contexto político estatal contrastado con la cronología de La Moncloa.
 * Las coaliciones comienzan con la formación del nuevo Consejo de Ministros.
 *
 * @return list<array{desde:string,hasta:?string,partidos:string,presidente:string,color:string}>
 */
function obtenerGobiernosEspanaPobreza(): array
{
    return [
        [
            'desde' => '2004-04-17',
            'hasta' => '2011-12-21',
            'partidos' => 'PSOE',
            'presidente' => 'José Luis Rodríguez Zapatero',
            'color' => '#ef4444',
        ],
        [
            'desde' => '2011-12-21',
            'hasta' => '2018-06-02',
            'partidos' => 'PP',
            'presidente' => 'Mariano Rajoy',
            'color' => '#2563eb',
        ],
        [
            'desde' => '2018-06-02',
            'hasta' => '2020-01-13',
            'partidos' => 'PSOE',
            'presidente' => 'Pedro Sánchez',
            'color' => '#ef4444',
        ],
        [
            'desde' => '2020-01-13',
            'hasta' => '2023-11-21',
            'partidos' => 'PSOE + Unidas Podemos',
            'presidente' => 'Pedro Sánchez',
            'color' => '#9333ea',
        ],
        [
            'desde' => '2023-11-21',
            'hasta' => null,
            'partidos' => 'PSOE + Sumar',
            'presidente' => 'Pedro Sánchez',
            'color' => '#be123c',
        ],
    ];
}

/**
 * @return array<string,list<array{desde:string,hasta:?string,partidos:string,color:string}>>
 */
function obtenerGobiernosAutonomicosPobreza(): array
{
    $archivo = dirname(__DIR__) . '/data/gobiernos-autonomicos.php';
    $datos = require $archivo;

    return is_array($datos) ? $datos : [];
}

/**
 * AROPE para menores de 18 años en España y la UE-27.
 *
 * @return array{series:array<string,array{nombre:string,valores:array<int,float>}>,actualizado:int,cache:bool}
 */
function obtenerPobrezaInfantilEurostat(): array
{
    $directorio = defined('ROOT_PATH')
        ? ROOT_PATH . 'storage/cache/eurostat'
        : dirname(__DIR__, 2) . '/storage/cache/eurostat';
    $archivo = $directorio . '/arope-infantil-paises-v2.json';
    $cache = leerCachePobrezaIne($archivo);

    if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_EUROSTAT_CACHE_TTL) {
        $cache['cache'] = true;
        return $cache;
    }

    $bloqueo = obtenerBloqueoActualizacionPobreza($archivo);
    if ($bloqueo === null && $cache !== null) {
        $cache['cache'] = true;
        return $cache;
    }

    try {
        $cacheRenovada = leerCachePobrezaIne($archivo);
        if ($cacheRenovada !== null && time() - $cacheRenovada['actualizado'] <= POBREZA_EUROSTAT_CACHE_TTL) {
            $cacheRenovada['cache'] = true;
            return $cacheRenovada;
        }

        $respuesta = descargarPobrezaInfantilEurostat();
        $series = normalizarPobrezaInfantilEurostat($respuesta);
        if (!isset($series['espana'], $series['ue27'])) {
            throw new RuntimeException('La respuesta infantil de Eurostat está incompleta.');
        }

        $resultado = ['series' => $series, 'actualizado' => time(), 'cache' => false];
        guardarCachePobrezaIne($archivo, $resultado);
        return $resultado;
    } catch (Throwable $error) {
        if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_EUROSTAT_CACHE_MAXIMA) {
            $cache['cache'] = true;
            return $cache;
        }
        throw $error;
    } finally {
        liberarBloqueoActualizacionPobreza($bloqueo);
    }
}

function descargarPobrezaInfantilEurostat(): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL no está disponible.');
    }

    $url = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/'
        . 'ilc_peps01n?lang=en&age=Y_LT18&sex=T&unit=PC';
    $contenido = '';
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la consulta a Eurostat.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'ERUN estadisticas/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $fragmento) use (&$contenido): int {
            if (strlen($contenido) + strlen($fragmento) > POBREZA_EUROSTAT_MAX_BYTES) {
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
        throw new RuntimeException('Eurostat no devolvió datos válidos.');
    }

    return $contenido;
}

/**
 * @return array<string,array{nombre:string,valores:array<int,float>}>
 */
function normalizarPobrezaInfantilEurostat(string $json): array
{
    $datos = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    $geo = $datos['dimension']['geo']['category']['index'] ?? null;
    $tiempo = $datos['dimension']['time']['category']['index'] ?? null;
    $valores = $datos['value'] ?? null;

    if (!is_array($geo) || !is_array($tiempo) || !is_array($valores)) {
        throw new RuntimeException('El formato de Eurostat no es válido.');
    }

    $cantidadAnyos = count($tiempo);
    if ($cantidadAnyos === 0) {
        throw new RuntimeException('Eurostat no devolvió periodos.');
    }

    $configuracion = obtenerPaisesUnionEuropeaPobreza();
    $series = [];

    foreach ($configuracion as $codigo => $config) {
        if (!isset($geo[$codigo]) || !is_int($geo[$codigo])) {
            continue;
        }

        $serie = [];
        foreach ($tiempo as $anyo => $posicionTiempo) {
            if (!is_int($posicionTiempo) || !preg_match('/^20\d{2}$/', (string) $anyo)) {
                continue;
            }
            $posicion = $geo[$codigo] * $cantidadAnyos + $posicionTiempo;
            $valor = $valores[(string) $posicion] ?? $valores[$posicion] ?? null;
            if (is_numeric($valor)) {
                $serie[(int) $anyo] = round((float) $valor, 1);
            }
        }

        if ($serie !== []) {
            ksort($serie);
            $series[$config['clave']] = ['nombre' => $config['nombre'], 'valores' => $serie];
        }
    }

    return $series;
}

/**
 * Países de la UE-27 con los códigos territoriales usados por Eurostat.
 *
 * @return array<string,array{clave:string,nombre:string}>
 */
function obtenerPaisesUnionEuropeaPobreza(): array
{
    return [
        'EU27_2020' => ['clave' => 'ue27', 'nombre' => 'Unión Europea (UE-27)'],
        'DE' => ['clave' => 'alemania', 'nombre' => 'Alemania'],
        'AT' => ['clave' => 'austria', 'nombre' => 'Austria'],
        'BE' => ['clave' => 'belgica', 'nombre' => 'Bélgica'],
        'BG' => ['clave' => 'bulgaria', 'nombre' => 'Bulgaria'],
        'CY' => ['clave' => 'chipre', 'nombre' => 'Chipre'],
        'HR' => ['clave' => 'croacia', 'nombre' => 'Croacia'],
        'DK' => ['clave' => 'dinamarca', 'nombre' => 'Dinamarca'],
        'SK' => ['clave' => 'eslovaquia', 'nombre' => 'Eslovaquia'],
        'SI' => ['clave' => 'eslovenia', 'nombre' => 'Eslovenia'],
        'ES' => ['clave' => 'espana', 'nombre' => 'España'],
        'EE' => ['clave' => 'estonia', 'nombre' => 'Estonia'],
        'FI' => ['clave' => 'finlandia', 'nombre' => 'Finlandia'],
        'FR' => ['clave' => 'francia', 'nombre' => 'Francia'],
        'EL' => ['clave' => 'grecia', 'nombre' => 'Grecia'],
        'HU' => ['clave' => 'hungria', 'nombre' => 'Hungría'],
        'IE' => ['clave' => 'irlanda', 'nombre' => 'Irlanda'],
        'IT' => ['clave' => 'italia', 'nombre' => 'Italia'],
        'LV' => ['clave' => 'letonia', 'nombre' => 'Letonia'],
        'LT' => ['clave' => 'lituania', 'nombre' => 'Lituania'],
        'LU' => ['clave' => 'luxemburgo', 'nombre' => 'Luxemburgo'],
        'MT' => ['clave' => 'malta', 'nombre' => 'Malta'],
        'NL' => ['clave' => 'paises-bajos', 'nombre' => 'Países Bajos'],
        'PL' => ['clave' => 'polonia', 'nombre' => 'Polonia'],
        'PT' => ['clave' => 'portugal', 'nombre' => 'Portugal'],
        'CZ' => ['clave' => 'republica-checa', 'nombre' => 'República Checa'],
        'RO' => ['clave' => 'rumania', 'nombre' => 'Rumanía'],
        'SE' => ['clave' => 'suecia', 'nombre' => 'Suecia'],
    ];
}

/**
 * @return array<string,array{fuente:string,periodos:list<array{desde:string,hasta:?string,partidos:string,color:string}>}>
 */
function obtenerGobiernosEuropeosPobreza(): array
{
    $datos = require dirname(__DIR__) . '/data/gobiernos-europeos.php';
    return is_array($datos) ? $datos : [];
}

/**
 * @return array{series: array<string,array{nombre:string,valores:array<int,float>}>, actualizado:int, cache:bool}
 */
function obtenerPobrezaComunidadesIne(): array
{
    $directorio = defined('ROOT_PATH')
        ? ROOT_PATH . 'storage/cache/ine'
        : dirname(__DIR__, 2) . '/storage/cache/ine';
    $archivo = $directorio . '/pobreza-comunidades.json';

    $cache = leerCachePobrezaIne($archivo);
    if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_INE_CACHE_TTL) {
        $cache['cache'] = true;
        return $cache;
    }

    $bloqueo = obtenerBloqueoActualizacionPobreza($archivo);
    if ($bloqueo === null && $cache !== null) {
        $cache['cache'] = true;
        return $cache;
    }

    try {
        $cacheRenovada = leerCachePobrezaIne($archivo);
        if ($cacheRenovada !== null && time() - $cacheRenovada['actualizado'] <= POBREZA_INE_CACHE_TTL) {
            $cacheRenovada['cache'] = true;
            return $cacheRenovada;
        }

        $respuesta = descargarPobrezaIne();
        $series = normalizarPobrezaIne($respuesta);

        if (!isset($series['espana']) || count($series) < 18) {
            throw new RuntimeException('La respuesta del INE está incompleta.');
        }

        $resultado = [
            'series' => $series,
            'actualizado' => time(),
            'cache' => false,
        ];

        guardarCachePobrezaIne($archivo, $resultado);
        return $resultado;
    } catch (Throwable $error) {
        if (
            $cache !== null
            && time() - $cache['actualizado'] <= POBREZA_INE_CACHE_MAXIMA
        ) {
            $cache['cache'] = true;
            return $cache;
        }

        throw $error;
    } finally {
        liberarBloqueoActualizacionPobreza($bloqueo);
    }
}

function descargarPobrezaIne(): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL no está disponible.');
    }

    $url = 'https://servicios.ine.es/wstempus/js/ES/DATOS_TABLA/'
        . POBREZA_INE_TABLA
        . '?nult=30';
    $contenido = '';
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la consulta al INE.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'ERUN estadisticas/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $fragmento) use (&$contenido): int {
            if (strlen($contenido) + strlen($fragmento) > POBREZA_INE_MAX_BYTES) {
                return 0;
            }

            $contenido .= $fragmento;
            return strlen($fragmento);
        },
    ]);

    $correcto = curl_exec($curl);
    $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($correcto === false || $estado !== 200 || $contenido === '') {
        throw new RuntimeException(
            $error !== '' ? 'No se pudo consultar el INE.' : 'El INE no devolvió datos válidos.'
        );
    }

    return $contenido;
}

/**
 * @return array<string,array{nombre:string,valores:array<int,float>}>
 */
function normalizarPobrezaIne(string $json): array
{
    $datos = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($datos)) {
        throw new RuntimeException('El formato de datos del INE no es válido.');
    }

    $series = [];
    foreach ($datos as $serie) {
        if (!is_array($serie)) {
            continue;
        }

        $nombreCompleto = trim((string) ($serie['Nombre'] ?? ''));
        if (
            $nombreCompleto === ''
            || str_contains($nombreCompleto, '(con alquiler imputado)')
            || !str_contains($nombreCompleto, '. Total. Tasa de riesgo de pobreza')
        ) {
            continue;
        }

        $nombre = trim((string) strstr($nombreCompleto, '. Total.', true));
        if ($nombre === 'Total Nacional') {
            $nombre = 'España';
        }

        $clave = claveTerritorioPobreza($nombre);
        $valores = [];
        foreach (($serie['Data'] ?? []) as $dato) {
            $anyo = filter_var($dato['Anyo'] ?? null, FILTER_VALIDATE_INT);
            $valor = $dato['Valor'] ?? null;
            if ($anyo === false || !is_numeric($valor) || $anyo < 2000 || $anyo > 2100) {
                continue;
            }

            $valores[(int) $anyo] = round((float) $valor, 1);
        }

        if ($clave !== '' && $valores !== []) {
            ksort($valores);
            $series[$clave] = ['nombre' => $nombre, 'valores' => $valores];
        }
    }

    uasort($series, static function (array $a, array $b): int {
        if ($a['nombre'] === 'España') {
            return -1;
        }
        if ($b['nombre'] === 'España') {
            return 1;
        }
        return strcasecmp($a['nombre'], $b['nombre']);
    });

    return $series;
}

function claveTerritorioPobreza(string $nombre): string
{
    $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
    $normalizado = strtolower($normalizado !== false ? $normalizado : $nombre);
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $normalizado), '-');
}

/**
 * @return array{series: array<string,array{nombre:string,valores:array<int,float>}>, actualizado:int, cache:bool}|null
 */
function leerCachePobrezaIne(string $archivo): ?array
{
    if (!is_file($archivo) || !is_readable($archivo)) {
        return null;
    }

    try {
        $datos = json_decode((string) file_get_contents($archivo), true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }

    if (
        !is_array($datos)
        || !is_array($datos['series'] ?? null)
        || !is_int($datos['actualizado'] ?? null)
    ) {
        return null;
    }

    return [
        'series' => $datos['series'],
        'actualizado' => $datos['actualizado'],
        'cache' => true,
    ];
}

/**
 * @param array{series:array,actualizado:int,cache:bool} $datos
 */
function guardarCachePobrezaIne(string $archivo, array $datos): void
{
    $directorio = dirname($archivo);
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        return;
    }

    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $temporal = tempnam($directorio, '.ine-');
    if ($temporal === false) {
        return;
    }

    if (file_put_contents($temporal, $json, LOCK_EX) !== strlen($json)) {
        @unlink($temporal);
        return;
    }

    chmod($temporal, 0640);
    if (!rename($temporal, $archivo)) {
        @unlink($temporal);
    }
}

/**
 * Evita que varias peticiones renueven simultáneamente una misma caché.
 * Si otro proceso ya la está actualizando, se utiliza la copia anterior.
 *
 * @return resource|null
 */
function obtenerBloqueoActualizacionPobreza(string $archivo)
{
    $directorio = dirname($archivo);
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        return null;
    }

    $bloqueo = fopen($archivo . '.lock', 'c');
    if ($bloqueo === false) {
        return null;
    }

    if (!flock($bloqueo, LOCK_EX | LOCK_NB)) {
        fclose($bloqueo);
        return null;
    }

    return $bloqueo;
}

/** @param resource|null $bloqueo */
function liberarBloqueoActualizacionPobreza($bloqueo): void
{
    if (!is_resource($bloqueo)) {
        return;
    }

    flock($bloqueo, LOCK_UN);
    fclose($bloqueo);
}
