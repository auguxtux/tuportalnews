<?php
declare(strict_types=1);


/**
 * ADMIN - BUSCADOR DE NOTICIAS PRIVADAS
 * Listado de noticias marcadas como privadas con filtros
 * Diseño responsive con tarjetas (3-2-1 columnas)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();

// Obtener categorías para el filtro
$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM categorias WHERE activa = 1 ORDER BY nombre_categoria")->fetchAll();

// Obtener usuarios privados para el filtro
$usuarios_privados = $pdo->query("
    SELECT u.id_usuario, u.nombre
    FROM usuarios u
    INNER JOIN usuarios_privados up ON u.id_usuario = up.id_usuario
    WHERE up.activo = 1 AND u.rol = 'periodista'
    ORDER BY u.nombre
")->fetchAll();

// Filtros
$filtro_categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$filtro_usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Paginación
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 12; // Múltiplo de 3 para grid
$offset = ($pagina - 1) * $por_pagina;

try {
    // Consulta base
    $sql_count = "SELECT COUNT(*) FROM noticias WHERE privada = 1";
    
    $sql = "
        SELECT 
            n.id_noticia,
            n.titulo,
            n.subtitulo,
            n.fecha_publicacion,
            n.estado,
            n.privada,
            n.imagen_principal,
            u.nombre as autor_nombre,
            u.id_usuario as autor_id,
            u.avatar as autor_avatar,
            c.nombre_categoria,
            c.id_categoria as categoria_id,
            COALESCE(ep.visitas_privadas, 0) as visitas_priv,
            COALESCE(ep.megusta_privados, 0) as megusta_priv
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        JOIN categorias c ON n.id_categoria = c.id_categoria
        LEFT JOIN estadisticas_privadas ep ON n.id_noticia = ep.id_noticia
        WHERE n.privada = 1
    ";
    
    $params = [];
    
    // Aplicar filtros
    if ($filtro_categoria > 0) {
        $sql .= " AND n.id_categoria = :categoria";
        $sql_count .= " AND id_categoria = :categoria";
        $params[':categoria'] = $filtro_categoria;
    }
    
    if ($filtro_usuario > 0) {
        $sql .= " AND n.id_autor = :usuario";
        $sql_count .= " AND id_autor = :usuario";
        $params[':usuario'] = $filtro_usuario;
    }
    
    if ($filtro_estado !== '') {
        $sql .= " AND n.estado = :estado";
        $sql_count .= " AND estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    
    // Total registros
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_noticias = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_noticias / $por_pagina);
    
    // Añadir orden y paginación
    $sql .= " ORDER BY n.fecha_publicacion DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $noticias = $stmt->fetchAll();
    
    // Estadísticas rápidas
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_privadas,
            COUNT(DISTINCT id_autor) as total_autores,
            COUNT(DISTINCT id_categoria) as total_categorias
        FROM noticias
        WHERE privada = 1
    ")->fetch();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar las noticias privadas.';
    registrarErrorInterno('ADMIN.NOTICIAS_PRIVADAS.CARGA', $e);
}

$titulo_pagina = 'Buscador de Noticias Privadas';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('privado-noticias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<div class="privadas-container">
    
    <div class="privadas-header">
        <h1>🔍 Buscador de Noticias Privadas</h1>
        <p class="privadas-desc">Gestión de noticias privadas del sistema</p>
    </div>
    
    <!-- Estadísticas rápidas -->
    <div class="privadas-stats">
        <div class="privadas-stat-card">
            <div class="privadas-stat-valor"><?php echo $stats['total_privadas']; ?></div>

            <div class="privadas-stat-etiqueta">Noticias privadas</div>
        </div>
        <div class="privadas-stat-card">
            <div class="privadas-stat-valor"><?php echo $stats['total_autores']; ?></div>

            <div class="privadas-stat-etiqueta">Autores</div>
        </div>
        <div class="privadas-stat-card">
            <div class="privadas-stat-valor"><?php echo $stats['total_categorias']; ?></div>

            <div class="privadas-stat-etiqueta">Categorías</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="privadas-filtros">
        <form method="GET" class="privadas-filtros-form">
            <div class="privadas-filtro-grupo">
                <label>📂 Categoría:</label>
                <select name="categoria">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $cat): ?>

                        <option value="<?php echo $cat['id_categoria']; ?>" 

                            <?php echo $filtro_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>

                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <div class="privadas-filtro-grupo">
                <label>👤 Autor:</label>
                <select name="usuario">
                    <option value="0">Todos</option>
                    <?php foreach ($usuarios_privados as $usr): ?>

                        <option value="<?php echo $usr['id_usuario']; ?>" 

                            <?php echo $filtro_usuario == $usr['id_usuario'] ? 'selected' : ''; ?>>

                            <?php echo htmlspecialchars($usr['nombre']); ?>

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <div class="privadas-filtro-grupo">
                <label>📌 Estado:</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="publicada" <?php echo $filtro_estado == 'publicada' ? 'selected' : ''; ?>>Publicada</option>

                    <option value="borrador" <?php echo $filtro_estado == 'borrador' ? 'selected' : ''; ?>>Borrador</option>

                    <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>

                </select>
            </div>
            
            <div class="privadas-filtro-acciones">
                <button type="submit" class="privadas-btn privadas-btn-filtrar">Filtrar</button>
                <a href="<?php echo htmlspecialchars(route('admin_noticias_privadas_buscar'), ENT_QUOTES, 'UTF-8'); ?>" class="privadas-btn privadas-btn-limpiar">Limpiar</a>
            </div>
        </form>
    </div>
    
    <?php if (isset($error)): ?>

        <div class="privadas-alerta privadas-alerta-error">⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>

    <?php endif; ?>

    
    <?php if (empty($noticias)): ?>

        <div class="privadas-alerta privadas-alerta-info">
            <p>📭 No hay noticias privadas con los filtros seleccionados.</p>
        </div>
    <?php else: ?>

        
        <div class="privadas-resultados">
            <p class="privadas-resultados-info">
                📊 Mostrando <strong><?php echo count($noticias); ?></strong> de <strong><?php echo $total_noticias; ?></strong> noticias

            </p>
        </div>
        
        <!-- GRID DE TARJETAS (3 → 2 → 1 columnas) -->
        <div class="privadas-grid">
            <?php foreach ($noticias as $n): ?>

                <?php
                $claseEstadoTarjeta = match ($n['estado'] ?? '') {
                    'borrador' => ' news-card--draft',
                    'pendiente' => ' news-card--pending',
                    'archivada' => ' news-card--archived',
                    default => '',
                };
                ?>
                <div class="privadas-card <?php echo $n['estado']; ?> news-card news-card--vertical news-card--private<?php echo $claseEstadoTarjeta; ?>">
                    <h3 class="privadas-card-titulo news-card__title">
                        <a href="<?php echo htmlspecialchars(route('privado_noticia', ['id' => $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo htmlspecialchars($n['titulo']); ?>
                        </a>
                    </h3>

                    <?php if ($n['subtitulo']): ?>
                        <p class="privadas-card-subtitulo news-card__subtitle">
                            <?php echo htmlspecialchars($n['subtitulo']); ?>
                        </p>
                    <?php endif; ?>

                    
                    <!-- Imagen destacada -->
                    <div class="privadas-card-imagen news-card__media">
                        <?php if ($n['imagen_principal']): ?>

                            <img src="<?php echo base_url('uploads/noticias/' . $n['imagen_principal']); ?>" 

                                 alt="<?php echo htmlspecialchars($n['titulo']); ?>">

                        <?php else: ?>

                            <div class="privadas-card-imagen-placeholder">
                                <span>📰</span>
                            </div>
                        <?php endif; ?>

                        <div class="privadas-card-estado-badge">
                            <?php

                            $estado_texto = $n['estado'] === 'publicada' ? 'Publicada' : ucfirst($n['estado']);
                            $estado_color = $n['estado'] === 'publicada' ? 'publicada' : 'borrador';
                            ?>
                            <span class="privadas-badge privadas-badge-<?php echo $estado_color; ?>">

                                <?php echo $estado_texto; ?>

                            </span>
                        </div>
                    </div>
                    
                    <!-- Contenido de la tarjeta -->
                    <div class="privadas-card-contenido news-card__body">
                        <div class="privadas-card-meta news-card__meta news-card__meta--standard">
                            <div class="privadas-card-autor">
                                <?php if ($n['autor_avatar']): ?>

                                    <img src="<?php echo base_url('uploads/perfiles/' . $n['autor_avatar']); ?>" 

                                         alt="<?php echo htmlspecialchars($n['autor_nombre']); ?>"

                                         class="privadas-avatar-mini">
                                <?php else: ?>

                                    <span class="privadas-autor-icono">👤</span>
                                <?php endif; ?>

                                <a href="?usuario=<?php echo $n['autor_id']; ?>">

                                    <?php echo htmlspecialchars($n['autor_nombre']); ?>

                                </a>
                            </div>
                            <div class="privadas-card-fecha">
                                <?php
                                $fechaPublicacion = !empty($n['fecha_publicacion'])
                                    ? strtotime((string) $n['fecha_publicacion'])
                                    : false;
                                ?>
                                📅 <?php echo $fechaPublicacion !== false ? date('d/m/Y', $fechaPublicacion) : 'Sin fecha'; ?>

                            </div>
                        </div>
                        
                        <div class="privadas-card-categoria">
                            📂 <a href="?categoria=<?php echo $n['categoria_id']; ?>">

                                <?php echo htmlspecialchars($n['nombre_categoria']); ?>

                            </a>
                        </div>
                        
                        <div class="privadas-card-stats">
                            <div class="privadas-stat">
                                <span class="privadas-stat-numero"><?php echo number_format((int) $n['visitas_priv']); ?></span>

                                <span class="privadas-stat-texto">visitas</span>
                            </div>
                            <div class="privadas-stat">
                                <span class="privadas-stat-numero"><?php echo $n['megusta_priv']; ?></span>

                                <span class="privadas-stat-texto">me gusta</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="privadas-card-acciones news-card__actions">
                        <a href="<?php echo htmlspecialchars(route('admin_editar_noticia', ['id' => (int) $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="privadas-btn privadas-btn-editar">

                            ✏️ Editar
                        </a>
                        <a href="<?php echo htmlspecialchars(route('privado_noticia', ['id' => $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="privadas-btn privadas-btn-ver" target="_blank" rel="noopener noreferrer">

                            👁️ Ver
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>

            <div class="privadas-paginacion">
                <?php if ($pagina > 1): ?>

                    <a href="?pagina=<?php echo $pagina - 1; ?>&categoria=<?php echo $filtro_categoria; ?>&usuario=<?php echo $filtro_usuario; ?>&estado=<?php echo $filtro_estado; ?>" 

                       class="privadas-pagina-btn">
                        « Anterior
                    </a>
                <?php endif; ?>

                
                <div class="privadas-pagina-numeros">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                        <?php if ($i == $pagina): ?>

                            <span class="privadas-pagina-activo"><?php echo $i; ?></span>

                        <?php else: ?>

                            <a href="?pagina=<?php echo $i; ?>&categoria=<?php echo $filtro_categoria; ?>&usuario=<?php echo $filtro_usuario; ?>&estado=<?php echo $filtro_estado; ?>" 

                               class="privadas-pagina-link">
                                <?php echo $i; ?>

                            </a>
                        <?php endif; ?>

                    <?php endfor; ?>

                </div>
                
                <?php if ($pagina < $total_paginas): ?>

                    <a href="?pagina=<?php echo $pagina + 1; ?>&categoria=<?php echo $filtro_categoria; ?>&usuario=<?php echo $filtro_usuario; ?>&estado=<?php echo $filtro_estado; ?>" 

                       class="privadas-pagina-btn">
                        Siguiente »
                    </a>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        
    <?php endif; ?>

    
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
