<?php
declare(strict_types=1);


/** PÁGINA DE INICIO - LISTADO DE NOTICIAS
 * Muestra las últimas noticias con paginación
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';

// Obtener número de página
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
if ($pagina < 1) $pagina = 1;

$por_pagina = ITEMS_PER_PAGE;
$offset = ($pagina - 1) * $por_pagina;

try {
    $pdo = db();
    
    // Obtener total de noticias publicadas
    $stmt_total = $pdo->query("SELECT COUNT(*) FROM noticias WHERE estado = 'publicada' AND privada = 0");
    $total_noticias = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_noticias / $por_pagina);
    
    // Obtener condición según permisos
    $condicion_privacidad = getCondicionNoticias();
    
    // Obtener noticias de la página actual
    $stmt = $pdo->prepare("
        SELECT n.*, 
               u.nombre as autor_nombre,
               u.avatar as autor_avatar,
               c.nombre_categoria,
               c.slug_categoria,
               f.nombre AS fuente_normal_nombre,
               fr.nombre AS fuente_rss_nombre,
               (
                   SELECT COUNT(*)
                   FROM comentarios co
                   WHERE co.id_noticia = n.id_noticia
                     AND co.estado = 'aprobado'
               ) AS total_comentarios
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        JOIN categorias c ON n.id_categoria = c.id_categoria
        LEFT JOIN fuentes f ON f.id_fuente = n.id_fuente
        LEFT JOIN fuentes_rss fr ON fr.id_fuente = n.id_fuente_rss
        WHERE n.estado = 'publicada' AND $condicion_privacidad
        ORDER BY n.fecha_publicacion DESC, n.id_noticia DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $noticias = $stmt->fetchAll();
    
    // Obtener categorías para el menú lateral
    $stmt_cats = $pdo->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY orden, nombre_categoria LIMIT 5");
    $categorias_destacadas = $stmt_cats->fetchAll();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar las noticias.';
    registrarErrorInterno('PUBLIC.LISTADO_NOTICIAS.CARGA', $e);
}

$titulo_pagina = 'Inicio';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-index-noticias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<!-- CONTENIDO PRINCIPAL -->
<div class="home-contenido-principal">

<h1>Listado de Noticias</h1>
    <?php if (isset($error)): ?>

        <div class="home-alerta home-alerta-error"><?php echo $error; ?></div>

    <?php endif; ?>

    
    <?php if (empty($noticias)): ?>

        <div class="home-alerta home-alerta-info">
            <p>No hay noticias disponibles en este momento.</p>
            <p>Vuelve más tarde para ver las últimas actualizaciones.</p>
        </div>
    <?php else: ?>

        
        <!-- LISTADO DE NOTICIAS EN GRID -->
        <div class="home-grid-noticias">
            <?php foreach ($noticias as $noticia): ?>

                <article class="home-tarjeta-noticia news-card news-card--vertical news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                    
                    <!-- 1. TÍTULO -->
                    <h2 class="home-tarjeta-titulo news-card__title">
                        <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">

                            <?php echo htmlspecialchars($noticia['titulo']); ?>

                        </a>
                    </h2>
                    
                    <!-- 2. SUBTÍTULO (si existe) -->
                    <?php if ($noticia['subtitulo']): ?>

                        <h3 class="home-tarjeta-subtitulo news-card__subtitle">
                            <?php echo htmlspecialchars($noticia['subtitulo']); ?>

                        </h3>
                    <?php endif; ?>

                    
                   <!-- 3. IMAGEN PRINCIPAL -->
<?php echo mostrarImagenNoticia(
    $noticia,
    'home-tarjeta-imagen news-card__media',
    '📷',
    route('noticia', ['id' => $noticia['id_noticia']])
); ?>


            <!-- FUNCION LLAMADA -->
            <?php $test = obtenerPrimerParrafo($noticia['contenido'], 200); ?>                    

                    <!-- 4. EXTRACTO (primer párrafo) -->
                    <div class="home-tarjeta-extracto">
                        <p><?php echo $test; ?></p>

                    </div>
                  
                    <!-- 5. METADATOS (Periodista, Categoría, Fecha) -->
                    <div class="home-tarjeta-metadatos news-card__meta">
                        <div class="home-metadato-item">
                            <span class="home-metadato-icono">✍️</span>
                            <a href="<?php echo route('periodistas', ['id' => (int) $noticia['id_autor']]); ?>" class="home-metadato-enlace"><?php echo htmlspecialchars($noticia['autor_nombre']); ?></a>

                        </div>
                        <div class="home-metadato-item">
                            <span class="home-metadato-icono">📂</span>
                            <a href="<?php echo route('categoria', ['id' => (int) $noticia['id_categoria']]); ?>" class="home-metadato-enlace">

                                <?php echo htmlspecialchars($noticia['nombre_categoria']); ?>

                            </a>
                        </div>
                        <div class="home-metadato-item">
                            <span class="home-metadato-icono">📅</span>
                            <span class="home-metadato-texto"><?php echo formatearFecha($noticia['fecha_publicacion'], 'd/m/Y'); ?></span>

                        </div>
                        
                        <!-- Ubicación si existe -->
                        <?php

                        $nombre_ubicacion = '';
                        $url_ubicacion = '#';
                        if ($noticia['tipo_ubicacion'] == 'espana' && $noticia['id_provincia']) {
                            $stmt_ub = $pdo->prepare("
                                SELECT p.nombre as provincia, c.nombre as comunidad
                                FROM provincias p
                                JOIN comunidades c ON p.id_comunidad = c.id_comunidad
                                WHERE p.id_provincia = ?
                            ");
                            $stmt_ub->execute([$noticia['id_provincia']]);
                            $ubicacion_data = $stmt_ub->fetch();
                            if ($ubicacion_data) {
                                $nombre_ubicacion = $ubicacion_data['provincia'] . ' (' . $ubicacion_data['comunidad'] . ')';
                                $url_ubicacion = 'ubicacion.php?provincia=' . $noticia['id_provincia'];
                            }
                        } elseif ($noticia['tipo_ubicacion'] == 'internacional' && $noticia['lugar_internacional']) {
                            $nombre_ubicacion = $noticia['lugar_internacional'];
                            $url_ubicacion = 'ubicacion.php?internacional=' . urlencode($noticia['lugar_internacional']);
                        }
                        
                        if ($nombre_ubicacion): ?>
                            <div class="home-metadato-item">
                                <span class="home-metadato-icono">📍</span>
                                <a href="<?php echo $url_ubicacion; ?>" class="home-metadato-enlace">

                                    <?php echo htmlspecialchars($nombre_ubicacion); ?>

                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="home-metadato-item">
                            <span class="home-metadato-icono">💬</span>
                            <a href="<?php echo route('comentarios_noticia', ['id' => (int) $noticia['id_noticia']]); ?>" class="home-metadato-enlace">
                                <?php echo (int) ($noticia['total_comentarios'] ?? 0); ?> Coment
                            </a>
                        </div>

                        <?php if (!empty($noticia['fuente_rss_nombre'])): ?>
                            <div class="home-metadato-item">
                                <span class="home-metadato-icono">📡</span>
                                <a href="<?php echo route('buscar', ['fuente' => 'rss:' . (int) $noticia['id_fuente_rss']]); ?>" class="home-metadato-enlace">
                                    <?php echo htmlspecialchars((string) $noticia['fuente_rss_nombre']); ?>
                                </a>
                            </div>
                        <?php elseif (!empty($noticia['fuente_normal_nombre'])): ?>
                            <div class="home-metadato-item">
                                <span class="home-metadato-icono">📰</span>
                                <a href="<?php echo route('buscar', ['fuente' => 'normal:' . (int) $noticia['id_fuente']]); ?>" class="home-metadato-enlace">
                                    <?php echo htmlspecialchars((string) $noticia['fuente_normal_nombre']); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                    
                    <!-- 6. BOTÓN LEER MÁS -->
                    <div class="home-tarjeta-acciones news-card__actions">
                        <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>" class="home-btn home-btn-leer-mas news-card__button">

                            <span>Leer noticia completa</span>
                            <span class="home-btn-icono">→</span>
                        </a>
                    </div>
                    
                </article>
            <?php endforeach; ?>

        </div>
        
        <!-- PAGINACIÓN MEJORADA -->
        <?php if ($total_paginas > 1): ?>

            <div class="home-paginacion">
                <?php if ($pagina > 1): ?>

                    <a href="?pagina=<?php echo $pagina - 1; ?>" class="home-pagina-btn home-pagina-anterior">

                        <span class="home-pagina-icono">←</span>
                        <span class="home-pagina-texto">Anterior</span>
                    </a>
                <?php endif; ?>

                
                <div class="home-pagina-numeros">
                    <?php

                    // Mostrar páginas alrededor de la actual
                    $rango = 2;
                    $inicio = max(1, $pagina - $rango);
                    $fin = min($total_paginas, $pagina + $rango);
                    
                    if ($inicio > 1): ?>
                        <a href="?pagina=1" class="home-pagina-numero">1</a>
                        <?php if ($inicio > 2): ?>

                            <span class="home-pagina-puntos">...</span>
                        <?php endif; ?>

                    <?php endif; ?>

                    
                    <?php for ($i = $inicio; $i <= $fin; $i++): ?>

                        <?php if ($i == $pagina): ?>

                            <span class="home-pagina-numero home-pagina-activo"><?php echo $i; ?></span>

                        <?php else: ?>

                            <a href="?pagina=<?php echo $i; ?>" class="home-pagina-numero"><?php echo $i; ?></a>

                        <?php endif; ?>

                    <?php endfor; ?>

                    
                    <?php if ($fin < $total_paginas): ?>

                        <?php if ($fin < $total_paginas - 1): ?>

                            <span class="home-pagina-puntos">...</span>
                        <?php endif; ?>

                        <a href="?pagina=<?php echo $total_paginas; ?>" class="home-pagina-numero"><?php echo $total_paginas; ?></a>

                    <?php endif; ?>

                </div>
                
                <?php if ($pagina < $total_paginas): ?>

                    <a href="?pagina=<?php echo $pagina + 1; ?>" class="home-pagina-btn home-pagina-siguiente">

                        <span class="home-pagina-texto">Siguiente</span>
                        <span class="home-pagina-icono">→</span>
                    </a>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        
    <?php endif; ?>


</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
