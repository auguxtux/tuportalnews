<?php
declare(strict_types=1);



/**
 * ÚLTIMAS NOTICIAS
 *
 * Muestra exclusivamente las noticias públicas más recientes.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/helpers/noticias.php';

// Valores seguros por defecto para que la plantilla siempre pueda renderizarse.
$noticias = [];
$totalNoticias = 0;
$totalPaginas = 1;
$error = null;

// La página nunca puede ser inferior a 1.
$paginaSolicitada = filter_input(
    INPUT_GET,
    'pagina',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 1,
            'min_range' => 1,
        ],
    ]
);

$pagina = is_int($paginaSolicitada) ? $paginaSolicitada : 1;
$porPagina = max(1, (int) ITEMS_PER_PAGE);

try {
    $pdo = db();

    // Esta página forma parte del portal público para cualquier rol conectado.
    $puedeVerPrivadas = false;

    $totalNoticias = contarNoticiasPublicadas(
        $pdo,
        $puedeVerPrivadas
    );

    $totalPaginas = max(
        1,
        (int) ceil($totalNoticias / $porPagina)
    );

    // Evita páginas inexistentes y desplazamientos fuera del listado.
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $noticias = obtenerNoticiasPublicadasPaginadas(
        $pdo,
        $puedeVerPrivadas,
        $porPagina,
        $offset
    );
} catch (Throwable $e) {
    registrarErrorInterno('PUBLIC.ULTIMAS.CARGA', $e);

    // Nunca se muestran detalles internos del error al visitante.
    $error = 'No ha sido posible cargar las noticias en este momento.';
}

$titulo_pagina = 'Últimas Noticias - Actualidad';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-ultimas-noticias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">

<div class="ultimas-pagina-noticias">
    <h1>📰 Últimas Noticias</h1>

    <p class="ultimas-subtitulo-pagina">
        Las noticias más recientes de nuestro portal
    </p>

    <?php if ($error !== null): ?>
        <div class="ultimas-alerta ultimas-alerta-error" role="alert">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($error === null && empty($noticias)): ?>
        <div class="ultimas-alerta ultimas-alerta-info">
            <p>No hay noticias disponibles en este momento.</p>
        </div>
    <?php elseif (!empty($noticias)): ?>

        <p class="ultimas-resultados-info">
            Mostrando
            <?php echo count($noticias); ?>
            de
            <?php echo number_format($totalNoticias, 0, ',', '.'); ?>
            noticias
        </p>

        <div class="ultimas-lista-noticias">
            <?php foreach ($noticias as $noticia): ?>
                <article class="ultimas-tarjeta-noticia-horizontal news-card news-card--vertical news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                    <div class="ultimas-cabe-titulo-sbtitulo news-card__body">
                        <h2 class="ultimas-tarjeta-titulo news-card__title">
                            <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">
                                <?php echo htmlspecialchars(
                                    (string) $noticia['titulo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </a>
                        </h2>

                        <?php if (!empty($noticia['subtitulo'])): ?>
                            <p class="ultimas-tarjeta-subtitulo news-card__subtitle">
                                <?php echo htmlspecialchars(
                                    (string) $noticia['subtitulo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php echo mostrarImagenNoticia(
                        $noticia,
                        'public-categorias-card-imagen news-card__media',
                        '📷',
                        route('noticia', ['id' => $noticia['id_noticia']])
                    ); ?>

                    <div class="ultimas-tarjeta-meta news-card__meta news-card__meta--standard">
                        <span>
                            📅 <?php echo htmlspecialchars(formatearFecha((string) $noticia['fecha_publicacion'], 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span>
                            ✍️ <a href="<?php echo htmlspecialchars(route('periodistas', ['id' => (int) $noticia['id_autor']]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $noticia['autor_nombre'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </span>
                        <span>
                            💬 <a href="<?php echo htmlspecialchars(route('comentarios_noticia', ['id' => (int) $noticia['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) ($noticia['total_comentarios'] ?? 0); ?> Coment</a>
                        </span>
                    </div>

                    <a
                        href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>"
                        class="ultimas-btn ultimas-btn-small news-card__button"
                    >
                        Leer noticia →
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="ultimas-clean"></div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="ultimas-paginacion" aria-label="Paginación de noticias">
                <?php if ($pagina > 1): ?>
                    <a
                        href="?pagina=<?php echo $pagina - 1; ?>"
                        class="ultimas-pagina-link"
                        rel="prev"
                    >
                        « Anteriores
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <?php if ($i === $pagina): ?>
                        <span
                            class="ultimas-pagina-activo"
                            aria-current="page"
                        >
                            <?php echo $i; ?>
                        </span>
                    <?php else: ?>
                        <a
                            href="?pagina=<?php echo $i; ?>"
                            class="ultimas-pagina-link"
                        >
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($pagina < $totalPaginas): ?>
                    <a
                        href="?pagina=<?php echo $pagina + 1; ?>"
                        class="ultimas-pagina-link"
                        rel="next"
                    >
                        Siguientes »
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
