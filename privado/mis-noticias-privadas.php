<?php
declare(strict_types=1);


/**
 * MIS NOTICIAS PRIVADAS - Listado de noticias privadas
 * Los periodistas con permiso ven todas las noticias privadas
 * Los admin ven todas
 * Diseño responsive con tarjetas (3 → 2 → 1 columnas)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
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
$tiene_privado = usuarioEsPrivado();

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['accion'] ?? '') === 'eliminar_seleccion'
) {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', 'Error de seguridad');
        redireccionar(route('privado_mis_noticias'));
    }

    $ids = $_POST['noticias'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $resultado = eliminarNoticiasCompletamente(
        $pdo,
        $ids,
        (int) $id_usuario,
        $es_admin,
        1
    );
    $tipoMensaje = $resultado['success'] ? 'success' : 'error';
    $mensaje = $resultado['message'];
    if (($resultado['archivos_no_eliminados'] ?? 0) > 0) {
        $mensaje .= ' Algún archivo no pudo retirarse y requiere revisión.';
    }
    mensajeFlash($tipoMensaje, $mensaje);
    redireccionar(route('privado_mis_noticias'));
}

// Paginación
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 12; // Múltiplo de 3 para grid
$offset = ($pagina - 1) * $por_pagina;

try {
    // Consulta base
    $sql_count = "SELECT COUNT(*) FROM noticias WHERE privada = 1 AND id_autor = :id_autor";
    $sql = "
        SELECT n.*, u.nombre as autor_nombre, u.avatar as autor_avatar, c.nombre_categoria
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        JOIN categorias c ON n.id_categoria = c.id_categoria
        WHERE n.privada = 1
          AND n.id_autor = :id_autor
    ";
    
    $params = [':id_autor' => $id_usuario];
    
    // Total registros
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_noticias = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_noticias / $por_pagina);
    
    // Orden y paginación
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
    $error = 'No se pudieron cargar las noticias privadas.';
    registrarErrorInterno('PRIVADO.MIS_NOTICIAS.CARGA', $e);
}

$titulo_pagina = 'Mis Noticias Privadas';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('privado-mis-noticias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<div class="privado-noticias-container">
    
    <h1 class="privado-noticias-titulo">🔒 Mis Noticias Privadas</h1>
    
    <?php if (isset($error)): ?>

        <div class="privado-noticias-alerta privado-noticias-alerta-error"><?php echo $error; ?></div>

    <?php endif; ?>

    
    <div class="privado-noticias-acciones">
        <a href="<?php echo htmlspecialchars(route('privado_nueva_noticia'), ENT_QUOTES, 'UTF-8'); ?>" class="privado-noticias-btn privado-noticias-btn-primary">➕ Nueva noticia privada</a>
        <a href="<?php echo htmlspecialchars(route('privado_buscar'), ENT_QUOTES, 'UTF-8'); ?>" class="privado-noticias-btn privado-noticias-btn-secondary">🔍 Buscar noticias</a>
        <a href="<?php echo htmlspecialchars(route('privado_dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="privado-noticias-btn privado-noticias-btn-secondary">← Panel Privado</a>
    </div>
    
    <?php if (empty($noticias)): ?>

        <div class="privado-noticias-alerta privado-noticias-alerta-info">
            <p>No hay noticias privadas.</p>
            <?php if (!$tiene_privado && !$es_admin): ?>

                <p>No tienes permiso para crear noticias privadas. Contacta con el administrador.</p>
            <?php else: ?>

                <p><a href="<?php echo htmlspecialchars(route('privado_nueva_noticia'), ENT_QUOTES, 'UTF-8'); ?>">Crea tu primera noticia privada</a></p>
            <?php endif; ?>

        </div>
    <?php else: ?>

        <form
            id="eliminar-lote-privado"
            method="POST"
            onsubmit="return confirm('¿Eliminar definitivamente las noticias privadas seleccionadas y todo su contenido relacionado?')"
            style="margin-bottom: 1rem;"
        >
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="accion" value="eliminar_seleccion">
            <button type="submit" class="privado-noticias-btn privado-noticias-btn-secondary">🗑️ Eliminar seleccionadas (máximo 10)</button>
        </form>

        
        <p class="privado-noticias-resultados-info">
            📊 Mostrando <?php echo count($noticias); ?> de <?php echo $total_noticias; ?> noticias privadas

        </p>
        
        <!-- GRID DE TARJETAS (3 → 2 → 1) -->
        <div class="privado-noticias-grid">
            <?php foreach ($noticias as $n): ?>
                <?php
                $claseEstado = match ($n['estado'] ?? '') {
                    'borrador' => ' news-card--draft',
                    'pendiente' => ' news-card--pending',
                    'archivada' => ' news-card--archived',
                    default => '',
                };
                ?>
                <div class="privado-noticias-card news-card news-card--vertical news-card--private<?php echo $claseEstado; ?>">
    
    <!-- 1. TÍTULO -->
    <h3 class="privado-noticias-card-titulo news-card__title">
        <a href="<?php echo htmlspecialchars(route('privado_noticia', ['id' => $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>">

            <?php echo htmlspecialchars($n['titulo']); ?>

        </a>
    </h3>
    
    <!-- 2. SUBTÍTULO (si existe) -->
    <?php if (!empty($n['subtitulo'])): ?>

        <div class="privado-noticias-card-subtitulo news-card__subtitle">
            <?php echo htmlspecialchars($n['subtitulo']); ?>

        </div>
    <?php endif; ?>

    
    <!-- 3. IMAGEN (local o externa) -->
    <div class="privado-noticias-card-imagen news-card__media">
        <?php echo mostrarImagenNoticia(
            $n,
            'privado-noticias-img',
            '📷 Sin imagen',
            route('privado_noticia', ['id' => $n['id_noticia']])
        ); ?>

    </div>
    
    <!-- 4. CATEGORÍA -->
    <div class="privado-noticias-card-categoria news-card__meta news-card__meta--standard">
        <label>
            <input
                type="checkbox"
                name="noticias[]"
                value="<?php echo (int) $n['id_noticia']; ?>"
                form="eliminar-lote-privado"
            >
            Seleccionar · <?php echo number_format(calcularEspacioNoticiaBytes($n) / 1048576, 2); ?> MB
        </label>
        <span>📂 <a href="<?php echo route('privado_buscar', ['categoria' => (int) $n['id_categoria']]); ?>"><?php echo htmlspecialchars($n['nombre_categoria']); ?></a></span>

    </div>
    
    <!-- 5. FECHA -->
    <div class="privado-noticias-card-fecha">
        📅 <?php echo date('d/m/Y', strtotime($n['fecha_publicacion'])); ?>

    </div>
    
    <!-- 6. ESTADÍSTICAS -->
    <div class="privado-noticias-card-stats">
        <div class="privado-noticias-stat">
            <span class="privado-noticias-stat-numero"><?php echo number_format($n['visitas']); ?></span>

            <span class="privado-noticias-stat-etiqueta">visitas</span>
        </div>
        <div class="privado-noticias-stat">
            <span class="privado-noticias-stat-numero"><?php echo $n['megusta'] ?? 0; ?></span>

            <span class="privado-noticias-stat-etiqueta">👍 me gusta</span>
        </div>
    </div>
    
    <!-- 7. ESTADO -->
    <div class="privado-noticias-card-estado">
        <span class="privado-noticias-badge privado-noticias-badge-<?php echo $n['estado']; ?>">

            <?php echo ucfirst($n['estado']); ?>

        </span>
    </div>
    
    <!-- 8. ACCIONES -->
    <div class="privado-noticias-card-acciones news-card__actions">
        <?php if ($es_admin || (int) $n['id_autor'] === (int) $id_usuario): ?>

            <a href="<?php echo htmlspecialchars(route('privado_editar_noticia', ['id' => $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="privado-noticias-btn privado-noticias-btn-editar">

                ✏️ Editar
            </a>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars(route('privado_noticia', ['id' => $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="privado-noticias-btn privado-noticias-btn-ver">

            👁️ Ver
        </a>
    </div>
</div>

            <?php endforeach; ?>

        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>

            <div class="privado-noticias-paginacion">
                <?php if ($pagina > 1): ?>

                    <a href="?pagina=<?php echo $pagina - 1; ?>" class="privado-noticias-btn-pagina">« Anterior</a>

                <?php endif; ?>

                
                <div class="privado-noticias-pagina-numeros">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                        <?php if ($i == $pagina): ?>

                            <span class="privado-noticias-btn-pagina privado-noticias-active"><?php echo $i; ?></span>

                        <?php else: ?>

                            <a href="?pagina=<?php echo $i; ?>" class="privado-noticias-btn-pagina"><?php echo $i; ?></a>

                        <?php endif; ?>

                    <?php endfor; ?>

                </div>
                
                <?php if ($pagina < $total_paginas): ?>

                    <a href="?pagina=<?php echo $pagina + 1; ?>" class="privado-noticias-btn-pagina">Siguiente »</a>

                <?php endif; ?>

            </div>
        <?php endif; ?>

        
    <?php endif; ?>

    
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
