<?php
declare(strict_types=1);


/**
 * PÁGINA DE NOTICIAS POR FUENTE
 * Muestra noticias de una fuente o listado de todas las fuentes
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/routes.php';

// Obtener la fuente
$fuente_nombre = '';
$fuente_id = 0;
$nombre_parametro = $_GET['nombre'] ?? null;
$fuente_parametro = $_GET['fuente'] ?? null;
$id_parametro = $_GET['id'] ?? null;

if (is_string($nombre_parametro) && trim($nombre_parametro) !== '') {
    $fuente_nombre = urldecode(trim($nombre_parametro));
} elseif (is_string($fuente_parametro) && trim($fuente_parametro) !== '') {
    $fuente_nombre = urldecode(trim($fuente_parametro));
} elseif (is_scalar($id_parametro) && (int) $id_parametro > 0) {
    $fuente_id = (int) $id_parametro;
}

try {
    $pdo = db();
    
    // ============================================
    // CASO 1: NO HAY FUENTE ESPECIFICADA
    // ============================================
    if (empty($fuente_nombre) && !$fuente_id) {
        $stmt = $pdo->prepare("
            SELECT f.*, 
                   COUNT(n.id_noticia) as total_noticias,
                   (SELECT id_noticia FROM noticias 
                    WHERE id_fuente = f.id_fuente AND estado = 'publicada' AND privada = 0
                    ORDER BY fecha_publicacion DESC LIMIT 1) as ultima_noticia_id
            FROM fuentes f
            LEFT JOIN noticias n ON n.id_fuente = f.id_fuente AND n.estado = 'publicada' AND n.privada = 0
            WHERE f.activa = 1
            GROUP BY f.id_fuente
            HAVING total_noticias > 0
            ORDER BY f.nombre ASC
        ");
        $stmt->execute();
        $fuentes = $stmt->fetchAll();
        
        $titulo_pagina = 'Fuentes de Noticias';
        require_once __DIR__ . '/../partials/header.php';
        ?>
        <link rel="stylesheet" href="<?php echo css_url('public-fuentes.css'); ?>">
        <link rel="stylesheet" href="<?php echo css_url('public-news-browse.css'); ?>">

        
        <div class="contenido-principal">
            <div class="fuente-header">
                <h1><span class="fuente-icono">📰</span> Fuentes de Noticias</h1>
                <p class="fuente-total">Selecciona una fuente para ver sus noticias</p>
            </div>
            
            <?php if (empty($fuentes)): ?>

                <div class="alerta alerta-info" style="text-align: center; padding: 3rem;">
                    <h2>⚠️ No hay fuentes disponibles</h2>
                    <p><a href="<?php echo route('home'); ?>">Volver al inicio</a></p>

                </div>
            <?php else: ?>

                <div class="grid-fuentes">
                    <?php foreach ($fuentes as $fuente): 

                        $ultima_noticia = null;
                        if ($fuente['ultima_noticia_id']) {
                            $stmt_noticia = $pdo->prepare("
                                SELECT id_noticia, titulo, imagen_principal, fecha_publicacion
                                FROM noticias WHERE id_noticia = ? AND estado = 'publicada' AND privada = 0
                            ");
                            $stmt_noticia->execute([$fuente['ultima_noticia_id']]);
                            $ultima_noticia = $stmt_noticia->fetch();
                        }
                    ?>
                        <div class="tarjeta-fuente-completa">
                            <div class="tarjeta-fuente-header">
                                <h3 class="tarjeta-fuente-nombre">📰 <?php echo htmlspecialchars($fuente['nombre']); ?></h3>

                                <span class="fuente-noticias-count"><?php echo $fuente['total_noticias']; ?> noticias</span>

                            </div>
                            
                            <?php if ($ultima_noticia): ?>

                                <div class="tarjeta-fuente-ultima-noticia">
                                    <?php if (!empty($ultima_noticia['imagen_principal'])): ?>

                                        <div class="ultima-noticia-imagen">
                                            <a href="<?php echo route('noticia', ['id' => $ultima_noticia['id_noticia']]); ?>">

                                                <?php echo mostrarImagenNoticia($ultima_noticia, 'ultima-noticia-img', '📷'); ?>

                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ultima-noticia-info">
                                        <h4 class="ultima-noticia-titulo">
                                            <a href="<?php echo route('noticia', ['id' => $ultima_noticia['id_noticia']]); ?>">

                                                <?php echo htmlspecialchars($ultima_noticia['titulo']); ?>

                                            </a>
                                        </h4>
                                        <div class="ultima-noticia-fecha">
                                            📅 <?php echo formatearFecha($ultima_noticia['fecha_publicacion'], 'd/m/Y'); ?>

                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>

                                <div class="tarjeta-fuente-sin-noticias">
                                    <p>No hay noticias recientes</p>
                                </div>
                            <?php endif; ?>

                            
                            <div class="tarjeta-fuente-footer">
                                <a href="<?php echo route('fuente', ['nombre' => $fuente['nombre']]); ?>" class="btn-ver-todas">

                                    Ver noticias de <?php echo htmlspecialchars($fuente['nombre']); ?> →

                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </div>
        <?php

        require_once __DIR__ . '/../partials/footer.php';
        exit;
    }
    
    // ============================================
    // CASO 2: HAY FUENTE SELECCIONADA
    // ============================================
    
    $datos_fuente = null;
    
    if ($fuente_nombre) {
        $stmt = $pdo->prepare("SELECT * FROM fuentes WHERE nombre = ? AND activa = 1");
        $stmt->execute([$fuente_nombre]);
        $datos_fuente = $stmt->fetch();
    } elseif ($fuente_id) {
        $stmt = $pdo->prepare("SELECT * FROM fuentes WHERE id_fuente = ? AND activa = 1");
        $stmt->execute([$fuente_id]);
        $datos_fuente = $stmt->fetch();
    }
    
    if (!$datos_fuente) {
        $titulo_pagina = 'Fuente no encontrada';
        require_once __DIR__ . '/../partials/header.php';
        echo '<div class="alerta alerta-info"><h2>⚠️ Fuente no encontrada</h2></div>';
        require_once __DIR__ . '/../partials/footer.php';
        exit;
    }
    
    $fuente_nombre = $datos_fuente['nombre'];
    
    // Paginación
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $por_pagina = ITEMS_PER_PAGE ?? 9;
    $offset = ($pagina - 1) * $por_pagina;
    
    // Total de noticias
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE estado = 'publicada' AND privada = 0 AND id_fuente = ?");
    $stmt->execute([$datos_fuente['id_fuente']]);
    $total_noticias = $stmt->fetchColumn();
    $total_paginas = max(1, ceil($total_noticias / $por_pagina));
    
    // Obtener noticias
    $stmt = $pdo->prepare("
        SELECT n.*, u.nombre as autor_nombre, c.nombre_categoria, c.slug_categoria
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        JOIN categorias c ON n.id_categoria = c.id_categoria
        WHERE n.estado = 'publicada' AND n.privada = 0 AND n.id_fuente = ?
        ORDER BY n.fecha_publicacion DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$datos_fuente['id_fuente'], $por_pagina, $offset]);
    $noticias = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'No se pudo cargar la fuente.';
    registrarErrorInterno('PUBLIC.FUENTE.CARGA', $e);
}

$titulo_pagina = 'Noticias de ' . htmlspecialchars($fuente_nombre);
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-fuentes.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('public-news-browse.css'); ?>">

<div class="contenido-principal">
    
    <!-- CABECERA -->
    <div class="fuente-header">
        <div class="fuente-header-content">
            <a href="<?php echo route('fuente'); ?>" class="btn-back">← Todas las fuentes</a>

            <h1><span class="fuente-icono">📰</span> <?php echo htmlspecialchars($fuente_nombre); ?></h1>

            
            <?php if ($datos_fuente['comentario']): ?>

                <p class="fuente-comentario"><?php echo nl2br(htmlspecialchars($datos_fuente['comentario'])); ?></p>

            <?php endif; ?>

            
            <p class="fuente-total">📄 <?php echo number_format($total_noticias, 0, ',', '.'); ?> noticias</p>

        </div>
    </div>
    
    <!-- GRID DE NOTICIAS - SIGUIENDO EL MISMO PATRÓN QUE UBICACION.PHP -->
    <div class="grid-noticias">
        <?php if (empty($noticias)): ?>

            <div class="alerta alerta-info">
                <p>No hay noticias de esta fuente.</p>
                <p><a href="<?php echo route('fuente'); ?>">Ver todas las fuentes</a></p>

            </div>
        <?php else: ?>

            <?php foreach ($noticias as $noticia): ?>
                <?php
                $noticiaTarjeta = $noticia;
                $varianteTarjeta = 'source';
                require __DIR__ . '/../partials/noticias/tarjeta-listado-publico.php';
                ?>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
    
    <!-- PAGINACIÓN -->
    <?php if ($total_paginas > 1): ?>

        <div class="paginacion">
            <?php if ($pagina > 1): ?>

                <a href="<?php echo route('fuente', ['nombre' => $fuente_nombre, 'pagina' => $pagina - 1]); ?>" class="btn-pagina">« Anterior</a>

            <?php endif; ?>

            
            <?php

            $rango = 2;
            $inicio = max(1, $pagina - $rango);
            $fin = min($total_paginas, $pagina + $rango);
            
            if ($inicio > 1): ?>
                <a href="<?php echo route('fuente', ['nombre' => $fuente_nombre, 'pagina' => 1]); ?>" class="btn-pagina">1</a>

                <?php if ($inicio > 2): ?><span class="btn-pagina disabled">...</span><?php endif; ?>

            <?php endif; ?>

            
            <?php for ($i = $inicio; $i <= $fin; $i++): ?>

                <?php if ($i == $pagina): ?>

                    <span class="btn-pagina active"><?php echo $i; ?></span>

                <?php else: ?>

                    <a href="<?php echo route('fuente', ['nombre' => $fuente_nombre, 'pagina' => $i]); ?>" class="btn-pagina"><?php echo $i; ?></a>

                <?php endif; ?>

            <?php endfor; ?>

            
            <?php if ($fin < $total_paginas): ?>

                <?php if ($fin < $total_paginas - 1): ?><span class="btn-pagina disabled">...</span><?php endif; ?>

                <a href="<?php echo route('fuente', ['nombre' => $fuente_nombre, 'pagina' => $total_paginas]); ?>" class="btn-pagina"><?php echo $total_paginas; ?></a>

            <?php endif; ?>

            
            <?php if ($pagina < $total_paginas): ?>

                <a href="<?php echo route('fuente', ['nombre' => $fuente_nombre, 'pagina' => $pagina + 1]); ?>" class="btn-pagina">Siguiente »</a>

            <?php endif; ?>

        </div>
    <?php endif; ?>

    
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
