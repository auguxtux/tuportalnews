<?php
declare(strict_types=1);


/**
 * ADMIN - USUARIOS DEL SISTEMA
 * Paginación: 15 usuarios por página
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';

Permisos::requerirAdmin();

$pdo = db();
$roles_filtro_validos = ['todos', 'usuario', 'admin', 'periodista', 'periodista_privado'];
$estados_filtro_validos = ['todos', 'activo', 'inactivo', 'bloqueado', 'pendiente'];

// ============================================
// PROCESAR ACCIONES POR POST
// ============================================
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$mensaje_flash = null;

// Preservar filtros para redirección
$filtro_rol_guardar = (string)($_POST['rol_filtro'] ?? 'todos');
if (!in_array($filtro_rol_guardar, $roles_filtro_validos, true)) {
    $filtro_rol_guardar = 'todos';
}
$filtro_estado_guardar = (string)($_POST['estado_filtro'] ?? 'todos');
if (!in_array($filtro_estado_guardar, $estados_filtro_validos, true)) {
    $filtro_estado_guardar = 'todos';
}
$filtro_busqueda_guardar = isset($_POST['buscar_filtro']) ? $_POST['buscar_filtro'] : '';
$pagina_guardar = max(1, (int)($_POST['pagina_filtro'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $mensaje_flash = ['tipo' => 'error', 'mensaje' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.'];
} elseif (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $accion === 'desactivar'
    && $id === (int) ($_SESSION['usuario_id'] ?? 0)
) {
    $mensaje_flash = ['tipo' => 'error', 'mensaje' => 'No puedes desactivar tu propia cuenta desde este panel.'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion && $id) {
    try {
        $stmt = $pdo->prepare(
            'SELECT id_usuario, email, rol, creado_por_admin FROM usuarios WHERE id_usuario = ?'
        );
        $stmt->execute([$id]);
        $usuarioObjetivo = $stmt->fetch();
        if (
            !$usuarioObjetivo
            || !Permisos::puedeGestionarUsuario($usuarioObjetivo, $accion)
        ) {
            throw new RuntimeException('No tienes permiso para administrar esta cuenta.');
        }

        switch ($accion) {
            case 'activar':
                $pdo->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?")->execute([$id]);
                $mensaje_flash = ['tipo' => 'success', 'mensaje' => 'Usuario activado'];
                break;
            case 'desactivar':
                $pdo->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?")->execute([$id]);
                $mensaje_flash = ['tipo' => 'success', 'mensaje' => 'Usuario desactivado'];
                break;
            case 'toggle_privado':
                $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() !== 'periodista') {
                    throw new RuntimeException('El acceso privado solo puede asignarse a articulistas.');
                }

                $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios_privados WHERE id_usuario = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()) {
                    $resultado = reasignarColaboradorAArticulista($pdo, $id);
                    if (!$resultado['success']) {
                        throw new RuntimeException($resultado['message']);
                    }
                    $mensaje_flash = ['tipo' => 'success', 'mensaje' => $resultado['message']];
                } else {
                    $pdo->prepare("INSERT INTO usuarios_privados (id_usuario, activo, fecha_alta) VALUES (?, 1, NOW())")->execute([$id]);
                    $mensaje_flash = ['tipo' => 'success', 'mensaje' => 'Permiso OTORGADO'];
                }
                break;
        }
    } catch (Throwable $e) {
        registrarErrorInterno('ADMIN.USUARIOS_TABLA.GESTION', $e);
        $mensaje_flash = ['tipo' => 'error', 'mensaje' => 'Error al procesar la acción'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mensaje_flash) {
        $_SESSION['mensaje_flash'] = $mensaje_flash;
    }
    
    $redirect_url = 'usuarios-logueados-tabla?rol=' . $filtro_rol_guardar . 
                '&estado=' . $filtro_estado_guardar . 
                '&buscar=' . urlencode($filtro_busqueda_guardar) . 
                '&pagina=' . $pagina_guardar;
    header('Location: ' . $redirect_url);
    exit;
}

if (isset($_SESSION['mensaje_flash'])) {
    $mensaje_flash = $_SESSION['mensaje_flash'];
    unset($_SESSION['mensaje_flash']);
}

// ============================================
// FILTROS
// ============================================
$filtro_rol = is_string($_GET['rol'] ?? null) ? $_GET['rol'] : 'todos';
$filtro_estado = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : 'todos';
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

$es_usuario_normal = ($filtro_rol === 'usuario');

// ============================================
// CONSTRUIR CONSULTA WHERE
// ============================================
$where = [];
$params = [];

if ($filtro_rol === 'todos') {
    // Sin condición de rol: mostrar todos los perfiles.
} elseif ($es_usuario_normal) {
    // Usuarios normales: solo rol = 'usuario'
    $where[] = "u.rol = 'usuario'";
} elseif ($filtro_rol === 'periodista_privado') {
    $where[] = "u.rol = 'periodista' AND up.id_usuario IS NOT NULL";
} else {
    $where[] = "u.rol = ?";
    $params[] = $filtro_rol;
}

if ($filtro_estado !== 'todos') {
    $where[] = "u.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_busqueda)) {
    $where[] = "(u.nombre LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?)";
    $params[] = "%{$filtro_busqueda}%";
    $params[] = "%{$filtro_busqueda}%";
    $params[] = "%{$filtro_busqueda}%";
}

$sql_where = $where ? "WHERE " . implode(" AND ", $where) : '';

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

// Ajustar página si es mayor que el total
if ($pagina > $total_paginas) {
    $pagina = $total_paginas;
    $offset = ($pagina - 1) * $por_pagina;
}

// ============================================
// OBTENER USUARIOS
// ============================================
if ($es_usuario_normal) {
    // Usuarios normales: sin JOIN con usuarios_privados
    $sql = "SELECT
                u.*,
                0 AS es_privado,
                COALESCE(n.total_noticias, 0) AS total_noticias,
                COALESCE(c.total_comentarios, 0) AS total_comentarios
            FROM usuarios u
            LEFT JOIN (
                SELECT id_autor, COUNT(*) AS total_noticias
                FROM noticias
                GROUP BY id_autor
            ) n ON n.id_autor = u.id_usuario
            LEFT JOIN (
                SELECT id_usuario, COUNT(*) AS total_comentarios
                FROM comentarios
                GROUP BY id_usuario
            ) c ON c.id_usuario = u.id_usuario
            {$sql_where}
            ORDER BY u.fecha_registro DESC, u.id_usuario DESC
            LIMIT ? OFFSET ?";
} else {
    // Otros roles: con JOIN a usuarios_privados
    $sql = "SELECT
                u.*,
                CASE WHEN up.id_usuario IS NOT NULL THEN 1 ELSE 0 END AS es_privado,
                COALESCE(n.total_noticias, 0) AS total_noticias,
                COALESCE(c.total_comentarios, 0) AS total_comentarios
            FROM usuarios u
            LEFT JOIN usuarios_privados up ON u.id_usuario = up.id_usuario
            LEFT JOIN (
                SELECT id_autor, COUNT(*) AS total_noticias
                FROM noticias
                GROUP BY id_autor
            ) n ON n.id_autor = u.id_usuario
            LEFT JOIN (
                SELECT id_usuario, COUNT(*) AS total_comentarios
                FROM comentarios
                GROUP BY id_usuario
            ) c ON c.id_usuario = u.id_usuario
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
];

$titulo_pagina = 'Gestión de Perfiles';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-usuarios-logueados-tabla.css'); ?>">



<div class="admin-container">
    
    <h1>👥 Gestión de Perfiles</h1>
    
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

    </div>
    
    <!-- Filtros -->
    <div class="filtros">
        <form method="GET">
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
                <?php if ($filtro_rol !== 'todos' || $filtro_estado !== 'todos' || !empty($filtro_busqueda)): ?>

                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="btn btn-secondary">🗑️ Limpiar</a>

                <?php endif; ?>

            </div>
        </form>
    </div>
    
    <!-- Tabla de usuarios -->
    <div class="tabla-container">
        <table>
            <thead>
                <tr><th>ID</th><th>Usuario</th><th>Email</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th>Privado</th><th>Registro</th><th>Nº Noticias / Comentarios</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>

                    <tr><td colspan="10" style="text-align:center;">No hay usuarios que coincidan con los filtros</td></tr>
                <?php else: ?>

                    <?php foreach ($usuarios as $user): ?>
                        <?php
                        $esCuentaRoot = Permisos::esUsuarioRoot($user);
                        $accionEstado = $user['estado'] === 'activo' ? 'desactivar' : 'activar';
                        $puedeCambiarEstado = Permisos::puedeGestionarUsuario($user, $accionEstado);
                        $puedeGestionarPrivado = Permisos::puedeGestionarUsuario($user, 'toggle_privado');
                        $puedeEliminarCuenta = Permisos::puedeGestionarUsuario($user, 'eliminar');
                        ?>

                        <tr>
                            <td><?php echo $user['id_usuario']; ?></td>

                            <td class="usuario-info">
                                <div class="usuario-avatar">
                                    <img src="<?php echo base_url('uploads/perfiles/' . ($user['avatar'] ?? 'default-avatar.png')); ?>" 

                                         onerror="this.src='<?php echo base_url('assets/img/default-avatar.png'); ?>'">

                                </div>
                                <div class="usuario-nombre">
                                    <?php echo htmlspecialchars($user['nombre']); ?>

                                    <small><?php echo htmlspecialchars($user['telefono'] ?? ''); ?></small>

                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>

                            <td><?php echo htmlspecialchars($user['telefono'] ?? ''); ?></td>

                            <td><span class="rol-badge rol-<?php echo $user['rol']; ?>"><?php echo htmlspecialchars(match (true) {
                                $esCuentaRoot => 'Root',
                                $user['rol'] === 'admin' => 'Admin',
                                $user['rol'] === 'periodista' && (int) $user['es_privado'] === 1 => 'Colaborador',
                                $user['rol'] === 'periodista' => 'Articulista',
                                default => 'Comentarista',
                            }, ENT_QUOTES, 'UTF-8'); ?></span></td>

                            <td><span class="estado-badge estado-<?php echo $user['estado']; ?>"><?php echo $user['estado']; ?></span></td>

                            <td>
                                <?php if ($user['rol'] === 'periodista'): ?>

                                    <?php echo $user['es_privado'] ? '🔒 Sí' : '🔓 No'; ?>

                                <?php else: ?>—<?php endif; ?>

                            </td>
                            <td><?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?></td>

                            <td style="text-align:center; white-space:nowrap;">
                                📰 <?php echo number_format((int) $user['total_noticias'], 0, ',', '.'); ?>
                                &nbsp;/&nbsp;
                                💬 <?php echo number_format((int) $user['total_comentarios'], 0, ',', '.'); ?>
                            </td>

                            <td class="acciones">
                                <div class="btn-grupo">
                                    <?php if ($puedeCambiarEstado && $user['estado'] === 'activo'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="desactivar">
                                            <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                                            <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                                            <button type="submit" class="btn-desactivar" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('¿Desactivar este usuario?')" title="Desactivar">🔴</button>
                                        </form>

                                    <?php elseif ($puedeCambiarEstado): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="activar">
                                            <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                                            <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                                            <button type="submit" class="btn-activar" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('¿Activar este usuario?')" title="Activar">🟢</button>
                                        </form>

                                    <?php endif; ?>

                                    <?php if (Permisos::esRoot() && $user['rol'] === 'periodista' && (int) $user['es_privado'] === 1): ?>
                                        <form method="POST" action="<?php echo htmlspecialchars(route('admin_usuarios_logueados'), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="cambiar_rol">
                                            <input type="hidden" name="nuevo_rol" value="admin">
                                            <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                                            <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                                            <button type="submit" class="btn-activar" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('¿Convertir este Colaborador en Admin? Mantendrá sus contenidos y dejará de figurar como Colaborador.')" title="Convertir en Admin">👑</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (Permisos::esRoot() && $user['rol'] === 'admin' && !$esCuentaRoot): ?>
                                        <form method="POST" action="<?php echo htmlspecialchars(route('admin_usuarios_logueados'), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="cambiar_rol">
                                            <input type="hidden" name="nuevo_rol" value="periodista">
                                            <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                                            <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                                            <button type="submit" class="btn-privado" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('¿Convertir este Admin en Colaborador? Mantendrá sus contenidos y perderá los permisos administrativos.')" title="Convertir en Colaborador">👤</button>
                                        </form>
                                    <?php endif; ?>

                                    
                                    <?php if ($puedeGestionarPrivado && $user['rol'] === 'periodista'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="toggle_privado">
                                            <input type="hidden" name="id" value="<?php echo $user['id_usuario']; ?>">
                                            <input type="hidden" name="rol_filtro" value="<?php echo htmlspecialchars($filtro_rol, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="buscar_filtro" value="<?php echo htmlspecialchars($filtro_busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="pagina_filtro" value="<?php echo $pagina; ?>">
                                            <button type="submit" class="btn-privado" style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit;" onclick="return confirm('<?php echo $user['es_privado'] ? '¿Reasignar como Articulista? Se eliminarán definitivamente sus noticias privadas y todos sus comentarios.' : '¿Otorgar acceso de Colaborador?'; ?>')" title="Permiso privado">🔒</button>
                                        </form>

                                    <?php endif; ?>

                                    
                                    <?php if ($puedeEliminarCuenta && $user['id_usuario'] != $_SESSION['usuario_id']): ?>

                                        <a href="<?php echo htmlspecialchars(route('admin_usuarios_logueados', [
                                            'modal' => 'eliminar',
                                            'id' => $user['id_usuario'],
                                            'rol' => $filtro_rol,
                                            'estado' => $filtro_estado,
                                            'buscar' => $filtro_busqueda,
                                            'pagina' => $pagina,
                                        ]), ENT_QUOTES, 'UTF-8'); ?>" class="btn-eliminar" title="Eliminar">🗑️</a>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>

        <div class="paginacion">
            <?php if ($pagina > 1): ?>

                <a href="?rol=<?php echo $filtro_rol; ?>&estado=<?php echo $filtro_estado; ?>&buscar=<?php echo urlencode($filtro_busqueda); ?>&pagina=<?php echo $pagina - 1; ?>" class="pagina">« Anterior</a>

            <?php endif; ?>

            
            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>

                <?php if ($i == $pagina): ?>

                    <span class="pagina active"><?php echo $i; ?></span>

                <?php else: ?>

                    <a href="?rol=<?php echo $filtro_rol; ?>&estado=<?php echo $filtro_estado; ?>&buscar=<?php echo urlencode($filtro_busqueda); ?>&pagina=<?php echo $i; ?>" class="pagina"><?php echo $i; ?></a>

                <?php endif; ?>

            <?php endfor; ?>

            
            <?php if ($pagina < $total_paginas): ?>

                <a href="?rol=<?php echo $filtro_rol; ?>&estado=<?php echo $filtro_estado; ?>&buscar=<?php echo urlencode($filtro_busqueda); ?>&pagina=<?php echo $pagina + 1; ?>" class="pagina">Siguiente »</a>

            <?php endif; ?>

        </div>
    <?php endif; ?>

    
    <!-- Información adicional -->
    <div class="info-footer">
        📊 Mostrando <strong><?php echo count($usuarios); ?></strong> de <strong><?php echo number_format($total_usuarios, 0, ',', '.'); ?></strong> usuarios (página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>)

    </div>
    
</div><?php require_once __DIR__ . '/../partials/footer.php'; ?>
