<?php
declare(strict_types=1);


/**
 * MIS COMENTARIOS - Usuarios normales
 * Ver, editar y eliminar sus propios comentarios
 */

// Rutas corregidas
require_once __DIR__ . '/../includes/bootstrap.php';

// Verificar autenticación
if (!estaLogueado()) {
    mensajeFlash('warning', 'Debes iniciar sesión');
    redireccionar(route('login'));
    exit;
}

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['editar_comentario']) || ($_POST['accion'] ?? '') === 'eliminar_comentario')
    && !verificarTokenCSRF($_POST['csrf_token'] ?? '')
) {
    mensajeFlash('error', 'Error de seguridad');
    redireccionar(route('mis_comentarios'));
    exit;
}

// Procesar edición de comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_comentario'])) {
    $id_comentario = (int) ($_POST['id_comentario'] ?? 0);
    $contenido = sanitizarHtmlComentario(trim((string) ($_POST['contenido'] ?? '')));
    
    $stmt = $pdo->prepare("SELECT id_usuario FROM comentarios WHERE id_comentario = ?");
    $stmt->execute([$id_comentario]);
    $autor = $stmt->fetchColumn();
    
    if ($autor == $id_usuario) {
        $stmt = $pdo->prepare("UPDATE comentarios SET contenido = ?, fecha_actualizacion = NOW() WHERE id_comentario = ?");
        $stmt->execute([$contenido, $id_comentario]);
        mensajeFlash('success', 'Comentario actualizado');
    } else {
        mensajeFlash('error', 'No tienes permiso');
    }
    
    redireccionar(route('mis_comentarios'));
    exit;
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_comentario') {
    $id_comentario = (int) ($_POST['id_comentario'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT id_usuario FROM comentarios WHERE id_comentario = ?");
    $stmt->execute([$id_comentario]);
    $autor = $stmt->fetchColumn();
    
    if ($autor == $id_usuario) {
        $pdo->prepare("DELETE FROM comentarios WHERE id_comentario = ?")->execute([$id_comentario]);
        mensajeFlash('success', 'Comentario eliminado');
    } else {
        mensajeFlash('error', 'No tienes permiso');
    }
    
    redireccionar(route('mis_comentarios'));
    exit;
}

// Obtener comentarios
$sql = "
    SELECT c.*, n.titulo as noticia_titulo, n.slug, n.privada
    FROM comentarios c
    JOIN noticias n ON c.id_noticia = n.id_noticia
    WHERE c.id_usuario = ?
    ORDER BY c.fecha_comentario DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario, $por_pagina, $offset]);
$comentarios = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$total = $stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

$titulo_pagina = 'Mis Comentarios';
$cargar_tinymce = true;
$cargar_editor_config = false;

// Ruta CORREGIDA a header.php
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('usuario-mis-comentarios.css'); ?>">


<div class="comentarios-container">
    <h1>💬 Mis Comentarios</h1>
    <p>Total: <?php echo $total; ?> comentarios</p>

    
    <?php if (empty($comentarios)): ?>

        <div class="comentary">
            <p>No has escrito ningún comentario aún.</p>
        </div>
    <?php else: ?>

        <?php foreach ($comentarios as $com): ?>

            <?php
            $ruta_noticia_comentada = (int) $com['privada'] === 1
                ? route('privado_noticia', ['id' => (int) $com['id_noticia']])
                : route('noticia', ['id' => (int) $com['id_noticia']]);
            ?>

            <div class="comentario-card" id="comentario-<?php echo $com['id_comentario']; ?>">

                <div class="comentario-header">
                    <div class="comentario-noticia">
                        📰 <a href="<?php echo htmlspecialchars($ruta_noticia_comentada, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">

                            <?php echo htmlspecialchars($com['noticia_titulo']); ?>

                        </a>
                    </div>
                    <div class="comentario-fecha">
                        <?php echo date('d/m/Y H:i', strtotime($com['fecha_comentario'])); ?>

                        <?php if ($com['fecha_actualizacion']): ?>

                            <br><small>Editado: <?php echo date('d/m/Y H:i', strtotime($com['fecha_actualizacion'])); ?></small>

                        <?php endif; ?>

                    </div>
                </div>
                
                <div class="comentario-contenido" id="contenido-<?php echo $com['id_comentario']; ?>">

                    <?php 

                    echo sanitizarHtmlComentario($com['contenido']);
                    ?>
                </div>
                
                <div class="comentario-acciones" id="acciones-<?php echo $com['id_comentario']; ?>">

                    <button type="button" class="btn btn-editar" onclick="mostrarEditor(<?php echo $com['id_comentario']; ?>)">✏️ Editar</button>

                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este comentario?')">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="accion" value="eliminar_comentario">
                        <input type="hidden" name="id_comentario" value="<?php echo (int) $com['id_comentario']; ?>">
                        <button type="submit" class="btn btn-eliminar">🗑️ Eliminar</button>
                    </form>

                </div>
                
                <div id="editor-<?php echo $com['id_comentario']; ?>" style="display: none;">

                    <form method="POST" class="form-editar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="editar_comentario" value="1">
                        <input type="hidden" name="id_comentario" value="<?php echo $com['id_comentario']; ?>">

                        <textarea id="editor-contenido-<?php echo $com['id_comentario']; ?>" name="contenido" class="editor-tinymce" rows="4" required><?php echo htmlspecialchars($com['contenido'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                        
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-guardar">💾 Guardar cambios</button>
                            <button type="button" class="btn btn-cancelar" onclick="cancelarEdicion(<?php echo $com['id_comentario']; ?>)">Cancelar</button>

                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        
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
<!-- ============================================ -->
<!-- SCRIPTS TINYMCE Y SINCRONIZACIÓN -->
<!-- ============================================ -->

<script>
function mostrarEditor(idComentario) {
    const contenido = document.getElementById('contenido-' + idComentario);
    const acciones = document.getElementById('acciones-' + idComentario);
    const contenedorEditor = document.getElementById('editor-' + idComentario);
    const textarea = document.getElementById('editor-contenido-' + idComentario);

    if (!contenedorEditor || !textarea) {
        return;
    }

    if (contenido) contenido.style.display = 'none';
    if (acciones) acciones.style.display = 'none';
    contenedorEditor.style.display = 'block';

    if (typeof window.tinymce === 'undefined' || window.tinymce.get(textarea.id)) {
        return;
    }

    window.tinymce.init({
        target: textarea,
        height: 200,
        menubar: false,
        plugins: 'advlist lists link textcolor hr emoticons charmap searchreplace preview fullscreen visualblocks wordcount help',
        toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent blockquote hr | link unlink | emoticons charmap | searchreplace preview fullscreen visualblocks | removeformat help',
        toolbar_mode: 'sliding',
        branding: false,
        statusbar: true
    });
}

function cancelarEdicion(idComentario) {
    const contenido = document.getElementById('contenido-' + idComentario);
    const acciones = document.getElementById('acciones-' + idComentario);
    const contenedorEditor = document.getElementById('editor-' + idComentario);
    const instancia = typeof window.tinymce !== 'undefined'
        ? window.tinymce.get('editor-contenido-' + idComentario)
        : null;

    if (instancia) instancia.remove();
    if (contenedorEditor) contenedorEditor.style.display = 'none';
    if (contenido) contenido.style.display = 'block';
    if (acciones) acciones.style.display = 'block';
}
</script>

<?php 

// Ruta CORREGIDA a footer.php
require_once __DIR__ . '/../partials/footer.php'; 
?>
