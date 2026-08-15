<?php
declare(strict_types=1);

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso denegado - Error 403</title>
    <style>
        body { margin: 0; background: #f8fafc; color: #1f2937; font-family: Arial, sans-serif; }
        main { max-width: 640px; margin: 10vh auto; padding: 2rem; text-align: center; }
        .codigo { color: #cbd5e1; font-size: 7rem; font-weight: 700; line-height: 1; }
        a { display: inline-block; margin-top: 1rem; padding: .75rem 1.25rem; border-radius: 8px; background: #2563eb; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <div class="codigo">403</div>
    <h1>Acceso denegado</h1>
    <p>No tienes permiso para acceder a este recurso.</p>
    <a href="/">Volver al inicio</a>
</main>
</body>
</html>
