<?php
declare(strict_types=1);


/**
 * NOTICIAS MÁS VISTAS (POPULARES)
 * Muestra las noticias ordenadas por número de visitas
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/routes.php'; // ← AÑADIDO: para URLs amigables

$paginaEntrada = $_GET['pagina'] ?? null;
$pagina = is_scalar($paginaEntrada) ? max(1, (int) $paginaEntrada) : 1;
$por_pagina = ITEMS_PER_PAGE;
$offset = ($pagina - 1) * $por_pagina;

// Permitir diferentes períodos
$periodo = is_string($_GET['periodo'] ?? null) ? $_GET['periodo'] : 'todo';
$filtro_fecha = '';
$noticias = [];
$top_noticia = false;
$total_noticias = 0;
$total_paginas = 0;
$error = null;

switch ($periodo) {
    case 'semana':
        $filtro_fecha = "AND n.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'mes':
        $filtro_fecha = "AND n.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'ano':
        $filtro_fecha = "AND n.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    default:
        $periodo = 'todo';
        break;
}

try {
    $pdo = db();
    
    // Total de noticias publicadas (con filtro de período)
    $sql_total = "SELECT COUNT(*) FROM noticias n WHERE n.estado = 'publicada' AND n.privada = 0 $filtro_fecha";
    $stmt_total = $pdo->query($sql_total);
    $total_noticias = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_noticias / $por_pagina);
    
    // Noticias más vistas
    $sql = "
        SELECT n.*, 
               u.nombre as autor_nombre, 
               u.avatar as autor_avatar,
               c.nombre_categoria,
               c.slug_categoria,
               (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia AND estado = 'aprobado') as total_comentarios
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        JOIN categorias c ON n.id_categoria = c.id_categoria
        WHERE n.estado = 'publicada' AND n.privada = 0 $filtro_fecha
        ORDER BY n.visitas DESC, n.fecha_publicacion DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $noticias = $stmt->fetchAll();
    
    // Obtener la noticia más vista en general
    $stmt_top = $pdo->query("
        SELECT n.titulo, n.visitas, n.id_noticia
        FROM noticias n
        WHERE n.estado = 'publicada' AND n.privada = 0
        ORDER BY n.visitas DESC
        LIMIT 1
    ");
    $top_noticia = $stmt_top->fetch();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar las noticias populares.';
    registrarErrorInterno('PUBLIC.POPULARES.CARGA', $e);
}

$titulo_pagina = 'Noticias Más Populares';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-populares.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<div class="populares-container">
    <div class="populares-header">
        <h1>📊 Noticias Más Vistas</h1>
        <p class="populares-subtitulo">Las noticias que más interés han generado en nuestra comunidad</p>
    </div>
    
    <?php if ($top_noticia): ?>

        <div class="top-noticia-card news-card news-card--vertical news-card--public">
            <div class="top-noticia-badge">🏆 Noticia más popular</div>
            <div class="top-noticia-contenido">
                <h3 class="top-noticia-titulo">
                    <a href="<?php echo route('noticia', ['id' => $top_noticia['id_noticia']]); ?>">

                        <?php echo htmlspecialchars($top_noticia['titulo']); ?>

                    </a>
                </h3>
                <div class="top-noticia-visitas">
                    <span class="visitas-numero"><?php echo number_format($top_noticia['visitas']); ?></span>

                    <span class="visitas-label">visitas</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    
    <!-- Filtro de período -->
    <div class="filtros-periodo">
        <form method="GET" class="filtros-form">
            <label for="periodo">📅 Periodo:</label>
            <select name="periodo" id="periodo" onchange="this.form.submit()">
                <option value="todo" <?php echo $periodo == 'todo' ? 'selected' : ''; ?>>Todo el tiempo</option>

                <option value="ano" <?php echo $periodo == 'ano' ? 'selected' : ''; ?>>Último año</option>

                <option value="mes" <?php echo $periodo == 'mes' ? 'selected' : ''; ?>>Último mes</option>

                <option value="semana" <?php echo $periodo == 'semana' ? 'selected' : ''; ?>>Última semana</option>

            </select>
        </form>
    </div>
    
    <?php if (isset($error)): ?>

        <div class="alerta alerta-error">⚠️ <?php echo $error; ?></div>

    <?php endif; ?>

    
    <?php if (empty($noticias)): ?>

        <div class="alerta alerta-info">
            <p>📭 No hay noticias en este período.</p>
        </div>
    <?php else: ?>

        
        <div class="resultados-header">
            <p class="resultados-info">
                Mostrando <strong><?php echo count($noticias); ?></strong> de <strong><?php echo $total_noticias; ?></strong> noticias

            </p>
        </div>
        
        <!-- Grid de tarjetas de noticias populares -->
        <div class="populares-grid">
            <?php 

            $posicion = ($pagina - 1) * $por_pagina + 1;
            foreach ($noticias as $noticia): 
            ?>
                <article class="popular-card news-card news-card--vertical news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                    <h3 class="popular-titulo news-card__title">
                        <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">

                            <?php echo htmlspecialchars($noticia['titulo']); ?>

                        </a>
                    </h3>
                    
                    <?php if (!empty($noticia['subtitulo'])): ?>

                        <p class="popular-subtitulo news-card__subtitle"><?php echo htmlspecialchars($noticia['subtitulo']); ?></p>

                    <?php endif; ?>

                    
                    <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>" class="popular-imagen-enlace">

                        <?php echo mostrarImagenNoticia($noticia, 'public-categorias-card-imagen news-card__media', '📷'); ?>

                    </a>
                    
                    <div class="popular-card-header">
                        <span class="popular-posicion">#<?php echo $posicion; ?></span>

                        <div class="popular-stats-header">
                            <span class="popular-visitas-header">👁️ <?php echo number_format($noticia['visitas']); ?></span>

                        </div>
                    </div>
                    
                    <div class="popular-contenido news-card__body">
                        <div class="popular-stats-grid">
                            <div class="popular-stat-card">
                                <span class="popular-stat-icon">👁️</span>
                                <div class="popular-stat-info">
                                    <span class="popular-stat-value"><?php echo number_format($noticia['visitas']); ?></span>

                                    <span class="popular-stat-label">Visitas</span>
                                </div>
                            </div>
                            
                            <div class="popular-stat-card">
                                <span class="popular-stat-icon">💬</span>
                                <div class="popular-stat-info">
                                    <span class="popular-stat-value"><?php echo $noticia['total_comentarios']; ?></span>

                                    <span class="popular-stat-label">Comentarios</span>
                                </div>
                            </div>
                            
                            <div class="popular-stat-card">
                                <span class="popular-stat-icon">❤️</span>
                                <div class="popular-stat-info">
                                    <span class="popular-stat-value"><?php echo $noticia['megusta'] ?? 0; ?></span>

                                    <span class="popular-stat-label">Me gusta</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="popular-card-footer news-card__actions">
                        <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>" class="btn-leer-mas news-card__button">

                            <span>Leer noticia completa</span>
                            <span class="btn-icon">→</span>
                        </a>
                    </div>
                </article>
            <?php 

                $posicion++;
            endforeach; 
            ?>
        </div>
        
        <!-- Paginación mejorada -->
        <?php if ($total_paginas > 1): ?>

            <div class="paginacion-moderna">
                <div class="paginacion-info">
                    Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>

                </div>
                <div class="paginacion-botones">
                    <?php if ($pagina > 1): ?>

                        <a href="<?php echo route('populares', ['pagina' => $pagina - 1, 'periodo' => $periodo]); ?>" class="paginacion-btn prev" title="Página anterior">

                            <span class="btn-icon">←</span>
                            <span class="btn-text">Anteriores</span>
                        </a>
                    <?php endif; ?>

                    
                    <div class="paginacion-numeros">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                            <?php if ($i == $pagina): ?>

                                <span class="page-number active"><?php echo $i; ?></span>

                            <?php else: ?>

                                <a href="<?php echo route('populares', ['pagina' => $i, 'periodo' => $periodo]); ?>" class="page-number"><?php echo $i; ?></a>

                            <?php endif; ?>

                        <?php endfor; ?>

                    </div>
                    
                    <?php if ($pagina < $total_paginas): ?>

                        <a href="<?php echo route('populares', ['pagina' => $pagina + 1, 'periodo' => $periodo]); ?>" class="paginacion-btn next" title="Página siguiente">

                            <span class="btn-text">Siguientes</span>
                            <span class="btn-icon">→</span>
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

        
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
