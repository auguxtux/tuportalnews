<?php
declare(strict_types=1);

/** Datos oficiales del indicador ODS 1.2.2 de Naciones Unidas. */

const POBREZA_ONU_CACHE_TTL = 604800;
const POBREZA_ONU_CACHE_MAXIMA = 2592000;
const POBREZA_ONU_MAX_BYTES = 1048576;

/** @return array<string,array{codigo:string,iso:string,nombre:string}> */
function obtenerPaisesPobrezaOnu(): array
{
    $paises = [
        ['004', 'AFG'], ['008', 'ALB'], ['024', 'AGO'], ['032', 'ARG'],
        ['040', 'AUT'], ['051', 'ARM'], ['056', 'BEL'], ['076', 'BRA'],
        ['100', 'BGR'], ['108', 'BDI'], ['144', 'LKA'], ['152', 'CHL'],
        ['170', 'COL'], ['188', 'CRI'], ['191', 'HRV'], ['196', 'CYP'],
        ['203', 'CZE'], ['208', 'DNK'], ['214', 'DOM'], ['218', 'ECU'],
        ['222', 'SLV'], ['233', 'EST'], ['246', 'FIN'], ['250', 'FRA'],
        ['276', 'DEU'], ['288', 'GHA'], ['300', 'GRC'], ['320', 'GTM'],
        ['340', 'HND'], ['348', 'HUN'], ['356', 'IND'], ['372', 'IRL'],
        ['380', 'ITA'], ['428', 'LVA'], ['440', 'LTU'], ['442', 'LUX'],
        ['454', 'MWI'], ['462', 'MDV'], ['470', 'MLT'], ['484', 'MEX'],
        ['504', 'MAR'], ['516', 'NAM'], ['524', 'NPL'], ['528', 'NLD'],
        ['566', 'NGA'], ['578', 'NOR'], ['586', 'PAK'], ['591', 'PAN'],
        ['600', 'PRY'], ['616', 'POL'], ['642', 'ROU'], ['646', 'RWA'],
        ['688', 'SRB'], ['703', 'SVK'], ['705', 'SVN'], ['710', 'ZAF'],
        ['724', 'ESP'], ['752', 'SWE'], ['764', 'THA'], ['792', 'TUR'],
        ['800', 'UGA'], ['818', 'EGY'], ['858', 'URY'],
    ];
    $resultado = [];
    foreach ($paises as [$codigo, $iso]) {
        $nombre = extension_loaded('intl')
            ? Locale::getDisplayRegion('-' . $iso, 'es')
            : $iso;
        $resultado[$codigo] = [
            'codigo' => $codigo,
            'iso' => $iso,
            'nombre' => $nombre !== '' ? $nombre : $iso,
        ];
    }
    uasort($resultado, static fn(array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
    return $resultado;
}

/** @return array{series:array<string,array{nombre:string,valores:array<int,float>}>,actualizado:int,cache:bool} */
function obtenerPobrezaMultidimensionalOnu(array $codigos): array
{
    $paises = obtenerPaisesPobrezaOnu();
    $codigos = array_values(array_unique(array_filter(
        array_map(static fn(mixed $codigo): string => str_pad((string) (int) $codigo, 3, '0', STR_PAD_LEFT), $codigos),
        static fn(string $codigo): bool => isset($paises[$codigo])
    )));
    $codigos = array_slice($codigos, 0, 6);
    if ($codigos === []) {
        throw new InvalidArgumentException('Debe seleccionarse al menos un país de Naciones Unidas.');
    }
    sort($codigos);
    $archivo = ROOT_PATH . 'storage/cache/pobreza/onu/multidimensional-' . hash('sha256', implode('-', $codigos)) . '.json';
    $cache = leerCachePobrezaIne($archivo);
    if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_ONU_CACHE_TTL) {
        $cache['cache'] = true;
        return $cache;
    }
    $bloqueo = obtenerBloqueoActualizacionPobreza($archivo);
    if ($bloqueo === null && $cache !== null) {
        $cache['cache'] = true;
        return $cache;
    }
    try {
        $renovada = leerCachePobrezaIne($archivo);
        if ($renovada !== null && time() - $renovada['actualizado'] <= POBREZA_ONU_CACHE_TTL) {
            $renovada['cache'] = true;
            return $renovada;
        }
        $json = descargarPobrezaOnu($codigos);
        $series = normalizarPobrezaOnu($json);
        if ($series === []) {
            throw new RuntimeException('Naciones Unidas no devolvió series utilizables.');
        }
        $resultado = ['series' => $series, 'actualizado' => time(), 'cache' => false];
        guardarCachePobrezaIne($archivo, $resultado);
        return $resultado;
    } catch (Throwable $error) {
        if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_ONU_CACHE_MAXIMA) {
            $cache['cache'] = true;
            return $cache;
        }
        throw $error;
    } finally {
        liberarBloqueoActualizacionPobreza($bloqueo);
    }
}

function descargarPobrezaOnu(array $codigos): string
{
    $parametros = ['seriesCode=SD_MDP_MUHC', 'pageSize=1000'];
    foreach ($codigos as $codigo) {
        $parametros[] = 'areaCode=' . rawurlencode((string) (int) $codigo);
    }
    return descargarDatosPobrezaSeguros(
        'https://unstats.un.org/SDGAPI/v1/sdg/Series/Data?' . implode('&', $parametros),
        POBREZA_ONU_MAX_BYTES,
        'application/json'
    );
}

/** @return array<string,array{nombre:string,valores:array<int,float>}> */
function normalizarPobrezaOnu(string $json): array
{
    $datos = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
    $paises = obtenerPaisesPobrezaOnu();
    $series = [];
    $prioridades = [];
    foreach (($datos['data'] ?? []) as $fila) {
        if (!is_array($fila)) continue;
        $codigo = str_pad((string) ($fila['geoAreaCode'] ?? ''), 3, '0', STR_PAD_LEFT);
        $dimensiones = $fila['dimensions'] ?? [];
        if (
            !isset($paises[$codigo])
            || ($dimensiones['Age'] ?? '') !== 'ALLAGE'
            || ($dimensiones['Location'] ?? '') !== 'ALLAREA'
            || ($dimensiones['Sex'] ?? '') !== 'BOTHSEX'
            || !is_numeric($fila['value'] ?? null)
        ) continue;
        $anyo = (int) ($fila['timePeriodStart'] ?? 0);
        if ($anyo < 2000 || $anyo > (int) date('Y')) continue;
        $naturaleza = (string) ($fila['attributes']['Nature'] ?? '');
        $prioridad = ['C' => 4, 'CA' => 3, 'E' => 2, 'M' => 1][$naturaleza] ?? 0;
        if (($prioridades[$codigo][$anyo] ?? -1) > $prioridad) continue;
        $series[$codigo] ??= ['nombre' => $paises[$codigo]['nombre'], 'valores' => []];
        $series[$codigo]['valores'][$anyo] = round((float) $fila['value'], 1);
        $prioridades[$codigo][$anyo] = $prioridad;
    }
    foreach ($series as &$serie) ksort($serie['valores']);
    unset($serie);
    uasort($series, static fn(array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
    return $series;
}
