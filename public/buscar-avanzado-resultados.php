<?php
declare(strict_types=1);


/**
 * RESULTADOS DE BÚSQUEDA AVANZADA
 *
 * Ejecuta los filtros de búsqueda, muestra resultados paginados
 * y conserva todos los parámetros aplicados.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/routes.php';

/**
 * Escapa contenido para HTML.
 */
function eResultadosBusqueda(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Valida una fecha con formato YYYY-MM-DD.
 */
function validarFechaResultados(string $fecha): string
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

/**
 * Devuelve las páginas visibles para una paginación compacta.
 *
 * @return array<int|string>
 */
function paginasVisiblesResultados(int $paginaActual, int $totalPaginas): array
{
    if ($totalPaginas <= 9) {
        return range(1, $totalPaginas);
    }

    $paginas = [1, 2];
    $inicio = max(3, $paginaActual - 2);
    $fin = min($totalPaginas - 2, $paginaActual + 2);

    if ($inicio > 3) {
        $paginas[] = '...';
    }

    for ($i = $inicio; $i <= $fin; $i++) {
        $paginas[] = $i;
    }

    if ($fin < $totalPaginas - 2) {
        $paginas[] = '...';
    }

    $paginas[] = $totalPaginas - 1;
    $paginas[] = $totalPaginas;

    return array_values(array_unique($paginas, SORT_REGULAR));
}

/**
 * Construye una URL de paginación conservando los filtros válidos.
 */
function urlPaginaResultados(array $filtros, int $pagina): string
{
    $filtros['pagina'] = $pagina;

    return '?' . http_build_query(
        array_filter(
            $filtros,
            static fn (mixed $valor): bool => $valor !== '' && $valor !== null
        )
    );
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

[$tipoFuente, $idFuente] = $fuente !== ''
    ? explode(':', $fuente, 2)
    : ['', '0'];
$idFuente = (int) $idFuente;

$ubicacionesValidas = ['', 'espana', 'internacional', 'otras', 'ninguna'];
$ubicacionRecibida = (string) ($_GET['ubicacion'] ?? '');
$ubicacion = in_array($ubicacionRecibida, $ubicacionesValidas, true)
    ? $ubicacionRecibida
    : '';

$provincia = max(0, (int) ($_GET['provincia'] ?? 0));
$lugar_internacional = limpiarDatos((string) ($_GET['lugar_internacional'] ?? ''));
$otras_ubicacion = limpiarDatos((string) ($_GET['otras_ubicacion'] ?? ''));

$fecha_desde = validarFechaResultados((string) ($_GET['fecha_desde'] ?? ''));
$fecha_hasta = validarFechaResultados((string) ($_GET['fecha_hasta'] ?? ''));

$ordenesValidos = ['relevancia', 'fecha_desc', 'fecha_asc', 'visitas', 'comentarios'];
$ordenRecibido = (string) ($_GET['orden'] ?? 'relevancia');
$orden = in_array($ordenRecibido, $ordenesValidos, true)
    ? $ordenRecibido
    : 'relevancia';

if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_desde > $fecha_hasta) {
    [$fecha_desde, $fecha_hasta] = [$fecha_hasta, $fecha_desde];
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = max(1, (int) (defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 9));
$offset = ($pagina - 1) * $por_pagina;

$resultados = [];
$total_resultados = 0;
$total_paginas = 0;
$error = null;

$nombreCategoriaFiltro = '';
$nombrePeriodistaFiltro = '';
$nombreFuenteFiltro = '';
$nombreProvinciaFiltro = '';

// ============================================
// PREPARAR CONSULTAS
// ============================================
try {
    $pdo = db();
    $condicionPrivacidad = getCondicionNoticias('n');

    $where = [
        "n.estado = 'publicada'",
        $condicionPrivacidad,
    ];

    $paramsCount = [];
    $paramsData = [];

    if ($q !== '' && mb_strlen($q, 'UTF-8') >= 2) {
        $where[] = '(
            n.titulo LIKE :q_titulo
            OR n.subtitulo LIKE :q_subtitulo
            OR n.contenido LIKE :q_contenido
            OR u.nombre LIKE :q_autor
        )';

        $valorLike = '%' . $q . '%';

        foreach ([
            ':q_titulo',
            ':q_subtitulo',
            ':q_contenido',
            ':q_autor',
        ] as $parametro) {
            $paramsCount[$parametro] = $valorLike;
            $paramsData[$parametro] = $valorLike;
        }
    }

    if ($categoria > 0) {
        $where[] = 'n.id_categoria = :categoria';
        $paramsCount[':categoria'] = $categoria;
        $paramsData[':categoria'] = $categoria;
    }

    if ($periodista > 0) {
        $where[] = 'n.id_autor = :periodista';
        $paramsCount[':periodista'] = $periodista;
        $paramsData[':periodista'] = $periodista;
    }

    if ($idFuente > 0) {
        $where[] = $tipoFuente === 'normal'
            ? 'n.id_fuente = :fuente'
            : 'n.id_fuente_rss = :fuente';
        $paramsCount[':fuente'] = $idFuente;
        $paramsData[':fuente'] = $idFuente;
    }

    switch ($ubicacion) {
        case 'espana':
            $where[] = "n.tipo_ubicacion = 'espana'";

            if ($provincia > 0) {
                $where[] = 'n.id_provincia = :provincia';
                $paramsCount[':provincia'] = $provincia;
                $paramsData[':provincia'] = $provincia;
            }
            break;

        case 'internacional':
            $where[] = "n.tipo_ubicacion = 'internacional'";

            if ($lugar_internacional !== '') {
                $where[] = 'n.lugar_internacional LIKE :lugar_internacional';
                $valorLugar = '%' . $lugar_internacional . '%';
                $paramsCount[':lugar_internacional'] = $valorLugar;
                $paramsData[':lugar_internacional'] = $valorLugar;
            }
            break;

        case 'otras':
            $where[] = "n.tipo_ubicacion = 'otras'";

            if ($otras_ubicacion !== '') {
                $where[] = 'n.otras_ubicacion LIKE :otras_ubicacion';
                $valorOtraUbicacion = '%' . $otras_ubicacion . '%';
                $paramsCount[':otras_ubicacion'] = $valorOtraUbicacion;
                $paramsData[':otras_ubicacion'] = $valorOtraUbicacion;
            }
            break;

        case 'ninguna':
            $where[] = "(
                n.tipo_ubicacion = 'ninguna'
                OR n.tipo_ubicacion IS NULL
                OR n.tipo_ubicacion = ''
            )";
            break;
    }

    if ($fecha_desde !== '') {
        $where[] = 'n.fecha_publicacion >= :fecha_desde';
        $paramsCount[':fecha_desde'] = $fecha_desde . ' 00:00:00';
        $paramsData[':fecha_desde'] = $fecha_desde . ' 00:00:00';
    }

    if ($fecha_hasta !== '') {
        $where[] = 'n.fecha_publicacion <= :fecha_hasta';
        $paramsCount[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
        $paramsData[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = "
        SELECT COUNT(*)
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        WHERE {$whereSql}
    ";

    $sqlData = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            u.avatar AS autor_avatar,
            c.nombre_categoria,
            fr.nombre AS fuente_rss_nombre,
            COALESCE(co.total_comentarios, 0) AS total_comentarios
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN fuentes_rss fr
            ON fr.id_fuente = n.id_fuente_rss
        LEFT JOIN (
            SELECT
                id_noticia,
                COUNT(*) AS total_comentarios
            FROM comentarios
            WHERE estado = 'aprobado'
            GROUP BY id_noticia
        ) co
            ON co.id_noticia = n.id_noticia
        WHERE {$whereSql}
    ";

    switch ($orden) {
        case 'fecha_asc':
            $sqlData .= ' ORDER BY n.fecha_publicacion ASC, n.id_noticia ASC';
            break;

        case 'visitas':
            $sqlData .= ' ORDER BY n.visitas DESC, n.fecha_publicacion DESC';
            break;

        case 'comentarios':
            $sqlData .= ' ORDER BY total_comentarios DESC, n.fecha_publicacion DESC';
            break;

        case 'relevancia':
            if ($q !== '' && mb_strlen($q, 'UTF-8') >= 2) {
                $sqlData .= "
                    ORDER BY
                        CASE
                            WHEN n.titulo LIKE :rel_titulo THEN 4
                            WHEN n.subtitulo LIKE :rel_subtitulo THEN 3
                            WHEN u.nombre LIKE :rel_autor THEN 2
                            WHEN n.contenido LIKE :rel_contenido THEN 1
                            ELSE 0
                        END DESC,
                        n.fecha_publicacion DESC
                ";

                $valorLike = '%' . $q . '%';
                $paramsData[':rel_titulo'] = $valorLike;
                $paramsData[':rel_subtitulo'] = $valorLike;
                $paramsData[':rel_autor'] = $valorLike;
                $paramsData[':rel_contenido'] = $valorLike;
            } else {
                $sqlData .= ' ORDER BY n.fecha_publicacion DESC';
            }
            break;

        case 'fecha_desc':
        default:
            $sqlData .= ' ORDER BY n.fecha_publicacion DESC, n.id_noticia DESC';
            break;
    }

    $stmtCount = $pdo->prepare($sqlCount);

    foreach ($paramsCount as $clave => $valor) {
        $stmtCount->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmtCount->execute();
    $total_resultados = (int) $stmtCount->fetchColumn();
    $total_paginas = $total_resultados > 0
        ? (int) ceil($total_resultados / $por_pagina)
        : 0;

    if ($total_paginas > 0 && $pagina > $total_paginas) {
        $pagina = $total_paginas;
        $offset = ($pagina - 1) * $por_pagina;
    }

    $sqlData .= ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sqlData);

    foreach ($paramsData as $clave => $valor) {
        $stmt->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($categoria > 0) {
        $stmtCategoria = $pdo->prepare(
            'SELECT nombre_categoria
             FROM categorias
             WHERE id_categoria = :id
             LIMIT 1'
        );
        $stmtCategoria->bindValue(':id', $categoria, PDO::PARAM_INT);
        $stmtCategoria->execute();
        $nombreCategoriaFiltro = (string) ($stmtCategoria->fetchColumn() ?: '');
    }

    if ($periodista > 0) {
        $stmtPeriodista = $pdo->prepare(
            'SELECT nombre
             FROM usuarios
             WHERE id_usuario = :id
             LIMIT 1'
        );
        $stmtPeriodista->bindValue(':id', $periodista, PDO::PARAM_INT);
        $stmtPeriodista->execute();
        $nombrePeriodistaFiltro = (string) ($stmtPeriodista->fetchColumn() ?: '');
    }

    if ($idFuente > 0) {
        $tablaFuente = $tipoFuente === 'normal' ? 'fuentes' : 'fuentes_rss';
        $stmtFuente = $pdo->prepare(
            'SELECT nombre
             FROM ' . $tablaFuente . '
             WHERE id_fuente = :id
             LIMIT 1'
        );
        $stmtFuente->bindValue(':id', $idFuente, PDO::PARAM_INT);
        $stmtFuente->execute();
        $nombreFuenteFiltro = (string) ($stmtFuente->fetchColumn() ?: '');
    }

    if ($provincia > 0) {
        $stmtProvincia = $pdo->prepare(
            'SELECT nombre
             FROM provincias
             WHERE id_provincia = :id
             LIMIT 1'
        );
        $stmtProvincia->bindValue(':id', $provincia, PDO::PARAM_INT);
        $stmtProvincia->execute();
        $nombreProvinciaFiltro = (string) ($stmtProvincia->fetchColumn() ?: '');
    }
} catch (Throwable $e) {
    $error = 'Error en la búsqueda. Por favor, inténtalo de nuevo.';
    registrarErrorInterno('PUBLIC.BUSCAR_AVANZADO.RESULTADOS', $e);
}

$filtrosPaginacion = [
    'q' => $q,
    'categoria' => $categoria > 0 ? $categoria : null,
    'periodista' => $periodista > 0 ? $periodista : null,
    'fuente' => $fuente !== '' ? $fuente : null,
    'ubicacion' => $ubicacion,
    'provincia' => $provincia > 0 ? $provincia : null,
    'lugar_internacional' => $lugar_internacional,
    'otras_ubicacion' => $otras_ubicacion,
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta,
    'orden' => $orden,
];

$urlNuevaBusqueda = route('buscar_avanzado');

$titulo_pagina = 'Resultados de búsqueda';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-buscador-avanzado.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('public-form-focus.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<div class="public-buscador-resultados-page">
    <div class="resultados-header">
        <h1 class="resultados-titulo">🔍 Resultados de búsqueda</h1>

        <div class="filtros-aplicados">
            <strong>Filtros aplicados:</strong>

            <?php if ($q !== ''): ?>

                <span class="filtro-tag">
                    📝 Palabras: “<?php echo eResultadosBusqueda($q); ?>”

                </span>
            <?php endif; ?>


            <?php if ($nombreCategoriaFiltro !== ''): ?>

                <span class="filtro-tag">
                    📂 Categoría: <?php echo eResultadosBusqueda($nombreCategoriaFiltro); ?>

                </span>
            <?php endif; ?>


            <?php if ($nombrePeriodistaFiltro !== ''): ?>

                <span class="filtro-tag">
                    ✍️ Articulista: <?php echo eResultadosBusqueda($nombrePeriodistaFiltro); ?>

                </span>
            <?php endif; ?>


            <?php if ($nombreFuenteFiltro !== ''): ?>

                <span class="filtro-tag">
                    <?php echo $tipoFuente === 'rss' ? '📡 Fuente RSS:' : '📰 Fuente:'; ?>
                    <?php echo eResultadosBusqueda($nombreFuenteFiltro); ?>

                </span>
            <?php endif; ?>


            <?php if ($ubicacion === 'espana'): ?>

                <span class="filtro-tag">
                    🇪🇸 España<?php echo $nombreProvinciaFiltro !== '' ? ': ' . eResultadosBusqueda($nombreProvinciaFiltro) : ''; ?>

                </span>
            <?php elseif ($ubicacion === 'internacional'): ?>

                <span class="filtro-tag">
                    🌍 Internacional<?php echo $lugar_internacional !== '' ? ': ' . eResultadosBusqueda($lugar_internacional) : ''; ?>

                </span>
            <?php elseif ($ubicacion === 'otras'): ?>

                <span class="filtro-tag">
                    🗺️ Otras ubicaciones<?php echo $otras_ubicacion !== '' ? ': ' . eResultadosBusqueda($otras_ubicacion) : ''; ?>

                </span>
            <?php elseif ($ubicacion === 'ninguna'): ?>

                <span class="filtro-tag">📍 Sin ubicación especificada</span>
            <?php endif; ?>


            <?php if ($fecha_desde !== ''): ?>

                <span class="filtro-tag">
                    📅 Desde: <?php echo eResultadosBusqueda($fecha_desde); ?>

                </span>
            <?php endif; ?>


            <?php if ($fecha_hasta !== ''): ?>

                <span class="filtro-tag">
                    📅 Hasta: <?php echo eResultadosBusqueda($fecha_hasta); ?>

                </span>
            <?php endif; ?>


            <a
                href="<?php echo eResultadosBusqueda($urlNuevaBusqueda . '?' . http_build_query($filtrosPaginacion)); ?>"

                class="btn-nueva-busqueda"
            >➕ Nueva búsqueda</a>
        </div>
    </div>

    <?php if ($error !== null): ?>

        <div class="public-buscador-alerta public-buscador-alerta-error">
            <?php echo eResultadosBusqueda($error); ?>

        </div>

    <?php elseif ($resultados === []): ?>

        <div class="public-buscador-alerta public-buscador-alerta-info">
            <p>📭 No se encontraron noticias con los filtros seleccionados.</p>
            <p>Sugerencias:</p>
            <ul>
                <li>Revisa la ortografía de las palabras clave.</li>
                <li>Intenta con términos más generales.</li>
                <li>Elimina algunos filtros para ampliar la búsqueda.</li>
            </ul>
        </div>

    <?php else: ?>

        <div class="resultados-info">
            📊 Se encontraron
            <strong><?php echo number_format($total_resultados, 0, ',', '.'); ?></strong>

            noticias
        </div>

        <div class="resultados-grid">
            <?php foreach ($resultados as $noticia): ?>

                <?php $urlNoticia = route('noticia', ['id' => (int) $noticia['id_noticia']]); ?>


                <article class="resultado-card news-card news-card--vertical news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                    <h3 class="resultado-titulo news-card__title">
                        <a href="<?php echo eResultadosBusqueda($urlNoticia); ?>">
                            <?php echo eResultadosBusqueda($noticia['titulo'] ?? ''); ?>
                        </a>
                    </h3>

                    <?php if (!empty($noticia['subtitulo'])): ?>
                        <p class="resultado-subtitulo news-card__subtitle">
                            <?php echo eResultadosBusqueda($noticia['subtitulo']); ?>
                        </p>
                    <?php endif; ?>

                    <?php echo mostrarImagenNoticia(
                        $noticia,
                        'resultado-imagen news-card__media',
                        '📷',
                        $urlNoticia
                    ); ?>


                    <div class="resultado-contenido news-card__body">
                        <div class="resultado-meta news-card__meta news-card__meta--standard">
                            <span class="meta-item">
                                👤 <a href="<?php echo eResultadosBusqueda(route('periodistas', ['id' => (int) ($noticia['id_autor'] ?? 0)])); ?>"><?php echo eResultadosBusqueda($noticia['autor_nombre'] ?? 'Autor'); ?></a>

                            </span>
                            <span class="meta-item">
                                📅 <?php echo eResultadosBusqueda(formatearFecha($noticia['fecha_publicacion'])); ?>

                            </span>
                            <span class="meta-item">
                                📂 <a href="<?php echo eResultadosBusqueda(route('categoria', ['id' => (int) ($noticia['id_categoria'] ?? 0)])); ?>"><?php echo eResultadosBusqueda($noticia['nombre_categoria'] ?? ''); ?></a>

                            </span>
                            <span class="meta-item">
                                👁️ <?php echo number_format((int) ($noticia['visitas'] ?? 0), 0, ',', '.'); ?>

                            </span>
                            <span class="meta-item">
                                💬 <a href="<?php echo eResultadosBusqueda(route('comentarios_noticia', ['id' => (int) $noticia['id_noticia']])); ?>"><?php echo (int) ($noticia['total_comentarios'] ?? 0); ?></a>

                            </span>

                            <?php if (!empty($noticia['fuente_rss_nombre'])): ?>
                                <span class="meta-item">
                                    📡 <a href="<?php echo eResultadosBusqueda(route('buscar', ['fuente' => 'rss:' . (int) ($noticia['id_fuente_rss'] ?? 0)])); ?>"><?php echo eResultadosBusqueda($noticia['fuente_rss_nombre']); ?></a>
                                </span>
                            <?php elseif (!empty($noticia['fuente'])): ?>

                                <span class="meta-item">
                                    <?php if (!empty($noticia['id_fuente'])): ?>
                                        📰 <a href="<?php echo eResultadosBusqueda(route('buscar', ['fuente' => 'normal:' . (int) $noticia['id_fuente']])); ?>"><?php echo eResultadosBusqueda($noticia['fuente']); ?></a>
                                    <?php else: ?>
                                        📰 <?php echo eResultadosBusqueda($noticia['fuente']); ?>
                                    <?php endif; ?>

                                </span>
                            <?php endif; ?>

                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

        </div>

        <?php if ($total_paginas > 1): ?>

            <nav class="resultados-paginacion" aria-label="Paginación de resultados">
                <?php if ($pagina > 1): ?>

                    <a
                        href="<?php echo eResultadosBusqueda(urlPaginaResultados($filtrosPaginacion, $pagina - 1)); ?>"

                        class="pagina-btn"
                        rel="prev"
                    >« Anterior</a>
                <?php endif; ?>


                <div class="public-categorias-pagina-numeros">
                    <?php foreach (paginasVisiblesResultados($pagina, $total_paginas) as $paginaVisible): ?>

                        <?php if ($paginaVisible === '...'): ?>

                            <span class="public-categorias-pagina-puntos" aria-hidden="true">…</span>
                        <?php elseif ((int) $paginaVisible === $pagina): ?>

                            <span class="public-categorias-pagina-activo" aria-current="page">
                                <?php echo (int) $paginaVisible; ?>

                            </span>
                        <?php else: ?>

                            <a
                                href="<?php echo eResultadosBusqueda(urlPaginaResultados(

                                    $filtrosPaginacion,
                                    (int) $paginaVisible
                                )); ?>"
                                class="public-categorias-pagina-link"
                            ><?php echo (int) $paginaVisible; ?></a>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <span class="pagina-info">
                    Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>

                </span>

                <?php if ($pagina < $total_paginas): ?>

                    <a
                        href="<?php echo eResultadosBusqueda(urlPaginaResultados($filtrosPaginacion, $pagina + 1)); ?>"

                        class="pagina-btn"
                        rel="next"
                    >Siguiente »</a>
                <?php endif; ?>

            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
