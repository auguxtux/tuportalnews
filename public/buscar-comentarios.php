<?php
declare(strict_types=1);


/* BUSCADOR DE COMENTARIOS - VERSIÓN CORREGIDA */
/* El formulario se oculta al buscar, con botón "Nueva búsqueda" */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';

$busquedaPrivada = defined('BUSCAR_COMENTARIOS_PRIVADOS')
    && BUSCAR_COMENTARIOS_PRIVADOS === true;

if ($busquedaPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$valorPrivacidad = $busquedaPrivada ? 1 : 0;
$rutaBuscadorComentarios = route(
    $busquedaPrivada ? 'privado_buscar_comentarios' : 'buscar-comentarios'
);


/**
 * Escapa un valor antes de mostrarlo en HTML.
 */
function eBuscarComentarios(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Valida una fecha en formato YYYY-MM-DD.
 */
function validarFechaBuscarComentarios(string $fecha): string
{
    if ($fecha === '') {
        return '';
    }

    $objetoFecha = DateTime::createFromFormat('Y-m-d', $fecha);
    $errores = DateTime::getLastErrors();

    if (
        $objetoFecha === false
        || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))
        || $objetoFecha->format('Y-m-d') !== $fecha
    ) {
        return '';
    }

    return $fecha;
}

/**
 * Genera una URL de paginación conservando los filtros actuales.
 */
function urlPaginacionComentarios(array $parametros): string
{
    return '?' . http_build_query(
        array_filter(
            $parametros,
            static fn (mixed $valor): bool => $valor !== '' && $valor !== null
        )
    );
}

// Obtener filtros desde POST o GET.
$metodoPeticion = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$entrada = $metodoPeticion === 'POST' ? $_POST : $_GET;

$id_categoria = max(0, (int) ($entrada['id_categoria'] ?? 0));
$id_noticia = max(0, (int) ($entrada['id_noticia'] ?? 0));
$id_usuario = max(0, (int) ($entrada['id_usuario'] ?? 0));
$palabras = limpiarDatos((string) ($entrada['palabras'] ?? ''));

$estadosValidos = ['todos', 'aprobado', 'pendiente', 'rechazado'];
$estadoRecibido = (string) ($entrada['estado'] ?? 'todos');
$estado = in_array($estadoRecibido, $estadosValidos, true)
    ? $estadoRecibido
    : 'todos';

$fecha_desde = validarFechaBuscarComentarios((string) ($entrada['fecha_desde'] ?? ''));
$fecha_hasta = validarFechaBuscarComentarios((string) ($entrada['fecha_hasta'] ?? ''));

if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_desde > $fecha_hasta) {
    [$fecha_desde, $fecha_hasta] = [$fecha_hasta, $fecha_desde];
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Variable para saber si se ha realizado una búsqueda
$busqueda_realizada = (
    $metodoPeticion === 'POST'
    || $palabras !== ''
    || $id_categoria > 0
    || $id_noticia > 0
    || $id_usuario > 0
    || $fecha_desde !== ''
    || $fecha_hasta !== ''
    || (esAdmin() && $estado !== 'todos')
);

$categorias = [];
$noticias = [];
$usuarios = [];
$comentarios = [];
$total_resultados = 0;
$total_paginas = 0;
$error = null;

try {
    $pdo = db();
    
    // Obtener categorías para el filtro
    $stmt_cats = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM comentarios co 
                JOIN noticias n ON co.id_noticia = n.id_noticia 
                WHERE n.id_categoria = c.id_categoria
                  AND n.privada = {$valorPrivacidad}) as total_comentarios
        FROM categorias c
        WHERE c.activa = 1
        ORDER BY c.nombre_categoria
    ");
    $categorias = $stmt_cats->fetchAll();
    
    // Obtener noticias para el selector
    if ($id_categoria > 0) {
        $stmt_noticias = $pdo->prepare("
            SELECT DISTINCT n.id_noticia, n.titulo, 
                   (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia) as total_comentarios
            FROM noticias n
            INNER JOIN comentarios c ON n.id_noticia = c.id_noticia
            WHERE n.estado = 'publicada'
              AND n.privada = {$valorPrivacidad}
              AND n.id_categoria = :categoria
            ORDER BY n.fecha_publicacion DESC 
            LIMIT 100
        ");
        $stmt_noticias->execute([':categoria' => $id_categoria]);
    } else {
        $stmt_noticias = $pdo->query("
            SELECT DISTINCT n.id_noticia, n.titulo,
                   (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia) as total_comentarios
            FROM noticias n
            INNER JOIN comentarios c ON n.id_noticia = c.id_noticia
            WHERE n.estado = 'publicada'
              AND n.privada = {$valorPrivacidad}
            ORDER BY n.fecha_publicacion DESC 
            LIMIT 100
        ");
    }
    $noticias = $stmt_noticias->fetchAll();
    
    // Obtener usuarios que han comentado
    $stmt_usuarios = $pdo->query("
        SELECT DISTINCT u.id_usuario, u.nombre, u.email, u.rol, COUNT(c.id_comentario) as total_comentarios
        FROM usuarios u
        INNER JOIN comentarios c ON u.id_usuario = c.id_usuario
        INNER JOIN noticias n ON n.id_noticia = c.id_noticia
        WHERE n.privada = {$valorPrivacidad}
        GROUP BY u.id_usuario, u.nombre, u.email, u.rol
        ORDER BY u.nombre ASC
    ");
    $usuarios = $stmt_usuarios->fetchAll();
    
        // ============================================
    // CONSTRUIR CONSULTA - VERSIÓN SIMPLIFICADA Y FORZADA
    // ============================================
    
    $params = [];
    $where_sql = "";

    // El buscador público y el privado nunca comparten resultados.
    $where_sql .= " AND n.privada = :privada";
    $params[':privada'] = $valorPrivacidad;
    
    // Filtro por categoría
    if ($id_categoria > 0) {
        $where_sql .= " AND n.id_categoria = :id_categoria";
        $params[':id_categoria'] = $id_categoria;
    }
    
    // Filtro por noticia
    if ($id_noticia > 0) {
        $where_sql .= " AND c.id_noticia = :id_noticia";
        $params[':id_noticia'] = $id_noticia;
    }
    
    // Filtro por usuario
    if ($id_usuario > 0) {
        $where_sql .= " AND c.id_usuario = :id_usuario";
        $params[':id_usuario'] = $id_usuario;
    }
    
    // Filtro por palabras clave
    if (!empty($palabras)) {
        $where_sql .= " AND c.contenido LIKE :palabras";
        $params[':palabras'] = "%$palabras%";
    }
    
    // ============================================
    // FILTRO DE ESTADO - FORZADO PARA USUARIO NORMAL
    // ============================================
    if (!esAdmin()) {
        // USUARIO NORMAL: SOLO comentarios aprobados de noticias públicas
        $where_sql .= " AND c.estado = 'aprobado'";
    } elseif ($estado !== 'todos' && esAdmin()) {
        // ADMINISTRADOR: filtra por estado seleccionado
        $where_sql .= " AND c.estado = :estado";
        $params[':estado'] = $estado;
    }
    // Si es admin y estado == 'todos', no se añade filtro
    
    // Filtro por fecha desde
    if (!empty($fecha_desde)) {
        $where_sql .= " AND c.fecha_comentario >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde . ' 00:00:00';
    }
    
    // Filtro por fecha hasta
    if (!empty($fecha_hasta)) {
        $where_sql .= " AND c.fecha_comentario <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    }
    
    // Base de las consultas
    $sql_base = "FROM comentarios c
                 JOIN usuarios u ON c.id_usuario = u.id_usuario
                 JOIN noticias n ON c.id_noticia = n.id_noticia
                 WHERE 1=1";
    
    // ============================================
    // CONSULTA DE CONTEO
    // ============================================
    $sql_count = "SELECT COUNT(*) " . $sql_base . $where_sql;

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_resultados = (int) $stmt_count->fetchColumn();
    $total_paginas = (int) ceil($total_resultados / $por_pagina);
    
    // ============================================
    // CONSULTA DE RESULTADOS
    // ============================================
    $sql = "SELECT c.*, 
                   u.nombre as usuario_nombre, 
                   u.avatar as usuario_avatar,
                   u.rol as usuario_rol,
                   n.titulo as noticia_titulo,
                   n.id_noticia,
                   n.privada as noticia_privada
            " . $sql_base . $where_sql . " 
            ORDER BY c.fecha_comentario DESC";
    
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
    
} catch (Throwable $e) {
    $error = 'No se ha podido realizar la búsqueda de comentarios.';
    registrarErrorInterno('PUBLIC.BUSCAR_COMENTARIOS', $e);
}

$titulo_pagina = $busquedaPrivada
    ? 'Buscador de Comentarios Privados'
    : 'Buscador de Comentarios';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-buscar-comentarios.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('public-form-focus.css'); ?>">


<div class="public-buscar-comentarios-container">

<?php if ($error !== null): ?>

    <div class="public-buscar-comentarios-alerta public-buscar-comentarios-alerta-error">
        ⚠️ <?php echo eBuscarComentarios($error); ?>

    </div>
<?php endif; ?>


<h1>🔍 <?php echo eBuscarComentarios($titulo_pagina); ?></h1>
    <p class="public-buscar-comentarios-desc">Encuentra comentarios <?php echo $busquedaPrivada ? 'de noticias privadas ' : ''; ?>por categoría, noticia, usuario o palabras clave</p>
    
    <!-- FORMULARIO DE BÚSQUEDA - Se oculta si ya se ha buscado -->
    <div class="public-buscar-comentarios-formulario" id="formularioBusqueda" <?php echo $busqueda_realizada ? 'style="display: none;"' : ''; ?>>

        <form method="POST" class="form-busqueda" id="formBusqueda">
            <div class="public-buscar-comentarios-grid-2">
                
                <!-- Columna izquierda -->
                <div class="public-buscar-comentarios-columna">
                    <div class="public-buscar-comentarios-campo">
                        <label for="id_categoria">📂 Categoría:</label>
                        <select id="id_categoria" name="id_categoria" class="public-buscar-comentarios-select">
                            <option value="0">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>

                                <option value="<?php echo $cat['id_categoria']; ?>" 

                                    <?php echo $id_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>

                                    <?php echo eBuscarComentarios($cat['nombre_categoria']); ?> 

                                    (<?php echo $cat['total_comentarios']; ?> comentarios)

                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>
                    
                    <div class="public-buscar-comentarios-campo">
                        <label for="id_noticia">📰 Noticia:</label>
                        <select id="id_noticia" name="id_noticia" class="public-buscar-comentarios-select">
                            <option value="0">Todas las noticias</option>
                            <?php foreach ($noticias as $n): ?>

                                <option value="<?php echo $n['id_noticia']; ?>" 

                                    <?php echo $id_noticia == $n['id_noticia'] ? 'selected' : ''; ?>>

                                    <?php echo eBuscarComentarios(truncarTexto($n['titulo'], 60)); ?> 

                                    (<?php echo $n['total_comentarios']; ?> comentarios)

                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>
                    
                    <div class="public-buscar-comentarios-campo">
                        <label for="id_usuario">👤 Usuario:</label>
                        <select id="id_usuario" name="id_usuario" class="public-buscar-comentarios-select">
                            <option value="0">Todos los usuarios</option>
                            <?php foreach ($usuarios as $u): ?>

                                <option value="<?php echo $u['id_usuario']; ?>" 

                                    <?php echo $id_usuario == $u['id_usuario'] ? 'selected' : ''; ?>

                                    >
                                    <?php echo eBuscarComentarios($u['nombre']); ?> 

                                    (<?php echo $u['total_comentarios']; ?> comentarios)

                                    <?php echo $u['rol'] !== 'usuario' ? '[' . ucfirst($u['rol']) . ']' : ''; ?>

                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>
                    
                    <div class="public-buscar-comentarios-campo">
                        <label for="palabras">🔤 Palabras en comentario:</label>
                        <input type="text" id="palabras" name="palabras" class="public-buscar-comentarios-input"
                               value="<?php echo eBuscarComentarios($palabras); ?>" 

                               placeholder="Ej: muy bueno, interesante...">
                    </div>
                </div>
                
                <!-- Columna derecha -->
                <div class="public-buscar-comentarios-columna">
                    <?php if (esAdmin()): ?>

                    <div class="public-buscar-comentarios-campo">
                        <label for="estado">📌 Estado:</label>
                        <select id="estado" name="estado" class="public-buscar-comentarios-select">
                            <option value="todos" <?php echo $estado == 'todos' ? 'selected' : ''; ?>>Todos</option>

                            <option value="aprobado" <?php echo $estado == 'aprobado' ? 'selected' : ''; ?>>✅ Aprobados</option>

                            <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>⏳ Pendientes</option>

                            <option value="rechazado" <?php echo $estado == 'rechazado' ? 'selected' : ''; ?>>❌ Rechazados</option>

                        </select>
                    </div>
                    <?php endif; ?>

                    
                    <div class="public-buscar-comentarios-campo">
                        <label for="fecha_desde">📅 Desde fecha:</label>
                        <input type="date" id="fecha_desde" name="fecha_desde" class="public-buscar-comentarios-input"
                               value="<?php echo eBuscarComentarios($fecha_desde); ?>">

                    </div>
                    
                    <div class="public-buscar-comentarios-campo">
                        <label for="fecha_hasta">📅 Hasta fecha:</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" class="public-buscar-comentarios-input"
                               value="<?php echo eBuscarComentarios($fecha_hasta); ?>">

                    </div>
                </div>
            </div>
            
            <div class="public-buscar-comentarios-acciones">
                <button type="submit" class="public-buscar-comentarios-btn public-buscar-comentarios-btn-buscar" id="btnBuscar">
                    🔍 Buscar
                </button>
                <button type="reset" class="public-buscar-comentarios-btn public-buscar-comentarios-btn-limpiar" onclick="window.location='<?php echo eBuscarComentarios($rutaBuscadorComentarios); ?>'">

                    🧹 Limpiar
                </button>
            </div>
        </form>
    </div>
    
    <!-- BOTÓN PARA MOSTRAR EL FORMULARIO NUEVAMENTE (solo si está oculto) -->
    <?php if ($busqueda_realizada): ?>


        <button onclick="mostrarFormulario()" class="public-buscar-comentarios-btn">
            🔍 Nueva búsqueda
        </button>

    <?php endif; ?>

    
    <!-- RESULTADOS -->
    <?php if ($busqueda_realizada): ?>

        <div class="public-buscar-comentarios-resultados">
            <h2 class="public-buscar-comentarios-resultados-titulo">
                Resultados: <?php echo $total_resultados; ?> comentario(s)

            </h2>
            
            <?php if (empty($comentarios)): ?>

                <div class="public-buscar-comentarios-alerta public-buscar-comentarios-alerta-info">
                    No se encontraron comentarios con esos criterios.
                </div>
            <?php else: ?>

                <div class="public-buscar-comentarios-lista">
                    <?php foreach ($comentarios as $comentario): ?>

                        <div class="public-buscar-comentarios-tarjeta" id="comentario-<?php echo $comentario['id_comentario']; ?>">

    <div class="public-buscar-comentarios-tarjeta-header">
        <img src="<?php echo base_url('uploads/perfiles/' . ($comentario['usuario_avatar'] ?? 'default-avatar.png')); ?>" 

             alt="Avatar" width="40" height="40" 
             onerror="this.src='<?php echo base_url('assets/img/default-avatar.png'); ?>'"

             class="public-buscar-comentarios-avatar">
        <div class="public-buscar-comentarios-info">
             📰 Noticia:&nbsp;<a href="<?php echo route($busquedaPrivada ? 'privado_comentarios' : 'noticia', ['id' => $comentario['id_noticia']]); ?>#comentario-<?php echo (int) $comentario['id_comentario']; ?>" class="public-buscar-comentarios-enlace">

                    <?php echo eBuscarComentarios($comentario['noticia_titulo']); ?>

                </a>
            
            <!-- ✅ NOTICIA MOVIDA AQUÍ - al lado del usuario -->
            <div class="public-buscar-comentarios-noticia-header">
                <strong>Comenta:&nbsp;<?php echo eBuscarComentarios($comentario['usuario_nombre']); ?></strong>

            </div>
        </div>
        <div class="public-buscar-comentarios-fecha">
            <?php echo formatearFecha($comentario['fecha_comentario']); ?><br>

            <small><?php echo tiempoTranscurrido($comentario['fecha_comentario']); ?></small>

        </div>
    </div>
    
    <div class="public-buscar-comentarios-contenido">
        <?php echo sanitizarHtmlComentario($comentario['contenido']); ?>

    </div>
    
    <div class="public-buscar-comentarios-acciones">
        <?php if (!$busquedaPrivada && estaLogueado() && (int) ($_SESSION['usuario_id'] ?? 0) === (int) $comentario['id_usuario']): ?>

            <a href="<?php echo route('editar_comentario', ['id' => $comentario['id_comentario']]); ?>" class="btn btn-small">✏️ Editar</a>

            <form method="POST" action="<?php echo route('eliminar_comentario'); ?>" style="display: inline;" onsubmit="return confirm('¿Eliminar este comentario?')">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="id_comentario" value="<?php echo (int) $comentario['id_comentario']; ?>">
                <button type="submit" class="btn btn-small btn-eliminar">🗑️ Eliminar</button>
            </form>

        <?php endif; ?>

        
        <?php if (estaLogueado() && (int) ($_SESSION['usuario_id'] ?? 0) !== (int) $comentario['id_usuario']): ?>

            <a href="<?php echo route($busquedaPrivada ? 'privado_reportar_comentario' : 'reportar_comentario', ['id' => $comentario['id_comentario']]); ?>" class="btn-reportar">

                🚩 Reportar
            </a>
        <?php endif; ?>

        <?php if (esAdmin()): ?>

            <a href="<?php echo route('admin_comentarios'); ?>?id=<?php echo (int) $comentario['id_comentario']; ?>" class="btn btn-small">

                🛠️ Gestionar
            </a>
        <?php endif; ?>

    </div>
</div>
                    <?php endforeach; ?>

                </div>
                
                <!-- PAGINACIÓN -->
                <?php if ($total_paginas > 1): ?>

                    <?php

                    $filtrosPaginacion = [
                        'id_categoria' => $id_categoria > 0 ? $id_categoria : null,
                        'id_noticia' => $id_noticia > 0 ? $id_noticia : null,
                        'id_usuario' => $id_usuario > 0 ? $id_usuario : null,
                        'palabras' => $palabras,
                        'estado' => esAdmin() && $estado !== 'todos' ? $estado : null,
                        'fecha_desde' => $fecha_desde,
                        'fecha_hasta' => $fecha_hasta,
                    ];
                    ?>
                    <div class="public-buscar-comentarios-paginacion">
                        <?php if ($pagina > 1): ?>

                            <?php

                            $parametrosAnterior = $filtrosPaginacion;
                            $parametrosAnterior['pagina'] = $pagina - 1;
                            ?>
                            <a href="<?php echo eBuscarComentarios(urlPaginacionComentarios($parametrosAnterior)); ?>" class="pagina-btn" rel="prev">

                                « Anterior
                            </a>
                        <?php endif; ?>


                        <span class="pagina-info">
                            Página <?php echo (int) $pagina; ?> de <?php echo (int) $total_paginas; ?>

                        </span>

                        <?php if ($pagina < $total_paginas): ?>

                            <?php

                            $parametrosSiguiente = $filtrosPaginacion;
                            $parametrosSiguiente['pagina'] = $pagina + 1;
                            ?>
                            <a href="<?php echo eBuscarComentarios(urlPaginacionComentarios($parametrosSiguiente)); ?>" class="pagina-btn" rel="next">

                                Siguiente »
                            </a>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    <?php endif; ?>

    
</div>

<script>
// Función para mostrar el formulario nuevamente y recargar la página sin filtros
function mostrarFormulario() {
    window.location.href = '<?php echo eBuscarComentarios($rutaBuscadorComentarios); ?>';

}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
