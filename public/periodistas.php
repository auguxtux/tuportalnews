<?php
declare(strict_types=1);


/**
 * PÁGINA DE PERIODISTAS
 * Muestra listado de periodistas y sus noticias con comentarios
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';

$id_periodista = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = ITEMS_PER_PAGE;
$offset = ($pagina - 1) * $por_pagina;

try {
    $pdo = db();
    
    // ============================================
    // CASO 1: NO HAY ID - MOSTRAR LISTADO DE PERIODISTAS
    // ============================================
    if (!$id_periodista) {
        // Obtener todos los periodistas con estadísticas
        $stmt = $pdo->query("
            SELECT 
                u.*,
                COUNT(DISTINCT n.id_noticia) as total_noticias,
                COALESCE(SUM(n.visitas), 0) as total_visitas,
                MAX(n.fecha_publicacion) as ultima_noticia
            FROM usuarios u
            LEFT JOIN noticias n ON u.id_usuario = n.id_autor AND n.estado = 'publicada' AND n.privada = 0
            WHERE u.rol = 'periodista' AND u.estado = 'activo'
            GROUP BY u.id_usuario
            ORDER BY total_noticias DESC, u.nombre ASC
        ");
        $periodistas = $stmt->fetchAll();
        
        $titulo_pagina = 'Nuestros Articulistas';
        require_once __DIR__ . '/../partials/header.php';
        ?>
        <link rel="stylesheet" href="<?php echo css_url('public-periodistas.css'); ?>">

        
        <div class="public-periodistas-container">

            <h1>Editores de TuPortalNews</h1>
            <p class="public-periodistas-subtitulo">Conoce al equipo de profesionales que hacen posible las noticias</p>
            
            <?php if (empty($periodistas)): ?>

                <div class="public-periodistas-alerta public-periodistas-alerta-info">
                    <p>No hay periodistas disponibles en este momento.</p>
                </div>
            <?php else: ?>

                <div class="public-periodistas-grid">
                    <?php foreach ($periodistas as $per): 

                        $promedio_visitas = $per['total_noticias'] > 0 
                            ? round($per['total_visitas'] / $per['total_noticias'], 1) 
                            : 0;
                    ?>
                        <div class="public-periodistas-card">
                            <div class="public-periodistas-card-avatar">
                                <img src="<?php echo base_url('uploads/perfiles/' . ($per['avatar'] ?? 'default-avatar.png')); ?>" 

                                     alt="<?php echo htmlspecialchars($per['nombre']); ?>">

                            </div>
                            <h2 class="public-periodistas-card-nombre">
                                <a href="<?php echo route('periodista', ['id' => $per['id_usuario']]); ?>">

                                    <?php echo htmlspecialchars($per['nombre']); ?>

                                </a>
                            </h2>
                            <?php if ($per['ciudad']): ?>

                                <p class="public-periodistas-card-ciudad">📍 <?php echo htmlspecialchars($per['ciudad']); ?></p>

                            <?php endif; ?>

                            <?php if ($per['biografia']): ?>

                                <p class="public-periodistas-card-bio"><?php echo htmlspecialchars(truncarTexto($per['biografia'], 120)); ?></p>

                            <?php endif; ?>

                            <div class="public-periodistas-card-stats">
                                <div class="public-periodistas-stat">
                                    <span class="public-periodistas-stat-numero"><?php echo $per['total_noticias']; ?></span>

                                    <span class="public-periodistas-stat-etiqueta">Noticias</span>
                                </div>
                                <div class="public-periodistas-stat">
                                    <span class="public-periodistas-stat-numero"><?php echo number_format((int) $per['total_visitas']); ?></span>

                                    <span class="public-periodistas-stat-etiqueta">Visitas</span>
                                </div>
                                <div class="public-periodistas-stat">
                                    <span class="public-periodistas-stat-numero"><?php echo number_format($promedio_visitas); ?></span>

                                    <span class="public-periodistas-stat-etiqueta">Promedio</span>
                                </div>
                            </div>
                            <?php if ($per['ultima_noticia']): ?>

                                <p class="public-periodistas-card-ultima">
                                    📅 Última noticia: <?php echo tiempoTranscurrido($per['ultima_noticia']); ?>

                                </p>
                            <?php endif; ?>

                            <a href="<?php echo route('periodista', ['id' => $per['id_usuario']]); ?>" class="public-periodistas-btn public-periodistas-btn-ver">

                                Ver sus noticias →
                            </a>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </div>
        <?php require_once __DIR__ . '/../partials/footer.php'; ?>
        <?php

        exit;
    }
    
    // CASO 2: HAY ID - MOSTRAR NOTICIAS DEL PERIODISTA
    
    // Obtener datos del periodista
    $stmt_per = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT n.id_noticia) as total_noticias,
               COALESCE(SUM(n.visitas), 0) as total_visitas,
               MAX(n.fecha_publicacion) as ultima_noticia
        FROM usuarios u
        LEFT JOIN noticias n ON u.id_usuario = n.id_autor AND n.estado = 'publicada' AND n.privada = 0
        WHERE u.id_usuario = :id AND u.rol = 'periodista' AND u.estado = 'activo'
        GROUP BY u.id_usuario
    ");
    $stmt_per->execute([':id' => $id_periodista]);
    $periodista = $stmt_per->fetch();
    
    if (!$periodista) {
        header('Location: ' . route('periodistas'));
        exit;
    }
    
    $titulo_pagina = 'Noticias de ' . $periodista['nombre'];
    
    // Total de noticias del periodista
    $stmt_total = $pdo->prepare("
        SELECT COUNT(*) 
        FROM noticias 
        WHERE id_autor = :id AND estado = 'publicada' AND privada = 0
    ");
    $stmt_total->execute([':id' => $id_periodista]);
    $total_noticias = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_noticias / $por_pagina);
    
    // Noticias del periodista
    $stmt = $pdo->prepare("
        SELECT n.*, 
               c.nombre_categoria,
               (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia AND estado = 'aprobado') as total_comentarios
        FROM noticias n
        JOIN categorias c ON n.id_categoria = c.id_categoria
        WHERE n.estado = 'publicada' AND n.privada = 0 AND n.id_autor = :id
        ORDER BY n.fecha_publicacion DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':id', $id_periodista, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $noticias = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar los periodistas.';
    registrarErrorInterno('PUBLIC.PERIODISTAS.CARGA', $e);
}

$titulo_pagina = isset($periodista) ? 'Noticias de ' . $periodista['nombre'] : 'Articulistas';
require_once __DIR__ . '/../partials/header.php';
?>

<!-- CSS ESPECÍFICO PARA VISTA INDIVIDUAL -->
<link rel="stylesheet" href="<?php echo css_url('public-periodistas.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<?php if (isset($error)): ?>

    <div class="public-periodistas-alerta public-periodistas-alerta-error"><?php echo $error; ?></div>

<?php endif; ?>


<?php if (isset($periodista) && $periodista): ?>

    <!-- CABECERA DEL PERIODISTA -->
    <div class="public-periodista-header">
        <a href="<?php echo route('periodistas'); ?>" class="public-periodistas-btn public-periodistas-btn-volver">←Editores</a>
        <div class="public-periodista-header-avatar">
            
            <img src="<?php echo base_url('uploads/perfiles/' . ($periodista['avatar'] ?? 'default-avatar.png')); ?>" 

                 alt="<?php echo htmlspecialchars($periodista['nombre']); ?>">

        </div>
        <h1 class="public-periodista-header-nombre"><?php echo htmlspecialchars($periodista['nombre']); ?></h1>

        <?php if ($periodista['ciudad']): ?>

                <p class="public-periodista-header-ciudad">📍 <?php echo htmlspecialchars($periodista['ciudad']); ?></p>

            <?php endif; ?>

        
        
        <div class="public-periodista-header-stats">
             
            <div class="public-periodista-stat-destacada">
                
                <span class="public-periodista-stat-valor"><?php echo $periodista['total_noticias']; ?></span>

                <span class="public-periodista-stat-etiqueta">Noticias</span>
            </div>
            <div class="public-periodista-stat-destacada">
                <span class="public-periodista-stat-valor"><?php echo number_format((int) $periodista['total_visitas']); ?></span>

                <span class="public-periodista-stat-etiqueta">Visitas</span>
            </div>
        </div>
    </div>
    
    <!-- NOTICIAS DEL PERIODISTA -->
    <div class="public-periodista-noticias">
        <h2 class="public-periodista-noticias-titulo">📰 Noticias de <?php echo htmlspecialchars($periodista['nombre']); ?></h2>

        
        <?php if (empty($noticias)): ?>

            <div class="public-periodistas-alerta public-periodistas-alerta-info">
                <p>Este periodista aún no ha publicado noticias.</p>
            </div>
        <?php else: ?>

            
            <p class="public-periodista-noticias-resultados">
                Mostrando <strong><?php echo count($noticias); ?></strong> de <strong><?php echo $total_noticias; ?></strong> noticias

            </p>
            
            <div class="public-periodista-noticias-grid">
                <?php foreach ($noticias as $noticia): ?>

                    <article class="public-periodista-noticia-card news-card news-card--vertical news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                        <h3 class="public-periodista-noticia-titulo news-card__title">
                            <a href="<?php echo route('noticia', ['id' => (int) $noticia['id_noticia']]); ?>">
                                <?php echo htmlspecialchars($noticia['titulo']); ?>
                            </a>
                        </h3>

                        <?php if ($noticia['subtitulo']): ?>
                            <p class="public-periodista-noticia-subtitulo news-card__subtitle"><?php echo htmlspecialchars($noticia['subtitulo']); ?></p>
                        <?php endif; ?>

                       <?php echo mostrarImagenNoticia(
                           $noticia,
                           'public-categorias-card-imagen news-card__media',
                           '📷',
                           route('noticia', ['id' => (int) $noticia['id_noticia']])
                       ); ?>

                        
                        <div class="public-periodista-noticia-contenido news-card__body">
                            <div class="public-periodista-noticia-meta news-card__meta news-card__meta--standard">
                                <span class="public-periodista-meta-item">📂 <a href="<?php echo route('categoria', ['id' => (int) $noticia['id_categoria']]); ?>"><?php echo htmlspecialchars($noticia['nombre_categoria']); ?></a></span>

                                <span class="public-periodista-meta-item">📅 <?php echo formatearFecha($noticia['fecha_publicacion'], 'd/m/Y'); ?></span>

                                <span class="public-periodista-meta-item">👁️ <?php echo number_format($noticia['visitas']); ?> visitas</span>

                                <span class="public-periodista-meta-item">💬 <a href="<?php echo route('comentarios_noticia', ['id' => (int) $noticia['id_noticia']]); ?>"><?php echo $noticia['total_comentarios']; ?> comentarios</a></span>

                            </div>
                            
                            <a href="<?php echo route('noticia', ['id' => (int) $noticia['id_noticia']]); ?>" class="public-periodistas-btn public-periodistas-btn-leer news-card__button">

                                Leer noticia →
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>

            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>

                <div class="public-periodistas-paginacion">
                    <?php if ($pagina > 1): ?>

                        <a href="?id=<?php echo $id_periodista; ?>&pagina=<?php echo $pagina - 1; ?>" 

                           class="public-periodistas-pagina-btn public-periodistas-pagina-anterior">
                            « Anteriores
                        </a>
                    <?php endif; ?>

                    
                    <div class="public-periodistas-pagina-numeros">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                            <?php if ($i == $pagina): ?>

                                <span class="public-periodistas-pagina-numero public-periodistas-pagina-activo"><?php echo $i; ?></span>

                            <?php else: ?>

                                <a href="?id=<?php echo $id_periodista; ?>&pagina=<?php echo $i; ?>" 

                                   class="public-periodistas-pagina-numero">
                                    <?php echo $i; ?>

                                </a>
                            <?php endif; ?>

                        <?php endfor; ?>

                    </div>
                    
                    <?php if ($pagina < $total_paginas): ?>

                        <a href="?id=<?php echo $id_periodista; ?>&pagina=<?php echo $pagina + 1; ?>" 

                           class="public-periodistas-pagina-btn public-periodistas-pagina-siguiente">
                            Siguientes »
                        </a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            
        <?php endif; ?>

    </div>

<?php else: ?>

    <div class="public-periodistas-alerta public-periodistas-alerta-error">
        <p>Articulista no encontrado.</p>
        <p><a href="<?php echo route('periodistas'); ?>" class="public-periodistas-btn">Volver al listado de periodistas</a></p>
    </div>
<?php endif; ?><?php require_once __DIR__ . '/../partials/footer.php'; ?>
