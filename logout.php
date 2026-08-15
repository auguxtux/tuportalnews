<?php
declare(strict_types=1);


/**
 * CIERRE DE SESIÓN
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/logs.php';  // ← NUEVA LÍNEA

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.';
    } else {
        // Registrar cierre de sesión en logs (si hay usuario logueado)
        if (isset($_SESSION['usuario_email'])) {
            registrarLogout($_SESSION['usuario_email']);
        }

        // Destruir la sesión
        auth()->logout();

        redireccionar(route('home'));
    }
}

$csrf_token = generarTokenCSRF();
$titulo_pagina = 'Cerrar sesión';
$standalone_css = [];
require_once __DIR__ . '/partials/standalone-header.php';
?>

<section class="logout-page">
    <h1>Cerrar sesión</h1>
    <p>¿Seguro que quieres cerrar tu sesión?</p>

    <?php if (!empty($error)): ?>
        <p role="alert" style="color: #b91c1c;">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars(route('logout'), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">Cerrar sesión</button>
        <a href="<?php echo htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>">Cancelar</a>
    </form>
</section>

<?php require_once __DIR__ . '/partials/standalone-footer.php'; ?>
