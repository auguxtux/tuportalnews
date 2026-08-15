<?php
declare(strict_types=1);


/**
 * PROCESAR FORMULARIO DE CONTACTO
 * Envía el mensaje por email y guarda en BD
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/logs.php';  // ← NUEVA LÍNEA

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar(route('contacto'));
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $_SESSION['contacto_errores'] = ['Error de seguridad. Recarga el formulario.'];
    redireccionar(route('contacto'));
}

// Recoger y limpiar datos
$nombre = limpiarDatos($_POST['nombre'] ?? '');
$email = limpiarDatos($_POST['email'] ?? '');
$asunto = limpiarDatos($_POST['asunto'] ?? '');
$mensaje = limpiarDatos($_POST['mensaje'] ?? '');

$errores = [];

if (!isset($_POST['privacidad'])) {
    $errores[] = 'Debes aceptar la política de privacidad';
}

if (mb_strlen($nombre) > 100) {
    $errores[] = 'El nombre es demasiado largo';
}

if (mb_strlen($email) > 255) {
    $errores[] = 'El email es demasiado largo';
}

if (mb_strlen($asunto) > 150) {
    $errores[] = 'El asunto es demasiado largo';
}

if (mb_strlen($mensaje) > 500) {
    $errores[] = 'El mensaje no puede superar 500 caracteres';
}

// Validaciones
if (empty($nombre)) {
    $errores[] = 'El nombre es obligatorio';
}

if (!validarEmail($email)) {
    $errores[] = 'Email no válido';
}

if (empty($asunto)) {
    $errores[] = 'El asunto es obligatorio';
} elseif (strlen($asunto) < 5) {
    $errores[] = 'El asunto debe tener al menos 5 caracteres';
}

if (empty($mensaje)) {
    $errores[] = 'El mensaje es obligatorio';
} elseif (strlen($mensaje) < 10) {
    $errores[] = 'El mensaje debe tener al menos 10 caracteres';
}

// Si hay errores, volver al formulario
if (!empty($errores)) {
    $_SESSION['contacto_errores'] = $errores;
    $_SESSION['contacto_datos'] = [
        'nombre' => $nombre,
        'email' => $email,
        'asunto' => $asunto,
        'mensaje' => $mensaje
    ];
    redireccionar(route('contacto'));
}

$rateDir = ROOT_PATH . 'cache' . DIRECTORY_SEPARATOR . 'contacto';
$rateFile = $rateDir . DIRECTORY_SEPARATOR . hash('sha256', obtenerIP()) . '.json';
$limiteSuperado = false;

if (!is_dir($rateDir) && !mkdir($rateDir, 0750, true) && !is_dir($rateDir)) {
    error_log('No se pudo preparar el límite del formulario de contacto');
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
        $limiteSuperado = count($intentosRate) >= 3;
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

if ($limiteSuperado) {
    mensajeFlash('warning', 'Has enviado varios mensajes. Inténtalo de nuevo más tarde.');
    redireccionar(route('contacto'));
}

// Guardar en base de datos (opcional)
try {
    $pdo = db();
    
    // Insertar mensaje
    $stmt = $pdo->prepare("
        INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje, ip)
        VALUES (:nombre, :email, :asunto, :mensaje, :ip)
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':asunto' => $asunto,
        ':mensaje' => $mensaje,
        ':ip' => obtenerIP()
    ]);
    
    // 🆕 Registrar mensaje de contacto en logs
    registrarLog('mensaje_contacto', obtenerIP(), null, 'Mensaje de contacto recibido');
    
    $guardado = true;
    
} catch (Exception $e) {
    registrarErrorInterno('PUBLIC.CONTACTO.GUARDAR', $e);
    $guardado = false;
}

$enviado = enviarEmailContacto($nombre, $email, $asunto, $mensaje);

// Mensaje de resultado
if ($enviado && $guardado) {
    mensajeFlash('success', 'Mensaje enviado correctamente. Te responderemos a la mayor brevedad.');
} elseif ($enviado) {
    mensajeFlash('success', 'Mensaje enviado correctamente. (No se pudo guardar en BD)');
} elseif ($guardado) {
    mensajeFlash('warning', 'Mensaje guardado pero no se pudo enviar el email. Contactaremos contigo pronto.');
} else {
    mensajeFlash('error', 'Hubo un problema al enviar el mensaje. Por favor, inténtalo más tarde.');
}

redireccionar(route('contacto'));
?>
