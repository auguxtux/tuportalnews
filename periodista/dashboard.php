<?php
declare(strict_types=1);


/**
 * DASHBOARD PÚBLICO DE PERIODISTA
 * Muestra exclusivamente noticias y actividad públicas.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/minify.php';

// Verificar que es periodista o admin.
Permisos::requerirPeriodista();

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$es_admin = Permisos::esAdmin();
$tiene_privado = usuarioEsPrivado();

// Estadísticas exclusivas de la actividad pública del periodista.
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(visitas), 0) AS visitas,
        SUM(CASE WHEN estado = 'publicada' THEN 1 ELSE 0 END) AS publicadas,
        SUM(CASE WHEN estado IN ('borrador', 'pendiente') THEN 1 ELSE 0 END) AS pendientes
    FROM noticias
    WHERE id_autor = ? AND privada = 0
");
$stmt->execute([$id_usuario]);
$stats = $stmt->fetch() ?: [
    'total' => 0,
    'visitas' => 0,
    'publicadas' => 0,
    'pendientes' => 0,
];

// Últimas noticias públicas.
$stmt = $pdo->prepare("
    SELECT n.*, c.nombre_categoria
    FROM noticias n
    JOIN categorias c ON n.id_categoria = c.id_categoria
    WHERE n.id_autor = ? AND n.privada = 0
    ORDER BY n.fecha_publicacion DESC
    LIMIT 8
");
$stmt->execute([$id_usuario]);
$ultimas_noticias = $stmt->fetchAll();

// Comentarios recientes en noticias del periodista (últimos 8)
$stmt = $pdo->prepare("
    SELECT c.*, n.titulo as noticia_titulo, n.slug as noticia_slug,
           u.nombre as usuario_nombre, u.avatar as usuario_avatar
    FROM comentarios c
    JOIN noticias n ON c.id_noticia = n.id_noticia
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE n.id_autor = ? AND n.privada = 0 AND c.estado = 'aprobado'
    ORDER BY c.fecha_comentario DESC
    LIMIT 8
");
$stmt->execute([$id_usuario]);
$comentarios_recientes = $stmt->fetchAll();

$titulo_pagina = 'Panel de Articulista';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('periodista-panel.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('dashboard-roles.css'); ?>">

<header class="role-panel-hero role-panel-hero--periodista">
    <h1 class="role-panel-hero__title">✍️ Panel público de Articulista</h1>
    <p class="role-panel-hero__description">Crea y administra noticias públicas, importa contenidos RSS y consulta los comentarios recibidos.</p>
</header>

<nav class="role-panel-nav role-panel-hero--periodista" aria-label="Funciones del articulista">
    <a href="<?php echo route('mis_noticias'); ?>">📰 Mis noticias</a>
    <a href="<?php echo route('nueva_noticia'); ?>">➕ Nueva noticia</a>
    <a href="<?php echo route('importar_rss'); ?>">📡 Importar RSS</a>
    <a href="<?php echo route('periodista_comentarios_recibidos'); ?>">💬 Comentarios</a>
    <a href="<?php echo route('periodista_perfil'); ?>">👤 Mi perfil</a>
    <?php if ($tiene_privado): ?><a href="<?php echo route('privado_dashboard'); ?>">🔒 Panel privado</a><?php endif; ?>
</nav>

<?php require_once __DIR__ . '/../partials/instrucciones.php'; ?>

<?php
// Mostrar información de almacenamiento después del título.
$id_usuario = (int) $_SESSION['usuario_id'];
include __DIR__ . '/../partials/almacenamiento-info.php';
?>


<!-- DASHBOARD PERIODISTA  -->
<div class="panel-periodista-dashboard">
    
    <!-- 1º. HEADER -->
    <div class="panel-periodista-header">
        
        <p class="panel-periodista-subtitulo">Bienvenido/a a tu panel de control</p>
    </div>
    
    <!-- 2º. ALERTA INFORMATIVA -->
    <?php if ($tiene_privado): ?>

        <div class="panel-periodista-alert panel-periodista-alert-info">
            <span class="panel-periodista-alert-icon">🔒</span>
            <span class="panel-periodista-alert-text">Tu actividad pública y privada está separada. Accede al <a href="<?php echo route('privado_dashboard'); ?>"><strong>panel privado</strong></a> para gestionar contenido privado.</span>
            
        </div>
    <?php endif; ?>

    <div class="clean"></div>
    <section class="panel-periodista-stats-grid" aria-label="Resumen de actividad pública">
        <article class="panel-periodista-stat-card"><strong><?php echo (int) $stats['total']; ?></strong><span>Noticias públicas</span></article>
        <article class="panel-periodista-stat-card"><strong><?php echo (int) $stats['publicadas']; ?></strong><span>Publicadas</span></article>
        <article class="panel-periodista-stat-card"><strong><?php echo (int) $stats['pendientes']; ?></strong><span>Borradores o pendientes</span></article>
        <article class="panel-periodista-stat-card"><strong><?php echo number_format((int) $stats['visitas'], 0, ',', '.'); ?></strong><span>Visitas públicas</span></article>
    </section>
    <!-- 3º. ACCIONES RÁPIDAS (MENÚ SENCILLO) -->
<div class="panel-periodista-seccion-acciones">
    <div class="panel-periodista-acciones-grid">
        <a href="<?php echo route('mis_noticias'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-primary">
            <span class="panel-periodista-btn-icono">📰</span>
            <span class="panel-periodista-btn-texto">Mis noticias</span>
        </a>
        <a href="<?php echo route('nueva_noticia'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-success">
            <span class="panel-periodista-btn-icono">➕</span>
            <span class="panel-periodista-btn-texto">Nueva noticia</span>
        </a>
        
        <a href="<?php echo route('importar_rss'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-info">
            <span class="panel-periodista-btn-icono">📡</span>
            <span class="panel-periodista-btn-texto">Importar RSS</span>
        </a>
        <a href="<?php echo route('periodista_comentarios_recibidos'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-secondary">
            <span class="panel-periodista-btn-icono">💬</span>
            <span class="panel-periodista-btn-texto">Comentarios recibidos</span>
        </a>
        <a href="<?php echo route('periodista_perfil'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-secondary">
            <span class="panel-periodista-btn-icono">👤</span>
            <span class="panel-periodista-btn-texto">Mi perfil</span>
        </a>
        <?php if (!$es_admin): ?>
            <a href="<?php echo route('periodista_eliminar_cuenta'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-danger">
                <span class="panel-periodista-btn-icono">🗑️</span>
                <span class="panel-periodista-btn-texto">Eliminar cuenta</span>
            </a>
        <?php endif; ?>
        <?php if ($tiene_privado): ?>
            <a href="<?php echo route('privado_dashboard'); ?>" class="panel-periodista-btn-accion panel-periodista-btn-private">
                <span class="panel-periodista-btn-icono">🔒</span>
                <span class="panel-periodista-btn-texto">Panel privado</span>
            </a>
        <?php endif; ?>
    </div>
</div>

    <!-- 4º. ÚLTIMAS NOTICIAS (TARJETAS RESPONSIVE) -->
    <div class="panel-periodista-seccion-noticias">
        <div class="panel-periodista-seccion-header">
            <h2 class="panel-periodista-seccion-titulo">📰 Últimas noticias</h2>
            <?php if (!empty($ultimas_noticias)): ?>

                <a href="<?php echo route('mis_noticias'); ?>" class="panel-periodista-ver-todas-link">Ver todas →</a>
            <?php endif; ?>

        </div>
        
        <?php if (empty($ultimas_noticias)): ?>

            <div class="panel-periodista-empty-state">
                <p>No tienes noticias aún.</p>
                <a href="<?php echo route('nueva_noticia'); ?>" class="panel-periodista-btn-link">Crea tu primera noticia →</a>
            </div>
        <?php else: ?>

            <div class="panel-periodista-noticias-grid">
                <?php foreach ($ultimas_noticias as $noticia): ?>
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
                    <div class="panel-periodista-noticia-card news-card news-card--vertical<?php echo $claseOrigen . $claseEstado; ?>">
                        <h3 class="panel-periodista-card-titulo news-card__title">
                            <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">
                                <?php echo htmlspecialchars($noticia['titulo']); ?>
                            </a>
                        </h3>

                        <?php $tieneImagen = !empty($noticia['imagen_principal']) || !empty($noticia['imagen_externa']); ?>
                        <div class="panel-periodista-card-imagen news-card__media<?php echo $tieneImagen ? '' : ' panel-periodista-placeholder'; ?>">
                            <?php echo mostrarImagenNoticia(
                                $noticia,
                                'panel-periodista-card-imagen-recurso',
                                '📰',
                                route('noticia', ['id' => $noticia['id_noticia']])
                            ); ?>
                        </div>

                        
                        <div class="panel-periodista-card-contenido news-card__body">
                            <div class="panel-periodista-card-meta news-card__meta news-card__meta--standard">
                                <span class="panel-periodista-fecha">📅 <?php echo date('d/m/Y', strtotime($noticia['fecha_publicacion'])); ?></span>

                                <span class="panel-periodista-categoria">📂 <a href="<?php echo route('categoria', ['id' => (int) $noticia['id_categoria']]); ?>"><?php echo htmlspecialchars($noticia['nombre_categoria']); ?></a></span>

                            </div>
                            
                            <div class="panel-periodista-card-stats">
                                <span>👁️ <?php echo number_format($noticia['visitas']); ?> visitas</span>

                            </div>
                            
                            <div class="panel-periodista-card-acciones news-card__actions">
                                <a href="<?php echo route('editar_noticia', ['id' => $noticia['id_noticia']]); ?>" class="panel-periodista-btn-editar">✏️ Editar</a>

                                <form method="POST" action="<?php echo route('eliminar_noticia'); ?>" class="panel-periodista-eliminar-form" onsubmit="return confirm('¿Eliminar esta noticia?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_noticia" value="<?php echo (int) $noticia['id_noticia']; ?>">
                                    <button type="submit" class="panel-periodista-btn-eliminar">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>
    
    <!-- 5º. COMENTARIOS RECIENTES (TARJETAS RESPONSIVE) -->
    <div class="panel-periodista-seccion-comentarios">
        <div class="panel-periodista-seccion-header">
            <h2 class="panel-periodista-seccion-titulo">💬 Comentarios recientes</h2>
            <a href="<?php echo route('periodista_comentarios_recibidos'); ?>" class="panel-periodista-ver-todas-link">Ver todos →</a>
        </div>
        
        <?php if (empty($comentarios_recientes)): ?>

            <div class="panel-periodista-empty-state">
                <p>No hay comentarios en tus noticias aún.</p>
            </div>
        <?php else: ?>

            <div class="panel-periodista-comentarios-grid">
                <?php foreach ($comentarios_recientes as $comentario): ?>

                    <div class="panel-periodista-comentario-card">
                        <div class="panel-periodista-comentario-header">
                            <div class="panel-periodista-comentario-autor">
                                <?php if (!empty($comentario['usuario_avatar'])): ?>

                                    <img src="<?php echo base_url('uploads/perfiles/' . $comentario['usuario_avatar']); ?>"

                                         alt="<?php echo htmlspecialchars($comentario['usuario_nombre']); ?>"

                                         class="panel-periodista-autor-avatar">
                                <?php else: ?>

                                    <span class="panel-periodista-autor-icono">👤</span>
                                <?php endif; ?>

                                <strong><?php echo htmlspecialchars($comentario['usuario_nombre']); ?></strong>

                            </div>
                            <span class="panel-periodista-comentario-fecha"><?php echo tiempoTranscurrido($comentario['fecha_comentario']); ?></span>

                        </div>
                        
                        <div class="panel-periodista-comentario-contenido">
                            <p><?php echo htmlspecialchars(obtenerPrimerParrafo($comentario['contenido'], 180)); ?></p>

                            <details>
                                <summary>Mostrar comentario completo</summary>
                                <div>
                                    <?php echo sanitizarHtmlComentario((string) $comentario['contenido']); ?>
                                </div>
                            </details>

                        </div>
                        
                        <div class="panel-periodista-comentario-noticia">
                            <a href="<?php echo route('noticia', ['id' => $comentario['id_noticia']]) . '#comentario-' . (int) $comentario['id_comentario']; ?>">

                                📄 <?php echo htmlspecialchars($comentario['noticia_titulo']); ?> · Abrir noticia →

                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>
    
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
