<?php
declare(strict_types=1);

/** Índice de pobreza multidimensional global PNUD/OPHI 2025. */

const POBREZA_MPI_TTL = 2592000;
const POBREZA_MPI_MAXIMA = 7776000;

/** @return array{series:array<string,array{nombre:string,anyo:string,valor:float,intensidad:float}>,actualizado:int,cache:bool} */
function obtenerPobrezaMultidimensionalPnud(): array
{
    $archivo = ROOT_PATH . 'storage/cache/pobreza/pnud/mpi-2025.json';
    $cache = leerCachePobrezaIne($archivo);
    if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_MPI_TTL) {
        $cache['cache'] = true;
        return $cache;
    }
    $bloqueo = obtenerBloqueoActualizacionPobreza($archivo);
    try {
        $xlsx = descargarDatosPobrezaSeguros(
            'https://hdr.undp.org/sites/default/files/publications/additional-files/2025-10/2025_gMPI_Table1and2.xlsx',
            524288,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $series = normalizarPobrezaMpiXlsx($xlsx);
        if ($series === []) throw new RuntimeException('PNUD no devolvió datos MPI utilizables.');
        $resultado = ['series' => $series, 'actualizado' => time(), 'cache' => false];
        guardarCachePobrezaIne($archivo, $resultado);
        return $resultado;
    } catch (Throwable $error) {
        if ($cache !== null && time() - $cache['actualizado'] <= POBREZA_MPI_MAXIMA) {
            $cache['cache'] = true;
            return $cache;
        }
        throw $error;
    } finally {
        liberarBloqueoActualizacionPobreza($bloqueo);
    }
}

/** @return array<string,array{nombre:string,anyo:string,valor:float,intensidad:float}> */
function normalizarPobrezaMpiXlsx(string $xlsx): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('No se puede leer la tabla MPI.');
    $temporal = tempnam(sys_get_temp_dir(), 'mpi-');
    if ($temporal === false || file_put_contents($temporal, $xlsx, LOCK_EX) === false) throw new RuntimeException('No se pudo preparar la tabla MPI.');
    $zip = new ZipArchive();
    try {
        if ($zip->open($temporal) !== true) throw new RuntimeException('La tabla MPI no es válida.');
        $compartidas = simplexml_load_string((string) $zip->getFromName('xl/sharedStrings.xml'));
        $hoja = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        if ($compartidas === false || $hoja === false) throw new RuntimeException('La tabla MPI está incompleta.');
        $textos = [];
        foreach ($compartidas->si as $texto) $textos[] = trim((string) $texto->t);
        $hoja->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $resultado = [];
        foreach ($hoja->xpath('//m:sheetData/m:row') ?: [] as $fila) {
            if ((int) $fila['r'] < 8) continue;
            $celdas = [];
            foreach ($fila->c as $celda) {
                $columna = preg_replace('/\d+/', '', (string) $celda['r']);
                $valor = (string) $celda->v;
                if ((string) $celda['t'] === 's') $valor = $textos[(int) $valor] ?? '';
                $celdas[$columna] = $valor;
            }
            if (($celdas['A'] ?? '') === '' || !is_numeric($celdas['F'] ?? null) || !is_numeric($celdas['L'] ?? null)) continue;
            $nombre = trim($celdas['A']);
            $clave = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre) ?: $nombre)), '-');
            $resultado[$clave] = ['nombre' => $nombre, 'anyo' => trim($celdas['B'] ?? ''), 'valor' => round((float) $celdas['F'], 1), 'intensidad' => round((float) $celdas['L'], 1)];
        }
        uasort($resultado, static fn(array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
        return $resultado;
    } finally {
        $zip->close();
        @unlink($temporal);
    }
}
