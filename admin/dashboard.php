<?php
declare(strict_types=1);


/**
 * PANEL DE ADMINISTRACIÓN PRINCIPAL
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/helpers/actividad-usuarios.php';
// Requerir acceso de admin
Permisos::requerirAdmin();

$pdo = db();
$ultimos_usuarios = [];
$ultimas_noticias = [];
$comentarios_pendientes = [];
$usuarios_en_linea = [];
$roles_actividad_validos = ['todos', 'usuario', 'periodista', 'periodista_privado', 'admin'];
$filtro_rol_actividad = (string) ($_GET['actividad_rol'] ?? 'todos');
if (!in_array($filtro_rol_actividad, $roles_actividad_validos, true)) {
    $filtro_rol_actividad = 'todos';
}
$actividad_dias = [];
$nombres_dias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

for ($dias_atras = 6; $dias_atras >= 0; $dias_atras--) {
    $fecha_dia = new DateTimeImmutable("-{$dias_atras} days");
    $actividad_dias[] = [
        'fecha' => $fecha_dia->format('Y-m-d'),
        'etiqueta' => $nombres_dias[(int) $fecha_dia->format('N')],
        'total' => 0,
    ];
}
/** @var array $stats */
try {
    // Estadísticas generales del sistema
    $stats = [
    'total_usuarios' => 0,
    'total_periodistas' => 0,
    'periodistas_pendientes' => 0,
    'total_admins' => 0,
    'usuarios_activos' => 0,
    'usuarios_inactivos' => 0,
    'usuarios_bloqueados' => 0,
    'total_noticias' => 0,
    'noticias_publicadas' => 0,
    'noticias_borrador' => 0,
    'noticias_pendientes' => 0,
    'total_comentarios' => 0,
    'comentarios_aprobados' => 0,
    'comentarios_pendientes' => 0,
    'comentarios_rechazados' => 0,
    'total_categorias' => 0,
    'categorias_activas' => 0,
    'visitas_totales' => 0,
    'noticias_semana' => 0,
    'comentarios_semana' => 0,
    'usuarios_semana' => 0,
];
    
    // Usuarios por rol
    $stats['total_usuarios'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario'")->fetchColumn();
    $stats['total_periodistas'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'periodista'")->fetchColumn();
    $stats['periodistas_pendientes'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'periodista' AND estado = 'pendiente'")->fetchColumn();
    $stats['total_admins'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
    
    // Usuarios por estado
    $stats['usuarios_activos'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'")->fetchColumn();
    $stats['usuarios_inactivos'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'inactivo'")->fetchColumn();
    $stats['usuarios_bloqueados'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'bloqueado'")->fetchColumn();
    
    // Contenido
    $stats['total_noticias'] = $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn();
    $stats['noticias_publicadas'] = $pdo->query("SELECT COUNT(*) FROM noticias WHERE estado = 'publicada'")->fetchColumn();
    $stats['noticias_borrador'] = $pdo->query("SELECT COUNT(*) FROM noticias WHERE estado = 'borrador'")->fetchColumn();
    $stats['noticias_pendientes'] = $pdo->query("SELECT COUNT(*) FROM noticias WHERE estado = 'pendiente'")->fetchColumn();
    
    // Comentarios
    $stats['total_comentarios'] = $pdo->query("SELECT COUNT(*) FROM comentarios")->fetchColumn();
    $stats['comentarios_aprobados'] = $pdo->query("SELECT COUNT(*) FROM comentarios WHERE estado = 'aprobado'")->fetchColumn();
    $stats['comentarios_pendientes'] = $pdo->query("SELECT COUNT(*) FROM comentarios WHERE estado = 'pendiente'")->fetchColumn();
    $stats['comentarios_rechazados'] = $pdo->query("SELECT COUNT(*) FROM comentarios WHERE estado = 'rechazado'")->fetchColumn();
    
    // Categorías
    $stats['total_categorias'] = $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
    $stats['categorias_activas'] = $pdo->query("SELECT COUNT(*) FROM categorias WHERE activa = 1")->fetchColumn();
    
        // Visitas totales
    $stats['visitas_totales'] = (int)($pdo->query("SELECT COALESCE(SUM(visitas), 0) FROM noticias")->fetchColumn());
    
    // Actividad reciente (últimos 7 días)
    $semana = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    // Noticias esta semana
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE fecha_publicacion >= ?");
    $stmt->execute([$semana]);
    $stats['noticias_semana'] = (int)$stmt->fetchColumn();
    
    // Comentarios esta semana
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE fecha_comentario >= ?");
    $stmt->execute([$semana]);
    $stats['comentarios_semana'] = (int)$stmt->fetchColumn();
    
    // Usuarios esta semana
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE fecha_registro >= ?");
    $stmt->execute([$semana]);
    $stats['usuarios_semana'] = (int)$stmt->fetchColumn();

    // Actividad real de noticias y comentarios durante los últimos 7 días.
    $stmt = $pdo->query("
        SELECT fecha, SUM(total) AS total
        FROM (
            SELECT DATE(fecha_publicacion) AS fecha, COUNT(*) AS total
            FROM noticias
            WHERE fecha_publicacion >= CURDATE() - INTERVAL 6 DAY
            GROUP BY DATE(fecha_publicacion)

            UNION ALL

            SELECT DATE(fecha_comentario) AS fecha, COUNT(*) AS total
            FROM comentarios
            WHERE fecha_comentario >= CURDATE() - INTERVAL 6 DAY
            GROUP BY DATE(fecha_comentario)
        ) AS actividad
        GROUP BY fecha
        ORDER BY fecha ASC
    ");
    $actividad_por_fecha = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($actividad_dias as &$actividad_dia) {
        $actividad_dia['total'] = (int) ($actividad_por_fecha[$actividad_dia['fecha']] ?? 0);
    }
    unset($actividad_dia);
    
    // Últimos usuarios registrados
    $stmt = $pdo->query("
        SELECT id_usuario, nombre, email, rol, fecha_registro 
        FROM usuarios 
        ORDER BY fecha_registro DESC 
        LIMIT 5
    ");
    $ultimos_usuarios = $stmt->fetchAll();
    
    // Últimas noticias
    $stmt = $pdo->query("
        SELECT n.id_noticia, n.titulo, n.estado, n.fecha_publicacion, u.nombre as autor
        FROM noticias n
        JOIN usuarios u ON n.id_autor = u.id_usuario
        ORDER BY n.fecha_publicacion DESC 
        LIMIT 5
    ");
    $ultimas_noticias = $stmt->fetchAll();
    
    // Últimos comentarios pendientes
    $stmt = $pdo->query("
        SELECT c.id_comentario, c.contenido, c.fecha_comentario, 
               u.nombre as autor, n.titulo as noticia
        FROM comentarios c
        JOIN usuarios u ON c.id_usuario = u.id_usuario
        JOIN noticias n ON c.id_noticia = n.id_noticia
        WHERE c.estado = 'pendiente'
        ORDER BY c.fecha_comentario DESC
        LIMIT 5
    ");
    $comentarios_pendientes = $stmt->fetchAll();

    $sqlUsuariosEnLinea = "
        SELECT u.id_usuario, u.nombre, u.email, u.rol,
               u.total_conexiones, u.tiempo_conectado_segundos,
               u.ultima_actividad,
               CASE WHEN up.id_usuario IS NULL THEN 0 ELSE 1 END AS es_privado
        FROM usuarios u
        LEFT JOIN usuarios_privados up ON up.id_usuario = u.id_usuario
        WHERE u.estado = 'activo'
          AND u.ultima_actividad >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ";
    $paramsUsuariosEnLinea = [];

    if ($filtro_rol_actividad === 'periodista_privado') {
        $sqlUsuariosEnLinea .= " AND u.rol = 'periodista' AND up.id_usuario IS NOT NULL";
    } elseif ($filtro_rol_actividad === 'periodista') {
        $sqlUsuariosEnLinea .= " AND u.rol = 'periodista' AND up.id_usuario IS NULL";
    } elseif ($filtro_rol_actividad !== 'todos') {
        $sqlUsuariosEnLinea .= ' AND u.rol = ?';
        $paramsUsuariosEnLinea[] = $filtro_rol_actividad;
    }

    $sqlUsuariosEnLinea .= ' ORDER BY u.ultima_actividad DESC LIMIT 12';
    $stmt = $pdo->prepare($sqlUsuariosEnLinea);
    $stmt->execute($paramsUsuariosEnLinea);
    $usuarios_en_linea = $stmt->fetchAll();
    
} catch (Throwable $e) {
    $error = 'No se pudieron cargar todas las estadísticas del panel.';
    registrarErrorInterno('ADMIN.DASHBOARD.ESTADISTICAS', $e);
}

$titulo_pagina = 'Dashboard Admin';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-panel.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('dashboard-roles.css'); ?>">

<div class="panel-admin-container">

    <header class="role-panel-hero role-panel-hero--admin">
        <h1 class="role-panel-hero__title">👑 Panel de Admin</h1>
        <p class="role-panel-hero__description">Supervisa perfiles y contenidos, revisa la seguridad y administra la configuración del portal.</p>
    </header>

    <nav class="role-panel-nav role-panel-hero--admin" aria-label="Funciones del administrador">
        <a href="#admin-resumen">📊 Resumen</a>
        <a href="#admin-actividad">🟢 Actividad</a>
        <a href="#admin-gestion">🗂️ Gestión</a>
        <a href="#admin-sistema">🛠️ Sistema</a>
    </nav>

    <?php require_once __DIR__ . '/../partials/instrucciones.php'; ?>

    <?php

    // Mostrar información de almacenamiento (administrador sin límite).
    $id_usuario = (int) ($_SESSION['usuario_id'] ?? 0);
    include __DIR__ . '/../partials/almacenamiento-info.php';
    ?>

    <h2 class="panel-admin-acciones-titulo role-panel-anchor" id="admin-resumen">Estadísticas</h2>
    <p class="role-panel-section-help">Resumen del estado actual de perfiles, noticias, comentarios y visitas.</p>

    <div class="estadisticas">        
    <?php if (isset($error)): ?>

        <div class="panel-admin-alerta panel-admin-alerta-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>

    <?php endif; ?>

    
    <!-- TARJETAS DE ESTADÍSTICAS PRINCIPALES -->
    <div class="panel-admin-grid-5">
        <div class="panel-admin-card panel-admin-stat-card">
            <div class="panel-admin-stat-icono">👥</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['total_usuarios']; ?></span>

                <span class="panel-admin-stat-etiqueta">Perfiles</span>
            </div>
            <div class="panel-admin-stat-detalle">
                +<?php echo $stats['usuarios_semana']; ?> esta semana

            </div>
        </div>
        
        <div class="panel-admin-card panel-admin-stat-card">
            <div class="panel-admin-stat-icono">✍️</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['total_periodistas']; ?></span>

                <span class="panel-admin-stat-etiqueta">Articulistas</span>
            </div>
        </div>
        
        <div class="panel-admin-card panel-admin-stat-card">
            <div class="panel-admin-stat-icono">📰</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['total_noticias']; ?></span>

                <span class="panel-admin-stat-etiqueta">Noticias</span>
            </div>
            <div class="panel-admin-stat-detalle">
                <?php echo $stats['noticias_semana']; ?> nuevas

            </div>
        </div>
        
        <div class="panel-admin-card panel-admin-stat-card">
            <div class="panel-admin-stat-icono">💬</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['total_comentarios']; ?></span>

                <span class="panel-admin-stat-etiqueta">Comentarios</span>
            </div>
            <div class="panel-admin-stat-detalle">
                <?php echo $stats['comentarios_semana']; ?> nuevos

            </div>
        </div>
    </div>
    
    <!-- SEGUNDA FILA DE TARJETAS -->
    <div class="panel-admin-grid-6"  style="background: none;">
        <div class="panel-admin-card panel-admin-stat-card panel-admin-stat-card-secundaria">
            <div class="panel-admin-stat-icono">👁️</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo number_format($stats['visitas_totales']); ?></span>

                <span class="panel-admin-stat-etiqueta">Visitas totales</span>
            </div>
        </div>
        
        <div class="panel-admin-card panel-admin-stat-card panel-admin-stat-card-secundaria">
            <div class="panel-admin-stat-icono">📊</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['noticias_pendientes']; ?></span>

                <span class="panel-admin-stat-etiqueta">Noticias pendientes</span>
            </div>
        </div>
        
        <div class="panel-admin-card panel-admin-stat-card panel-admin-stat-card-secundaria">
            <div class="panel-admin-stat-icono">⏳</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['comentarios_pendientes']; ?></span>

                <span class="panel-admin-stat-etiqueta">Comentarios pendientes</span>
            </div>
        </div>
        
        <div class="panel-admin-card panel-admin-stat-card panel-admin-stat-card-secundaria">
            <div class="panel-admin-stat-icono">🏷️</div>
            <div class="panel-admin-stat-datos">
                <span class="panel-admin-stat-valor"><?php echo $stats['total_categorias']; ?></span>

                <span class="panel-admin-stat-etiqueta">Categorías</span>
            </div>
        </div>
    </div>

    </div>

    <section class="panel-admin-conectados role-panel-anchor" id="admin-actividad" aria-labelledby="usuarios-conectados-titulo">
        <div class="panel-admin-conectados-cabecera">
            <div>
                <h2 id="usuarios-conectados-titulo">🟢 Usuarios activos ahora</h2>
                <p>Actividad autenticada durante los últimos 5 minutos.</p>
            </div>
            <form method="GET" class="panel-admin-conectados-filtro">
                <label for="actividad-rol">Rol</label>
                <select id="actividad-rol" name="actividad_rol">
                    <option value="todos" <?php echo $filtro_rol_actividad === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="usuario" <?php echo $filtro_rol_actividad === 'usuario' ? 'selected' : ''; ?>>Comentaristas</option>
                    <option value="periodista" <?php echo $filtro_rol_actividad === 'periodista' ? 'selected' : ''; ?>>Articulistas</option>
                    <option value="periodista_privado" <?php echo $filtro_rol_actividad === 'periodista_privado' ? 'selected' : ''; ?>>Colaboradores</option>
                    <option value="admin" <?php echo $filtro_rol_actividad === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
                <button type="submit">Filtrar</button>
            </form>
        </div>

        <?php if (empty($usuarios_en_linea)): ?>
            <p class="panel-admin-sin-datos">No hay usuarios de este rol conectados ahora.</p>
        <?php else: ?>
            <div class="panel-admin-conectados-lista">
                <?php foreach ($usuarios_en_linea as $usuario_conectado): ?>
                    <?php
                    $rol_conectado = match (true) {
                        $usuario_conectado['rol'] === 'admin' => 'Admin',
                        $usuario_conectado['rol'] === 'periodista' && (int) $usuario_conectado['es_privado'] === 1 => 'Colaborador',
                        $usuario_conectado['rol'] === 'periodista' => 'Articulista',
                        default => 'Comentarista',
                    };
                    ?>
                    <article class="panel-admin-conectado-item">
                        <div class="panel-admin-conectado-identidad">
                            <strong><?= htmlspecialchars((string) $usuario_conectado['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?= htmlspecialchars((string) $usuario_conectado['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <span class="panel-admin-conectado-rol"><?= htmlspecialchars($rol_conectado, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><strong><?= number_format((int) $usuario_conectado['total_conexiones'], 0, ',', '.'); ?></strong> conexiones</span>
                        <span><strong><?= htmlspecialchars(formatearTiempoActividad((int) $usuario_conectado['tiempo_conectado_segundos']), ENT_QUOTES, 'UTF-8'); ?></strong> acumulado</span>
                        <span>Actividad <?= htmlspecialchars(tiempoTranscurrido((string) $usuario_conectado['ultima_actividad']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="panel-admin-ver-todos">
            <a href="<?= htmlspecialchars(route('admin_usuarios_logueados', ['rol' => $filtro_rol_actividad, 'conexion' => 'en_linea']), ENT_QUOTES, 'UTF-8'); ?>">
                Abrir listado completo →
            </a>
        </p>
    </section>
    
    <!-- ACCIONES RÁPIDAS -->
    <div class="panel-admin-acciones-rapidas">
        <h2 class="panel-admin-acciones-titulo role-panel-anchor" id="admin-gestion">Gestión Administrativa</h2>
        <p class="role-panel-section-help">Administra perfiles, contenido público y privado, categorías, fuentes y reportes.</p>
        
        <div class="panel-admin-grid-4">
            <a href="<?php echo route('home'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">🌐</span>
                <span class="panel-admin-btn-texto">Ver Sitio</span>
        </a>
        <a href="<?php echo route('admin_usuarios_logueados'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">👥</span>
                <span class="panel-admin-btn-texto">Gestión de Perfiles</span>
            </a>
            
            <a href="<?php echo route('admin_periodistas', ['estado' => 'pendiente']); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">✍️</span>
                <span class="panel-admin-btn-texto">
                    Articulistas pendientes
                    (<?php echo (int) $stats['periodistas_pendientes']; ?>)
                </span>
            </a>
            <a href="<?php echo route('admin_noticias'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">📰</span>
                <span class="panel-admin-btn-texto">Gestión de Noticias</span>
            </a>
            <a href="<?php echo route('admin_categorias'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">🏷️</span>
                <span class="panel-admin-btn-texto">Gestión de Categorías</span>
            </a>
            <a href="<?php echo route('admin_fuentes'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">🗞️</span>
                <span class="panel-admin-btn-texto">Gestión de Fuentes</span>
            </a>
            <a href="<?php echo route('admin_comentarios'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">💬</span>
                <span class="panel-admin-btn-texto">Gestión de Comentarios</span>
            </a>
            <a href="<?php echo route('admin_mensajes'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">✉️</span>
                <span class="panel-admin-btn-texto">Gestión de Mensajes</span>
            </a>
            <a href="<?php echo route('admin_reportes'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">🚩</span>
                <span class="panel-admin-btn-texto">Gestión de Reportes</span>
            </a>
            <a href="<?php echo route('admin_usuarios_privados'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">🔒</span>
                <span class="panel-admin-btn-texto">Colaboradores</span>
            </a>
            <a href="<?php echo route('admin_noticias_privadas_buscar'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">📰</span>
                <span class="panel-admin-btn-texto">Noticias Privadas</span>
            </a>
            <a href="<?php echo route('admin_rss'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">📡</span>
                <span class="panel-admin-btn-texto">Fuentes RSS</span>
            </a>
        </div>
        <h2 class="panel-admin-acciones-titulo role-panel-anchor" id="admin-sistema">Herramientas del Sistema</h2>
        <p class="role-panel-section-help">Configura, diagnostica, protege y mantiene el funcionamiento técnico del portal.</p>
        <div class="panel-admin-grid-4" >
            <a href="<?php echo route('admin_config'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">⚙️</span>
                <span class="panel-admin-btn-texto">Configuración</span>
            </a>
           
            <a href="<?php echo route('ataques'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">🛡️</span>
                <span class="panel-admin-btn-texto">Ataques</span>
            </a>
            
            <a href="<?php echo route('admin_noticias_relacionadas'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">🔗</span>
                <span class="panel-admin-btn-texto">Relaciones</span>
            </a>
            <a href="<?php echo route('actualizar'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">🔄</span>
                <span class="panel-admin-btn-texto">Actualizaciones</span>
            </a>
                        
            <a href="<?php echo route('admin_rutas'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">🗺️</span>
                <span class="panel-admin-btn-texto">Rutas</span>
            </a>
            <a href="<?php echo route('admin_diagnostico'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">🔍</span>
                <span class="panel-admin-btn-texto">Diagnóstico</span>
            </a>
            <a href="<?php echo route('admin_logs'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">📋</span>
                <span class="panel-admin-btn-texto">Logs del Sistema</span>
            </a>
            <a href="<?php echo route('admin_logs_activity'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">🧾</span>
                <span class="panel-admin-btn-texto">Registro de Actividad</span>
            </a>
            <a href="<?php echo route('admin_backups'); ?>" class="panel-admin-btn-accion">

                <span class="panel-admin-btn-icono">💾</span>
                <span class="panel-admin-btn-texto">Backups</span>
            </a>
            <a href="<?php echo route('admin_documentacion'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">📚</span>
                <span class="panel-admin-btn-texto">Documentación</span>
            </a>
            <a href="<?php echo route('admin_perfil'); ?>" class="panel-admin-btn-accion">
                <span class="panel-admin-btn-icono">👤</span>
                <span class="panel-admin-btn-texto">Mi Perfil</span>
            </a>
        </div>
    </div>
    
    <!-- TRES COLUMNAS PARA ACTIVIDAD RECIENTE -->
    <div class="panel-admin-grid-3">
        
        <!-- ÚLTIMOS USUARIOS -->
        <div class="panel-admin-card">
            <h2 class="panel-admin-card-titulo">Últimos registros</h2>
            <?php if (empty($ultimos_usuarios)): ?>

                <p class="panel-admin-sin-datos">No hay usuarios recientes</p>
            <?php else: ?>

                <div class="panel-admin-lista-actividad">
                    <?php foreach ($ultimos_usuarios as $user): ?>

                        <div class="panel-admin-item-actividad">
                            <div class="panel-admin-actividad-icono">
                                <?php

                                $icono = match($user['rol']) {
                                    'admin' => '👑',
                                    'periodista' => '✍️',
                                    default => '👤'
                                };
                                echo $icono;
                                ?>
                            </div>
                            <div class="panel-admin-actividad-info">
                                <strong><?php echo htmlspecialchars($user['nombre']); ?></strong>

                                <small><?php echo htmlspecialchars($user['email']); ?></small>

                            </div>
                            <div class="panel-admin-actividad-fecha">
                                <?php echo tiempoTranscurrido($user['fecha_registro']); ?>

                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
                <p class="panel-admin-ver-todos"><a href="<?php echo route('admin_usuarios_logueados'); ?>">Ver todos →</a></p>
            <?php endif; ?>

        </div>
        
        <!-- ÚLTIMAS NOTICIAS -->
        <div class="panel-admin-card">
            <h2 class="panel-admin-card-titulo">Últimas noticias</h2>
            <?php if (empty($ultimas_noticias)): ?>

                <p class="panel-admin-sin-datos">No hay noticias recientes</p>
            <?php else: ?>

                <div class="panel-admin-lista-actividad">
                    <?php foreach ($ultimas_noticias as $noticia): ?>

                        <div class="panel-admin-item-actividad">
                            <div class="panel-admin-actividad-info">
                                <strong>
                                    <a href="<?php echo route('admin_editar_noticia', ['id' => $noticia['id_noticia']]); ?>">

                                        <?php echo htmlspecialchars(truncarTexto($noticia['titulo'], 40)); ?>

                                    </a>
                                </strong>
                                <small>Por <?php echo htmlspecialchars($noticia['autor']); ?></small>

                            </div>
                            <div class="panel-admin-actividad-estado">
                                <?php

                                $estado_class = match($noticia['estado']) {
                                    'publicada' => 'panel-admin-badge-publicada',
                                    'borrador' => 'panel-admin-badge-borrador',
                                    'pendiente' => 'panel-admin-badge-pendiente',
                                    default => 'panel-admin-badge-archivada'
                                };
                                ?>
                                <span class="panel-admin-badge <?php echo $estado_class; ?>">

                                    <?php echo ucfirst($noticia['estado']); ?>

                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
                <p class="panel-admin-ver-todos"><a href="<?php echo route('admin_noticias'); ?>">Ver todas →</a></p>
            <?php endif; ?>

        </div>
        
        <!-- COMENTARIOS PENDIENTES -->
        <div class="panel-admin-card">
            <h2 class="panel-admin-card-titulo">Comentarios pendientes</h2>
            <?php if (empty($comentarios_pendientes)): ?>

                <p class="panel-admin-sin-datos">No hay comentarios pendientes</p>
            <?php else: ?>

                <div class="panel-admin-lista-actividad">
                    <?php foreach ($comentarios_pendientes as $comentario): ?>

                        <div class="panel-admin-item-actividad">
                            <div class="panel-admin-actividad-info">
                                <strong><?php echo htmlspecialchars($comentario['autor']); ?></strong>

                                <small><?php echo htmlspecialchars(truncarTexto($comentario['contenido'], 50)); ?></small>

                                <small>en: <?php echo htmlspecialchars(truncarTexto($comentario['noticia'], 30)); ?></small>

                            </div>
                            <div class="panel-admin-actividad-fecha">
                                <?php echo tiempoTranscurrido($comentario['fecha_comentario']); ?>

                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
                <p class="panel-admin-ver-todos"><a href="<?php echo route('admin_comentarios'); ?>">Moderar →</a></p>
            <?php endif; ?>

        </div>
        
    </div>
    
    <!-- ACTIVIDAD REAL DE LOS ÚLTIMOS 7 DÍAS -->
    <div class="panel-admin-card panel-admin-grafico-card">
        <h2 class="panel-admin-card-titulo">Actividad de los últimos 7 días</h2>
        <div class="panel-admin-barras-actividad">
            <?php

            $max = max(array_column($actividad_dias, 'total')) ?: 1;
            
            foreach ($actividad_dias as $actividad):
                $altura = ($actividad['total'] / $max) * 150;
            ?>
                <div class="panel-admin-barra-dia">
                    <div class="panel-admin-barra" style="height: <?php echo $altura; ?>px;"></div>

                    <span class="panel-admin-dia"><?php echo htmlspecialchars($actividad['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></span>

                    <span class="panel-admin-valor"><?php echo $actividad['total']; ?></span>

                </div>
            <?php endforeach; ?>

        </div>
        <p class="panel-admin-nota-grafico">Actividad combinada de noticias y comentarios registrados cada día.</p>
    </div>
    
</div>

<?php

$footer = __DIR__ . '/../partials/footer.php';
if (is_file($footer)) {
    require_once $footer;
}
?>
