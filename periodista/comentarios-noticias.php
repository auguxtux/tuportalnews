<?php
declare(strict_types=1);

/**
 * Comentarios recibidos en las noticias públicas del articulista.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirPeriodista();

$pdo = db();
$idAutor = (int) ($_SESSION['usuario_id'] ?? 0);
$idNoticia = max(0, (int) ($_GET['noticia'] ?? 0));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 20;

$stmt = $pdo->prepare(
    "SELECT id_noticia, titulo
     FROM noticias
     WHERE id_autor = ? AND privada = 0
     ORDER BY fecha_publicacion DESC"
);
$stmt->execute([$idAutor]);
$noticias = $stmt->fetchAll();
$idsNoticias = array_map('intval', array_column($noticias, 'id_noticia'));

if ($idNoticia > 0 && !in_array($idNoticia, $idsNoticias, true)) {
    $idNoticia = 0;
}

$condiciones = [
    'n.id_autor = :autor',
    'n.privada = 0',
    "c.estado = 'aprobado'",
];
$parametros = [':autor' => $idAutor];

if ($idNoticia > 0) {
    $condiciones[] = 'n.id_noticia = :noticia';
    $parametros[':noticia'] = $idNoticia;
}

$where = implode(' AND ', $condiciones);
$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM comentarios c
     INNER JOIN noticias n ON n.id_noticia = c.id_noticia
     WHERE {$where}"
);
$stmt->execute($parametros);
$totalComentarios = (int) $stmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalComentarios / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare(
    "SELECT c.id_comentario, c.id_noticia, c.contenido,
            c.fecha_comentario, u.nombre, u.avatar,
            n.titulo AS noticia_titulo
     FROM comentarios c
     INNER JOIN noticias n ON n.id_noticia = c.id_noticia
     INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
     WHERE {$where}
     ORDER BY n.titulo ASC, c.fecha_comentario DESC
     LIMIT :limite OFFSET :offset"
);
foreach ($parametros as $clave => $valor) {
    $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
}
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$comentarios = $stmt->fetchAll();

$titulo_pagina = 'Comentarios recibidos';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('periodista-panel.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('periodista-comentarios-noticias.css'); ?>">

<main class="panel-periodista-dashboard comentarios-recibidos-page">
    <div class="panel-periodista-seccion-header">
        <div style="width: 100%;">
            <h1 class="panel-periodista-titulo">💬 Comentarios recibidos</h1>
            <p class="panel-periodista-subtitulo">Comentarios publicados en tus noticias públicas.</p>
        </div>
        <a href="<?php echo route('periodista_dashboard'); ?>" class="panel-periodista-ver-todas-link">← Volver al panel</a>
    </div>

    <section class="panel-periodista-seccion-comentarios" aria-label="Comentarios recibidos">
        <form method="GET" class="comentarios-recibidos-filtros">
            <label for="comentarios-noticia"><strong>Noticia</strong></label>
            <select id="comentarios-noticia" name="noticia">
                <option value="0">Todas mis noticias públicas</option>
                <?php foreach ($noticias as $noticia): ?>
                    <option value="<?php echo (int) $noticia['id_noticia']; ?>" <?php echo $idNoticia === (int) $noticia['id_noticia'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="panel-periodista-btn-accion panel-periodista-btn-primary">Filtrar comentarios</button>
        </form>

        <p class="comentarios-recibidos-resumen"><strong><?php echo $totalComentarios; ?></strong> comentario(s)</p>

        <?php if ($comentarios === []): ?>
            <div class="panel-periodista-empty-state">
                <p>No hay comentarios aprobados para mostrar.</p>
            </div>
        <?php else: ?>
            <div class="panel-periodista-comentarios-grid">
                <?php $noticiaAnterior = 0; ?>
                <?php foreach ($comentarios as $comentario): ?>
                    <?php if ($noticiaAnterior !== (int) $comentario['id_noticia']): ?>
                        <?php $noticiaAnterior = (int) $comentario['id_noticia']; ?>
                        <h2 class="panel-periodista-seccion-titulo">📰 <?php echo htmlspecialchars((string) $comentario['noticia_titulo'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php endif; ?>

                    <article class="panel-periodista-comentario-card" id="comentario-<?php echo (int) $comentario['id_comentario']; ?>">
                        <div class="panel-periodista-comentario-header">
                            <div class="panel-periodista-comentario-autor">
                                <img
                                    class="panel-periodista-autor-avatar"
                                    src="<?php echo base_url('uploads/perfiles/' . ($comentario['avatar'] ?: 'default-avatar.png')); ?>"
                                    alt=""
                                >
                                <strong><?php echo htmlspecialchars((string) $comentario['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <time class="panel-periodista-comentario-fecha"><?php echo tiempoTranscurrido((string) $comentario['fecha_comentario']); ?></time>
                        </div>
                        <div class="panel-periodista-comentario-contenido">
                            <?php echo sanitizarHtmlComentario((string) $comentario['contenido']); ?>
                        </div>
                        <div class="panel-periodista-comentario-noticia">
                            <a href="<?php echo route('comentarios_noticia', ['id' => (int) $comentario['id_noticia']]) . '#comentario-' . (int) $comentario['id_comentario']; ?>">
                                Abrir comentario →
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPaginas > 1): ?>
            <nav class="panel-periodista-seccion-header comentarios-recibidos-paginacion" aria-label="Paginación">
                <?php if ($pagina > 1): ?>
                    <a class="panel-periodista-ver-todas-link" href="<?php echo route('periodista_comentarios_recibidos', ['noticia' => $idNoticia, 'pagina' => $pagina - 1]); ?>">← Anterior</a>
                <?php endif; ?>
                <span>Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?></span>
                <?php if ($pagina < $totalPaginas): ?>
                    <a class="panel-periodista-ver-todas-link" href="<?php echo route('periodista_comentarios_recibidos', ['noticia' => $idNoticia, 'pagina' => $pagina + 1]); ?>">Siguiente →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
