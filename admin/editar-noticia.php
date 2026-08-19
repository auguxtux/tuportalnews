<?php
declare(strict_types=1);


/**
 * EDITAR NOTICIA (PANEL ADMIN)
 * Permite editar cualquier noticia y gestionar relaciones
 * Diseño responsive con formulario y tarjetas de relaciones
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$id_noticia = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_noticia) {
    mensajeFlash('error', 'ID de noticia no válido');
    redireccionar(route('admin_noticias'));
}

$pdo = db();

// Obtener noticia
$stmt = $pdo->prepare("
    SELECT n.*, c.nombre_categoria
    FROM noticias n
    JOIN categorias c ON n.id_categoria = c.id_categoria
    WHERE n.id_noticia = :id
");
$stmt->execute([':id' => $id_noticia]);
$noticia = $stmt->fetch();

if (!$noticia) {
    mensajeFlash('error', 'Noticia no encontrada');
    redireccionar(route('admin_noticias'));
}

// Obtener categorías para el selector
$categorias = $pdo->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY nombre_categoria")->fetchAll();
$fuentes = $pdo->query("SELECT id_fuente, nombre FROM fuentes WHERE activa = 1 ORDER BY nombre")->fetchAll();
$provincias = $pdo->query("SELECT id_provincia, nombre FROM provincias ORDER BY nombre")->fetchAll();

$errores = [];
$datos = $noticia;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !verificarTokenCSRF($_POST['csrf_token'] ?? '')
) {
    mensajeFlash('error', 'Error de seguridad');
    redireccionar(route('admin_editar_noticia', ['id' => $id_noticia]));
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $datos = [
        'titulo' => limpiarDatos($_POST['titulo'] ?? ''),
        'subtitulo' => limpiarDatos($_POST['subtitulo'] ?? ''),
        'contenido' => limpiarDatos($_POST['contenido'] ?? ''),
        'id_categoria' => (int)($_POST['id_categoria'] ?? 0),
        'id_fuente' => !empty($_POST['id_fuente']) ? (int) $_POST['id_fuente'] : null,
        'fuente' => '',
        'estado' => $_POST['estado'] ?? 'borrador',
        'tipo_ubicacion' => $_POST['tipo_ubicacion'] ?? 'otras',
        'id_provincia' => (int)($_POST['id_provincia'] ?? 0),
        'lugar_internacional' => limpiarDatos($_POST['lugar_internacional'] ?? ''),
        'otras_ubicacion' => limpiarDatos($_POST['otras_ubicacion'] ?? '')
    ];
    
    if (empty($datos['titulo'])) $errores[] = 'El título es obligatorio';
    if (empty($datos['contenido'])) $errores[] = 'El contenido es obligatorio';
    if (!$datos['id_categoria']) {
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

    if (empty($datos['id_fuente'])) {
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
    
    // Procesar imagen
    $imagen = $noticia['imagen_principal'];
    $imagen_nueva = null;
    $imagen_anterior_pendiente = null;
    $eliminar_imagen = isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] === '1';

    if ($eliminar_imagen) {
        $imagen_anterior_pendiente = $noticia['imagen_principal'] ?: null;
        $imagen = null;
    } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $upload = new UploadHandler($_FILES['imagen'], 'noticia');
        $nueva_imagen = $upload->subir();
        
        if ($nueva_imagen) {
            $imagen_nueva = $nueva_imagen;
            $imagen_anterior_pendiente = $noticia['imagen_principal'] ?: null;
            $imagen = $nueva_imagen;
        } else {
            $errores = array_merge($errores, $upload->getErrores());
        }
    }

    if (empty($imagen) && empty($noticia['imagen_externa'])) {
        $errores[] = 'La imagen principal es obligatoria';
    }

    // Validación de ubicación
    if (!in_array($datos['tipo_ubicacion'], ['espana', 'internacional', 'otras'], true)) {
        $errores[] = 'Debes seleccionar una ubicación';
    } elseif ($datos['tipo_ubicacion'] === 'espana' && $datos['id_provincia'] <= 0) {
        $errores[] = 'Debes seleccionar una provincia';
    } elseif ($datos['tipo_ubicacion'] === 'internacional' && empty($datos['lugar_internacional'])) {
        $errores[] = 'Debes indicar el lugar internacional';
    } elseif ($datos['tipo_ubicacion'] === 'otras' && empty($datos['otras_ubicacion'])) {
        $errores[] = 'Debes indicar el nombre del lugar';
    }

    if (empty($errores)) {
        try {
            $slug = generarSlug($datos['titulo']);

            $sql = "UPDATE noticias SET
                    titulo = :titulo,
                    slug = :slug,
                    subtitulo = :subtitulo,
                    contenido = :contenido,
                    fuente = :fuente,
                    imagen_principal = :imagen,
                    id_categoria = :id_categoria,
                    id_fuente = :id_fuente,
                    estado = :estado,
                    tipo_ubicacion = :tipo_ubicacion,
                    id_provincia = :id_provincia,
                    lugar_internacional = :lugar_internacional,
                    otras_ubicacion = :otras_ubicacion,
                    fecha_actualizacion = NOW()
                    WHERE id_noticia = :id";

            $stmt = $pdo->prepare($sql);
            $resultado = $stmt->execute([
                ':titulo' => $datos['titulo'],
                ':slug' => $slug,
                ':subtitulo' => $datos['subtitulo'] ?: null,
                ':contenido' => $datos['contenido'],
                ':fuente' => $datos['fuente'],
                ':imagen' => $imagen,
                ':id_categoria' => $datos['id_categoria'],
                ':id_fuente' => $datos['id_fuente'],
                ':estado' => $datos['estado'],
                ':tipo_ubicacion' => $datos['tipo_ubicacion'],
                ':id_provincia' => $datos['tipo_ubicacion'] === 'espana' ? $datos['id_provincia'] : null,
                ':lugar_internacional' => $datos['tipo_ubicacion'] === 'internacional' ? $datos['lugar_internacional'] : null,
                ':otras_ubicacion' => $datos['tipo_ubicacion'] === 'otras' ? $datos['otras_ubicacion'] : null,
                ':id' => $id_noticia
            ]);

            if ($resultado) {
                if (is_string($imagen_anterior_pendiente) && basename($imagen_anterior_pendiente) === $imagen_anterior_pendiente) {
                    $ruta_anterior = UPLOAD_NOTICIAS . $imagen_anterior_pendiente;
                    if (is_file($ruta_anterior)) {
                        unlink($ruta_anterior);
                    }
                }
                mensajeFlash('success', 'Noticia actualizada correctamente');
                redireccionar(route('admin_noticias'));
            } else {
                if (is_string($imagen_nueva) && basename($imagen_nueva) === $imagen_nueva) {
                    $ruta_nueva = UPLOAD_NOTICIAS . $imagen_nueva;
                    if (is_file($ruta_nueva)) {
                        unlink($ruta_nueva);
                    }
                }
                $errores[] = 'Error al actualizar la noticia';
            }
        } catch (Throwable $e) {
            if (is_string($imagen_nueva) && basename($imagen_nueva) === $imagen_nueva) {
                $ruta_nueva = UPLOAD_NOTICIAS . $imagen_nueva;
                if (is_file($ruta_nueva)) {
                    unlink($ruta_nueva);
                }
            }
            $errores[] = 'No se pudo actualizar la noticia.';
            registrarErrorInterno('ADMIN.NOTICIA.EDITAR', $e);
        }
    } elseif (is_string($imagen_nueva) && basename($imagen_nueva) === $imagen_nueva) {
        $ruta_nueva = UPLOAD_NOTICIAS . $imagen_nueva;
        if (is_file($ruta_nueva)) {
            unlink($ruta_nueva);
        }
    }
}

// Procesar eliminación de relación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_relacion') {
    $id_destino = (int)($_POST['destino'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM noticias_relacionadas 
                           WHERE id_noticia_origen = ? AND id_noticia_destino = ?");
    if ($stmt->execute([$id_noticia, $id_destino])) {
        mensajeFlash('success', 'Relación eliminada');
    } else {
        mensajeFlash('error', 'Error al eliminar la relación');
    }
    redireccionar(route('admin_editar_noticia', ['id' => $id_noticia]));
}

// Obtener relaciones actuales
$stmt = $pdo->prepare("
    SELECT n.*, c.nombre_categoria 
    FROM noticias_relacionadas r
    JOIN noticias n ON r.id_noticia_destino = n.id_noticia
    JOIN categorias c ON n.id_categoria = c.id_categoria
    WHERE r.id_noticia_origen = ?
    ORDER BY r.peso DESC
");
$stmt->execute([$id_noticia]);
$relaciones_actuales = $stmt->fetchAll();

$titulo_pagina = 'Editar Noticia';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-editar-noticia.css'); ?>">


<div class="admin-editar-noticia-container">
    
    <div class="admin-editar-noticia-header">
        <h1 class="admin-editar-noticia-titulo">✏️ Editar Noticia</h1>
        <p class="admin-editar-noticia-desc">Modifica los datos de la noticia</p>
    </div>
    
    <?php if (!empty($errores)): ?>

        <div class="admin-editar-noticia-alerta admin-editar-noticia-alerta-error">
            <ul class="admin-editar-noticia-error-list">
                <?php foreach ($errores as $e): ?>

                    <li><?php echo $e; ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <!-- FORMULARIO DE EDICIÓN -->
    <div class="admin-editar-noticia-card">
        <form method="POST" enctype="multipart/form-data" class="admin-editar-noticia-form">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
            
            <div class="admin-editar-noticia-grid">
                <div class="admin-editar-noticia-campo">
                    <label for="titulo">📝 Título *</label>
                    <input type="text" id="titulo" name="titulo" required 
                           value="<?php echo htmlspecialchars($datos['titulo']); ?>"

                           placeholder="Título de la noticia">
                </div>
                
                <div class="admin-editar-noticia-campo">
                    <label for="subtitulo">📄 Subtítulo</label>
                    <input type="text" id="subtitulo" name="subtitulo" 
                           value="<?php echo htmlspecialchars($datos['subtitulo'] ?? ''); ?>"

                           placeholder="Subtítulo o entradilla">
                </div>
                
                <div class="admin-editar-noticia-campo">
                    <label for="id_categoria">📂 Categoría *</label>
                    <select id="id_categoria" name="id_categoria" required>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($categorias as $cat): ?>

                            <option value="<?php echo $cat['id_categoria']; ?>" 

                                <?php echo $datos['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>

                                <?php echo htmlspecialchars($cat['nombre_categoria']); ?>

                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="admin-editar-noticia-campo">
                    <label for="id_fuente">📰 Fuente *</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <select id="id_fuente" name="id_fuente" required style="flex: 1;">
                            <option value="">Selecciona una fuente</option>
                            <?php foreach ($fuentes as $fuente): ?>
                                <option
                                    value="<?php echo (int) $fuente['id_fuente']; ?>"
                                    <?php echo ($datos['id_fuente'] ?? '') == $fuente['id_fuente'] ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($fuente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnCrearFuente" class="admin-editar-noticia-btn-mini">+ Nueva</button>
                    </div>
                    <?php if (!empty($noticia['id_fuente_rss'])): ?>
                        <small>Fuente RSS original.</small>
                    <?php endif; ?>
                </div>
                
                <div class="admin-editar-noticia-campo">
                    <label for="estado">📌 Estado</label>
                    <select id="estado" name="estado">
                        <option value="borrador" <?php echo $datos['estado'] == 'borrador' ? 'selected' : ''; ?>>📝 Borrador</option>

                        <option value="publicada" <?php echo $datos['estado'] == 'publicada' ? 'selected' : ''; ?>>✅ Publicada</option>

                        <option value="pendiente" <?php echo $datos['estado'] == 'pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>

                        <option value="archivada" <?php echo $datos['estado'] == 'archivada' ? 'selected' : ''; ?>>📦 Archivada</option>

                        <option value="destacada" <?php echo $datos['estado'] == 'destacada' ? 'selected' : ''; ?>>⭐ Destacada</option>

                    </select>
                </div>

                <div class="admin-editar-noticia-campo admin-editar-noticia-campo-full">
                    <label for="tipo_ubicacion">📍 Ubicación *</label>
                    <div class="admin-editar-noticia-radio-group">
                        <label><input type="radio" name="tipo_ubicacion" value="espana" <?php echo ($datos['tipo_ubicacion'] ?? 'espana') === 'espana' ? 'checked' : ''; ?>> 🇪🇸 España</label>
                        <label><input type="radio" name="tipo_ubicacion" value="internacional" <?php echo ($datos['tipo_ubicacion'] ?? '') === 'internacional' ? 'checked' : ''; ?>> 🌍 Internacional</label>
                        <label><input type="radio" name="tipo_ubicacion" value="otras" <?php echo ($datos['tipo_ubicacion'] ?? '') === 'otras' ? 'checked' : ''; ?>> 🗺️ Otras ubicaciones</label>
                    </div>

                    <div id="provincia-container" style="margin-top: 0.75rem; <?php echo ($datos['tipo_ubicacion'] ?? 'espana') !== 'espana' ? 'display:none;' : ''; ?>">
                        <label>🏞️ Provincia</label>
                        <select name="id_provincia">
                            <option value="">Seleccionar</option>
                            <?php foreach ($provincias as $prov): ?>
                                <option value="<?php echo $prov['id_provincia']; ?>" <?php echo ($datos['id_provincia'] ?? 0) == $prov['id_provincia'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="internacional-container" style="margin-top: 0.75rem; <?php echo ($datos['tipo_ubicacion'] ?? '') !== 'internacional' ? 'display:none;' : ''; ?>">
                        <label>🌎 Lugar internacional</label>
                        <input type="text" name="lugar_internacional" value="<?php echo htmlspecialchars($datos['lugar_internacional'] ?? ''); ?>" placeholder="Ej: Nueva York, Londres, París...">
                    </div>

                    <div id="otras-container" style="margin-top: 0.75rem; <?php echo ($datos['tipo_ubicacion'] ?? '') !== 'otras' ? 'display:none;' : ''; ?>">
                        <label>🗺️ Nombre del lugar</label>
                        <input type="text" name="otras_ubicacion" value="<?php echo htmlspecialchars($datos['otras_ubicacion'] ?? ''); ?>" placeholder="Ej: Isla de Pascua, Machu Picchu...">
                        <small>Escribe cualquier ubicación que no esté en la lista de provincias</small>
                    </div>
                </div>
            </div>
            
            <!-- Sección de imagen -->
            <div class="admin-editar-noticia-imagen-section">
                <label class="admin-editar-noticia-imagen-label">🖼️ Imagen principal</label>
                
                <?php if ($noticia['imagen_principal']): ?>

                    <div class="admin-editar-noticia-imagen-actual">
                        <img src="<?php echo base_url('uploads/noticias/' . $noticia['imagen_principal']); ?>" 

                             alt="Imagen actual">
                        <label class="admin-editar-noticia-checkbox">
                            <input type="checkbox" name="eliminar_imagen" value="1"> 🗑️ Eliminar imagen actual
                        </label>
                    </div>
                <?php endif; ?>

                
                <div class="admin-editar-noticia-campo">
                    <input type="file" id="imagen" name="imagen" accept="image/*">
                    <small>Formatos: JPG, PNG, GIF, WEBP (máx. 5MB)</small>
                    <img id="preview-imagen" class="admin-editar-noticia-preview" style="display: none;">
                </div>
            </div>
            
            <div class="admin-editar-noticia-campo">
                <label for="contenido">📰 Contenido *</label>
                <textarea id="contenido" name="contenido" rows="12" required 
                          placeholder="Escribe el contenido de la noticia..."><?php echo htmlspecialchars(
                    html_entity_decode((string) $datos['contenido'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ); ?></textarea>

            </div>
            
            <div class="admin-editar-noticia-acciones">
                <button type="submit" class="admin-editar-noticia-btn admin-editar-noticia-btn-guardar">
                    💾 Guardar cambios
                </button>
                <a href="<?php echo route('admin_noticias'); ?>" class="admin-editar-noticia-btn admin-editar-noticia-btn-cancelar">

                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
    
    <!-- SECCIÓN DE NOTICIAS RELACIONADAS -->
    <div class="admin-editar-noticia-relaciones-section">
        <div class="admin-editar-noticia-relaciones-header">
            <h2 class="admin-editar-noticia-relaciones-titulo">🔗 Noticias relacionadas</h2>
            <p class="admin-editar-noticia-relaciones-desc">Gestiona las relaciones con otras noticias</p>
        </div>
        
        <?php if (!empty($relaciones_actuales)): ?>

            <div class="admin-editar-noticia-relaciones-grid">
                <?php foreach ($relaciones_actuales as $rel): ?>

                    <div class="admin-editar-noticia-relacion-card">
                        <div class="admin-editar-noticia-relacion-card-contenido">
                            <h3 class="admin-editar-noticia-relacion-titulo">
                                <a href="<?php echo route('noticia', ['id' => $rel['id_noticia']]); ?>">

                                    <?php echo htmlspecialchars($rel['titulo']); ?>

                                </a>
                            </h3>
                            <div class="admin-editar-noticia-relacion-meta">
                                <span class="admin-editar-noticia-relacion-categoria">
                                    📂 <?php echo htmlspecialchars($rel['nombre_categoria']); ?>

                                </span>
                                <span class="admin-editar-noticia-relacion-fecha">
                                    📅 <?php echo formatearFecha($rel['fecha_publicacion']); ?>

                                </span>
                            </div>
                        </div>
                        <div class="admin-editar-noticia-relacion-acciones">
                            <form method="POST" onsubmit="return confirm('¿Eliminar esta relación?')">
                                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
                                <input type="hidden" name="accion" value="eliminar_relacion">
                                <input type="hidden" name="destino" value="<?php echo $rel['id_noticia']; ?>">
                                <button type="submit" class="admin-editar-noticia-btn-relacion admin-editar-noticia-btn-relacion-eliminar">
                                    🗑️ Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>

            <div class="admin-editar-noticia-relaciones-vacio">
                <p>📭 No hay noticias relacionadas actualmente</p>
            </div>
        <?php endif; ?>

        
        <!-- Formulario para añadir relación -->
        <div class="admin-editar-noticia-relacion-formulario">
            <h3 class="admin-editar-noticia-relacion-formulario-titulo">➕ Añadir relación manual</h3>
            <form method="POST" action="<?php echo htmlspecialchars(route('procesar_relacion'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-editar-noticia-relacion-form">
                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
                <input type="hidden" name="id_origen" value="<?php echo $id_noticia; ?>">

                
                <div class="admin-editar-noticia-relacion-campo">
                    <label for="id_destino">Seleccionar noticia:</label>
                    <select id="id_destino" name="id_destino" required>
                        <option value="">-- Selecciona una noticia --</option>
                        <?php

                        // Obtener noticias para relacionar (excluyendo la actual y las ya relacionadas)
                        $ids_relacionadas = array_column($relaciones_actuales, 'id_noticia');
                        $ids_relacionadas[] = $id_noticia;
                        $placeholders = implode(',', array_fill(0, count($ids_relacionadas), '?'));
                        
                        $stmt = $pdo->prepare("
                            SELECT n.id_noticia, n.titulo, c.nombre_categoria
                            FROM noticias n
                            JOIN categorias c ON n.id_categoria = c.id_categoria
                            WHERE n.id_noticia NOT IN ($placeholders)
                            ORDER BY n.fecha_publicacion DESC
                            LIMIT 50
                        ");
                        $stmt->execute($ids_relacionadas);
                        $noticias_disponibles = $stmt->fetchAll();
                        ?>
                        <?php foreach ($noticias_disponibles as $disp): ?>

                            <option value="<?php echo $disp['id_noticia']; ?>">

                                <?php echo htmlspecialchars($disp['titulo']); ?> 

                                (<?php echo htmlspecialchars($disp['nombre_categoria']); ?>)

                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>
                
                <div class="admin-editar-noticia-relacion-campo">
                    <label for="peso">⭐ Prioridad (1-10):</label>
                    <input type="number" id="peso" name="peso" min="1" max="10" value="5" required>
                    <small>Números más altos aparecen primero</small>
                </div>
                
                <button type="submit" class="admin-editar-noticia-btn-relacion admin-editar-noticia-btn-relacion-agregar">
                    ➕ Añadir relación
                </button>
            </form>
        </div>
    </div>
    
</div>

<script>
// Toggle ubicación
function toggleUbicacion() {
    const seleccionado = document.querySelector('input[name="tipo_ubicacion"]:checked');
    if (!seleccionado) return;
    const provinciaContainer = document.getElementById('provincia-container');
    const internacionalContainer = document.getElementById('internacional-container');
    const otrasContainer = document.getElementById('otras-container');
    if (provinciaContainer) provinciaContainer.style.display = seleccionado.value === 'espana' ? 'block' : 'none';
    if (internacionalContainer) internacionalContainer.style.display = seleccionado.value === 'internacional' ? 'block' : 'none';
    if (otrasContainer) otrasContainer.style.display = seleccionado.value === 'otras' ? 'block' : 'none';
}

// Vista previa de imagen
document.addEventListener('DOMContentLoaded', function() {
    const inputImagen = document.getElementById('imagen');
    const preview = document.getElementById('preview-imagen');
    
    if (inputImagen && preview) {
        inputImagen.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    }

    // Toggle ubicación
    toggleUbicacion();
    document.querySelectorAll('input[name="tipo_ubicacion"]').forEach(function(radio) {
        radio.addEventListener('change', toggleUbicacion);
    });
});
</script>

<script>
// Inicializar TinyMCE para el editor de noticias
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar para el textarea de contenido
    if (typeof tinymce !== 'undefined' && document.getElementById('contenido')) {
        if (!tinymce.get('contenido')) {
            tinymce.init({
                selector: '#contenido',
                height: 500,
                menubar: true,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help | image media link',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; }',
                images_upload_url: '/ajax/upload-editor-image.php',
                automatic_uploads: true,
                relative_urls: false,
                remove_script_host: false,
                convert_urls: true,
                branding: false,
                elementpath: true,
                resize: true
            });
            console.log('TinyMCE inicializado en #contenido');
        }
    }
    
    // Inicializar para comentarios (si existe)
    if (typeof tinymce !== 'undefined' && document.getElementById('comentario-editor-modal')) {
        if (!tinymce.get('comentario-editor-modal')) {
            tinymce.init({
                selector: '#comentario-editor-modal',
                height: 200,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic backcolor | ' +
                    'alignleft aligncenter alignright alignjustify | ' +
                    'bullist numlist outdent indent | removeformat | help',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
            });
        }
    }
});

// Crear fuente nueva
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
