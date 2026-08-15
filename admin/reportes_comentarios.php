<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();
$mensaje = '';
$estadosValidos = ['pendiente', 'confirmado', 'revisado', 'desestimado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $mensaje = '❌ Error de seguridad.';
    } else {
        $tipo = ($_POST['tipo'] ?? '') === 'noticia' ? 'noticia' : 'comentario';
        $idReporte = (int)($_POST['id_reporte'] ?? 0);
        $accion = (string)($_POST['accion'] ?? '');
        $tabla = $tipo === 'noticia' ? 'reportes_noticias' : 'reportes_comentarios';

        if ($idReporte > 0 && in_array($accion, $estadosValidos, true)) {
            $stmt = $pdo->prepare("UPDATE {$tabla} SET estado = ? WHERE id_reporte = ?");
            $stmt->execute([$accion, $idReporte]);
            $mensaje = '✅ Estado del reporte actualizado.';
        } elseif ($idReporte > 0 && $accion === 'eliminar_reporte') {
            try {
                $pdo->beginTransaction();
                $comentarioId = 0;
                if ($tipo === 'comentario') {
                    $stmt = $pdo->prepare('SELECT comentario_id FROM reportes_comentarios WHERE id_reporte = ? FOR UPDATE');
                    $stmt->execute([$idReporte]);
                    $comentarioId = (int)$stmt->fetchColumn();
                }
                $pdo->prepare("DELETE FROM {$tabla} WHERE id_reporte = ?")->execute([$idReporte]);
                if ($comentarioId > 0) {
                    $pdo->prepare("UPDATE comentarios
                                   SET reportes_total = (SELECT COUNT(*) FROM reportes_comentarios WHERE comentario_id = ?)
                                   WHERE id_comentario = ?")
                        ->execute([$comentarioId, $comentarioId]);
                }
                $pdo->commit();
                $mensaje = '✅ Reporte eliminado.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                registrarErrorInterno('ADMIN.REPORTES.ELIMINAR', $e);
                $mensaje = '❌ No se pudo eliminar el reporte.';
            }
        } elseif ($idReporte > 0 && $tipo === 'comentario' && in_array($accion, ['ocultar_comentario', 'eliminar_comentario'], true)) {
            $stmt = $pdo->prepare('SELECT comentario_id FROM reportes_comentarios WHERE id_reporte = ?');
            $stmt->execute([$idReporte]);
            $comentarioId = (int)$stmt->fetchColumn();

            if ($comentarioId > 0 && $accion === 'ocultar_comentario') {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE comentarios SET estado = 'rechazado' WHERE id_comentario = ?")->execute([$comentarioId]);
                    $pdo->prepare("UPDATE reportes_comentarios SET estado = 'revisado' WHERE comentario_id = ?")->execute([$comentarioId]);
                    $pdo->commit();
                    $mensaje = '✅ Comentario ocultado y reportes revisados.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    registrarErrorInterno('ADMIN.REPORTES.COMENTARIO_OCULTAR', $e);
                    $mensaje = '❌ No se pudo ocultar el comentario.';
                }
            } elseif ($comentarioId > 0) {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM reportes_comentarios WHERE comentario_id = ?')
                        ->execute([$comentarioId]);
                    $pdo->prepare('DELETE FROM comentarios WHERE id_comentario = ?')
                        ->execute([$comentarioId]);
                    $pdo->commit();
                    $mensaje = '✅ Comentario y reportes asociados eliminados.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    registrarErrorInterno('ADMIN.REPORTES.COMENTARIO_ELIMINAR', $e);
                    $mensaje = '❌ No se pudo eliminar el comentario.';
                }
            }
        } elseif ($idReporte > 0 && $tipo === 'noticia' && in_array($accion, ['archivar_noticia', 'eliminar_noticia'], true)) {
            $stmt = $pdo->prepare('SELECT noticia_id FROM reportes_noticias WHERE id_reporte = ?');
            $stmt->execute([$idReporte]);
            $noticiaId = (int)$stmt->fetchColumn();

            if ($noticiaId > 0 && $accion === 'archivar_noticia') {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE noticias SET estado = 'archivada' WHERE id_noticia = ?")->execute([$noticiaId]);
                    $pdo->prepare("UPDATE reportes_noticias SET estado = 'revisado' WHERE noticia_id = ?")->execute([$noticiaId]);
                    $pdo->commit();
                    $mensaje = '✅ Noticia archivada y reportes revisados.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    registrarErrorInterno('ADMIN.REPORTES.NOTICIA_ARCHIVAR', $e);
                    $mensaje = '❌ No se pudo archivar la noticia.';
                }
            } elseif ($noticiaId > 0) {
                $resultado = eliminarNoticiasCompletamente(
                    $pdo,
                    [$noticiaId],
                    (int) ($_SESSION['usuario_id'] ?? 0),
                    true
                );
                $mensaje = ($resultado['success'] ? '✅ ' : '❌ ')
                    . $resultado['message'];
            }
        }
    }
}

$filtroTipo = in_array($_GET['tipo'] ?? 'todos', ['todos', 'comentario', 'noticia'], true)
    ? (string)($_GET['tipo'] ?? 'todos') : 'todos';
$filtroEstado = in_array($_GET['estado'] ?? 'todos', array_merge(['todos'], $estadosValidos), true)
    ? (string)($_GET['estado'] ?? 'todos') : 'todos';
$filtroMotivo = (string)($_GET['motivo'] ?? 'todos');
if ($filtroMotivo !== 'todos' && !motivoReporteValido($filtroMotivo)) {
    $filtroMotivo = 'todos';
}
$filtroAmbito = in_array($_GET['ambito'] ?? 'todos', ['todos', 'publico', 'privado'], true)
    ? (string)($_GET['ambito'] ?? 'todos') : 'todos';

$condicionesComentarios = [];
$condicionesNoticias = [];
$parametrosComentarios = [];
$parametrosNoticias = [];

if ($filtroEstado !== 'todos') {
    $condicionesComentarios[] = 'r.estado = ?';
    $condicionesNoticias[] = 'r.estado = ?';
    $parametrosComentarios[] = $filtroEstado;
    $parametrosNoticias[] = $filtroEstado;
}
if ($filtroMotivo !== 'todos') {
    $condicionesComentarios[] = 'r.motivo = ?';
    $condicionesNoticias[] = 'r.motivo = ?';
    $parametrosComentarios[] = $filtroMotivo;
    $parametrosNoticias[] = $filtroMotivo;
}
if ($filtroAmbito !== 'todos') {
    $ambitoPrivado = $filtroAmbito === 'privado' ? 1 : 0;
    $condicionesComentarios[] = 'n.privada = ?';
    $condicionesNoticias[] = 'n.privada = ?';
    $parametrosComentarios[] = $ambitoPrivado;
    $parametrosNoticias[] = $ambitoPrivado;
}

$reportes = [];
if ($filtroTipo !== 'noticia') {
    $sql = "SELECT r.*, c.contenido AS contenido_reportado, c.id_noticia,
                   n.titulo AS noticia_titulo, u.nombre AS autor_nombre,
                   ru.nombre AS reportador_nombre, n.privada AS ambito_privado, 'comentario' AS tipo
            FROM reportes_comentarios r
            JOIN comentarios c ON r.comentario_id = c.id_comentario
            JOIN noticias n ON c.id_noticia = n.id_noticia
            LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
            LEFT JOIN usuarios ru ON r.usuario_id = ru.id_usuario";
    if ($condicionesComentarios) {
        $sql .= ' WHERE ' . implode(' AND ', $condicionesComentarios);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametrosComentarios);
    $reportes = array_merge($reportes, $stmt->fetchAll());
}

if ($filtroTipo !== 'comentario') {
    $sql = "SELECT r.*, n.titulo AS noticia_titulo, n.contenido AS contenido_reportado,
                   n.id_noticia, u.nombre AS autor_nombre,
                   ru.nombre AS reportador_nombre, n.privada AS ambito_privado, 'noticia' AS tipo
            FROM reportes_noticias r
            JOIN noticias n ON r.noticia_id = n.id_noticia
            LEFT JOIN usuarios u ON n.id_autor = u.id_usuario
            LEFT JOIN usuarios ru ON r.usuario_id = ru.id_usuario";
    if ($condicionesNoticias) {
        $sql .= ' WHERE ' . implode(' AND ', $condicionesNoticias);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametrosNoticias);
    $reportes = array_merge($reportes, $stmt->fetchAll());
}

usort($reportes, static fn(array $a, array $b): int => strtotime((string)$b['fecha']) <=> strtotime((string)$a['fecha']));

$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM reportes_comentarios) + (SELECT COUNT(*) FROM reportes_noticias) AS total,
        (SELECT COUNT(*) FROM reportes_comentarios WHERE estado = 'pendiente') +
            (SELECT COUNT(*) FROM reportes_noticias WHERE estado = 'pendiente') AS pendientes,
        (SELECT COUNT(*) FROM reportes_comentarios WHERE estado = 'revisado') +
            (SELECT COUNT(*) FROM reportes_noticias WHERE estado = 'revisado') AS revisados,
        (SELECT COUNT(*) FROM reportes_comentarios WHERE estado = 'confirmado') +
            (SELECT COUNT(*) FROM reportes_noticias WHERE estado = 'confirmado') AS confirmados,
        (SELECT COUNT(*) FROM reportes_comentarios WHERE estado = 'desestimado') +
            (SELECT COUNT(*) FROM reportes_noticias WHERE estado = 'desestimado') AS desestimados,
        (SELECT COUNT(*) FROM reportes_comentarios) AS comentarios,
        (SELECT COUNT(*) FROM reportes_noticias) AS noticias
")->fetch();

$titulo_pagina = 'Gestión de Reportes';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('admin-comentarios.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('admin-reportes.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="admin-comentarios-container">
    <h1>🚩 Gestión de Reportes</h1>

    <details class="reportes-ayuda">
        <summary>ℹ️ ¿Qué hace cada acción?</summary>
        <div class="reportes-ayuda-contenido">
            <ul class="reportes-ayuda-lista">
                <li><strong>Pendiente:</strong> reabre el reporte para revisarlo posteriormente.</li>
                <li><strong>Revisado:</strong> confirma que fue examinado sin ocultar ni eliminar el contenido.</li>
                <li><strong>Confirmado:</strong> valida el reporte y publica solo su número y motivo; el denunciante y su descripción permanecen privados.</li>
                <li><strong>Desestimado:</strong> cierra el reporte porque no se considera válido.</li>
                <li><strong>Ocultar comentario:</strong> rechaza el comentario y marca sus reportes como revisados.</li>
                <li class="reportes-ayuda-peligro"><strong>Eliminar comentario:</strong> borra definitivamente el comentario y todos sus reportes.</li>
                <li><strong>Archivar noticia:</strong> retira la noticia de publicación sin eliminarla.</li>
                <li class="reportes-ayuda-peligro"><strong>Eliminar noticia:</strong> borra la noticia, sus comentarios y todos sus reportes.</li>
                <li><strong>Eliminar reporte:</strong> borra únicamente el reporte, conservando el contenido.</li>
                <li><strong>Vista previa:</strong> muestra el contenido en una ventana emergente.</li>
                <li><strong>Ver contenido:</strong> abre la noticia o el comentario en su página.</li>
            </ul>
        </div>
    </details>

    <?php if ($mensaje !== ''): ?>
        <div class="admin-comentarios-alerta admin-comentarios-alerta-info"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="reportes-resumen-linea" aria-label="Resumen de reportes">
        <span><strong>Total:</strong> <?= (int)$stats['total']; ?></span>
        <span><strong>Pendientes:</strong> <?= (int)$stats['pendientes']; ?></span>
        <span class="confirmado"><strong>Confirmados:</strong> <?= (int)$stats['confirmados']; ?></span>
        <span><strong>Revisados:</strong> <?= (int)$stats['revisados']; ?></span>
        <span><strong>Desestimados:</strong> <?= (int)$stats['desestimados']; ?></span>
        <span><strong>Noticias:</strong> <?= (int)$stats['noticias']; ?></span>
        <span><strong>Comentarios:</strong> <?= (int)$stats['comentarios']; ?></span>
    </div>

    <form method="GET" class="admin-comentarios-filtros">
        <select name="tipo">
            <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : ''; ?>>Todos los tipos</option>
            <option value="noticia" <?= $filtroTipo === 'noticia' ? 'selected' : ''; ?>>Noticias</option>
            <option value="comentario" <?= $filtroTipo === 'comentario' ? 'selected' : ''; ?>>Comentarios</option>
        </select>
        <select name="estado">
            <option value="todos" <?= $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
            <?php foreach ($estadosValidos as $estado): ?>
                <option value="<?= $estado; ?>" <?= $filtroEstado === $estado ? 'selected' : ''; ?>><?= ucfirst($estado); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="motivo">
            <option value="todos">Todos los motivos</option>
            <?php foreach (motivosReporte() as $valor => $etiqueta): ?>
                <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?= $filtroMotivo === $valor ? 'selected' : ''; ?>><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="ambito">
            <option value="todos" <?= $filtroAmbito === 'todos' ? 'selected' : ''; ?>>Todos los ámbitos</option>
            <option value="publico" <?= $filtroAmbito === 'publico' ? 'selected' : ''; ?>>Contenido público</option>
            <option value="privado" <?= $filtroAmbito === 'privado' ? 'selected' : ''; ?>>Contenido privado</option>
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <?php if (!$reportes): ?>
        <p>✅ No hay reportes para los filtros seleccionados.</p>
    <?php else: ?>
        <div class="admin-comentarios-grid">
            <?php foreach ($reportes as $reporte): ?>
                <?php
                $esReportePrivado = (int) $reporte['ambito_privado'] === 1;
                $rutaNoticiaReporte = route(
                    $esReportePrivado ? 'privado_noticia' : 'noticia',
                    ['id' => (int) $reporte['id_noticia']]
                );
                $rutaComentariosReporte = route(
                    $esReportePrivado ? 'privado_comentarios' : 'comentarios_noticia',
                    ['id' => (int) $reporte['id_noticia']]
                );
                ?>
                <article class="admin-comentarios-card <?= htmlspecialchars($reporte['estado'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="admin-comentarios-card-header">
                        <strong><?= $reporte['tipo'] === 'noticia' ? '📰 Noticia' : '💬 Comentario'; ?> #<?= (int)$reporte['id_reporte']; ?></strong>
                        <span><?= (int)$reporte['ambito_privado'] === 1 ? '🔒 Privado' : '🌐 Público'; ?> · <?= htmlspecialchars(ucfirst($reporte['estado']), ENT_QUOTES, 'UTF-8'); ?> · <?= date('d/m/Y H:i', strtotime((string)$reporte['fecha'])); ?></span>
                    </div>
                    <div class="admin-comentarios-card-contenido">
                        <p>
                            <strong>Noticia:</strong>
                            <a href="<?= htmlspecialchars($rutaNoticiaReporte, ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-noticia-link"><?= htmlspecialchars($reporte['noticia_titulo'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </p>
                        <?php if ($reporte['tipo'] === 'comentario'): ?>
                            <p>
                                <strong>Comentario:</strong>
                                <a href="<?= htmlspecialchars($rutaComentariosReporte . '#comentario-' . (int)$reporte['comentario_id'], ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-noticia-link">Ver comentario reportado</a>
                            </p>
                        <?php endif; ?>
                        <button type="button" class="admin-comentarios-btn admin-comentarios-btn-ver-contexto js-abrir-reporte" data-preview="reporte-preview-<?= htmlspecialchars($reporte['tipo'], ENT_QUOTES, 'UTF-8'); ?>-<?= (int)$reporte['id_reporte']; ?>">
                            👁️ Vista previa <?= $reporte['tipo'] === 'noticia' ? 'de la noticia' : 'del comentario'; ?>
                        </button>
                        <p><strong>Autor:</strong> <?= htmlspecialchars((string)($reporte['autor_nombre'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Reportado por:</strong> <?= htmlspecialchars((string)($reporte['reportador_nombre'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Motivo:</strong> <?= htmlspecialchars(etiquetaMotivoReporte((string)$reporte['motivo']), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($reporte['descripcion'])): ?>
                            <p><strong>Descripción:</strong> <?= htmlspecialchars((string)$reporte['descripcion'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="admin-comentarios-card-acciones">
                        <?php foreach ($estadosValidos as $estado): ?>
                            <?php if ($estado !== $reporte['estado']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="tipo" value="<?= $reporte['tipo']; ?>">
                                    <input type="hidden" name="id_reporte" value="<?= (int)$reporte['id_reporte']; ?>">
                                    <input type="hidden" name="accion" value="<?= $estado; ?>">
                                    <button type="submit"><?= ucfirst($estado); ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($reporte['tipo'] === 'comentario'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Ocultar este comentario?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="tipo" value="comentario"><input type="hidden" name="id_reporte" value="<?= (int)$reporte['id_reporte']; ?>"><input type="hidden" name="accion" value="ocultar_comentario">
                                <button type="submit">🙈 Ocultar</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar el comentario y todos sus reportes?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="tipo" value="comentario"><input type="hidden" name="id_reporte" value="<?= (int)$reporte['id_reporte']; ?>"><input type="hidden" name="accion" value="eliminar_comentario">
                                <button type="submit">🗑️ Eliminar comentario</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Archivar esta noticia?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="tipo" value="noticia"><input type="hidden" name="id_reporte" value="<?= (int)$reporte['id_reporte']; ?>"><input type="hidden" name="accion" value="archivar_noticia">
                                <button type="submit">📦 Archivar noticia</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar la noticia, sus comentarios y todos sus reportes?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="tipo" value="noticia"><input type="hidden" name="id_reporte" value="<?= (int)$reporte['id_reporte']; ?>"><input type="hidden" name="accion" value="eliminar_noticia">
                                <button type="submit">🗑️ Eliminar noticia</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar solamente este reporte?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tipo" value="<?= $reporte['tipo']; ?>"><input type="hidden" name="id_reporte" value="<?= (int)$reporte['id_reporte']; ?>"><input type="hidden" name="accion" value="eliminar_reporte">
                            <button type="submit">✖ Eliminar reporte</button>
                        </form>
                        <?php if ($reporte['tipo'] === 'comentario'): ?>
                            <a href="<?= htmlspecialchars($rutaComentariosReporte . '#comentario-' . (int)$reporte['comentario_id'], ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-btn admin-comentarios-btn-ver-contexto">👁️ Ver comentario</a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($rutaNoticiaReporte, ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-btn admin-comentarios-btn-ver-contexto">👁️ Ver noticia</a>
                        <?php endif; ?>
                    </div>
                    <template id="reporte-preview-<?= htmlspecialchars($reporte['tipo'], ENT_QUOTES, 'UTF-8'); ?>-<?= (int)$reporte['id_reporte']; ?>">
                        <h2><?= $reporte['tipo'] === 'noticia' ? 'Noticia reportada' : 'Comentario reportado'; ?></h2>
                        <p><strong>Noticia:</strong> <?= htmlspecialchars($reporte['noticia_titulo'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Autor:</strong> <?= htmlspecialchars((string)($reporte['autor_nombre'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <div><?= sanitizarHtmlComentario((string)$reporte['contenido_reportado']); ?></div>
                        <?php if ($reporte['tipo'] === 'comentario'): ?>
                            <a href="<?= htmlspecialchars($rutaComentariosReporte . '#comentario-' . (int)$reporte['comentario_id'], ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-btn admin-comentarios-btn-ver-contexto">Abrir comentario completo</a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($rutaNoticiaReporte, ENT_QUOTES, 'UTF-8'); ?>" class="admin-comentarios-btn admin-comentarios-btn-ver-contexto">Abrir noticia completa</a>
                        <?php endif; ?>
                    </template>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="reportes-modal" class="reportes-modal" role="dialog" aria-modal="true" aria-labelledby="reportes-modal-titulo">
    <div class="reportes-modal-contenido">
        <div class="reportes-modal-cabecera">
            <strong id="reportes-modal-titulo">Vista previa del contenido reportado</strong>
            <button type="button" class="reportes-modal-cerrar" aria-label="Cerrar">&times;</button>
        </div>
        <div id="reportes-modal-cuerpo" class="reportes-modal-cuerpo"></div>
    </div>
</div>

<script>
const reportesModal = document.getElementById('reportes-modal');
const reportesModalCuerpo = document.getElementById('reportes-modal-cuerpo');

function cerrarVistaPreviaReporte() {
    reportesModal.classList.remove('activo');
    reportesModalCuerpo.replaceChildren();
    document.body.style.overflow = '';
}

document.addEventListener('click', function (event) {
    const botonAbrir = event.target.closest('.js-abrir-reporte');
    if (botonAbrir) {
        const plantilla = document.getElementById(botonAbrir.dataset.preview);
        if (plantilla) {
            reportesModalCuerpo.replaceChildren(plantilla.content.cloneNode(true));
            reportesModal.classList.add('activo');
            document.body.style.overflow = 'hidden';
        }
        return;
    }

    if (event.target === reportesModal || event.target.closest('.reportes-modal-cerrar')) {
        cerrarVistaPreviaReporte();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && reportesModal.classList.contains('activo')) {
        cerrarVistaPreviaReporte();
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
