<?php
declare(strict_types=1);


/**
 * PÁGINA DE NOTICIAS POR UBICACIÓN
 * Filtra noticias por provincia, lugar internacional u otras ubicaciones
 * 
 * AHORA SOPORTA:
 * - 🇪🇸 España (provincias)
 * - 🌍 Internacional (lugar_internacional)
 * - 🗺️ Otras ubicaciones (otras_ubicacion - texto libre)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/routes.php'; // ← AÑADIDO: para URLs amigables

$provincia_parametro = $_GET['provincia'] ?? null;
$internacional_parametro = $_GET['internacional'] ?? null;
$otras_parametro = $_GET['otras'] ?? null;

$provincia_id = is_scalar($provincia_parametro) ? (int) $provincia_parametro : 0;
$internacional = is_string($internacional_parametro)
    ? urldecode(trim($internacional_parametro))
    : '';
$otras = is_string($otras_parametro) ? urldecode(trim($otras_parametro)) : '';

try {
    $pdo = db();
    
    // ============================================
    // OBTENER UBICACIONES CON NOTICIAS (PROVINCIAS)
    // ============================================
    $stmt_provincias = $pdo->prepare("
        SELECT 
            p.id_provincia,
            p.nombre as provincia,
            c.nombre as comunidad,
            COUNT(n.id_noticia) as total_noticias,
            MAX(n.fecha_publicacion) as ultima_noticia
        FROM provincias p
        JOIN comunidades c ON p.id_comunidad = c.id_comunidad
        JOIN noticias n ON n.id_provincia = p.id_provincia
        WHERE n.estado = 'publicada'
            AND n.privada = 0
            AND n.tipo_ubicacion = 'espana'
        GROUP BY p.id_provincia
        ORDER BY c.nombre, p.nombre
    ");
    $stmt_provincias->execute();
    $provincias = $stmt_provincias->fetchAll();
    
    // ============================================
    // OBTENER UBICACIONES CON NOTICIAS (INTERNACIONAL)
    // ============================================
    $stmt_internacional = $pdo->prepare("
        SELECT 
            lugar_internacional,
            COUNT(id_noticia) as total_noticias,
            MAX(fecha_publicacion) as ultima_noticia
        FROM noticias
        WHERE estado = 'publicada'
            AND privada = 0
            AND tipo_ubicacion = 'internacional'
            AND lugar_internacional IS NOT NULL
            AND lugar_internacional != ''
        GROUP BY lugar_internacional
        ORDER BY lugar_internacional
    ");
    $stmt_internacional->execute();
    $internacionales = $stmt_internacional->fetchAll();
    
    // ============================================
    // 🆕 OBTENER UBICACIONES CON NOTICIAS (OTRAS UBICACIONES)
    // ============================================
    $stmt_otras = $pdo->prepare("
        SELECT 
            otras_ubicacion,
            COUNT(id_noticia) as total_noticias,
            MAX(fecha_publicacion) as ultima_noticia
        FROM noticias
        WHERE estado = 'publicada'
            AND privada = 0
            AND tipo_ubicacion = 'otras'
            AND otras_ubicacion IS NOT NULL
            AND otras_ubicacion != ''
        GROUP BY otras_ubicacion
        ORDER BY otras_ubicacion
    ");
    $stmt_otras->execute();
    $otras_ubicaciones = $stmt_otras->fetchAll();
    
    // ============================================
    // SI HAY UNA UBICACIÓN SELECCIONADA, MOSTRAR SUS NOTICIAS
    // ============================================
    if ($provincia_id > 0 || !empty($internacional) || !empty($otras)) {
        
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        if ($pagina < 1) $pagina = 1;
        
        $por_pagina = ITEMS_PER_PAGE ?? 9;
        $offset = ($pagina - 1) * $por_pagina;
        
        $titulo_ubicacion = '';
        $where = '';
        $params = [];
        
        if ($provincia_id > 0) {
            // Obtener nombre de la provincia
            $stmt = $pdo->prepare("
                SELECT p.nombre as provincia, c.nombre as comunidad
                FROM provincias p
                JOIN comunidades c ON p.id_comunidad = c.id_comunidad
                WHERE p.id_provincia = ?
            ");
            $stmt->execute([$provincia_id]);
            $provincia = $stmt->fetch();
            
            if ($provincia) {
                $titulo_ubicacion = $provincia['provincia'] . ' (' . $provincia['comunidad'] . ')';
                $where = "n.tipo_ubicacion = 'espana' AND n.id_provincia = ?";
                $params[] = $provincia_id;
            } else {
                header('Location: ' . route('ubicacion'));
                exit;
            }
        } elseif (!empty($internacional)) {
            $titulo_ubicacion = htmlspecialchars($internacional);
            $where = "n.tipo_ubicacion = 'internacional' AND n.lugar_internacional = ?";
            $params[] = $internacional;
        } elseif (!empty($otras)) {
            $titulo_ubicacion = htmlspecialchars($otras);
            $where = "n.tipo_ubicacion = 'otras' AND n.otras_ubicacion = ?";
            $params[] = $otras;
        }
        
        // Obtener total de noticias
        $sql_total = "SELECT COUNT(*) FROM noticias n WHERE n.estado = 'publicada' AND n.privada = 0 AND " . $where;
        $stmt_total = $pdo->prepare($sql_total);
        $stmt_total->execute($params);
        $total_noticias = $stmt_total->fetchColumn();
        $total_paginas = ceil($total_noticias / $por_pagina);
        
        // Obtener noticias
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
            WHERE n.estado = 'publicada' AND n.privada = 0 AND " . $where . "
            ORDER BY n.fecha_publicacion DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $por_pagina;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $noticias = $stmt->fetchAll();
        
        $titulo_pagina = 'Noticias de ' . $titulo_ubicacion;
        require_once __DIR__ . '/../partials/header.php';
        ?>
        <link rel="stylesheet" href="<?php echo css_url('public-ubicacion.css'); ?>">
        <link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">
        <link rel="stylesheet" href="<?php echo css_url('public-news-browse.css'); ?>">

        
        <div class="contenido-principal">
            
            <!-- Cabecera con botón volver -->
            <div class="ubicacion-noticias-header">
                <a href="<?php echo route('ubicacion'); ?>" class="btn-back">

                    ← Volver a todas las ubicaciones
                </a>
                <h1 class="titulo">
                    <span class="ubicacion-icono">📍</span>
                    Noticias de <?php echo $titulo_ubicacion; ?>

                </h1>
                <p class="ubicacion-total"><?php echo number_format($total_noticias, 0, ',', '.'); ?> noticias</p>

            </div>
            
            <?php if (empty($noticias)): ?>

                <div class="alerta alerta-info" style="text-align: center; padding: 3rem;">
                    <p>No hay noticias disponibles para esta ubicación.</p>
                    <a href="<?php echo route('ubicacion'); ?>" class="btn btn-primary">Ver todas las ubicaciones</a>

                </div>
            <?php else: ?>

                
                <div class="grid-noticias">
                    <?php foreach ($noticias as $noticia): ?>

                        <article class="tarjeta-noticia news-card news-card--vertical news-card--location news-card--public<?php echo !empty($noticia['id_fuente_rss']) ? ' news-card--external' : ''; ?>">
                            <h2 class="tarjeta-titulo news-card__title">
                                <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">
                                    <?php echo htmlspecialchars($noticia['titulo']); ?>
                                </a>
                            </h2>
                            <?php if (!empty($noticia['subtitulo'])): ?>
                                <h3 class="tarjeta-subtitulo news-card__subtitle"><?php echo htmlspecialchars($noticia['subtitulo']); ?></h3>
                            <?php endif; ?>

                            <div class="tarjeta-imagen news-card__media">
                                <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">

                                    <?php echo mostrarImagenNoticia($noticia, 'tarjeta-imagen-img', '📷'); ?>

                                </a>
                            </div>
                            <div class="tarjeta-metadatos news-card__meta news-card__meta--standard">
                                <div class="metadato-autor">
                                    <img src="<?php echo base_url('uploads/perfiles/' . ($noticia['autor_avatar'] ?? 'default-avatar.png')); ?>" 

                                         alt="" width="20" height="20" class="avatar-mini">
                                    <a href="<?php echo route('periodistas', ['id' => (int) $noticia['id_autor']]); ?>"><?php echo htmlspecialchars($noticia['autor_nombre']); ?></a>

                                </div>
                                <div class="metadato-fecha">📅 <?php echo formatearFecha($noticia['fecha_publicacion']); ?></div>

                                <div class="metadato-categoria">📁 <a href="<?php echo route('categoria', ['id' => $noticia['id_categoria']]); ?>"><?php echo htmlspecialchars($noticia['nombre_categoria']); ?></a></div>

                                <div class="metadato-visitas">👁️ <?php echo number_format($noticia['visitas']); ?></div>

                                <div class="metadato-comentarios"><a href="<?php echo route('comentarios_noticia', ['id' => $noticia['id_noticia']]); ?>">💬 <?php echo $noticia['total_comentarios']; ?></a></div>

                            </div>
                            <div class="tarjeta-acciones news-card__actions">
                                <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>" class="btn btn-small news-card__button">Leer más →</a>

                            </div>
                        </article>
                    <?php endforeach; ?>

                </div>
                
                <?php if ($total_paginas > 1): ?>

                    <div class="paginacion">
                        <?php if ($pagina > 1): ?>

                            <a href="<?php echo route('ubicacion', array_merge(

                                $provincia_id ? ['provincia' => $provincia_id] : ($internacional ? ['internacional' => $internacional] : ['otras' => $otras]),
                                ['pagina' => $pagina - 1]
                            )); ?>" class="btn-pagina">« Anterior</a>
                        <?php endif; ?>

                        
                        <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>

                            <?php if ($i == $pagina): ?>

                                <span class="btn-pagina active"><?php echo $i; ?></span>

                            <?php else: ?>

                                <a href="<?php echo route('ubicacion', array_merge(

                                    $provincia_id ? ['provincia' => $provincia_id] : ($internacional ? ['internacional' => $internacional] : ['otras' => $otras]),
                                    ['pagina' => $i]
                                )); ?>" class="btn-pagina"><?php echo $i; ?></a>

                            <?php endif; ?>

                        <?php endfor; ?>

                        
                        <?php if ($pagina < $total_paginas): ?>

                            <a href="<?php echo route('ubicacion', array_merge(

                                $provincia_id ? ['provincia' => $provincia_id] : ($internacional ? ['internacional' => $internacional] : ['otras' => $otras]),
                                ['pagina' => $pagina + 1]
                            )); ?>" class="btn-pagina">Siguiente »</a>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                
            <?php endif; ?>

            
        </div>
        
        <?php

        require_once __DIR__ . '/../partials/footer.php';
        exit;
    }
    
    // ============================================
    // POR DEFECTO: MOSTRAR LISTADO DE TODAS LAS UBICACIONES CON NOTICIAS
    // ============================================
    
    $titulo_pagina = 'Noticias por lugares';
    require_once __DIR__ . '/../partials/header.php';
    ?>
    
    <link rel="stylesheet" href="<?php echo css_url('public-ubicacion.css'); ?>">
    <link rel="stylesheet" href="<?php echo css_url('public-news-browse.css'); ?>">

    
    <div class="contenido-principal">
        
        <div class="ubicacion-header">
            <h1>
                <span class="header-icon">📍</span>
                Noticias por lugares
            </h1>
            <p class="ubicacion-desc">Selecciona un lugar para consultar sus noticias</p>
        </div>
        
        <?php if (empty($provincias) && empty($internacionales) && empty($otras_ubicaciones)): ?>

            <div class="alerta alerta-info" style="text-align: center; padding: 3rem;">
                <h2>⚠️ No hay ubicaciones disponibles</h2>
                <p>No se encontraron noticias con ubicación geográfica asignada.</p>
                <a href="<?php echo route('home'); ?>" class="btn btn-primary">Volver al inicio</a>

            </div>
        <?php else: ?>

            
            <!-- Sección España (Provincias) -->
            <?php if (!empty($provincias)): ?>

            <div class="ubicacion-seccion">
                <h2 class="seccion-titulo">
                    <span class="seccion-icono">🇪🇸</span>
                    España
                    <span class="seccion-count">(<?php echo count($provincias); ?> ubicaciones con noticias)</span>

                </h2>
                <div class="grid-ubicaciones">
                    <?php foreach ($provincias as $prov): ?>

                        <a href="<?php echo route('ubicacion', ['provincia' => $prov['id_provincia']]); ?>" class="tarjeta-ubicacion">

                            <div class="tarjeta-ubicacion-icono"><span>📍</span></div>
                            <div class="tarjeta-ubicacion-info">
                                <h3 class="tarjeta-ubicacion-nombre"><?php echo htmlspecialchars($prov['provincia']); ?></h3>

                                <p class="tarjeta-ubicacion-comunidad"><?php echo htmlspecialchars($prov['comunidad']); ?></p>

                                <div class="tarjeta-ubicacion-stats">
                                    <span class="stat">📄 <?php echo $prov['total_noticias']; ?> noticias</span>

                                    <?php if ($prov['ultima_noticia']): ?>

                                        <span class="stat">🕒 <?php echo formatearFecha($prov['ultima_noticia']); ?></span>

                                    <?php endif; ?>

                                </div>
                            </div>
                            <div class="tarjeta-ubicacion-arrow"><span>→</span></div>
                        </a>
                    <?php endforeach; ?>

                </div>
            </div>
            <?php endif; ?>

            
            <!-- Sección Internacional -->
            <?php if (!empty($internacionales)): ?>

            <div class="ubicacion-seccion">
                <h2 class="seccion-titulo">
                    <span class="seccion-icono">🌍</span>
                    Internacional
                    <span class="seccion-count">(<?php echo count($internacionales); ?> ubicaciones con noticias)</span>

                </h2>
                <div class="grid-ubicaciones">
                    <?php foreach ($internacionales as $inter): ?>

                        <a href="<?php echo route('ubicacion', ['internacional' => $inter['lugar_internacional']]); ?>" class="tarjeta-ubicacion">

                            <div class="tarjeta-ubicacion-icono"><span>🌎</span></div>
                            <div class="tarjeta-ubicacion-info">
                                <h3 class="tarjeta-ubicacion-nombre"><?php echo htmlspecialchars($inter['lugar_internacional']); ?></h3>

                                <div class="tarjeta-ubicacion-stats">
                                    <span class="stat">📄 <?php echo $inter['total_noticias']; ?> noticias</span>

                                    <?php if ($inter['ultima_noticia']): ?>

                                        <span class="stat">🕒 <?php echo formatearFecha($inter['ultima_noticia']); ?></span>

                                    <?php endif; ?>

                                </div>
                            </div>
                            <div class="tarjeta-ubicacion-arrow"><span>→</span></div>
                        </a>
                    <?php endforeach; ?>

                </div>
            </div>
            <?php endif; ?>

            
            <!-- 🆕 Sección: Otras ubicaciones -->
            <?php if (!empty($otras_ubicaciones)): ?>

            <div class="ubicacion-seccion">
                <h2 class="seccion-titulo">
                    <span class="seccion-icono">🗺️</span>
                    Otras ubicaciones
                    <span class="seccion-count">(<?php echo count($otras_ubicaciones); ?> ubicaciones con noticias)</span>

                </h2>
                <div class="grid-ubicaciones">
                    <?php foreach ($otras_ubicaciones as $ubi): ?>

                        <a href="<?php echo route('ubicacion', ['otras' => $ubi['otras_ubicacion']]); ?>" class="tarjeta-ubicacion">

                            <div class="tarjeta-ubicacion-icono"><span>🗺️</span></div>
                            <div class="tarjeta-ubicacion-info">
                                <h3 class="tarjeta-ubicacion-nombre"><?php echo htmlspecialchars($ubi['otras_ubicacion']); ?></h3>

                                <div class="tarjeta-ubicacion-stats">
                                    <span class="stat">📄 <?php echo $ubi['total_noticias']; ?> noticias</span>

                                    <?php if ($ubi['ultima_noticia']): ?>

                                        <span class="stat">🕒 <?php echo formatearFecha($ubi['ultima_noticia']); ?></span>

                                    <?php endif; ?>

                                </div>
                            </div>
                            <div class="tarjeta-ubicacion-arrow"><span>→</span></div>
                        </a>
                    <?php endforeach; ?>

                </div>
            </div>
            <?php endif; ?>

            
        <?php endif; ?>

        
    </div>
    
    <?php

    require_once __DIR__ . '/../partials/footer.php';
    
} catch (Exception $e) {
    registrarErrorInterno('PUBLIC.UBICACION.CARGA', $e);
    $titulo_pagina = 'Error';
    require_once __DIR__ . '/../partials/header.php';
    ?>
    <div class="contenido-principal">
        <div class="alerta alerta-error" style="text-align: center; padding: 3rem;">
            <h2>⚠️ Error</h2>
            <p>Ha ocurrido un error al cargar las ubicaciones.</p>
            <a href="<?php echo route('home'); ?>" class="btn btn-primary">Volver al inicio</a>

        </div>
    </div>
    <?php

    require_once __DIR__ . '/../partials/footer.php';
}
?>
