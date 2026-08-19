<?php
declare(strict_types=1);


/**
 * PÁGINA DE RECUPERACIÓN DE CONTRASEÑA
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$mensaje = '';
$error = '';
$email_enviado = false;
$csrf_token = generarTokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $error = 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? limpiarDatos($_POST['email']) : '';
    $ip = obtenerIP();
    $rateDir = ROOT_PATH . 'cache' . DIRECTORY_SEPARATOR . 'password-reset';
    $rateFile = $rateDir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $limiteSuperado = false;

    if (!is_dir($rateDir) && !mkdir($rateDir, 0750, true) && !is_dir($rateDir)) {
        error_log('No se pudo preparar el límite de recuperación');
    } else {
        $rateHandle = fopen($rateFile, 'c+');
        if ($rateHandle !== false && flock($rateHandle, LOCK_EX)) {
            $contenidoRate = stream_get_contents($rateHandle);
            $intentosRate = json_decode($contenidoRate ?: '[]', true);
            $intentosRate = is_array($intentosRate) ? $intentosRate : [];
            $ahoraRate = time();
            $intentosRate = array_values(array_filter(
                $intentosRate,
                static fn($momento): bool => is_int($momento) && $momento > $ahoraRate - 600
            ));
            $limiteSuperado = count($intentosRate) >= 5;
            if (!$limiteSuperado) {
                $intentosRate[] = $ahoraRate;
            }
            ftruncate($rateHandle, 0);
            rewind($rateHandle);
            fwrite($rateHandle, json_encode($intentosRate));
            fflush($rateHandle);
            flock($rateHandle, LOCK_UN);
            fclose($rateHandle);
            chmod($rateFile, 0640);
        } elseif ($rateHandle !== false) {
            fclose($rateHandle);
        }
    }
    
    if (empty($email)) {
        $error = 'Por favor, introduce tu email.';
    } elseif (!validarEmail($email)) {
        $error = 'El email no es válido.';
    } elseif ($limiteSuperado) {
        $email_enviado = true;
        $mensaje = '✅ Si el email está registrado, recibirás un enlace de recuperación.';
    } else {
        auth()->solicitarRecuperacion($email);
        $email_enviado = true;
        $mensaje = '✅ Si el email está registrado, recibirás un enlace de recuperación.';
    }
}

$titulo_pagina = 'Recuperar contraseña';
$standalone_css = ['public-password-form.css'];
require_once __DIR__ . '/../partials/standalone-header.php';

?>
<div class="recuperar-container">
    <h1 class="recuperar-titulo">🔐 Recuperar contraseña</h1>
    
    <?php if ($email_enviado && $mensaje): ?>

        <div class="mensaje-success"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>

        <a href="<?php echo route('login'); ?>" class="btn-volver">← Volver al inicio de sesión</a>

    <?php else: ?>

        <?php if ($error): ?>

            <div class="mensaje-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>

        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="campo">
                <label>📧 Correo electrónico</label>
                <input type="email" name="email" required placeholder="tu@email.com">
            </div>
            <button type="submit" class="btn-enviar">📤 Enviar enlace</button>
        </form>
        <a href="<?php echo route('login'); ?>" class="btn-volver">← Volver al inicio de sesión</a>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/standalone-footer.php'; ?>
