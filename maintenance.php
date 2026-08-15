<?php
declare(strict_types=1);


http_response_code(503);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>En mantenimiento - portalNews</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            text-align: center;
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 450px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .icon { font-size: 70px; margin-bottom: 15px; }
        h1 { color: #1e293b; font-size: 1.5rem; margin-bottom: 10px; }
        p { color: #64748b; margin-bottom: 20px; line-height: 1.5; }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Sitio en mantenimiento</h1>
        <p>Estamos realizando mejoras en el portal.<br>Volveremos en breve.</p>
        <a href="/public/login" class="btn">🔐 Acceso administrador</a>
    </div>
</body>
</html>
