<?php
declare(strict_types=1);


/**
 * GALERÍA DE IMÁGENES - VENTANA INDEPENDIENTE
 * Soporta imágenes locales y externas (URL)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';

$id_noticia = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$imagen_actual = isset($_GET['img']) ? (int)$_GET['img'] : 0;
$vistaPrivada = defined('VISTA_GALERIA_PRIVADA') && VISTA_GALERIA_PRIVADA === true;

if (!$id_noticia) {
    die('ID no válido');
}

if ($vistaPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    die('Noticia no encontrada');
}

$pdo = db();

// Obtener noticia
$stmt = $pdo->prepare(
    "SELECT *
     FROM noticias
     WHERE id_noticia = ?
       AND estado = 'publicada'
       AND privada = ?
     LIMIT 1"
);
$stmt->execute([$id_noticia, $vistaPrivada ? 1 : 0]);
$noticia = $stmt->fetch();

if (!$noticia) {
    http_response_code(404);
    die('Noticia no encontrada');
}

// ============================================
// CONSTRUIR ARRAY DE GALERÍA
// ============================================
$galeria = [];
$textos_imagenes = json_decode($noticia['textos_imagenes'] ?? '{}', true);

// Imagen principal (prioriza imagen_principal, si no, imagen_externa)
if (!empty($noticia['imagen_principal'])) {
    $galeria[] = [
        'archivo' => $noticia['imagen_principal'],
        'texto'   => $noticia['texto_imagen_principal'] ?? '',
        'es_local' => true
    ];
} elseif (!empty($noticia['imagen_externa'])) {
    $galeria[] = [
        'archivo' => $noticia['imagen_externa'],
        'texto'   => $noticia['texto_imagen_principal'] ?? '',
        'es_local' => false
    ];
}

// Imágenes adicionales (2..6)
for ($i = 2; $i <= 6; $i++) {
    $campo = "imagen_$i";
    if (!empty($noticia[$campo])) {
        $galeria[] = [
            'archivo' => $noticia[$campo],
            'texto'   => $textos_imagenes["img$i"] ?? '',
            'es_local' => !filter_var($noticia[$campo], FILTER_VALIDATE_URL)
        ];
    }
}

$total = count($galeria);

// Validar índice de imagen actual
if ($total == 0) {
    die('No hay imágenes en la galería');
}

if ($imagen_actual >= $total || $imagen_actual < 0) {
    $imagen_actual = 0;
}

$imagen = $galeria[$imagen_actual];

// Título para la página
$titulo_pagina = 'Galería - ' . $noticia['titulo'];

// No usar header.php porque tiene su propia estructura HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo_pagina); ?></title>

    <link rel="stylesheet" href="<?php echo css_url('public-noticias.css'); ?>">

    
</head>
<body>
<div class="galeria-container">
    <div class="galeria-header">
        <h1><?php echo htmlspecialchars($noticia['titulo']); ?></h1>

        <a href="<?php echo route($vistaPrivada ? 'privado_noticia' : 'noticia', ['id' => $id_noticia]); ?>">← Volver a la noticia</a>

    </div>
    
    <div class="galeria-img-container">
        <?php 

        $url_imagen = $imagen['es_local'] 
            ? base_url('uploads/noticias/' . $imagen['archivo'])
            : $imagen['archivo'];
        ?>
        <img src="<?php echo htmlspecialchars($url_imagen, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"

             alt="Imagen <?php echo $imagen_actual + 1; ?> de <?php echo $total; ?>"

             loading="lazy">
    </div>
    
    <?php if (!empty($imagen['texto'])): ?>

        <div class="galeria-texto"><?php echo nl2br(htmlspecialchars($imagen['texto'])); ?></div>

    <?php endif; ?>

    
    <div class="galeria-controles">
        <?php if ($imagen_actual > 0): ?>

            <a href="?id=<?php echo $id_noticia; ?>&img=<?php echo $imagen_actual - 1; ?>" class="galeria-btn">❮ Anterior</a>

        <?php else: ?>

            <span class="galeria-btn" style="opacity: 0.5; cursor: default;">❮ Anterior</span>
        <?php endif; ?>

        
        <span class="galeria-contador"><?php echo $imagen_actual + 1; ?> / <?php echo $total; ?></span>

        
        <?php if ($imagen_actual < $total - 1): ?>

            <a href="?id=<?php echo $id_noticia; ?>&img=<?php echo $imagen_actual + 1; ?>" class="galeria-btn">Siguiente ❯</a>

        <?php else: ?>

            <span class="galeria-btn" style="opacity: 0.5; cursor: default;">Siguiente ❯</span>
        <?php endif; ?>

    </div>
    
    <?php if ($total > 1): ?>

    <div class="galeria-miniaturas">
        <?php for ($i = 0; $i < $total; $i++): 

            $url_mini = $galeria[$i]['es_local'] 
                ? base_url('uploads/noticias/' . $galeria[$i]['archivo'])
                : $galeria[$i]['archivo'];
        ?>
            <a href="?id=<?php echo $id_noticia; ?>&img=<?php echo $i; ?>" 

               class="<?php echo $i === $imagen_actual ? 'active' : ''; ?>">

                <img src="<?php echo htmlspecialchars($url_mini, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Miniatura <?php echo $i+1; ?>" loading="lazy">

            </a>
        <?php endfor; ?>

    </div>
    <?php endif; ?>

</div>
</body>
</html>
