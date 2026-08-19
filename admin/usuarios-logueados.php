<?php
declare(strict_types=1);


/**
 * ADMIN - USUARIOS DEL SISTEMA
 * Vista de tarjetas responsive
 * Con confirmación diferenciada para desactivar o eliminar definitivamente
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/logs.php';
require_once __DIR__ . '/../includes/eliminar-cuenta.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/helpers/actividad-usuarios.php';
Permisos::requerirAdmin();

$pdo = db();
$roles_filtro_validos = ['todos', 'usuario', 'admin', 'periodista', 'periodista_privado'];
$estados_filtro_validos = ['todos', 'activo', 'inactivo', 'bloqueado', 'pendiente'];
$conexiones_filtro_validas = ['todos', 'en_linea', 'desconectado'];

// ============================================
// PROCESAR ACCIONES POR POST (confirmación modal)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion_post = isset($_POST['accion']) ? $_POST['accion'] : '';
    $id_post = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $confirmar = isset($_POST['confirmar']) ? (int)$_POST['confirmar'] : 0;

    $rol_redirect = (string)($_POST['rol_filtro'] ?? 'todos');
    if (!in_array($rol_redirect, $roles_filtro_validos, true)) {
        $rol_redirect = 'todos';
    }
    $estado_redirect = (string)($_POST['estado_filtro'] ?? 'todos');
    if (!in_array($estado_redirect, $estados_filtro_validos, true)) {
        $estado_redirect = 'todos';
    }
    $conexion_redirect = (string) ($_POST['conexion_filtro'] ?? 'todos');
    if (!in_array($conexion_redirect, $conexiones_filtro_validas, true)) {
        $conexion_redirect = 'todos';
    }
    $pagina_redirect = max(1, (int)($_POST['pagina_filtro'] ?? 1));

    $redirect_url = route('admin_usuarios_logueados', [
        'rol' => $rol_redirect,
        'estado' => $estado_redirect,
        'conexion' => $conexion_redirect,
        'buscar' => (string) ($_POST['buscar_filtro'] ?? ''),
        'pagina' => $pagina_redirect,
    ]);

    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.'];
        header('Location: ' . $redirect_url);
        exit;
    }

    if (
        $id_post === (int) ($_SESSION['usuario_id'] ?? 0)
        && in_array($accion_post, ['desactivar', 'eliminar'], true)
    ) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'No puedes desactivar ni eliminar tu propia cuenta desde este panel.'];
        header('Location: ' . $redirect_url);
        exit;
    }

    if (in_array($accion_post, ['activar', 'toggle_privado', 'cambiar_rol'], true) && $id_post) {
        $stmt = $pdo->prepare("SELECT email, rol FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_post]);
        $usuario_data = $stmt->fetch();
        $email_usuario = $usuario_data['email'] ?? '';
        $rol_actual = $usuario_data['rol'] ?? '';

        try {
            if ($accion_post === 'activar') {
                $pdo->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?")->execute([$id_post]);
                registrarAdminAccionUsuario('activar', $id_post, $email_usuario, 'Usuario activado');
                $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Usuario activado'];
            } elseif ($accion_post === 'toggle_privado') {
                if ($rol_actual !== 'periodista') {
                    throw new RuntimeException('El acceso privado solo puede asignarse a articulistas.');
                }

                $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios_privados WHERE id_usuario = ?");
                $stmt->execute([$id_post]);
                if ($stmt->fetch()) {
                    $resultado = reasignarColaboradorAArticulista($pdo, $id_post);
                    if (!$resultado['success']) {
                        throw new RuntimeException($resultado['message']);
                    }
                    registrarAdminAccionUsuario('quitar_privado', $id_post, $email_usuario, 'Permiso privado revocado');
                    $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '🔓 ' . $resultado['message']];
                } else {
                    $pdo->prepare("INSERT INTO usuarios_privados (id_usuario, activo, fecha_alta) VALUES (?, 1, NOW())")->execute([$id_post]);
                    registrarAdminAccionUsuario('dar_privado', $id_post, $email_usuario, 'Permiso privado otorgado');
                    $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '🔒 Permiso privado OTORGADO'];
                }
            } elseif ($accion_post === 'cambiar_rol') {
                $nuevo_rol = $_POST['nuevo_rol'] ?? '';
                if (in_array($nuevo_rol, ['usuario', 'periodista', 'admin'], true)) {
                    $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id_usuario = ?")->execute([$nuevo_rol, $id_post]);
                    registrarAdminCambioRol($id_post, $email_usuario, $rol_actual, $nuevo_rol);
                    $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Rol actualizado correctamente'];
                }
            }
        } catch (Throwable $e) {
            registrarErrorInterno('ADMIN.USUARIOS.PERMISOS', $e);
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'Error al procesar la acción'];
        }

        header('Location: ' . $redirect_url);
        exit;
    }
    
    if ($confirmar && $accion_post && $id_post) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_post]);
            $usuario_eliminar = $stmt->fetch();
            $email_usuario = $usuario_eliminar['email'] ?? '';

            if (!$usuario_eliminar) {
                throw new RuntimeException('Usuario no encontrado');
            }

            if ($accion_post === 'desactivar') {
                $stmt = $pdo->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?");
                $stmt->execute([$id_post]);
                registrarAdminAccionUsuario('desactivar', $id_post, $email_usuario, 'Usuario desactivado');
                $mensaje = '🔓 Usuario desactivado. Todo su contenido se conserva.';
            } elseif ($accion_post === 'eliminar') {
                $resultado = eliminarCuentaCompleta($id_post, $pdo);
                if (!$resultado['success']) {
                    throw new RuntimeException($resultado['message']);
                }
                registrarAdminAccionUsuario('eliminar', $id_post, $email_usuario, 'Usuario eliminado permanentemente');
                $mensaje = '🗑️ Usuario y todo su contenido asociado eliminados permanentemente.';
            } else {
                throw new RuntimeException('Acción no permitida');
            }

            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => $mensaje];
            
        } catch (Throwable $e) {
            registrarErrorInterno('ADMIN.USUARIOS.GESTION', $e);
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'No se pudo procesar la acción del usuario.'];
        }
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

// ============================================
// PROCESAR POR GET SOLO LA APERTURA DEL MODAL
// ============================================
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mensaje_flash = null;

// Preservar filtros para redirección
$filtro_rol_guardar = (string)($_GET['rol'] ?? 'todos');
if (!in_array($filtro_rol_guardar, $roles_filtro_validos, true)) {
    $filtro_rol_guardar = 'todos';
}
$filtro_estado_guardar = (string)($_GET['estado'] ?? 'todos');
if (!in_array($filtro_estado_guardar, $estados_filtro_validos, true)) {
    $filtro_estado_guardar = 'todos';
}
$filtro_conexion_guardar = (string) ($_GET['conexion'] ?? 'todos');
if (!in_array($filtro_conexion_guardar, $conexiones_filtro_validas, true)) {
    $filtro_conexion_guardar = 'todos';
}
$filtro_busqueda_guardar = (string) ($_GET['buscar'] ?? '');
$pagina_guardar = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

if (in_array($accion, ['desactivar', 'eliminar'], true) && $id) {
    header('Location: ' . route('admin_usuarios_logueados', [
        'modal' => $accion,
        'id' => $id,
        'rol' => $filtro_rol_guardar,
        'estado' => $filtro_estado_guardar,
        'conexion' => $filtro_conexion_guardar,
        'buscar' => $filtro_busqueda_guardar,
        'pagina' => $pagina_guardar,
    ]));
    exit;
}

// ============================================
// RECUPERAR MENSAJE FLASH
// ============================================
if (isset($_SESSION['mensaje_flash'])) {
    $mensaje_flash = $_SESSION['mensaje_flash'];
    unset($_SESSION['mensaje_flash']);
}

// ============================================
// FILTROS
// ============================================
$filtro_rol = is_string($_GET['rol'] ?? null) ? $_GET['rol'] : 'todos';
$filtro_estado = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : 'todos';
$filtro_conexion = is_string($_GET['conexion'] ?? null) ? $_GET['conexion'] : 'todos';
$filtro_busqueda = is_string($_GET['buscar'] ?? null) ? trim($_GET['buscar']) : '';
$pagina = is_scalar($_GET['pagina'] ?? null) ? max(1, (int) $_GET['pagina']) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

if (!in_array($filtro_rol, $roles_filtro_validos, true)) {
    $filtro_rol = 'todos';
}
if (!in_array($filtro_estado, $estados_filtro_validos, true)) {
    $filtro_estado = 'todos';
}
if (!in_array($filtro_conexion, $conexiones_filtro_validas, true)) {
    $filtro_conexion = 'todos';
}

$es_usuario_normal = ($filtro_rol === 'usuario');

// ============================================
// CONSTRUIR CONSULTA WHERE
// ============================================
$where = [];
$params = [];

if ($filtro_rol === 'todos') {
    // Sin condición de rol: se muestran todos los perfiles.
} elseif ($es_usuario_normal) {
    $where[] = "u.rol = 'usuario'";
} elseif ($filtro_rol === 'periodista_privado') {
    $where[] = "u.rol = 'periodista' AND up.id_usuario IS NOT NULL";
} elseif ($filtro_rol === 'periodista') {
    $where[] = "u.rol = 'periodista' AND up.id_usuario IS NULL";
} else {
    $where[] = "u.rol = ?";
    $params[] = $filtro_rol;
}

if ($filtro_estado !== 'todos') {
    $where[] = "u.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_conexion === 'en_linea') {
    $where[] = "u.ultima_actividad >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
} elseif ($filtro_conexion === 'desconectado') {
    $where[] = "(u.ultima_actividad IS NULL OR u.ultima_actividad < DATE_SUB(NOW(), INTERVAL 5 MINUTE))";
}

if (!empty($filtro_busqueda)) {
    $where[] = "(u.nombre LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?)";
    $params[] = "%{$filtro_busqueda}%";
    $params[] = "%{$filtro_busqueda}%";
    $params[] = "%{$filtro_busqueda}%";
}

$sql_where = !empty($where) ? "WHERE " . implode(" AND ", $where) : '';

// ============================================
// CONTAR TOTAL
// ============================================
if ($es_usuario_normal) {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM usuarios u {$sql_where}");
} else {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM usuarios u LEFT JOIN usuarios_privados up ON u.id_usuario = up.id_usuario {$sql_where}");
}
$stmt_total->execute($params);
$total_usuarios = $stmt_total->fetchColumn();
$total_paginas = max(1, ceil($total_usuarios / $por_pagina));

if ($pagina > $total_paginas) {
    $pagina = $total_paginas;
    $offset = ($pagina - 1) * $por_pagina;
}

// ============================================
// OBTENER USUARIOS
// ============================================
if ($es_usuario_normal) {
    $sql = "SELECT u.*, 0 as es_privado,
                   (SELECT COUNT(*) FROM comentarios WHERE id_usuario = u.id_usuario) as total_comentarios
            FROM usuarios u
            {$sql_where}
            ORDER BY u.fecha_registro DESC, u.id_usuario DESC
            LIMIT ? OFFSET ?";
} else {
    $sql = "SELECT u.*, CASE WHEN up.id_usuario IS NOT NULL THEN 1 ELSE 0 END as es_privado,
                   (SELECT COUNT(*) FROM comentarios WHERE id_usuario = u.id_usuario) as total_comentarios
            FROM usuarios u
            LEFT JOIN usuarios_privados up ON u.id_usuario = up.id_usuario
            {$sql_where}
            ORDER BY u.fecha_registro DESC, u.id_usuario DESC
            LIMIT ? OFFSET ?";
}

$stmt = $pdo->prepare($sql);
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// ============================================
// ESTADÍSTICAS
// ============================================
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
    'administradores' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn(),
    'periodistas' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'periodista'")->fetchColumn(),
    'periodistas_privados' => $pdo->query("SELECT COUNT(*) FROM usuarios_privados")->fetchColumn(),
    'usuarios' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario'")->fetchColumn(),
    'activos' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'")->fetchColumn(),
    'en_linea' => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo' AND ultima_actividad >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn(),
];

$titulo_pagina = 'Gestión de Perfiles';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-usuarios-logueados.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('admin-confirm-modal.css'); ?>">




<div class="admin-container">
    
    <h1>👥 Gestión de Perfiles</h1>

    <p class="usuarios-actividad-ayuda">
        «En línea» indica actividad durante los últimos 5 minutos. El tiempo
        acumulado es aproximado y solo cuenta periodos de uso autenticado.
    </p>
    
    <?php if ($mensaje_flash): ?>

        <div class="mensaje mensaje-<?php echo $mensaje_flash['tipo']; ?>">

            <?php echo htmlspecialchars($mensaje_flash['mensaje'], ENT_QUOTES, 'UTF-8'); ?>

        </div>
    <?php endif; ?>

    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo number_format($stats['total'], 0, ',', '.'); ?></div><div class="stat-label">Total</div></div>

        <div class="stat-card"><div class="stat-number"><?php echo $stats['administradores']; ?></div><div class="stat-label">Admin</div></div>

        <div class="stat-card"><div class="stat-number"><?php echo $stats['periodistas']; ?></div><div class="stat-label">Articulistas</div></div>

        <div class="stat-card"><div class="stat-number"><?php echo $stats['periodistas_privados']; ?></div><div class="stat-label">Colaboradores</div></div>

        <div class="stat-card"><div class="stat-number"><?php echo number_format($stats['usuarios'], 0, ',', '.'); ?></div><div class="stat-label">Comentaristas</div></div>

        <div class="stat-card"><div class="stat-number"><?php echo number_format($stats['activos'], 0, ',', '.'); ?></div><div class="stat-label">Activos</div></div>

        <div class="stat-card"><div class="stat-number"><?php echo number_format($stats['en_linea'], 0, ',', '.'); ?></div><div class="stat-label">En línea</div></div>

    </div>
    
    <!-- Filtros -->
    <div class="filtros">
        <form method="GET">
            <div class="filtros-grupo">
                <label>Conexión:</label>
                <select name="conexion">
                    <option value="todos" <?php echo $filtro_conexion === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="en_linea" <?php echo $filtro_conexion === 'en_linea' ? 'selected' : ''; ?>>🟢 En línea</option>
                    <option value="desconectado" <?php echo $filtro_conexion === 'desconectado' ? 'selected' : ''; ?>>⚪ Desconectados</option>
                </select>
            </div>
            <div class="filtros-grupo">
                <label>Rol:</label>
                <select name="rol">
                    <option value="todos" <?php echo $filtro_rol === 'todos' ? 'selected' : ''; ?>>👥 Todos</option>

                    <option value="periodista" <?php echo $filtro_rol === 'periodista' ? 'selected' : ''; ?>>✍️ Articulistas</option>

                    <option value="periodista_privado" <?php echo $filtro_rol === 'periodista_privado' ? 'selected' : ''; ?>>🔒 Colaboradores</option>

                    <option value="admin" <?php echo $filtro_rol === 'admin' ? 'selected' : ''; ?>>👑 Admin</option>

                    <option value="usuario" <?php echo $filtro_rol === 'usuario' ? 'selected' : ''; ?>>💬 Comentaristas</option>

                </select>
            </div>
            <div class="filtros-grupo">
                <label>Estado:</label>
                <select name="estado">
                    <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos</option>

                    <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>🟢 Activos</option>

                    <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>🔴 Inactivos</option>

                    <option value="bloqueado" <?php echo $filtro_estado === 'bloqueado' ? 'selected' : ''; ?>>⛔ Bloqueados</option>

                    <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>⏳ Pendientes</option>

                </select>
            </div>
            <div class="filtros-grupo">
                <label>Buscar:</label>
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($filtro_busqueda); ?>" placeholder="Nombre o email...">

            </div>
            <div class="filtros-grupo">
                <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                <?php if ($filtro_rol !== 'todos' || $filtro_estado !== 'todos' || $filtro_conexion !== 'todos' || !empty($filtro_busqueda)): ?>

                    <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">🗑️ Limpiar</a>

                <?php endif; ?>

            </div>
        </form>
    </div>
    
    <!-- Tarjetas de usuarios -->
    <div class="tarjetas-grid">
        <?php if (empty($usuarios)): ?>

            <div class="tarjeta-vacia">
                No hay usuarios que coincidan con los filtros
            </div>
        <?php else: ?>

            <?php foreach ($usuarios as $user): ?>

    <div class="tarjeta-usuario">
        <div class="tarjeta-header">
            <div class="tarjeta-avatar">
                <img src="<?php echo base_url('uploads/perfiles/' . ($user['avatar'] ?? 'default-avatar.png')); ?>" 

                     onerror="this.src='<?php echo base_url('assets/img/default-avatar.png'); ?>'">

            </div>
            <div class="tarjeta-nombre"><?php echo htmlspecialchars($user['nombre']); ?></div>

            <div class="tarjeta-id">ID: <?php echo $user['id_usuario']; ?></div>

            <div class="clean"></div>
        </div>
        
        <div class="tarjeta-body">
            <div class="tarjeta-info">
                <div class="tarjeta-info-item">
                    <span class="icono">📧</span>
                    <span class="label">Email:</span>
                    <span class="valor"><?php echo htmlspecialchars($user['email']); ?></span>

                </div>
                <div class="tarjeta-info-item">
                    <span class="icono">📞</span>
                    <span class="label">Teléfono:</span>
                    <span class="valor"><?php echo htmlspecialchars($user['telefono'] ?? '—'); ?></span>

                </div>
                <div class="tarjeta-info-item">
                    <span class="icono">📅</span>
                    <span class="label">Registro:</span>
                    <span class="valor"><?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?></span>

                </div>
                <div class="tarjeta-info-item">
                    <span class="icono">🔌</span>
                    <span class="label">Conexiones:</span>
                    <span class="valor"><?php echo number_format((int) ($user['total_conexiones'] ?? 0), 0, ',', '.'); ?></span>
                </div>
                <div class="tarjeta-info-item">
                    <span class="icono">⏱️</span>
                    <span class="label">Tiempo:</span>
                    <span class="valor"><?php echo htmlspecialchars(formatearTiempoActividad((int) ($user['tiempo_conectado_segundos'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            
            <div class="tarjeta-badges">
                <?php

                $rol_badge = match($user['rol']) {
                    'admin' => 'badge-rol-admin',
                    'periodista' => 'badge-rol-periodista',
                    'usuario' => 'badge-rol-usuario',
                    default => ''
                };
                $rol_visible = match (true) {
                    $user['rol'] === 'admin' => 'Admin',
                    $user['rol'] === 'periodista' && (int) $user['es_privado'] === 1 => 'Colaborador',
                    $user['rol'] === 'periodista' => 'Articulista',
                    default => 'Comentarista',
                };
                $estado_badge = match($user['estado']) {
                    'activo' => 'badge-estado-activo',
                    'inactivo' => 'badge-estado-inactivo',
                    'bloqueado' => 'badge-estado-bloqueado',
                    'pendiente' => 'badge-estado-pendiente',
                    default => ''
                };
                ?>
                <span class="badge <?php echo $rol_badge; ?>">

                    <?php echo htmlspecialchars($rol_visible, ENT_QUOTES, 'UTF-8'); ?>

                </span>
                <span class="badge <?php echo $estado_badge; ?>">

                    <?php echo $user['estado']; ?>

                </span>
                <?php if ($user['rol'] === 'periodista'): ?>

                    <span class="badge <?php echo $user['es_privado'] ? 'badge-privado-si' : 'badge-privado-no'; ?>">

                        <?php echo $user['es_privado'] ? '🔒 Colaborador' : '✍️ Articulista'; ?>

                    </span>
                <?php endif; ?>

                <span class="badge badge-comentarios">
                    💬 <?php echo $user['total_comentarios']; ?> comentarios

                </span>
                <?php $esta_en_linea = usuarioEstaEnLinea($user['ultima_actividad'] ?? null); ?>
                <span class="badge <?php echo $esta_en_linea ? 'badge-conexion-online' : 'badge-conexion-offline'; ?>">
                    <?php echo $esta_en_linea ? '🟢 En línea' : '⚪ Desconectado'; ?>
                </span>
            </div>
        </div>
        
        <div class="tarjeta-footer">
            <div class="tarjeta-fecha">
                <span>🕒</span> 
                <?php echo $user['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($user['ultimo_acceso'])) : 'Nunca'; ?>

            </div>
            <div class="tarjeta-acciones">
                <?php 

                $parametros_listado = [
                    'rol' => $filtro_rol,
                    'estado' => $filtro_estado,
                    'conexion' => $filtro_conexion,
                    'buscar' => $filtro_busqueda,
                    'pagina' => $pagina,
                ];
                ?>
                
                <?php if ($user['estado'] === 'activo'): ?>

                    <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados', array_merge([
                        'accion' => 'desactivar',
                        'id' => (int) $user['id_usuario'],
                    ], $parametros_listado)), ENT_QUOTES, 'UTF-8'); ?>"

                       class="btn-desactivar" title="Desactivar">🔴</a>
                <?php else: ?>
                    <form method="POST" action="<?php echo htmlspecialchars(route('admin_usuarios_logueados'), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="accion" value="activar">
                        <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                        <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="conexion_filtro" value="<?php echo htmlspecialchars($filtro_conexion, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                        <button type="submit" class="btn-activar" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('¿Activar este usuario?')" title="Activar">🟢</button>
                    </form>
                <?php endif; ?>

                
                <?php if ($user['rol'] === 'periodista'): ?>
                    <form method="POST" action="<?php echo htmlspecialchars(route('admin_usuarios_logueados'), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="accion" value="toggle_privado">
                        <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                        <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="conexion_filtro" value="<?php echo htmlspecialchars($filtro_conexion, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                        <button type="submit" class="btn-privado" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('<?php echo $user['es_privado'] ? '¿Reasignar como Articulista? Se eliminarán definitivamente sus noticias privadas y todos sus comentarios.' : '¿Otorgar acceso de Colaborador?'; ?>')" title="Permiso privado">🔒</button>
                    </form>
                <?php endif; ?>

                
                <?php if ($user['id_usuario'] != $_SESSION['usuario_id']): ?>

                    <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados', array_merge([
                        'accion' => 'eliminar',
                        'id' => (int) $user['id_usuario'],
                    ], $parametros_listado)), ENT_QUOTES, 'UTF-8'); ?>"

                       class="btn-eliminar" title="Eliminar">🗑️</a>
                <?php endif; ?>

            </div>
        </div>
    </div>
<?php endforeach; ?>

        <?php endif; ?>

    </div>
    
    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>

        <div class="paginacion">
            <?php if ($pagina > 1): ?>

                <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados', [
                    'rol' => $filtro_rol,
                    'estado' => $filtro_estado,
                    'conexion' => $filtro_conexion,
                    'buscar' => $filtro_busqueda,
                    'pagina' => $pagina - 1,
                ]), ENT_QUOTES, 'UTF-8'); ?>" class="pagina">« Anterior</a>

            <?php endif; ?>

            
            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>

                <?php if ($i == $pagina): ?>

                    <span class="pagina active"><?php echo $i; ?></span>

                <?php else: ?>

                    <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados', [
                        'rol' => $filtro_rol,
                        'estado' => $filtro_estado,
                        'conexion' => $filtro_conexion,
                        'buscar' => $filtro_busqueda,
                        'pagina' => $i,
                    ]), ENT_QUOTES, 'UTF-8'); ?>" class="pagina"><?php echo $i; ?></a>

                <?php endif; ?>

            <?php endfor; ?>

            
            <?php if ($pagina < $total_paginas): ?>

                <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados', [
                    'rol' => $filtro_rol,
                    'estado' => $filtro_estado,
                    'conexion' => $filtro_conexion,
                    'buscar' => $filtro_busqueda,
                    'pagina' => $pagina + 1,
                ]), ENT_QUOTES, 'UTF-8'); ?>" class="pagina">Siguiente »</a>

            <?php endif; ?>

        </div>
    <?php endif; ?>

    
    <div class="info-footer">
        📊 Mostrando <strong><?php echo count($usuarios); ?></strong> de <strong><?php echo number_format($total_usuarios, 0, ',', '.'); ?></strong> usuarios (página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>)

    </div>
    
</div>

<!-- ============================================ -->
<!-- MODAL PARA CONFIRMAR ACCIÓN SOBRE COMENTARIOS -->
<!-- ============================================ -->
<?php

$modal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modal_accion = isset($_GET['modal']) ? $_GET['modal'] : '';
$modal_rol = $filtro_rol;
$modal_estado = $filtro_estado;
$modal_conexion = $filtro_conexion;
$modal_buscar = $filtro_busqueda;
$modal_pagina = $pagina;
$modal_return_url = route('admin_usuarios_logueados', [
    'rol' => $modal_rol,
    'estado' => $modal_estado,
    'conexion' => $modal_conexion,
    'buscar' => $modal_buscar,
    'pagina' => $modal_pagina,
]);

if ($modal_accion && $modal_id && ($modal_accion === 'desactivar' || $modal_accion === 'eliminar')):
    // Obtener datos del usuario y conteo de comentarios
    $stmt = $pdo->prepare("
        SELECT u.nombre, u.email, u.rol, COUNT(c.id_comentario) as total_comentarios
        FROM usuarios u
        LEFT JOIN comentarios c ON u.id_usuario = c.id_usuario
        WHERE u.id_usuario = ?
        GROUP BY u.id_usuario
    ");
    $stmt->execute([$modal_id]);
    $usuario_modal = $stmt->fetch();
    
    if ($usuario_modal):
        $accion_texto = $modal_accion === 'desactivar' ? 'desactivar' : 'eliminar permanentemente';
        $btn_texto = $modal_accion === 'desactivar' ? 'Desactivar usuario' : 'Eliminar usuario';
        $btn_color = $modal_accion === 'desactivar' ? 'warning' : 'danger';
?>
<div class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-header">
            <h3>⚠️ <?php echo $modal_accion === 'desactivar' ? 'Desactivar usuario' : 'Eliminar usuario'; ?></h3>

            <a href="<?php echo htmlspecialchars($modal_return_url, ENT_QUOTES, 'UTF-8'); ?>" class="modal-cerrar" aria-label="Cerrar">&times;</a>
        </div>
        
        <div class="modal-body">
            <p>
                El usuario <strong><?php echo htmlspecialchars($usuario_modal['nombre']); ?></strong> 

                (<?php echo htmlspecialchars($usuario_modal['email']); ?>)

                será <?php echo $accion_texto; ?>.

            </p>
            
            <div class="modal-alerta">
                <?php if ($modal_accion === 'desactivar'): ?>
                    La cuenta quedará inactiva y se conservarán todas sus noticias, comentarios y archivos.
                <?php else: ?>
                    Se eliminarán permanentemente la cuenta, sus <?php echo (int) $usuario_modal['total_comentarios']; ?> comentarios, noticias públicas y privadas, archivos y demás datos asociados. Si es periodista, sus fuentes RSS se transferirán al administrador.
                <?php endif; ?>
            </div>

            <form method="POST" action="<?php echo htmlspecialchars(route('admin_usuarios_logueados'), ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('<?php echo $modal_accion === 'eliminar' ? 'Esta eliminación es irreversible. ¿Continuar?' : '¿Desactivar esta cuenta conservando todo su contenido?'; ?>')">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="accion" value="<?php echo $modal_accion; ?>">
                    <input type="hidden" name="id" value="<?php echo $modal_id; ?>">
                    <input type="hidden" name="confirmar" value="1">
                    <input type="hidden" name="rol_filtro" value="<?php echo $modal_rol; ?>">
                    <input type="hidden" name="estado_filtro" value="<?php echo $modal_estado; ?>">
                    <input type="hidden" name="conexion_filtro" value="<?php echo $modal_conexion; ?>">
                    <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($modal_buscar); ?>">
                    <input type="hidden" name="pagina_filtro" value="<?php echo $modal_pagina; ?>">

                <div class="modal-buttons">
                    <a href="<?php echo htmlspecialchars($modal_return_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-<?php echo $btn_color; ?>"><?php echo $btn_texto; ?></button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php

    endif;
endif;
?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
