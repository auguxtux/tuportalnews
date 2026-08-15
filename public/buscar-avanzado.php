<?php
declare(strict_types=1);


/**
 * BÚSQUEDA AVANZADA
 *
 * Formulario de filtros para localizar noticias por palabras clave,
 * categoría, periodista, fuente, ubicación, fechas y orden.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/routes.php';

/**
 * Escapa contenido para HTML.
 */
function eBuscarAvanzado(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Valida una fecha con formato YYYY-MM-DD.
 */
function validarFechaBusqueda(string $fecha): string
{
    if ($fecha === '') {
        return '';
    }

    $objetoFecha = DateTime::createFromFormat('Y-m-d', $fecha);
    $errores = DateTime::getLastErrors();

    if (
        $objetoFecha === false
        || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))
        || $objetoFecha->format('Y-m-d') !== $fecha
    ) {
        return '';
    }

    return $fecha;
}

// ============================================
// RECUPERAR Y VALIDAR FILTROS
// ============================================
$q = limpiarDatos((string) ($_GET['q'] ?? ''));
$categoria = max(0, (int) ($_GET['categoria'] ?? 0));
$periodista = max(0, (int) ($_GET['periodista'] ?? 0));
$fuenteRecibida = (string) ($_GET['fuente'] ?? '');
$fuente = '';
if (preg_match('/^(normal|rss):([1-9][0-9]*)$/', $fuenteRecibida, $coincidenciaFuente)) {
    $fuente = $coincidenciaFuente[1] . ':' . (int) $coincidenciaFuente[2];
} elseif (ctype_digit($fuenteRecibida) && (int) $fuenteRecibida > 0) {
    // Compatibilidad con enlaces creados cuando el filtro solo incluía RSS.
    $fuente = 'rss:' . (int) $fuenteRecibida;
}

$ubicacionesValidas = ['', 'espana', 'internacional', 'otras', 'ninguna'];
$ubicacionRecibida = (string) ($_GET['ubicacion'] ?? '');
$ubicacion = in_array($ubicacionRecibida, $ubicacionesValidas, true)
    ? $ubicacionRecibida
    : '';

$provincia = max(0, (int) ($_GET['provincia'] ?? 0));
$lugar_internacional = limpiarDatos((string) ($_GET['lugar_internacional'] ?? ''));
$otras_ubicacion = limpiarDatos((string) ($_GET['otras_ubicacion'] ?? ''));

$fecha_desde = validarFechaBusqueda((string) ($_GET['fecha_desde'] ?? ''));
$fecha_hasta = validarFechaBusqueda((string) ($_GET['fecha_hasta'] ?? ''));

$ordenesValidos = ['relevancia', 'fecha_desc', 'fecha_asc', 'visitas', 'comentarios'];
$ordenRecibido = (string) ($_GET['orden'] ?? 'relevancia');
$orden = in_array($ordenRecibido, $ordenesValidos, true)
    ? $ordenRecibido
    : 'relevancia';

if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_desde > $fecha_hasta) {
    [$fecha_desde, $fecha_hasta] = [$fecha_hasta, $fecha_desde];
}

// ============================================
// CARGAR OPCIONES DEL FORMULARIO
// ============================================
$categorias = [];
$periodistas = [];
$fuentes = [];
$provincias = [];
$error = null;

try {
    $pdo = db();

    $categorias = $pdo->query(
        'SELECT id_categoria, nombre_categoria
         FROM categorias
         WHERE activa = 1
         ORDER BY nombre_categoria'
    )->fetchAll(PDO::FETCH_ASSOC);

    $periodistas = $pdo->query(
        "SELECT id_usuario, nombre
         FROM usuarios
         WHERE rol = 'periodista'
           AND estado = 'activo'
         ORDER BY nombre"
    )->fetchAll(PDO::FETCH_ASSOC);

    $fuentesNormales = $pdo->query(
        'SELECT id_fuente, nombre
         FROM fuentes
         WHERE activa = 1'
    )->fetchAll(PDO::FETCH_ASSOC);

    $fuentesRss = $pdo->query(
        'SELECT id_fuente, nombre
         FROM fuentes_rss
         WHERE activa = 1'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fuentesNormales as $itemFuente) {
        $fuentes[] = [
            'valor' => 'normal:' . (int) $itemFuente['id_fuente'],
            'nombre' => (string) $itemFuente['nombre'],
            'tipo' => 'normal',
        ];
    }

    foreach ($fuentesRss as $itemFuente) {
        $fuentes[] = [
            'valor' => 'rss:' . (int) $itemFuente['id_fuente'],
            'nombre' => (string) $itemFuente['nombre'],
            'tipo' => 'rss',
        ];
    }

    usort(
        $fuentes,
        static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre'])
    );

    $provincias = $pdo->query(
        'SELECT id_provincia, nombre
         FROM provincias
         ORDER BY nombre'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'No se han podido cargar todos los filtros de búsqueda.';
    registrarErrorInterno('PUBLIC.BUSCAR_AVANZADO.FILTROS', $e);
}

$titulo_pagina = 'Búsqueda Avanzada';
require_once __DIR__ . '/../partials/header.php';

$urlResultados = route('buscar');
?>

<link rel="stylesheet" href="<?php echo css_url('public-buscador-avanzado.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('public-form-focus.css'); ?>">

<div class="public-buscador-container">
    <h1>🔍 Búsqueda Avanzada de Noticias</h1>
    <p class="public-buscador-desc">
        Encuentra noticias por palabras clave, categoría, periodista, fuente o ubicación
    </p>

    <?php if ($error !== null): ?>
        <div class="public-buscador-alerta public-buscador-alerta-error">
            ⚠️ <?php echo eBuscarAvanzado($error); ?>
        </div>
    <?php endif; ?>

    <div class="public-buscador-formulario">
        <form
            id="formBusquedaAvanzada"
            method="GET"
            action="<?php echo eBuscarAvanzado($urlResultados); ?>"
        >
            <div class="public-buscador-grid-3">
                <div class="public-buscador-filtros-columna">
                    <div class="public-buscador-campo">
                        <label for="q">🔤 Palabras clave</label>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="<?php echo eBuscarAvanzado($q); ?>"
                            placeholder="Ej: fútbol, política, tecnología..."
                            maxlength="150"
                        >
                    </div>

                    <div class="public-buscador-campo">
                        <label for="categoria">📂 Categoría</label>
                        <select id="categoria" name="categoria" class="public-buscador-select">
                            <option value="0">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option
                                    value="<?php echo (int) $cat['id_categoria']; ?>"
                                    <?php echo $categoria === (int) $cat['id_categoria'] ? 'selected' : ''; ?>
                                >
                                    <?php echo eBuscarAvanzado($cat['nombre_categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="public-buscador-filtros-columna">
                    <div class="public-buscador-campo">
                        <label for="periodista">✍️ Articulista</label>
                        <select id="periodista" name="periodista" class="public-buscador-select">
                            <option value="0">Todos los periodistas</option>
                            <?php foreach ($periodistas as $per): ?>
                                <option
                                    value="<?php echo (int) $per['id_usuario']; ?>"
                                    <?php echo $periodista === (int) $per['id_usuario'] ? 'selected' : ''; ?>
                                >
                                    <?php echo eBuscarAvanzado($per['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="public-buscador-campo">
                        <label for="fuente">🔗 Fuente</label>
                        <select id="fuente" name="fuente" class="public-buscador-select">
                            <option value="">Todas las fuentes</option>
                            <?php foreach ($fuentes as $itemFuente): ?>
                                <?php $valorFuente = (string) ($itemFuente['valor'] ?? ''); ?>
                                <?php if ($valorFuente !== ''): ?>
                                    <option
                                        value="<?php echo eBuscarAvanzado($valorFuente); ?>"
                                        <?php echo $fuente === $valorFuente ? 'selected' : ''; ?>
                                    >
                                        <?php echo ($itemFuente['tipo'] ?? '') === 'rss' ? '📡' : '📰'; ?>
                                        <?php echo eBuscarAvanzado($itemFuente['nombre'] ?? 'Fuente'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="public-buscador-filtros-columna">
                    <div class="public-buscador-campo">
                        <label for="ubicacion">📍 Ubicación</label>
                        <select id="ubicacion" name="ubicacion" class="public-buscador-select">
                            <option value="">Todas las ubicaciones</option>
                            <option value="espana" <?php echo $ubicacion === 'espana' ? 'selected' : ''; ?>>
                                🇪🇸 España
                            </option>
                            <option value="internacional" <?php echo $ubicacion === 'internacional' ? 'selected' : ''; ?>>
                                🌍 Internacional
                            </option>
                            <option value="otras" <?php echo $ubicacion === 'otras' ? 'selected' : ''; ?>>
                                🗺️ Otras ubicaciones
                            </option>
                            <option value="ninguna" <?php echo $ubicacion === 'ninguna' ? 'selected' : ''; ?>>
                                📍 Sin ubicación
                            </option>
                        </select>
                    </div>

                    <div
                        id="provincia-container"
                        class="public-buscador-campo"
                        <?php echo $ubicacion === 'espana' ? '' : 'hidden'; ?>
                    >
                        <label for="provincia">🏞️ Provincia</label>
                        <select id="provincia" name="provincia" class="public-buscador-select">
                            <option value="0">Todas las provincias</option>
                            <?php foreach ($provincias as $prov): ?>
                                <option
                                    value="<?php echo (int) $prov['id_provincia']; ?>"
                                    <?php echo $provincia === (int) $prov['id_provincia'] ? 'selected' : ''; ?>
                                >
                                    <?php echo eBuscarAvanzado($prov['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div
                        id="internacional-container"
                        class="public-buscador-campo"
                        <?php echo $ubicacion === 'internacional' ? '' : 'hidden'; ?>
                    >
                        <label for="lugar_internacional">🌎 Lugar internacional</label>
                        <input
                            type="text"
                            id="lugar_internacional"
                            name="lugar_internacional"
                            value="<?php echo eBuscarAvanzado($lugar_internacional); ?>"
                            placeholder="Ej: Francia, Japón, Nueva York..."
                            maxlength="150"
                        >
                    </div>

                    <div
                        id="otras-container"
                        class="public-buscador-campo"
                        <?php echo $ubicacion === 'otras' ? '' : 'hidden'; ?>
                    >
                        <label for="otras_ubicacion">🗺️ Otra ubicación</label>
                        <input
                            type="text"
                            id="otras_ubicacion"
                            name="otras_ubicacion"
                            value="<?php echo eBuscarAvanzado($otras_ubicacion); ?>"
                            placeholder="Ej: Océano Atlántico, espacio..."
                            maxlength="150"
                        >
                    </div>
                </div>
            </div>

            <div class="public-buscador-grid-3 public-buscador-grid-fechas">
                <div class="public-buscador-filtros-columna">
                    <div class="public-buscador-campo">
                        <label for="fecha_desde">📅 Desde fecha</label>
                        <input
                            type="date"
                            id="fecha_desde"
                            name="fecha_desde"
                            value="<?php echo eBuscarAvanzado($fecha_desde); ?>"
                        >
                    </div>
                </div>

                <div class="public-buscador-filtros-columna">
                    <div class="public-buscador-campo">
                        <label for="fecha_hasta">📅 Hasta fecha</label>
                        <input
                            type="date"
                            id="fecha_hasta"
                            name="fecha_hasta"
                            value="<?php echo eBuscarAvanzado($fecha_hasta); ?>"
                        >
                    </div>
                </div>

                <div class="public-buscador-filtros-columna">
                    <div class="public-buscador-campo">
                        <label for="orden">⚡ Ordenar por</label>
                        <select id="orden" name="orden" class="public-buscador-select">
                            <option value="relevancia" <?php echo $orden === 'relevancia' ? 'selected' : ''; ?>>
                                Relevancia
                            </option>
                            <option value="fecha_desc" <?php echo $orden === 'fecha_desc' ? 'selected' : ''; ?>>
                                Más recientes
                            </option>
                            <option value="fecha_asc" <?php echo $orden === 'fecha_asc' ? 'selected' : ''; ?>>
                                Más antiguos
                            </option>
                            <option value="visitas" <?php echo $orden === 'visitas' ? 'selected' : ''; ?>>
                                Más visitados
                            </option>
                            <option value="comentarios" <?php echo $orden === 'comentarios' ? 'selected' : ''; ?>>
                                Más comentados
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="public-buscador-acciones">
                <button
                    type="submit"
                    class="public-buscador-btn public-buscador-btn-buscar"
                >🔍 Buscar noticias</button>

                <button
                    type="button"
                    id="btnLimpiarFiltros"
                    class="public-buscador-btn public-buscador-btn-limpiar"
                >🧹 Limpiar filtros</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    const form = document.getElementById('formBusquedaAvanzada');
    const ubicacion = document.getElementById('ubicacion');
    const provinciaContainer = document.getElementById('provincia-container');
    const internacionalContainer = document.getElementById('internacional-container');
    const otrasContainer = document.getElementById('otras-container');

    const provincia = document.getElementById('provincia');
    const lugarInternacional = document.getElementById('lugar_internacional');
    const otrasUbicacion = document.getElementById('otras_ubicacion');
    const botonLimpiar = document.getElementById('btnLimpiarFiltros');

    function actualizarCamposUbicacion(limpiarOcultos) {
        const valor = ubicacion.value;

        const mostrarProvincia = valor === 'espana';
        const mostrarInternacional = valor === 'internacional';
        const mostrarOtras = valor === 'otras';

        provinciaContainer.hidden = !mostrarProvincia;
        internacionalContainer.hidden = !mostrarInternacional;
        otrasContainer.hidden = !mostrarOtras;

        provincia.disabled = !mostrarProvincia;
        lugarInternacional.disabled = !mostrarInternacional;
        otrasUbicacion.disabled = !mostrarOtras;

        if (limpiarOcultos) {
            if (!mostrarProvincia) {
                provincia.value = '0';
            }

            if (!mostrarInternacional) {
                lugarInternacional.value = '';
            }

            if (!mostrarOtras) {
                otrasUbicacion.value = '';
            }
        }
    }

    ubicacion.addEventListener('change', function () {
        actualizarCamposUbicacion(true);
    });

    botonLimpiar.addEventListener('click', function () {
        form.reset();
        ubicacion.value = '';
        actualizarCamposUbicacion(true);
    });

    actualizarCamposUbicacion(false);
})();
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
