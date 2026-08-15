<?php
declare(strict_types=1);

/** Indicadores comparables de Eurostat y Banco Mundial–UNICEF. */

const POBREZA_COMPARABLE_TTL = 604800;
const POBREZA_COMPARABLE_MAXIMA = 2592000;

/** @return array{series:array<string,array{nombre:string,valores:array<int,float>}>,actualizado:int,cache:bool} */
function obtenerPobrezaInfantilRelativaEurostat(): array
{
    $archivo = ROOT_PATH . 'storage/cache/pobreza/eurostat/infantil-relativa.json';
    return obtenerIndicadorPobrezaConCache($archivo, static function (): array {
        $json = descargarDatosPobrezaSeguros(
            'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/'
                . 'ilc_li02?lang=en&age=Y_LT18&sex=T&unit=PC&rskpovth=B_60&statinfo=MED_EI',
            524288,
            'application/json'
        );
        return normalizarPobrezaInfantilEurostat($json);
    });
}

/** @return array{series:array<string,array{nombre:string,valores:array<int,float>}>,actualizado:int,cache:bool} */
function obtenerPobrezaInfantilMundial(): array
{
    $archivo = ROOT_PATH . 'storage/cache/pobreza/banco-mundial/infantil-global.json';
    return obtenerIndicadorPobrezaConCache($archivo, static function (): array {
        $html = descargarDatosPobrezaSeguros(
            'https://flo.uri.sh/visualisation/24764612/embed',
            1048576,
            'text/html'
        );
        if (!preg_match('/_Flourish_data\s*=\s*\{"data":\[(.*)\]\},\s*_Flourish_visualisation_id/sU', $html, $coincidencia)) {
            throw new RuntimeException('No se encontró la serie mundial infantil.');
        }
        preg_match_all('/new Date\((\d+)\).*?"value":\[([0-9.]+)\]/', $coincidencia[1], $filas, PREG_SET_ORDER);
        $valores = [];
        foreach ($filas as $fila) {
            $anyo = (int) gmdate('Y', (int) floor(((int) $fila[1]) / 1000));
            $valores[$anyo] = round((float) $fila[2], 1);
        }
        return $valores === [] ? [] : ['mundo' => ['nombre' => 'Mundo', 'valores' => $valores]];
    });
}

/** @return array{series:array<string,array{nombre:string,valores:array<int,float>}>,actualizado:int,cache:bool} */
function obtenerIndicadorPobrezaConCache(string $archivo, callable $descargar): array
{
    $cache = leerCachePobrezaIne($archivo);
    if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_COMPARABLE_TTL) {
        $cache['cache'] = true;
        return $cache;
    }
    $bloqueo = obtenerBloqueoActualizacionPobreza($archivo);
    if ($bloqueo === null && $cache !== null) {
        $cache['cache'] = true;
        return $cache;
    }
    try {
        $series = $descargar();
        if (!is_array($series) || $series === []) throw new RuntimeException('El proveedor no devolvió datos utilizables.');
        $resultado = ['series' => $series, 'actualizado' => time(), 'cache' => false];
        guardarCachePobrezaIne($archivo, $resultado);
        return $resultado;
    } catch (Throwable $error) {
        if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_COMPARABLE_MAXIMA) {
            $cache['cache'] = true;
            return $cache;
        }
        throw $error;
    } finally {
        liberarBloqueoActualizacionPobreza($bloqueo);
    }
}
