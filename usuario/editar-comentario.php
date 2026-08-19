<?php
declare(strict_types=1);


/**
 * EDITAR COMENTARIO
 * Permite a un usuario editar su propio comentario
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirLogin();

$id_comentario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_comentario) {
    mensajeFlash('error', 'Comentario no válido');
    redireccionar(route('mis_comentarios'));
}

$pdo = db();

try {
    // Obtener comentario con datos de la noticia
    $stmt = $pdo->prepare("
        SELECT c.*, n.titulo as noticia_titulo, n.id_noticia, n.estado as noticia_estado
        FROM comentarios c
        JOIN noticias n ON c.id_noticia = n.id_noticia
        WHERE c.id_comentario = :id
    ");
    $stmt->execute([':id' => $id_comentario]);
    $comentario = $stmt->fetch();
    
    if (!$comentario) {
        mensajeFlash('error', 'Comentario no encontrado');
        redireccionar(route('mis_comentarios'));
    }
    
    // Verificar propiedad
    if (!Permisos::puedeEditarComentario($comentario['id_usuario'])) {
        mensajeFlash('error', 'No tienes permiso para editar este comentario');
        redireccionar(route('mis_comentarios'));
    }
    
    // No se pueden editar comentarios rechazados
    if ($comentario['estado'] === 'rechazado') {
        mensajeFlash('error', 'No se pueden editar comentarios rechazados');
        redireccionar(route('mis_comentarios'));
    }
    
    // Procesar formulario de edición
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
            mensajeFlash('error', 'Error de seguridad. Recarga la página e inténtalo de nuevo.');
            redireccionar(route('editar_comentario', ['id' => $id_comentario]));
            exit;
        }

        $contenidoEntrada = is_string($_POST['contenido'] ?? null) ? $_POST['contenido'] : '';
        $contenido = sanitizarHtmlComentario($contenidoEntrada);
        $contenidoTexto = trim(strip_tags($contenido));
        
        $errores = [];
        
        if ($contenidoTexto === '') {
            $errores[] = 'El comentario no puede estar vacío';
        }
        
        if (mb_strlen($contenidoTexto) < 3) {
            $errores[] = 'El comentario debe tener al menos 3 caracteres';
        }
        
        if (mb_strlen($contenidoTexto) > 1000) {
            $errores[] = 'El comentario no puede tener más de 1000 caracteres';
        }
        
        if (empty($errores)) {
            // Si el comentario estaba aprobado y se edita, puede volver a pendiente
            $nuevo_estado = $comentario['estado'];
            
            // Si estaba aprobado, pasa a pendiente de moderación
            if ($comentario['estado'] === 'aprobado') {
                $nuevo_estado = 'pendiente';
            }
            
            $sql = "UPDATE comentarios SET 
                    contenido = :contenido,
                    estado = :estado,
                    fecha_actualizacion = NOW()
                    WHERE id_comentario = :id";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                ':contenido' => $contenido,
                ':estado' => $nuevo_estado,
                ':id' => $id_comentario
            ])) {
                if ($nuevo_estado === 'pendiente') {
                    mensajeFlash('success', 'Comentario actualizado y enviado a moderación');
                } else {
                    mensajeFlash('success', 'Comentario actualizado correctamente');
                }
                
                redireccionar(route('noticia', ['id' => (int) $comentario['id_noticia']]));
            } else {
                $error = 'Error al actualizar el comentario';
            }
        }
    }
    
} catch (Exception $e) {
    $error = 'No se pudo procesar el comentario. Inténtalo de nuevo.';
    registrarErrorInterno('USUARIO.COMENTARIO.EDITAR', $e);
}

$titulo_pagina = 'Editar Comentario';
$cargar_tinymce = true;
$cargar_editor_config = false;
$cargar_comentarios_js = false;
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('usuario-editar-comentario.css'); ?>">

<div class="usuario-editar-comentario-container">
    
    <h1 class="usuario-editar-comentario-titulo">✏️ Editar Comentario</h1>
    <p class="usuario-editar-comentario-nota">⚠️ Al editar un comentario aprobado, pasará a pendiente de moderación.</p>
    <?php if (isset($error)): ?>

        <div class="usuario-editar-comentario-alerta usuario-editar-comentario-alerta-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>

    <?php endif; ?>

    
    <?php if (!empty($errores)): ?>

        <div class="usuario-editar-comentario-alerta usuario-editar-comentario-alerta-error">
            <ul class="usuario-editar-comentario-error-list">
                <?php foreach ($errores as $error_item): ?>

                    <li><?php echo htmlspecialchars($error_item, ENT_QUOTES, 'UTF-8'); ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <div class="usuario-editar-comentario-formulario">
        
        <div class="usuario-editar-comentario-info-noticia">
            <p class="usuario-editar-comentario-info-item">
                <strong class="usuario-editar-comentario-info-label">📰 Noticia:</strong> 
                <a href="<?php echo htmlspecialchars(route('noticia', ['id' => (int) $comentario['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="usuario-editar-comentario-info-link">

                    <?php echo htmlspecialchars($comentario['noticia_titulo']); ?>

                </a>
            </p>
            <p class="usuario-editar-comentario-info-item">
                <strong class="usuario-editar-comentario-info-label">📅 Fecha original:</strong> 
                <span class="usuario-editar-comentario-info-valor"><?php echo formatearFecha($comentario['fecha_comentario']); ?></span>

            </p>
            <p class="usuario-editar-comentario-info-item">
                <strong class="usuario-editar-comentario-info-label">📌 Estado actual:</strong> 
                <?php

                switch ($comentario['estado']) {
                    case 'aprobado':
                        echo '<span class="usuario-editar-comentario-badge usuario-editar-comentario-badge-aprobado">✅ Aprobado</span>';
                        break;
                    case 'pendiente':
                        echo '<span class="usuario-editar-comentario-badge usuario-editar-comentario-badge-pendiente">⏳ Pendiente de moderación</span>';
                        break;
                    case 'rechazado':
                        echo '<span class="usuario-editar-comentario-badge usuario-editar-comentario-badge-rechazado">❌ Rechazado</span>';
                        break;
                }
                ?>
            </p>
            <?php if ($comentario['estado'] === 'aprobado'): ?>

                
            <?php endif; ?>

        </div>
        
        <form method="POST" class="usuario-editar-comentario-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="usuario-editar-comentario-campo">
                <label for="contenido" class="usuario-editar-comentario-label">📝 Editar comentario:</label>
                <textarea id="comentario-editor" name="contenido" rows="6" required 
                          class="usuario-editar-comentario-textarea"
                          placeholder="Escribe tu comentario..."><?php echo htmlspecialchars(is_string($_POST['contenido'] ?? null) ? $_POST['contenido'] : $comentario['contenido']); ?></textarea>

                <div class="usuario-editar-comentario-contador">
                    <span id="contador" class="usuario-editar-comentario-contador-numero">0</span>
                    <span class="usuario-editar-comentario-contador-texto">/1000 caracteres</span>
                </div>
            </div>
            
            <div class="usuario-editar-comentario-acciones">
                <button type="submit" class="usuario-editar-comentario-btn usuario-editar-comentario-btn-guardar">
                    💾 Guardar cambios
                </button>
                <a href="<?php echo htmlspecialchars(route('noticia', ['id' => (int) $comentario['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="usuario-editar-comentario-btn usuario-editar-comentario-btn-cancelar">

                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
    
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.tinymce === 'undefined') {
        return;
    }

    window.tinymce.init({
        selector: '#comentario-editor',
        height: 300,
        menubar: false,
        plugins: 'advlist lists link textcolor hr emoticons charmap searchreplace preview fullscreen visualblocks wordcount help',
        toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent blockquote hr | link unlink | emoticons charmap | searchreplace preview fullscreen visualblocks | removeformat help',
        toolbar_mode: 'sliding',
        branding: false,
        statusbar: true
    });
});
</script>

<script>
// Contador de caracteres
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('comentario-editor');
    const contadorSpan = document.getElementById('contador');
    
    function actualizarContador() {
        if (textarea && contadorSpan) {
            const longitud = textarea.value.length;
            contadorSpan.textContent = longitud;
            
            if (longitud > 990) {
                contadorSpan.style.color = '#ef4444';
            } else if (longitud > 950) {
                contadorSpan.style.color = '#f59e0b';
            } else {
                contadorSpan.style.color = '#6b7280';
            }
        }
    }
    
    if (textarea) {
        textarea.addEventListener('input', actualizarContador);
        actualizarContador();
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
