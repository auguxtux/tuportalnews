<?php
declare(strict_types=1);



/**
 * PÁGINA DE NOTICIAS RSS
 *
 * Muestra varias fuentes RSS en una sola página, incluyendo imágenes
 * cuando están disponibles y utilizando una caché local de 15 minutos.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/helpers/rss.php';

// Tiempo de conservación de cada fuente en caché: 15 minutos.
$duracionCache = 900;

$feeds = obtenerFuentesRssExternas(db());

$resultados = cargarFeedsRSS(
    $feeds,
    ROOT_PATH . 'storage/cache/rss',
    $duracionCache,
    false
);

$titulo_pagina = 'Noticias de actualidad';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-rss-feed.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">

<div class="container">
    <div class="rss-header">
        <h1>
            📰
            <?php echo htmlspecialchars(
                $titulo_pagina,
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </h1>

        <p>
            Las últimas noticias de los principales medios,
            actualizadas automáticamente
        </p>

        <div class="update-info">
            🔄 Actualizado: <?php echo date('d/m/Y H:i:s'); ?>
        </div>
    </div>

    <div class="rss-grid">
        <?php foreach ($resultados as $resultado): ?>
            <?php
            $config = $resultado['config'];
            $datos = $resultado['datos'];
            $color = $config['color'];
            ?>

            <div class="rss-card">
                <div
                    class="rss-card-header"
                    style="border-bottom-color: <?php echo htmlspecialchars(
                        $color,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>;"
                >
                    <h2>
                        <span>
                            <?php echo htmlspecialchars(
                                $config['icono'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                        <?php echo htmlspecialchars(
                            $config['nombre'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </h2>
                </div>

                <?php if ($datos['error']): ?>
                    <div class="rss-error">
                        ⚠️ No se pudieron cargar las noticias
                    </div>
                <?php elseif (empty($datos['noticias'])): ?>
                    <div class="rss-vacio">
                        📭 No hay noticias disponibles
                    </div>
                <?php else: ?>
                    <ul class="rss-noticias">
                        <?php foreach ($datos['noticias'] as $noticia): ?>
                            <?php $tieneImagen = !empty($noticia['imagen']); ?>

                            <li class="news-card news-card--horizontal news-card--compact news-card--external">
                                <a
                                    href="<?php echo htmlspecialchars(
                                        $noticia['link'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="noticia-link <?php echo $tieneImagen
                                        ? ''
                                        : 'sin-imagen'; ?>"
                                >
                                    <div class="noticia-titulo news-card__title">
                                        <?php echo htmlspecialchars(
                                            $noticia['titulo'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>

                                    <?php if ($noticia['descripcion'] !== ''): ?>
                                        <div class="noticia-descripcion news-card__subtitle">
                                            <?php echo htmlspecialchars(
                                                $noticia['descripcion'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($tieneImagen): ?>
                                        <div class="noticia-imagen news-card__media">
                                            <img
                                                src="<?php echo htmlspecialchars(
                                                    (string) $noticia['imagen'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                alt=""
                                                width="320"
                                                height="180"
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.parentElement.style.display='none'"
                                            >
                                        </div>
                                    <?php endif; ?>

                                    <div class="noticia-contenido news-card__body">
                                        <?php if ($noticia['fecha'] !== ''): ?>
                                            <div class="noticia-fecha">
                                                📅
                                                <?php echo htmlspecialchars(
                                                    $noticia['fecha'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="rss-card-footer">
                        <a
                            href="<?php echo htmlspecialchars(
                                $config['url'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="color: <?php echo htmlspecialchars(
                                $color,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>;"
                        >
                            🌐 Ver más en
                            <?php echo htmlspecialchars(
                                $config['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                            →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="page-footer">
        <p>
            © <?php echo date('Y'); ?> - Portal de Noticias |
            Las noticias se obtienen automáticamente de sus respectivos
            feeds RSS
        </p>

        <p style="margin-top: 10px;">
            <a
                href="<?php echo htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>"
                style="color: #2a5298; text-decoration: none;"
            >
                ← Volver al inicio
            </a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
