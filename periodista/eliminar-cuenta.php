<?php
declare(strict_types=1);


/**
 * ELIMINAR CUENTA - Periodistas
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/eliminar-cuenta.php';

Permisos::requerirLogin();

if (!esPeriodista()) {
    mensajeFlash('error', 'Esta opción corresponde únicamente a cuentas de periodista');
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

<style>
.eliminar-container { max-width: 600px; margin: 2rem auto; padding: 1rem; }
.eliminar-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
.eliminar-header { background: #fee2e2; padding: 1.5rem; text-align: center; }
.eliminar-header h1 { color: #dc2626; margin: 0; }
.eliminar-body { padding: 1.5rem; }
.eliminar-alerta { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; }
.eliminar-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; }
.eliminar-stat-number { font-size: 1.5rem; font-weight: bold; color: #2563eb; }
.eliminar-stat-label { font-size: 0.7rem; color: #6b7280; }
.eliminar-warning { background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin: 1.5rem 0; font-size: 0.85rem; }
.eliminar-campo { margin-bottom: 1rem; }
.eliminar-campo label { display: block; margin-bottom: 0.5rem; }
.eliminar-campo input { width: 100%; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; }
.eliminar-checkbox { display: flex; align-items: center; gap: 0.75rem; margin: 1.5rem 0; padding: 1rem; background: #fef3c7; border-radius: 8px; }
.eliminar-botones { display: flex; gap: 1rem; margin-top: 1.5rem; }
.btn-cancelar { flex: 1; background: #6b7280; color: white; text-align: center; padding: 0.75rem; border-radius: 6px; text-decoration: none; }
.btn-eliminar { flex: 1; background: #dc2626; color: white; border: none; padding: 0.75rem; border-radius: 6px; cursor: pointer; }
.menu-periodista { display: flex; gap: 1rem; justify-content: center; margin-bottom: 2rem; flex-wrap: wrap; }
.menu-periodista a { background: #f3f4f6; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; color: #374151; }
</style>

<div class="eliminar-container">
    <div class="menu-periodista">
        <a href="<?php echo route('periodista_perfil'); ?>">👤 Mi perfil</a>
        <a href="<?php echo route('mis_noticias'); ?>">📝 Mis noticias</a>
        <a href="<?php echo route('mis_comentarios'); ?>">💬 Mis comentarios</a>
        <a href="<?php echo route('periodista_eliminar_cuenta'); ?>" style="background:#fee2e2; color:#dc2626;">🗑️ Eliminar mi cuenta</a>
    </div>
    
    <div class="eliminar-card">
        <div class="eliminar-header">
            <h1>⚠️ Eliminar mi cuenta</h1>
            <p style="margin-top: 0.5rem;">Como periodista, se eliminará definitivamente todo tu contenido</p>
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
                <div><div class="eliminar-stat-number"><?php echo $stats['noticias']; ?></div><div class="eliminar-stat-label">Noticias</div></div>

                <div><div class="eliminar-stat-number"><?php echo $stats['comentarios']; ?></div><div class="eliminar-stat-label">Comentarios</div></div>

                <div><div class="eliminar-stat-number"><?php echo $stats['likes_dados']; ?></div><div class="eliminar-stat-label">Likes dados</div></div>

            </div>
            
            <div class="eliminar-warning">
                <strong>🔒 Se eliminarán:</strong> noticias públicas y privadas, comentarios, imágenes, vídeos, archivos del editor, valoraciones, favoritos, reportes, avatar y acceso a la cuenta. Tus fuentes RSS se conservarán bajo administración para que sigan disponibles.
            </div>
            
            <form method="POST" onsubmit="return confirm('⚠️ ¿ESTÁS SEGURO? Se eliminarán permanentemente tu cuenta y todo su contenido asociado.')">
                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                
                <div class="eliminar-campo">
                    <label>🔑 Contraseña actual:</label>
                    <input type="password" name="password" required maxlength="4096">
                </div>
                
                <div class="eliminar-checkbox">
                    <input type="checkbox" name="confirmar" required>
                    <label>Entiendo que se eliminarán mi cuenta y todo su contenido de forma irreversible</label>
                </div>
                
                <div class="eliminar-botones">
                    <a href="<?php echo route('periodista_perfil'); ?>" class="btn-cancelar">Cancelar</a>
                    <button type="submit" class="btn-eliminar">🗑️ Eliminar mi cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
