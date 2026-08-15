<?php
declare(strict_types=1);


/**
 * PÁGINA DE RESTABLECIMIENTO DE CONTRASEÑA - CORREGIDA
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

$token_parametro = $_GET['token'] ?? null;
$token = is_string($token_parametro) ? trim($token_parametro) : '';
$error = '';
$mensaje = '';
$usuario = null;
$token_valido = false;
$csrf_token = generarTokenCSRF();

$auth = auth();

// Verificar el formato generado por bin2hex(random_bytes(32)) antes de consultar.
if (validarFormatoTokenRecuperacion($token)) {
    $usuario = $auth->verificarToken($token);
    $token_valido = is_array($usuario);

    if (!$token_valido) {
        $error = 'El enlace de recuperación no es válido o ha caducado. Solicita uno nuevo.';
    }
} else {
    $error = empty($token)
        ? 'Token no proporcionado.'
        : 'El enlace de recuperación no es válido. Solicita un nuevo enlace.';
    error_log('Token de recuperación ausente o con formato no válido');
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido && $usuario) {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $password2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.';
    } elseif (strlen($password) > 4096) {
        $error = 'La contraseña supera la longitud permitida.';
    } elseif (strlen($password) < 10) {
        $error = 'La contraseña debe tener al menos 10 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        if ($auth->restablecerPassword($token, $password)) {
            $mensaje = '✅ Contraseña actualizada correctamente. Ya puedes iniciar sesión.';
            $token_valido = false;
            limpiarTokenCSRF();
        } else {
            $error = 'El enlace de recuperación ya no es válido. Solicita uno nuevo.';
            $token_valido = false;
        }
    }
}

$titulo_pagina = 'Restablecer contraseña';
$standalone_css = ['public-password-form.css'];
require_once __DIR__ . '/../partials/standalone-header.php';
?>
<div class="reset-container">
    <h1 class="reset-titulo">🔑 Restablecer contraseña</h1>
    
    <?php if ($mensaje): ?>

        <div class="mensaje-success"><?php echo $mensaje; ?></div>

        <a href="<?php echo route('login'); ?>" class="btn-volver">← Ir al inicio de sesión</a>

    <?php elseif ($error && !$token_valido): ?>

        <div class="mensaje-error"><?php echo $error; ?></div>

        <a href="<?php echo route('recuperar_password'); ?>" class="btn-volver">← Solicitar nuevo enlace</a>

    <?php elseif ($token_valido && $usuario): ?>

        <div class="info-usuario">
            <i class="fas fa-user"></i> Restableciendo contraseña para: <strong><?php echo htmlspecialchars($usuario['email']); ?></strong>

        </div>
        
        <?php if ($error): ?>

            <div class="mensaje-error"><?php echo $error; ?></div>

        <?php endif; ?>

        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="campo">
                <label>🔒 Nueva contraseña</label>
                <input type="password" name="password" required minlength="10" maxlength="4096" placeholder="Mínimo 10 caracteres">
            </div>
            <div class="campo">
                <label>✅ Confirmar contraseña</label>
                <input type="password" name="password2" required minlength="10" maxlength="4096" placeholder="Repite tu contraseña">
            </div>
            <button type="submit" class="btn-reset">🔓 Restablecer contraseña</button>
        </form>
        
        <a href="<?php echo route('login'); ?>" class="btn-volver">← Volver al inicio</a>

    <?php else: ?>

        <div class="mensaje-error"><?php echo $error ?: 'Token no válido o ausente.'; ?></div>

        <a href="<?php echo route('recuperar_password'); ?>" class="btn-volver">← Solicitar nuevo enlace</a>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/standalone-footer.php'; ?>
