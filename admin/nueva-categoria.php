<?php
declare(strict_types=1);


/**
 * NUEVA CATEGORÍA
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $errores[] = 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarDatos(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : '');
    $descripcion = limpiarDatos(is_string($_POST['descripcion'] ?? null) ? $_POST['descripcion'] : '');
    $orden = is_scalar($_POST['orden'] ?? null) ? (int) $_POST['orden'] : 0;
    $activa = isset($_POST['activa']) ? 1 : 0;
    
    if (empty($nombre)) {
        $errores[] = 'El nombre de la categoría es obligatorio';
    }
    
    // Generar slug
    $slug = generarSlug($nombre);
    
    // Verificar slug único
    $stmt = $pdo->prepare("SELECT id_categoria FROM categorias WHERE slug_categoria = :slug");
    $stmt->execute([':slug' => $slug]);
    if ($stmt->fetch()) {
        $errores[] = 'Ya existe una categoría con ese nombre';
    }
    
    if (empty($errores)) {
        $sql = "INSERT INTO categorias (nombre_categoria, slug_categoria, descripcion, orden, activa) 
                VALUES (:nombre, :slug, :descripcion, :orden, :activa)";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([
            ':nombre' => $nombre,
            ':slug' => $slug,
            ':descripcion' => $descripcion,
            ':orden' => $orden,
            ':activa' => $activa
        ])) {
            mensajeFlash('success', 'Categoría creada correctamente');
            redireccionar(route('admin_categorias'));
        } else {
            $errores[] = 'Error al crear la categoría';
        }
    }
}

$titulo_pagina = 'Nueva Categoría';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-categoria-form.css'); ?>">

<div class="admin-nueva-categoria-container">
    
    <div class="admin-nueva-categoria-header">
        <h1 class="admin-nueva-categoria-titulo">📁 Nueva Categoría</h1>
        <p class="admin-nueva-categoria-desc">Crea una nueva categoría para organizar las noticias</p>
    </div>
    
    <?php if (!empty($errores)): ?>

        <div class="admin-nueva-categoria-alerta admin-nueva-categoria-alerta-error">
            <ul class="admin-nueva-categoria-error-list">
                <?php foreach ($errores as $e): ?>

                    <li><?php echo $e; ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <div class="admin-nueva-categoria-card">
        <form method="POST" class="admin-nueva-categoria-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="admin-nueva-categoria-campo">
                <label for="nombre">📝 Nombre de la categoría *</label>
                <input type="text" id="nombre" name="nombre" required 
                       value="<?php echo htmlspecialchars(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : ''); ?>"

                       placeholder="Ej: Tecnología, Deportes, Actualidad">
                <small>El nombre se usará para mostrar la categoría</small>
            </div>
            
            <div class="admin-nueva-categoria-campo">
                <label for="descripcion">📄 Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="4" 
                          placeholder="Describe brevemente de qué trata esta categoría..."><?php 

                    echo htmlspecialchars(is_string($_POST['descripcion'] ?? null) ? $_POST['descripcion'] : '');
                ?></textarea>
                <small>Descripción opcional para SEO y contexto</small>
            </div>
            
            <div class="admin-nueva-categoria-grid">
                <div class="admin-nueva-categoria-campo">
                    <label for="orden">🔢 Orden de visualización</label>
                    <input type="number" id="orden" name="orden" min="0" 
                           value="<?php echo (int)($_POST['orden'] ?? 0); ?>">

                    <small>Números más bajos aparecen primero</small>
                </div>
                
                <div class="admin-nueva-categoria-campo admin-nueva-categoria-checkbox">
                    <label class="admin-nueva-categoria-checkbox-label">
                        <input type="checkbox" name="activa" value="1" checked>
                        <span>✅ Activar categoría</span>
                    </label>
                    <small>Las categorías inactivas no se muestran</small>
                </div>
            </div>
            
            <div class="admin-nueva-categoria-acciones">
                <button type="submit" class="admin-nueva-categoria-btn admin-nueva-categoria-btn-guardar">
                    💾 Crear categoría
                </button>
                <a href="<?php echo route('admin_categorias'); ?>" class="admin-nueva-categoria-btn admin-nueva-categoria-btn-cancelar">
                    ❌ Cancelar
                </a>
            </div>
            
        </form>
    </div>
    
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
