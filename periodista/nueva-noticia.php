<?php
declare(strict_types=1);


/**
 * NUEVA NOTICIA - Unificada (pública o privada según permiso)
 * 
 * ============================================
 * SECCIONES DEL FORMULARIO:
 * 1. Información básica (título, subtítulo)
 * 2. Ubicación (España, Internacional, Otras, Ninguna)
 * 3. Categoría, Fuente y Opciones
 * 4. Contenido principal
 * 5. Imagen principal (local o URL externa)
 * 6. Galería de imágenes (hasta 5 adicionales)
 * 7. Video (local, YouTube o Vimeo)
 * ============================================
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/logs.php';
require_once __DIR__ . '/../includes/modules/nasa.php';
require_once __DIR__ . '/../includes/rss.php';
iniciarSesion();

// ============================================
// VERIFICAR ACCESO
// ============================================
Permisos::requerirPeriodista();

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$es_admin = Permisos::esAdmin();
$tiene_privado = usuarioEsPrivado();
$formularioPrivado = defined('FORMULARIO_NOTICIA_PRIVADA') && FORMULARIO_NOTICIA_PRIVADA === true;

if ($formularioPrivado && !$es_admin && !$tiene_privado) {
    http_response_code(404);
    exit('Contenido no disponible');
}

// ============================================
// OBTENER DATOS PARA SELECTS
// ============================================
$categorias = $pdo->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY nombre_categoria")->fetchAll();
$fuentes = $pdo->query("SELECT id_fuente, nombre FROM fuentes WHERE activa = 1 ORDER BY nombre")->fetchAll();
$provincias = $pdo->query("SELECT * FROM provincias ORDER BY nombre")->fetchAll();

$errores = [];

// ============================================
// DATOS DEL FORMULARIO
// ============================================
$datos = [
    'titulo' => limpiarDatos($_POST['titulo'] ?? ''),
    'subtitulo' => limpiarDatos($_POST['subtitulo'] ?? ''),
    'contenido' => $_POST['contenido'] ?? '',
    'fuente' => limpiarDatos($_POST['fuente'] ?? ''),
    'id_categoria' => (int)($_POST['id_categoria'] ?? 0),
    'id_fuente' => !empty($_POST['id_fuente']) ? (int)$_POST['id_fuente'] : null,
    'tipo_ubicacion' => $_POST['tipo_ubicacion'] ?? 'espana',
    'id_provincia' => (int)($_POST['id_provincia'] ?? 0),
    'lugar_internacional' => limpiarDatos($_POST['lugar_internacional'] ?? ''),
    'otras_ubicacion' => limpiarDatos($_POST['otras_ubicacion'] ?? ''),
    'privada' => $formularioPrivado ? 1 : 0,
    'permitir_comentarios' => isset($_POST['permitir_comentarios']) ? 1 : 0,
    'texto_imagen_principal' => limpiarDatos($_POST['texto_imagen_principal'] ?? '')
];

// Variables para URLs externas
$imagen_url = limpiarDatos($_POST['imagen_url'] ?? '');
$video_url = limpiarDatos($_POST['video_url'] ?? '');
$tipo_video = $_POST['tipo_video'] ?? 'local';
$medio_principal = ($_POST['medio_principal'] ?? 'imagen') === 'video' ? 'video' : 'imagen';

// Variables para archivos subidos
$imagen_principal = '';
$imagen_externa = '';
$imagenes_galeria = [];
$textos_imagenes = [];
$video_nombre = '';
$video_externo = '';
$video_embed = '';
$video_tipo_db = 'local';

// ============================================
// FUNCIONES PARA OBTENER EMBED DE VIDEOS
// ============================================
function getYouTubeEmbed($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $params);
    $video_id = $params['v'] ?? null;
    if (!$video_id) {
        preg_match('/youtu.be\/([^?]+)/', $url, $matches);
        $video_id = $matches[1] ?? null;
    }
    return $video_id ? "https://www.youtube.com/embed/$video_id" : null;
}

function getVimeoEmbed($url) {
    preg_match('/vimeo.com\/(\d+)/', $url, $matches);
    $video_id = $matches[1] ?? null;
    return $video_id ? "https://player.vimeo.com/video/$video_id" : null;
}

// ============================================
// PROCESAR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $errores[] = 'Error de seguridad. Inténtalo de nuevo.';
    } else {
        $archivos_subidos = [];
        $limpiar_archivos_subidos = static function (array $nombres_archivo): void {
            foreach ($nombres_archivo as $nombre_archivo) {
                if (!is_string($nombre_archivo) || $nombre_archivo === '' || basename($nombre_archivo) !== $nombre_archivo) {
                    continue;
                }

                $ruta_archivo = UPLOAD_NOTICIAS . $nombre_archivo;
                if (is_file($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }
        };

        // --- VALIDAR LÍMITE DE ALMACENAMIENTO ---
        $tamaño_total_bytes = 0;
        
        if (isset($_FILES['imagen_principal']['size']) && $_FILES['imagen_principal']['size'] > 0) {
            $tamaño_total_bytes += $_FILES['imagen_principal']['size'];
        }
        
        for ($i = 2; $i <= 6; $i++) {
            if (isset($_FILES["imagen_$i"]['size']) && $_FILES["imagen_$i"]['size'] > 0) {
                $tamaño_total_bytes += $_FILES["imagen_$i"]['size'];
            }
        }
        
        if (isset($_FILES['video']['size']) && $_FILES['video']['size'] > 0) {
            $tamaño_total_bytes += $_FILES['video']['size'];
        }
        
        if ($tamaño_total_bytes > 0) {
            $verificacion = verificarLimiteAlmacenamiento($_SESSION['usuario_id'], $tamaño_total_bytes);
            if (!$verificacion['permitido']) {
                $errores[] = $verificacion['mensaje'];
                goto mostrar_errores;
            }
        }
        
        // --- RECOGER DATOS DEL POST ---
        $datos['titulo'] = limpiarDatos($_POST['titulo'] ?? '');
        $datos['subtitulo'] = limpiarDatos($_POST['subtitulo'] ?? '');
        $contenido_raw = $_POST['contenido'] ?? '';
        $datos['contenido'] = sanitizarHtmlNoticia($contenido_raw);
        $datos['fuente'] = limpiarDatos($_POST['fuente'] ?? '');
        $datos['id_categoria'] = (int)($_POST['id_categoria'] ?? 0);
        $datos['tipo_ubicacion'] = $_POST['tipo_ubicacion'] ?? 'espana';
        $datos['id_provincia'] = (int)($_POST['id_provincia'] ?? 0);
        $datos['lugar_internacional'] = limpiarDatos($_POST['lugar_internacional'] ?? '');
        $datos['privada'] = $formularioPrivado ? 1 : 0;
        $datos['permitir_comentarios'] = isset($_POST['permitir_comentarios']) ? 1 : 0;
        $datos['texto_imagen_principal'] = limpiarDatos($_POST['texto_imagen_principal'] ?? '');

        $rssSeleccionado = null;
        $rssIdFuente = (int) ($_POST['rss_id_fuente'] ?? 0);
        $rssItemHash = trim((string) ($_POST['rss_item_hash'] ?? ''));
        if ($rssIdFuente > 0 || $rssItemHash !== '') {
            try {
                $rssSeleccionado = validarItemSeleccionadoRss($pdo, $rssIdFuente, $rssItemHash);
                if ($rssSeleccionado === null) {
                    $errores[] = 'La noticia RSS seleccionada ya no está disponible';
                }
            } catch (DomainException $errorRss) {
                $errores[] = $errorRss->getMessage();
            }
        }
        
        // --- VALIDACIONES BÁSICAS ---
        if ($formularioPrivado && !$es_admin && !$tiene_privado) {
            $errores[] = 'No tienes permiso para crear noticias privadas';
        }
        if (empty($datos['titulo'])) $errores[] = 'El título es obligatorio';
        if (empty($datos['contenido'])) $errores[] = 'El contenido es obligatorio';
        if ($datos['id_categoria'] <= 0) {
            $errores[] = 'Debes seleccionar una categoría';
        } else {
            $stmt_categoria = $pdo->prepare(
                'SELECT id_categoria FROM categorias WHERE id_categoria = ? AND activa = 1 LIMIT 1'
            );
            $stmt_categoria->execute([$datos['id_categoria']]);
            if (!$stmt_categoria->fetchColumn()) {
                $errores[] = 'La categoría seleccionada no está disponible';
            }
        }
        if ($rssSeleccionado !== null) {
            $datos['fuente'] = (string) $rssSeleccionado['item']['enlace'];
            $datos['id_fuente'] = null;
        } elseif ($datos['fuente'] === '') {
            $errores[] = 'Debes seleccionar una fuente';
        } else {
            $stmt_fuente = $pdo->prepare(
                'SELECT id_fuente, nombre FROM fuentes WHERE nombre = ? AND activa = 1 LIMIT 1'
            );
            $stmt_fuente->execute([$datos['fuente']]);
            $fuente_seleccionada = $stmt_fuente->fetch();

            if (!$fuente_seleccionada) {
                $errores[] = 'La fuente seleccionada no está disponible';
            } else {
                $datos['id_fuente'] = (int) $fuente_seleccionada['id_fuente'];
                $datos['fuente'] = (string) $fuente_seleccionada['nombre'];
            }
        }
        
        // Validaciones de ubicación
        if (!in_array($datos['tipo_ubicacion'], ['espana', 'internacional', 'otras'], true)) {
            $errores[] = 'Debes seleccionar una ubicación';
        } elseif ($datos['tipo_ubicacion'] === 'espana' && $datos['id_provincia'] <= 0) {
            $errores[] = 'Debes seleccionar una provincia';
        } elseif ($datos['tipo_ubicacion'] === 'internacional' && empty($datos['lugar_internacional'])) {
            $errores[] = 'Debes indicar el lugar internacional';
        } elseif ($datos['tipo_ubicacion'] === 'otras' && empty($datos['otras_ubicacion'])) {
            $errores[] = 'Debes indicar el nombre del lugar';
        }
        
        // --- PROCESAR IMAGEN PRINCIPAL ---
        $imagen_url = limpiarDatos($_POST['imagen_url'] ?? '');
        $imagen_principal = '';
        $imagen_externa = '';
        
        if (!empty($imagen_url)) {
            // Convertir URLs de servicios en la nube a enlaces directos
            $imagen_url_convertida = convertirUrlNubeDirecta($imagen_url);
            $imagen_externa = validarUrlHttpHttps($imagen_url_convertida);
            if (!$imagen_externa) {
                $errores[] = 'La URL de la imagen no es válida';
            } else {
                // Si la URL fue convertida, actualizar la variable para guardar
                $imagen_url = $imagen_url_convertida;
            }
        }
        
        elseif (!empty($_FILES['imagen_principal']['name'])) {
            $uploader = new UploadHandler($_FILES['imagen_principal'], 'noticia', 'imagen', $id_usuario);
            $resultado = $uploader->subir();
            if ($resultado !== false && $resultado !== null) {
                $imagen_principal = $resultado;
                $archivos_subidos[] = $resultado;
            } else {
                $errores[] = 'Error al subir imagen principal: ' . implode(', ', $uploader->getErrores());
            }
        }

        if ($imagen_principal === '' && $imagen_externa === '') {
            $errores[] = 'La imagen principal es obligatoria';
        }
        
        // --- PROCESAR GALERÍA ---
        $imagenes_galeria = [];
        $textos_imagenes = [];
        
        for ($i = 2; $i <= 6; $i++) {
            $campo_file = "imagen_$i";
            $campo_url = "imagen_galeria_url_$i";
            $imagen_guardada = null;
            
            if (!empty($_POST[$campo_url])) {
                $url = limpiarDatos($_POST[$campo_url]);
                // Convertir URLs de servicios en la nube a enlaces directos
                $url_convertida = convertirUrlNubeDirecta($url);
                $imagen_guardada = validarUrlHttpHttps($url_convertida);
                if (!$imagen_guardada) {
                    $errores[] = "La URL de la imagen $i no es válida";
                } else {
                    // Guardar la URL convertida en el POST (para preview y almacenamiento)
                    $_POST[$campo_url] = $url_convertida;
                }
            }

            
            elseif (!empty($_FILES[$campo_file]['name'])) {
                $uploader = new UploadHandler($_FILES[$campo_file], 'noticia', 'imagen', $id_usuario);
                $resultado = $uploader->subir();
                if ($resultado !== false && $resultado !== null) {
                    $imagen_guardada = $resultado;
                    $archivos_subidos[] = $resultado;
                } else {
                    $errores[] = "Error al subir imagen $i: " . implode(', ', $uploader->getErrores());
                }
            }
            
            if ($imagen_guardada) {
                $imagenes_galeria[$campo_file] = $imagen_guardada;
            }
            
            $texto_imagen = $_POST["texto_imagen_$i"] ?? null;
            if (is_string($texto_imagen) && trim($texto_imagen) !== '') {
                $textos_imagenes["img$i"] = limpiarDatos($texto_imagen);
            }
        }
        
        // --- PROCESAR VIDEO ---
        $tipo_video = $_POST['tipo_video'] ?? 'local';
        $video_url = limpiarDatos($_POST['video_url'] ?? '');
        $video_nombre = '';
        $video_externo = '';
        $video_embed = '';
        $video_tipo_db = 'local';
        
        if ($tipo_video === 'local' && !empty($_FILES['video']['name'])) {
            $uploader = new UploadHandler($_FILES['video'], 'noticia', 'video', $id_usuario);
            $resultado = $uploader->subir();
            if ($resultado !== false && $resultado !== null) {
                $video_nombre = $resultado;
                $video_tipo_db = 'local';
                $archivos_subidos[] = $resultado;
            } else {
                $errores[] = 'Error al subir video: ' . implode(', ', $uploader->getErrores());
            }
        } elseif ($tipo_video === 'youtube' && !empty($video_url)) {
            $video_externo = validarUrlHttpHttps($video_url);
            $video_embed = getYouTubeEmbed($video_url);
            $video_tipo_db = 'youtube';
            if (!$video_externo || !$video_embed) {
                $errores[] = 'URL de YouTube no válida';
            }
        } elseif ($tipo_video === 'vimeo' && !empty($video_url)) {
            $video_externo = validarUrlHttpHttps($video_url);
            $video_embed = getVimeoEmbed($video_url);
            $video_tipo_db = 'vimeo';
            if (!$video_externo || !$video_embed) {
                $errores[] = 'URL de Vimeo no válida';
            }
        } elseif ($tipo_video === 'nasa' && !empty($video_url)) {
            $video_externo = validarUrlHttpHttps($video_url);
            $video_tipo_db = 'nasa';
            if (!$video_externo || !esUrlMultimediaNasa($video_externo) || !preg_match('/\.mp4(?:\?|$)/i', $video_externo)) {
                $video_externo = '';
                $errores[] = 'El vídeo de NASA no es válido';
            }
        }

        if ($medio_principal === 'video') {
            if ($video_nombre === '' && $video_externo === '') {
                $errores[] = 'Selecciona un vídeo antes de marcarlo como medio principal';
            }
            if ($imagen_principal === '' && $imagen_externa === '') {
                $errores[] = 'El vídeo principal necesita una imagen de portada para las tarjetas';
            }
        }
        
        // --- GUARDAR EN BASE DE DATOS ---
        mostrar_errores:
        if (empty($errores)) {
            $noticia_guardada = false;
            try {
                $slug = generarSlug($datos['titulo']);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetchColumn() > 0) {
                    $slug = $slug . '-' . time();
                }
                
                $textos_json = json_encode($textos_imagenes);
                
                $sql = "INSERT INTO noticias (
                    titulo, slug, subtitulo, contenido, fuente,
                    imagen_principal, imagen_externa, texto_imagen_principal, medio_principal,
                    imagen_2, imagen_3, imagen_4, imagen_5, imagen_6,
                    textos_imagenes, video_nombre, video_externo, video_embed, video_tipo,
                    id_autor, id_categoria, id_fuente, id_fuente_rss, rss_item_hash, privada, permitir_comentarios,
                    tipo_ubicacion, id_provincia, lugar_internacional, otras_ubicacion,
                    estado, fecha_publicacion
                ) VALUES (
                    :titulo, :slug, :subtitulo, :contenido, :fuente,
                    :imagen_principal, :imagen_externa, :texto_imagen_principal, :medio_principal,
                    :imagen_2, :imagen_3, :imagen_4, :imagen_5, :imagen_6,
                    :textos_imagenes, :video_nombre, :video_externo, :video_embed, :video_tipo,
                    :id_autor, :id_categoria, :id_fuente, :id_fuente_rss, :rss_item_hash, :privada, :permitir_comentarios,
                    :tipo_ubicacion, :id_provincia, :lugar_internacional, :otras_ubicacion,
                    'publicada', NOW()
                )";
                                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':titulo' => $datos['titulo'],
                    ':slug' => $slug,
                    ':subtitulo' => $datos['subtitulo'],
                    ':contenido' => $datos['contenido'],
                    ':fuente' => $datos['fuente'],
                    ':imagen_principal' => $imagen_principal,
                    ':imagen_externa' => $imagen_externa,
                    ':texto_imagen_principal' => $datos['texto_imagen_principal'],
                    ':medio_principal' => $medio_principal,
                    ':imagen_2' => $imagenes_galeria['imagen_2'] ?? null,
                    ':imagen_3' => $imagenes_galeria['imagen_3'] ?? null,
                    ':imagen_4' => $imagenes_galeria['imagen_4'] ?? null,
                    ':imagen_5' => $imagenes_galeria['imagen_5'] ?? null,
                    ':imagen_6' => $imagenes_galeria['imagen_6'] ?? null,
                    ':textos_imagenes' => $textos_json,
                    ':video_nombre' => $video_nombre,
                    ':video_externo' => $video_externo,
                    ':video_embed' => $video_embed,
                    ':video_tipo' => $video_tipo_db,
                    ':id_autor' => $id_usuario,
                    ':id_categoria' => $datos['id_categoria'],
                    ':id_fuente' => $datos['id_fuente'] ?? null,
                    ':id_fuente_rss' => $rssSeleccionado['fuente']['id_fuente'] ?? null,
                    ':rss_item_hash' => $rssSeleccionado !== null ? $rssItemHash : null,
                    ':privada' => $datos['privada'],
                    ':permitir_comentarios' => $datos['permitir_comentarios'],
                    ':tipo_ubicacion' => $datos['tipo_ubicacion'],
                    ':id_provincia' => $datos['id_provincia'] ?: null,
                    ':lugar_internacional' => $datos['lugar_internacional'] ?: null,
                    ':otras_ubicacion' => $datos['otras_ubicacion'] ?: null
                ]);
                $noticia_guardada = true;

                registrarCreacionNoticia($pdo->lastInsertId(), $datos['titulo']);

                mensajeFlash('success', 'Noticia creada correctamente');
                header('Location: ' . route($formularioPrivado ? 'privado_mis_noticias' : 'mis_noticias'));
                exit;
                
            } catch (Throwable $e) {
                if (!$noticia_guardada) {
                    $limpiar_archivos_subidos($archivos_subidos);
                }
                $errores[] = 'No se pudo guardar la noticia.';
                registrarErrorInterno('PERIODISTA.NOTICIA.CREAR', $e);
            }
        } else {
            $limpiar_archivos_subidos($archivos_subidos);
        }
    }
}

$titulo_pagina = $formularioPrivado ? 'Nueva Noticia Privada' : 'Nueva Noticia Pública';
$cargar_tinymce = true;
$cargar_editor_config = true;
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('periodista-nueva-noticia.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('nasa-selector.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('rss-selector.css'); ?>">
<script defer src="<?php echo htmlspecialchars(js_url('nasa-selector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script defer src="<?php echo htmlspecialchars(js_url('rss-selector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>


<!-- ============================================ -->
<!-- CONTENEDOR PRINCIPAL -->
<!-- ============================================ -->
<div class="nueva-noticia-container">
    
    <!-- HEADER -->
    <div class="nueva-noticia-header">
        <h1 class="nueva-noticia-titulo"><?php echo $formularioPrivado ? '🔒 Nueva Noticia Privada' : '📝 Nueva Noticia Pública'; ?></h1>
        <p class="nueva-noticia-introduccion">Completa los datos esenciales, redacta el contenido y añade los recursos multimedia. Los campos marcados con * son obligatorios.</p>
        <?php if ($formularioPrivado): ?>

            <p class="nueva-noticia-info-privada">🔒 Esta noticia será accesible únicamente para usuarios con permiso privado.</p>
        <?php endif; ?>

    </div>
    
    <!-- ERRORES -->
    <?php if (!empty($errores)): ?>

        <div class="nueva-noticia-alerta-error">
            <ul class="nueva-noticia-error-list">
                <?php foreach ($errores as $error): ?>

                    <li><?php echo $error; ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <!-- FORMULARIO PRINCIPAL -->
    <form method="POST" enctype="multipart/form-data" class="nueva-noticia-form">
        <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
        <input type="hidden" name="rss_id_fuente" value="<?php echo htmlspecialchars((string) ($_POST['rss_id_fuente'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rss_item_hash" value="<?php echo htmlspecialchars((string) ($_POST['rss_item_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rss_enlace" value="<?php echo htmlspecialchars((string) ($_POST['rss_enlace'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-seccion selector-rss-acciones">
            <button type="button" class="btn btn-secondary" data-abrir-selector-rss>📡 Importar desde RSS</button>
            <span class="selector-rss-estado" data-rss-seleccion-estado hidden></span>
        </div>

        
        <!-- ============================================ -->
        <!-- BLOQUE 1: INFORMACIÓN BÁSICA -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">📋 Información básica</h2>
            <div class="form-grid-2">
                
                <div class="campo">
                    <label for="titulo">📝 Título *</label>
                    <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($datos['titulo']); ?>" required>

                </div>
                
                <div class="campo">
                    <label for="subtitulo">📄 Subtítulo</label>
                    <input type="text" id="subtitulo" name="subtitulo" value="<?php echo htmlspecialchars($datos['subtitulo']); ?>">

                </div>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BLOQUE 2: UBICACIÓN -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">📍 Ubicación</h2>
            
            <div class="radio-group">
                <label><input type="radio" name="tipo_ubicacion" value="espana" <?php echo $datos['tipo_ubicacion'] === 'espana' ? 'checked' : ''; ?>> 🇪🇸 España</label>

                <label><input type="radio" name="tipo_ubicacion" value="internacional" <?php echo $datos['tipo_ubicacion'] === 'internacional' ? 'checked' : ''; ?>> 🌍 Internacional</label>

                <label><input type="radio" name="tipo_ubicacion" value="otras" <?php echo $datos['tipo_ubicacion'] === 'otras' ? 'checked' : ''; ?>> 🗺️ Otras ubicaciones</label>

            </div>
            
            <!-- España (Provincias) -->
            <div id="provincia-container" class="ubicacion-panel" style="display: <?php echo $datos['tipo_ubicacion'] === 'espana' ? 'block' : 'none'; ?>;">

                <label for="id_provincia">🏞️ Provincia</label>
                <select id="id_provincia" name="id_provincia">
                    <option value="">Seleccionar</option>
                    <?php foreach ($provincias as $prov): ?>

                        <option value="<?php echo $prov['id_provincia']; ?>" <?php echo $datos['id_provincia'] == $prov['id_provincia'] ? 'selected' : ''; ?>>

                            <?php echo htmlspecialchars($prov['nombre']); ?>

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <!-- Internacional -->
            <div id="internacional-container" class="ubicacion-panel" style="display: <?php echo $datos['tipo_ubicacion'] === 'internacional' ? 'block' : 'none'; ?>;">

                <label for="lugar_internacional">🌎 Lugar internacional</label>
                <input type="text" id="lugar_internacional" name="lugar_internacional" value="<?php echo htmlspecialchars($datos['lugar_internacional']); ?>" placeholder="Ej: Nueva York, Londres, París...">

            </div>
            
            <!-- Otras ubicaciones -->
            <div id="otras-container" class="ubicacion-panel" style="display: <?php echo $datos['tipo_ubicacion'] === 'otras' ? 'block' : 'none'; ?>;">

                <label for="otras_ubicacion">🗺️ Nombre del lugar</label>
                <input type="text" id="otras_ubicacion" name="otras_ubicacion" value="<?php echo htmlspecialchars($datos['otras_ubicacion'] ?? ''); ?>" placeholder="Ej: Isla de Pascua, Machu Picchu, Costa Rica...">

                <small>Escribe cualquier ubicación que no esté en la lista de provincias</small>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BLOQUE 3: CATEGORÍA, FUENTE Y OPCIONES -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">📂 Clasificación</h2>
            <div class="form-grid-2">
                
                <div class="campo">
                    <label for="id_categoria">📂 Categoría *</label>
                    <div class="campo-con-boton">
                        <select id="id_categoria" name="id_categoria" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($categorias as $cat): ?>

                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $datos['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>

                                </option>
                            <?php endforeach; ?>

                        </select>
                        <button type="button" id="btnCrearCategoria" class="btn-mini">+ Nueva</button>
                    </div>
                </div>
                
                <div class="campo">
                    <label for="fuente">🔗 Fuente *</label>
                    <div class="campo-con-boton">
                        <select id="fuente" name="fuente" required>
                            <option value="">Seleccionar fuente...</option>
                            <?php foreach ($fuentes as $f): ?>

                                <option value="<?php echo htmlspecialchars($f['nombre']); ?>" <?php echo $datos['fuente'] === $f['nombre'] ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($f['nombre']); ?>

                                </option>
                            <?php endforeach; ?>

                        </select>
                        <button type="button" id="btnCrearFuente" class="btn-mini">+ Nueva</button>
                    </div>
                </div>
            </div>
            
            <div class="opciones-grid">
                <label class="checkbox">
                    <input type="checkbox" name="permitir_comentarios" <?php echo $datos['permitir_comentarios'] ? 'checked' : ''; ?>>

                    <span>💬 Permitir comentarios</span>
                </label>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BLOQUE 4: CONTENIDO -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">📰 Contenido</h2>
            <div class="campo">
                <label for="contenido">Contenido *</label>
                <textarea id="contenido" name="contenido"><?php echo htmlspecialchars($datos['contenido']); ?></textarea>

            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BLOQUE 5: IMAGEN PRINCIPAL -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">📸 Imagen principal</h2>

            <div class="radio-group" aria-label="Medio que encabezará la noticia">
                <label><input type="radio" name="medio_principal" value="imagen" <?php echo $medio_principal === 'imagen' ? 'checked' : ''; ?>> 🖼️ Mostrar primero la imagen</label>
                <label><input type="radio" name="medio_principal" value="video" <?php echo $medio_principal === 'video' ? 'checked' : ''; ?>> 🎬 Mostrar primero el vídeo</label>
            </div>
            <small>La imagen se conserva como portada para tarjetas, portada, buscadores y enlaces compartidos.</small>

            <p><button type="button" class="btn btn-secondary" data-abrir-selector-nasa>🚀 Seleccionar imágenes o vídeos de NASA</button></p>
            
            <div class="campo">
                <label class="checkbox">
                    <input type="checkbox" id="chkImagenUrl">
                    <span>🔗 Usar URL de imagen externa</span>
                </label>
            </div>
            
            <!-- Subida local -->
            <div id="panelImagenLocal" class="panel-imagen">
                <div class="form-grid-2">
                    <div class="campo">
                        <label for="imagen_principal">🖼️ Archivo local</label>
                        <input type="file" id="imagen_principal" name="imagen_principal" accept="image/*">
                    </div>
                    <div class="campo">
                        <label for="texto_imagen_principal">📝 Texto descriptivo</label>
                        <textarea id="texto_imagen_principal" name="texto_imagen_principal" rows="3"><?php echo htmlspecialchars($datos['texto_imagen_principal']); ?></textarea>

                    </div>
                </div>
            </div>
            
            <!-- URL externa -->
            <div id="panelImagenUrl" class="panel-imagen" style="display: none;">
                <div class="form-grid-2">
                    <div class="campo">
                        <label for="imagen_url">🔗 URL externa</label>
                        <input type="url" id="imagen_url" name="imagen_url" value="<?php echo htmlspecialchars($imagen_url); ?>" placeholder="https://ejemplo.com/imagen.jpg">

                        
                        <!-- AYUDA: Servicios que funcionan -->
                        <div class="ayuda-servicios" style="margin-top: 0.5rem; font-size: 0.75rem; background: #f0fdf4; padding: 0.5rem; border-radius: 8px; border-left: 3px solid #10b981;">
                            <strong>✅ Servicios que funcionan:</strong>
                            <ul style="margin: 0.25rem 0 0 1rem; padding: 0;">
                                <li>📁 <strong>Dropbox</strong> (pega el enlace compartido)</li>
                                <li>🖼️ <strong>Imgur</strong> (usa el enlace directo i.imgur.com/...)</li>
                                <li>📷 <strong>Flickr</strong> (enlace directo de la imagen)</li>
                                <li>🌐 <strong>Cualquier URL pública de imagen</strong></li>
                            </ul>
                            <strong style="color: #dc2626;">❌ No funciona:</strong> Google Drive, OneDrive (bloquean la visualización externa)
                        </div>
                        
                        <div id="previewImagenUrl" class="preview"></div>
                    </div>
                    <div class="campo">
                        <label for="texto_imagen_principal_url">📝 Texto descriptivo</label>
                        <textarea name="texto_imagen_principal" rows="3"><?php echo htmlspecialchars($datos['texto_imagen_principal']); ?></textarea>

                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BLOQUE 6: GALERÍA -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">📸 Galería (hasta 5 imágenes)</h2>
            <div class="galeria-grid">
                <?php for ($i = 2; $i <= 6; $i++): ?>
                    <?php $urlGaleriaActual = trim((string) ($_POST["imagen_galeria_url_$i"] ?? '')); ?>

                    <div class="galeria-item">
                        <h3 class="galeria-item-titulo">Imagen <?php echo $i - 1; ?></h3>

                        
                        <label class="checkbox-small">
                            <input type="checkbox" class="chkGaleriaUrl" data-img="<?php echo $i; ?>" <?php echo $urlGaleriaActual !== '' ? 'checked' : ''; ?>>

                            <span>🔗 Usar URL externa</span>
                        </label>
                        
                        <div id="divGaleriaLocal_<?php echo $i; ?>" <?php echo $urlGaleriaActual !== '' ? 'style="display: none;"' : ''; ?>>

                            <label>📁 Archivo local</label>
                            <input type="file" name="imagen_<?php echo $i; ?>" accept="image/*">

                        </div>
                        
                        <div id="divGaleriaUrl_<?php echo $i; ?>" style="display: <?php echo $urlGaleriaActual !== '' ? 'block' : 'none'; ?>;">

                            <label>🔗 URL externa</label>
                            <input type="url" name="imagen_galeria_url_<?php echo $i; ?>" value="<?php echo htmlspecialchars($urlGaleriaActual, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://ejemplo.com/imagen.jpg">

                            <div id="previewGaleriaUrl_<?php echo $i; ?>" class="preview-mini"></div>

                        </div>
                        
                        <div class="campo-texto-mini">
                            <label>📝 Texto descriptivo</label>
                            <textarea name="texto_imagen_<?php echo $i; ?>" rows="2"><?php echo htmlspecialchars($textos_imagenes["img$i"] ?? ''); ?></textarea>

                        </div>
                    </div>
                <?php endfor; ?>

            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BLOQUE 7: VIDEO -->
        <!-- ============================================ -->
        <div class="form-seccion">
            <h2 class="seccion-titulo">🎬 Video</h2>
            
            <div class="radio-group">
                <label><input type="radio" name="tipo_video" value="local" <?php echo $tipo_video === 'local' ? 'checked' : ''; ?>> 📁 Subir archivo</label>

                <label><input type="radio" name="tipo_video" value="youtube" <?php echo $tipo_video === 'youtube' ? 'checked' : ''; ?>> ▶️ YouTube</label>

                <label><input type="radio" name="tipo_video" value="vimeo" <?php echo $tipo_video === 'vimeo' ? 'checked' : ''; ?>> 🎥 Vimeo</label>

                <label><input type="radio" name="tipo_video" value="nasa" <?php echo $tipo_video === 'nasa' ? 'checked' : ''; ?>> 🚀 NASA</label>

            </div>
            
            <div id="videoLocalDiv" class="panel-video">
                <div class="campo">
                    <label for="video">🎥 Video (MP4, WebM, OGG)</label>
                    <input type="file" id="video" name="video" accept="video/*">
                </div>
            </div>
            
            <div id="videoExternoDiv" class="panel-video" style="display: none;">
                <div class="campo">
                    <label id="labelVideoUrl" for="video_url">🔗 URL del video</label>
                    <input type="url" id="video_url" name="video_url" value="<?php echo htmlspecialchars($video_url); ?>" placeholder="https://www.youtube.com/watch?v=... o https://vimeo.com/...">

                    <div id="previewVideo" class="preview"></div>
                </div>
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- BOTONES DE ACCIÓN -->
        <!-- ============================================ -->
        <div class="acciones-form">
            <button type="submit" class="btn btn-primary">📤 Publicar noticia</button>
            <a href="/periodista/mis-noticias" class="btn btn-secondary">❌ Cancelar</a>
        </div>
        
    </form>
</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
function mostrarImagenPreview(contenedor, url, ancho, alto) {
    const imagen = document.createElement('img');
    imagen.src = url;
    imagen.style.maxWidth = ancho + 'px';
    imagen.style.maxHeight = alto + 'px';
    imagen.addEventListener('error', () => imagen.remove());
    contenedor.replaceChildren(imagen);
}

function mostrarVideoPreview(contenedor, url) {
    contenedor.replaceChildren();
    if (!url) return;
    const iframe = document.createElement('iframe');
    iframe.width = '200';
    iframe.height = '113';
    iframe.src = url;
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allowfullscreen', '');
    contenedor.appendChild(iframe);
}

// Toggle imagen local vs URL
function toggleImagenUrl() {
    var chk = document.getElementById('chkImagenUrl');
    var panelLocal = document.getElementById('panelImagenLocal');
    var panelUrl = document.getElementById('panelImagenUrl');
    if (chk.checked) {
        panelLocal.style.display = 'none';
        panelUrl.style.display = 'block';
    } else {
        panelLocal.style.display = 'block';
        panelUrl.style.display = 'none';
    }
}

// Toggle video local vs externo
function toggleVideoOpciones() {
    var tipo = document.querySelector('input[name="tipo_video"]:checked').value;
    var divLocal = document.getElementById('videoLocalDiv');
    var divExterno = document.getElementById('videoExternoDiv');
    var label = document.getElementById('labelVideoUrl');
    
    if (tipo === 'local') {
        divLocal.style.display = 'block';
        divExterno.style.display = 'none';
    } else {
        divLocal.style.display = 'none';
        divExterno.style.display = 'block';
        label.textContent = tipo === 'youtube'
            ? '▶️ URL de YouTube'
            : (tipo === 'vimeo' ? '🎥 URL de Vimeo' : '🚀 URL de vídeo NASA');
        document.getElementById('previewVideo').innerHTML = '';
    }
}

// Previsualización de imagen URL principal
document.getElementById('imagen_url')?.addEventListener('input', function() {
    var preview = document.getElementById('previewImagenUrl');
    var url = this.value;
    
    if (url && url.trim() !== '') {
        var previewUrl = url;
        
        // Google Drive
        if (url.includes('drive.google.com')) {
            var driveMatch = url.match(/\/d\/([^\/]+)/);
            if (driveMatch) {
                previewUrl = 'https://drive.google.com/uc?export=download&id=' + driveMatch[1];
            }
        }
        
        // Dropbox (nuevo formato /scl/fi/)
        if (url.includes('dropbox.com/scl/fi/')) {
            var dropboxMatch = url.match(/dropbox\.com\/scl\/fi\/([^?]+)/);
            if (dropboxMatch) {
                var path = dropboxMatch[1];
                var rlkeyMatch = url.match(/rlkey=([^&]+)/);
                var rlkey = rlkeyMatch ? rlkeyMatch[1] : '';
                previewUrl = 'https://dl.dropboxusercontent.com/scl/fi/' + path;
                if (rlkey) {
                    previewUrl += '?rlkey=' + rlkey + '&dl=0';
                }
            }
        }
        
        // Dropbox (formato antiguo /s/)
        if (url.includes('dropbox.com/s/') && !url.includes('/scl/')) {
            var dropboxMatch = url.match(/dropbox\.com\/s\/([^\/]+)\/([^?]+)/);
            if (dropboxMatch) {
                previewUrl = 'https://dl.dropboxusercontent.com/s/' + dropboxMatch[1] + '/' + dropboxMatch[2] + '?raw=1';
            }
        }
        
        // Intentar cargar la imagen
        var img = new Image();
        img.onload = function() {
            mostrarImagenPreview(preview, previewUrl, 200, 100);
        };
        img.onerror = function() {
            const aviso = document.createElement('div');
            aviso.style.cssText = 'background:#fef3c7;padding:0.5rem;border-radius:4px;font-size:0.7rem;';
            aviso.append('⚠️ No se pudo cargar la imagen directamente.');
            aviso.appendChild(document.createElement('br'));
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
            enlace.style.color = '#3b82f6';
            enlace.textContent = 'Ver en ' + (url.includes('dropbox.com') ? 'Dropbox' : 'Google Drive');
            aviso.appendChild(enlace);
            preview.replaceChildren(aviso);
        };
        img.src = previewUrl;
    } else {
        preview.innerHTML = '';
    }
});

// Previsualización de video URL
document.getElementById('video_url')?.addEventListener('input', function() {
    var preview = document.getElementById('previewVideo');
    var url = this.value;
    var embed = '';
    
    if (url.includes('youtube.com') || url.includes('youtu.be')) {
        var match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&?]+)/);
        if (match) embed = 'https://www.youtube.com/embed/' + match[1];
    } else if (url.includes('vimeo.com')) {
        var match = url.match(/vimeo\.com\/(\d+)/);
        if (match) embed = 'https://player.vimeo.com/video/' + match[1];
    }
    
    mostrarVideoPreview(preview, embed);
});

// Galería toggles
document.querySelectorAll('.chkGaleriaUrl').forEach(chk => {
    chk.addEventListener('change', function() {
        var imgNum = this.getAttribute('data-img');
        var divLocal = document.getElementById('divGaleriaLocal_' + imgNum);
        var divUrl = document.getElementById('divGaleriaUrl_' + imgNum);
        if (this.checked) {
            divLocal.style.display = 'none';
            divUrl.style.display = 'block';
        } else {
            divLocal.style.display = 'block';
            divUrl.style.display = 'none';
        }
    });
});

// Previsualización de URLs de galería
for (var i = 2; i <= 6; i++) {
    (function(idx) {
        var urlInput = document.getElementById('imagen_galeria_url_' + idx);
        if (urlInput) {
            urlInput.addEventListener('input', function() {
                var preview = document.getElementById('previewGaleriaUrl_' + idx);
                if (this.value && this.value.trim()) {
                    mostrarImagenPreview(preview, this.value, 150, 80);
                } else {
                    preview.replaceChildren();
                }
            });
        }
    })(i);
}

// Preview de imágenes de galería (archivo local)
document.addEventListener('DOMContentLoaded', function() {
    for (var i = 2; i <= 6; i++) {
        (function(idx) {
            var input = document.getElementById('imagen_' + idx);
            if (input) {
                var preview = document.createElement('img');
                preview.style.display = 'none';
                preview.style.maxWidth = '150px';
                preview.style.marginTop = '0.5rem';
                input.parentNode.appendChild(preview);
                input.addEventListener('change', function() {
                    var file = this.files[0];
                    if (file) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }
        })(i);
    }
});

// Toggle entre opciones de ubicación (incluyendo 'ninguna')
document.querySelectorAll('input[name="tipo_ubicacion"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('provincia-container').style.display = this.value === 'espana' ? 'block' : 'none';
        document.getElementById('internacional-container').style.display = this.value === 'internacional' ? 'block' : 'none';
        document.getElementById('otras-container').style.display = this.value === 'otras' ? 'block' : 'none';
        // 'ninguna' - todos los paneles ocultos (ya están ocultos por defecto)
    });
});

// Estado inicial de toggles
const tipoSeleccionado = document.querySelector('input[name="tipo_ubicacion"]:checked')?.value;
if (tipoSeleccionado === 'espana') {
    document.getElementById('provincia-container').style.display = 'block';
    document.getElementById('internacional-container').style.display = 'none';
    document.getElementById('otras-container').style.display = 'none';
} else if (tipoSeleccionado === 'internacional') {
    document.getElementById('provincia-container').style.display = 'none';
    document.getElementById('internacional-container').style.display = 'block';
    document.getElementById('otras-container').style.display = 'none';
} else if (tipoSeleccionado === 'otras') {
    document.getElementById('provincia-container').style.display = 'none';
    document.getElementById('internacional-container').style.display = 'none';
    document.getElementById('otras-container').style.display = 'block';
} else if (tipoSeleccionado === 'ninguna') {
    document.getElementById('provincia-container').style.display = 'none';
    document.getElementById('internacional-container').style.display = 'none';
    document.getElementById('otras-container').style.display = 'none';
}

// Inicializar TinyMCE desde la configuración central
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnCrearCategoria')?.addEventListener('click', crearCategoria);
    document.getElementById('btnCrearFuente')?.addEventListener('click', crearFuente);
    document.getElementById('chkImagenUrl')?.addEventListener('change', toggleImagenUrl);
    document.querySelectorAll('input[name="tipo_video"]').forEach((radio) => {
        radio.addEventListener('change', toggleVideoOpciones);
    });

    if (typeof initTinyMCE === 'function') {
        initTinyMCE('#contenido');
    } else {
        console.warn('No se ha podido cargar la configuración local de TinyMCE.');
    }
});

// Crear fuente
function crearFuente() {
    var nombre = prompt('Nombre de la nueva fuente:');
    if (!nombre) return;
    fetch('/ajax/crear-fuente.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'nombre=' + encodeURIComponent(nombre) + '&csrf_token=<?php echo generarTokenCSRF(); ?>'

    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            var select = document.getElementById('fuente');
            var option = document.createElement('option');
            option.value = data.nombre;
            option.textContent = data.nombre;
            option.selected = true;
            select.appendChild(option);
            alert('✅ Fuente creada');
        } else {
            alert('❌ ' + data.error);
        }
    });
}

// Crear categoría
function crearCategoria() {
    var nombre = prompt('Nombre de la nueva categoría:');
    if (!nombre) return;
    fetch('/ajax/crear-categoria.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'nombre=' + encodeURIComponent(nombre) + '&csrf_token=<?php echo generarTokenCSRF(); ?>'

    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            var select = document.getElementById('id_categoria');
            var option = document.createElement('option');
            option.value = data.id;
            option.textContent = data.nombre;
            option.selected = true;
            select.appendChild(option);
            alert('✅ Categoría creada');
        } else {
            alert('❌ ' + data.error);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
