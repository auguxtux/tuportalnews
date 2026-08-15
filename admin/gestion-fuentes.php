<?php
declare(strict_types=1);


/**
 * GESTIÓN DE FUENTES DE NOTICIAS
 * CRUD completo: listar, añadir, editar, activar/desactivar, eliminar
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();
$mensaje = '';
$error = '';

// ============================================
// PROCESAR ACCIONES (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Inténtalo de nuevo.';
    } else {
        $accion_post = $_POST['accion'] ?? '';
        $id_fuente = isset($_POST['id_fuente']) ? (int)$_POST['id_fuente'] : 0;
        $nombre = limpiarDatos($_POST['nombre'] ?? '');
        $comentario = limpiarDatos($_POST['comentario'] ?? '');
        $slug_base = limpiarDatos($_POST['slug'] ?? '');
        
        try {
            // AÑADIR NUEVA FUENTE
            if ($accion_post === 'añadir') {
                if (empty($nombre)) {
                    $error = 'El nombre de la fuente es obligatorio';
                } else {
                    $slug = !empty($slug_base) ? $slug_base : generarSlug($nombre);
                    
                    // Verificar duplicados
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fuentes WHERE nombre = ? OR slug = ?");
                    $stmt->execute([$nombre, $slug]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Ya existe una fuente con ese nombre o slug';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO fuentes (nombre, slug, comentario) VALUES (?, ?, ?)");
                        $stmt->execute([$nombre, $slug, $comentario]);
                        $mensaje = '✅ Fuente añadida correctamente';
                    }
                }
            }

            // EDITAR FUENTE
            if ($accion_post === 'editar' && $id_fuente > 0) {
                if (empty($nombre)) {
                    $error = 'El nombre de la fuente es obligatorio';
                } else {
                    $slug = !empty($slug_base) ? $slug_base : generarSlug($nombre);
                    
                    // Verificar duplicados (excluyendo la misma fuente)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fuentes WHERE (nombre = ? OR slug = ?) AND id_fuente != ?");
                    $stmt->execute([$nombre, $slug, $id_fuente]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Ya existe otra fuente con ese nombre o slug';
                    } else {
                        // Actualizar nombre en noticias existentes (si cambió)
                        $stmt_old = $pdo->prepare("SELECT nombre FROM fuentes WHERE id_fuente = ?");
                        $stmt_old->execute([$id_fuente]);
                        $nombre_antiguo = $stmt_old->fetchColumn();
                        
                        if ($nombre_antiguo && $nombre_antiguo !== $nombre) {
                            $pdo->prepare("UPDATE noticias SET fuente = ? WHERE id_fuente = ?")
                                ->execute([$nombre, $id_fuente]);
                        }
                        
                        $stmt = $pdo->prepare("UPDATE fuentes SET nombre = ?, slug = ?, comentario = ? WHERE id_fuente = ?");
                        $stmt->execute([$nombre, $slug, $comentario, $id_fuente]);
                        $mensaje = '✅ Fuente actualizada correctamente';
                    }
                }
            }

            // ACTIVAR FUENTE
            if ($accion_post === 'activar' && $id_fuente > 0) {
                $stmt = $pdo->prepare("UPDATE fuentes SET activa = 1 WHERE id_fuente = ?");
                $stmt->execute([$id_fuente]);
                $mensaje = '✅ Fuente activada';
            }

            // DESACTIVAR FUENTE
            if ($accion_post === 'desactivar' && $id_fuente > 0) {
                $stmt = $pdo->prepare("UPDATE fuentes SET activa = 0 WHERE id_fuente = ?");
                $stmt->execute([$id_fuente]);
                $mensaje = '✅ Fuente desactivada';
            }

            // ELIMINAR FUENTE SIN NOTICIAS ASOCIADAS
            if ($accion_post === 'eliminar' && $id_fuente > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_fuente = ?");
                $stmt->execute([$id_fuente]);
                $total = $stmt->fetchColumn();

                if ($total > 0) {
                    $error = "❌ No se puede eliminar: hay $total noticias con esta fuente";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM fuentes WHERE id_fuente = ?");
                    $stmt->execute([$id_fuente]);
                    $mensaje = '✅ Fuente eliminada correctamente';
                }
            }

        } catch (Exception $e) {
            $error = 'No se pudo procesar la fuente.';
            registrarErrorInterno('ADMIN.FUENTES.GESTION', $e);
        }
    }
}

// ============================================
// OBTENER DATOS PARA MOSTRAR
// ============================================

// Listado de fuentes con conteo de noticias
$fuentes = $pdo->query("
    SELECT f.*, 
           COUNT(n.id_noticia) as total_noticias
    FROM fuentes f
    LEFT JOIN noticias n ON n.id_fuente = f.id_fuente
    GROUP BY f.id_fuente
    ORDER BY f.nombre ASC
")->fetchAll();

// Si se va a editar, obtener datos de la fuente
$fuente_editar = null;
if (isset($_GET['editar']) && (int)$_GET['editar'] > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fuentes WHERE id_fuente = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $fuente_editar = $stmt->fetch();
}

$titulo_pagina = 'Gestión de Fuentes';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-gestion-fuentes.css'); ?>">


<div class="gfuente-container">
    <header class="gfuente-cabecera">
        <h1 class="gfuente-titulo">🗞️ Gestión de Fuentes de Noticias</h1>
        <p>Administra el origen informativo que debe indicarse en cada noticia publicada.</p>
    </header>

    <nav class="gfuente-nav" aria-label="Gestión de clasificación de noticias">
        <a href="<?php echo route('admin_categorias'); ?>">🏷️ Categorías</a>
        <a class="activo" href="<?php echo route('admin_fuentes'); ?>">🗞️ Fuentes</a>
    </nav>
    
    <!-- MENSAJES -->
    <?php if ($mensaje): ?>

        <div class="gfuente-alerta gfuente-alerta-success"><?php echo $mensaje; ?></div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="gfuente-alerta gfuente-alerta-error"><?php echo $error; ?></div>

    <?php endif; ?>

    
    <!-- ======================================== -->
    <!-- FORMULARIO: AÑADIR / EDITAR FUENTE -->
    <!-- ======================================== -->
    <div class="gfuente-card">
        <h2 class="gfuente-card-titulo">
            <?php echo $fuente_editar ? '✏️ Editar Fuente' : '➕ Añadir Nueva Fuente'; ?>

        </h2>
        <p class="gfuente-ayuda">El nombre es obligatorio. El slug identifica la fuente en su URL y puede generarse automáticamente.</p>
        
        <form method="POST" class="gfuente-form">
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

            <input type="hidden" name="accion" value="<?php echo $fuente_editar ? 'editar' : 'añadir'; ?>">

            <?php if ($fuente_editar): ?>

                <input type="hidden" name="id_fuente" value="<?php echo $fuente_editar['id_fuente']; ?>">

            <?php endif; ?>

            
            <div class="gfuente-grid-2">
                <div class="gfuente-campo">
                    <label for="nombre">🏷️ Nombre de la fuente *</label>
                    <input type="text" id="nombre" name="nombre" required
                           value="<?php echo htmlspecialchars($fuente_editar['nombre'] ?? ''); ?>"

                           placeholder="Ej: EFE, Reuters, AFP...">
                </div>
                
                <div class="gfuente-campo">
                    <label for="slug">🔗 Slug (URL amigable)</label>
                    <input type="text" id="slug" name="slug"
                           value="<?php echo htmlspecialchars($fuente_editar['slug'] ?? ''); ?>"

                           placeholder="Se genera automáticamente si se deja vacío">
                </div>
            </div>
            
            <div class="gfuente-campo">
                <label for="comentario">📝 Comentario / Descripción</label>
                <textarea id="comentario" name="comentario" rows="3"
                          placeholder="Información sobre esta fuente..."><?php echo htmlspecialchars($fuente_editar['comentario'] ?? ''); ?></textarea>

            </div>
            
            <div class="gfuente-acciones-form">
                <button type="submit" class="gfuente-btn gfuente-btn-primary">
                    <?php echo $fuente_editar ? '💾 Guardar cambios' : '➕ Añadir fuente'; ?>

                </button>
                <?php if ($fuente_editar): ?>

                    <a href="<?php echo htmlspecialchars(route('admin_fuentes'), ENT_QUOTES, 'UTF-8'); ?>" class="gfuente-btn gfuente-btn-secondary">❌ Cancelar</a>
                <?php endif; ?>

            </div>
        </form>
    </div>
    
    <!-- ======================================== -->
    <!-- TABLA DE FUENTES -->
    <!-- ======================================== -->
    <div class="gfuente-card">
        <h2 class="gfuente-card-titulo">📋 Listado de Fuentes (<?php echo count($fuentes); ?>)</h2>
        <p class="gfuente-ayuda">Desactivar conserva las noticias asociadas. Eliminar solo está disponible cuando la fuente no tiene noticias.</p>

        
        <?php if (empty($fuentes)): ?>

            <p class="gfuente-sin-datos">📭 No hay fuentes registradas</p>
        <?php else: ?>

            <div class="gfuente-tabla-responsive">
                <table class="gfuente-tabla">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Slug</th>
                            <th>Comentario</th>
                            <th>Noticias</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fuentes as $fuente): ?>

                        <tr class="<?php echo !$fuente['activa'] ? 'gfuente-inactiva' : ''; ?>">

                            <td><?php echo $fuente['id_fuente']; ?></td>

                            <td>
                                <strong><?php echo htmlspecialchars($fuente['nombre']); ?></strong>

                            </td>
                            <td><code><?php echo htmlspecialchars($fuente['slug']); ?></code></td>

                            <td class="gfuente-comentario">
                                <?php echo $fuente['comentario'] ? htmlspecialchars(substr($fuente['comentario'], 0, 80)) . '...' : '-'; ?>

                            </td>
                            <td class="gfuente-centrar">
                                <a href="<?php echo route('fuente', ['nombre' => $fuente['nombre']]); ?>" 

                                   target="_blank" rel="noopener noreferrer" title="Ver noticias de esta fuente">
                                    <?php echo $fuente['total_noticias']; ?> 📄

                                </a>
                            </td>
                            <td class="gfuente-centrar">
                                <?php if ($fuente['activa']): ?>

                                    <span class="gfuente-badge gfuente-badge-activo">✅ Activa</span>
                                <?php else: ?>

                                    <span class="gfuente-badge gfuente-badge-inactivo">❌ Inactiva</span>
                                <?php endif; ?>

                            </td>
                            <td class="gfuente-acciones-td">
                                <a href="?editar=<?php echo $fuente['id_fuente']; ?>" 

                                   class="gfuente-btn gfuente-btn-small gfuente-btn-edit" title="Editar">✏️</a>
                                
                                <?php if ($fuente['activa']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="accion" value="desactivar">
                                        <input type="hidden" name="id_fuente" value="<?php echo $fuente['id_fuente']; ?>">
                                        <button type="submit" class="gfuente-btn gfuente-btn-small gfuente-btn-warning" style="cursor: pointer;" onclick="return confirm('¿Desactivar esta fuente?')" title="Desactivar">👁️</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="accion" value="activar">
                                        <input type="hidden" name="id_fuente" value="<?php echo $fuente['id_fuente']; ?>">
                                        <button type="submit" class="gfuente-btn gfuente-btn-small gfuente-btn-success" style="cursor: pointer;" onclick="return confirm('¿Activar esta fuente?')" title="Activar">✅</button>
                                    </form>
                                <?php endif; ?>

                                
                                <?php if ($fuente['total_noticias'] == 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_fuente" value="<?php echo $fuente['id_fuente']; ?>">
                                        <button type="submit" class="gfuente-btn gfuente-btn-small gfuente-btn-danger" style="cursor: pointer;" onclick="return confirm('¿Eliminar esta fuente permanentemente?')" title="Eliminar">🗑️</button>
                                    </form>
                                <?php endif; ?>

                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
    
    <!-- ======================================== -->
    <!-- INFORMACIÓN -->
    <!-- ======================================== -->
    <div class="gfuente-card gfuente-info">
        <h3>ℹ️ Información sobre fuentes</h3>
        <ul>
            <li>📰 Las fuentes permiten agrupar noticias por su origen (agencia, periódico, etc.)</li>
            <li>🔗 El <strong>slug</strong> se usa para crear URLs amigables (ej: <code>/fuente/efe</code>)</li>
            <li>📝 El <strong>comentario</strong> es una descripción interna sobre la fuente</li>
            <li>⚠️ No se puede eliminar una fuente si tiene noticias asociadas</li>
            <li>👁️ Al desactivar una fuente, no se eliminan sus noticias</li>
        </ul>
    </div>
</div>

<script>
// Auto-generar slug desde el nombre
document.getElementById('nombre').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value || slugInput.dataset.auto === 'true') {
        slugInput.value = this.value
            .toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        slugInput.dataset.auto = 'true';
    }
});

document.getElementById('slug').addEventListener('input', function() {
    this.dataset.auto = 'false';
});
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
