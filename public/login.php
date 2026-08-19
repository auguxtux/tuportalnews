<?php
declare(strict_types=1);


/**
 * PÁGINA DE LOGIN
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/logs.php';
// Si ya está logueado, redirigir según su rol y permisos
if (estaLogueado()) {
    redirigirSegunRol();
    exit;
}

$error = '';
$mensaje_registro = $_SESSION['mensaje_registro'] ?? null;
unset($_SESSION['mensaje_registro']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiarDatos(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    
    // 🟢 PASO 1: Verificar token CSRF antes de consultar bloqueos
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = '❌ Error de seguridad. Inténtalo de nuevo.';
    }
    // 🔴 PASO 2: Verificar si está bloqueado por fuerza bruta
    elseif (estaBloqueado($email)) {
        $error = '⚠️ Acceso bloqueado por seguridad. Inténtalo de nuevo más tarde.';
    } else {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        
        $auth = new Auth();
        
        if ($auth->login($email, $password)) {
            // ✅ Login exitoso - limpiar intentos fallidos
            limpiarIntentosFallidos($email);
            
            // 🆕 Registrar login exitoso en logs
            registrarLoginExitoso($email, $_SERVER['REMOTE_ADDR']);
            
            mensajeFlash('success', '¡Bienvenido ' . $_SESSION['usuario_nombre'] . '!');
            
            // Redireccionar según rol y permisos usando la función unificada
            redirigirSegunRol();
            exit;
        } else {
            // ❌ Login fallido - registrar intento
            registrarIntentoFallido($email);
            
            // 🆕 Registrar login fallido en logs
            registrarLoginFallido($email, $_SERVER['REMOTE_ADDR']);
            
            $error = $auth->getErrores()[0] ?? '❌ Email o contraseña incorrectos';
        }
    }
    
    // Limpiar token CSRF después de usarlo (¡AHORA FUERA DEL ELSE!)
    limpiarTokenCSRF();
}
$titulo_pagina = 'Iniciar Sesión';
$standalone_css = ['login.css', 'public-auth-form.css'];
require_once __DIR__ . '/../partials/standalone-header.php';

?>
<div class="public-login-container">
    
    <div class="public-login-card">
        
        <div class="public-login-header">
            <h1 class="public-login-titulo">🔐 Iniciar Sesión en <strong>TuPortalNews</strong></h1>
            <p class="public-login-desc">Accede a tu cuenta para continuar</p>
        </div>
        <?php if ($mensaje_registro): ?>

    <div class="public-login-alerta" style="background: #d1fae5; color: #065f46; border-left: 4px solid #10b981;">
        <?php echo $mensaje_registro; ?>

    </div>
<?php endif; ?>

        <?php if ($error): ?>

            <div class="public-login-alerta public-login-alerta-error">
                <?php echo $error; ?>

            </div>
        <?php endif; ?>

        
        <form method="POST" action="<?php echo route('login'); ?>" class="public-login-form">
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

            
            <div class="public-login-campo">
                <label for="email">📧 Email:</label>
                <input type="email" name="email" id="email" 
                       value="<?php echo htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''); ?>"

                       placeholder="tu@email.com"
                       required>
            </div>
            
            <div class="public-login-campo">
                <label for="password">🔒 Contraseña:</label>
                <input type="password" name="password" id="password" 
                       placeholder="Ingresa tu contraseña"
                       required>
            </div>
            
            <div class="public-login-acciones">
                <button type="submit" class="public-login-btn public-login-btn-ingresar">
                    🚪 Ingresar
                </button>
            </div>
            
            <div class="public-login-enlaces">
                <p>¿No tienes cuenta? <a href="<?php echo route('registro'); ?>" class="public-login-enlace">Regístrate aquí</a></p>

                    <p><a href="<?php echo route('recuperar_password'); ?>" class="public-login-enlace">¿Olvidaste tu contraseña?</a></p>

                <p style="text-align: center;"><a class="public-login-enlace"href="<?php echo base_url(); ?>">🏠 Ir al Inicio</a></p>

            </div>
            
        </form>
        
    </div>
    
</div><?php require_once __DIR__ . '/../partials/standalone-footer.php'; ?>
