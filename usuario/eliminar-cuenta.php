<?php
declare(strict_types=1);


/**
 * ELIMINAR CUENTA - Usuarios normales
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/eliminar-cuenta.php';

Permisos::requerirLogin();

if (!esUsuario()) {
    mensajeFlash('error', 'Esta opción corresponde únicamente a cuentas de usuario');
    redireccionar(route('home'));
    exit;
}

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$errores = [];

$stats = getEstadisticasUsuario($id_usuario, $pdo);

$stmt = $pdo->prepare("SELECT nombre, email, rol FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmar = isset($_POST['confirmar']) ? true : false;
    $csrf_valido = verificarTokenCSRF($_POST['csrf_token'] ?? '');

    if (!$csrf_valido) {
        $errores[] = 'Error de seguridad';
    }
    
    if ($csrf_valido && !$confirmar) {
        $errores[] = 'Debes marcar la casilla de confirmación';
    }

    if ($csrf_valido) {
        if (empty($password)) {
            $errores[] = 'Debes ingresar tu contraseña';
        } elseif (strlen($password) > 4096) {
            $errores[] = 'La contraseña supera la longitud permitida';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
            $hash = $stmt->fetchColumn();
            if (!password_verify($password, $hash)) {
                $errores[] = 'Contraseña incorrecta';
            }
        }
    }
    
    if (empty($errores)) {
        $resultado = eliminarCuentaCompleta($id_usuario, $pdo);
        if ($resultado['success']) {
            session_destroy();
            header('Location: ' . route('cuenta_eliminada'));
            exit;
        } else {
            $errores[] = $resultado['message'];
        }
    }
}

$titulo_pagina = 'Eliminar mi cuenta';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('usuario-eliminar-cuenta.css'); ?>">


<div class="eliminar-container">
    <div class="menu-perfil">
        <a href="<?php echo route('usuario_perfil'); ?>">👤 Mi perfil</a>
        <a href="<?php echo route('mis_comentarios'); ?>">💬 Mis comentarios</a>
        <a href="<?php echo route('usuario_eliminar_cuenta'); ?>" style="background:#fee2e2; color:#dc2626;">🗑️ Eliminar mi cuenta</a>
    </div>
    
    <div class="eliminar-card">
        <div class="eliminar-header">
            <h1>⚠️ Eliminar mi cuenta</h1>
        </div>
        
        <div class="eliminar-body">
            <?php if (!empty($errores)): ?>

                <div class="eliminar-alerta" style="background:#fee2e2; border-left-color:#dc2626;">
                    <?php foreach ($errores as $e): ?>

                        <p>❌ <?php echo $e; ?></p>

                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

            
            <div class="eliminar-stats-grid">
                <div><div class="eliminar-stat-number"><?php echo $stats['comentarios']; ?></div><div class="eliminar-stat-label">Comentarios</div></div>

                <div><div class="eliminar-stat-number"><?php echo $stats['likes_dados']; ?></div><div class="eliminar-stat-label">Likes dados</div></div>

            </div>
            
            <div class="eliminar-warning">
                <strong>🔒 Se eliminarán:</strong> comentarios, valoraciones, favoritos, reportes, avatar, notificaciones y acceso a la cuenta.
            </div>
            
            <form method="POST" onsubmit="return confirm('⚠️ ¿ESTÁS SEGURO? Esta acción es irreversible.')">
                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                
                <div class="eliminar-campo">
                    <label>🔑 Contraseña actual:</label>
                    <input type="password" name="password" required maxlength="4096">
                </div>
                
                <div class="eliminar-checkbox">
                    <input type="checkbox" name="confirmar" required>
                    <label>Entiendo que esta acción es irreversible</label>
                </div>
                
                <div class="eliminar-botones">
                    <a href="<?php echo route('usuario_perfil'); ?>" class="btn-cancelar">Cancelar</a>
                    <button type="submit" class="btn-eliminar">🗑️ Eliminar mi cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
