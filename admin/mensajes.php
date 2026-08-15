<?php
declare(strict_types=1);


/**
 * GESTIÓN DE MENSAJES DE CONTACTO
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
Permisos::requerirAdmin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', 'Error de seguridad');
        redireccionar(route('admin_mensajes'));
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && $accion === 'marcar_leido') {
        $stmt = $pdo->prepare("UPDATE mensajes_contacto SET leido = 1 WHERE id_mensaje = :id");
        $stmt->execute([':id' => $id]);
    } elseif ($id > 0 && $accion === 'eliminar') {
        $stmt = $pdo->prepare("DELETE FROM mensajes_contacto WHERE id_mensaje = :id");
        $stmt->execute([':id' => $id]);
        mensajeFlash('success', 'Mensaje eliminado');
    }

    redireccionar(route('admin_mensajes'));
}

// Obtener mensajes
$stmt = $pdo->query("
    SELECT * FROM mensajes_contacto 
    ORDER BY 
        leido ASC,
        fecha_envio DESC
");
$mensajes = $stmt->fetchAll();

$titulo_pagina = 'Mensajes de Contacto';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-mensajes.css'); ?>">


<h1 class="titulo">Mensajes de Contacto</h1>

<?php if (empty($mensajes)): ?>

    <div class="alerta alerta-info">
        <p>No hay mensajes de contacto</p>
    </div>
<?php else: ?>

    
    <!-- Grid de tarjetas responsive -->
    <div class="mensajes-grid">
        <?php foreach ($mensajes as $msg): ?>

            <div class="mensaje-card <?php echo $msg['leido'] ? 'leido' : 'no-leido'; ?>">

                <div class="mensaje-header">
                    <div class="mensaje-avatar">
                        <div class="avatar-inicial">
                            <?php echo htmlspecialchars(strtoupper(substr((string) $msg['nombre'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>

                        </div>
                    </div>
                    <div class="mensaje-info">
                        <h3 class="mensaje-nombre">
                            <?php echo htmlspecialchars($msg['nombre']); ?>

                        </h3>
                        <div class="mensaje-email">
                            <span class="email-icon">📧</span>
                            <?php echo htmlspecialchars($msg['email']); ?>

                        </div>
                    </div>
                    <div class="mensaje-estado">
                        <?php if (!$msg['leido']): ?>

                            <span class="badge badge-no-leido">🔴 No leído</span>
                        <?php else: ?>

                            <span class="badge badge-leido">✅ Leído</span>
                        <?php endif; ?>

                    </div>
                </div>
                
                <div class="mensaje-asunto">
                    <span class="asunto-icon">📌</span>
                    <strong>Asunto:</strong> 
                    <?php echo htmlspecialchars($msg['asunto']); ?>

                </div>
                
                <div class="mensaje-contenido">
                    <?php echo nl2br(htmlspecialchars($msg['mensaje'])); ?>

                </div>
                
                <div class="mensaje-metadata">
                    <div class="metadata-item">
                        <span class="metadata-icon">📅</span>
                        <?php echo formatearFecha($msg['fecha_envio']); ?>

                    </div>
                    <div class="metadata-item">
                        <span class="metadata-icon">🌐</span>
                        IP: <?php echo htmlspecialchars((string) $msg['ip'], ENT_QUOTES, 'UTF-8'); ?>

                    </div>
                </div>
                
                <div class="mensaje-footer">
                    <div class="mensaje-acciones">
                        <?php if (!$msg['leido']): ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Marcar este mensaje como leído?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="marcar_leido">
                                <input type="hidden" name="id" value="<?php echo (int) $msg['id_mensaje']; ?>">
                                <button type="submit" class="btn btn-success">✓ Marcar leído</button>
                            </form>
                        <?php endif; ?>

                        
                        <a href="<?php echo htmlspecialchars('mailto:' . $msg['email'] . '?subject=' . rawurlencode('Re: ' . $msg['asunto']), ENT_QUOTES, 'UTF-8'); ?>"

                           class="btn btn-primary"
                           target="_blank"
                           rel="noopener noreferrer">
                            📧 Responder
                        </a>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este mensaje permanentemente?\nEsta acción no se puede deshacer.')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo (int) $msg['id_mensaje']; ?>">
                            <button type="submit" class="btn btn-danger">🗑️ Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
    
    <!-- Contador de mensajes -->
    <div class="mensajes-stats">
        <?php 

        $total_no_leidos = count(array_filter($mensajes, function($msg) { return !$msg['leido']; }));
        $total_leidos = count(array_filter($mensajes, function($msg) { return $msg['leido']; }));
        ?>
        <div class="stat-item">
            <span class="stat-valor"><?php echo count($mensajes); ?></span>

            <span class="stat-label">Total</span>
        </div>
        <div class="stat-item">
            <span class="stat-valor"><?php echo $total_no_leidos; ?></span>

            <span class="stat-label">No leídos</span>
        </div>
        <div class="stat-item">
            <span class="stat-valor"><?php echo $total_leidos; ?></span>

            <span class="stat-label">Leídos</span>
        </div>
    </div>
    
<?php endif; ?>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
