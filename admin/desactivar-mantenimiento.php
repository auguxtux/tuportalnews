<?php
declare(strict_types=1);


/**
 * DESACTIVAR MODO MANTENIMIENTO - FORZADO
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$archivo = ROOT_PATH . '.maintenance';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $mensaje = '❌ Error de seguridad. Recarga la página.';
    } elseif (file_exists($archivo)) {
        if (unlink($archivo)) {
            $mensaje = '✅ Modo mantenimiento DESACTIVADO correctamente.';
        } else {
            $mensaje = '❌ Error: No se pudo eliminar el archivo. Verifica permisos.';
        }
    } else {
        $mensaje = 'ℹ️ El archivo .maintenance no existe. El modo mantenimiento ya está desactivado.';
    }
}

$archivo_existe = file_exists($archivo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Desactivar Mantenimiento</title>
    <link rel="stylesheet" href="<?php echo css_url('admin-desactivar-mantenimiento.css'); ?>">

</head>
<body>
    <div class="container">
        <h1>🔧 Desactivar Modo Mantenimiento</h1>
        
        <div class="estado <?php echo $archivo_existe ? 'estado-activo' : 'estado-inactivo'; ?>">

            <strong>Estado actual:</strong>
            <?php if ($archivo_existe): ?>

                🔒 MODO MANTENIMIENTO ACTIVADO
            <?php else: ?>

                🌐 SITIO NORMAL (modo mantenimiento desactivado)
            <?php endif; ?>

        </div>
        
        <?php if ($mensaje): ?>

            <div style="padding: 0.75rem; background: #f0fdf4; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>

            </div>
        <?php endif; ?>

        
        <?php if ($archivo_existe): ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn">🔓 Desactivar Mantenimiento Ahora</button>
            </form>
        <?php else: ?>

            <p>✅ El sitio ya está funcionando normalmente.</p>
            <a href="<?php echo htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>" class="volver">← Ir al inicio</a>
        <?php endif; ?>

        
        <br>
        <a href="<?php echo htmlspecialchars(route('admin_config'), ENT_QUOTES, 'UTF-8'); ?>" class="volver" style="display: block; margin-top: 1rem;">← Volver al panel de configuración</a>
    </div>
</body>
</html>
