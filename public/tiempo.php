<?php
declare(strict_types=1);

/**
 * Página pública de previsión meteorológica para municipios de España.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/helpers/aemet.php';

function obtenerIconoMeteorologicoAemet(string $descripcion): string
{
    $texto = function_exists('mb_strtolower')
        ? mb_strtolower($descripcion, 'UTF-8')
        : strtolower($descripcion);

    return match (true) {
        str_contains($texto, 'tormenta') => '⛈️',
        str_contains($texto, 'nieve') => '🌨️',
        str_contains($texto, 'granizo') => '🌨️',
        str_contains($texto, 'lluvia') => '🌧️',
        str_contains($texto, 'chubasco') => '🌦️',
        str_contains($texto, 'niebla') => '🌫️',
        str_contains($texto, 'bruma') => '🌫️',
        str_contains($texto, 'muy nuboso') => '☁️',
        str_contains($texto, 'cubierto') => '☁️',
        str_contains($texto, 'nuboso') => '🌥️',
        str_contains($texto, 'intervalos nubosos') => '⛅',
        str_contains($texto, 'poco nuboso') => '🌤️',
        str_contains($texto, 'despejado') => '☀️',
        default => '🌤️',
    };
}

function obtenerClaseMeteorologicaAemet(string $descripcion): string
{
    $texto = function_exists('mb_strtolower')
        ? mb_strtolower($descripcion, 'UTF-8')
        : strtolower($descripcion);

    return match (true) {
        str_contains($texto, 'tormenta') => 'aemet-clima-tormenta',
        str_contains($texto, 'nieve') => 'aemet-clima-nieve',
        str_contains($texto, 'lluvia') => 'aemet-clima-lluvia',
        str_contains($texto, 'chubasco') => 'aemet-clima-lluvia',
        str_contains($texto, 'niebla') => 'aemet-clima-niebla',
        str_contains($texto, 'bruma') => 'aemet-clima-niebla',
        str_contains($texto, 'cubierto') => 'aemet-clima-nublado',
        str_contains($texto, 'nuboso') => 'aemet-clima-nublado',
        str_contains($texto, 'despejado') => 'aemet-clima-sol',
        default => 'aemet-clima-variable',
    };
}

$configAemet = [];
$municipioPredeterminado = '35017';
$provinciaPredeterminada = 'Las Palmas';
$municipioSolicitado = '35017';
$municipioSeleccionado = null;
$provincias = [];
$municipiosProvincia = [];
$dias = [];
$alertas = [];
$estadoCache = null;
$error = null;
$fuenteMeteorologica = 'AEMET';
$sesionCerradaDuranteConsulta = false;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
    $sesionCerradaDuranteConsulta = true;
}

try {
    $configAemet = cargarConfiguracionAemet();

    $municipioPredeterminado = preg_match(
        '/^\d{5}$/',
        (string) ($configAemet['municipio_id'] ?? '')
    )
        ? (string) $configAemet['municipio_id']
        : '35017';

    $municipioSolicitado = isset($_GET['municipio'])
        ? trim((string) $_GET['municipio'])
        : $municipioPredeterminado;

    if (!preg_match('/^\d{5}$/', $municipioSolicitado)) {
        $municipioSolicitado = $municipioPredeterminado;
    }

    try {
        $provincias = obtenerMunicipiosEspanaAemet();

        $municipioSeleccionado = buscarMunicipioEspanaAemet(
            $provincias,
            $municipioSolicitado
        );

        if ($municipioSeleccionado === null) {
            $municipioSolicitado = $municipioPredeterminado;

            $municipioSeleccionado = buscarMunicipioEspanaAemet(
                $provincias,
                $municipioSolicitado
            );
        }
    } catch (Throwable $e) {
        if (!esLimitacionTemporalAemet($e)) {
            registrarErrorInterno('AEMET.CATALOGO.CARGA', $e);
        }
    }

    if ($municipioSeleccionado === null) {
        $municipioSeleccionado = [
            'codigo' => $municipioSolicitado,
            'nombre' => (string) (
                $configAemet['municipio_nombre'] ?? 'Municipio seleccionado'
            ),
            'provincia' => obtenerProvinciaPorCodigoAemet(
                $municipioSolicitado
            ) ?: $provinciaPredeterminada,
        ];
    }

    try {
        $respuesta = obtenerPrediccionMunicipioAemet(
            $municipioSeleccionado['codigo']
        );

        if (isset($respuesta[0]) && is_array($respuesta[0])) {
            $nombreRespuesta = trim((string) ($respuesta[0]['nombre'] ?? ''));
            if ($nombreRespuesta !== '') {
                $municipioSeleccionado['nombre'] = $nombreRespuesta;
            }

            $provinciaPorCodigo = obtenerProvinciaPorCodigoAemet(
                (string) $municipioSeleccionado['codigo']
            );

            if ($provinciaPorCodigo !== '') {
                $municipioSeleccionado['provincia'] = $provinciaPorCodigo;
            }
        }

        $dias = normalizarPrediccionAemet($respuesta);
        $estadoCache = obtenerEstadoCachePrediccionAemet(
            $municipioSeleccionado['codigo']
        );
    } catch (Throwable $errorAemet) {
        $latitud = $municipioSeleccionado['latitud'] ?? null;
        $longitud = $municipioSeleccionado['longitud'] ?? null;

        if (!is_numeric($latitud) || !is_numeric($longitud)) {
            throw $errorAemet;
        }

        try {
            $dias = obtenerPrediccionOpenMeteo(
                (string) $municipioSeleccionado['codigo'],
                (float) $latitud,
                (float) $longitud
            );
            $estadoCache = obtenerEstadoCacheOpenMeteo(
                (string) $municipioSeleccionado['codigo']
            );
            $fuenteMeteorologica = 'Open-Meteo';
        } catch (Throwable $errorRespaldo) {
            error_log(
                '[METEO] Fallaron el proveedor principal y el respaldo.'
            );

            throw $errorAemet;
        }
    }

    $alertas = generarAlertasAemet(
        $dias,
        is_array($configAemet['alertas'] ?? null)
            ? $configAemet['alertas']
            : []
    );

} catch (Throwable $e) {
    $error = 'No se pudo obtener la predicción meteorológica en este momento.';

    if (!esLimitacionTemporalAemet($e)) {
        registrarErrorInterno('AEMET.PREDICCION.CARGA', $e);
    }
}

if (
    $sesionCerradaDuranteConsulta
    && session_status() === PHP_SESSION_NONE
    && !headers_sent()
) {
    session_start();
}

$nombreLugar = (string) (
    $municipioSeleccionado['nombre']
    ?? $configAemet['municipio_nombre']
    ?? 'España'
);

$provinciaActual = obtenerProvinciaPorCodigoAemet(
    (string) ($municipioSeleccionado['codigo'] ?? $municipioSolicitado)
);

if ($provinciaActual === '') {
    $provinciaActual = $provinciaPredeterminada;
}

if ($municipioSeleccionado !== null) {
    $municipioSeleccionado['provincia'] = $provinciaActual;
}

/*
 * El catálogo y el selector no dependen de que AEMET pueda devolver
 * la predicción en este momento.
 */
if (
    isset($provincias[$provinciaActual])
    && is_array($provincias[$provinciaActual])
) {
    $municipiosProvincia = $provincias[$provinciaActual];
}

if (
    $municipiosProvincia === []
    && isset($provincias[$provinciaPredeterminada])
    && is_array($provincias[$provinciaPredeterminada])
) {
    $provinciaActual = $provinciaPredeterminada;
    $municipiosProvincia = $provincias[$provinciaPredeterminada];
}

$titulo_pagina = 'El tiempo en ' . $nombreLugar;

$estadoPrincipal = trim(
    (string) ($dias[0]['estado_cielo'] ?? '')
);

$clasePrincipal = obtenerClaseMeteorologicaAemet($estadoPrincipal);

$datosGrafica = [];

foreach ($dias as $diaGrafica) {
    $fechaGrafica = strtotime((string) ($diaGrafica['fecha'] ?? ''));

    $datosGrafica[] = [
        'fecha' => $fechaGrafica !== false
            ? date('d/m', $fechaGrafica)
            : '',
        'temperatura_maxima' => $diaGrafica['temperatura_maxima'] ?? null,
        'temperatura_minima' => $diaGrafica['temperatura_minima'] ?? null,
        'humedad_maxima' => $diaGrafica['humedad_maxima'] ?? null,
        'humedad_minima' => $diaGrafica['humedad_minima'] ?? null,
        'probabilidad_lluvia' => $diaGrafica['probabilidad_lluvia'] ?? null,
        'viento_velocidad' => $diaGrafica['viento_velocidad'] ?? null,
    ];
}

require_once __DIR__ . '/../partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        css_url('public-tiempo.css'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>

<?php
$urlJavaScriptTiempo = js_url('public-tiempo.js');

$separadorVersion = str_contains($urlJavaScriptTiempo, '?')
    ? '&'
    : '?';

$urlJavaScriptTiempo .= $separadorVersion . 'v=20260806-6';
?>

<script
    defer
    src="<?= htmlspecialchars(
        $urlJavaScriptTiempo,
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
></script>

<main class="aemet-pagina <?= htmlspecialchars(
    $clasePrincipal,
    ENT_QUOTES,
    'UTF-8'
); ?>">
    <header class="aemet-cabecera">
        <div class="aemet-cabecera-contenido">
            <div class="aemet-cabecera-icono" aria-hidden="true">
                <?= obtenerIconoMeteorologicoAemet($estadoPrincipal); ?>
            </div>

            <div>
                <p class="aemet-etiqueta">AEMET OpenData</p>

                <h1>
                    El tiempo en
                    <?= htmlspecialchars(
                        $nombreLugar,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </h1>

                <?php if ($provinciaActual !== ''): ?>
                    <p class="aemet-provincia">
                        📍
                        <?= htmlspecialchars(
                            $provinciaActual,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($provincias !== []): ?>
        <form
            class="aemet-selector"
            method="get"
            data-municipios-url="<?= htmlspecialchars(
                SITE_URL . '/public/aemet-municipios.php',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            data-ubicacion-url="<?= htmlspecialchars(
                SITE_URL . '/public/aemet-municipio-cercano.php',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            data-tiempo-url="<?= htmlspecialchars(
                SITE_URL . '/tiempo',
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >
            <div class="aemet-campo">
                <label for="provincia">🗺️ Provincia</label>

                <select id="provincia" name="provincia">
                    <?php foreach ($provincias as $provincia => $municipios): ?>
                        <option
                            value="<?= htmlspecialchars(
                                (string) $provincia,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            <?= $provincia === $provinciaActual
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                (string) $provincia,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="aemet-campo">
                <label for="municipio">📍 Municipio</label>

                <select id="municipio" name="municipio" required>
                    <?php foreach ($municipiosProvincia as $municipio): ?>
                        <option
                            value="<?= htmlspecialchars(
                                (string) $municipio['codigo'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            <?= $municipio['codigo']
                                === ($municipioSeleccionado['codigo'] ?? '')
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                (string) $municipio['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <span
                    id="aemetMunicipiosEstado"
                    class="aemet-selector-estado"
                    aria-live="polite"
                ></span>
            </div>

            <button class="aemet-boton" type="submit">
                🔎 Ver previsión
            </button>

            <button
                id="aemetUsarUbicacion"
                class="aemet-boton aemet-boton-ubicacion"
                type="button"
            >
                📍 Tiempo de mi ubicación
            </button>

            <p
                id="aemetUbicacionEstado"
                class="aemet-ubicacion-estado"
                aria-live="polite"
            ></p>

            <p class="aemet-ubicacion-nota">
                Si el navegador no puede localizarte, se mantiene el
                municipio seleccionado.
            </p>
        </form>
    <?php endif; ?>

    <?php if (
        is_array($estadoCache)
        && $estadoCache['actualizado_en'] !== null
    ): ?>
        <p class="aemet-actualizacion">
            🕒 Datos guardados el
            <?= date(
                'd/m/Y \a \l\a\s H:i',
                (int) $estadoCache['actualizado_en']
            ); ?>.
            La actualización se realiza automáticamente.
        </p>

        <?php if ($estadoCache['caducada']): ?>
            <div class="aemet-aviso-cache" role="status">
                ⚠️ AEMET no ha podido actualizar los datos recientemente.
                Se muestra la última predicción disponible.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="aemet-error" role="alert">
            ⚠️
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php else: ?>

        <?php if (!empty($alertas)): ?>
            <section
                class="aemet-alertas"
                aria-labelledby="aemet-alertas-titulo"
            >
                <h2 id="aemet-alertas-titulo">⚠️ Avisos informativos</h2>

                <?php foreach ($alertas as $alerta): ?>
                    <article class="aemet-alerta">
                        <strong>
                            <?= htmlspecialchars(
                                ucfirst((string) $alerta['tipo']),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>:
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                (string) $alerta['mensaje'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>
                    </article>
                <?php endforeach; ?>

                <p class="aemet-nota">
                    Estos avisos son generados por el portal y no sustituyen
                    los avisos oficiales de AEMET.
                </p>
            </section>
        <?php endif; ?>

        <?php if ($datosGrafica !== []): ?>
            <section
                class="aemet-grafica"
                aria-labelledby="aemet-grafica-titulo"
            >
                <div class="aemet-grafica-cabecera">
                    <div>
                        <p class="aemet-grafica-etiqueta">
                            📈 Evolución de la predicción
                        </p>

                        <h2 id="aemet-grafica-titulo">
                            Datos meteorológicos por día
                        </h2>
                    </div>

                    <div
                        class="aemet-grafica-controles"
                        role="group"
                        aria-label="Variable meteorológica"
                    >
                        <button
                            type="button"
                            class="aemet-grafica-boton activo"
                            data-grafica="temperatura"
                            aria-pressed="true"
                        >
                            🌡️ Temperatura
                        </button>

                        <button
                            type="button"
                            class="aemet-grafica-boton"
                            data-grafica="humedad"
                            aria-pressed="false"
                        >
                            💧 Humedad
                        </button>

                        <button
                            type="button"
                            class="aemet-grafica-boton"
                            data-grafica="lluvia"
                            aria-pressed="false"
                        >
                            🌧️ Lluvia
                        </button>

                        <button
                            type="button"
                            class="aemet-grafica-boton"
                            data-grafica="viento"
                            aria-pressed="false"
                        >
                            💨 Viento
                        </button>
                    </div>
                </div>

                <div class="aemet-grafica-lienzo">
                    <canvas
                        id="aemetGrafica"
                        role="img"
                        aria-label="Gráfica de evolución meteorológica"
                    ></canvas>
                </div>
            </section>

            <script id="aemetDatosGrafica" type="application/json">
                <?= json_encode(
                    $datosGrafica,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ); ?>
            </script>
        <?php endif; ?>

        <section class="aemet-grid" aria-label="Predicción por días">
            <?php foreach ($dias as $indice => $dia): ?>
                <?php
                $fecha = strtotime((string) ($dia['fecha'] ?? ''));
                $fechaVisible = $fecha !== false
                    ? date('d/m/Y', $fecha)
                    : 'Sin fecha';

                $diasSemana = [
                    'Monday' => 'Lunes',
                    'Tuesday' => 'Martes',
                    'Wednesday' => 'Miércoles',
                    'Thursday' => 'Jueves',
                    'Friday' => 'Viernes',
                    'Saturday' => 'Sábado',
                    'Sunday' => 'Domingo',
                ];

                $diaSemanaVisible = $fecha !== false
                    ? ($diasSemana[date('l', $fecha)] ?? '')
                    : '';

                $estadoCielo = trim(
                    (string) ($dia['estado_cielo'] ?? '')
                );
                ?>

                <article
                    class="aemet-tarjeta <?= htmlspecialchars(
                        obtenerClaseMeteorologicaAemet($estadoCielo),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?> <?= $indice === 0
                        ? 'aemet-tarjeta-destacada'
                        : ''; ?>"
                >
                    <?php if ($indice === 0): ?>
                        <span class="aemet-hoy">Hoy</span>
                    <?php endif; ?>

                    <header class="aemet-tarjeta-cabecera">
                        <div>
                            <h2>
                                <?= htmlspecialchars(
                                    $diaSemanaVisible,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </h2>

                            <p class="aemet-fecha">
                                <?= htmlspecialchars(
                                    $fechaVisible,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </p>
                        </div>

                        <span class="aemet-icono-cielo" aria-hidden="true">
                            <?= obtenerIconoMeteorologicoAemet($estadoCielo); ?>
                        </span>
                    </header>

                    <p class="aemet-cielo">
                        <?= htmlspecialchars(
                            $estadoCielo !== ''
                                ? $estadoCielo
                                : 'Sin descripción',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </p>

                    <div class="aemet-temperaturas">
                        <div class="aemet-temperatura aemet-temperatura-max">
                            🌡️
                            <span>
                                Máxima
                                <strong>
                                    <?= $dia['temperatura_maxima'] !== null
                                        ? (int) $dia['temperatura_maxima']
                                            . ' °C'
                                        : '—'; ?>
                                </strong>
                            </span>
                        </div>

                        <div class="aemet-temperatura aemet-temperatura-min">
                            ❄️
                            <span>
                                Mínima
                                <strong>
                                    <?= $dia['temperatura_minima'] !== null
                                        ? (int) $dia['temperatura_minima']
                                            . ' °C'
                                        : '—'; ?>
                                </strong>
                            </span>
                        </div>
                    </div>

                    <dl class="aemet-datos">
                        <div class="aemet-dato aemet-dato-humedad">
                            <dt>💧 Humedad</dt>
                            <dd>
                                <?php if (
                                    $dia['humedad_minima'] !== null
                                    && $dia['humedad_maxima'] !== null
                                ): ?>
                                    <?= (int) $dia['humedad_minima']; ?>
                                    –
                                    <?= (int) $dia['humedad_maxima']; ?> %
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>
                        </div>

                        <div class="aemet-dato aemet-dato-lluvia">
                            <dt>🌧️ Lluvia</dt>
                            <dd>
                                <?= (int) (
                                    $dia['probabilidad_lluvia'] ?? 0
                                ); ?> %
                            </dd>
                        </div>

                        <div class="aemet-dato aemet-dato-viento">
                            <dt>💨 Viento</dt>
                            <dd>
                                <?= (int) (
                                    $dia['viento_velocidad'] ?? 0
                                ); ?> km/h

                                <?php if (
                                    trim(
                                        (string) (
                                            $dia['viento_direccion'] ?? ''
                                        )
                                    ) !== ''
                                ): ?>
                                    <?= htmlspecialchars(
                                        (string) $dia['viento_direccion'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>

    <footer class="aemet-fuente">
        <?php if ($fuenteMeteorologica === 'Open-Meteo'): ?>
            ℹ️ Fuente alternativa:
            <a
                href="https://open-meteo.com/"
                target="_blank"
                rel="noopener noreferrer"
            >Open-Meteo</a>.
            AEMET no estaba disponible para esta consulta.
        <?php else: ?>
            ℹ️ Fuente: Agencia Estatal de Meteorología (AEMET).
        <?php endif; ?>
    </footer>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
