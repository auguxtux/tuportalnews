<?php
declare(strict_types=1);


/**
 * MODERACIÓN DE COMENTARIOS
 * Diseño con tarjetas responsive (3-2-1 columnas)
 * Incluye filtros por: estado, búsqueda, noticia, usuario, tipo de noticia (privada/pública)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();

// Procesar acciones
$accion = (string) ($_POST['accion'] ?? '');
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', 'Error de seguridad');
        redireccionar(route('admin_comentarios'));
    }

    try {
        switch ($id > 0 ? $accion : '') {
            case 'aprobar':
                $stmt = $pdo->prepare("UPDATE comentarios SET estado = 'aprobado' WHERE id_comentario = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Comentario aprobado');
                break;
                
            case 'rechazar':
                $stmt = $pdo->prepare("UPDATE comentarios SET estado = 'rechazado' WHERE id_comentario = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Comentario rechazado');
                break;
                
            case 'eliminar':
                $stmt = $pdo->prepare("DELETE FROM comentarios WHERE id_comentario = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Comentario eliminado');
                break;
        }
    } catch (PDOException $e) {
        mensajeFlash('error', 'Error al procesar la acción');
        registrarErrorInterno('ADMIN.COMENTARIOS.ACCION', $e);
    }
    
    redireccionar(route('admin_comentarios'));
}

// Filtros
$filtro_estado = $_GET['estado'] ?? 'pendiente';
$busqueda = $_GET['q'] ?? '';
$filtro_noticia = isset($_GET['noticia']) ? (int)$_GET['noticia'] : 0;
$filtro_usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$filtro_privada = $_GET['privada'] ?? ''; // 'si', 'no', ''

// Paginación
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 12;
$offset = ($pagina - 1) * $por_pagina;

try {
    // Obtener listado de noticias para el filtro (solo las que tienen comentarios)
    $stmt_noticias = $pdo->query("
        SELECT DISTINCT n.id_noticia, n.titulo, n.privada
        FROM noticias n
        INNER JOIN comentarios c ON n.id_noticia = c.id_noticia
        ORDER BY n.fecha_publicacion DESC
    ");
    $noticias = $stmt_noticias->fetchAll();
    
    // Obtener listado de usuarios para el filtro (solo los que han comentado)
    $stmt_usuarios = $pdo->query("
        SELECT DISTINCT u.id_usuario, u.nombre, u.email, u.rol
        FROM usuarios u
        INNER JOIN comentarios c ON u.id_usuario = c.id_usuario
        ORDER BY u.nombre
    ");
    $usuarios = $stmt_usuarios->fetchAll();
    
    // Construir query
    $sql_count = "SELECT COUNT(*) FROM comentarios c";
    $sql = "SELECT c.*, 
                   u.nombre as autor_nombre, 
                   u.email as autor_email, 
                   u.avatar,
                   u.rol as autor_rol,
                   n.titulo as noticia_titulo, 
                   n.id_noticia, 
                   n.slug,
                   n.privada as noticia_privada
            FROM comentarios c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            JOIN noticias n ON c.id_noticia = n.id_noticia";
    
    $where = [];
    $params = [];
    
    // Filtro por estado
    if ($filtro_estado) {
        $where[] = "c.estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    
    // Filtro por búsqueda en contenido
    if ($busqueda) {
        $where[] = "(c.contenido LIKE :q OR u.nombre LIKE :q OR n.titulo LIKE :q)";
        $params[':q'] = "%$busqueda%";
    }
    
    // Filtro por noticia
    if ($filtro_noticia > 0) {
        $where[] = "c.id_noticia = :id_noticia";
        $params[':id_noticia'] = $filtro_noticia;
    }
    
    // Filtro por usuario
    if ($filtro_usuario > 0) {
        $where[] = "c.id_usuario = :id_usuario";
        $params[':id_usuario'] = $filtro_usuario;
    }
    
    // Filtro por tipo de noticia (privada/pública)
    if ($filtro_privada === 'si') {
        $where[] = "n.privada = 1";
    } elseif ($filtro_privada === 'no') {
        $where[] = "n.privada = 0";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
        // Para el conteo, también necesitamos el JOIN con noticias si hay filtro de privada
        $sql_count .= " JOIN noticias n ON c.id_noticia = n.id_noticia";
        $sql_count .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY c.fecha_comentario DESC";
    
    // Total registros
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_comentarios = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_comentarios / $por_pagina);
    
    // Resultados paginados
    $sql .= " LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $comentarios = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar los comentarios.';
    registrarErrorInterno('ADMIN.COMENTARIOS.CARGA', $e);
}

$titulo_pagina = 'Moderación de Comentarios';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-comentarios.css'); ?>">


<div class="admin-comentarios-container">
    
    <h1 class="admin-comentarios-titulo">📝 Moderación de Comentarios</h1>
    
    <!-- Filtros -->
    <div class="admin-comentarios-filtros">
        <form method="GET" class="admin-comentarios-filtros-form">
            <!-- Búsqueda por texto -->
            <input type="text" name="q" placeholder="🔍 Buscar en comentarios..." 
                   value="<?php echo htmlspecialchars($busqueda); ?>" 

                   class="admin-comentarios-busqueda">
            
            <!-- Filtro por estado -->
            <select name="estado" class="admin-comentarios-select-estado">
                <option value="">📋 Todos</option>
                <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>⏳ Pendientes</option>

                <option value="aprobado" <?php echo $filtro_estado === 'aprobado' ? 'selected' : ''; ?>>✅ Aprobados</option>

                <option value="rechazado" <?php echo $filtro_estado === 'rechazado' ? 'selected' : ''; ?>>❌ Rechazados</option>

            </select>
            
            <!-- Filtro por tipo de noticia (privada/pública) -->
            <select name="privada" class="admin-comentarios-select-privada">
                <option value="">📰 Todos los tipos</option>
                <option value="si" <?php echo $filtro_privada === 'si' ? 'selected' : ''; ?>>🔒 Solo privadas</option>

                <option value="no" <?php echo $filtro_privada === 'no' ? 'selected' : ''; ?>>🌐 Solo públicas</option>

            </select>
            
            <!-- Filtro por noticia -->
            <select name="noticia" class="admin-comentarios-select-noticia">
                <option value="0">📰 Todas las noticias</option>
                <?php foreach ($noticias as $noticia): ?>

                    <option value="<?php echo $noticia['id_noticia']; ?>" 

                        <?php echo $filtro_noticia == $noticia['id_noticia'] ? 'selected' : ''; ?>>

                        <?php if ($noticia['privada']): ?>🔒 <?php endif; ?>

                        <?php echo htmlspecialchars(truncarTexto($noticia['titulo'], 50)); ?>

                    </option>
                <?php endforeach; ?>

            </select>
            
            <!-- Filtro por usuario -->
            <select name="usuario" class="admin-comentarios-select-usuario">
                <option value="0">👥 Todos los usuarios</option>
                <?php foreach ($usuarios as $usuario): 

                    $rol_icono = match($usuario['rol']) {
                        'admin' => '👑',
                        'periodista' => '📰',
                        default => '👤'
                    };
                ?>
                    <option value="<?php echo $usuario['id_usuario']; ?>" 

                        <?php echo $filtro_usuario == $usuario['id_usuario'] ? 'selected' : ''; ?>>

                        <?php echo $rol_icono . ' ' . htmlspecialchars($usuario['nombre']) . ' (' . htmlspecialchars($usuario['email']) . ')'; ?>

                    </option>
                <?php endforeach; ?>

            </select>
            
            <button type="submit" class="admin-comentarios-btn admin-comentarios-btn-filtrar">
                🔍 Filtrar
            </button>
            <a href="<?php echo htmlspecialchars(route('admin_comentarios'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-btn admin-comentarios-btn-limpiar">
                🧹 Limpiar
            </a>
        </form>
    </div>
    
    <?php if (isset($error)): ?>

        <div class="admin-comentarios-alerta admin-comentarios-alerta-error">
            ⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

        </div>
    <?php endif; ?>

    
    <?php if (empty($comentarios)): ?>

        <div class="admin-comentarios-alerta admin-comentarios-alerta-info">
            <p>📭 No hay comentarios con los criterios seleccionados.</p>
        </div>
    <?php else: ?>

        
        <div class="admin-comentarios-resultados">
            <p class="admin-comentarios-resultados-info">
                📊 Mostrando <strong><?php echo count($comentarios); ?></strong> de <strong><?php echo $total_comentarios; ?></strong> comentarios

            </p>
        </div>
        
        <!-- GRID DE TARJETAS -->
        <div class="admin-comentarios-grid">
            <?php foreach ($comentarios as $com): ?>

                <div class="admin-comentarios-card <?php echo $com['estado']; ?>">

                    
                    <!-- HEADER DE LA TARJETA -->
                    <div class="admin-comentarios-card-header">
                        <div class="admin-comentarios-avatar">
                            <img src="<?php echo base_url('uploads/perfiles/' . ($com['avatar'] ?? 'default-avatar.png')); ?>" 

                                 alt="<?php echo htmlspecialchars($com['autor_nombre']); ?>">

                        </div>
                        <div class="admin-comentarios-autor-info">
                            <strong class="admin-comentarios-autor-nombre">
                                <?php echo htmlspecialchars($com['autor_nombre']); ?>

                            </strong>
                            <span class="admin-comentarios-autor-email">
                                <?php echo htmlspecialchars($com['autor_email']); ?>

                            </span>
                            <span class="admin-comentarios-autor-rol">
                                <?php

                                $rol_icono = match($com['autor_rol']) {
                                    'admin' => '👑 Admin',
                                    'periodista' => '✍️ Articulista',
                                    default => '👤 Usuario'
                                };
                                echo $rol_icono;
                                ?>
                            </span>
                        </div>
                        <div class="admin-comentarios-estado">
                            <?php

                            $estado_class = match($com['estado']) {
                                'aprobado' => 'admin-comentarios-badge-aprobado',
                                'pendiente' => 'admin-comentarios-badge-pendiente',
                                'rechazado' => 'admin-comentarios-badge-rechazado',
                                default => ''
                            };
                            $estado_texto = match($com['estado']) {
                                'aprobado' => '✅ Aprobado',
                                'pendiente' => '⏳ Pendiente',
                                'rechazado' => '❌ Rechazado',
                                default => $com['estado']
                            };
                            ?>
                            <span class="admin-comentarios-badge <?php echo $estado_class; ?>">

                                <?php echo $estado_texto; ?>

                            </span>
                        </div>
                    </div>
                    
                    <!-- FECHA -->
                    <div class="admin-comentarios-fecha">
                        <span class="admin-comentarios-fecha-completa">
                            📅 <?php echo formatearFecha($com['fecha_comentario']); ?>

                        </span>
                        <span class="admin-comentarios-fecha-relativa">
                            <?php echo tiempoTranscurrido($com['fecha_comentario']); ?>

                        </span>
                    </div>
                    
                    <!-- CONTENIDO DEL COMENTARIO -->
                    <div class="admin-comentarios-card-contenido">
                        <?php echo sanitizarHtmlComentario($com['contenido']); ?>

                    </div>
                    
                    <!-- NOTICIA RELACIONADA (con badge de privada si aplica) -->
                    <div class="admin-comentarios-noticia">
                        <span class="admin-comentarios-noticia-icono">📰</span>
                        <?php if ($com['noticia_privada']): ?>

                            <span class="badge-privada" title="Noticia privada">🔒</span>
                        <?php endif; ?>

                        <a href="<?php echo htmlspecialchars(route('noticia', ['id' => (int) $com['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>"

                           class="admin-comentarios-noticia-link"
                           >
                            <?php echo htmlspecialchars($com['noticia_titulo']); ?>

                        </a>
                    </div>
                    
                    <!-- ACCIONES -->
                    <div class="admin-comentarios-card-acciones">
                        <?php if ($com['estado'] !== 'aprobado'): ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Aprobar este comentario?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <input type="hidden" name="id" value="<?php echo (int) $com['id_comentario']; ?>">
                                <button type="submit" class="admin-comentarios-btn admin-comentarios-btn-aprobar">Aprobar</button>
                            </form>
                        <?php endif; ?>

                        
                        <?php if ($com['estado'] !== 'rechazado'): ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Rechazar este comentario?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="id" value="<?php echo (int) $com['id_comentario']; ?>">
                                <button type="submit" class="admin-comentarios-btn admin-comentarios-btn-rechazar">Rechazar</button>
                            </form>
                        <?php endif; ?>

                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿ELIMINAR este comentario permanentemente?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo (int) $com['id_comentario']; ?>">
                            <button type="submit" class="admin-comentarios-btn admin-comentarios-btn-eliminar">Eliminar</button>
                        </form>
                        
                        <a href="<?php echo htmlspecialchars(route('noticia', ['id' => (int) $com['id_noticia']]) . '#comentario-' . (int) $com['id_comentario'], ENT_QUOTES, 'UTF-8'); ?>"

                           class="admin-comentarios-btn admin-comentarios-btn-ver-contexto"
                           >
                            Ver
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
        
        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>

            <div class="admin-comentarios-paginacion">
                <?php if ($pagina > 1): ?>

                    <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo $filtro_estado; ?>&q=<?php echo urlencode($busqueda); ?>&noticia=<?php echo $filtro_noticia; ?>&usuario=<?php echo $filtro_usuario; ?>&privada=<?php echo $filtro_privada; ?>" 

                       class="admin-comentarios-pagina-btn admin-comentarios-pagina-anterior">
                        « Anterior
                    </a>
                <?php endif; ?>

                
                <div class="admin-comentarios-pagina-numeros">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                        <?php if ($i == $pagina): ?>

                            <span class="admin-comentarios-pagina-numero admin-comentarios-pagina-activo"><?php echo $i; ?></span>

                        <?php else: ?>

                            <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $filtro_estado; ?>&q=<?php echo urlencode($busqueda); ?>&noticia=<?php echo $filtro_noticia; ?>&usuario=<?php echo $filtro_usuario; ?>&privada=<?php echo $filtro_privada; ?>" 

                               class="admin-comentarios-pagina-numero">
                                <?php echo $i; ?>

                            </a>
                        <?php endif; ?>

                    <?php endfor; ?>

                </div>
                
                <?php if ($pagina < $total_paginas): ?>

                    <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo $filtro_estado; ?>&q=<?php echo urlencode($busqueda); ?>&noticia=<?php echo $filtro_noticia; ?>&usuario=<?php echo $filtro_usuario; ?>&privada=<?php echo $filtro_privada; ?>" 

                       class="admin-comentarios-pagina-btn admin-comentarios-pagina-siguiente">
                        Siguiente »
                    </a>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        
    <?php endif; ?>

</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
