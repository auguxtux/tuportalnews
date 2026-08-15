<?php
declare(strict_types=1);


/**
 * PÁGINA MOSTRADA DESPUÉS DE ELIMINAR LA CUENTA
 */

require_once __DIR__ . '/includes/bootstrap.php';

$mensaje = 'Tu cuenta ha sido eliminada correctamente';

$titulo_pagina = 'Cuenta Eliminada';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta Eliminada - <?php echo SITE_NAME; ?></title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; border-radius: 16px; padding: 2rem; max-width: 500px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .icono { font-size: 4rem; margin-bottom: 1rem; }
        h1 { color: #dc2626; margin-bottom: 1rem; }
        p { color: #4b5563; margin-bottom: 1.5rem; line-height: 1.5; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; margin-top: 1rem; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icono">🗑️</div>
        <h1>Cuenta Eliminada</h1>
        <p><?php echo htmlspecialchars($mensaje); ?></p>

        <p>Lamentamos verte partir. Si cambias de opinión, siempre puedes volver a registrarte.</p>
        <a href="<?php echo SITE_URL; ?>" class="btn">Volver al inicio</a>

    </div>
</body>
</html>
