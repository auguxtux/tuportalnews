<?php
declare(strict_types=1);


/**
 * GESTIÓN DE BACKUPS
 * Solo administradores
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirAdmin();

$backup_dir = __DIR__ . '/../backups/database/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_backup') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['backup_msg'] = '❌ Error de seguridad';
    } else {
        $file = basename((string) ($_POST['backup_file'] ?? ''));
        $filepath = $backup_dir . $file;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($file !== '' && is_file($filepath) && in_array($extension, ['sql', 'zip'], true)) {
            $_SESSION['backup_msg'] = unlink($filepath)
                ? '✅ Backup eliminado'
                : '❌ No se pudo eliminar el backup';
        } else {
            $_SESSION['backup_msg'] = '❌ Backup no válido';
        }
    }

    header('Location: ' . route('admin_backups'));
    exit;
}

// Listar backups
$backups = [];
$archivos = glob($backup_dir . 'backup_*.{sql,zip}', GLOB_BRACE);
if ($archivos) {
    rsort($archivos);
    foreach ($archivos as $archivo) {
        $backups[] = [
            'nombre' => basename($archivo),
            'tamano' => filesize($archivo),
            'fecha' => date('d/m/Y H:i', filemtime($archivo))
        ];
    }
}

$mensaje = $_SESSION['backup_msg'] ?? null;
unset($_SESSION['backup_msg']);

$titulo_pagina = 'Backups';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-backups.css'); ?>">

<div class="backup-container">
    <h1>💾 Gestión de Backups</h1>
    
    <?php if ($mensaje): ?>

        <div class="backup-alerta <?php echo strpos($mensaje, '✅') !== false ? 'backup-success' : 'backup-error'; ?>">

            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>

        </div>
    <?php endif; ?>

    
    <div class="backup-card">
        <h2>🛠️ Crear Nuevo Backup</h2>
        <p>Backup completo de la base de datos (estructura + datos).</p>
        <p>Se conservan automáticamente los 5 backups completos más recientes.</p>
        <form method="POST" action="<?php echo route('admin_backup_ejecutar'); ?>" class="backup-form">
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

            <button type="submit" class="backup-btn backup-btn-primary" onclick="return confirm('¿Crear backup ahora?')">
                💾 Crear Backup Ahora
            </button>
        </form>
    </div>
    
    <div class="backup-card">
        <h2>📋 Backups Existentes (<?php echo count($backups); ?>)</h2>

        <?php if (empty($backups)): ?>

            <p class="backup-vacio">📭 No hay backups disponibles</p>
        <?php else: ?>

            <div class="backup-tabla-wrap">
                <table class="backup-tabla">
                    <thead><tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>

                        <tr>
                            <td><code><?php echo htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8'); ?></code></td>

                            <td><?php echo round($b['tamano'] / 1024, 1); ?> KB</td>

                            <td><?php echo $b['fecha']; ?></td>

                            <td class="backup-acciones">
                                <a href="<?php echo htmlspecialchars(route('admin_backup_descargar', ['file' => $b['nombre']]), ENT_QUOTES, 'UTF-8'); ?>" class="backup-btn backup-btn-small backup-btn-download">⬇️ Descargar</a>

                                <form method="POST" action="<?php echo route('admin_backups'); ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este backup?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="eliminar_backup">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="backup-btn backup-btn-small backup-btn-danger">🗑️</button>
                                </form>

                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
    <a href="<?php echo route('admin_backup_restaurar'); ?>" class="restore-backup">Restore</a>
    <div class="backup-card backup-info">
        <h3>ℹ️ Información</h3>
        <ul>
            <li>💾 Los backups contienen toda la base de datos</li>
            <li>🔒 La carpeta <code>/backups/</code> está protegida con <code>.htaccess</code></li>
            <li>📅 Se recomienda hacer backups semanales</li>
            <li>⬇️ Descarga los backups a tu ordenador</li>
        </ul>
    </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
