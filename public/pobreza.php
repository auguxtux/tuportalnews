<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/modules/pobreza.php';
require_once __DIR__ . '/../includes/modules/pobreza-onu.php';
require_once __DIR__ . '/../includes/modules/pobreza-unicef.php';
require_once __DIR__ . '/../includes/modules/pobreza-comparable.php';
require_once __DIR__ . '/../includes/modules/pobreza-mpi.php';

$error = null;
$resultado = null;
$series = [];
$seleccionadas = [];
$anyoInicio = null;
$anyoFin = null;
$anyosDisponibles = [];
$datosGrafica = [];
$gobiernosEspana = obtenerGobiernosEspanaPobreza();
$gobiernosAutonomicos = obtenerGobiernosAutonomicosPobreza();
$gobiernosAutonomicosSeleccionados = [];
$resultadoInfantil = null;
$seriesInfantiles = [];
$datosGraficaInfantil = [];
$errorInfantil = null;
$paisesSeleccionados = [];
$gobiernosEuropeos = obtenerGobiernosEuropeosPobreza();
$gobiernosEuropeosSeleccionados = [];
$resultadoOnu = null;
$seriesOnu = [];
$seleccionOnu = [];
$datosGraficaOnu = [];
$anyosOnu = [];
$errorOnu = null;
$paisesOnu = obtenerPaisesPobrezaOnu();
$resultadoUnicef = null;
$seriesUnicef = [];
$seleccionUnicef = [];
$datosGraficaUnicef = [];
$anyosUnicef = [];
$errorUnicef = null;
$datosGraficaRelativa = [];
$errorRelativa = null;
$datosGraficaMundial = [];
$errorMundial = null;
$seriesMpi = [];
$seleccionMpi = [];
$errorMpi = null;
$formularioFiltrosCerrado = isset($_GET['desde'])
    || isset($_GET['hasta'])
    || isset($_GET['comunidades'])
    || isset($_GET['paises'])
    || isset($_GET['onu'])
    || isset($_GET['unicef'])
    || isset($_GET['mpi']);

try {
    $resultado = obtenerPobrezaComunidadesIne();
    $series = $resultado['series'];

    foreach ($series as $serie) {
        $anyosDisponibles = array_merge($anyosDisponibles, array_keys($serie['valores']));
    }
    $anyosDisponibles = array_values(array_unique($anyosDisponibles));
    sort($anyosDisponibles);

    $anyoMinimo = $anyosDisponibles[0] ?? (int) date('Y');
    $anyoMaximo = $anyosDisponibles[count($anyosDisponibles) - 1] ?? (int) date('Y');
    $anyoInicio = filter_input(INPUT_GET, 'desde', FILTER_VALIDATE_INT);
    $anyoFin = filter_input(INPUT_GET, 'hasta', FILTER_VALIDATE_INT);
    $anyoInicio = is_int($anyoInicio) && in_array($anyoInicio, $anyosDisponibles, true)
        ? $anyoInicio
        : max($anyoMinimo, $anyoMaximo - 9);
    $anyoFin = is_int($anyoFin) && in_array($anyoFin, $anyosDisponibles, true)
        ? $anyoFin
        : $anyoMaximo;

    if ($anyoInicio > $anyoFin) {
        [$anyoInicio, $anyoFin] = [$anyoFin, $anyoInicio];
    }

    $solicitadas = $_GET['comunidades'] ?? [];
    $solicitadas = is_array($solicitadas) ? $solicitadas : [];
    foreach ($solicitadas as $clave) {
        $clave = trim((string) $clave);
        if ($clave !== 'espana' && isset($series[$clave]) && !in_array($clave, $seleccionadas, true)) {
            $seleccionadas[] = $clave;
        }
        if (count($seleccionadas) === 6) {
            break;
        }
    }

    if ($seleccionadas === []) {
        $seleccionadas = array_values(array_filter(
            ['canarias', 'andalucia'],
            static fn(string $clave): bool => isset($series[$clave])
        ));
    }

    $clavesGrafica = array_merge(['espana'], $seleccionadas);
    foreach ($clavesGrafica as $clave) {
        if (!isset($series[$clave])) {
            continue;
        }
        $valores = array_filter(
            $series[$clave]['valores'],
            static fn(float $valor, int $anyo): bool => $anyo >= $anyoInicio && $anyo <= $anyoFin,
            ARRAY_FILTER_USE_BOTH
        );
        $datosGrafica[] = [
            'nombre' => $series[$clave]['nombre'],
            'valores' => $valores,
            'nacional' => $clave === 'espana',
        ];
    }

    foreach ($seleccionadas as $clave) {
        if (isset($gobiernosAutonomicos[$clave], $series[$clave])) {
            $gobiernosAutonomicosSeleccionados[$clave] = [
                'nombre' => $series[$clave]['nombre'],
                'periodos' => $gobiernosAutonomicos[$clave],
            ];
        }
    }

} catch (Throwable $e) {
    $error = 'No se pudieron obtener los datos de pobreza en este momento.';
    error_log('[INE] No se pudo cargar la tabla de pobreza.');
}

try {
    $resultadoInfantil = obtenerPobrezaInfantilEurostat();
    $seriesInfantiles = $resultadoInfantil['series'];

    $paisesSolicitados = $_GET['paises'] ?? [];
    $paisesSolicitados = is_array($paisesSolicitados) ? $paisesSolicitados : [];
    foreach ($paisesSolicitados as $clave) {
        $clave = trim((string) $clave);
        if ($clave !== 'ue27' && isset($seriesInfantiles[$clave]) && !in_array($clave, $paisesSeleccionados, true)) {
            $paisesSeleccionados[] = $clave;
        }
        if (count($paisesSeleccionados) === 6) {
            break;
        }
    }
    if ($paisesSeleccionados === []) {
        $paisesSeleccionados = ['espana'];
    }

    foreach (array_merge(['ue27'], $paisesSeleccionados) as $clave) {
        if (!isset($seriesInfantiles[$clave])) {
            continue;
        }
        $valores = array_filter(
            $seriesInfantiles[$clave]['valores'],
            static fn(float $valor, int $anyo): bool => $anyo >= (int) $anyoInicio && $anyo <= (int) $anyoFin,
            ARRAY_FILTER_USE_BOTH
        );
        $datosGraficaInfantil[] = [
            'nombre' => $seriesInfantiles[$clave]['nombre'],
            'valores' => $valores,
        ];
    }

    foreach ($paisesSeleccionados as $clave) {
        if (isset($gobiernosEuropeos[$clave], $seriesInfantiles[$clave])) {
            $gobiernosEuropeosSeleccionados[$clave] = [
                'nombre' => $seriesInfantiles[$clave]['nombre'],
                'fuente' => $gobiernosEuropeos[$clave]['fuente'],
                'periodos' => $gobiernosEuropeos[$clave]['periodos'],
            ];
        }
    }

} catch (Throwable) {
    $errorInfantil = 'Los datos infantiles de Eurostat no están disponibles temporalmente.';
    error_log('[EUROSTAT] No se pudo cargar la serie AROPE infantil.');
}

try {
    $solicitadasOnu = is_array($_GET['onu'] ?? null) ? $_GET['onu'] : [];
    foreach ($solicitadasOnu as $codigo) {
        $codigo = str_pad((string) (int) $codigo, 3, '0', STR_PAD_LEFT);
        if (isset($paisesOnu[$codigo]) && !in_array($codigo, $seleccionOnu, true)) $seleccionOnu[] = $codigo;
        if (count($seleccionOnu) === 6) break;
    }
    if ($seleccionOnu === []) {
        $seleccionOnu = ['724', '484', '152'];
    }
    $resultadoOnu = obtenerPobrezaMultidimensionalOnu($seleccionOnu);
    $seriesOnu = $resultadoOnu['series'];
    foreach ($seleccionOnu as $codigo) {
        $valores = array_filter(
            $seriesOnu[$codigo]['valores'],
            static fn(float $valor, int $anyo): bool => $anyo >= (int) $anyoInicio && $anyo <= (int) $anyoFin,
            ARRAY_FILTER_USE_BOTH
        );
        $datosGraficaOnu[] = ['nombre' => $seriesOnu[$codigo]['nombre'], 'valores' => $valores];
        $anyosOnu = array_merge($anyosOnu, array_keys($valores));
    }
    $anyosOnu = array_values(array_unique($anyosOnu));
    sort($anyosOnu);
} catch (Throwable $errorProveedorOnu) {
    $errorOnu = 'Los datos de Naciones Unidas no están disponibles temporalmente.';
    registrarErrorInterno('POBREZA.ONU', $errorProveedorOnu);
}

try {
    $resultadoUnicef = obtenerPobrezaInfantilUnicef();
    $seriesUnicef = $resultadoUnicef['series'];
    $solicitadasUnicef = is_array($_GET['unicef'] ?? null) ? $_GET['unicef'] : [];
    foreach ($solicitadasUnicef as $iso) {
        $iso = strtoupper(trim((string) $iso));
        if (isset($seriesUnicef[$iso]) && !in_array($iso, $seleccionUnicef, true)) $seleccionUnicef[] = $iso;
        if (count($seleccionUnicef) === 6) break;
    }
    if ($seleccionUnicef === []) {
        $seleccionUnicef = array_values(array_filter(['MEX', 'ARG', 'CHL'], static fn(string $iso): bool => isset($seriesUnicef[$iso])));
    }
    foreach ($seleccionUnicef as $iso) {
        $valores = array_filter(
            $seriesUnicef[$iso]['valores'],
            static fn(float $valor, int $anyo): bool => $anyo >= (int) $anyoInicio && $anyo <= (int) $anyoFin,
            ARRAY_FILTER_USE_BOTH
        );
        $datosGraficaUnicef[] = ['nombre' => $seriesUnicef[$iso]['nombre'], 'valores' => $valores];
        $anyosUnicef = array_merge($anyosUnicef, array_keys($valores));
    }
    $anyosUnicef = array_values(array_unique($anyosUnicef));
    sort($anyosUnicef);
} catch (Throwable $errorProveedorUnicef) {
    $errorUnicef = 'Los datos de UNICEF no están disponibles temporalmente.';
    registrarErrorInterno('POBREZA.UNICEF', $errorProveedorUnicef);
}

try {
    $relativa = obtenerPobrezaInfantilRelativaEurostat();
    foreach (array_merge(['ue27'], $paisesSeleccionados) as $clave) {
        if (!isset($relativa['series'][$clave])) continue;
        $valores = array_filter($relativa['series'][$clave]['valores'], static fn(float $valor, int $anyo): bool => $anyo >= (int) $anyoInicio && $anyo <= (int) $anyoFin, ARRAY_FILTER_USE_BOTH);
        $datosGraficaRelativa[] = ['nombre' => $relativa['series'][$clave]['nombre'], 'valores' => $valores];
    }
} catch (Throwable $e) {
    $errorRelativa = 'La pobreza infantil relativa no está disponible temporalmente.';
    registrarErrorInterno('POBREZA.RELATIVA', $e);
}

try {
    $mundial = obtenerPobrezaInfantilMundial();
    $datosGraficaMundial = array_values($mundial['series']);
} catch (Throwable $e) {
    $errorMundial = 'La tendencia mundial infantil no está disponible temporalmente.';
    registrarErrorInterno('POBREZA.MUNDIAL', $e);
}

try {
    $mpi = obtenerPobrezaMultidimensionalPnud();
    $seriesMpi = $mpi['series'];
    $solicitadasMpi = is_array($_GET['mpi'] ?? null) ? $_GET['mpi'] : [];
    foreach ($solicitadasMpi as $clave) {
        $clave = trim((string) $clave);
        if (isset($seriesMpi[$clave]) && !in_array($clave, $seleccionMpi, true)) $seleccionMpi[] = $clave;
        if (count($seleccionMpi) === 6) break;
    }
    if ($seleccionMpi === []) {
        foreach (['morocco', 'mexico', 'argentina'] as $clave) if (isset($seriesMpi[$clave])) $seleccionMpi[] = $clave;
    }
} catch (Throwable $e) {
    $errorMpi = 'El índice multidimensional global no está disponible temporalmente.';
    registrarErrorInterno('POBREZA.MPI', $e);
}

$titulo_pagina = 'Pobreza por comunidades autónomas';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('public-pobreza.css'), ENT_QUOTES, 'UTF-8'); ?>">
<script defer src="<?= htmlspecialchars(js_url('public-pobreza.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<main class="pobreza-pagina">
    <header class="pobreza-cabecera">
        <p class="pobreza-etiqueta">📊 Estadísticas oficiales</p>
        <h1>Pobreza: INE - EuroStat - ONU - UNICEF</h1>
        <p>Compara territorios y países con datos oficiales de INE, Eurostat, Naciones Unidas y UNICEF.</p>
    </header>

    <nav class="pobreza-navegacion" aria-label="Conjuntos de estadísticas">
        <a href="#pobreza-grafica-titulo"><span>🇪🇸</span><strong>INE</strong><small>Comunidades</small></a>
        <a href="#pobreza-infantil-titulo"><span>🇪🇺</span><strong>Eurostat</strong><small>Infancia UE</small></a>
        <a href="#pobreza-onu-titulo"><span>🌍</span><strong>ONU</strong><small>Multidimensional</small></a>
        <a href="#pobreza-unicef-titulo"><span>👧</span><strong>UNICEF</strong><small>Pobreza infantil</small></a>
    </nav>

    <?php if ($error !== null): ?>
        <div class="pobreza-aviso pobreza-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
        <button
            id="pobrezaAlternarFiltros"
            class="pobreza-alternar-filtros"
            type="button"
            aria-controls="pobrezaFormularioFiltros"
            aria-expanded="<?= $formularioFiltrosCerrado ? 'false' : 'true'; ?>"
        ><span aria-hidden="true">⚙️</span> <span data-texto-filtros><?= $formularioFiltrosCerrado ? 'Cambiar selección y periodo' : 'Ocultar selección'; ?></span></button>

        <form
            id="pobrezaFormularioFiltros"
            class="pobreza-filtros<?= $formularioFiltrosCerrado ? ' pobreza-filtros-cerrados' : ''; ?>"
            method="get"
        >
            <div class="pobreza-filtros-cabecera">
                <div>
                    <h2>Configura la comparación</h2>
                    <p>Elige el periodo y hasta seis territorios en cada conjunto.</p>
                </div>
                <span><?= (int) $anyoInicio; ?>–<?= (int) $anyoFin; ?></span>
            </div>

            <div class="pobreza-periodo" aria-label="Periodo de comparación">
                <strong>Periodo</strong>
                <label>Desde
                    <select name="desde">
                        <?php foreach ($anyosDisponibles as $anyo): ?>
                            <option value="<?= $anyo; ?>" <?= $anyo === $anyoInicio ? 'selected' : ''; ?>><?= $anyo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Hasta
                    <select name="hasta">
                        <?php foreach ($anyosDisponibles as $anyo): ?>
                            <option value="<?= $anyo; ?>" <?= $anyo === $anyoFin ? 'selected' : ''; ?>><?= $anyo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="pobreza-filtros-grid">
            <fieldset class="pobreza-selector-grupo">
                <legend><span aria-hidden="true">🇪🇸</span> Comunidades · INE</legend>
                <p class="pobreza-ayuda">Selecciona hasta seis. España aparece siempre como referencia.</p>
                <details class="pobreza-desplegable" data-selector-multiple data-singular="seleccionada" data-plural="seleccionadas">
                    <summary>
                        Seleccionar comunidades
                        <span data-resumen-seleccion><?= count($seleccionadas); ?> seleccionadas</span>
                    </summary>
                    <div class="pobreza-desplegable-panel">
                        <input type="search" placeholder="Buscar comunidad…" aria-label="Buscar comunidad" data-buscar-opciones>
                        <div class="pobreza-comunidades" data-lista-opciones>
                    <?php foreach ($series as $clave => $serie): ?>
                        <?php if ($clave === 'espana') continue; ?>
                        <label>
                            <input type="checkbox" name="comunidades[]" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>" <?= in_array($clave, $seleccionadas, true) ? 'checked' : ''; ?>>
                            <?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                        </div>
                        <button type="button" class="pobreza-limpiar-seleccion" data-limpiar-seleccion>Quitar todas</button>
                    </div>
                </details>
            </fieldset>

            <fieldset class="pobreza-paises-filtro pobreza-selector-grupo">
                <legend><span aria-hidden="true">🇪🇺</span> Países · Eurostat</legend>
                <p class="pobreza-ayuda">Selecciona hasta seis países. La UE-27 aparece siempre como referencia.</p>
                <details class="pobreza-desplegable" data-selector-multiple data-singular="seleccionado" data-plural="seleccionados">
                    <summary>
                        Seleccionar países
                        <span data-resumen-seleccion><?= count($paisesSeleccionados); ?> seleccionados</span>
                    </summary>
                    <div class="pobreza-desplegable-panel">
                        <input type="search" placeholder="Buscar país…" aria-label="Buscar país" data-buscar-opciones>
                        <div class="pobreza-comunidades" data-lista-opciones>
                    <?php foreach ($seriesInfantiles as $clave => $serie): ?>
                        <?php if ($clave === 'ue27') continue; ?>
                        <label>
                            <input type="checkbox" name="paises[]" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>" <?= in_array($clave, $paisesSeleccionados, true) ? 'checked' : ''; ?>>
                            <?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                        </div>
                        <button type="button" class="pobreza-limpiar-seleccion" data-limpiar-seleccion>Quitar todos</button>
                    </div>
                </details>
            </fieldset>

            <?php if ($paisesOnu !== []): ?>
            <fieldset class="pobreza-paises-filtro pobreza-selector-grupo">
                <legend><span aria-hidden="true">🌍</span> Países · ONU</legend>
                <p class="pobreza-ayuda">Selecciona hasta seis países con datos oficiales del indicador ODS 1.2.2.</p>
                <details class="pobreza-desplegable" data-selector-multiple data-singular="seleccionado" data-plural="seleccionados">
                    <summary>Seleccionar países ONU <span data-resumen-seleccion><?= count($seleccionOnu); ?> seleccionados</span></summary>
                    <div class="pobreza-desplegable-panel">
                        <input type="search" placeholder="Buscar país…" aria-label="Buscar país ONU" data-buscar-opciones>
                        <div class="pobreza-comunidades" data-lista-opciones>
                        <?php foreach ($paisesOnu as $codigo => $pais): ?>
                            <?php $codigo = str_pad((string) $codigo, 3, '0', STR_PAD_LEFT); ?>
                            <label><input type="checkbox" name="onu[]" value="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>" <?= in_array($codigo, $seleccionOnu, true) ? 'checked' : ''; ?>><?= htmlspecialchars($pais['nombre'], ENT_QUOTES, 'UTF-8'); ?></label>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="pobreza-limpiar-seleccion" data-limpiar-seleccion>Quitar todos</button>
                    </div>
                </details>
            </fieldset>
            <?php endif; ?>

            <?php if ($seriesUnicef !== []): ?>
            <fieldset class="pobreza-paises-filtro pobreza-selector-grupo">
                <legend><span aria-hidden="true">👧</span> Países · UNICEF</legend>
                <p class="pobreza-ayuda">Selecciona hasta seis países con datos disponibles sobre hogares bajo la línea nacional de pobreza.</p>
                <details class="pobreza-desplegable" data-selector-multiple data-singular="seleccionado" data-plural="seleccionados">
                    <summary>Seleccionar países UNICEF <span data-resumen-seleccion><?= count($seleccionUnicef); ?> seleccionados</span></summary>
                    <div class="pobreza-desplegable-panel">
                        <input type="search" placeholder="Buscar país…" aria-label="Buscar país UNICEF" data-buscar-opciones>
                        <div class="pobreza-comunidades" data-lista-opciones>
                        <?php foreach ($seriesUnicef as $iso => $serie): ?>
                            <label><input type="checkbox" name="unicef[]" value="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8'); ?>" <?= in_array($iso, $seleccionUnicef, true) ? 'checked' : ''; ?>><?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?></label>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="pobreza-limpiar-seleccion" data-limpiar-seleccion>Quitar todos</button>
                    </div>
                </details>
            </fieldset>
            <?php endif; ?>

            <?php if ($seriesMpi !== []): ?>
            <fieldset class="pobreza-paises-filtro pobreza-selector-grupo">
                <legend><span aria-hidden="true">🧭</span> Países · PNUD/OPHI</legend>
                <p class="pobreza-ayuda">Hasta seis países cubiertos por el índice multidimensional global.</p>
                <details class="pobreza-desplegable" data-selector-multiple data-singular="seleccionado" data-plural="seleccionados">
                    <summary>Seleccionar países MPI <span data-resumen-seleccion><?= count($seleccionMpi); ?> seleccionados</span></summary>
                    <div class="pobreza-desplegable-panel">
                        <input type="search" placeholder="Buscar país…" aria-label="Buscar país PNUD" data-buscar-opciones>
                        <div class="pobreza-comunidades" data-lista-opciones>
                        <?php foreach ($seriesMpi as $clave => $serie): ?>
                            <label><input type="checkbox" name="mpi[]" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>" <?= in_array($clave, $seleccionMpi, true) ? 'checked' : ''; ?>><?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?></label>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="pobreza-limpiar-seleccion" data-limpiar-seleccion>Quitar todos</button>
                    </div>
                </details>
            </fieldset>
            <?php endif; ?>
            </div>

            <div class="pobreza-acciones-filtro">
                <button type="submit">Aplicar comparación</button>
            </div>
        </form>

        <section class="pobreza-panel" aria-labelledby="pobreza-grafica-titulo">
            <div class="pobreza-panel-cabecera">
                <div>
                    <h2 id="pobreza-grafica-titulo">Evolución anual</h2>
                    <p>Porcentaje de población por debajo del 60 % de la mediana nacional de ingresos.</p>
                </div>
                <span><?= (int) $anyoInicio; ?>–<?= (int) $anyoFin; ?></span>
            </div>
            <?php if ($gobiernosAutonomicosSeleccionados !== []): ?>
                <div class="pobreza-contexto-regional" aria-label="Gobiernos regionales">
                    <strong>Gobiernos:</strong>
                    <label>
                        <input id="pobrezaMostrarGobiernoEspana" type="checkbox" checked>
                        España
                    </label>
                    <?php foreach ($gobiernosAutonomicosSeleccionados as $clave => $gobierno): ?>
                        <label>
                            <input class="pobreza-mostrar-gobierno-regional" type="checkbox" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($gobierno['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>
            <div class="pobreza-grafica-contenedor">
                <canvas id="pobrezaGrafica" aria-label="Gráfica de evolución de la tasa de pobreza"></canvas>
            </div>
            <div id="pobrezaLeyenda" class="pobreza-leyenda"></div>
        </section>

        <section class="pobreza-panel pobreza-internacional" aria-labelledby="pobreza-relativa-titulo">
            <div class="pobreza-panel-cabecera"><div><p class="pobreza-etiqueta">⚖️ Comparación homogénea</p><h2 id="pobreza-relativa-titulo">Pobreza monetaria infantil relativa</h2><p>Menores de 18 años en hogares con ingresos inferiores al 60 % de la mediana nacional, después de transferencias sociales.</p></div><span>Eurostat · UNICEF</span></div>
            <p class="pobreza-definicion"><strong>Qué mide:</strong> desigualdad económica infantil dentro de cada país. Es comparable entre Europa y países ricos con la misma metodología, pero no equivale a pobreza extrema.</p>
            <?php if ($errorRelativa !== null): ?><div class="pobreza-aviso pobreza-error"><?= htmlspecialchars($errorRelativa, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($datosGraficaRelativa !== []): ?><div class="pobreza-grafica-contenedor"><canvas data-pobreza-grafica-simple data-datos="pobrezaDatosRelativa" data-leyenda="pobrezaLeyendaRelativa" aria-label="Gráfica de pobreza infantil relativa"></canvas></div><div id="pobrezaLeyendaRelativa" class="pobreza-leyenda"></div><?php endif; ?>
        </section>

        <section class="pobreza-panel pobreza-internacional" aria-labelledby="pobreza-mundial-titulo">
            <div class="pobreza-panel-cabecera"><div><p class="pobreza-etiqueta">🌐 Umbral internacional común</p><h2 id="pobreza-mundial-titulo">Pobreza infantil extrema mundial</h2><p>Porcentaje mundial de menores en hogares con menos de 3 dólares diarios por persona, ajustados por poder adquisitivo de 2021.</p></div><span>Banco Mundial · UNICEF</span></div>
            <p class="pobreza-definicion"><strong>Qué mide:</strong> carencia económica extrema aplicando el mismo poder de compra. La fuente pública ofrece esta tendencia mundial, no una clasificación completa por país.</p>
            <?php if ($errorMundial !== null): ?><div class="pobreza-aviso pobreza-error"><?= htmlspecialchars($errorMundial, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($datosGraficaMundial !== []): ?><div class="pobreza-grafica-contenedor"><canvas data-pobreza-grafica-simple data-datos="pobrezaDatosMundial" data-leyenda="pobrezaLeyendaMundial" aria-label="Tendencia mundial de pobreza infantil extrema"></canvas></div><div id="pobrezaLeyendaMundial" class="pobreza-leyenda"></div><?php endif; ?>
        </section>

        <section class="pobreza-panel pobreza-internacional" aria-labelledby="pobreza-mpi-titulo">
            <div class="pobreza-panel-cabecera"><div><p class="pobreza-etiqueta">🧭 Privaciones simultáneas</p><h2 id="pobreza-mpi-titulo">Índice de pobreza multidimensional global</h2><p>Porcentaje de población con carencias simultáneas en salud, educación y condiciones de vida.</p></div><span>PNUD · OPHI 2025</span></div>
            <p class="pobreza-definicion"><strong>Qué mide:</strong> diez privaciones comunes, como nutrición, escolarización, agua, saneamiento, electricidad y vivienda. Solo incluye países con encuestas compatibles; normalmente no cubre países ricos.</p>
            <?php if ($errorMpi !== null): ?><div class="pobreza-aviso pobreza-error"><?= htmlspecialchars($errorMpi, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($seleccionMpi !== []): ?><div class="pobreza-mpi-grid"><?php foreach ($seleccionMpi as $clave): $dato = $seriesMpi[$clave]; ?><article><h3><?= htmlspecialchars($dato['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3><strong><?= number_format($dato['valor'], 1, ',', ''); ?> %</strong><span>Población multidimensionalmente pobre</span><small>Encuesta <?= htmlspecialchars($dato['anyo'], ENT_QUOTES, 'UTF-8'); ?> · intensidad <?= number_format($dato['intensidad'], 1, ',', ''); ?> %</small></article><?php endforeach; ?></div><?php endif; ?>
        </section>

        <section class="pobreza-tabla-seccion" aria-labelledby="pobreza-tabla-titulo">
            <h2 id="pobreza-tabla-titulo">Valores exactos (%)</h2>
            <div class="pobreza-tabla-scroll">
                <table>
                    <thead><tr><th>Territorio</th><?php for ($anyo = $anyoInicio; $anyo <= $anyoFin; $anyo++): ?><th><?= $anyo; ?></th><?php endfor; ?></tr></thead>
                    <tbody>
                    <?php foreach ($datosGrafica as $serie): ?>
                        <tr><th><?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?></th><?php for ($anyo = $anyoInicio; $anyo <= $anyoFin; $anyo++): ?><td><?= isset($serie['valores'][$anyo]) ? number_format((float) $serie['valores'][$anyo], 1, ',', '') : '—'; ?></td><?php endfor; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pobreza-panel pobreza-infantil" aria-labelledby="pobreza-infantil-titulo">
            <div class="pobreza-panel-cabecera">
                <div>
                    <p class="pobreza-etiqueta">👧 Menores de 18 años</p>
                    <h2 id="pobreza-infantil-titulo">Riesgo de pobreza o exclusión social (AROPE)</h2>
                    <p>Comparación de España con la Unión Europea. Esta serie es nacional y no representa datos infantiles por comunidad.</p>
                </div>
                <span>Eurostat</span>
            </div>

            <?php if ($errorInfantil !== null): ?>
                <div class="pobreza-aviso pobreza-error" role="status"><?= htmlspecialchars($errorInfantil, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($datosGraficaInfantil === [] || array_sum(array_map(static fn(array $serie): int => count($serie['valores']), $datosGraficaInfantil)) === 0): ?>
                <p class="pobreza-aviso">El periodo seleccionado no contiene datos infantiles. Eurostat dispone de esta serie desde 2015.</p>
            <?php else: ?>
                <?php if ($gobiernosEuropeosSeleccionados !== []): ?>
                    <div class="pobreza-contexto-regional pobreza-contexto-europeo" aria-label="Gobiernos de países europeos">
                        <strong>Gobiernos:</strong>
                        <?php foreach ($gobiernosEuropeosSeleccionados as $clave => $gobierno): ?>
                            <label>
                                <input class="pobreza-mostrar-gobierno-europeo" type="checkbox" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>">
                                 <?= htmlspecialchars($gobierno['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>
                <div class="pobreza-grafica-contenedor">
                    <canvas id="pobrezaGraficaInfantil" aria-label="Gráfica de pobreza infantil en España y la Unión Europea"></canvas>
                </div>
                <div id="pobrezaLeyendaInfantil" class="pobreza-leyenda"></div>
                <div class="pobreza-tabla-scroll">
                    <table>
                        <thead><tr><th>Territorio</th><?php foreach (array_keys($datosGraficaInfantil[0]['valores'] ?? []) as $anyo): ?><th><?= (int) $anyo; ?></th><?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($datosGraficaInfantil as $serie): ?><tr><th><?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?></th><?php foreach (($datosGraficaInfantil[0]['valores'] ?? []) as $anyo => $_): ?><td><?= isset($serie['valores'][$anyo]) ? number_format((float) $serie['valores'][$anyo], 1, ',', '') : '—'; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="pobreza-panel pobreza-internacional" aria-labelledby="pobreza-onu-titulo">
            <div class="pobreza-panel-cabecera">
                <div>
                    <p class="pobreza-etiqueta">🌍 Indicador ODS 1.2.2</p>
                    <h2 id="pobreza-onu-titulo">Pobreza multidimensional</h2>
                    <p>Proporción de la población que vive en pobreza multidimensional según la definición nacional de cada país.</p>
                </div>
                <span>Naciones Unidas</span>
            </div>
            <?php if ($errorOnu !== null): ?>
                <div class="pobreza-aviso pobreza-error" role="status"><?= htmlspecialchars($errorOnu, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($anyosOnu === []): ?>
                <p class="pobreza-aviso">El periodo o los países seleccionados no contienen observaciones comparables de ONU.</p>
            <?php else: ?>
                <div class="pobreza-grafica-contenedor"><canvas id="pobrezaGraficaOnu" data-pobreza-grafica-simple data-datos="pobrezaDatosOnu" data-leyenda="pobrezaLeyendaOnu" aria-label="Gráfica de pobreza multidimensional de Naciones Unidas"></canvas></div>
                <div id="pobrezaLeyendaOnu" class="pobreza-leyenda"></div>
                <div class="pobreza-tabla-scroll">
                    <table><thead><tr><th>País</th><?php foreach ($anyosOnu as $anyo): ?><th><?= (int) $anyo; ?></th><?php endforeach; ?></tr></thead>
                    <tbody><?php foreach ($datosGraficaOnu as $serie): ?><tr><th><?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?></th><?php foreach ($anyosOnu as $anyo): ?><td><?= isset($serie['valores'][$anyo]) ? number_format((float) $serie['valores'][$anyo], 1, ',', '') : '—'; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
                </div>
            <?php endif; ?>
        </section>

        <section class="pobreza-panel pobreza-internacional" aria-labelledby="pobreza-unicef-titulo">
            <div class="pobreza-panel-cabecera">
                <div>
                    <p class="pobreza-etiqueta">👧 Pobreza infantil</p>
                    <h2 id="pobreza-unicef-titulo">Niños en hogares bajo la línea nacional de pobreza</h2>
                    <p>Porcentaje de niños que viven en hogares con ingresos inferiores a la línea nacional de pobreza de cada país.</p>
                </div>
                <span>UNICEF</span>
            </div>
            <?php if ($errorUnicef !== null): ?>
                <div class="pobreza-aviso pobreza-error" role="status"><?= htmlspecialchars($errorUnicef, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($anyosUnicef === []): ?>
                <p class="pobreza-aviso">El periodo o los países seleccionados no contienen observaciones de UNICEF.</p>
            <?php else: ?>
                <div class="pobreza-grafica-contenedor"><canvas id="pobrezaGraficaUnicef" data-pobreza-grafica-simple data-datos="pobrezaDatosUnicef" data-leyenda="pobrezaLeyendaUnicef" aria-label="Gráfica de pobreza infantil de UNICEF"></canvas></div>
                <div id="pobrezaLeyendaUnicef" class="pobreza-leyenda"></div>
                <div class="pobreza-tabla-scroll">
                    <table><thead><tr><th>País</th><?php foreach ($anyosUnicef as $anyo): ?><th><?= (int) $anyo; ?></th><?php endforeach; ?></tr></thead>
                    <tbody><?php foreach ($datosGraficaUnicef as $serie): ?><tr><th><?= htmlspecialchars($serie['nombre'], ENT_QUOTES, 'UTF-8'); ?></th><?php foreach ($anyosUnicef as $anyo): ?><td><?= isset($serie['valores'][$anyo]) ? number_format((float) $serie['valores'][$anyo], 1, ',', '') : '—'; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
                </div>
            <?php endif; ?>
        </section>

        <script id="pobrezaDatos" type="application/json"><?= json_encode($datosGrafica, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaGobiernosEspana" type="application/json"><?= json_encode($gobiernosEspana, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaGobiernosAutonomicos" type="application/json"><?= json_encode($gobiernosAutonomicosSeleccionados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaDatosInfantiles" type="application/json"><?= json_encode($datosGraficaInfantil, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaGobiernosEuropeos" type="application/json"><?= json_encode($gobiernosEuropeosSeleccionados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaDatosOnu" type="application/json"><?= json_encode($datosGraficaOnu, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaDatosUnicef" type="application/json"><?= json_encode($datosGraficaUnicef, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaDatosRelativa" type="application/json"><?= json_encode($datosGraficaRelativa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script id="pobrezaDatosMundial" type="application/json"><?= json_encode($datosGraficaMundial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

        <footer class="pobreza-fuente">
            <strong>Fuente:</strong> Instituto Nacional de Estadística, Encuesta de Condiciones de Vida, tabla 9963.
            El año mostrado corresponde al año de la entrevista y la renta al año anterior.
            <?php if (($resultado['cache'] ?? false) === true): ?>Datos servidos desde caché local.<?php endif; ?>
            <a href="https://ine.es/jaxiT3/Tabla.htm?t=9963" target="_blank" rel="noopener noreferrer">Consultar metodología y tabla oficial</a>.
            Contexto político: <a href="https://www.lamoncloa.gob.es/presidente/presidentes-desde-1823/Paginas/index.aspx" target="_blank" rel="noopener noreferrer">cronología oficial de La Moncloa</a>.
            Presidencias regionales: <a href="https://www.senado.es/web/conocersenado/biblioteca/dossieresareastematicas/detalle/index.html?lang=es" target="_blank" rel="noopener noreferrer">dosier institucional del Senado</a>.
            Pobreza infantil: <a href="https://ec.europa.eu/eurostat/databrowser/view/ilc_peps01n/default/table" target="_blank" rel="noopener noreferrer">Eurostat, conjunto ilc_peps01n</a>.
            Pobreza multidimensional: <a href="https://unstats.un.org/SDGAPI/swagger/" target="_blank" rel="noopener noreferrer">División de Estadística de Naciones Unidas, indicador ODS 1.2.2</a>.
            Pobreza infantil por ingresos: <a href="https://data.unicef.org/sdmx-api-documentation/" target="_blank" rel="noopener noreferrer">UNICEF Data, flujo Child Poverty</a>.
            Pobreza infantil relativa: <a href="https://ec.europa.eu/eurostat/databrowser/view/ilc_li02/default/table" target="_blank" rel="noopener noreferrer">Eurostat EU-SILC, umbral del 60 % de la mediana</a>.
            Pobreza infantil extrema mundial: <a href="https://www.worldbank.org/en/topic/poverty/publication/child-poverty-global-regional-and-select-national-trends" target="_blank" rel="noopener noreferrer">Banco Mundial–UNICEF, línea internacional de 3 dólares (PPA 2021)</a>.
            Pobreza multidimensional global: <a href="https://hdr.undp.org/data-center/documentation-and-downloads" target="_blank" rel="noopener noreferrer">PNUD/OPHI, tablas MPI 2025</a>.
            Las metodologías nacionales pueden diferir; las series ONU y UNICEF se muestran separadas de AROPE.
            <?php if ($gobiernosEuropeosSeleccionados !== []): ?>
                Fuentes políticas:
                <?php foreach ($gobiernosEuropeosSeleccionados as $gobierno): ?>
                    <a href="<?= htmlspecialchars($gobierno['fuente'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($gobierno['nombre'], ENT_QUOTES, 'UTF-8'); ?></a>.
                <?php endforeach; ?>
            <?php endif; ?>
            Las franjas políticas son contexto histórico y no implican una relación causal con los datos.
        </footer>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
