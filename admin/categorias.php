<?php
declare(strict_types=1);


/**
 * GESTIÓN DE CATEGORÍAS
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';


Permisos::requerirAdmin();

$pdo = db();

// Procesar acciones
$accion = $_POST['accion'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    mensajeFlash('error', 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.');
    redireccionar(route('admin_categorias'));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion && $id) {
    try {
        switch ($accion) {
            case 'activar':
                $stmt = $pdo->prepare("UPDATE categorias SET activa = 1 WHERE id_categoria = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Categoría activada');
                break;
                
            case 'desactivar':
                $stmt = $pdo->prepare("UPDATE categorias SET activa = 0 WHERE id_categoria = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Categoría desactivada');
                break;
                
            case 'eliminar':
                // Verificar si hay noticias en esta categoría
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_categoria = :id");
                $stmt->execute([':id' => $id]);
                $total = $stmt->fetchColumn();
                
                if ($total > 0) {
                    mensajeFlash('error', "No se puede eliminar: hay $total noticias en esta categoría");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id_categoria = :id");
                    $stmt->execute([':id' => $id]);
                    mensajeFlash('success', 'Categoría eliminada');
                }
                break;
        }
    } catch (PDOException $e) {
        mensajeFlash('error', 'Error al procesar la acción');
        registrarErrorInterno('ADMIN.CATEGORIAS.ACCION', $e);
    }
    
    redireccionar(route('admin_categorias'));
}

// Obtener todas las categorías
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM noticias WHERE id_categoria = c.id_categoria) as total_noticias
        FROM categorias c
        ORDER BY c.orden, c.nombre_categoria
    ");
    $categorias = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'No se pudieron cargar las categorías.';
    registrarErrorInterno('ADMIN.CATEGORIAS.CARGA', $e);
}

$titulo_pagina = 'Gestión de Categorías';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-categorias.css'); ?>">


<main class="admin-categorias-pagina">
<header class="admin-categorias-cabecera">
    <h1 class="titulo-categorias">🏷️ Gestión de Categorías</h1>
    <p>Organiza las noticias por temas y controla qué categorías están disponibles en el portal.</p>
</header>

<nav class="admin-taxonomia-nav" aria-label="Gestión de clasificación de noticias">
    <a class="activo" href="<?php echo route('admin_categorias'); ?>">🏷️ Categorías</a>
    <a href="<?php echo route('admin_fuentes'); ?>">🗞️ Fuentes</a>
</nav>

<div class="admin-categorias-barra-acciones">
    <div>
        <strong>📋 <?php echo count($categorias ?? []); ?> categorías</strong>
        <small>Una categoría con noticias asociadas no puede eliminarse.</small>
    </div>
    <a href="<?php echo route('admin_nueva_categoria'); ?>" class="admin-categorias-btn admin-categorias-btn-nueva">➕ Nueva categoría</a>
</div>

<?php if (isset($error)): ?>

    <div class="admin-categorias-alerta admin-categorias-alerta-error"><?php echo $error; ?></div>

<?php endif; ?>


<?php if (empty($categorias)): ?>

    <div class="admin-categorias-alerta admin-categorias-alerta-info">
        <p>No hay categorías creadas.</p>
    </div>
<?php else: ?>

    
<div class="admin-categorias-contenedor">
    <div class="admin-categorias-tabla-responsive">
    <table class="admin-categorias-tabla">
        <thead>
            <tr>
                <th>ID</th>
                <th>Categoría</th>
                <th>Slug</th>
                <th>Descripción</th>
                <th>Noticias</th>
                <th>Orden</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categorias as $cat): ?>
            <tr class="<?php echo !$cat['activa'] ? 'admin-categorias-inactiva' : ''; ?>">
                <td>#<?php echo (int) $cat['id_categoria']; ?></td>
                <td><strong><?php echo htmlspecialchars($cat['nombre_categoria']); ?></strong></td>
                <td><code><?php echo htmlspecialchars($cat['slug_categoria']); ?></code></td>
                <td class="admin-categorias-descripcion"><?php echo htmlspecialchars(truncarTexto($cat['descripcion'] ?? '', 100)); ?></td>
                <td class="admin-categorias-centrar"><?php echo (int) $cat['total_noticias']; ?> 📄</td>
                <td class="admin-categorias-centrar"><?php echo (int) $cat['orden']; ?></td>
                <td class="admin-categorias-centrar">
                    <?php if ($cat['activa']): ?>
                        <span class="admin-categorias-badge admin-categorias-badge-activo">✅ Activa</span>
                    <?php else: ?>
                        <span class="admin-categorias-badge admin-categorias-badge-inactivo">❌ Inactiva</span>
                    <?php endif; ?>
                </td>
                <td class="admin-categorias-acciones">
                        <a href="<?php echo route('admin_editar_categoria', ['id' => (int) $cat['id_categoria']]); ?>"
                           class="admin-categorias-btn admin-categorias-btn-editar" title="Editar categoría">✏️</a>
                        
                        <?php if ($cat['activa']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="desactivar">
                                <input type="hidden" name="id" value="<?php echo $cat['id_categoria']; ?>">
                                <button type="submit" class="admin-categorias-btn admin-categorias-btn-desactivar" style="cursor: pointer;" onclick="return confirm('¿Desactivar esta categoría?')" title="Desactivar categoría">
                                👁️
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="activar">
                                <input type="hidden" name="id" value="<?php echo $cat['id_categoria']; ?>">
                                <button type="submit" class="admin-categorias-btn admin-categorias-btn-activar" style="cursor: pointer;" title="Activar categoría">
                                ✅
                                </button>
                            </form>
                        <?php endif; ?>

                        
                        <?php if ($cat['total_noticias'] == 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $cat['id_categoria']; ?>">
                                <button type="submit" class="admin-categorias-btn admin-categorias-btn-eliminar" style="cursor: pointer;" onclick="return confirm('¿Eliminar esta categoría?')" title="Eliminar categoría">
                                🗑️
                                </button>
                            </form>
                        <?php endif; ?>

                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
    
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
