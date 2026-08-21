<?php
declare(strict_types=1);


/**
 * PORTADA PRINCIPAL - PORTAL DE NOTICIAS
 * Versión Mejorada con Categorías, Búsqueda y Listado
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/helpers/rss.php';
require_once __DIR__ . '/../includes/helpers/noticias.php';

$colorTextoContraste = static function (string $hex): string {
    $hex = ltrim(trim($hex), '#');
    if (preg_match('/^[a-f0-9]{6}$/i', $hex) !== 1) {
        return '#000000';
    }

    $componentes = [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
    $lineales = array_map(
        static fn (float $valor): float => $valor <= 0.04045
            ? $valor / 12.92
            : (($valor + 0.055) / 1.055) ** 2.4,
        $componentes
    );
    $luminancia = 0.2126 * $lineales[0]
        + 0.7152 * $lineales[1]
        + 0.0722 * $lineales[2];

    $contrasteOscuro = ($luminancia + 0.05) / 0.05;
    $contrasteClaro = 1.05 / ($luminancia + 0.05);

    return $contrasteClaro >= $contrasteOscuro ? '#ffffff' : '#000000';
};

// --------------------------------------------------------------
// 3. LÓGICA DE NOTICIAS LOCALES
// --------------------------------------------------------------
try {
    $pdo = db();

    $feeds_config = [];
    $rss_noticias = [];
    foreach (obtenerFuentesRssExternas($pdo) as $feed) {
        $nombre = $feed['nombre'];
        $feeds_config[$nombre] = $feed;
        // La portada nunca espera a servicios externos: consume la caché local.
        $rss_noticias[$nombre] = cargarFeedRSS(
            $feed['url'],
            $feed['limite'],
            false
        );
    }

    // La portada pertenece al portal público y nunca debe mezclar contenido privado.
    $puedeVerPrivadas = false;

    $noticias_slider = obtenerNoticiasSlider(
        $pdo,
        $puedeVerPrivadas,
        5
    );

    $ultimas_noticias = obtenerUltimasNoticias(
        $pdo,
        $puedeVerPrivadas,
        6
    );

    $noticias_populares = obtenerNoticiasPopulares(
        $pdo,
        $puedeVerPrivadas,
        5
    );

    $categorias_seleccionadas = [
        'Política' => [
            'color' => '#dc3545',
            'icono' => '🏛️',
        ],
        'Medio Ambiente' => [
            'color' => '#28a745',
            'icono' => '🌱',
        ],
        'Corrupción' => [
            'color' => '#fd7e14',
            'icono' => '💰',
        ],
        'Tecnología' => [
            'color' => '#17a2b8',
            'icono' => '💻',
        ],
        'Misterios' => [
            'color' => '#6f42c1',
            'icono' => '🔮',
        ],
        'Sucesos' => [
            'color' => '#e83e8c',
            'icono' => '🚨',
        ],
        'Ciencia' => [
            'color' => '#20c997',
            'icono' => '🔬',
        ],
        'Cultura' => [
            'color' => '#ffc107',
            'icono' => '🎭',
        ],
        'Salud' => [
            'color' => '#198754',
            'icono' => '🏥',
        ],
        'Opinión' => [
            'color' => '#0dcaf0',
            'icono' => '💬',
        ],
        'Justicia' => [
            'color' => '#6c757d',
            'icono' => '⚖️',
        ],
    ];

    $noticias_por_categoria = obtenerNoticiasPorCategorias(
        $pdo,
        $categorias_seleccionadas,
        $puedeVerPrivadas
    );

    $noticias_destacadas_listado = obtenerNoticiasDestacadasListado(
        $pdo,
        $puedeVerPrivadas,
        4
    );

    $noticia_destacada = obtenerNoticiaDestacadaPrincipal(
        $pdo,
        $puedeVerPrivadas
    );
} catch (Throwable $e) {
    registrarErrorInterno('PUBLIC.PORTADA.CARGA', $e);

    $noticias_slider = [];
    $ultimas_noticias = [];
    $noticias_populares = [];
    $noticias_por_categoria = [];
    $noticias_destacadas_listado = [];
    $noticia_destacada = false;
    $feeds_config = [];
    $rss_noticias = [];
}

$titulo_pagina = 'Tus Noticias - Actualidad Global';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-portada.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">

<div class="portada-container">

    <!-- ============================================ -->
    <!-- HERO SLIDER (LOCAL) -->
    <!-- ============================================ -->
    <?php if (!empty($noticias_slider)): ?>
    <div class="hero-slider" id="heroSlider">
        <?php foreach ($noticias_slider as $index => $slide): ?>
        <?php
        $imagenSlide = !empty($slide['imagen_principal'])
            ? base_url('uploads/noticias/' . ltrim((string) $slide['imagen_principal'], '/'))
            : (string) ($slide['imagen_externa'] ?? '');
        $imagenSlideOptimizada = !empty($slide['imagen_principal'])
            ? obtenerImagenLocalOptimizadaRss(basename((string) $slide['imagen_principal']))
            : obtenerImagenExternaOptimizadaRss($imagenSlide);
        ?>
        <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>">
            <img
                src="<?php echo htmlspecialchars($imagenSlideOptimizada['src'], ENT_QUOTES, 'UTF-8'); ?>"
                <?php if ($imagenSlideOptimizada['srcset'] !== ''): ?>
                srcset="<?php echo htmlspecialchars($imagenSlideOptimizada['srcset'], ENT_QUOTES, 'UTF-8'); ?>"
                sizes="100vw"
                <?php endif; ?>
                alt="<?php echo htmlspecialchars((string) $slide['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                width="1200"
                height="675"
                loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
                fetchpriority="<?php echo $index === 0 ? 'high' : 'low'; ?>"
                decoding="async"
                <?php if (empty($slide['imagen_principal'])): ?>
                    onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(base_url('assets/img/default-image.jpg'), ENT_QUOTES, 'UTF-8'); ?>';"
                <?php endif; ?>
            >
                <div class="hero-slide-overlay">
                <div class="hero-tags">
                    <span class="hero-categoria"><?php echo htmlspecialchars($slide['nombre_categoria']); ?></span>
                    <?php if (!empty($slide['nombre_region'])): ?>
                        <span class="hero-region"><?php echo htmlspecialchars($slide['nombre_region']); ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="hero-slide-titulo"><a href="<?php echo route('noticia', ['id' => $slide['id_noticia']]); ?>"><?php echo htmlspecialchars($slide['titulo']); ?></a></h2>
                <?php if ($slide['subtitulo']): ?>
                <p class="hero-slide-subtitulo"><?php echo htmlspecialchars($slide['subtitulo']); ?></p>
                <?php endif; ?>
                <div class="hero-slide-meta">
                    <span>✍️ <?php echo htmlspecialchars($slide['autor_nombre']); ?></span>
                    <span>📅 <?php echo formatearFecha($slide['fecha_publicacion']); ?></span>
                </div>
                <a href="<?php echo route('noticia', ['id' => $slide['id_noticia']]); ?>" class="hero-btn" aria-label="Leer noticia completa: <?php echo htmlspecialchars((string) $slide['titulo'], ENT_QUOTES, 'UTF-8'); ?>">Leer noticia completa →</a>
            </div>
        </div>
        <?php endforeach; ?>
        <button class="hero-prev" onclick="cambiarSlide(-1)">❮</button>
        <button class="hero-next" onclick="cambiarSlide(1)">❯</button>
        <div class="hero-dots"><?php for ($i = 0; $i < count($noticias_slider); $i++): ?><span class="hero-dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="irSlide(<?php echo $i; ?>)"></span><?php endfor; ?></div>
    </div>
    <?php endif; ?>
    <!-- ============================================ -->
    <!-- BLOQUE EDITORIAL (para mejorar SEO) -->
    <!-- ============================================ -->
    <div class="editorial-block" style="background: #e2f0fd; padding: 0.5rem 1rem; border-radius: 12px; margin: 1rem 0; text-align: center;">
    <h1 style="color: #1f2937; margin-bottom: 2px; font-size: 1.25rem;">
        Bienvenido a TuPortalNews
    </h1>

    <p style="color: #4b5563; line-height: 1.4; margin-bottom: 4px; text-align: justify; font-size: 0.9rem;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>TuPortalNews</strong> Portal informativo donde puedes consultar noticias, comentar, valorar y denunciar contenidos. Con informaciones de actualidad Política, Economía, Ciencia, Cultura, Deportes y otros temas de interés.

<br>
        &nbsp;&nbsp;&nbsp;&nbsp;Si deseas <strong>contribuir activamente</strong> en la elaboración de contenidos, regístrate y solicita un perfil de <strong>Comentarista</strong> de las publicaciones, <strong>Articulista</strong> independiente o <strong>Colaborador</strong> del Portal.
        <br>
        &nbsp;&nbsp;&nbsp;&nbsp; <strong>Se rechazarán aquellas personas</strong> que no cumplan con las normas de respeto a los <strong>Derechos Humanos</strong> y <strong>Animales</strong>. El equipo de administradores garantiza una gestión segura y organizada.

    </p>
</div>
    <!-- ============================================ -->
    <!-- FORMULARIO DE BÚSQUEDA CON BOTÓN AVANZADO -->
    <!-- ============================================ -->
    <div class="busqueda-container">
        <form action="<?php echo route('buscar'); ?>" method="GET" class="buscador-form">
            <input type="text" name="q" class="buscador-input" placeholder="🔍 Buscar noticias... (ej: Fuerteventura, Política, Turismo)" aria-label="Buscar noticias">
            <button type="submit" class="buscador-btn">Buscar</button>
            
        </form>
        <a href="<?php echo route('buscar_avanzado'); ?>" class="buscador-btn-avanzado">🔎 Búsqueda avanzada</a>
    </div>
    <?php if (!empty($noticia_destacada) && is_array($noticia_destacada)): ?>
    <?php
    $imagenDestacadaOptimizada = !empty($noticia_destacada['imagen_externa'])
        ? obtenerImagenExternaOptimizadaRss((string) $noticia_destacada['imagen_externa'])
        : ['src' => '', 'srcset' => ''];
    $imagenDestacadaLocal = !empty($noticia_destacada['imagen_principal'])
        ? obtenerImagenLocalOptimizadaRss(basename((string) $noticia_destacada['imagen_principal']))
        : ['src' => '', 'srcset' => ''];
    ?>
    <div class="destacada-principal">
    <div class="destacada-badge">
        <span>⭐ NOTICIA DESTACADA</span>
    </div>
    <div class="destacada-contenido">
        <div class="destacada-imagen">
            <a href="<?php echo route('noticia', ['id' => $noticia_destacada['id_noticia']]); ?>">
                <?php if ($noticia_destacada['imagen_principal']): ?>
                    <img src="<?php echo htmlspecialchars($imagenDestacadaLocal['src'], ENT_QUOTES, 'UTF-8'); ?>"
                         <?php if ($imagenDestacadaLocal['srcset'] !== ''): ?>srcset="<?php echo htmlspecialchars($imagenDestacadaLocal['srcset'], ENT_QUOTES, 'UTF-8'); ?>" sizes="(min-width: 768px) 50vw, 100vw"<?php endif; ?>
                         alt="<?php echo htmlspecialchars($noticia_destacada['titulo']); ?>"
                         width="640" height="360"
                         loading="lazy" decoding="async">
                <?php elseif ($noticia_destacada['imagen_externa']): ?>
                    <img src="<?php echo htmlspecialchars($imagenDestacadaOptimizada['src'], ENT_QUOTES, 'UTF-8'); ?>"
                         <?php if ($imagenDestacadaOptimizada['srcset'] !== ''): ?>srcset="<?php echo htmlspecialchars($imagenDestacadaOptimizada['srcset'], ENT_QUOTES, 'UTF-8'); ?>" sizes="(min-width: 768px) 50vw, 100vw"<?php endif; ?>
                         alt="<?php echo htmlspecialchars($noticia_destacada['titulo']); ?>"
                         width="640" height="360"
                         loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="destacada-sin-imagen">📷 Sin imagen</div>
                <?php endif; ?>
            </a>
        </div>
        <div class="destacada-info">
            <div class="destacada-tags">
                <span class="destacada-categoria"><?php echo htmlspecialchars($noticia_destacada['nombre_categoria']); ?></span>
                <?php if (!empty($noticia_destacada['nombre_region'])): ?>
                    <span class="destacada-region"><?php echo htmlspecialchars($noticia_destacada['nombre_region']); ?></span>
                <?php endif; ?>
            </div>
            <h2 class="destacada-titulo">
                <a href="<?php echo route('noticia', ['id' => $noticia_destacada['id_noticia']]); ?>">
                    <?php echo htmlspecialchars($noticia_destacada['titulo']); ?>
                </a>
            </h2>
            <?php if ($noticia_destacada['subtitulo']): ?>
                <p class="destacada-subtitulo"><?php echo htmlspecialchars($noticia_destacada['subtitulo']); ?></p>
            <?php endif; ?>
            <p class="destacada-extracto">
                <?php echo htmlspecialchars(
                    obtenerPrimerParrafo((string) $noticia_destacada['contenido'], 200),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>
            <div class="destacada-meta">
                <span>✍️ <?php echo htmlspecialchars($noticia_destacada['autor_nombre']); ?></span>
                <span>📅 <?php echo formatearFecha($noticia_destacada['fecha_publicacion']); ?></span>
                <span>👁️ <?php echo number_format($noticia_destacada['visitas']); ?> visitas</span>
            </div>
            <a href="<?php echo route('noticia', ['id' => $noticia_destacada['id_noticia']]); ?>" class="destacada-btn">
                Leer noticia completa →
            </a>
        </div>
    </div>
</div>
    <?php endif; ?>
    <!-- ============================================ -->
    <!-- SECCIÓN: NOTICIAS POR CATEGORÍAS (Política, Ciencia, Medio Ambiente) -->
    <!-- ============================================ -->
    <?php if (!empty($noticias_por_categoria)): ?>
    <div class="temas-destacados-section">
        <div class="section-header">
            <h2 class="section-titulo">📌 Categorías</h2>
            <a href="<?php echo route('categorias'); ?>" class="section-ver-todas">Todas categorías →</a>
        </div>
        <div class="temas-destacados-grid">
            <?php foreach ($noticias_por_categoria as $nombre_cat => $data): 
                $noticia = $data['noticia'];
                $color = $data['color'];
                $icono = $data['icono'];
                if (!$noticia) continue;
                $imagenTemaOptimizada = !empty($noticia['imagen_externa'])
                    ? obtenerImagenExternaOptimizadaRss((string) $noticia['imagen_externa'])
                    : ['src' => '', 'srcset' => ''];
                $imagenTemaLocal = !empty($noticia['imagen_principal'])
                    ? obtenerImagenLocalOptimizadaRss(basename((string) $noticia['imagen_principal']))
                    : ['src' => '', 'srcset' => ''];
            ?>
            <div class="tema-card">
                <div class="tema-header" style="background-color: <?php echo $color; ?>; color: <?php echo $colorTextoContraste((string) $color); ?>;">
                    <h3><span><?php echo $icono; ?></span> <?php echo htmlspecialchars($nombre_cat); ?></h3>
                </div>
                                <div class="tema-noticia">
                    <div class="tema-imagen">
                        <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">
                            <?php
                            if (!empty($noticia['imagen_principal'])) {
                                echo '<img src="' . htmlspecialchars($imagenTemaLocal['src'], ENT_QUOTES, 'UTF-8') . '"'
                                    . ($imagenTemaLocal['srcset'] !== '' ? ' srcset="' . htmlspecialchars($imagenTemaLocal['srcset'], ENT_QUOTES, 'UTF-8') . '" sizes="(min-width: 900px) 25vw, 100vw"' : '')
                                    . ' alt="' . htmlspecialchars($noticia['titulo']) . '" width="480" height="270" loading="lazy" decoding="async">';
                            } elseif (!empty($noticia['imagen_externa'])) {
                                echo '<img src="' . htmlspecialchars($imagenTemaOptimizada['src'], ENT_QUOTES, 'UTF-8') . '"'
                                    . ($imagenTemaOptimizada['srcset'] !== '' ? ' srcset="' . htmlspecialchars($imagenTemaOptimizada['srcset'], ENT_QUOTES, 'UTF-8') . '" sizes="(min-width: 900px) 25vw, 100vw"' : '')
                                    . ' alt="' . htmlspecialchars($noticia['titulo']) . '" width="480" height="270" loading="lazy" decoding="async"
      onerror="this.onerror=null;this.src=\'' . htmlspecialchars(base_url('assets/img/default-image.jpg'), ENT_QUOTES, 'UTF-8') . '\';">';
                            } else {
                                echo '<div class="sin-imagen">📷 Sin imagen</div>';
                            }
                            ?>
                        </a>
                    </div>
                    
                    <a href="<?php echo route('categoria', ['id' => $noticia['slug_categoria']]); ?>" class="btn-tema">
                        Noticias de <?php echo htmlspecialchars($nombre_cat); ?> →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- NOTICIAS RSS EXTERNAS -->
    <!-- ============================================ -->
    <?php if (!empty($rss_noticias)): ?>
    <div class="rss-externas-section">
        <div class="section-header">
            <h2 class="section-titulo">📡 Noticias de Medios Externos</h2>
        </div>
        <div class="rss-externas-grid">
            <?php foreach ($rss_noticias as $nombre => $noticias): ?>
                <?php if (empty($noticias)) continue; ?>
                <div class="rss-card">
                    <div class="rss-card-header" style="border-bottom-color: <?php echo $feeds_config[$nombre]['color']; ?>;">
                        <h3><span><?php echo $feeds_config[$nombre]['icono']; ?></span> <?php echo htmlspecialchars($nombre); ?></h3>
                    </div>
                    <ul class="rss-noticias">
                        <?php foreach ($noticias as $item): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="rss-item-link">
                                <?php if ($item['imagen']): ?>
                                <div class="rss-item-img"><img src="<?php echo htmlspecialchars($item['imagen']); ?>" alt="" width="320" height="180" loading="lazy" decoding="async"></div>
                                <?php endif; ?>
                                <div class="rss-item-content">
                                    <strong><?php echo htmlspecialchars($item['titulo']); ?></strong>
                                    <small><?php echo htmlspecialchars($item['fecha']); ?></small>
                                    <p><?php echo htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

            <!-- ============================================ -->
    <!-- BOTÓN RSS ADICIONAL -->
    <!-- ============================================ -->
    <div class="rss-footer-link">
        <a href="<?php echo base_url('public/rss-feed.php'); ?>" class="btn-rss">📡 Más Noticias de medios externos  →</a>
    </div>                            

    <!-- ============================================ -->
    <!-- SECCIÓN: TODAS LAS NOTICIAS (LISTADO) -->
    <!-- ============================================ -->
    <?php if (!empty($noticias_destacadas_listado)): ?>
    <div class="listado-section">
        <div class="section-header">
            <h2 class="section-titulo">📰 Noticias</h2>
            <a href="<?php echo route('listado_noticias'); ?>" class="section-ver-todas">Listado completo →</a>
        </div>
        <div class="listado-grid">
            <?php foreach ($noticias_destacadas_listado as $noticia): ?>
            <?php
            $imagenListadoLocal = !empty($noticia['imagen_principal'])
                ? obtenerImagenLocalOptimizadaRss(basename((string) $noticia['imagen_principal']))
                : ['src' => '', 'srcset' => ''];
            ?>
            <div class="listado-card">
                <?php if ($noticia['imagen_principal']): ?>
                <div class="listado-img">
                    <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">
                        <img src="<?php echo htmlspecialchars($imagenListadoLocal['src'], ENT_QUOTES, 'UTF-8'); ?>" <?php if ($imagenListadoLocal['srcset'] !== ''): ?>srcset="<?php echo htmlspecialchars($imagenListadoLocal['srcset'], ENT_QUOTES, 'UTF-8'); ?>" sizes="(min-width: 900px) 25vw, 100vw"<?php endif; ?> alt="<?php echo htmlspecialchars($noticia['titulo']); ?>" width="480" height="270" loading="lazy" decoding="async">
                    </a>
                </div>
                <?php endif; ?>
                <div class="listado-contenido">
                    <div class="listado-tags">
                        <span class="listado-categoria"><?php echo htmlspecialchars($noticia['nombre_categoria']); ?></span>
                        <?php if (!empty($noticia['nombre_region'])): ?>
                            <span class="listado-region"><?php echo htmlspecialchars($noticia['nombre_region']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center;">
            <a href="<?php echo route('listado_noticias'); ?>" class="btn-ver-todas">📋 Ver todas las noticias  →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- ÚLTIMAS NOTICIAS LOCALES -->
    <!-- ============================================ -->
    <div class="main-content">
        <div class="ultimas-section">
            <div class="section-header">
                <h2 class="section-titulo">📰 Últimas noticias</h2>
                                <a href="<?php echo route('ultimas'); ?>" class="section-ver-todas">Ver todas →</a>
            </div>
            <div class="ultimas-grid">
                <?php foreach ($ultimas_noticias as $noticia): ?>
                <?php
                $tituloUltima = (string) ($noticia['titulo'] ?? '');
                $tituloUltimaLargo = function_exists('mb_strlen')
                    ? mb_strlen($tituloUltima, 'UTF-8') > 55
                    : strlen($tituloUltima) > 55;
                $imagenUltimaOptimizada = !empty($noticia['imagen_externa'])
                    ? obtenerImagenExternaOptimizadaRss((string) $noticia['imagen_externa'])
                    : ['src' => '', 'srcset' => ''];
                $imagenUltimaLocal = !empty($noticia['imagen_principal'])
                    ? obtenerImagenLocalOptimizadaRss(basename((string) $noticia['imagen_principal']))
                    : ['src' => '', 'srcset' => ''];
                ?>
                                <article class="ultima-card news-card news-card--vertical news-card--compact news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                    <h3 class="ultima-titulo news-card__title"><a<?php echo $tituloUltimaLargo ? ' class="ultima-titulo-largo"' : ''; ?> href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>"><?php echo htmlspecialchars($tituloUltima); ?></a></h3>
                    <div class="ultima-imagen news-card__media">
                        <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">
                            <?php
                            // Intentar imagen_principal (local)
                            if (!empty($noticia['imagen_principal'])) {
                                echo '<img src="' . htmlspecialchars($imagenUltimaLocal['src'], ENT_QUOTES, 'UTF-8') . '"'
                                    . ($imagenUltimaLocal['srcset'] !== '' ? ' srcset="' . htmlspecialchars($imagenUltimaLocal['srcset'], ENT_QUOTES, 'UTF-8') . '" sizes="(min-width: 900px) 33vw, 50vw"' : '')
                                    . ' alt="' . htmlspecialchars($noticia['titulo']) . '" width="480" height="270" loading="lazy" decoding="async">';
                            }
                            // Si no, intentar imagen_externa (RSS)
                            elseif (!empty($noticia['imagen_externa'])) {
                                echo '<img src="' . htmlspecialchars($imagenUltimaOptimizada['src'], ENT_QUOTES, 'UTF-8') . '"'
                                    . ($imagenUltimaOptimizada['srcset'] !== '' ? ' srcset="' . htmlspecialchars($imagenUltimaOptimizada['srcset'], ENT_QUOTES, 'UTF-8') . '" sizes="(min-width: 900px) 33vw, 50vw"' : '')
                                    . ' alt="' . htmlspecialchars($noticia['titulo']) . '" width="480" height="270" loading="lazy" decoding="async"
      onerror="this.onerror=null;this.src=\'' . htmlspecialchars(base_url('assets/img/default-image.jpg'), ENT_QUOTES, 'UTF-8') . '\';">';
                            }
                            // Si no hay ninguna imagen, usar imagen por defecto
                            else {
                                echo '<img src="' . base_url('assets/img/default-image.jpg') . '" alt="" width="480" height="270" loading="lazy" decoding="async">';
                            }
                            ?>
                        </a>
                    </div>
                    <div class="ultima-contenido news-card__body">
                        <div class="ultima-tags">
                            <span class="ultima-categoria"><?php echo htmlspecialchars($noticia['nombre_categoria']); ?></span>
                            <?php if (!empty($noticia['nombre_region'])): ?>
                                <span class="ultima-region"><?php echo htmlspecialchars($noticia['nombre_region']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ============================================ -->
<!-- SIDEBAR (POPULARES CON IMÁGENES) -->
<!-- ============================================ -->
<aside class="sidebar">
    <div class="popular-section">
        <div class="section-header"><h2 class="section-titulo">🔥 Más populares</h2></div>
        <div class="popular-lista">
            <?php foreach ($noticias_populares as $index => $popular): ?>
            <?php
            $popularImg = '';
            if (!empty($popular['imagen_principal'])) {
                $popularImg = obtenerImagenLocalOptimizadaRss(
                    basename((string) $popular['imagen_principal'])
                )['src'];
            } elseif (!empty($popular['imagen_externa'])) {
                $popularImg = obtenerUrlMiniaturaRss(
                    (string) $popular['imagen_externa'],
                    320,
                    180
                ) ?? (string) $popular['imagen_externa'];
            }
            ?>
            <div class="popular-item">
                <div class="popular-posicion"><?php echo $index + 1; ?></div>
                
                <?php if ($popularImg): ?>
                <div class="popular-imagen">
                    <a href="<?php echo route('noticia', ['id' => $popular['id_noticia']]); ?>">
                        <img src="<?php echo htmlspecialchars($popularImg, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($popular['titulo']); ?>" 
                             width="160"
                             height="90"
                             loading="lazy"
                             decoding="async">
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="popular-contenido">
                    <a href="<?php echo route('noticia', ['id' => $popular['id_noticia']]); ?>" class="popular-titulo"><?php echo htmlspecialchars($popular['titulo']); ?></a>
                    <?php if ($popular['subtitulo']): ?>
                    <div class="popular-subtitulo" style="font-size: 0.7rem; color: #666; margin: 4px 0;"><?php echo htmlspecialchars($popular['subtitulo']); ?></div>
                    <?php endif; ?>
                    <div class="popular-meta"><span>👁️ <?php echo number_format($popular['visitas']); ?> visitas</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</aside>
    </div>

    

</div>

<?php if (!empty($noticias_slider) && count($noticias_slider) > 1): ?>
<script>
let slideActual = 0;
var slides = document.querySelectorAll('.hero-slide');
var dots = document.querySelectorAll('.hero-dot');
var autoPlay;
function mostrarSlide(index) { slides.forEach(s => s.classList.remove('active')); dots.forEach(d => d.classList.remove('active')); slideActual = (index + slides.length) % slides.length; slides[slideActual].classList.add('active'); if(dots.length) dots[slideActual].classList.add('active'); }
function cambiarSlide(dir) { mostrarSlide(slideActual + dir); reiniciarAutoPlay(); }
function irSlide(index) { mostrarSlide(index); reiniciarAutoPlay(); }
function reiniciarAutoPlay() { clearInterval(autoPlay); autoPlay = setInterval(() => cambiarSlide(1), 6000); }
autoPlay = setInterval(() => cambiarSlide(1), 6000);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
