<?php
declare(strict_types=1);


/**
 * PÁGINA DE COMENTARIOS INDEPENDIENTE
 * Incluye editor TinyMCE con funcionamiento alternativo mediante textarea.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/helpers/reportes-confirmados.php';

iniciarSesion();

$id_noticia = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$vistaPrivada = defined('VISTA_COMENTARIOS_PRIVADOS') && VISTA_COMENTARIOS_PRIVADOS === true;

if ($id_noticia <= 0) {
    http_response_code(400);
    die('ID de noticia no válido');
}

if ($vistaPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    die('Noticia no encontrada');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT id_noticia, titulo, permitir_comentarios
    FROM noticias
    WHERE id_noticia = ?
      AND estado = 'publicada'
      AND privada = ?
    LIMIT 1
");
$stmt->execute([$id_noticia, $vistaPrivada ? 1 : 0]);
$noticia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$noticia) {
    http_response_code(404);
    die('Noticia no encontrada');
}

$stmt = $pdo->prepare("
    SELECT
        c.id_comentario,
        c.id_noticia,
        c.id_usuario,
        c.contenido,
        c.estado,
        c.fecha_comentario,
        u.nombre,
        u.avatar,
        u.rol
    FROM comentarios c
    INNER JOIN usuarios u
        ON c.id_usuario = u.id_usuario
    WHERE c.id_noticia = ?
      AND c.estado = 'aprobado'
    ORDER BY c.fecha_comentario DESC
");
$stmt->execute([$id_noticia]);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reportesConfirmadosComentarios = obtenerReportesConfirmadosComentarios(
    $pdo,
    array_column($comentarios, 'id_comentario'),
    $vistaPrivada
);

$titulo_pagina = 'Comentarios - ' . $noticia['titulo'];
$cargar_tinymce = true;
$cargar_editor_config = false;
$cargar_comentarios_js = false;
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-comentarios.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('reportes-confirmados.css'); ?>">


<div class="comentarios-container">
    <div class="comentarios-header">
        <a href="<?php echo route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $id_noticia]); ?>" class="comentarios-volver">← Noticia</a>

        <p class="comentarios-noticia-titulo"><?php echo htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?></p>

        <h1>💬&nbsp;<?php echo count($comentarios); ?></h1>

    </div>

    <?php if ((int) $noticia['permitir_comentarios'] === 1): ?>

        <?php if (estaLogueado()): ?>

            <div class="comentarios-boton-abrir">
                <button type="button" id="btn-mostrar-formulario" class="comentarios-btn-abrir">✏️ Deja tu comentario</button>
            </div>

            <div id="comentarios-formulario" class="comentarios-form-card" hidden>
                <div class="comentarios-form-header">
                    <div><h2>Deja tu comentario</h2><p>Comparte tu opinión con respeto. Podrá quedar pendiente de moderación.</p></div>
                    <button type="button" id="btn-cerrar-formulario" class="comentarios-btn-cerrar" aria-label="Cerrar formulario">✕</button>
                </div>

                <form id="form-comentario" action="<?php echo route($vistaPrivada ? 'privado_procesar_comentario' : 'procesar_comentario'); ?>" method="POST">

                    <input type="hidden" name="id_noticia" value="<?php echo (int) $id_noticia; ?>">

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">


                    <textarea
                        name="contenido"
                        id="comentario-editor"
                        rows="6"
                        required
                        maxlength="5000"
                        placeholder="Escribe tu comentario..."
                    ></textarea>

                    <div class="comentarios-form-botones">
                        <button type="submit" class="comentarios-btn-publicar">📤 Publicar comentario</button>
                        <button type="button" id="btn-cancelar-formulario" class="comentarios-btn-cancelar">❌ Cancelar</button>
                    </div>
                </form>
            </div>
        <?php else: ?>

            <div class="comentarios-login-card">
                <p>🔑 <a href="<?php echo route('login'); ?>">Inicia sesión</a> o <a href="<?php echo route('registro'); ?>">regístrate</a> para comentar.</p>

            </div>
        <?php endif; ?>

    <?php else: ?>

        <div class="comentarios-info-card">
            <p>🔒 Los comentarios están desactivados para esta noticia.</p>
        </div>
    <?php endif; ?>


    <div class="comentarios-lista">
        <?php if (empty($comentarios)): ?>

            <p class="comentarios-vacio">📭 No hay comentarios todavía. ¡Sé el primero en comentar!</p>
        <?php else: ?>

            <?php foreach ($comentarios as $comentario): ?>

                <div class="comentario-card" id="comentario-<?php echo (int) $comentario['id_comentario']; ?>">

                    <div class="comentario-header">
                        <img
                            src="<?php echo base_url('uploads/perfiles/' . ($comentario['avatar'] ?: 'default-avatar.png')); ?>"

                            alt="<?php echo htmlspecialchars($comentario['nombre'], ENT_QUOTES, 'UTF-8'); ?>"

                            class="comentario-avatar"
                            onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(base_url('assets/img/default-avatar.png'), ENT_QUOTES, 'UTF-8'); ?>';"

                        >

                        <div>
                            <strong><?php echo htmlspecialchars($comentario['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>

                            <span class="comentario-rol <?php echo htmlspecialchars($comentario['rol'], ENT_QUOTES, 'UTF-8'); ?>">

                                <?php echo htmlspecialchars($comentario['rol'], ENT_QUOTES, 'UTF-8'); ?>

                            </span>
                        </div>

                        <span class="comentario-fecha">
                            <?php echo htmlspecialchars(tiempoTranscurrido($comentario['fecha_comentario']), ENT_QUOTES, 'UTF-8'); ?>

                        </span>
                    </div>

                    <div class="comentario-contenido">
                        <?php echo sanitizarHtmlComentario($comentario['contenido']); ?>

                    </div>

                    <?php $resumenReporte = $reportesConfirmadosComentarios[(int) $comentario['id_comentario']] ?? null; ?>
                    <?php if ($resumenReporte !== null && $resumenReporte['total'] > 0): ?>
                        <aside class="reporte-confirmado-aviso reporte-confirmado-aviso-comentario">
                            <strong>🚩 <?= (int) $resumenReporte['total']; ?> reporte<?= $resumenReporte['total'] === 1 ? '' : 's'; ?> confirmado<?= $resumenReporte['total'] === 1 ? '' : 's'; ?></strong>
                            <span>Motivos: <?= htmlspecialchars(implode(', ', $resumenReporte['motivos']), ENT_QUOTES, 'UTF-8'); ?>.</span>
                        </aside>
                    <?php endif; ?>

                    <div class="comentario-acciones">
                        <?php

                        $usuarioActual = (int) ($_SESSION['usuario_id'] ?? 0);
                        $autorComentario = (int) $comentario['id_usuario'];
                        ?>

                        <?php if (!$vistaPrivada && estaLogueado() && $usuarioActual === $autorComentario): ?>

                            <a href="<?php echo route('editar_comentario', ['id' => $comentario['id_comentario']]); ?>">✏️ Editar</a>

                            <form method="POST" action="<?php echo route('eliminar_comentario'); ?>" style="display: inline;" onsubmit="return confirm('¿Eliminar este comentario?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id_comentario" value="<?php echo (int) $comentario['id_comentario']; ?>">
                                <button type="submit" style="background: none; border: 0; padding: 0; color: #6b7280; font: inherit; cursor: pointer;">🗑️ Eliminar</button>
                            </form>

                        <?php endif; ?>


                        <?php if (estaLogueado() && $usuarioActual !== $autorComentario): ?>

                            <a class="comentario-btn-reportar" href="<?php echo route($vistaPrivada ? 'privado_reportar_comentario' : 'reportar_comentario', ['id' => $comentario['id_comentario']]); ?>">🚩 Reportar</a>

                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const btnMostrar = document.getElementById('btn-mostrar-formulario');
    const formulario = document.getElementById('comentarios-formulario');
    const btnCerrar = document.getElementById('btn-cerrar-formulario');
    const btnCancelar = document.getElementById('btn-cancelar-formulario');
    const formComentario = document.getElementById('form-comentario');
    const editor = document.getElementById('comentario-editor');

    if (!btnMostrar || !formulario) {
        return;
    }

    function mostrarFormulario() {
        formulario.hidden = false;
        btnMostrar.hidden = true;

        if (typeof window.tinymce !== 'undefined' && window.tinymce.get('comentario-editor')) {
            window.tinymce.get('comentario-editor').focus();
        } else if (editor) {
            editor.focus();
        }
    }

    function ocultarFormulario() {
        formulario.hidden = true;
        btnMostrar.hidden = false;

        if (typeof window.tinymce !== 'undefined' && window.tinymce.get('comentario-editor')) {
            window.tinymce.get('comentario-editor').setContent('');
        } else if (editor) {
            editor.value = '';
        }
    }

    btnMostrar.addEventListener('click', mostrarFormulario);

    if (btnCerrar) {
        btnCerrar.addEventListener('click', ocultarFormulario);
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', ocultarFormulario);
    }

    if (typeof window.tinymce !== 'undefined' && editor) {
        window.tinymce.init({
            selector: '#comentario-editor',
            height: 280,
            menubar: false,
            branding: false,
            elementpath: true,
            statusbar: true,
            resize: true,

            plugins: [
                'advlist',
                'autolink',
                'lists',
                'link',
                'charmap',
                'preview',
                'searchreplace',
                'visualblocks',
                'code',
                'fullscreen',
                'insertdatetime',
                'table',
                'help',
                'wordcount',
                'emoticons'
            ],

            toolbar: [
                'undo redo | blocks | bold italic underline strikethrough',
                'forecolor backcolor | alignleft aligncenter alignright alignjustify',
                'bullist numlist | outdent indent | blockquote',
                'link emoticons charmap | removeformat | fullscreen | help'
            ].join(' | '),

            toolbar_mode: 'sliding',

            content_style:
                'body {' +
                'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;' +
                'font-size: 15px;' +
                'line-height: 1.6;' +
                'padding: 8px;' +
                '}',

            placeholder: 'Escribe tu comentario...',

            invalid_elements:
                'script,iframe,object,embed,form,input,button,img,image,media,video,audio,source',

            extended_valid_elements:
                'a[href|target|rel|title],blockquote,cite,code,pre,span[style],p[style]',

            link_assume_external_targets: true,
            default_link_target: '_blank',

            setup: function (tinyEditor) {
                tinyEditor.on('change keyup input undo redo', function () {
                    tinyEditor.save();
                });
            }
        }).catch(function (error) {
            console.warn('TinyMCE no pudo inicializarse:', error);
        });
    } else {
        console.warn('TinyMCE no se ha cargado. Se utilizará el textarea normal.');
    }

    if (formComentario) {
        formComentario.addEventListener('submit', function (event) {
            if (typeof window.tinymce !== 'undefined' && window.tinymce.get('comentario-editor')) {
                window.tinymce.get('comentario-editor').save();
            }

            const contenido = editor ? editor.value.trim() : '';

            if (contenido === '') {
                event.preventDefault();
                alert('Escribe un comentario antes de publicarlo.');

                if (typeof window.tinymce !== 'undefined' && window.tinymce.get('comentario-editor')) {
                    window.tinymce.get('comentario-editor').focus();
                } else if (editor) {
                    editor.focus();
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
