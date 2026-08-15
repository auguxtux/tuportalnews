<?php
declare(strict_types=1);


/**
 * PÁGINA DE REGISTRO DE USUARIOS
 * Permite registro como usuario normal o periodista (pendiente de aprobación)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logs.php';
iniciarSesion();  // ✅ Necesario para mensajeFlash()
// Si ya está logueado, redirigir
if (estaLogueado()) {
    redireccionar(route('home'));
}

// Verificar configuraciones
$pdo = db();

$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'permitir_registro'");
$stmt->execute();
$permitir_registro = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'permitir_registro_periodistas'");
$stmt->execute();
$permitir_periodistas = $stmt->fetchColumn();

$registro_bloqueado = ($permitir_registro != '1');
$periodistas_bloqueados = ($permitir_periodistas != '1');

$errores = [];
$datos = [
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'ciudad' => '',
    'password' => '',
    'password2' => '',
    'rol' => 'usuario'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($registro_bloqueado) {
        $errores[] = 'El registro está deshabilitado. Contacte con el administrador.';
    } else {
        if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
            $errores[] = 'Error de seguridad. Inténtalo de nuevo.';
        } else {
            $rate_dir = ROOT_PATH . 'cache' . DIRECTORY_SEPARATOR . 'registro';
            $rate_file = $rate_dir . DIRECTORY_SEPARATOR . hash('sha256', obtenerIP()) . '.json';
            $limite_registro_superado = false;

            if (!is_dir($rate_dir) && !mkdir($rate_dir, 0750, true) && !is_dir($rate_dir)) {
                error_log('No se pudo preparar el límite del formulario de registro');
            } else {
                $rate_handle = fopen($rate_file, 'c+');
                if ($rate_handle !== false && flock($rate_handle, LOCK_EX)) {
                    $contenido_rate = stream_get_contents($rate_handle);
                    $intentos_rate = json_decode($contenido_rate ?: '[]', true);
                    $intentos_rate = is_array($intentos_rate) ? $intentos_rate : [];
                    $ahora_rate = time();
                    $intentos_rate = array_values(array_filter(
                        $intentos_rate,
                        static fn($momento): bool => is_int($momento) && $momento > $ahora_rate - 600
                    ));
                    $limite_registro_superado = count($intentos_rate) >= 5;
                    if (!$limite_registro_superado) {
                        $intentos_rate[] = $ahora_rate;
                    }
                    ftruncate($rate_handle, 0);
                    rewind($rate_handle);
                    fwrite($rate_handle, json_encode($intentos_rate));
                    fflush($rate_handle);
                    flock($rate_handle, LOCK_UN);
                    fclose($rate_handle);
                    chmod($rate_file, 0640);
                } elseif ($rate_handle !== false) {
                    fclose($rate_handle);
                }
            }

            if ($limite_registro_superado) {
                $errores[] = 'Has realizado varias solicitudes. Inténtalo de nuevo más tarde.';
            }

            $datos = [
                'nombre' => limpiarDatos($_POST['nombre'] ?? ''),
                'email' => limpiarDatos($_POST['email'] ?? ''),
                'telefono' => limpiarDatos($_POST['telefono'] ?? ''),
                'ciudad' => limpiarDatos($_POST['ciudad'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'password2' => $_POST['password2'] ?? '',
                'rol' => in_array($_POST['rol'] ?? '', ['usuario', 'periodista']) ? $_POST['rol'] : 'usuario'
            ];
            $acepta_terminos = ($_POST['aceptar_terminos'] ?? '') === '1';
            
            // Si quiere ser periodista pero está bloqueado, forzar usuario
            if ($datos['rol'] === 'periodista' && $periodistas_bloqueados) {
                $datos['rol'] = 'usuario';
            }
            
            // Validaciones
            if (empty($datos['nombre'])) $errores[] = 'El nombre es obligatorio';
            if (!validarEmail($datos['email'])) $errores[] = 'Email no válido';
            if (!validarTelefono($datos['telefono'])) $errores[] = 'Teléfono no válido (9 dígitos, empieza por 6-9)';
            if (empty($datos['ciudad'])) $errores[] = 'La ciudad es obligatoria';
            if (strlen($datos['password']) < 10) $errores[] = 'La contraseña debe tener al menos 10 caracteres';
            if ($datos['password'] !== $datos['password2']) $errores[] = 'Las contraseñas no coinciden';
            if (!$acepta_terminos) $errores[] = 'Debes aceptar los términos y condiciones';
            
            if (empty($errores)) {
    $auth = new Auth();
    if ($auth->registrar($datos)) {
        // 🆕 Registrar nuevo usuario en logs
        registrarLog('registro_usuario', null, $datos['email'], "Rol: {$datos['rol']}");
        
        if ($datos['rol'] === 'periodista') {
            $_SESSION['mensaje_registro'] = '✅ Solicitud enviada. Un Admin revisará tu registro de Articulista y recibirás un email cuando sea aprobado.';
        } else {
            $_SESSION['mensaje_registro'] = '✅ Registro completado. Ya puedes iniciar sesión.';
        }
        redireccionar(route('login'));
    } else {
        $errores = array_merge($errores, $auth->getErrores());
    }
}
        }
    }
}

$titulo_pagina = 'Registro';
$standalone_css = ['public-registro.css', 'public-auth-form.css'];
require_once __DIR__ . '/../partials/standalone-header.php';
?>
<div class="public-registro-container">
    <div class="public-registro-card">
        
        <div class="public-registro-header">
            <h1>📝 Registro</h1>
            <p class="public-registro-desc">
                <?php if (!$periodistas_bloqueados): ?>

                Regístrate como usuario o solicita acceso de periodista
                <?php else: ?>

                Crea una cuenta para comentar noticias
                <?php endif; ?>

            </p>
        </div>
        
        <?php if ($registro_bloqueado): ?>

            <div class="public-registro-alerta public-registro-alerta-warning">
                <strong>⚠️ Registro deshabilitado</strong>
               <p style="text-align: center;"> El registro está temporalmente desactivado por el administrador.</p>
                <a href="<?php echo route('home'); ?>">← Volver al inicio</a>

            </div>
        <?php else: ?>

        
        <?php if (!empty($errores)): ?>

            <div class="public-registro-alerta public-registro-alerta-error">
                <ul><?php foreach ($errores as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul>

            </div>
        <?php endif; ?>

        
        <form method="POST" class="public-registro-form">
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

            
            <!-- SELECTOR DE ROL -->
            <div class="public-registro-campo">
                <label>Tipo de cuenta:</label>
                <div class="public-registro-rol-selector">
                    <label class="public-registro-rol-opcion <?php echo $datos['rol'] === 'usuario' ? 'seleccionado' : ''; ?>">

                        <input type="radio" name="rol" value="usuario" <?php echo $datos['rol'] === 'usuario' ? 'checked' : ''; ?>>

                        <span class="rol-icono">👤</span>
                        <span class="rol-titulo">Comentarista</span>
                        <span class="rol-desc">Comentar y valorar noticias</span>
                    </label>
                    
                    <?php if (!$periodistas_bloqueados): ?>

                    <label class="public-registro-rol-opcion <?php echo $datos['rol'] === 'periodista' ? 'seleccionado' : ''; ?>">

                        <input type="radio" name="rol" value="periodista" <?php echo $datos['rol'] === 'periodista' ? 'checked' : ''; ?>>

                        <span class="rol-icono">✍️</span>
                        <span class="rol-titulo">Articulista</span>
                        <span class="rol-desc">Crear noticias (requiere aprobación)</span>
                    </label>
                    <?php endif; ?>

                </div>
                <?php if ($datos['rol'] === 'periodista'): ?>

                    <small style="color: #f59e0b;">⚠️ Tu cuenta quedará pendiente de aprobación por un Admin</small>
                <?php endif; ?>

            </div>
            
            <div class="public-registro-grid-2">
                <div class="public-registro-campo">
                    <label for="nombre">👤 Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($datos['nombre']); ?>">

                </div>
                <div class="public-registro-campo">
                    <label for="email">📧 Email *</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($datos['email']); ?>">

                </div>
            </div>
            
            <div class="public-registro-grid-2">
                <div class="public-registro-campo">
                    <label for="telefono">📞 Teléfono *</label>
                    <input type="tel" id="telefono" name="telefono" required value="<?php echo htmlspecialchars($datos['telefono']); ?>"

                           pattern="[6-9][0-9]{8}" title="9 dígitos empezando por 6-9">
                </div>
                <div class="public-registro-campo">
                    <label for="ciudad">🏙️ Ciudad *</label>
                    <input type="text" id="ciudad" name="ciudad" required value="<?php echo htmlspecialchars($datos['ciudad']); ?>">

                </div>
            </div>
            
            <div class="public-registro-grid-2">
                <div class="public-registro-campo">
                    <label for="password">🔒 Contraseña *</label>
                    <input type="password" id="password" name="password" required minlength="10" placeholder="Mínimo 10 caracteres">
                </div>
                <div class="public-registro-campo">
                    <label for="password2">✅ Repetir contraseña *</label>
                    <input type="password" id="password2" name="password2" required minlength="10" placeholder="Repite la contraseña">
                </div>
            </div>
            
            <div class="public-registro-campo public-registro-checkbox">
                <label><input type="checkbox" name="aceptar_terminos" value="1" required> Acepto los <a href="<?php echo route('terminos'); ?>">términos y condiciones</a></label>

            </div>
            
            <div class="public-registro-acciones">
                <button type="submit" class="public-registro-btn public-registro-btn-registrar">
                    <?php echo $datos['rol'] === 'periodista' ? '📝 Solicitar acceso de periodista' : '📝 Registrarse'; ?>

                </button>
            </div>
        </form>
        
        <div class="public-registro-enlaces">
            <p>¿Ya tienes cuenta? <a href="<?php echo route('login'); ?>">Inicia sesión</a></p>

        </div>
        <?php endif; ?>

    </div>
</div>
<?php require_once __DIR__ . '/../partials/standalone-footer.php'; ?>
