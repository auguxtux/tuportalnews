<?php

declare(strict_types=1);

/**
 * DIAGNÓSTICO DE RUTAS Y CONFIGURACIÓN DEL SISTEMA
 * Solo administradores
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();

// Estadísticas
$stats = [];
try {
    $stats = [
        'total_noticias' => (int) $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn(),
        'total_usuarios' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
        'total_comentarios' => (int) $pdo->query("SELECT COUNT(*) FROM comentarios")->fetchColumn(),
        'total_categorias' => (int) $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn(),
    ];
} catch (Throwable $e) {
    $stats['error'] = 'No se pudieron cargar las estadísticas.';
    registrarErrorInterno('ADMIN.RUTAS.ESTADISTICAS', $e);
}

// Datos para enlaces de ejemplo
$noticias_ejemplo = [];
$categorias = [];

try {
    $noticias_ejemplo = $pdo->query("SELECT id_noticia, slug, titulo FROM noticias WHERE estado = 'publicada' LIMIT 5")->fetchAll();
    $categorias = $pdo->query("SELECT id_categoria, slug_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria LIMIT 10")->fetchAll();
} catch (Throwable $e) {
    // Silencioso
}

// Obtener rutas del sistema
$routes = [];
if (file_exists(INCLUDES_PATH . 'routes.php')) {
    $routes = $GLOBALS['routes'] ?? [];
    if (!is_array($routes)) {
        $routes = [];
    }
}

$titulo_pagina = 'Diagnóstico de Rutas';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('admin-rutas.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="rutas-container">
    <div class="rutas-header">
        <h1><span>🗺️</span> Diagnóstico de Rutas y Configuración</h1>
        <p>Herramienta para verificar la estructura de directorios, rutas y archivos del sistema</p>
    </div>

    <div class="rutas-content">

        <div class="info-grid">
            <div class="info-card success">
                <h4>📁 Ubicación</h4>
                <div class="value"><?= htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="info-card success">
                <h4>📄 Configuración</h4>
                <div class="value">✅ Cargada correctamente</div>
            </div>
            <div class="info-card success">
                <h4>🗄️ Base de datos</h4>
                <div class="value">✅ Conexión establecida</div>
            </div>
        </div>

        <?php if (!empty($stats) && !isset($stats['error'])): ?>
        <div class="rutas-card">
            <div class="rutas-card-header"><h2>📊 Estadísticas del sistema</h2></div>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?= number_format($stats['total_noticias']); ?></div><div class="stat-label">📰 Noticias</div></div>
                <div class="stat-card"><div class="stat-number"><?= number_format($stats['total_usuarios']); ?></div><div class="stat-label">👥 Usuarios</div></div>
                <div class="stat-card"><div class="stat-number"><?= number_format($stats['total_comentarios']); ?></div><div class="stat-label">💬 Comentarios</div></div>
                <div class="stat-card"><div class="stat-number"><?= number_format($stats['total_categorias']); ?></div><div class="stat-label">📁 Categorías</div></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="rutas-grid">
            <div class="rutas-grid-card">
                <div class="rutas-grid-card-header"><h3>📄 Páginas públicas</h3></div>
                <ul class="rutas-lista">
                    <li><a href="<?= htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">🏠 Inicio</a></li>
                    <li><a href="<?= htmlspecialchars(route('ultimas'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📰 Últimas</a></li>
                    <li><a href="<?= htmlspecialchars(route('populares'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">🔥 Populares</a></li>
                    <li><a href="<?= htmlspecialchars(route('login'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">🔑 Login</a></li>
                    <li><a href="<?= htmlspecialchars(route('registro'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📝 Registro</a></li>
                    <li><a href="<?= htmlspecialchars(route('contacto'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📧 Contacto</a></li>
                </ul>
            </div>

            <div class="rutas-grid-card">
                <div class="rutas-grid-card-header"><h3>📰 Noticias y categorías</h3></div>
                <ul class="rutas-lista">
                    <?php foreach ($noticias_ejemplo as $n): ?>
                        <li><a href="<?= htmlspecialchars(route('noticia', ['id' => (int) $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📖 <?= htmlspecialchars(mb_substr((string) $n['titulo'], 0, 40), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endforeach; ?>
                    <?php foreach ($categorias as $cat): ?>
                        <li><a href="<?= htmlspecialchars(route('categoria', ['id' => (int) $cat['id_categoria']]), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📁 <?= htmlspecialchars((string) $cat['nombre_categoria'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="rutas-grid-card">
                <div class="rutas-grid-card-header"><h3>👤 Usuario</h3></div>
                <ul class="rutas-lista">
                    <li><a href="<?= htmlspecialchars(route('usuario_dashboard'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📊 Dashboard</a></li>
                    <li><a href="<?= htmlspecialchars(route('usuario_perfil'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">👤 Perfil</a></li>
                </ul>
            </div>

            <div class="rutas-grid-card">
                <div class="rutas-grid-card-header"><h3>✍️ Articulista</h3></div>
                <ul class="rutas-lista">
                    <li><a href="<?= htmlspecialchars(route('periodista_dashboard'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📊 Dashboard</a></li>
                    <li><a href="<?= htmlspecialchars(route('mis_noticias'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📰 Mis noticias</a></li>
                </ul>
            </div>

            <div class="rutas-grid-card">
                <div class="rutas-grid-card-header"><h3>👑 Admin</h3></div>
                <ul class="rutas-lista">
                    <li><a href="<?= htmlspecialchars(route('admin'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📊 Dashboard</a></li>
                    <li><a href="<?= htmlspecialchars(route('admin_noticias'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📰 Noticias</a></li>
                    <li><a href="<?= htmlspecialchars(route('admin_categorias'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">📁 Categorías</a></li>
                    <li><a href="<?= htmlspecialchars(route('admin_config'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">⚙️ Configuración</a></li>
                </ul>
            </div>
        </div>

        <?php if (!empty($routes)): ?>
        <div class="rutas-card">
            <div class="rutas-card-header"><h2>📋 Tabla de rutas</h2></div>
            <div class="tabla-responsive">
                <table class="rutas-tabla">
                    <thead><tr><th>Nombre</th><th>Archivo</th></tr></thead>
                    <tbody>
                        <?php foreach ($routes as $nombre => $archivo): ?>
                            <tr><td><?= htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8'); ?></td><td><code><?= htmlspecialchars((string) $archivo, ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="rutas-card">
            <div class="rutas-card-header"><h2>📁 Estructura de archivos</h2></div>
            <div class="estructura-archivos">
                <?php
                $directorios = ['admin', 'ajax', 'assets', 'includes', 'partials', 'periodista', 'privado', 'public', 'usuario', 'uploads'];
                foreach ($directorios as $dir):
                    $existe = file_exists(__DIR__ . '/../' . $dir);
                ?>
                <div class="estructura-item">
                    <span><?= $existe ? '📁' : '❌'; ?></span>
                    <span class="<?= $existe ? 'existe' : 'no-existe'; ?>">/<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>/</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
