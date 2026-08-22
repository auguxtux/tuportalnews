<?php
declare(strict_types=1);

/**
 * Inicio del documento para formularios y confirmaciones autónomas.
 * No carga el menú, el header general, el footer ni JavaScript de navegación.
 */
header_remove('X-Powered-By');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_documento = !empty($titulo_pagina)
    ? $titulo_pagina . ' - ' . SITE_NAME
    : SITE_NAME;
$ruta_actual = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$url_canonica = rtrim(SITE_URL, '/') . ($ruta_actual !== '' ? $ruta_actual : '/');
$css_adicional = $standalone_css ?? [];
if (!is_array($css_adicional)) {
    $css_adicional = [];
}
$standalone_css = array_values(array_unique(array_merge(
    ['standalone.css'],
    $css_adicional
)));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, follow">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($url_canonica, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo base_url('assets/img/favicon.ico'); ?>">
    <title><?php echo htmlspecialchars($titulo_documento, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php foreach ($standalone_css as $archivo_css): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars(css_url($archivo_css), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>
<body class="standalone-body">
    <main class="standalone-main" id="contenido-principal">
        <a class="standalone-brand" href="<?php echo htmlspecialchars(route('home'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Volver al inicio">
            <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>
        </a>
