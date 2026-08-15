<?php
declare(strict_types=1);

/** Serie oficial de pobreza monetaria infantil publicada por UNICEF. */

const POBREZA_UNICEF_CACHE_TTL = 604800;
const POBREZA_UNICEF_CACHE_MAXIMA = 2592000;
const POBREZA_UNICEF_MAX_BYTES = 262144;

/** @return array{series:array<string,array{nombre:string,valores:array<int,float>}>,actualizado:int,cache:bool} */
function obtenerPobrezaInfantilUnicef(): array
{
    $archivo = ROOT_PATH . 'storage/cache/pobreza/unicef/ingresos.json';
    $cache = leerCachePobrezaIne($archivo);
    if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_UNICEF_CACHE_TTL) {
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
        if ($renovada !== null && time() - $renovada['actualizado'] <= POBREZA_UNICEF_CACHE_TTL) {
            $renovada['cache'] = true;
            return $renovada;
        }
        $csv = descargarDatosPobrezaSeguros(
            'https://sdmx.data.unicef.org/ws/public/sdmxapi/rest/data/'
                . 'UNICEF,CHLD_PVTY,1.0/.PV_CHLD_INCM-PL._T._T?startPeriod=2000',
            POBREZA_UNICEF_MAX_BYTES,
            'application/vnd.sdmx.data+csv;version=2.0.0'
        );
        $series = normalizarPobrezaUnicef($csv);
        if ($series === []) {
            throw new RuntimeException('UNICEF no devolvió series utilizables.');
        }
        $resultado = ['series' => $series, 'actualizado' => time(), 'cache' => false];
        guardarCachePobrezaIne($archivo, $resultado);
        return $resultado;
    } catch (Throwable $error) {
        if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_UNICEF_CACHE_MAXIMA) {
            $cache['cache'] = true;
            return $cache;
        }
        throw $error;
    } finally {
        liberarBloqueoActualizacionPobreza($bloqueo);
    }
}

/** @return array<string,array{nombre:string,valores:array<int,float>}> */
function normalizarPobrezaUnicef(string $csv): array
{
    $lineas = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
    if (count($lineas) < 2) return [];
    $cabecera = str_getcsv((string) array_shift($lineas));
    $indices = array_flip($cabecera);
    foreach (['REF_AREA', 'TIME_PERIOD', 'OBS_VALUE', 'UNIT_MEASURE'] as $campo) {
        if (!isset($indices[$campo])) return [];
    }
    $series = [];
    foreach ($lineas as $linea) {
        if (trim($linea) === '') continue;
        $fila = str_getcsv($linea);
        $iso = strtoupper((string) ($fila[$indices['REF_AREA']] ?? ''));
        $anyo = (int) ($fila[$indices['TIME_PERIOD']] ?? 0);
        $valor = $fila[$indices['OBS_VALUE']] ?? null;
        if (!preg_match('/^[A-Z]{3}$/', $iso) || $anyo < 2000 || $anyo > (int) date('Y') || !is_numeric($valor)) continue;
        if (($fila[$indices['UNIT_MEASURE']] ?? '') !== 'PCNT') continue;
        $nombre = extension_loaded('intl') ? Locale::getDisplayRegion('-' . $iso, 'es') : $iso;
        $series[$iso] ??= ['nombre' => $nombre !== '' ? $nombre : $iso, 'valores' => []];
        $series[$iso]['valores'][$anyo] = round((float) $valor, 1);
    }
    foreach ($series as &$serie) ksort($serie['valores']);
    unset($serie);
    uasort($series, static fn(array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
    return $series;
}
