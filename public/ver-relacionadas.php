<?php
declare(strict_types=1);


/**
 * VENTANA EMERGENTE DE NOTICIAS RELACIONADAS CON COMENTARIOS EN MODAL
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/privado.php';
$id_noticia = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vistaPrivada = defined('VISTA_RELACIONADAS_PRIVADAS') && VISTA_RELACIONADAS_PRIVADAS === true;

if (!$id_noticia) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pdo = db();

if ($vistaPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// ============================================
// 1. OBTENER DATOS DE LA NOTICIA ACTUAL
// ============================================
$stmt = $pdo->prepare(
    "SELECT titulo, privada
     FROM noticias
     WHERE id_noticia = ?
       AND estado = 'publicada'
       AND privada = ?
     LIMIT 1"
);
$stmt->execute([$id_noticia, $vistaPrivada ? 1 : 0]);
$noticia = $stmt->fetch();

if (!$noticia) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// ============================================
// 2. OBTENER COMENTARIOS DE LA NOTICIA ACTUAL
// ============================================
$stmt_com = $pdo->prepare("
    SELECT c.*, u.nombre as usuario_nombre, u.avatar
    FROM comentarios c
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE c.id_noticia = ? AND c.estado = 'aprobado'
    ORDER BY c.fecha_comentario DESC
    LIMIT 5
");
$stmt_com->execute([$id_noticia]);
$comentarios_actual = $stmt_com->fetchAll();

// ============================================
// 3. OBTENER NOTICIAS RELACIONADAS Y PAGINAR
// ============================================
$todas_las_relacionadas = getNoticiasRelacionadas($id_noticia, 100, $vistaPrivada ? 1 : 0);
$total_relacionadas = count($todas_las_relacionadas);
$articulos_por_pagina = 9;
$total_paginas = ceil($total_relacionadas / $articulos_por_pagina);

$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
if ($pagina_actual > $total_paginas && $total_paginas > 0) $pagina_actual = $total_paginas;

$inicio = ($pagina_actual - 1) * $articulos_por_pagina;
$relacionadas = array_slice($todas_las_relacionadas, $inicio, $articulos_por_pagina);

// Obtener en una sola consulta los comentarios de las noticias de la página.
$comentarios_por_noticia = [];
$ids_relacionadas = array_map(
    static fn(array $relacionada): int => (int) $relacionada['id_noticia'],
    $relacionadas
);

if ($ids_relacionadas !== []) {
    $marcadores = implode(',', array_fill(0, count($ids_relacionadas), '?'));
    $stmt = $pdo->prepare("
        SELECT c.*, u.nombre AS usuario_nombre, u.avatar
        FROM comentarios c
        JOIN usuarios u ON c.id_usuario = u.id_usuario
        WHERE c.id_noticia IN ($marcadores)
          AND c.estado = 'aprobado'
        ORDER BY c.id_noticia, c.fecha_comentario DESC
    ");
    $stmt->execute($ids_relacionadas);

    while ($comentario = $stmt->fetch()) {
        $id_relacionada = (int) $comentario['id_noticia'];
        $comentarios_por_noticia[$id_relacionada] ??= [];

        if (count($comentarios_por_noticia[$id_relacionada]) >= 5) {
            continue;
        }

        $comentario['contenido'] = sanitizarHtmlComentario(
            html_entity_decode((string) $comentario['contenido'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        $comentarios_por_noticia[$id_relacionada][] = $comentario;
    }
}

foreach ($relacionadas as $key => $noticia_rel) {
    $comentarios = $comentarios_por_noticia[(int) $noticia_rel['id_noticia']] ?? [];
    $relacionadas[$key]['comentarios'] = $comentarios;
    $relacionadas[$key]['total_comentarios'] = count($comentarios);
}
$titulo_pagina = 'Noticias relacionadas - ' . htmlspecialchars((string) $noticia['titulo'], ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('public-ver-relacionadas.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-relacionadas">
    
    <div class="cabecera-relacionadas">
        <h1>🔗 Noticias relacionadas</h1>
        <a href="<?php echo route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $id_noticia]); ?>" class="btn-volver-relacionadas">← Volver a la noticia</a>

    </div>
    
    <div class="noticia-origen">
        <div class="comentarios-actual-relacionadas">
            <strong>📰 Noticia actual:</strong>
            <?php echo htmlspecialchars($noticia['titulo']); ?>

        </div>
    </div>
    
    <?php if (empty($relacionadas)): ?>

        <div class="sin-relacionadas">
            <p>No hay noticias relacionadas disponibles.</p>
        </div>
    <?php else: ?>

        <div class="lista-relacionadas">
            <?php foreach ($relacionadas as $rel): ?>

                <div class="tarjeta-relacionada">
                    
                    <!-- METADATOS -->
                    <div class="relacionada-metadatos">
                        <?php if ($rel['fuente']): ?>

                            <div><strong>Fuente:</strong> <?php echo htmlspecialchars($rel['fuente']); ?></div>

                        <?php endif; ?>

                        <div class="relacionada-fecha-visitas">
                            📅 <?php echo formatearFecha($rel['fecha_publicacion']); ?> | 

                            👁️ <?php echo number_format($rel['visitas']); ?> visitas

                        </div>
                        <?php if (isset($rel['tipo_relacion']) && $rel['tipo_relacion'] === 'manual'): ?>

                                <span class="badge-manual">✏️ Manual</span>
                            <?php else: ?>

                                <span class="badge-automatica">Automática</span>
                            <?php endif; ?>

                    </div>
                    
                    <!-- HEADER E IMAGEN -->
                    <div class="tarjeta-header-relacionadas">
                        <?php echo mostrarImagenNoticia(
                            $rel,
                            'relacionada-imagen',
                            '📷',
                            route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $rel['id_noticia']])
                        ); ?>

                        
                        <div class="relacionada-info">                            
                            
                            <h3 class="relacionada-titulo">
                                <a href="<?php echo route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $rel['id_noticia']]); ?>">

                                    <?php echo htmlspecialchars($rel['titulo']); ?>

                                </a>
                            </h3>
                            <?php if ($rel['subtitulo']): ?>

                                <p class="relacionada-subtitulo"><?php echo htmlspecialchars($rel['subtitulo']); ?></p>

                            <?php endif; ?>

                        </div>
                    </div>
                    
                    <!-- EXTRACTO -->
                    <?php if (!empty($rel['contenido'])): ?>

                        <div class="relacionada-extracto">
                            <p><?php echo htmlspecialchars(
                                obtenerPrimerParrafo((string) $rel['contenido'], 120),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?></p>

                        </div>
                        <!-- BOTÓN LEER MÁS -->
                    <div class="relacionada-accion">
                        <a href="<?php echo route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $rel['id_noticia']]); ?>" class="btn-leer-mas-relacionada">

                            Leer noticia completa →
                        </a>
                    </div>
                    <?php endif; ?>

                    
                    <!-- BOTÓN VER COMENTARIOS (MODAL) -->
                    <div class="comentarios-section-relacionadas">
                        <button type="button" class="btn-ver-comentarios" data-noticia-id="<?php echo (int) $rel['id_noticia']; ?>">

                            💬 Ver comentarios (<?php echo $rel['total_comentarios']; ?>)

                        </button>
                    </div>
                    
                    
                    
                </div>
            <?php endforeach; ?>

        </div>

        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>

            <div class="paginacion-container">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                    <a href="?id=<?php echo $id_noticia; ?>&p=<?php echo $i; ?>" 

                       class="paginacion-link <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">

                        <?php echo $i; ?>

                    </a>
                <?php endfor; ?>

            </div>
        <?php endif; ?>


    <?php endif; ?>

    
    <a href="<?php echo route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $id_noticia]); ?>" class="btn-volver-relacionadas">

        ← Volver a la noticia
    </a>
</div>

<!-- MODAL PARA COMENTARIOS -->
<div
    id="modalComentarios"
    class="modal-comentarios"
    style="display: none;position: fixed;top: 0;left: 0;"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="modalTitulo"
>
    <div class="modal-comentarios-contenido" tabindex="-1">
        <div class="modal-comentarios-header">
            <h3 id="modalTitulo" style="font-size: 1rem; margin: 0;">💬 Comentarios</h3>
            <button type="button" class="modal-comentarios-cerrar" aria-label="Cerrar comentarios">&times;</button>
        </div>
        <div id="modalComentariosBody" class="modal-comentarios-body" >
            <div style="text-align: center; padding: 2rem;">Cargando comentarios...</div>
        </div>
        <div id="modalFooter" class="modal-comentarios-footer" style="display: none">
            <a href="#" id="modalVerNoticia" style="color: #3b82f6; text-decoration: none;">Ver todos los comentarios en la noticia →</a>
        </div>
    </div>
</div>

<script type="application/json" id="datosComentariosRelacionados"><?php

    $comentarios_json = [];
    foreach ($relacionadas as $rel) {
        $comentarios_json[$rel['id_noticia']] = [
            'titulo' => $rel['titulo'],
            'comentarios' => $rel['comentarios'],
            'total' => $rel['total_comentarios'],
            'url' => route(
                $vistaPrivada ? 'privado_noticia' : 'noticia',
                ['id' => (int) $rel['id_noticia']]
            ),
        ];
    }
    echo json_encode(
        $comentarios_json,
        JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
?></script>
<script src="<?php echo htmlspecialchars(js_url('modal-comentarios.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
