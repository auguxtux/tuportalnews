<?php
declare(strict_types=1);


/**
 * PÁGINA DE CATEGORÍA
 *
 * Muestra el listado de categorías o las noticias de una categoría concreta.
 * Acepta slug e ID numérico.
 * Agrupa las noticias por ubicación.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/routes.php';

/**
 * Devuelve un icono representativo para una categoría.
 */
function getIconoCategoria(string $nombre): string
{
    $nombre = mb_strtolower($nombre, 'UTF-8');

    $iconos = [
        'deport' => '⚽',
        'fútbol' => '⚽',
        'baloncesto' => '🏀',
        'tenis' => '🎾',
        'motor' => '🏎️',
        'ciclismo' => '🚴',
        'natación' => '🏊',
        'atletismo' => '🏃',

        'cultura' => '🎭',
        'arte' => '🎨',
        'música' => '🎵',
        'cine' => '🎬',
        'teatro' => '🎭',
        'literatura' => '📚',
        'libros' => '📖',

        'política' => '🏛️',
        'gobierno' => '🏛️',
        'elecciones' => '🗳️',
        'actualidad' => '📰',
        'noticias' => '📰',
        'sucesos' => '🚨',

        'economía' => '💰',
        'negocios' => '💼',
        'empresa' => '🏢',
        'bolsa' => '📈',
        'finanzas' => '💵',

        'tecnología' => '💻',
        'ciencia' => '🔬',
        'informática' => '🖥️',
        'internet' => '🌐',
        'móvil' => '📱',
        'videojuegos' => '🎮',

        'salud' => '🏥',
        'medicina' => '💊',
        'bienestar' => '🧘',
        'nutrición' => '🥗',

        'educación' => '🎓',
        'colegio' => '🏫',
        'universidad' => '🎓',
        'aprender' => '📚',

        'viajes' => '✈️',
        'turismo' => '🏝️',
        'vacaciones' => '🌴',

        'naturaleza' => '🌿',
        'animales' => '🐾',
        'medio ambiente' => '🌍',
        'ecología' => '🌱',

        'sociedad' => '👥',
        'familia' => '👪',
        'gente' => '👤',

        'opinión' => '💬',
        'editorial' => '📝',
        'columna' => '✍️',

        'internacional' => '🌎',
        'mundo' => '🌍',
        'global' => '🌐',

        'local' => '📍',
        'regional' => '🗺️',
        'comunidad' => '🏘️',
    ];

    foreach ($iconos as $termino => $icono) {
        if (mb_strpos($nombre, $termino, 0, 'UTF-8') !== false) {
            return $icono;
        }
    }

    return '📁';
}

/**
 * Escapa texto para mostrarlo en HTML.
 */
function eCategoria(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Genera el HTML de una tarjeta de noticia.
 */
function renderTarjetaCategoria(array $noticia): void
{
    $urlNoticia = route('noticia', ['id' => (int) $noticia['id_noticia']]);
    $avatar = !empty($noticia['autor_avatar'])
        ? basename((string) $noticia['autor_avatar'])
        : '';
    ?>
    <article class="public-categorias-card-noticia news-card news-card--vertical news-card--category news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
        <h2 class="public-categorias-card-titulo news-card__title">
            <a href="<?php echo eCategoria($urlNoticia); ?>">
                <?php echo eCategoria($noticia['titulo'] ?? ''); ?>
            </a>
        </h2>

        <?php if (!empty($noticia['subtitulo'])): ?>
            <p class="public-categorias-card-subtitulo news-card__subtitle">
                <?php echo eCategoria($noticia['subtitulo']); ?>
            </p>
        <?php endif; ?>

        <?php echo mostrarImagenNoticia(
            $noticia,
            'public-categorias-card-imagen news-card__media',
            '📷',
            $urlNoticia
        ); ?>

        <div class="public-categorias-card-contenido news-card__body">
            <div class="public-categorias-card-meta news-card__meta news-card__meta--standard">
                <a class="public-categorias-card-autor" href="<?php echo eCategoria(route('periodistas', ['id' => (int) ($noticia['id_autor'] ?? 0)])); ?>">
                    <?php if ($avatar !== ''): ?>

                        <img
                            src="<?php echo eCategoria(base_url('uploads/perfiles/' . rawurlencode($avatar))); ?>"

                            alt="Avatar de <?php echo eCategoria($noticia['autor_nombre'] ?? 'Autor'); ?>"

                            class="public-categorias-autor-avatar"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='<?php echo eCategoria(base_url('assets/img/default-avatar.png')); ?>';"

                        >
                    <?php endif; ?>


                    <span class="public-categorias-autor-nombre">
                        <?php echo eCategoria($noticia['autor_nombre'] ?? 'Autor'); ?>

                    </span>
                </a>

                <div class="public-categorias-card-stats">
                    <?php
                    $parametrosUbicacion = match ((string) ($noticia['tipo_ubicacion'] ?? '')) {
                        'espana' => ['provincia' => (int) ($noticia['id_provincia'] ?? 0)],
                        'internacional' => ['internacional' => (string) ($noticia['lugar_internacional'] ?? '')],
                        'otras' => ['otras' => (string) ($noticia['otras_ubicacion'] ?? '')],
                        default => [],
                    };
                    ?>
                    <?php if ($parametrosUbicacion !== []): ?>
                        <a class="public-categorias-stat-item" title="Ver noticias de esta ubicación" href="<?php echo eCategoria(route('ubicacion', $parametrosUbicacion)); ?>">📍 <?php echo eCategoria($noticia['ubicacion_nombre'] ?? 'Sin ubicación especificada'); ?></a>
                    <?php else: ?>
                        <span class="public-categorias-stat-item" title="Ubicación">📍 <?php echo eCategoria($noticia['ubicacion_nombre'] ?? 'Sin ubicación especificada'); ?></span>
                    <?php endif; ?>
                    <span class="public-categorias-stat-item" title="Visitas">
                        👁️ <?php echo number_format((int) ($noticia['visitas'] ?? 0)); ?>

                    </span>
                    <a class="public-categorias-stat-item" title="Ver comentarios" href="<?php echo eCategoria(route('comentarios_noticia', ['id' => (int) $noticia['id_noticia']])); ?>">
                        💬 <?php echo (int) ($noticia['total_comentarios'] ?? 0); ?>
                    </a>
                </div>
            </div>

            <a
                href="<?php echo eCategoria($urlNoticia); ?>"

                class="public-categorias-btn public-categorias-btn-leer news-card__button"
            >Leer más →</a>
        </div>
    </article>
    <?php

}

/**
 * Genera una sección de noticias agrupadas.
 */
function renderGrupoCategoria(string $titulo, string $icono, array $noticias): void
{
    if ($noticias === []) {
        return;
    }
    ?>
    <section class="public-categorias-grupo-ubicacion">
        <h2 class="public-categorias-grupo-titulo">
            <span class="grupo-icono"><?php echo eCategoria($icono); ?></span>

            <?php echo eCategoria($titulo); ?>

        </h2>

        <div class="public-categorias-grid-noticias">
            <?php foreach ($noticias as $noticia): ?>

                <?php renderTarjetaCategoria($noticia); ?>

            <?php endforeach; ?>

        </div>
    </section>
    <?php

}

/**
 * Devuelve las páginas que deben mostrarse en la paginación.
 *
 * @return array<int|string>
 */
function obtenerPaginasVisibles(int $paginaActual, int $totalPaginas): array
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

// ============================================
// OBTENER IDENTIFICADOR
// ============================================
$identificador = trim((string) ($_GET['id'] ?? ''));

$categoria = null;
$id_categoria = 0;

if ($identificador !== '') {
    try {
        $pdo = db();

        if (ctype_digit($identificador)) {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM categorias
                 WHERE id_categoria = :id
                   AND activa = 1
                 LIMIT 1'
            );
            $stmt->bindValue(':id', (int) $identificador, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM categorias
                 WHERE slug_categoria = :slug
                   AND activa = 1
                 LIMIT 1'
            );
            $stmt->bindValue(':slug', $identificador, PDO::PARAM_STR);
        }

        $stmt->execute();
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($categoria !== null) {
            $id_categoria = (int) $categoria['id_categoria'];
        }
    } catch (Throwable $e) {
        registrarErrorInterno('PUBLIC.CATEGORIA.BUSCAR', $e);
    }
}

// ============================================
// LISTADO DE TODAS LAS CATEGORÍAS
// ============================================
if ($categoria === null) {
    try {
        $pdo = db();

        $stmt = $pdo->query(
            "SELECT
                c.*,
                COUNT(n.id_noticia) AS total_noticias
             FROM categorias c
             LEFT JOIN noticias n
                ON n.id_categoria = c.id_categoria
               AND n.estado = 'publicada'
               AND n.privada = 0
             WHERE c.activa = 1
             GROUP BY c.id_categoria
             ORDER BY c.nombre_categoria"
        );

        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $titulo_pagina = 'Todas las categorías';

        require_once __DIR__ . '/../partials/header.php';
        ?>
        <link rel="stylesheet" href="<?php echo css_url('public-categorias.css'); ?>">
        <link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">
        <link rel="stylesheet" href="<?php echo css_url('public-news-browse.css'); ?>">


        <div class="public-categorias-container">
            <h1>📂 Todas las categorías</h1>

            <?php if ($categorias === []): ?>

                <div class="public-categorias-alerta public-categorias-alerta-info">
                    <p>📭 No hay categorías disponibles.</p>
                    <p>
                        <a href="<?php echo eCategoria(base_url()); ?>" class="public-categorias-enlace">

                            🏠 Volver al inicio
                        </a>
                    </p>
                </div>
            <?php else: ?>

                <div class="public-categorias-grid">
                    <?php foreach ($categorias as $cat): ?>

                        <?php

                        $slugCategoria = (string) ($cat['slug_categoria'] ?? '');
                        $urlCategoria = route('categoria', ['id' => $slugCategoria]);
                        ?>
                        <a
                            href="<?php echo eCategoria($urlCategoria); ?>"

                            class="public-categorias-card public-categorias-card-enlace"
                        >
                            <div class="public-categorias-card-contenido">
                                <h3 class="public-categorias-card-titulo">
                                    <?php echo getIconoCategoria((string) $cat['nombre_categoria']); ?>

                                    <?php echo eCategoria($cat['nombre_categoria']); ?>

                                </h3>

                                <?php if (!empty($cat['descripcion'])): ?>

                                    <p class="public-categorias-card-descripcion">
                                        📝 <?php echo eCategoria($cat['descripcion']); ?>

                                    </p>
                                <?php endif; ?>


                                <span class="public-categorias-card-stats">
                                    📊 <?php echo (int) $cat['total_noticias']; ?> noticias

                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </div>

        <?php

        require_once __DIR__ . '/../partials/footer.php';
        exit;
    } catch (Throwable $e) {
        registrarErrorInterno('PUBLIC.CATEGORIA.LISTADO', $e);
        http_response_code(500);
        die('Error al cargar las categorías');
    }
}

// ============================================
// NOTICIAS DE LA CATEGORÍA
// ============================================
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = max(1, (int) ITEMS_PER_PAGE);
$offset = ($pagina - 1) * $por_pagina;

$noticias = [];
$total_noticias = 0;
$total_paginas = 0;
$error = null;

try {
    $pdo = db();
    $condicion_privacidad = getCondicionNoticias('n');

    $sql_count = "
        SELECT COUNT(*)
        FROM noticias n
        WHERE n.id_categoria = :categoria
          AND n.estado = 'publicada'
          AND {$condicion_privacidad}
    ";

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->bindValue(':categoria', $id_categoria, PDO::PARAM_INT);
    $stmt_count->execute();

    $total_noticias = (int) $stmt_count->fetchColumn();
    $total_paginas = (int) ceil($total_noticias / $por_pagina);

    if ($total_paginas > 0 && $pagina > $total_paginas) {
        $pagina = $total_paginas;
        $offset = ($pagina - 1) * $por_pagina;
    }

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            u.avatar AS autor_avatar,
            c.nombre_categoria,
            p.nombre AS provincia_nombre,
            (
                SELECT COUNT(*)
                FROM comentarios co
                WHERE co.id_noticia = n.id_noticia
                  AND co.estado = 'aprobado'
            ) AS total_comentarios
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN provincias p
            ON n.id_provincia = p.id_provincia
        WHERE n.id_categoria = :categoria
          AND n.estado = 'publicada'
          AND {$condicion_privacidad}
        ORDER BY n.fecha_publicacion DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':categoria', $id_categoria, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'No se han podido cargar las noticias de esta categoría.';
    registrarErrorInterno('PUBLIC.CATEGORIA.NOTICIAS', $e);
}

// ============================================
// AGRUPAR NOTICIAS POR UBICACIÓN
// ============================================
$noticias_agrupadas = [
    'espana' => [],
    'internacional' => [],
    'otras' => [],
    'ninguna' => [],
];

foreach ($noticias as $noticia) {
    $tipoUbicacion = (string) ($noticia['tipo_ubicacion'] ?? 'ninguna');

    switch ($tipoUbicacion) {
        case 'espana':
            $noticia['ubicacion_nombre'] =
                !empty($noticia['provincia_nombre'])
                    ? $noticia['provincia_nombre']
                    : 'España';
            $noticias_agrupadas['espana'][] = $noticia;
            break;

        case 'internacional':
            $noticia['ubicacion_nombre'] =
                !empty($noticia['lugar_internacional'])
                    ? $noticia['lugar_internacional']
                    : 'Internacional';
            $noticias_agrupadas['internacional'][] = $noticia;
            break;

        case 'otras':
            $noticia['ubicacion_nombre'] =
                !empty($noticia['otras_ubicacion'])
                    ? $noticia['otras_ubicacion']
                    : 'Otra ubicación';
            $noticias_agrupadas['otras'][] = $noticia;
            break;

        case 'ninguna':
        default:
            $noticia['ubicacion_nombre'] = 'Sin ubicación especificada';
            $noticias_agrupadas['ninguna'][] = $noticia;
            break;
    }
}

$icono_categoria = getIconoCategoria((string) $categoria['nombre_categoria']);
$titulo_pagina = $icono_categoria . ' ' . $categoria['nombre_categoria'];

require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-categorias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('public-news-browse.css'); ?>">


<div class="public-categorias-container">
    <h1 class="public-categorias-titulo-cat">
        <?php echo $icono_categoria; ?>

        <?php echo eCategoria($categoria['nombre_categoria']); ?>

    </h1>

    <?php if ($error !== null): ?>

        <div class="public-categorias-alerta public-categorias-alerta-error">
            ⚠️ <?php echo eCategoria($error); ?>

        </div>
    <?php endif; ?>


    <?php if ($noticias === []): ?>

        <div class="public-categorias-alerta public-categorias-alerta-info">
            <p>📭 No hay noticias en esta categoría.</p>
            <p>
                <a href="<?php echo eCategoria(route('home')); ?>" class="public-categorias-enlace">

                    🏠 Volver al inicio
                </a>
            </p>
        </div>
    <?php else: ?>


        <?php renderGrupoCategoria('España', '🇪🇸', $noticias_agrupadas['espana']); ?>

        <?php renderGrupoCategoria('Internacional', '🌍', $noticias_agrupadas['internacional']); ?>

        <?php renderGrupoCategoria('Otras ubicaciones', '🗺️', $noticias_agrupadas['otras']); ?>

        <?php renderGrupoCategoria('Sin ubicación especificada', '📍', $noticias_agrupadas['ninguna']); ?>


        <?php if ($total_paginas > 1): ?>

            <nav class="public-categorias-paginacion" aria-label="Paginación">
                <?php if ($pagina > 1): ?>

                    <a
                        href="<?php echo eCategoria(route('categoria', [

                            'id' => $categoria['slug_categoria'],
                            'pagina' => $pagina - 1,
                        ])); ?>"
                        class="public-categorias-pagina-btn"
                        rel="prev"
                    >◀️ Anterior</a>
                <?php endif; ?>


                <div class="public-categorias-pagina-numeros">
                    <?php foreach (obtenerPaginasVisibles($pagina, $total_paginas) as $paginaVisible): ?>

                        <?php if ($paginaVisible === '...'): ?>

                            <span class="public-categorias-pagina-puntos" aria-hidden="true">…</span>
                        <?php elseif ((int) $paginaVisible === $pagina): ?>

                            <span class="public-categorias-pagina-activo" aria-current="page">
                                <?php echo (int) $paginaVisible; ?>

                            </span>
                        <?php else: ?>

                            <a
                                href="<?php echo eCategoria(route('categoria', [

                                    'id' => $categoria['slug_categoria'],
                                    'pagina' => (int) $paginaVisible,
                                ])); ?>"
                                class="public-categorias-pagina-link"
                            ><?php echo (int) $paginaVisible; ?></a>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <?php if ($pagina < $total_paginas): ?>

                    <a
                        href="<?php echo eCategoria(route('categoria', [

                            'id' => $categoria['slug_categoria'],
                            'pagina' => $pagina + 1,
                        ])); ?>"
                        class="public-categorias-pagina-btn"
                        rel="next"
                    >Siguiente ▶️</a>
                <?php endif; ?>

            </nav>
        <?php endif; ?>


    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
