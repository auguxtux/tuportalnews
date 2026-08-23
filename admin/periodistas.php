<?php
declare(strict_types=1);


/**
 * GESTIÓN DE PERIODISTAS - Panel de administración
 * 
 * Permite gestionar periodistas: filtrar, aprobar, bloquear, desbloquear,
 * eliminar y gestionar permisos de noticias privadas.
 * 
 * Incluye modal para manejar noticias al bloquear/eliminar.
 */

// Requerir archivos necesarios
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/logs.php';
require_once __DIR__ . '/../includes/privado.php';
// Verificar que el usuario es administrador
Permisos::requerirAdmin();

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();

// ============================================
// FUNCIONES AUXILIARES (solo las específicas de este módulo)
// ============================================


// ============================================
// GENERAR TOKEN CSRF (usando la función existente)
// ============================================
$csrf_token = generarTokenCSRF();

// ============================================
// PROCESAR ACCIONES SIMPLES (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirmar_accion'])) {
    $accion_post = $_POST['accion'] ?? '';
    $id_post = (int)($_POST['id'] ?? 0);
    $acciones_simples = ['activar', 'aprobar', 'privado_activar', 'privado_desactivar'];

    $filtros_url = '?q=' . urlencode($_POST['q'] ?? '') .
                   '&estado=' . urlencode($_POST['estado'] ?? '') .
                   '&privado=' . urlencode($_POST['privado'] ?? '') .
                   '&pagina=' . max(1, (int)($_POST['pagina'] ?? 1));

    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error de seguridad. Intenta nuevamente.'];
        redireccionar(route('admin_periodistas') . $filtros_url);
        exit;
    }

    if ($id_post <= 0 || !in_array($accion_post, $acciones_simples, true)) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Acción no válida.'];
        redireccionar(route('admin_periodistas') . $filtros_url);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, email, estado FROM usuarios WHERE id_usuario = ? AND rol = 'periodista'");
        $stmt->execute([$id_post]);
        $periodista = $stmt->fetch();

        if (!$periodista) {
            throw new Exception('Articulista no encontrado.');
        }

        if ($accion_post === 'activar' || $accion_post === 'aprobar') {
            $stmt = $pdo->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = :id AND rol = 'periodista'");
            $stmt->execute([':id' => $id_post]);
            registrarAdminAccionUsuario('aprobar_periodista', $id_post, $periodista['email'], 'Articulista aprobado');

            if ($periodista['estado'] === 'pendiente') {
                $emailEnviado = enviarEmailAprobacion($periodista['email'], $periodista['nombre'], 'periodista');
                $_SESSION['mensaje_flash'] = $emailEnviado
                    ? ['tipo' => 'success', 'mensaje' => '✅ Articulista aprobado y email enviado']
                    : ['tipo' => 'warning', 'mensaje' => '⚠️ Articulista aprobado, pero no se pudo enviar el email'];
            } else {
                $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Articulista activado'];
            }
        } elseif ($accion_post === 'privado_activar') {
            $pdo->prepare("INSERT IGNORE INTO usuarios_privados (id_usuario, fecha_alta) VALUES (?, NOW())")->execute([$id_post]);
            registrarAdminAccionUsuario('dar_privado_periodista', $id_post, $periodista['email'], 'Permiso privado otorgado');
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '🔒 Acceso a noticias privadas activado'];
        } elseif ($accion_post === 'privado_desactivar') {
            $resultado = reasignarColaboradorAArticulista($pdo, $id_post);
            if (!$resultado['success']) {
                throw new RuntimeException($resultado['message']);
            }
            registrarAdminAccionUsuario('quitar_privado_periodista', $id_post, $periodista['email'], 'Permiso privado revocado');
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '🔓 ' . $resultado['message']];
        }

        limpiarTokenCSRF();
    } catch (Exception $e) {
        registrarErrorInterno('ADMIN.PERIODISTAS.GESTION', $e);
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'Error al procesar la acción'];
    }

    redireccionar(route('admin_periodistas') . $filtros_url);
    exit;
}

// ============================================
// PROCESAR ACCIONES CON CONFIRMACIÓN MODAL (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_accion'])) {
    // Validar CSRF usando la función existente verificarTokenCSRF()
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error de seguridad. Intenta nuevamente.'];
        redireccionar(route('admin_periodistas'));
        exit;
    }
    
    $accion_post = $_POST['accion'] ?? '';
    $id_post = (int)($_POST['id'] ?? 0);
    $accion_noticias = $_POST['accion_noticias'] ?? 'nada';
    
    // Validar que la acción es permitida
    $acciones_permitidas = ['bloquear', 'desbloquear'];
    if (!in_array($accion_post, $acciones_permitidas)) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Acción no válida.'];
        redireccionar(route('admin_periodistas'));
        exit;
    }
    
    // Validar que el ID es positivo
    if ($id_post <= 0) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ ID de periodista no válido.'];
        redireccionar(route('admin_periodistas'));
        exit;
    }
    
    if ($id_post && $accion_post) {
        try {
            $pdo->beginTransaction();
            
            // Verificar que el usuario existe y es periodista
            $stmt = $pdo->prepare("SELECT id_usuario, nombre, email, avatar, rol FROM usuarios WHERE id_usuario = ? AND rol = 'periodista'");
            $stmt->execute([$id_post]);
            $periodista = $stmt->fetch();
            
            if (!$periodista) {
                throw new Exception("Articulista no encontrado o no tiene ese perfil.");
            }
            
            $noticias_afectadas = 0;
            
            // Aplicar acción a las noticias
            $acciones_noticias_permitidas = ['nada', 'archivar', 'publicar_privadas', 'ocultar'];
            if (!in_array($accion_noticias, $acciones_noticias_permitidas)) {
                $accion_noticias = 'nada';
            }
            
            if ($accion_noticias !== 'nada') {
                switch ($accion_noticias) {
                    case 'archivar':
                        $stmt = $pdo->prepare("UPDATE noticias SET estado = 'archivada' WHERE id_autor = ?");
                        $stmt->execute([$id_post]);
                        $noticias_afectadas = $stmt->rowCount();
                        break;
                        
                    case 'publicar_privadas':
                        $stmt = $pdo->prepare("UPDATE noticias SET privada = 0 WHERE id_autor = ? AND privada = 1");
                        $stmt->execute([$id_post]);
                        $noticias_afectadas = $stmt->rowCount();
                        break;
                        
                    case 'ocultar':
                        $stmt = $pdo->prepare("UPDATE noticias SET estado = 'archivada', privada = 1 WHERE id_autor = ?");
                        $stmt->execute([$id_post]);
                        $noticias_afectadas = $stmt->rowCount();
                        break;
                        
                }
            }
            
            // Procesar la acción principal
            if ($accion_post === 'bloquear') {
                $stmt = $pdo->prepare("UPDATE usuarios SET estado = 'bloqueado' WHERE id_usuario = ?");
                $stmt->execute([$id_post]);
                
                // 🆕 Registrar en logs
                registrarAdminAccionUsuario('bloquear_periodista', $id_post, $periodista['email'], 'Articulista bloqueado');
                
                $mensaje = "🔒 Articulista bloqueado.";
                
            } elseif ($accion_post === 'desbloquear') {
                $stmt = $pdo->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?");
                $stmt->execute([$id_post]);
                
                // 🆕 Registrar en logs
                registrarAdminAccionUsuario('desbloquear_periodista', $id_post, $periodista['email'], 'Articulista desbloqueado');
                
                $mensaje = "🔓 Articulista desbloqueado.";
                
            }
            
            // Agregar información sobre noticias afectadas
            if ($noticias_afectadas > 0) {
                $accion_texto = [
                    'archivar' => 'archivadas',
                    'publicar_privadas' => 'convertidas a públicas',
                    'ocultar' => 'ocultas'
                ][$accion_noticias] ?? 'procesadas';
                
                $mensaje .= " Se {$accion_texto} {$noticias_afectadas} noticias.";
            }
            
            $pdo->commit();
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => $mensaje];
            
            // Limpiar token CSRF después de uso exitoso
            limpiarTokenCSRF();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'No se pudo procesar la acción del periodista.'];
            registrarErrorInterno('ADMIN.PERIODISTAS.ACCION', $e);
        }
        
        redireccionar(route('admin_periodistas'));
        exit;
    }
}

// ============================================
// PROCESAR POR GET SOLO LA APERTURA DE MODALES
// ============================================
$accion = $_GET['accion'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accion && $id && !isset($_POST['confirmar_accion'])) {
    $acciones_modal = ['bloquear', 'desbloquear'];
    if (in_array($accion, $acciones_modal, true)) {
        $filtros_url = '';
        if (isset($_GET['q'])) $filtros_url .= '&q=' . urlencode($_GET['q']);
        if (isset($_GET['estado'])) $filtros_url .= '&estado=' . urlencode($_GET['estado']);
        if (isset($_GET['privado'])) $filtros_url .= '&privado=' . urlencode($_GET['privado']);
        if (isset($_GET['pagina'])) $filtros_url .= '&pagina=' . (int)$_GET['pagina'];

        header('Location: ' . route('admin_periodistas') . '?modal=' . $accion . '&id=' . $id . $filtros_url);
        exit;
    }
}

// Recuperar mensaje flash
$mensaje_flash = isset($_SESSION['mensaje_flash']) ? $_SESSION['mensaje_flash'] : null;
unset($_SESSION['mensaje_flash']);

// Regenerar token CSRF para nueva solicitud
$csrf_token = generarTokenCSRF();

// ============================================
// FILTROS Y PAGINACIÓN
// ============================================
$busqueda = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_privado = $_GET['privado'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Validar página
if ($pagina < 1) $pagina = 1;

try {
    // Consulta de conteo
    $sql_count = "
        SELECT COUNT(DISTINCT u.id_usuario) 
        FROM usuarios u 
        LEFT JOIN usuarios_privados up ON u.id_usuario = up.id_usuario 
        WHERE u.rol = 'periodista'
    ";
    
    // Consulta principal
    $sql = "
        SELECT u.*, 
               CASE WHEN up.id_usuario IS NOT NULL THEN 1 ELSE 0 END as es_privado,
               up.activo as privado_activo,
               up.fecha_alta as privado_desde,
               (SELECT COUNT(*) FROM noticias WHERE id_autor = u.id_usuario) as total_noticias,
               (SELECT COUNT(*) FROM noticias WHERE id_autor = u.id_usuario AND privada = 1) as noticias_privadas,
               (SELECT COALESCE(SUM(visitas), 0) FROM noticias WHERE id_autor = u.id_usuario) as total_visitas
        FROM usuarios u
        LEFT JOIN usuarios_privados up ON u.id_usuario = up.id_usuario
        WHERE u.rol = 'periodista'
    ";
    
    $params = [];
    
    // Aplicar filtro de búsqueda
    if ($busqueda) {
        $sql .= " AND (u.nombre LIKE :q OR u.email LIKE :q)";
        $sql_count .= " AND (u.nombre LIKE :q OR u.email LIKE :q)";
        $params[':q'] = "%$busqueda%";
    }
    
    // Aplicar filtro de estado
    $estados_validos = ['activo', 'inactivo', 'pendiente', 'bloqueado'];
    if ($filtro_estado && in_array($filtro_estado, $estados_validos)) {
        $sql .= " AND u.estado = :estado";
        $sql_count .= " AND u.estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    
    // Aplicar filtro de permisos privados
    if ($filtro_privado === 'si') {
        $sql .= " AND up.id_usuario IS NOT NULL";
        $sql_count .= " AND up.id_usuario IS NOT NULL";
    } elseif ($filtro_privado === 'no') {
        $sql .= " AND up.id_usuario IS NULL";
        $sql_count .= " AND up.id_usuario IS NULL";
    }
    
    // Ejecutar consulta de conteo
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_periodistas = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_periodistas / $por_pagina);
    
    // Validar página máxima
    if ($pagina > $total_paginas && $total_paginas > 0) {
        $pagina = $total_paginas;
        $offset = ($pagina - 1) * $por_pagina;
    }
    
    // Agregar orden y paginación
    $sql .= " ORDER BY u.fecha_registro DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $periodistas = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'No se pudieron cargar los periodistas.';
    registrarErrorInterno('ADMIN.PERIODISTAS.CARGA', $e);
}

// ============================================
// RENDERIZADO DE LA PÁGINA
// ============================================
$titulo_pagina = 'Gestión de Articulistas';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-editar-periodista.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('admin-confirm-modal.css'); ?>">


<h1 class="titulo">✍️ Gestión de Articulistas</h1>

<?php if ($mensaje_flash): ?>

    <div class="admin-periodistas-alerta admin-periodistas-alerta-<?php echo $mensaje_flash['tipo']; ?>">

        <?php echo htmlspecialchars($mensaje_flash['mensaje']); ?>

    </div>
<?php endif; ?>


<!-- ============================================ -->
<!-- BARRA DE BÚSQUEDA Y FILTROS -->
<!-- ============================================ -->
<div class="admin-periodistas-barra">
    <form method="GET" class="admin-periodistas-filtros">
        <input type="text" name="q" placeholder="Buscar por nombre o email..." 
               value="<?php echo htmlspecialchars($busqueda); ?>" class="admin-periodistas-campo-busqueda">

        
        <select name="estado" class="admin-periodistas-filtro-estado">
            <option value="">Todos los estados</option>
            <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>

            <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>

            <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>

            <option value="bloqueado" <?php echo $filtro_estado === 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>

        </select>
        
        <select name="privado" class="admin-periodistas-filtro-privado">
            <option value="">Todos (privado)</option>
            <option value="si" <?php echo $filtro_privado === 'si' ? 'selected' : ''; ?>>✅ Con acceso privado</option>

            <option value="no" <?php echo $filtro_privado === 'no' ? 'selected' : ''; ?>>❌ Sin acceso privado</option>

        </select>
        
        <button type="submit" class="admin-periodistas-btn admin-periodistas-btn-filtrar">Filtrar</button>
        <a href="<?php echo route('admin_periodistas'); ?>" class="admin-periodistas-btn admin-periodistas-btn-limpiar">Limpiar</a>
        <a href="<?php echo route('admin_nuevo_periodista'); ?>" class="admin-periodistas-btn admin-periodistas-btn-nuevo">➕ Nuevo periodista</a>
    </form>
</div>

<?php if (isset($error)): ?>

    <div class="admin-periodistas-alerta admin-periodistas-alerta-error"><?php echo htmlspecialchars($error); ?></div>

<?php endif; ?>


<?php if (empty($periodistas)): ?>

    <div class="admin-periodistas-alerta admin-periodistas-alerta-info">
        <p>No se encontraron periodistas con los criterios seleccionados.</p>
    </div>
<?php else: ?>

    
    <p class="admin-periodistas-resultados-info">
        Mostrando <?php echo count($periodistas); ?> de <?php echo $total_periodistas; ?> periodistas

    </p>
    
    <!-- ============================================ -->
    <!-- GRID DE TARJETAS DE PERIODISTAS -->
    <!-- ============================================ -->
    <div class="admin-periodistas-contenedor">
        <div class="admin-periodistas-grid">
            <?php foreach ($periodistas as $per): ?>

                <div class="admin-periodistas-card">
                    <div class="admin-periodistas-card-header">
                        <span class="admin-periodistas-card-id">#<?php echo $per['id_usuario']; ?></span>

                        
                        <div class="admin-periodistas-card-avatar">
                            <img src="<?php echo base_url('uploads/perfiles/' . ($per['avatar'] ?? 'default-avatar.png')); ?>" 

                                 alt="Avatar de <?php echo htmlspecialchars($per['nombre']); ?>">

                        </div>
                        
                        <h3 class="admin-periodistas-card-nombre">
                            <a href="<?php echo route('periodistas', ['id' => (int) $per['id_usuario']]); ?>">

                                <?php echo htmlspecialchars($per['nombre']); ?>

                            </a>
                        </h3>
                        
                        <div class="admin-periodistas-card-contacto">
                            <div class="admin-periodistas-card-email">
                                📧 <?php echo htmlspecialchars($per['email']); ?>

                            </div>
                            <?php if (!empty($per['telefono'])): ?>

                                <div class="admin-periodistas-card-telefono">
                                    📞 <?php echo htmlspecialchars($per['telefono']); ?>

                                </div>
                            <?php endif; ?>

                        </div>
                        
                        <?php if (!empty($per['ciudad'])): ?>

                            <span class="admin-periodistas-card-ciudad">
                                📍 <?php echo htmlspecialchars($per['ciudad']); ?>

                            </span>
                        <?php endif; ?>

                    </div>
                    
                    <div class="admin-periodistas-card-body">
                        <div class="admin-periodistas-card-stats">
                            <div class="admin-periodistas-card-stat">
                                <span class="admin-periodistas-stat-valor"><?php echo $per['total_noticias']; ?></span>

                                <span class="admin-periodistas-stat-etiqueta">Noticias</span>
                            </div>
                            
                            <div class="admin-periodistas-card-stat">
                                <span class="admin-periodistas-stat-valor"><?php echo $per['noticias_privadas']; ?></span>

                                <span class="admin-periodistas-stat-etiqueta">Privadas</span>
                            </div>
                            
                            <div class="admin-periodistas-card-stat admin-periodistas-card-stat-visitas">
                                <span class="admin-periodistas-stat-valor"><?php echo number_format((float) ($per['total_visitas'] ?: 0)); ?></span>

                                <span class="admin-periodistas-stat-etiqueta">Visitas totales</span>
                            </div>
                        </div>
                        
                        <div class="admin-periodistas-card-badges">
                            <?php if ($per['es_privado']): ?>

                                <span class="admin-periodistas-badge admin-periodistas-badge-privado" 
                                      title="Desde: <?php echo $per['privado_desde'] ? date('d/m/Y', strtotime($per['privado_desde'])) : 'N/A'; ?>">

                                    🔒 Privado
                                </span>
                            <?php else: ?>

                                <span class="admin-periodistas-badge admin-periodistas-badge-no-privado">❌ No privado</span>
                            <?php endif; ?>

                            
                            <?php

                            $estado_class = match($per['estado']) {
                                'activo' => 'admin-periodistas-badge-activo',
                                'inactivo' => 'admin-periodistas-badge-inactivo',
                                'pendiente' => 'admin-periodistas-badge-pendiente',
                                'bloqueado' => 'admin-periodistas-badge-bloqueado',
                                default => ''
                            };
                            ?>
                            <span class="admin-periodistas-badge <?php echo $estado_class; ?>">

                                <?php echo ucfirst($per['estado']); ?>

                            </span>
                        </div>
                    </div>
                    
                    <div class="admin-periodistas-card-footer">
                        <div class="admin-periodistas-acciones">
                            <?php if ($per['estado'] === 'pendiente'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="aprobar">
                                    <input type="hidden" name="id" value="<?php echo $per['id_usuario']; ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="estado" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="privado" value="<?php echo htmlspecialchars($filtro_privado, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="pagina" value="<?php echo $pagina; ?>">
                                    <button type="submit" class="admin-periodistas-btn admin-periodistas-btn-aprobar" style="border: 0; cursor: pointer;" onclick="return confirm('¿Aprobar este periodista?')">✅ Aprobar</button>
                                </form>
                            <?php elseif ($per['estado'] === 'activo'): ?>

                                <a href="?accion=bloquear&id=<?php echo $per['id_usuario']; ?>&q=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($filtro_estado); ?>&privado=<?php echo urlencode($filtro_privado); ?>&pagina=<?php echo $pagina; ?>" 

                                   class="admin-periodistas-btn admin-periodistas-btn-bloquear">🔒 Bloquear</a>
                            <?php elseif ($per['estado'] === 'bloqueado'): ?>

                                <a href="?accion=desbloquear&id=<?php echo $per['id_usuario']; ?>&q=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($filtro_estado); ?>&privado=<?php echo urlencode($filtro_privado); ?>&pagina=<?php echo $pagina; ?>" 

                                   class="admin-periodistas-btn admin-periodistas-btn-desbloquear">🔓 Desbloquear</a>
                            <?php endif; ?>

                            
                            <?php if ($per['es_privado']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="privado_desactivar">
                                    <input type="hidden" name="id" value="<?php echo $per['id_usuario']; ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="estado" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="privado" value="<?php echo htmlspecialchars($filtro_privado, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="pagina" value="<?php echo $pagina; ?>">
                                    <button type="submit" class="admin-periodistas-btn admin-periodistas-btn-privado-off" style="border: 0; cursor: pointer;" onclick="return confirm('¿Reasignar como Articulista? Se eliminarán definitivamente sus noticias privadas y todos sus comentarios; las noticias públicas se conservarán.')">🔓 Quitar privado</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="privado_activar">
                                    <input type="hidden" name="id" value="<?php echo $per['id_usuario']; ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="estado" value="<?php echo htmlspecialchars($filtro_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="privado" value="<?php echo htmlspecialchars($filtro_privado, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="pagina" value="<?php echo $pagina; ?>">
                                    <button type="submit" class="admin-periodistas-btn admin-periodistas-btn-privado-on" style="border: 0; cursor: pointer;" onclick="return confirm('¿Activar acceso a noticias privadas?')">🔒 Dar privado</button>
                                </form>
                            <?php endif; ?>

                            
                            <a href="<?php echo route('admin_editar_periodista', ['id' => (int) $per['id_usuario']]); ?>"

                               class="admin-periodistas-btn admin-periodistas-btn-editar">✏️ Editar</a>
                            
                            <a href="<?php echo route('admin_usuarios_logueados', [
                                'modal' => 'eliminar',
                                'id' => (int) $per['id_usuario'],
                                'rol' => 'periodista',
                                'estado' => $filtro_estado,
                                'buscar' => $busqueda,
                                'pagina' => $pagina,
                            ]); ?>"

                               class="admin-periodistas-btn admin-periodistas-btn-eliminar">🗑️ Eliminar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- PAGINACIÓN -->
    <!-- ============================================ -->
    <?php if ($total_paginas > 1): ?>

        <div class="admin-periodistas-paginacion">
            <?php if ($pagina > 1): ?>

                <a href="?pagina=<?php echo $pagina - 1; ?>&q=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($filtro_estado); ?>&privado=<?php echo urlencode($filtro_privado); ?>"

                   class="admin-periodistas-pagina-btn">« Anterior</a>
            <?php endif; ?>

            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>

                <?php if ($i == $pagina): ?>

                    <span class="admin-periodistas-pagina-activo"><?php echo $i; ?></span>

                <?php else: ?>

                    <a href="?pagina=<?php echo $i; ?>&q=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($filtro_estado); ?>&privado=<?php echo urlencode($filtro_privado); ?>"

                       class="admin-periodistas-pagina-link"><?php echo $i; ?></a>

                <?php endif; ?>

            <?php endfor; ?>

            
            <?php if ($pagina < $total_paginas): ?>

                <a href="?pagina=<?php echo $pagina + 1; ?>&q=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($filtro_estado); ?>&privado=<?php echo urlencode($filtro_privado); ?>"

                   class="admin-periodistas-pagina-btn">Siguiente »</a>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    
<?php endif; ?>


<!-- ============================================ -->
<!-- MODAL PARA CONFIRMAR ACCIÓN SOBRE NOTICIAS -->
<!-- ============================================ -->
<?php

$modal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modal_accion = isset($_GET['modal']) ? $_GET['modal'] : '';
$modal_q = isset($_GET['q']) ? $_GET['q'] : '';
$modal_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$modal_privado = isset($_GET['privado']) ? $_GET['privado'] : '';
$modal_pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

if ($modal_accion && $modal_id && in_array($modal_accion, ['bloquear', 'desbloquear'], true)):
    // Obtener datos del periodista y conteo de noticias
    $stmt = $pdo->prepare("
        SELECT u.nombre, u.email, u.estado,
               COUNT(n.id_noticia) as total_noticias,
               SUM(CASE WHEN n.privada = 1 THEN 1 ELSE 0 END) as noticias_privadas
        FROM usuarios u
        LEFT JOIN noticias n ON u.id_usuario = n.id_autor
        WHERE u.id_usuario = ? AND u.rol = 'periodista'
        GROUP BY u.id_usuario
    ");
    $stmt->execute([$modal_id]);
    $periodista_modal = $stmt->fetch();
    
    if ($periodista_modal):
        $accion_texto = [
            'bloquear' => 'bloquear',
            'desbloquear' => 'desbloquear'
        ][$modal_accion] ?? 'procesar';
        
        $btn_texto = [
            'bloquear' => 'Bloquear periodista',
            'desbloquear' => 'Desbloquear periodista'
        ][$modal_accion] ?? 'Confirmar';
        
        $btn_color = 'warning';
        
        $mostrar_opciones = ($periodista_modal['total_noticias'] > 0 && $modal_accion === 'bloquear');
?>
<div class="modal-overlay" id="modalGestionPeriodista">
    <div class="modal-contenido">
        <div class="modal-header">
            <h3>⚠️ <?php echo $modal_accion === 'bloquear' ? 'Bloquear periodista' : 'Desbloquear periodista'; ?></h3>

            <button type="button" class="modal-cerrar" onclick="cerrarModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <p>
                El periodista <strong><?php echo htmlspecialchars($periodista_modal['nombre']); ?></strong> 

                (<?php echo htmlspecialchars($periodista_modal['email']); ?>)

                será <?php echo $accion_texto; ?>.

            </p>
            
            <?php if ($mostrar_opciones): ?>

                <div class="modal-alerta">
                    <strong>📰 <?php echo $periodista_modal['total_noticias']; ?> noticias</strong> 

                    (<?php echo $periodista_modal['noticias_privadas']; ?> privadas) están asociadas a este periodista.

                </div>
                
                <p>¿Qué deseas hacer con sus noticias?</p>
                
                <form method="POST" action="<?php echo route('admin_periodistas'); ?>" id="formModalPeriodista">
                    <input type="hidden" name="confirmar_accion" value="1">
                    <input type="hidden" name="accion" value="<?php echo $modal_accion; ?>">

                    <input type="hidden" name="id" value="<?php echo $modal_id; ?>">

                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($modal_q); ?>">

                    <input type="hidden" name="estado" value="<?php echo htmlspecialchars($modal_estado); ?>">

                    <input type="hidden" name="privado" value="<?php echo htmlspecialchars($modal_privado); ?>">

                    <input type="hidden" name="pagina" value="<?php echo $modal_pagina; ?>">

                    
                    <div class="modal-opciones">
                        <label class="modal-opcion">
                            <input type="radio" name="accion_noticias" value="nada" checked>
                            <div>
                                <strong>⏸️ No hacer nada</strong>
                                <small>Las noticias quedan como están</small>
                            </div>
                        </label>
                        
                        <label class="modal-opcion">
                            <input type="radio" name="accion_noticias" value="archivar">
                            <div>
                                <strong>📦 Archivar noticias</strong>
                                <small>Las noticias se archivan (ocultas, recuperables)</small>
                            </div>
                        </label>
                        
                        <label class="modal-opcion">
                            <input type="radio" name="accion_noticias" value="publicar_privadas">
                            <div>
                                <strong>🌍 Convertir privadas a públicas</strong>
                                <small>Solo las noticias privadas se vuelven visibles</small>
                            </div>
                        </label>
                        
                        <label class="modal-opcion">
                            <input type="radio" name="accion_noticias" value="ocultar">
                            <div>
                                <strong>🔒 Ocultar todas</strong>
                                <small>Todas las noticias se archivan y marcan como privadas</small>
                            </div>
                        </label>
                        
                    </div>
                    
                    <div class="modal-buttons">
                        <a href="<?php echo htmlspecialchars(route('admin_periodistas', [
                            'q' => $modal_q,
                            'estado' => $modal_estado,
                            'privado' => $modal_privado,
                            'pagina' => $modal_pagina,
                        ]), ENT_QUOTES, 'UTF-8'); ?>"

                           class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-<?php echo $btn_color; ?>">

                            <?php echo $btn_texto; ?>

                        </button>
                    </div>
                </form>
            <?php else: ?>

                <p>Este periodista no tiene noticias.</p>
                <form method="POST" action="<?php echo route('admin_periodistas'); ?>" id="formModalPeriodista">
                    <input type="hidden" name="confirmar_accion" value="1">
                    <input type="hidden" name="accion" value="<?php echo $modal_accion; ?>">

                    <input type="hidden" name="id" value="<?php echo $modal_id; ?>">

                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <input type="hidden" name="accion_noticias" value="nada">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($modal_q); ?>">

                    <input type="hidden" name="estado" value="<?php echo htmlspecialchars($modal_estado); ?>">

                    <input type="hidden" name="privado" value="<?php echo htmlspecialchars($modal_privado); ?>">

                    <input type="hidden" name="pagina" value="<?php echo $modal_pagina; ?>">

                    
                    <div class="modal-buttons">
                        <a href="<?php echo htmlspecialchars(route('admin_periodistas', [
                            'q' => $modal_q,
                            'estado' => $modal_estado,
                            'privado' => $modal_privado,
                            'pagina' => $modal_pagina,
                        ]), ENT_QUOTES, 'UTF-8'); ?>"

                           class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-<?php echo $btn_color; ?>">

                            <?php echo $btn_texto; ?>

                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function cerrarModal() {
    window.location.href = <?php echo json_encode(route('admin_periodistas', [
        'q' => $modal_q,
        'estado' => $modal_estado,
        'privado' => $modal_privado,
        'pagina' => $modal_pagina,
    ]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

}
</script>
<?php

    endif;
endif;
?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
