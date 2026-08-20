<?php
declare(strict_types=1);

/**
 * LISTADO DE NOTICIAS FAVORITAS DEL USUARIO
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';

Permisos::requerirLogin();

$pdo = db();
$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$rutaPanel = match ($_SESSION['usuario_rol'] ?? '') {
    'admin' => 'admin',
    'periodista' => usuarioEsPrivado()
        ? 'privado_dashboard'
        : 'periodista_dashboard',
    default => 'usuario_dashboard',
};
$favoritas = [];
$error = null;

try {
    $stmt = $pdo->prepare(
        "SELECT
            n.id_noticia,
            n.titulo,
            n.fecha_publicacion,
            c.nombre_categoria,
            u.nombre AS autor_nombre,
            f.fecha AS fecha_guardado
         FROM favoritos f
         INNER JOIN noticias n ON n.id_noticia = f.id_noticia
         INNER JOIN categorias c ON c.id_categoria = n.id_categoria
         INNER JOIN usuarios u ON u.id_usuario = n.id_autor
         WHERE f.id_usuario = ?
           AND n.estado = 'publicada'
           AND n.privada = 0
         ORDER BY f.fecha DESC"
    );
    $stmt->execute([$idUsuario]);
    $favoritas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    registrarErrorInterno('USUARIO.FAVORITAS.CARGA', $e);
    $error = 'No se pudieron cargar tus noticias favoritas.';
}

$titulo_pagina = 'Mis Favoritas';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('usuario-panel.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">

<div class="panel-usuario-container">
    <h1 class="panel-usuario-titulo">❤️ Mis Favoritas</h1>

    <?php if ($error !== null): ?>
        <div class="panel-usuario-alerta panel-usuario-alerta-error">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php elseif ($favoritas === []): ?>
        <div class="panel-usuario-card">
            <p class="panel-usuario-sin-datos">Todavía no has guardado ninguna noticia favorita.</p>
            <p class="panel-usuario-sin-datos-link">
                <a href="<?php echo base_url(); ?>" class="panel-usuario-btn panel-usuario-btn-link">
                    Explorar noticias
                </a>
            </p>
        </div>
    <?php else: ?>
        <div class="panel-usuario-card">
            <div class="panel-usuario-lista-noticias-recientes">
                <?php foreach ($favoritas as $noticia): ?>
                    <div class="panel-usuario-item-noticia news-card news-card--horizontal news-card--compact news-card--public">
                        <a href="<?php echo htmlspecialchars(route('noticia', ['id' => (int) $noticia['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) $noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <small>
                            <?php echo htmlspecialchars((string) $noticia['nombre_categoria'], ENT_QUOTES, 'UTF-8'); ?>
                            · <?php echo htmlspecialchars((string) $noticia['autor_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            · Guardada <?php echo tiempoTranscurrido((string) $noticia['fecha_guardado']); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <p class="panel-usuario-accion-cuenta">
        <a href="<?php echo route($rutaPanel); ?>" class="panel-usuario-btn panel-usuario-btn-principal">
            ← Volver a Mi Panel
        </a>
    </p>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
