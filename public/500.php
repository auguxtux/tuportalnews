<?php
declare(strict_types=1);

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error interno - Error 500</title>
    <style>
        body { margin: 0; background: #f8fafc; color: #1f2937; font-family: Arial, sans-serif; }
        main { max-width: 640px; margin: 10vh auto; padding: 2rem; text-align: center; }
        .codigo { color: #cbd5e1; font-size: 7rem; font-weight: 700; line-height: 1; }
        a { display: inline-block; margin-top: 1rem; padding: .75rem 1.25rem; border-radius: 8px; background: #2563eb; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <div class="codigo">500</div>
    <h1>Se ha producido un error interno</h1>
    <p>No se pudo completar la solicitud. Inténtalo de nuevo más tarde.</p>
    <a href="/">Volver al inicio</a>
</main>
</body>
</html>
