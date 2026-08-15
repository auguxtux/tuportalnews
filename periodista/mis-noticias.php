<?php
declare(strict_types=1);


/**
 * MIS NOTICIAS - Periodistas
 * Ver, editar y eliminar sus propias noticias
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/logs.php';

if (!estaLogueado()) {
    mensajeFlash('warning', 'Debes iniciar sesión');
    redireccionar(route('login'));
    exit;
}

if (!esPeriodista() && !esAdmin()) {
    mensajeFlash('error', 'Acceso solo para periodistas');
    redireccionar(route('home'));
    exit;
}

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(
    $_POST['accion'] ?? '',
    ['eliminar', 'eliminar_seleccion'],
    true
)) {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', 'Error de seguridad');
        redireccionar(route('mis_noticias'));
        exit;
    }

    $ids = ($_POST['accion'] ?? '') === 'eliminar_seleccion'
        ? ($_POST['noticias'] ?? [])
        : [(int) ($_POST['id_noticia'] ?? 0)];
    if (!is_array($ids)) {
        $ids = [];
    }

    $resultado = eliminarNoticiasCompletamente(
        $pdo,
        $ids,
        (int) $id_usuario,
        esAdmin(),
        0
    );

    $tipoMensaje = $resultado['success'] ? 'success' : 'error';
    $mensaje = $resultado['message'];
    if (($resultado['archivos_no_eliminados'] ?? 0) > 0) {
        $mensaje .= ' Algún archivo no pudo retirarse y requiere revisión.';
    }
    mensajeFlash($tipoMensaje, $mensaje);
    
    redireccionar(route('mis_noticias'));
    exit;
}

$sql = "
    SELECT n.*, c.nombre_categoria,
           (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia) as total_comentarios,
           (SELECT COUNT(*) FROM megusta_noticias WHERE id_noticia = n.id_noticia) as total_likes
    FROM noticias n
    JOIN categorias c ON n.id_categoria = c.id_categoria
    WHERE n.id_autor = ?
      AND n.privada = 0
    ORDER BY n.fecha_publicacion DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario, $por_pagina, $offset]);
$noticias = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_autor = ? AND privada = 0");
$stmt->execute([$id_usuario]);
$total = $stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

$titulo_pagina = 'Mis Noticias';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('periodista-mis-noticias.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">


<div class="noticias-container">
    <div class="menu-select">
        <a href="<?php echo route('periodista_perfil'); ?>">👤 Mi perfil</a>
        <a href="<?php echo route('mis_noticias'); ?>">📝 Mis noticias</a>
        <a href="<?php echo route('mis_comentarios'); ?>">💬 Mis comentarios</a>
        <a href="<?php echo route('periodista_eliminar_cuenta'); ?>" style="color:#dc2626;">🗑️ Eliminar mi cuenta</a>
    </div>
    
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 1rem;gap: 4%;">
        <h1>📝 Mis Noticias</h1>
        <a href="<?php echo route('nueva_noticia'); ?>" class="btn btn-nueva">➕ Nueva noticia</a>
    </div>
    
    <p>Total: <?php echo $total; ?> noticias</p>

    
    <?php if (empty($noticias)): ?>

        <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
            <p>No has publicado ninguna noticia aún.</p>
            <a href="<?php echo route('nueva_noticia'); ?>" class="btn btn-nueva">➕ Crear mi primera noticia</a>
        </div>
    <?php else: ?>

        <form
            id="eliminar-lote"
            method="POST"
            onsubmit="return confirm('¿Eliminar definitivamente las noticias seleccionadas y todo su contenido relacionado?')"
            style="margin-bottom: 1rem;"
        >
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="accion" value="eliminar_seleccion">
            <button type="submit" class="btn btn-eliminar">🗑️ Eliminar seleccionadas (máximo 10)</button>
        </form>

        <!-- Grid responsive: 4 columnas en desktop, 3 en tablet, 2 en móvil -->
<div class="noticias-grid">
    <?php foreach ($noticias as $noticia): ?>
                <?php
                $claseEstado = match ($noticia['estado'] ?? '') {
                    'borrador' => ' news-card--draft',
                    'pendiente' => ' news-card--pending',
                    'archivada' => ' news-card--archived',
                    default => '',
                };
                $claseOrigen = !empty($noticia['id_fuente_rss'])
                    ? ' news-card--external'
                    : ' news-card--public';
                ?>
                <div class="noticia-card news-card news-card--vertical<?php echo $claseOrigen . $claseEstado; ?>">
            <h3 class="noticia-titulo news-card__title">
                <a href="<?php echo enlaceNoticia($noticia); ?>">
                    <?php echo htmlspecialchars($noticia['titulo']); ?>
                </a>
            </h3>

            <!-- Imagen -->
            <div class="noticia-imagen news-card__media">
                <?php

                if (!empty($noticia['imagen_principal'])) {
                    echo '<img src="' . base_url('uploads/noticias/' . $noticia['imagen_principal']) . '" alt="' . htmlspecialchars($noticia['titulo']) . '">';
                } elseif (!empty($noticia['imagen_externa'])) {
                    echo '<img src="' . htmlspecialchars($noticia['imagen_externa']) . '" alt="' . htmlspecialchars($noticia['titulo']) . '" 
                          onerror="this.onerror=null; this.src=\'' . base_url('assets/img/default-image.jpg') . '\';">';
                } else {
                    echo '<div class="sin-imagen">📷 Sin imagen</div>';
                }
                ?>
            </div>
            
            <!-- Contenido de la tarjeta -->
            <div class="noticia-contenido news-card__body">
                <!-- Resto del código existente... -->
                
                <!-- Metadatos (categoría, fecha, estado, privada) -->
                <div class="noticia-meta news-card__meta news-card__meta--standard">
                    <label>
                        <input
                            type="checkbox"
                            name="noticias[]"
                            value="<?php echo (int) $noticia['id_noticia']; ?>"
                            form="eliminar-lote"
                        >
                        Seleccionar · <?php echo number_format(calcularEspacioNoticiaBytes($noticia) / 1048576, 2); ?> MB
                    </label>
                    <span class="noticia-categoria">📂 <a href="<?php echo $noticia['privada'] ? route('privado_buscar', ['categoria' => (int) $noticia['id_categoria']]) : route('categoria', ['id' => (int) $noticia['id_categoria']]); ?>"><?php echo htmlspecialchars($noticia['nombre_categoria']); ?></a></span>

                    <span class="noticia-fecha">📅 <?php echo date('d/m/Y H:i', strtotime($noticia['fecha_publicacion'])); ?></span>

                    <span class="estado-badge estado-<?php echo $noticia['estado']; ?>"><?php echo ucfirst($noticia['estado']); ?></span>

                    <?php if ($noticia['privada']): ?>

                        <span class="privada-badge">🔒 Privada</span>
                    <?php endif; ?>

                </div>
                
                <!-- Estadísticas -->
                <div class="noticia-stats">
                    <div class="noticia-stat">
                        <div class="noticia-stat-number"><?php echo number_format($noticia['visitas']); ?></div>

                        <div class="noticia-stat-label">Visitas</div>
                    </div>
                    <div class="noticia-stat">
                        <div class="noticia-stat-number"><a href="<?php echo route($noticia['privada'] ? 'privado_comentarios' : 'comentarios_noticia', ['id' => (int) $noticia['id_noticia']]); ?>"><?php echo $noticia['total_comentarios']; ?></a></div>

                        <div class="noticia-stat-label">Comentarios</div>
                    </div>
                    <div class="noticia-stat">
                        <div class="noticia-stat-number"><?php echo $noticia['total_likes']; ?></div>

                        <div class="noticia-stat-label">Likes</div>
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="noticia-acciones news-card__actions">
                    <a href="<?php echo route('editar_noticia', ['id' => (int) $noticia['id_noticia']]); ?>" class="btn btn-editar">✏️ Editar</a>

                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta noticia?')">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_noticia" value="<?php echo (int) $noticia['id_noticia']; ?>">
                        <button type="submit" class="btn btn-eliminar">🗑️ Eliminar</button>
                    </form>

                    <a href="<?php echo enlaceNoticia($noticia); ?>" class="btn btn-ver">👁️ Ver</a>

                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>
        
        <?php if ($total_paginas > 1): ?>

            <div class="paginacion">
                <?php if ($pagina > 1): ?>

                    <a href="?pagina=<?php echo $pagina - 1; ?>">← Anterior</a>

                <?php endif; ?>

                <span>Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>

                <?php if ($pagina < $total_paginas): ?>

                    <a href="?pagina=<?php echo $pagina + 1; ?>">Siguiente →</a>

                <?php endif; ?>

            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
