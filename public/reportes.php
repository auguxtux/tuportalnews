<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/modules/listado-reportes-confirmados.php';

$vistaPrivada = defined('VISTA_REPORTES_PRIVADOS') && VISTA_REPORTES_PRIVADOS === true;

if ($vistaPrivada && !usuarioEsPrivado() && !Permisos::esAdmin()) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$tipo = in_array($_GET['tipo'] ?? 'todos', ['todos', 'noticia', 'comentario'], true)
    ? (string) ($_GET['tipo'] ?? 'todos')
    : 'todos';
$reportes = [];
$error = null;

try {
    $reportes = listarReportesConfirmados(db(), $vistaPrivada, $tipo);
} catch (Throwable $e) {
    $error = 'No se pudo cargar el listado de reportes confirmados.';
    registrarErrorInterno('PUBLIC.REPORTES.LISTADO', $e);
}

$titulo_pagina = $vistaPrivada ? 'Reportes privados confirmados' : 'Reportes confirmados';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('listado-reportes.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="listado-reportes">
    <header class="listado-reportes-cabecera <?= $vistaPrivada ? 'es-privado' : ''; ?>">
        <h1><?= $vistaPrivada ? '🔒 Reportes privados confirmados' : '🚩 Reportes confirmados'; ?></h1>
        <p>Resumen anónimo de los reportes validados por el equipo de administración.</p>
    </header>

    <nav class="listado-reportes-filtros" aria-label="Filtrar reportes confirmados">
        <?php foreach (['todos' => 'Todos', 'noticia' => 'Noticias', 'comentario' => 'Comentarios'] as $valor => $etiqueta): ?>
            <a class="<?= $tipo === $valor ? 'activo' : ''; ?>" href="<?= htmlspecialchars(route($vistaPrivada ? 'privado_reportes' : 'reportes_publicos', ['tipo' => $valor]), ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($error !== null): ?>
        <p class="listado-reportes-mensaje error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif ($reportes === []): ?>
        <p class="listado-reportes-mensaje">No hay reportes confirmados con este filtro.</p>
    <?php else: ?>
        <div class="listado-reportes-grid">
            <?php foreach ($reportes as $reporte): ?>
                <?php
                $esComentario = $reporte['tipo'] === 'comentario';
                $rutaContenido = $esComentario
                    ? route($vistaPrivada ? 'privado_comentarios' : 'comentarios_noticia', ['id' => (int) $reporte['id_noticia']]) . '#comentario-' . (int) $reporte['id_comentario']
                    : route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => (int) $reporte['id_noticia']]);
                ?>
                <article class="listado-reporte-card <?= $esComentario ? 'es-comentario' : 'es-noticia'; ?>">
                    <div class="listado-reporte-tipo"><?= $esComentario ? '💬 Comentario' : '📰 Noticia'; ?></div>
                    <h2><a href="<?= htmlspecialchars($rutaContenido, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $reporte['titulo'], ENT_QUOTES, 'UTF-8'); ?></a></h2>
                    <?php if ($esComentario): ?>
                        <p class="listado-reporte-extracto"><?= htmlspecialchars(truncarTexto(strip_tags((string) $reporte['contenido']), 180), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <p><strong><?= (int) $reporte['total_reportes']; ?> reporte<?= (int) $reporte['total_reportes'] === 1 ? '' : 's'; ?> confirmado<?= (int) $reporte['total_reportes'] === 1 ? '' : 's'; ?></strong></p>
                    <p>Motivos: <?= htmlspecialchars(implode(', ', $reporte['motivos_etiquetas']), ENT_QUOTES, 'UTF-8'); ?>.</p>
                    <a class="listado-reporte-abrir" href="<?= htmlspecialchars($rutaContenido, ENT_QUOTES, 'UTF-8'); ?>">Ver contenido →</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
