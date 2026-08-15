<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/modules/nasa.php';

iniciarSesion();

$consulta = trim((string) ($_GET['q'] ?? 'universo'));
$tipo = (string) ($_GET['tipo'] ?? 'image,video');
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$pagina = is_int($pagina) ? max(1, min(100, $pagina)) : 1;
$desde = filter_input(INPUT_GET, 'desde', FILTER_VALIDATE_INT);
$desde = is_int($desde) && $desde >= 1920 && $desde <= (int) date('Y') ? $desde : null;
$tiposPermitidos = ['image', 'video', 'image,video'];
$tipo = in_array($tipo, $tiposPermitidos, true) ? $tipo : 'image,video';
$error = null;
$items = [];
$total = 0;
$desdeCache = false;
$modoSelector = ($_GET['seleccionar'] ?? '') === 'noticia'
    && in_array((string) ($_SESSION['usuario_rol'] ?? ''), ['periodista', 'admin'], true);
$temasNasa = [
    ['nombre' => 'Sistema Solar', 'consulta' => 'solar system', 'imagen' => 'https://images-assets.nasa.gov/image/PIA14074/PIA14074~thumb.jpg'],
    ['nombre' => 'Universo', 'consulta' => 'deep field universe', 'imagen' => 'https://images-assets.nasa.gov/image/hubble-sees-a-legion-of-galaxies_25608651281_o/hubble-sees-a-legion-of-galaxies_25608651281_o~thumb.jpg'],
    ['nombre' => 'Vía Láctea', 'consulta' => 'milky way', 'imagen' => 'https://images-assets.nasa.gov/image/PIA18913/PIA18913~thumb.jpg'],
    ['nombre' => 'Tierra', 'consulta' => 'earth', 'imagen' => 'https://images-assets.nasa.gov/image/PIA00342/PIA00342~thumb.jpg'],
    ['nombre' => 'Luna', 'consulta' => 'moon', 'imagen' => 'https://images-assets.nasa.gov/image/PIA12235/PIA12235~thumb.jpg'],
    ['nombre' => 'Marte', 'consulta' => 'mars', 'imagen' => 'https://images-assets.nasa.gov/image/NHQ201906010007/NHQ201906010007~thumb.jpg'],
    ['nombre' => 'Sol', 'consulta' => 'sun', 'imagen' => 'https://images-assets.nasa.gov/image/PIA18906/PIA18906~thumb.jpg'],
    ['nombre' => 'Mercurio', 'consulta' => 'mercury', 'imagen' => 'https://images-assets.nasa.gov/image/PIA16908/PIA16908~thumb.jpg'],
    ['nombre' => 'Venus', 'consulta' => 'venus', 'imagen' => 'https://images-assets.nasa.gov/image/PIA13001/PIA13001~thumb.jpg'],
    ['nombre' => 'Júpiter', 'consulta' => 'jupiter', 'imagen' => 'https://images-assets.nasa.gov/image/PIA09231/PIA09231~thumb.jpg'],
    ['nombre' => 'Saturno', 'consulta' => 'saturn', 'imagen' => 'https://images-assets.nasa.gov/image/PIA06423/PIA06423~thumb.jpg'],
    ['nombre' => 'Urano', 'consulta' => 'uranus', 'imagen' => 'https://images-assets.nasa.gov/image/PIA01391/PIA01391~thumb.jpg'],
    ['nombre' => 'Neptuno', 'consulta' => 'neptune', 'imagen' => 'https://images-assets.nasa.gov/image/PIA02210/PIA02210~thumb.jpg'],
    ['nombre' => 'Plutón', 'consulta' => 'pluto', 'imagen' => 'https://images-assets.nasa.gov/image/PIA19880/PIA19880~thumb.jpg'],
    ['nombre' => 'Asteroides', 'consulta' => 'asteroid', 'imagen' => 'https://images-assets.nasa.gov/image/PIA23195/PIA23195~thumb.jpg'],
    ['nombre' => 'Cometas', 'consulta' => 'comet', 'imagen' => 'https://images-assets.nasa.gov/image/PIA17666/PIA17666~thumb.jpg'],
    ['nombre' => 'Estación Espacial', 'consulta' => 'international space station', 'imagen' => 'https://images-assets.nasa.gov/image/200623_ISS_1/200623_ISS_1~thumb.jpg'],
    ['nombre' => 'James Webb', 'consulta' => 'james webb space telescope', 'imagen' => 'https://images-assets.nasa.gov/image/GSFC_20171208_Archive_e000356/GSFC_20171208_Archive_e000356~thumb.jpg'],
    ['nombre' => 'Imágenes recientes', 'consulta' => 'NASA', 'tipo' => 'image', 'desde' => (int) date('Y') - 1, 'imagen' => 'https://images-assets.nasa.gov/image/SSC-20260115-s00035H/SSC-20260115-s00035H~thumb.jpg'],
    ['nombre' => 'Vídeos recientes', 'consulta' => 'NASA', 'tipo' => 'video', 'desde' => (int) date('Y') - 1, 'imagen' => 'https://images-assets.nasa.gov/video/NASA’s%20Artemis%20III%20Announcement%20(Official%20NASA%20Trailer)/NASA’s%20Artemis%20III%20Announcement%20(Official%20NASA%20Trailer)~thumb.jpg'],
    ['nombre' => 'Curiosidades', 'consulta' => 'black hole nebula', 'imagen' => 'https://images-assets.nasa.gov/image/GSFC_20171208_Archive_e000433/GSFC_20171208_Archive_e000433~thumb.jpg'],
    ['nombre' => 'Noticias NASA', 'consulta' => 'NASA news', 'desde' => (int) date('Y') - 1, 'imagen' => 'https://images-assets.nasa.gov/video/iss074m260211914_NASA’s_SpaceX_Crew-11_Post-Flight_News_Conference_260121/iss074m260211914_NASA’s_SpaceX_Crew-11_Post-Flight_News_Conference_260121~thumb.jpg'],
    ['nombre' => 'Nuevos proyectos', 'consulta' => 'future technology', 'desde' => (int) date('Y') - 1, 'imagen' => 'https://images-assets.nasa.gov/video/NASA%20Minute%20Jan.%2023,%202026/NASA%20Minute%20Jan.%2023,%202026~thumb.jpg'],
    ['nombre' => 'Próximas misiones', 'consulta' => 'future missions', 'desde' => (int) date('Y') - 1, 'imagen' => 'https://images-assets.nasa.gov/video/jsc2026m000051_Artemis_II_Mission_Overview_v25_New_Edits_EP_Requests_AVAIL/jsc2026m000051_Artemis_II_Mission_Overview_v25_New_Edits_EP_Requests_AVAIL~thumb.jpg'],
];

try {
    $resultado = buscarCatalogoNasa($consulta, $tipo, $pagina, $desde);
    $items = $resultado['items'];
    $total = $resultado['total'];
    $desdeCache = $resultado['cache'];
} catch (InvalidArgumentException $errorValidacion) {
    $error = $errorValidacion->getMessage();
} catch (Throwable $errorInterno) {
    $error = 'El catálogo de NASA no está disponible temporalmente. Inténtalo de nuevo más tarde.';
    registrarErrorInterno('NASA_CATALOGO', $errorInterno);
}

$crearUrl = static function (int $numero) use ($consulta, $tipo, $desde, $modoSelector): string {
    $parametros = [
        'q' => $consulta,
        'tipo' => $tipo,
        'pagina' => $numero,
    ];
    if ($desde !== null) {
        $parametros['desde'] = $desde;
    }
    if ($modoSelector) {
        $parametros['seleccionar'] = 'noticia';
    }
    return route('nasa') . '?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986) . '#resultados-nasa';
};

$titulo_pagina = 'Imágenes y vídeos de NASA';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('public-nasa.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('nasa-traduccion.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('nasa-visor.css'), ENT_QUOTES, 'UTF-8'); ?>">
<script defer src="<?= htmlspecialchars(js_url('nasa-visor.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php if ($modoSelector): ?>
<script defer src="<?= htmlspecialchars(js_url('nasa-selector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<main class="nasa-pagina">
    <header class="nasa-cabecera">
        <p class="nasa-etiqueta">🚀 Biblioteca espacial oficial</p>
        <h1>Imágenes y vídeos de NASA</h1>
        <p>Explora el catálogo multimedia público de NASA sin salir de TuPortalNews.</p>
    </header>

    <section class="nasa-temas" aria-labelledby="nasaTemasTitulo">
        <div class="nasa-temas-cabecera">
            <h2 id="nasaTemasTitulo">Explora temas destacados</h2>
            <p>Selecciona un tema o realiza una búsqueda personalizada.</p>
        </div>
        <div class="nasa-temas-grid">
            <?php foreach ($temasNasa as $tema): ?>
                <?php
                $tipoTema = (string) ($tema['tipo'] ?? $tipo);
                $desdeTema = isset($tema['desde']) ? (int) $tema['desde'] : null;
                $parametrosTema = ['q' => $tema['consulta'], 'tipo' => $tipoTema];
                if ($desdeTema !== null) {
                    $parametrosTema['desde'] = $desdeTema;
                }
                if ($modoSelector) {
                    $parametrosTema['seleccionar'] = 'noticia';
                }
                $urlTema = route('nasa') . '?' . http_build_query($parametrosTema, '', '&', PHP_QUERY_RFC3986) . '#resultados-nasa';
                $temaActivo = strcasecmp($consulta, $tema['consulta']) === 0
                    && $tipo === $tipoTema
                    && $desde === $desdeTema;
                ?>
                <a class="nasa-tema<?= $temaActivo ? ' nasa-tema-activo' : ''; ?>" href="<?= htmlspecialchars($urlTema, ENT_QUOTES, 'UTF-8'); ?>"<?= $temaActivo ? ' aria-current="page"' : ''; ?>>
                    <img src="<?= htmlspecialchars($tema['imagen'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" width="300" height="170">
                    <span><?= htmlspecialchars($tema['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <form class="nasa-buscador" method="get" action="<?= htmlspecialchars(route('nasa') . '#resultados-nasa', ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($modoSelector): ?><input type="hidden" name="seleccionar" value="noticia"><?php endif; ?>
        <label for="nasaConsulta">Otra búsqueda</label>
        <div class="nasa-controles">
            <input id="nasaConsulta" type="search" name="q" maxlength="80" required value="<?= htmlspecialchars($consulta, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej.: Marte, Luna, Tierra…">
            <select name="tipo" aria-label="Tipo de contenido">
                <option value="image,video" <?= $tipo === 'image,video' ? 'selected' : ''; ?>>Imágenes y vídeos</option>
                <option value="image" <?= $tipo === 'image' ? 'selected' : ''; ?>>Solo imágenes</option>
                <option value="video" <?= $tipo === 'video' ? 'selected' : ''; ?>>Solo vídeos</option>
            </select>
            <button type="submit">🔎 Buscar</button>
        </div>
    </form>

    <div id="resultados-nasa" class="nasa-ancla-resultados" tabindex="-1"></div>

    <?php if ($error !== null): ?>
        <div class="nasa-aviso nasa-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($items === []): ?>
        <div class="nasa-aviso" role="status">No se encontraron resultados para esta búsqueda.</div>
    <?php else: ?>
        <div class="nasa-resumen">
            <strong><?= number_format($total, 0, ',', '.'); ?></strong> resultados en NASA
            <?= $desdeCache ? '<span>· copia temporal</span>' : ''; ?>
        </div>

        <?php if ($modoSelector): ?>
            <div class="nasa-traduccion-opciones">
                <label for="nasaParrafos">Descripción NASA para la noticia:</label>
                <select id="nasaParrafos">
                    <option value="0">No añadir descripción</option>
                    <option value="1">Traducir 1 párrafo</option>
                    <option value="2" selected>Traducir 2 párrafos</option>
                    <option value="3">Traducir 3 párrafos</option>
                    <option value="4">Traducir 4 párrafos</option>
                    <option value="5">Traducir 5 párrafos</option>
                </select>
                <small>Solo se traduce la descripción oficial proporcionada por NASA. Podrás revisarla antes de publicar.</small>
            </div>
        <?php endif; ?>

        <section class="nasa-cuadricula" aria-label="Resultados de NASA">
            <?php foreach ($items as $item): ?>
                <article class="nasa-tarjeta nasa-tarjeta-<?= $item['tipo'] === 'video' ? 'video' : 'imagen'; ?>">
                    <button class="nasa-imagen-enlace nasa-abrir-visor" type="button"
                        data-nasa-ver-id="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-nasa-ver-tipo="<?= htmlspecialchars($item['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-nasa-ver-titulo="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-nasa-ver-descripcion="<?= htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-nasa-ver-detalle="<?= htmlspecialchars($item['detalle'], ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?= htmlspecialchars($item['miniatura'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="400" height="225">
                        <span class="nasa-tipo"><?= $item['tipo'] === 'video' ? '▶️ Vídeo' : '🖼️ Imagen'; ?></span>
                    </button>
                    <div class="nasa-tarjeta-cuerpo">
                        <h2><button class="nasa-titulo-visor nasa-abrir-visor" type="button"
                            data-nasa-ver-id="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-nasa-ver-tipo="<?= htmlspecialchars($item['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-nasa-ver-titulo="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-nasa-ver-descripcion="<?= htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-nasa-ver-detalle="<?= htmlspecialchars($item['detalle'], ENT_QUOTES, 'UTF-8'); ?>"
                        ><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?></button></h2>
                        <?php if ($item['descripcion'] !== ''): ?><p><?= htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <button class="nasa-traducir-tarjeta" type="button"
                            data-nasa-traducir-id="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >🌐 Traducir</button>
                        <div class="nasa-meta">
                            <span><?= htmlspecialchars($item['centro'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($item['fecha'] !== ''): ?><time datetime="<?= htmlspecialchars($item['fecha'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(substr($item['fecha'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></time><?php endif; ?>
                        </div>
                        <a class="nasa-ver" href="<?= htmlspecialchars($item['detalle'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Ver en NASA ↗</a>
                        <?php if ($modoSelector): ?>
                            <button
                                class="nasa-seleccionar"
                                type="button"
                                data-nasa-seleccionar
                                data-tipo="<?= htmlspecialchars($item['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-id="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-url="<?= htmlspecialchars($item['miniatura'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-titulo="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-detalle="<?= htmlspecialchars($item['detalle'], ENT_QUOTES, 'UTF-8'); ?>"
                            ><?= $item['tipo'] === 'video' ? '🎬 Usar este vídeo' : '🖼️ Usar esta imagen'; ?></button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <nav class="nasa-paginacion" aria-label="Páginas del catálogo">
            <?php if ($pagina > 1): ?><a href="<?= htmlspecialchars($crearUrl($pagina - 1), ENT_QUOTES, 'UTF-8'); ?>">← Anterior</a><?php endif; ?>
            <span>Página <?= $pagina; ?></span>
            <?php if ($pagina < 100 && $pagina * NASA_CATALOGO_RESULTADOS < $total): ?><a href="<?= htmlspecialchars($crearUrl($pagina + 1), ENT_QUOTES, 'UTF-8'); ?>">Siguiente →</a><?php endif; ?>
        </nav>
    <?php endif; ?>

    <p class="nasa-fuente">Contenido y metadatos proporcionados por la <a href="https://images.nasa.gov/" target="_blank" rel="noopener noreferrer">NASA Image and Video Library</a>.</p>
    <?php if ($modoSelector): ?><div id="nasaSelectorEstado" class="nasa-aviso" hidden role="status"></div><?php endif; ?>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
