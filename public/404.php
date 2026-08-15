<?php
declare(strict_types=1);


/**
 * PÁGINA 404 - NO ENCONTRADA
 * Se muestra cuando una URL no existe
 */

http_response_code(404);

$titulo_pagina = 'Página no encontrada - Error 404';
$mostrar_header_footer = true;

require_once __DIR__ . '/../includes/bootstrap.php';

// Intentar cargar header si existe
$header_path = __DIR__ . '/../partials/header.php';
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    ?><!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Página no encontrada</title>
       <link rel="stylesheet" href="<?php echo css_url('public-404.css'); ?>">

    </head>
    <body>
    <?php

}

// Mostrar únicamente la ruta: la consulta puede contener tokens u otros datos.
$request_uri = (string) parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? ''),
    PHP_URL_PATH
);
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer_interno = '';
$perfil_route = match ($_SESSION['usuario_rol'] ?? null) {
    'admin' => 'admin_perfil',
    'periodista' => 'periodista_perfil',
    'usuario' => 'usuario_perfil',
    default => null,
};

if ($referer !== '') {
    $referer_partes = parse_url($referer);
    $sitio_partes = parse_url(SITE_URL);

    if (
        is_array($referer_partes)
        && is_array($sitio_partes)
        && ($referer_partes['scheme'] ?? '') === ($sitio_partes['scheme'] ?? '')
        && strcasecmp(
            (string) ($referer_partes['host'] ?? ''),
            (string) ($sitio_partes['host'] ?? '')
        ) === 0
        && ($referer_partes['port'] ?? null) === ($sitio_partes['port'] ?? null)
    ) {
        $referer_interno = $referer;
    }
}
?>

<div class="error-container" style="text-align: center; max-width: 600px; margin: 0 auto; padding: 2rem;">
    <!-- Animación/Icono -->
    <div style="font-size: 6rem; margin-bottom: 1rem;">
        🔍
    </div>
    
    <!-- Código de error -->
    <div style="font-size: 8rem; font-weight: bold; color: #e5e7eb; margin-bottom: 0.5rem;">
        404
    </div>
    
    <!-- Título -->
    <h1 style="font-size: 2rem; color: #1f2937; margin-bottom: 1rem;">
        ¡Ups! Página no encontrada
    </h1>
    
    <!-- Mensaje -->
    <p style="color: #6b7280; margin-bottom: 1.5rem; font-size: 1.1rem;">
        Lo sentimos, la página que estás buscando no existe o ha sido movida.
    </p>
    
    <!-- Mostrar URL intentada (solo en desarrollo o para admin) -->
    <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin'): ?>

        <div style="background: #f3f4f6; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.8rem; word-break: break-all;">
            <strong>🔗 URL intentada:</strong> <?php echo htmlspecialchars($request_uri); ?>

        </div>
    <?php endif; ?>

    
    <!-- Botones de acción -->
    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 2rem;">
        <a href="<?php echo htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>" class="btn" style="background: #2563eb; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none;">
            🏠 Ir al inicio
        </a>
        
        <?php if ($referer_interno !== ''): ?>

            <a href="<?php echo htmlspecialchars($referer_interno, ENT_QUOTES, 'UTF-8'); ?>" class="btn" style="background: #6b7280; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none;">
                ◀ Volver atrás
            </a>
        <?php endif; ?>

        
        <a href="<?php echo htmlspecialchars(route('buscar'), ENT_QUOTES, 'UTF-8'); ?>" class="btn" style="background: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none;">
            🔍 Buscar noticias
        </a>
    </div>
    
    <!-- Buscador rápido -->
    <div style="margin-top: 1rem;">
        <form action="<?php echo htmlspecialchars(route('buscar'), ENT_QUOTES, 'UTF-8'); ?>" method="GET" style="display: flex; justify-content: center; flex-wrap: wrap; gap: 0.5rem;">
            <input type="text" name="q" placeholder="Buscar en el sitio..." 
                   style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 8px; width: 250px; max-width: 100%; outline: none;">
            <button type="submit" style="background: #2563eb; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                Buscar
            </button>
        </form>
    </div>
    
    <!-- Enlaces útiles -->
    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
        <p style="color: #9ca3af; font-size: 0.8rem; margin-bottom: 0.5rem;">📌 Enlaces rápidos:</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; font-size: 0.85rem;">
            <a href="<?php echo htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>" style="color: #3b82f6; text-decoration: none;">Inicio</a>
            <a href="<?php echo htmlspecialchars(route('listado_noticias'), ENT_QUOTES, 'UTF-8'); ?>" style="color: #3b82f6; text-decoration: none;">Noticias</a>
            <a href="<?php echo htmlspecialchars(route('categorias'), ENT_QUOTES, 'UTF-8'); ?>" style="color: #3b82f6; text-decoration: none;">Categorías</a>
            <a href="<?php echo htmlspecialchars(route('contacto'), ENT_QUOTES, 'UTF-8'); ?>" style="color: #3b82f6; text-decoration: none;">Contacto</a>
            <?php if (isset($_SESSION['usuario_id']) && $perfil_route !== null): ?>

                <a href="<?php echo htmlspecialchars(route($perfil_route), ENT_QUOTES, 'UTF-8'); ?>" style="color: #3b82f6; text-decoration: none;">Mi perfil</a>
            <?php else: ?>

                <a href="<?php echo htmlspecialchars(route('login'), ENT_QUOTES, 'UTF-8'); ?>" style="color: #3b82f6; text-decoration: none;">Iniciar sesión</a>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php

// Intentar cargar footer si existe
$footer_path = __DIR__ . '/../partials/footer.php';
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
    ?>
    </body>
    </html>
    <?php

}
?>
