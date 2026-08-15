<?php
declare(strict_types=1);


/**
 * EDITAR CATEGORÍA
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    mensajeFlash('error', 'ID de categoría no válido');
    redireccionar(route('admin_categorias'));
}

$pdo = db();
$errores = [];

// Obtener datos de la categoría
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE id_categoria = :id");
$stmt->execute([':id' => $id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    mensajeFlash('error', 'Categoría no encontrada');
    redireccionar(route('admin_categorias'));
}

// Procesar formulario
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

    $stmt = $pdo->prepare(
        "SELECT id_categoria
         FROM categorias
         WHERE nombre_categoria = :nombre
           AND id_categoria != :id"
    );
    $stmt->execute([':nombre' => $nombre, ':id' => $id]);
    if ($stmt->fetch()) {
        $errores[] = 'Ya existe una categoría con ese nombre';
    }
    
    if (empty($errores)) {
        $sql = "UPDATE categorias SET 
                nombre_categoria = :nombre,
                descripcion = :descripcion,
                orden = :orden,
                activa = :activa
                WHERE id_categoria = :id";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':orden' => $orden,
            ':activa' => $activa,
            ':id' => $id
        ])) {
            mensajeFlash('success', 'Categoría actualizada correctamente');
            redireccionar(route('admin_categorias'));
        } else {
            $errores[] = 'Error al actualizar la categoría';
        }
    }
}

$titulo_pagina = 'Editar Categoría';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-categoria-form.css'); ?>">


<div class="admin-editar-categoria-container">
    
    <div class="admin-editar-categoria-header">
        <h1 class="admin-editar-categoria-titulo">✏️ Editar Categoría</h1>
        <p class="admin-editar-categoria-desc">Modifica los datos de la categoría</p>
    </div>
    
    <?php if (!empty($errores)): ?>

        <div class="admin-editar-categoria-alerta admin-editar-categoria-alerta-error">
            <ul class="admin-editar-categoria-error-list">
                <?php foreach ($errores as $e): ?>

                    <li><?php echo $e; ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <div class="admin-editar-categoria-card">
        <form method="POST" class="admin-editar-categoria-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="admin-editar-categoria-campo">
                <label for="nombre">📝 Nombre de la categoría *</label>
                <input type="text" id="nombre" name="nombre" required 
                       value="<?php echo htmlspecialchars(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : $categoria['nombre_categoria']); ?>"

                       placeholder="Ej: Tecnología, Deportes, Actualidad">
                <small>El nombre se usará para mostrar la categoría</small>
            </div>
            
            <div class="admin-editar-categoria-campo">
                <label for="descripcion">📄 Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="4" 
                          placeholder="Describe brevemente de qué trata esta categoría..."><?php 

                    echo htmlspecialchars(is_string($_POST['descripcion'] ?? null) ? $_POST['descripcion'] : ($categoria['descripcion'] ?? ''));
                ?></textarea>
                <small>Descripción opcional para SEO y contexto</small>
            </div>
            
            <div class="admin-editar-categoria-grid">
                <div class="admin-editar-categoria-campo">
                    <label for="orden">🔢 Orden de visualización</label>
                    <input type="number" id="orden" name="orden" min="0" 
                           value="<?php echo (int)($_POST['orden'] ?? $categoria['orden']); ?>">

                    <small>Números más bajos aparecen primero</small>
                </div>
                
                <div class="admin-editar-categoria-campo admin-editar-categoria-checkbox">
                    <label class="admin-editar-categoria-checkbox-label">
                        <input type="checkbox" name="activa" value="1" 
                            <?php echo ($_POST['activa'] ?? $categoria['activa']) ? 'checked' : ''; ?>>

                        <span>✅ Categoría activa</span>
                    </label>
                    <small>Las categorías inactivas no se muestran</small>
                </div>
            </div>
            
            <div class="admin-editar-categoria-info">
                <div class="admin-editar-categoria-info-item">
                    <span class="admin-editar-categoria-info-icono">🔗</span>
                    <div class="admin-editar-categoria-info-contenido">
                        <strong>Slug actual:</strong>
                        <code><?php echo htmlspecialchars($categoria['slug_categoria']); ?></code>

                        <small>Se conserva aunque cambies el nombre para no romper enlaces anteriores</small>
                    </div>
                </div>
                
                <div class="admin-editar-categoria-info-item">
                    <span class="admin-editar-categoria-info-icono">📊</span>
                    <div class="admin-editar-categoria-info-contenido">
                        <strong>Total noticias:</strong>
                        <?php

                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_categoria = :id");
                        $stmt->execute([':id' => $id]);
                        echo $stmt->fetchColumn();
                        ?> noticias en esta categoría
                    </div>
                </div>
            </div>
            
            <div class="admin-editar-categoria-acciones">
                <button type="submit" class="admin-editar-categoria-btn admin-editar-categoria-btn-guardar">
                    💾 Guardar cambios
                </button>
                <a href="<?php echo route('admin_categorias'); ?>" class="admin-editar-categoria-btn admin-editar-categoria-btn-cancelar">
                    ❌ Cancelar
                </a>
            </div>
            
        </form>
    </div>
    
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
