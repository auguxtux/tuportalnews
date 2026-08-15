<?php
declare(strict_types=1);


/**
 * HEADER UNIFICADO INTELIGENTE
 * Detecta automáticamente qué recursos necesita cada página.
 */

header_remove('X-Powered-By');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/minify.php';

// ============================================
// DETECCIÓN AUTOMÁTICA DE RECURSOS NECESARIOS
// ============================================
$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
$current_path = parse_url($current_uri, PHP_URL_PATH) ?: '/';

// TinyMCE: solo en páginas de edición/creación.
// Se respetan primero las opciones definidas por cada página.
$rutas_editor = [
    '/nueva-noticia',
    '/editar-noticia',
    '/admin/editar',
    '/admin/nueva',
    '/crear',
    '/editar',
];

if (!isset($cargar_tinymce)) {
    $cargar_tinymce = false;

    foreach ($rutas_editor as $ruta) {
        if (strpos($current_path, $ruta) !== false) {
            $cargar_tinymce = true;
            break;
        }
    }
}

if (!isset($cargar_editor_config)) {
    $cargar_editor_config = $cargar_tinymce;
}

if (!isset($cargar_comentarios_js)) {
    $cargar_comentarios_js =
        strpos($current_path, '/noticia') !== false ||
        strpos($current_path, '/comentarios') !== false ||
        strpos($current_path, '/public/noticia') !== false;
}

// URL canónica sin parámetros, salvo que la página defina una URL específica.
if (empty($url_canonica)) {
    $url_canonica = rtrim(SITE_URL, '/') . $current_path;
}

// Título seguro.
$titulo_documento = !empty($titulo_pagina)
    ? $titulo_pagina . ' - ' . SITE_NAME
    : SITE_NAME;

$meta_descripcion = trim((string) ($meta_descripcion ?? ''));
if ($meta_descripcion === '') {
    $meta_descripcion = SITE_NAME . ' - Tu fuente de noticias confiable';
}

$meta_tipo = (string) ($meta_tipo ?? 'website');
$meta_imagen = (string) ($meta_imagen ?? base_url('assets/img/logo.png'));
$meta_autor = (string) ($meta_autor ?? SITE_NAME);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="canonical" href="<?php echo htmlspecialchars($url_canonica, ENT_QUOTES, 'UTF-8'); ?>">


    <title><?php echo htmlspecialchars($titulo_documento, ENT_QUOTES, 'UTF-8'); ?></title>


    <!-- CSS global -->
    <link rel="stylesheet" href="<?php echo css_url('header.css'); ?>">

    <link rel="stylesheet" href="<?php echo css_url('general.css'); ?>">

    <link rel="stylesheet" href="<?php echo css_url('footer.css'); ?>">


    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo base_url('assets/img/favicon.ico'); ?>">


    <!-- Meta tags -->
    <meta name="description" content="<?php echo htmlspecialchars($meta_descripcion, ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="author" content="<?php echo htmlspecialchars($meta_autor, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:locale" content="es_ES">
    <meta property="og:type" content="<?php echo htmlspecialchars($meta_tipo, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($titulo_documento, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_descripcion, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($url_canonica, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($meta_imagen, ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($titulo_documento, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_descripcion, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($meta_imagen, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if (!empty($meta_fecha_publicacion)): ?>
    <meta property="article:published_time" content="<?php echo htmlspecialchars((string) $meta_fecha_publicacion, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (!empty($meta_fecha_modificacion)): ?>
    <meta property="article:modified_time" content="<?php echo htmlspecialchars((string) $meta_fecha_modificacion, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (!empty($meta_seccion)): ?>
    <meta property="article:section" content="<?php echo htmlspecialchars((string) $meta_seccion, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <?php if (!empty($datos_estructurados)): ?>
    <script type="application/ld+json"><?php echo $datos_estructurados; ?></script>
    <?php endif; ?>


    <!-- TinyMCE: solo en páginas de edición -->
    <?php if ($cargar_tinymce): ?>

        <script src="<?php echo base_url('assets/vendor/tinymce/tinymce.min.js'); ?>" defer></script>

    <?php endif; ?>


    <?php if ($cargar_editor_config): ?>

        <script src="<?php echo base_url('assets/js/editor-config.js'); ?>" defer></script>

    <?php endif; ?>


    <?php if ($cargar_comentarios_js): ?>

        <script src="<?php echo base_url('assets/js/comentarios-editor.js'); ?>" defer></script>

    <?php endif; ?>

</head>
<body>
    <a class="header-saltar-contenido" href="#contenido-principal">
        Saltar al contenido
    </a>

    <!-- MENÚ LATERAL -->
    <div class="header-menu-lateral" id="menuLateral" aria-hidden="true" inert>
        <div class="header-menu-lateral-header">
            <span class="header-menu-lateral-titulo">📋 Menú principal</span>
            <button
                type="button"
                class="header-menu-lateral-cerrar"
                id="menuCerrar"
                aria-label="Cerrar menú"
            >✕</button>
        </div>

        <div class="header-menu-lateral-contenido">
            <?php include __DIR__ . '/menu-unificado.php'; ?>

        </div>
    </div>

    <div class="header-contenedor" id="contenedorPrincipal">
        <!-- CABECERA SUPERIOR -->
        <header class="header-cabecera-superior header-sticky">
            <div class="header-navbar">
                <div class="header-left">
                    <button
                        type="button"
                        class="header-back-btn"
                        id="btnVolver"
                        data-url-inicio="<?php echo htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>"
                        title="Volver a la página anterior"
                        aria-label="Volver"
                    >
                        ← <span>Volver</span>
                    </button>

                    <div class="header-logo">
                        <a href="<?php echo base_url(); ?>" aria-label="Inicio">

                            <img
                                src="<?php echo base_url('assets/img/logo.png'); ?>"

                                alt="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>"

                                class="header-logo-img"
                            >
                        </a>
                    </div>

                    <button
                        type="button"
                        class="header-menu-btn"
                        id="menuAbrir"
                        aria-label="Abrir menú"
                        aria-controls="menuLateral"
                        aria-expanded="false"
                    >☰</button>
                </div>

                <div class="header-center">
                    <div class="header-app-title">
                        <a href="<?php echo base_url(); ?>">

                            <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>

                        </a>
                        <span class="subtitulo">Sum@mos Tod@s</span>
                    </div>
                </div>

                <div class="header-right">
                    <?php if (isset($_SESSION['usuario_id'])): ?>

                        <?php

                        $avatar = basename((string) ($_SESSION['usuario_avatar'] ?? 'default-avatar.png'));
                        $nombre = (string) ($_SESSION['usuario_nombre'] ?? 'Usuario');
                        ?>
                        <div class="header-avatar">
                            <img
                                src="<?php echo base_url('uploads/perfiles/' . rawurlencode($avatar)); ?>"

                                alt="Avatar de <?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>"

                                class="header-avatar-img"
                                onerror="this.onerror=null;this.src='<?php echo base_url('assets/img/default-avatar.png'); ?>';"

                            >
                        </div>

                        <div class="header-user-links">
                            <a
                                href="<?php echo route('logout'); ?>"

                                class="header-user-link header-link-logout"
                                onclick="return confirm('¿Cerrar sesión?')"
                            >🔓 Salir</a>
                        </div>

                        <span class="header-user-name">
                            <?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>

                        </span>
                    <?php else: ?>

                        <div class="header-user-links">
                            <a href="<?php echo route('login'); ?>" class="header-user-link header-link-login">🔑 Sesión</a>

                            <a href="<?php echo route('registro'); ?>" class="header-user-link header-link-registro">📝 Registro</a>

                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </header>

        <!-- MENSAJES FLASH -->
        <?php if ($flash = obtenerMensajeFlash()): ?>

            <?php

            $tipos_flash_permitidos = ['exito', 'error', 'aviso', 'info', 'success', 'danger', 'warning'];
            $tipo_flash = in_array($flash['tipo'] ?? '', $tipos_flash_permitidos, true)
                ? $flash['tipo']
                : 'info';
            ?>
            <div class="alerta alerta-<?php echo htmlspecialchars($tipo_flash, ENT_QUOTES, 'UTF-8'); ?>">

                <?php echo htmlspecialchars($flash['texto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>

            </div>
        <?php endif; ?>


        <!-- CONTENIDO PRINCIPAL -->
        <main class="principal" id="contenido-principal" tabindex="-1">
