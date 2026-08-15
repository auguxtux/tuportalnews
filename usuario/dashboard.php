<?php
declare(strict_types=1);


/**
 * PANEL DE USUARIO
 * Página principal para usuarios registrados
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

// Requerir login y verificar rol
Permisos::requerirLogin();

// Solo usuarios normales (no periodistas ni admins)
if (!esUsuario()) {
    mensajeFlash('error', 'Acceso no autorizado');
    redireccionar(route('home'));
}

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$usuario = [
    'fecha_registro' => date('Y-m-d'),
    'email' => '',
    'telefono' => '',
    'ciudad' => '',
    'ultimo_acceso' => null,
];
$stats = [
    'total_comentarios' => 0,
    'comentarios_pendientes' => 0,
];
$ultimos_comentarios = [];
$ultimas_noticias = [];

try {
    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $usuario = $stmt->fetch();
    
    // Estadísticas del usuario
    // Total comentarios
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $stats['total_comentarios'] = $stmt->fetchColumn();
    
    // Comentarios pendientes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE id_usuario = :id AND estado = 'pendiente'");
    $stmt->execute([':id' => $id_usuario]);
    $stats['comentarios_pendientes'] = $stmt->fetchColumn();
    
    // Últimos comentarios
    $stmt = $pdo->prepare("
        SELECT c.*, n.titulo as noticia_titulo, n.id_noticia
        FROM comentarios c
        JOIN noticias n ON c.id_noticia = n.id_noticia
        WHERE c.id_usuario = :id AND n.privada = 0
        ORDER BY c.fecha_comentario DESC
        LIMIT 5
    ");
    $stmt->execute([':id' => $id_usuario]);
    $ultimos_comentarios = $stmt->fetchAll();
    
    // Últimas noticias visitadas (requiere tabla de historial)
    // Por ahora, mostramos noticias recientes
    $stmt = $pdo->query("
        SELECT id_noticia, titulo, fecha_publicacion
        FROM noticias
        WHERE estado = 'publicada' AND privada = 0
        ORDER BY fecha_publicacion DESC
        LIMIT 5
    ");
    $ultimas_noticias = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar los datos. Inténtalo de nuevo más tarde.';
    error_log('[PANEL USUARIO] No se pudieron cargar los datos del dashboard.');
}

$titulo_pagina = 'Panel de Comentarista';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('usuario-panel.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('dashboard-roles.css'); ?>">

<div class="panel-usuario-container">

    <header class="role-panel-hero role-panel-hero--usuario">
        <h1 class="role-panel-hero__title">💬 Panel de Comentarista</h1>
        <p class="role-panel-hero__description">Hola, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>. Participa en las noticias, revisa tus comentarios y guarda tus favoritas.</p>
    </header>

    <nav class="role-panel-nav role-panel-hero--usuario" aria-label="Funciones del comentarista">
        <a href="#usuario-resumen">📊 Mi actividad</a>
        <a href="<?php echo route('mis_comentarios'); ?>">💬 Mis comentarios</a>
        <a href="<?php echo route('mis_favoritas'); ?>">❤️ Mis favoritas</a>
        <a href="<?php echo route('usuario_perfil'); ?>">👤 Mi perfil</a>
        <a href="<?php echo route('buscar-avanzado'); ?>">🔍 Buscar noticias</a>
    </nav>

    <?php require_once __DIR__ . '/../partials/instrucciones.php'; ?>

    <?php
    // Mostrar información de almacenamiento después del título.
    $id_usuario = (int) $_SESSION['usuario_id'];
    include __DIR__ . '/../partials/almacenamiento-info.php';
    ?>

    
    <?php if (isset($error)): ?>

        <div class="panel-usuario-alerta panel-usuario-alerta-error"><?php echo $error; ?></div>

    <?php endif; ?>

    
    <!-- RESUMEN DE ACTIVIDAD -->
    <div class="panel-usuario-grid panel-usuario-grid-3 role-panel-anchor" id="usuario-resumen">
        <div class="panel-usuario-card panel-usuario-resumen-item">
            <div class="panel-usuario-resumen-icono">💬</div>
            <div class="panel-usuario-resumen-datos">
                <span class="panel-usuario-resumen-numero"><?php echo $stats['total_comentarios']; ?></span>

                <span class="panel-usuario-resumen-etiqueta">Comentarios totales</span>
            </div>
        </div>
        
        <div class="panel-usuario-card panel-usuario-resumen-item">
            <div class="panel-usuario-resumen-icono">⏳</div>
            <div class="panel-usuario-resumen-datos">
                <span class="panel-usuario-resumen-numero"><?php echo $stats['comentarios_pendientes']; ?></span>

                <span class="panel-usuario-resumen-etiqueta">Pendientes de moderación</span>
            </div>
        </div>
        
        <div class="panel-usuario-card panel-usuario-resumen-item">
            <div class="panel-usuario-resumen-icono">📅</div>
            <div class="panel-usuario-resumen-datos">
                <span class="panel-usuario-resumen-numero"><?php echo formatearFecha($usuario['fecha_registro'], 'd/m/Y'); ?></span>

                <span class="panel-usuario-resumen-etiqueta">Miembro desde</span>
            </div>
        </div>
    </div>
    
    <!-- ACCIONES RÁPIDAS -->
    <div class="panel-usuario-acciones-rapidas">
        <h2 class="panel-usuario-acciones-titulo">Acciones rápidas</h2>
        <div class="panel-usuario-grid panel-usuario-grid-4">
            <a href="<?php echo route('usuario_perfil'); ?>" class="panel-usuario-btn-accion">
                <span class="panel-usuario-btn-icono">👤</span>
                <span class="panel-usuario-btn-texto">Mi Perfil</span>
            </a>
            <a href="<?php echo route('mis_comentarios'); ?>" class="panel-usuario-btn-accion">
                <span class="panel-usuario-btn-icono">💬</span>
                <span class="panel-usuario-btn-texto">Mis Comentarios</span>
            </a>
            <a href="<?php echo route('mis_favoritas'); ?>" class="panel-usuario-btn-accion">
                <span class="panel-usuario-btn-icono">❤️</span>
                <span class="panel-usuario-btn-texto">Mis Favoritas</span>
            </a>
            <a href="<?php echo route('buscar-avanzado'); ?>" class="panel-usuario-btn-accion">

                <span class="panel-usuario-btn-icono">🔍</span>
                <span class="panel-usuario-btn-texto">Buscar Noticias</span>
            </a>
            <a href="<?php echo route('home'); ?>" class="panel-usuario-btn-accion">

                <span class="panel-usuario-btn-icono">📰</span>
                <span class="panel-usuario-btn-texto">Ver Noticias</span>
            </a>
        </div>
    </div>
    
    <!-- DOS COLUMNAS: ÚLTIMOS COMENTARIOS Y NOTICIAS RECIENTES -->
    <div class="panel-usuario-grid panel-usuario-grid-2">
        
        <!-- ÚLTIMOS COMENTARIOS -->
        <div class="panel-usuario-card">
            <h2 class="panel-usuario-card-titulo">Mis últimos comentarios</h2>
            
            <?php if (empty($ultimos_comentarios)): ?>

                <p class="panel-usuario-sin-datos">Aún no has publicado ningún comentario.</p>
                <p class="panel-usuario-sin-datos-link"><a href="<?php echo route('home'); ?>" class="panel-usuario-btn panel-usuario-btn-link">Explorar noticias</a></p>

            <?php else: ?>

                <div class="panel-usuario-lista-comentarios-recientes">
                    <?php foreach ($ultimos_comentarios as $comentario): ?>

                        <div class="panel-usuario-item-comentario">
                            <p class="panel-usuario-comentario-contenido">
                                <?php echo htmlspecialchars(obtenerPrimerParrafo($comentario['contenido'], 80)); ?>

                            </p>
                            <p class="panel-usuario-comentario-meta">
                                En: <a href="<?php echo route('noticia', ['id' => $comentario['id_noticia']]); ?>">

                                    <?php echo htmlspecialchars(truncarTexto($comentario['noticia_titulo'], 40)); ?>

                                </a>
                                <br>
                                <small><?php echo tiempoTranscurrido($comentario['fecha_comentario']); ?></small>

                                <?php if ($comentario['estado'] === 'pendiente'): ?>

                                    <span class="panel-usuario-badge panel-usuario-badge-pendiente">Pendiente</span>
                                <?php endif; ?>

                            </p>
                        </div>
                    <?php endforeach; ?>

                </div>
                
                <?php if ($stats['total_comentarios'] > 5): ?>

                    <p class="panel-usuario-ver-todos">
                        <a href="<?php echo route('mis_comentarios'); ?>" class="panel-usuario-btn panel-usuario-btn-small">Ver todos mis comentarios →</a>
                    </p>
                <?php endif; ?>

            <?php endif; ?>

        </div>
        
        <!-- ÚLTIMAS NOTICIAS -->
        <div class="panel-usuario-card">
            <h2 class="panel-usuario-card-titulo">Últimas noticias</h2>
            
            <?php if (empty($ultimas_noticias)): ?>

                <p class="panel-usuario-sin-datos">No hay noticias disponibles.</p>
            <?php else: ?>

                <div class="panel-usuario-lista-noticias-recientes">
                    <?php foreach ($ultimas_noticias as $noticia): ?>

                        <div class="panel-usuario-item-noticia news-card news-card--compact news-card--public">
                            <a href="<?php echo route('noticia', ['id' => $noticia['id_noticia']]); ?>">

                                <?php echo htmlspecialchars($noticia['titulo']); ?>

                            </a>
                            <small><?php echo tiempoTranscurrido($noticia['fecha_publicacion']); ?></small>

                        </div>
                    <?php endforeach; ?>

                </div>
                
                <p class="panel-usuario-ver-todos">
                    <a href="<?php echo route('listado_noticias'); ?>" class="panel-usuario-btn panel-usuario-btn-small">Ver todas las noticias →</a>

                </p>
            <?php endif; ?>

        </div>
        
    </div>
    
    <!-- INFORMACIÓN DE LA CUENTA -->
    <div class="panel-usuario-card panel-usuario-info-cuenta">
        <h2 class="panel-usuario-card-titulo">Información de mi cuenta</h2>
        
        <div class="panel-usuario-grid panel-usuario-grid-2">
            <div class="panel-usuario-campo-info">
                <span class="panel-usuario-etiqueta">Email:</span>
                <span class="panel-usuario-valor"><?php echo htmlspecialchars($usuario['email']); ?></span>

            </div>
            
            <div class="panel-usuario-campo-info">
                <span class="panel-usuario-etiqueta">Teléfono:</span>
                <span class="panel-usuario-valor"><?php echo htmlspecialchars($usuario['telefono']); ?></span>

            </div>
            
            <div class="panel-usuario-campo-info">
                <span class="panel-usuario-etiqueta">Ciudad:</span>
                <span class="panel-usuario-valor"><?php echo htmlspecialchars($usuario['ciudad']); ?></span>

            </div>
            
            <div class="panel-usuario-campo-info">
                <span class="panel-usuario-etiqueta">Último acceso:</span>
                <span class="panel-usuario-valor"><?php echo $usuario['ultimo_acceso'] ? formatearFecha($usuario['ultimo_acceso']) : 'Primera vez'; ?></span>

            </div>
        </div>
        
        <p class="panel-usuario-accion-cuenta">
            <a href="<?php echo route('usuario_perfil'); ?>" class="panel-usuario-btn panel-usuario-btn-principal">Editar mi perfil</a>
        </p>
    </div>

    <section class="panel-usuario-zona-peligro" aria-labelledby="panel-usuario-zona-peligro-titulo">
        <h2 id="panel-usuario-zona-peligro-titulo">Gestión de la cuenta</h2>
        <p>La eliminación de la cuenta es permanente y requiere confirmación.</p>
        <a href="<?php echo route('usuario_eliminar_cuenta'); ?>" class="panel-usuario-btn panel-usuario-btn-danger">
            🗑️ Eliminar mi cuenta
        </a>
    </section>
    
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
