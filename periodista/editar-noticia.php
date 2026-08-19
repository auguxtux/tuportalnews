<?php
declare(strict_types=1);


/**
 * EDITAR NOTICIA - Con soporte para imagen externa (URL) y video externo (YouTube/Vimeo)
 * AHORA CON SOPORTE PARA "OTRAS UBICACIONES" Y "NINGUNA"
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/logs.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/modules/nasa.php';
require_once __DIR__ . '/../includes/rss.php';
Permisos::requerirPeriodista();

$formularioPrivado = defined('FORMULARIO_EDITAR_NOTICIA_PRIVADA')
    && FORMULARIO_EDITAR_NOTICIA_PRIVADA === true;
$id_usuario = (int) $_SESSION['usuario_id'];
$rutaListado = $formularioPrivado ? 'privado_mis_noticias' : 'mis_noticias';
$rutaEditor = $formularioPrivado ? 'privado_editar_noticia' : 'editar_noticia';
$rutaVista = $formularioPrivado ? 'privado_noticia' : 'noticia';

if ($formularioPrivado && !usuarioEsPrivado()) {
    http_response_code(404);
    exit('Contenido no disponible');
}

$id_noticia = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_noticia) {
    mensajeFlash('error', 'ID no válido');
    redireccionar(route($rutaListado));
}

$pdo = db();

try {
    // Obtener noticia
    $stmt = $pdo->prepare("SELECT n.*, c.nombre_categoria 
                           FROM noticias n
                           JOIN categorias c ON n.id_categoria = c.id_categoria
                           WHERE n.id_noticia = ?
                             AND n.privada = ?");
    $stmt->execute([$id_noticia, $formularioPrivado ? 1 : 0]);
    $noticia = $stmt->fetch();
    
    if (!$noticia) {
        mensajeFlash('error', 'Noticia no encontrada');
        redireccionar(route($rutaListado));
    }
    
    // Verificar permisos
    if (!Permisos::puedeEditarNoticia($noticia['id_autor'])) {
        mensajeFlash('error', 'No tienes permiso');
        redireccionar(route($rutaListado));
    }
    
    // Obtener datos para selects
    $categorias = $pdo->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY nombre_categoria")->fetchAll();
    $provincias = $pdo->query("SELECT * FROM provincias ORDER BY nombre")->fetchAll();
    $fuentes = $pdo->query("SELECT id_fuente, nombre FROM fuentes WHERE activa = 1 ORDER BY nombre")->fetchAll();
    $regiones = $pdo->query("SELECT id_region, nombre FROM regiones WHERE activa = 1 ORDER BY nombre")->fetchAll();

    $errores = [];
    $datos = $noticia;
    $medio_principal = ($noticia['medio_principal'] ?? 'imagen') === 'video' ? 'video' : 'imagen';
    
    // Funciones auxiliares para obtener embed (si no existen en global)
    if (!function_exists('getYouTubeEmbed')) {
        function getYouTubeEmbed($url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            $video_id = $params['v'] ?? null;
            if (!$video_id) {
                preg_match('/youtu.be\/([^?]+)/', $url, $matches);
                $video_id = $matches[1] ?? null;
            }
            return $video_id ? "https://www.youtube.com/embed/$video_id" : null;
        }
    }
    if (!function_exists('getVimeoEmbed')) {
        function getVimeoEmbed($url) {
            preg_match('/vimeo.com\/(\d+)/', $url, $matches);
            $video_id = $matches[1] ?? null;
            return $video_id ? "https://player.vimeo.com/video/$video_id" : null;
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
            mensajeFlash('error', 'Error de seguridad. Inténtalo de nuevo.');
            redireccionar(route($rutaEditor, ['id' => $id_noticia]));
        }

        // Recoger datos del POST
        $datos = [
            'titulo' => limpiarDatos($_POST['titulo'] ?? ''),
            'subtitulo' => limpiarDatos($_POST['subtitulo'] ?? ''),
            'contenido' => sanitizarHtmlNoticia((string) ($_POST['contenido'] ?? '')),
            'id_categoria' => (int)($_POST['id_categoria'] ?? 0),
            'id_fuente' => !empty($_POST['id_fuente']) ? (int)$_POST['id_fuente'] : null,
            'estado' => $_POST['estado'] ?? 'borrador',
            'fuente' => '',
            'texto_imagen_principal' => limpiarDatos($_POST['texto_imagen_principal'] ?? ''),
            'privada' => $formularioPrivado ? 1 : 0,
            'permitir_comentarios' => isset($_POST['permitir_comentarios']) ? 1 : 0,
            'tipo_ubicacion' => $_POST['tipo_ubicacion'] ?? 'espana',
            'id_provincia' => (int)($_POST['id_provincia'] ?? 0),
            'lugar_internacional' => limpiarDatos($_POST['lugar_internacional'] ?? ''),
            'otras_ubicacion' => limpiarDatos($_POST['otras_ubicacion'] ?? ''),
            'id_region' => (int)($_POST['id_region'] ?? 0) ?: null,
        ];
        $medio_principal = ($_POST['medio_principal'] ?? 'imagen') === 'video' ? 'video' : 'imagen';

        $rssSeleccionado = null;
        $reemplazarDesdeRss = ($_POST['rss_reemplazar'] ?? '') === '1';
        $rssIdFuente = (int) ($_POST['rss_id_fuente'] ?? 0);
        $rssItemHash = trim((string) ($_POST['rss_item_hash'] ?? ''));
        if ($reemplazarDesdeRss) {
            try {
                $rssSeleccionado = validarItemSeleccionadoRss($pdo, $rssIdFuente, $rssItemHash, $id_noticia);
                if ($rssSeleccionado === null) {
                    $errores[] = 'La noticia RSS seleccionada ya no está disponible';
                }
            } catch (DomainException $errorRss) {
                $errores[] = $errorRss->getMessage();
            }
        }

        $archivos_nuevos = [];
        $archivos_anteriores = [];
        $eliminar_archivos_locales = static function (array $nombres_archivo): void {
            foreach (array_unique($nombres_archivo) as $nombre_archivo) {
                if (!is_string($nombre_archivo) || $nombre_archivo === '' || basename($nombre_archivo) !== $nombre_archivo) {
                    continue;
                }

                $ruta_archivo = UPLOAD_NOTICIAS . $nombre_archivo;
                if (is_file($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }
        };
        
        // ============================================
        // ✅ VALIDAR LÍMITE DE ALMACENAMIENTO DEL USUARIO
        // ============================================
        $tamaño_total_bytes = 0;
        
        if (isset($_FILES['imagen']['size']) && $_FILES['imagen']['size'] > 0 && 
            (!isset($_POST['eliminar_imagen_principal']) || $_POST['eliminar_imagen_principal'] !== '1')) {
            $tamaño_total_bytes += $_FILES['imagen']['size'];
        }
        
        if (isset($_FILES['video']['size']) && $_FILES['video']['size'] > 0 && 
            (!isset($_POST['eliminar_video']) || $_POST['eliminar_video'] !== '1')) {
            $tamaño_total_bytes += $_FILES['video']['size'];
        }
        
        for ($i = 2; $i <= 6; $i++) {
            $campo_file = "imagen_galeria_$i";
            if (isset($_FILES[$campo_file]['size']) && $_FILES[$campo_file]['size'] > 0 &&
                (!isset($_POST["eliminar_imagen_$i"]) || $_POST["eliminar_imagen_$i"] !== '1')) {
                $tamaño_total_bytes += $_FILES[$campo_file]['size'];
            }
        }
        
        if ($tamaño_total_bytes > 0) {
            $verificacion = verificarLimiteAlmacenamiento($_SESSION['usuario_id'], $tamaño_total_bytes);
            if (!$verificacion['permitido']) {
                $errores[] = $verificacion['mensaje'];
                goto mostrar_errores_editar;
            }
        }
        
        // Validaciones básicas
        if (empty($datos['titulo'])) $errores[] = 'El título es obligatorio';
        if (empty($datos['contenido'])) $errores[] = 'El contenido es obligatorio';
        if (!$datos['id_categoria']) {
            $errores[] = 'Selecciona una categoría';
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
            $datos['fuente'] = extraerDominioFuente((string) $rssSeleccionado['item']['enlace']);
            $datos['id_fuente'] = null;
        } elseif (empty($datos['id_fuente'])) {
            $errores[] = 'Debes seleccionar una fuente';
        } else {
            $stmt_fuente = $pdo->prepare(
                'SELECT id_fuente, nombre FROM fuentes WHERE id_fuente = ? AND activa = 1 LIMIT 1'
            );
            $stmt_fuente->execute([(int) $datos['id_fuente']]);
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
        
        // ============================================
        // 1. IMAGEN PRINCIPAL (local o URL externa)
        // ============================================
        $imagen_principal = $noticia['imagen_principal'];
        $imagen_externa = $noticia['imagen_externa'] ?? '';
        
        $tipo_imagen = $_POST['tipo_imagen'] ?? 'local';
        $imagen_url = limpiarDatos($_POST['imagen_url'] ?? '');
        
        if (isset($_POST['eliminar_imagen_principal']) && $_POST['eliminar_imagen_principal'] === '1') {
            if ($noticia['imagen_principal']) {
                $archivos_anteriores[] = $noticia['imagen_principal'];
            }
            $imagen_principal = null;
            $imagen_externa = null;
        } elseif ($tipo_imagen === 'url' && !empty($imagen_url)) {
            // Usar URL externa - convertir servicios en la nube
            $imagen_url_convertida = convertirUrlNubeDirecta($imagen_url);
            $imagen_externa = validarUrlHttpHttps($imagen_url_convertida);
            $imagen_principal = null;
            if (!$imagen_externa) {
                $errores[] = 'La URL de la imagen no es válida';
            } else {
                $imagen_url = $imagen_url_convertida;
                if ($noticia['imagen_principal']) {
                    $archivos_anteriores[] = $noticia['imagen_principal'];
                }
            }
        } elseif ($tipo_imagen === 'local' && isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $upload = new UploadHandler($_FILES['imagen'], 'noticia', 'imagen', $id_usuario);
            $nueva_imagen = $upload->subir();
            if ($nueva_imagen) {
                if ($noticia['imagen_principal']) {
                    $archivos_anteriores[] = $noticia['imagen_principal'];
                }
                $archivos_nuevos[] = $nueva_imagen;
                $imagen_principal = $nueva_imagen;
                $imagen_externa = null;
            } else {
                $errores = array_merge($errores, $upload->getErrores());
            }
        }
        
        // ============================================
        // 2. VIDEO (local, YouTube o Vimeo)
        // ============================================
        $video_nombre = $noticia['video_nombre'];
        $video_externo = $noticia['video_externo'] ?? '';
        $video_embed = $noticia['video_embed'] ?? '';
        $video_tipo_db = $noticia['video_tipo'] ?? 'local';
        
        $tipo_video = $_POST['tipo_video'] ?? 'local';
        $video_url = limpiarDatos($_POST['video_url'] ?? '');
        
        if (isset($_POST['eliminar_video']) && $_POST['eliminar_video'] === '1') {
            if ($noticia['video_nombre']) {
                $archivos_anteriores[] = $noticia['video_nombre'];
            }
            $video_nombre = null;
            $video_externo = null;
            $video_embed = null;
            $video_tipo_db = 'local';
        } elseif ($tipo_video === 'subir' && isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $upload = new UploadHandler($_FILES['video'], 'noticia', 'video', $id_usuario);
            $nuevo_video = $upload->subir();
            if ($nuevo_video) {
                if ($noticia['video_nombre']) {
                    $archivos_anteriores[] = $noticia['video_nombre'];
                }
                $archivos_nuevos[] = $nuevo_video;
                $video_nombre = $nuevo_video;
                $video_externo = null;
                $video_embed = null;
                $video_tipo_db = 'local';
            } else {
                $errores = array_merge($errores, $upload->getErrores());
            }
        } elseif ($tipo_video === 'externo' && !empty($video_url)) {
            $video_externo = validarUrlHttpHttps($video_url);
            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                $video_embed = getYouTubeEmbed($video_url);
                $video_tipo_db = 'youtube';
            } elseif (strpos($video_url, 'vimeo.com') !== false) {
                $video_embed = getVimeoEmbed($video_url);
                $video_tipo_db = 'vimeo';
            } elseif (esUrlMultimediaNasa($video_url) && preg_match('/\.mp4(?:\?|$)/i', $video_url)) {
                $video_embed = null;
                $video_tipo_db = 'nasa';
            } else {
                $video_externo = null;
                $errores[] = 'El proveedor del vídeo externo no está permitido';
            }
            $video_nombre = null;
            if (!$video_externo) {
                $errores[] = 'La URL del video no es válida';
            } elseif ($noticia['video_nombre']) {
                $archivos_anteriores[] = $noticia['video_nombre'];
            }
        }

        if ($medio_principal === 'video') {
            if (empty($video_nombre) && empty($video_externo)) {
                $errores[] = 'Selecciona un vídeo antes de marcarlo como medio principal';
            }
            if (empty($imagen_principal) && empty($imagen_externa)) {
                $errores[] = 'El vídeo principal necesita una imagen de portada para las tarjetas';
            }
        }

        if (empty($imagen_principal) && empty($imagen_externa)) {
            $errores[] = 'La imagen principal es obligatoria';
        }
        
        // ============================================
        // 3. GALERÍA DE IMÁGENES (local o URL externa)
        // ============================================
        for ($i = 2; $i <= 6; $i++) {
            $campo_file = "imagen_galeria_$i";
            $campo_url = "imagen_galeria_url_$i";
            $imagen_guardada = $noticia["imagen_$i"] ?? null;
            
            if (isset($_POST["eliminar_imagen_$i"]) && $_POST["eliminar_imagen_$i"] === '1') {
                if ($noticia["imagen_$i"]) {
                    if (!filter_var($noticia["imagen_$i"], FILTER_VALIDATE_URL)) {
                        $archivos_anteriores[] = $noticia["imagen_$i"];
                    }
                }
                $imagen_guardada = null;
            } 
            
            elseif (!empty($_POST[$campo_url])) {
                $url = limpiarDatos($_POST[$campo_url]);
                // Convertir URLs de servicios en la nube a enlaces directos
                $url_convertida = convertirUrlNubeDirecta($url);
                $imagen_guardada = validarUrlHttpHttps($url_convertida);
                if (!$imagen_guardada) {
                    $errores[] = "La URL de la imagen $i no es válida";
                } else {
                    $_POST[$campo_url] = $url_convertida;
                    if ($noticia["imagen_$i"] && !filter_var($noticia["imagen_$i"], FILTER_VALIDATE_URL)) {
                        $archivos_anteriores[] = $noticia["imagen_$i"];
                    }
                }
            }
            
            elseif (!empty($_FILES[$campo_file]['name'])) {
                $upload = new UploadHandler($_FILES[$campo_file], 'noticia', 'imagen', $id_usuario);
                $nueva_imagen = $upload->subir();
                if ($nueva_imagen) {
                    $imagen_guardada = $nueva_imagen;
                    $archivos_nuevos[] = $nueva_imagen;
                    if ($noticia["imagen_$i"] && !filter_var($noticia["imagen_$i"], FILTER_VALIDATE_URL)) {
                        $archivos_anteriores[] = $noticia["imagen_$i"];
                    }
                } else {
                    $errores = array_merge($errores, $upload->getErrores());
                }
            }
            
            ${"imagen_$i"} = $imagen_guardada;
        }
        
        // Textos de la galería
        $textos_imagenes = [];
        for ($i = 2; $i <= 6; $i++) {
            $texto_imagen = $_POST["texto_imagen_$i"] ?? null;
            if (is_string($texto_imagen) && trim($texto_imagen) !== '') {
                $textos_imagenes["img$i"] = limpiarDatos($texto_imagen);
            }
        }
        $textos_json = !empty($textos_imagenes) ? json_encode($textos_imagenes, JSON_UNESCAPED_UNICODE) : null;
        
        // ============================================
        // GUARDAR EN BASE DE DATOS
        // ============================================
        mostrar_errores_editar:
        if (empty($errores)) {
            try {
                $slug = generarSlug($datos['titulo']);
                $slug_original = $slug;
                $contador = 1;
                while (true) {
                    $stmt = $pdo->prepare("SELECT id_noticia FROM noticias WHERE slug = ? AND id_noticia != ?");
                    $stmt->execute([$slug, $id_noticia]);
                    if (!$stmt->fetch()) break;
                    $slug = $slug_original . '-' . $contador;
                    $contador++;
                }
                
                $sql = "UPDATE noticias SET 
                        titulo = ?, slug = ?, subtitulo = ?, contenido = ?, fuente = ?,
                        texto_imagen_principal = ?, medio_principal = ?, privada = ?, permitir_comentarios = ?,
                        imagen_principal = ?, imagen_externa = ?,
                        imagen_2 = ?, imagen_3 = ?, imagen_4 = ?, imagen_5 = ?, imagen_6 = ?,
                        textos_imagenes = ?, video_nombre = ?, video_externo = ?, video_embed = ?, video_tipo = ?,
                        id_categoria = ?, id_region = ?, id_fuente = ?, id_fuente_rss = ?, rss_item_hash = ?, estado = ?,
                        tipo_ubicacion = ?, id_provincia = ?, lugar_internacional = ?, otras_ubicacion = ?,
                        fecha_actualizacion = NOW()
                        WHERE id_noticia = ?";
                
                $stmt = $pdo->prepare($sql);
                $resultado = $stmt->execute([
                    $datos['titulo'], $slug, $datos['subtitulo'] ?: null, $datos['contenido'], $datos['fuente'],
                    $datos['texto_imagen_principal'] ?? null, $medio_principal, $datos['privada'], $datos['permitir_comentarios'],
                    $imagen_principal ?? null, $imagen_externa ?? null,
                    $imagen_2 ?? null, $imagen_3 ?? null, $imagen_4 ?? null, $imagen_5 ?? null, $imagen_6 ?? null,
                    $textos_json, $video_nombre ?? null, $video_externo ?? null, $video_embed ?? null, $video_tipo_db ?? 'local',
                    $datos['id_categoria'], $datos['id_region'], $datos['id_fuente'] ?? null,
                    $rssSeleccionado['fuente']['id_fuente'] ?? ($noticia['id_fuente_rss'] ?? null),
                    $rssSeleccionado !== null ? $rssItemHash : ($noticia['rss_item_hash'] ?? null),
                    $datos['estado'],
                    $datos['tipo_ubicacion'], $datos['id_provincia'] ?: null, $datos['lugar_internacional'] ?: null,
                    $datos['otras_ubicacion'] ?: null,
                    $id_noticia
                ]);
                
                if ($resultado) {
                    $eliminar_archivos_locales($archivos_anteriores);
                    registrarEdicionNoticia($id_noticia, $datos['titulo']);
                    mensajeFlash('success', 'Noticia actualizada correctamente');
                    redireccionar(route($rutaListado));
                } else {
                    $eliminar_archivos_locales($archivos_nuevos);
                    $errores[] = 'Error al actualizar la noticia';
                }
                
            } catch (Throwable $e) {
                $eliminar_archivos_locales($archivos_nuevos);
                $errores[] = 'No se pudo guardar la noticia.';
                registrarErrorInterno('PERIODISTA.NOTICIA.EDITAR', $e);
            }
        } else {
            $eliminar_archivos_locales($archivos_nuevos);
        }
    }
    
} catch (Exception $e) {
    $error = 'No se pudo cargar o procesar la noticia.';
    registrarErrorInterno('PERIODISTA.NOTICIA.CARGA', $e);
}

$titulo_pagina = $formularioPrivado ? 'Editar Noticia Privada' : 'Editar Noticia Pública';
$cargar_tinymce = true;
$cargar_editor_config = true;
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('periodista-editar-noticia.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('nasa-selector.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('rss-selector.css'); ?>">
<script defer src="<?php echo htmlspecialchars(js_url('nasa-selector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script defer src="<?php echo htmlspecialchars(js_url('rss-selector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>


<h1 class="editar-noticia-titulo"><?php echo $formularioPrivado ? '🔒 Editar Noticia Privada' : '📝 Editar Noticia Pública'; ?></h1>
<p class="editar-noticia-introduccion">Revisa la información antes de guardar. Los campos marcados con * son obligatorios y los recursos actuales se conservan salvo que los sustituyas o elimines.</p>

<?php if (isset($error)): ?>

    <div class="editar-noticia-alerta editar-noticia-alerta-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>

<?php endif; ?>


<?php if (!empty($errores)): ?>

    <div class="editar-noticia-alerta editar-noticia-alerta-error">
        <ul><?php foreach ($errores as $e) echo "<li>$e</li>"; ?></ul>

    </div>
<?php endif; ?>


<form method="POST" enctype="multipart/form-data" class="editar-noticia-formulario">
    <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
    <input type="hidden" name="rss_id_fuente" value="<?php echo htmlspecialchars((string) ($_POST['rss_id_fuente'] ?? $noticia['id_fuente_rss'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="rss_item_hash" value="<?php echo htmlspecialchars((string) ($_POST['rss_item_hash'] ?? $noticia['rss_item_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="rss_enlace" value="<?php echo htmlspecialchars((string) ($_POST['rss_enlace'] ?? $noticia['fuente'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="rss_reemplazar" value="<?php echo ($_POST['rss_reemplazar'] ?? '') === '1' ? '1' : '0'; ?>">

    <div class="editar-noticia-campo-form selector-rss-acciones">
        <button type="button" class="btn btn-secondary" data-abrir-selector-rss>📡 Reemplazar desde RSS</button>
        <span class="selector-rss-estado" data-rss-seleccion-estado hidden></span>
    </div>

    
    <div class="editar-noticia-grid-2">
        <div class="editar-noticia-columna">
            <div class="editar-noticia-campo-form">
                <label>Título *</label>
                <input type="text" name="titulo" required value="<?php echo htmlspecialchars($datos['titulo']); ?>">

            </div>
            
            <div class="editar-noticia-campo-form">
                <label>Subtítulo</label>
                <input type="text" name="subtitulo" value="<?php echo htmlspecialchars($datos['subtitulo'] ?? ''); ?>">

            </div>
            
            <div class="editar-noticia-campo-form">
                <label>📍 Ubicación</label>
                <div class="editar-noticia-radio-group">
                    <label><input type="radio" name="tipo_ubicacion" value="espana" <?php echo ($datos['tipo_ubicacion'] ?? 'espana') === 'espana' ? 'checked' : ''; ?>> 🇪🇸 España</label>

                    <label><input type="radio" name="tipo_ubicacion" value="internacional" <?php echo ($datos['tipo_ubicacion'] ?? '') === 'internacional' ? 'checked' : ''; ?>> 🌍 Internacional</label>

                    <label><input type="radio" name="tipo_ubicacion" value="otras" <?php echo ($datos['tipo_ubicacion'] ?? '') === 'otras' ? 'checked' : ''; ?>> 🗺️ Otras ubicaciones</label>

                </div>
                
                <div id="provincia-container" style="margin-top: 1rem; <?php echo ($datos['tipo_ubicacion'] ?? 'espana') !== 'espana' ? 'display:none;' : ''; ?>">

                    <label>🏞️ Provincia</label>
                    <select name="id_provincia">
                        <option value="">Seleccionar</option>
                        <?php foreach ($provincias as $prov): ?>

                            <option value="<?php echo $prov['id_provincia']; ?>" <?php echo ($datos['id_provincia'] == $prov['id_provincia']) ? 'selected' : ''; ?>>

                                <?php echo htmlspecialchars($prov['nombre']); ?>

                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>
                
                <div id="internacional-container" style="margin-top: 1rem; <?php echo ($datos['tipo_ubicacion'] ?? '') !== 'internacional' ? 'display:none;' : ''; ?>">

                    <label>🌎 Lugar internacional</label>
                    <input type="text" name="lugar_internacional" value="<?php echo htmlspecialchars($datos['lugar_internacional'] ?? ''); ?>" placeholder="Ej: Nueva York, Londres, París...">

                </div>
                
                <!-- Otras ubicaciones -->
                <div id="otras-container" style="margin-top: 1rem; <?php echo ($datos['tipo_ubicacion'] ?? '') !== 'otras' ? 'display:none;' : ''; ?> padding: 0.75rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #8b5cf6;">

                    <label>🗺️ Nombre del lugar</label>
                    <input type="text" name="otras_ubicacion" value="<?php echo htmlspecialchars($datos['otras_ubicacion'] ?? ''); ?>" placeholder="Ej: Isla de Pascua, Machu Picchu, Costa Rica...">

                    <small>Escribe cualquier ubicación que no esté en la lista de provincias</small>
                </div>
            </div>
        </div>
        
        <div class="editar-noticia-columna">
            <div class="editar-noticia-campo-form">
                <label>Categoría *</label>
                <select name="id_categoria" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($categorias as $cat): ?>

                        <option value="<?php echo $cat['id_categoria']; ?>"

                            <?php echo $datos['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>

                            <?php echo $cat['nombre_categoria']; ?>

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <div class="editar-noticia-campo-form">
                <label>📍 Región</label>
                <select name="id_region">
                    <option value="">Sin región</option>
                    <?php foreach ($regiones as $region): ?>
                        <option
                            value="<?php echo (int) $region['id_region']; ?>"
                            <?php echo ($datos['id_region'] ?? 0) == $region['id_region'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($region['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="editar-noticia-campo-form">
                <label for="id_fuente">📰 Fuente *</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <select name="id_fuente" id="id_fuente" required style="flex: 1;">
                        <option value="">Selecciona una fuente...</option>
                        <?php foreach ($fuentes as $f): ?>
                            <option value="<?php echo (int) $f['id_fuente']; ?>"
                                <?php echo ($datos['id_fuente'] ?? '') == $f['id_fuente'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($f['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btnCrearFuente" class="btn-mini">+ Nueva</button>
                </div>
                <?php if (!empty($noticia['id_fuente_rss'])): ?>
                    <small>Fuente RSS original.</small>
                <?php endif; ?>
            </div>
            
            <div class="editar-noticia-campo-form">
                <label>Estado</label>
                <select name="estado">
                    <option value="borrador" <?php echo ($datos['estado'] ?? '') == 'borrador' ? 'selected' : ''; ?>>📝 Borrador</option>

                    <option value="publicada" <?php echo ($datos['estado'] ?? '') == 'publicada' ? 'selected' : ''; ?>>🌐 Publicada</option>

                    <option value="pendiente" <?php echo ($datos['estado'] ?? '') == 'pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>

                </select>
            </div>
            
            <div class="editar-noticia-campo-form">
                <label class="editar-noticia-checkbox-label">
                    <input type="checkbox" name="permitir_comentarios" <?php echo ($datos['permitir_comentarios'] ?? 1) ? 'checked' : ''; ?>>

                    💬 Permitir comentarios
                </label>
            </div>
        </div>
    </div>
    
    <!-- ======================================== -->
    <!-- IMAGEN PRINCIPAL (con opción local o URL) -->
    <!-- ======================================== -->
    <div class="editar-noticia-campo-form">
        <label>📸 Imagen principal</label>
        
        <div class="imagen-opciones">
            <label>
                <input type="radio" name="tipo_imagen" value="local" <?php echo (empty($noticia['imagen_externa']) && !empty($noticia['imagen_principal'])) ? 'checked' : (empty($noticia['imagen_externa']) ? 'checked' : ''); ?>>

                📁 Subir archivo local
            </label>
            <label>
                <input type="radio" name="tipo_imagen" value="url" <?php echo !empty($noticia['imagen_externa']) ? 'checked' : ''; ?>>

                🔗 URL externa
            </label>
        </div>
        
        <div id="panelImagenLocal">
            <?php if ($noticia['imagen_principal']): ?>

                <div class="editar-noticia-imagen-actual">
                    <img src="<?php echo htmlspecialchars(base_url('uploads/noticias/' . $noticia['imagen_principal']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="max-width:200px">

                    <label>
                        <input type="checkbox" name="eliminar_imagen_principal" value="1">
                        Eliminar esta imagen
                    </label>
                </div>
            <?php endif; ?>

            <input type="file" name="imagen" accept="image/*">
            <small class="ayuda">Máximo 10MB. Formatos: JPG, PNG, GIF, WEBP</small>
        </div>
        
        <!-- Imagen URL externa -->
        <div id="panelImagenUrl" style="display: none;">
            <?php if ($noticia['imagen_externa']): ?>

                <div class="editar-noticia-imagen-actual">
                    <img src="<?php echo htmlspecialchars($noticia['imagen_externa'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="max-width:200px">

                    <label>
                        <input type="checkbox" name="eliminar_imagen_principal" value="1">
                        Eliminar esta imagen
                    </label>
                </div>
            <?php endif; ?>

            <input type="url" name="imagen_url" id="imagen_url" value="<?php echo htmlspecialchars($noticia['imagen_externa'] ?? ''); ?>" 

                   placeholder="https://ejemplo.com/imagen.jpg" style="width:100%">
            
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
        
        <div style="margin-top: 0.5rem;">
            <label>Texto descriptivo:</label>
            <textarea name="texto_imagen_principal" rows="3"><?php echo htmlspecialchars($noticia['texto_imagen_principal'] ?? ''); ?></textarea>

        </div>
    </div>
    
    <!-- ======================================== -->
    <!-- VIDEO (local o externo) -->
    <!-- ======================================== -->
    <div class="editar-noticia-campo-form">
        <label>🎬 Video</label>

        <p><button type="button" class="btn btn-secondary" data-abrir-selector-nasa>🚀 Seleccionar imágenes o vídeos de NASA</button></p>

        <div class="video-opciones">
            <label><input type="radio" name="medio_principal" value="imagen" <?php echo $medio_principal === 'imagen' ? 'checked' : ''; ?>> 🖼️ Imagen primero</label>
            <label><input type="radio" name="medio_principal" value="video" <?php echo $medio_principal === 'video' ? 'checked' : ''; ?>> 🎬 Vídeo primero</label>
        </div>
        <small class="ayuda">La imagen seguirá utilizándose como portada en tarjetas y listados.</small>
        
        <div class="video-opciones">
            <label>
                <input type="radio" name="tipo_video" value="subir" <?php echo (!empty($noticia['video_nombre']) && empty($noticia['video_externo'])) ? 'checked' : (empty($noticia['video_externo']) ? 'checked' : ''); ?>>

                📤 Subir video al servidor
            </label>
            <label>
                <input type="radio" name="tipo_video" value="externo" <?php echo !empty($noticia['video_externo']) ? 'checked' : ''; ?>>

                🔗 Video externo (YouTube, Vimeo o NASA)
            </label>
        </div>
        
        <div id="panelVideoLocal">
            <?php if (!empty($noticia['video_nombre'])): ?>

                <div class="video-actual">
                    <video controls width="250">
                        <source src="<?php echo htmlspecialchars(base_url('uploads/noticias/' . $noticia['video_nombre']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                    </video>
                    <label>
                        <input type="checkbox" name="eliminar_video" value="1">
                        Eliminar este video
                    </label>
                </div>
            <?php endif; ?>

            <input type="file" name="video" accept="video/mp4,video/webm,video/ogg">
            <small class="ayuda">Máximo 100MB. Formatos: MP4, WEBM, OGG, MOV</small>
        </div>
        
        <div id="panelVideoExterno" style="display: none;">
            <?php if (!empty($noticia['video_embed'])): ?>

                <div class="video-externo-actual">
                    <iframe src="<?php echo htmlspecialchars($noticia['video_embed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" width="250" height="140" frameborder="0" allowfullscreen></iframe>

                    <label>
                        <input type="checkbox" name="eliminar_video" value="1">
                        Eliminar este video
                    </label>
                </div>
            <?php elseif (!empty($noticia['video_externo']) && empty($noticia['video_embed'])): ?>

                <div class="video-externo-actual">
                    <p>URL: <?php echo htmlspecialchars($noticia['video_externo']); ?></p>

                    <label>
                        <input type="checkbox" name="eliminar_video" value="1">
                        Eliminar este video
                    </label>
                </div>
            <?php endif; ?>

            <input type="url" name="video_url" id="video_url" value="<?php echo htmlspecialchars($noticia['video_externo'] ?? ''); ?>" 

                   placeholder="URL de YouTube, Vimeo o NASA" style="width:100%">
            <div id="previewVideo" class="preview"></div>
            <small class="ayuda">Pega la URL de YouTube, Vimeo o selecciona un vídeo del catálogo NASA</small>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- GALERÍA DE IMÁGENES (local o URL externa) -->
    <!-- ============================================ -->
    <?php

    $textos_json = json_decode($noticia['textos_imagenes'] ?? '{}', true);
    ?>
    <div class="editar-noticia-campo-form">
        <label>📸 Galería de imágenes (máximo 5)</label>
        <div class="editar-noticia-grid-galeria-edicion">
            <?php for ($i = 2; $i <= 6; $i++): 

                $imagen_actual = $noticia["imagen_$i"] ?? null;
                $texto_actual = $textos_json["img$i"] ?? '';
                $src_actual = null;
                if ($imagen_actual) {
                    $src_actual = (filter_var($imagen_actual, FILTER_VALIDATE_URL)) 
                                  ? $imagen_actual 
                                  : base_url('uploads/noticias/' . $imagen_actual);
                }
            ?>
            <div class="editar-noticia-item-galeria-edicion">
                <div class="editar-noticia-cabecera-item">
                    <strong>Imagen <?php echo $i - 1; ?></strong>

                    <?php if ($imagen_actual): ?>

                        <label>
                            <input type="checkbox" name="eliminar_imagen_<?php echo $i; ?>" value="1">

                            Eliminar
                        </label>
                    <?php endif; ?>

                </div>
                
                <div class="editar-noticia-campo">
                    <label>
                        <input type="checkbox" class="chkGaleriaUrl" data-img="<?php echo $i; ?>"> 

                        🔗 Usar URL externa
                    </label>
                </div>
                
                <div id="divGaleriaLocal_<?php echo $i; ?>">

                    <?php if ($imagen_actual && !filter_var($imagen_actual, FILTER_VALIDATE_URL)): ?>

                        <div class="editar-noticia-imagen-actual-preview">
                            <img src="<?php echo htmlspecialchars($src_actual, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                        </div>
                    <?php elseif ($imagen_actual && filter_var($imagen_actual, FILTER_VALIDATE_URL)): ?>

                    <?php else: ?>

                        <div class="editar-noticia-imagen-actual-preview vacia">Sin imagen</div>
                    <?php endif; ?>

                    <label>Cambiar imagen:</label>
                    <input type="file" name="imagen_galeria_<?php echo $i; ?>" accept="image/jpeg,image/png,image/gif,image/webp">

                </div>
                
                <div id="divGaleriaUrl_<?php echo $i; ?>" style="display: none;">

                    <?php if ($imagen_actual && filter_var($imagen_actual, FILTER_VALIDATE_URL)): ?>

                        <div class="editar-noticia-imagen-actual-preview">
                            <img src="<?php echo htmlspecialchars($src_actual, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

                        </div>
                    <?php endif; ?>

                    <label>URL externa:</label>
                    <input type="url" name="imagen_galeria_url_<?php echo $i; ?>" id="imagen_galeria_url_<?php echo $i; ?>" 

                           value="<?php echo htmlspecialchars(filter_var($imagen_actual, FILTER_VALIDATE_URL) ? $imagen_actual : ''); ?>" 

                           placeholder="https://ejemplo.com/imagen.jpg" style="width:100%">
                    <div id="previewGaleriaUrl_<?php echo $i; ?>" class="preview"></div>

                </div>
                
                <div class="editar-noticia-campo-texto-imagen">
                    <label>Texto descriptivo:</label>
                    <textarea name="texto_imagen_<?php echo $i; ?>" rows="2" placeholder="Describe esta imagen..."><?php echo htmlspecialchars($texto_actual); ?></textarea>

                </div>
            </div>
            <?php endfor; ?>

        </div>
    </div>
    
    <!-- ======================================== -->
    <!-- CONTENIDO -->
    <!-- ======================================== -->
    <div class="editar-noticia-campo-form">
        <label>Contenido *</label>
        <textarea id="contenido" name="contenido"><?php echo htmlspecialchars(
            html_entity_decode((string) $datos['contenido'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?></textarea>

    </div>
    
    <!-- ======================================== -->
    <!-- BOTONES -->
    <!-- ======================================== -->
    <div class="editar-noticia-acciones-form">
        <button type="submit" class="editar-noticia-btn editar-noticia-btn-principal">💾 Guardar cambios</button>
        <a href="<?php echo route($rutaListado); ?>" class="editar-noticia-btn editar-noticia-btn-secundario">❌ Cancelar</a>

        <a href="<?php echo htmlspecialchars(route($rutaVista, ['id' => $id_noticia]), ENT_QUOTES, 'UTF-8'); ?>" class="editar-noticia-btn editar-noticia-btn-secundario" target="_blank" rel="noopener noreferrer">👁️ Ver noticia</a>

    </div>
</form>

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

// ============================================
// 1. FUNCIONES DE TOGGLE Y PREVISUALIZACIÓN
// ============================================

function toggleUbicacion() {
    const seleccionado = document.querySelector('input[name="tipo_ubicacion"]:checked');
    if (!seleccionado) return;
    const provinciaContainer = document.getElementById('provincia-container');
    const internacionalContainer = document.getElementById('internacional-container');
    const otrasContainer = document.getElementById('otras-container');
    
    if (seleccionado.value === 'espana') {
        if (provinciaContainer) provinciaContainer.style.display = 'block';
        if (internacionalContainer) internacionalContainer.style.display = 'none';
        if (otrasContainer) otrasContainer.style.display = 'none';
    } else if (seleccionado.value === 'internacional') {
        if (provinciaContainer) provinciaContainer.style.display = 'none';
        if (internacionalContainer) internacionalContainer.style.display = 'block';
        if (otrasContainer) otrasContainer.style.display = 'none';
    } else if (seleccionado.value === 'otras') {
        if (provinciaContainer) provinciaContainer.style.display = 'none';
        if (internacionalContainer) internacionalContainer.style.display = 'none';
        if (otrasContainer) otrasContainer.style.display = 'block';
    } else if (seleccionado.value === 'ninguna') {
        if (provinciaContainer) provinciaContainer.style.display = 'none';
        if (internacionalContainer) internacionalContainer.style.display = 'none';
        if (otrasContainer) otrasContainer.style.display = 'none';
    }
}

function toggleImagenOpciones() {
    const tipoRadio = document.querySelector('input[name="tipo_imagen"]:checked');
    if (!tipoRadio) return;
    const panelLocal = document.getElementById('panelImagenLocal');
    const panelUrl = document.getElementById('panelImagenUrl');
    if (tipoRadio.value === 'local') {
        if (panelLocal) panelLocal.style.display = 'block';
        if (panelUrl) panelUrl.style.display = 'none';
    } else {
        if (panelLocal) panelLocal.style.display = 'none';
        if (panelUrl) panelUrl.style.display = 'block';
    }
}

function toggleVideoOpciones() {
    const tipoRadio = document.querySelector('input[name="tipo_video"]:checked');
    if (!tipoRadio) return;
    const panelLocal = document.getElementById('panelVideoLocal');
    const panelExterno = document.getElementById('panelVideoExterno');
    if (tipoRadio.value === 'subir') {
        if (panelLocal) panelLocal.style.display = 'block';
        if (panelExterno) panelExterno.style.display = 'none';
    } else {
        if (panelLocal) panelLocal.style.display = 'none';
        if (panelExterno) panelExterno.style.display = 'block';
    }
}

function setupPreviewImagenUrl() {
    const input = document.getElementById('imagen_url');
    if (!input) return;
    const preview = document.getElementById('previewImagenUrl');
    const actualizar = () => {
        if (preview) {
            if (input.value && input.value.trim() !== '') {
                mostrarImagenPreview(preview, input.value, 200, 100);
            } else {
                preview.replaceChildren();
            }
        }
    };
    input.addEventListener('input', actualizar);
    actualizar();
}

function setupPreviewVideoUrl() {
    const input = document.getElementById('video_url');
    if (!input) return;
    const preview = document.getElementById('previewVideo');
    const actualizar = () => {
        if (!preview) return;
        const url = input.value;
        let embed = '';
        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&?]+)/);
            if (match) embed = 'https://www.youtube.com/embed/' + match[1];
        } else if (url.includes('vimeo.com')) {
            const match = url.match(/vimeo\.com\/(\d+)/);
            if (match) embed = 'https://player.vimeo.com/video/' + match[1];
        }
        mostrarVideoPreview(preview, embed);
    };
    input.addEventListener('input', actualizar);
    actualizar();
}

function setupGaleria() {
    document.querySelectorAll('.chkGaleriaUrl').forEach(chk => {
        chk.addEventListener('change', function() {
            const imgNum = this.getAttribute('data-img');
            const divLocal = document.getElementById(`divGaleriaLocal_${imgNum}`);
            const divUrl = document.getElementById(`divGaleriaUrl_${imgNum}`);
            if (this.checked) {
                if (divLocal) divLocal.style.display = 'none';
                if (divUrl) divUrl.style.display = 'block';
            } else {
                if (divLocal) divLocal.style.display = 'block';
                if (divUrl) divUrl.style.display = 'none';
            }
        });
    });

    for (let i = 2; i <= 6; i++) {
        const urlInput = document.getElementById(`imagen_galeria_url_${i}`);
        if (!urlInput) continue;
        const preview = document.getElementById(`previewGaleriaUrl_${i}`);
        const actualizar = () => {
            if (preview) {
                if (urlInput.value && urlInput.value.trim() !== '') {
                    mostrarImagenPreview(preview, urlInput.value, 200, 100);
                } else {
                    preview.replaceChildren();
                }
            }
        };
        urlInput.addEventListener('input', actualizar);
        actualizar();
    }
}

function iniciarTinyMCE() {
    const textarea = document.getElementById('contenido');

    if (!textarea) {
        return;
    }

    if (typeof initTinyMCE === 'function') {
        initTinyMCE('#contenido');
    } else {
        console.warn('No se ha podido cargar la configuración local de TinyMCE.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const radiosUbicacion = document.querySelectorAll('input[name="tipo_ubicacion"]');
    radiosUbicacion.forEach(radio => radio.addEventListener('change', toggleUbicacion));
    toggleUbicacion();
    
    const radiosImagen = document.querySelectorAll('input[name="tipo_imagen"]');
    radiosImagen.forEach(radio => radio.addEventListener('change', toggleImagenOpciones));
    toggleImagenOpciones();
    
    const radiosVideo = document.querySelectorAll('input[name="tipo_video"]');
    radiosVideo.forEach(radio => radio.addEventListener('change', toggleVideoOpciones));
    toggleVideoOpciones();
    
    setupPreviewImagenUrl();
    setupPreviewVideoUrl();
    setupGaleria();
    iniciarTinyMCE();
});

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
            var select = document.getElementById('id_fuente');
            var option = document.createElement('option');
            option.value = data.id || '';
            option.textContent = data.nombre;
            option.selected = true;
            select.appendChild(option);
            alert('✅ Fuente creada');
        } else {
            alert('❌ ' + data.error);
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnCrearFuente')?.addEventListener('click', crearFuente);
});
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
