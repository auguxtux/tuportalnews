<?php
declare(strict_types=1);


/**
 * PANEL PRIVADO - Buscador de noticias privadas
 * Diseño responsive con tarjetas (3 → 2 → 1 columnas)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/minify.php';

// Verificar acceso
Permisos::requerirLogin();
if (!usuarioEsPrivado() && !Permisos::esAdmin()) {
    mensajeFlash('error', 'No tienes permiso para acceder al área privada');
    redireccionar(route('home'));
}

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$es_admin = Permisos::esAdmin();

// Obtener categorías
$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM categorias WHERE activa = 1 ORDER BY nombre_categoria")->fetchAll();

// Obtener usuarios privados (solo para admin)
$usuarios_privados = [];
if ($es_admin) {
    $usuarios_privados = $pdo->query("
        SELECT u.id_usuario, u.nombre
        FROM usuarios u
        INNER JOIN usuarios_privados up ON u.id_usuario = up.id_usuario
        WHERE up.activo = 1 AND u.rol = 'periodista'
        ORDER BY u.nombre
    ")->fetchAll();
}

// Filtros
$filtro_categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$filtro_usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$busqueda_titulo = isset($_GET['titulo']) ? $_GET['titulo'] : '';

// Paginación
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 12; // Múltiplo de 3 para grid
$offset = ($pagina - 1) * $por_pagina;

try {
    // Consulta de conteo
    $sql_count = "SELECT COUNT(*) FROM noticias n WHERE n.privada = 1 AND n.estado = 'publicada'";
    
    // Consulta principal
    $sql = "
        SELECT n.*, u.nombre as autor_nombre, c.nombre_categoria,
               COALESCE(ep.visitas_privadas, 0) as visitas_priv,
               COALESCE(ep.megusta_privados, 0) as megusta_priv
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        JOIN categorias c ON n.id_categoria = c.id_categoria
        LEFT JOIN estadisticas_privadas ep ON n.id_noticia = ep.id_noticia
        WHERE n.privada = 1
          AND n.estado = 'publicada'
    ";
    
    $params = [];
    
    // Filtro por título
    if (!empty($busqueda_titulo)) {
        $sql .= " AND n.titulo LIKE :titulo";
        $sql_count .= " AND titulo LIKE :titulo";
        $params[':titulo'] = "%$busqueda_titulo%";
    }
    
    // Filtro por categoría
    if ($filtro_categoria > 0) {
        $sql .= " AND n.id_categoria = :categoria";
        $sql_count .= " AND id_categoria = :categoria";
        $params[':categoria'] = $filtro_categoria;
    }
    
    // Filtro por usuario (solo admin)
    if ($es_admin && $filtro_usuario > 0) {
        $sql .= " AND n.id_autor = :usuario";
        $sql_count .= " AND id_autor = :usuario";
        $params[':usuario'] = $filtro_usuario;
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
    
} catch (Exception $e) {
    $error = 'No se pudo completar la búsqueda.';
    registrarErrorInterno('PRIVADO.BUSCAR_NOTICIAS', $e);
}

$titulo_pagina = 'Buscar Noticias Privadas';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('privado-buscar-noticias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">

<div class="privado-buscar-container">
    
    <h1 class="privado-buscar-titulo">🔍 Buscar Noticias Privadas</h1>
    
    <!-- FILTROS -->
    <div class="privado-buscar-filtros">
        <form method="GET" class="privado-buscar-filtros-form">
            
            <div class="privado-buscar-filtro-grupo">
                <label for="titulo">📝 Título:</label>
                <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($busqueda_titulo); ?>" 

                       placeholder="Palabras en el título" class="privado-buscar-input">
            </div>
            
            <div class="privado-buscar-filtro-grupo">
                <label for="categoria">📂 Categoría:</label>
                <select name="categoria" id="categoria" class="privado-buscar-select">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $cat): ?>

                        <option value="<?php echo $cat['id_categoria']; ?>" 

                            <?php echo $filtro_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>

                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <?php if ($es_admin): ?>

            <div class="privado-buscar-filtro-grupo">
                <label for="usuario">👤 Autor:</label>
                <select name="usuario" id="usuario" class="privado-buscar-select">
                    <option value="0">Todos</option>
                    <?php foreach ($usuarios_privados as $usr): ?>

                        <option value="<?php echo $usr['id_usuario']; ?>" 

                            <?php echo $filtro_usuario == $usr['id_usuario'] ? 'selected' : ''; ?>>

                            <?php echo htmlspecialchars($usr['nombre']); ?>

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            <?php endif; ?>

            
            <div class="privado-buscar-filtro-acciones">
                <button type="submit" class="privado-buscar-btn privado-buscar-btn-primario">🔍 Buscar</button>
                <a href="<?php echo htmlspecialchars(route('privado_buscar'), ENT_QUOTES, 'UTF-8'); ?>" class="privado-buscar-btn privado-buscar-btn-secundario">🧹 Limpiar</a>
                <a href="<?php echo htmlspecialchars(route('privado_dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="privado-buscar-btn privado-buscar-btn-secundario">← Volver</a>
            </div>
        </form>
    </div>
    
    <?php if (isset($error)): ?>

        <div class="privado-buscar-alerta privado-buscar-alerta-error">⚠️ <?php echo $error; ?></div>

    <?php endif; ?>

    
        <?php if (empty($noticias)): ?>

            <div class="privado-buscar-alerta privado-buscar-alerta-info">
                <p>📭 No se encontraron noticias con esos criterios.</p>
            </div>
        <?php else: ?>

            
            <p class="privado-buscar-resultados-info">
                📊 Mostrando <strong><?php echo count($noticias); ?></strong> de <strong><?php echo $total_noticias; ?></strong> noticias

            </p>
            
            <!-- GRID DE TARJETAS (3 → 2 → 1) -->
            <div class="privado-buscar-grid">
                <?php foreach ($noticias as $n): ?>

                    <div class="privado-buscar-card news-card news-card--vertical news-card--private">
                        <!-- Título -->
                        <h3 class="privado-buscar-card-titulo news-card__title">
                            <a href="<?php echo htmlspecialchars(
                                route('privado_noticia', ['id' => $n['id_noticia']]),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>">

                                <?php echo htmlspecialchars($n['titulo']); ?>

                            </a>
                        </h3>
                        <!-- Imagen (local o externa) -->
                        <div class="privado-buscar-card-imagen news-card__media">
                            <?php echo mostrarImagenNoticia(
                                $n,
                                'privado-buscar-img',
                                '📷 Sin imagen',
                                route('privado_noticia', ['id' => $n['id_noticia']])
                            ); ?>

                        </div>
                        <!-- Badge de tipo -->
                        <div class="privado-buscar-card-badge">
                            <span class="privado-buscar-badge privado-buscar-badge-privada">🔒 Privada</span>
                        </div>
                        <!-- Autor y categoría -->
                        <div class="privado-buscar-card-meta news-card__meta news-card__meta--standard">
                            <?php if ($es_admin): ?>

                                <span class="privado-buscar-meta-autor">✍️ <a href="<?php echo route('privado_buscar', ['usuario' => (int) $n['id_autor']]); ?>"><?php echo htmlspecialchars($n['autor_nombre']); ?></a></span>

                            <?php endif; ?>

                            <span class="privado-buscar-meta-categoria">📂 <a href="<?php echo route('privado_buscar', ['categoria' => (int) $n['id_categoria']]); ?>"><?php echo htmlspecialchars($n['nombre_categoria']); ?></a></span>

                        </div>
                        
                        <!-- Fecha -->
                        <div class="privado-buscar-card-fecha">
                            <?php
                            $fechaPublicacion = !empty($n['fecha_publicacion'])
                                ? strtotime((string) $n['fecha_publicacion'])
                                : false;
                            ?>
                            📅 <?php echo $fechaPublicacion !== false ? date('d/m/Y', $fechaPublicacion) : 'Sin fecha'; ?>

                        </div>
                        
                        <!-- Estadísticas -->
                        <div class="privado-buscar-card-stats">
                            <div class="privado-buscar-stat">
                                <span class="privado-buscar-stat-numero">👁️ <?php echo number_format((int) $n['visitas']); ?></span>

                            </div>
                            <div class="privado-buscar-stat">
                                <span class="privado-buscar-stat-numero">👍 <?php echo $n['megusta'] ?? 0; ?></span>

                            </div>
                            <div class="privado-buscar-stat">
                                <span class="privado-buscar-stat-numero">🔒 <?php echo number_format((int) $n['visitas_priv']); ?></span>

                            </div>

                        </div>
                        
                        <!-- Estado -->
                        <div class="privado-buscar-card-estado">
                            <span class="privado-buscar-badge-estado privado-buscar-badge-estado-<?php echo $n['estado']; ?>">

                                <?php echo ucfirst($n['estado']); ?>

                            </span>
                        </div>
                        
                        <!-- Acciones -->
                        <div class="privado-buscar-card-acciones news-card__actions">
                            <?php if ($es_admin || (int) $n['id_autor'] === (int) $id_usuario): ?>
                            <a href="<?php echo htmlspecialchars(route('privado_editar_noticia', ['id' => $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>"

                               class="privado-buscar-btn privado-buscar-btn-editar">✏️ Editar</a>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars(
                                route('privado_noticia', ['id' => $n['id_noticia']]),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"

                               class="privado-buscar-btn privado-buscar-btn-ver">👁️ Ver</a>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            
            <!-- PAGINACIÓN -->
            <?php if ($total_paginas > 1): ?>

                <div class="privado-buscar-paginacion">
                    <?php 

                    $query_params = [
                        'titulo' => $busqueda_titulo,
                        'categoria' => $filtro_categoria,
                        'usuario' => $filtro_usuario
                    ];
                    $query_string = http_build_query(array_filter($query_params, function($v) { return $v !== '' && $v !== null && $v !== 0; }));
                    ?>
                    
                    <?php if ($pagina > 1): ?>

                        <a href="?pagina=<?php echo $pagina - 1; ?>&<?php echo $query_string; ?>" class="privado-buscar-pagina-btn">« Anterior</a>

                    <?php endif; ?>

                    
                    <div class="privado-buscar-pagina-numeros">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                            <?php if ($i == $pagina): ?>

                                <span class="privado-buscar-pagina-activo"><?php echo $i; ?></span>

                            <?php else: ?>

                                <a href="?pagina=<?php echo $i; ?>&<?php echo $query_string; ?>" class="privado-buscar-pagina-link"><?php echo $i; ?></a>

                            <?php endif; ?>

                        <?php endfor; ?>

                    </div>
                    
                    <?php if ($pagina < $total_paginas): ?>

                        <a href="?pagina=<?php echo $pagina + 1; ?>&<?php echo $query_string; ?>" class="privado-buscar-pagina-btn">Siguiente »</a>

                    <?php endif; ?>

                </div>
            <?php endif; ?>

            
        <?php endif; ?>

</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
