<?php
declare(strict_types=1);


/**
 * GESTIÓN DE NOTICIAS RELACIONADAS (MANUALES)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
Permisos::requerirAdmin();

$pdo = db();
$mensaje = '';
$errores = [];

// Obtener lista de noticias para los selects
$stmt_noticias = $pdo->query("
    SELECT n.id_noticia, n.titulo, n.fecha_publicacion, 
           c.nombre_categoria, u.nombre as autor
    FROM noticias n
    JOIN categorias c ON n.id_categoria = c.id_categoria
    JOIN usuarios u ON n.id_autor = u.id_usuario
    WHERE n.estado = 'publicada'
    ORDER BY n.fecha_publicacion DESC
");
$noticias = $stmt_noticias->fetchAll();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $errores[] = 'Error de seguridad';
    } else {

        // AÑADIR RELACIÓN
        if (isset($_POST['accion']) && $_POST['accion'] === 'añadir') {
            $origen = (int) ($_POST['noticia_origen'] ?? 0);
            $destino = (int) ($_POST['noticia_destino'] ?? 0);
            $peso = (int) ($_POST['peso'] ?? 0);

            if ($origen === $destino) {
                $errores[] = 'No puedes relacionar una noticia consigo misma';
            } else {
                try {
                    // Verificar si ya existe
                    $stmt = $pdo->prepare("SELECT id_relacion FROM noticias_relacionadas
                                           WHERE id_noticia_origen = ? AND id_noticia_destino = ?");
                    $stmt->execute([$origen, $destino]);

                    if ($stmt->fetch()) {
                        $errores[] = 'Esta relación ya existe';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO noticias_relacionadas
                            (id_noticia_origen, id_noticia_destino, peso, tipo)
                            VALUES (?, ?, ?, 'manual')
                        ");
                        $stmt->execute([$origen, $destino, $peso]);
                        $mensaje = 'Relación añadida correctamente';
                    }
                } catch (Exception $e) {
                    $errores[] = 'No se pudo añadir la relación.';
                    registrarErrorInterno('ADMIN.RELACIONES.ANADIR', $e);
                }
            }
        }

        // ELIMINAR RELACIÓN
        if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
            $id_relacion = (int) ($_POST['id_relacion'] ?? 0);

            try {
                $stmt = $pdo->prepare("DELETE FROM noticias_relacionadas WHERE id_relacion = ?");
                $stmt->execute([$id_relacion]);
                $mensaje = 'Relación eliminada correctamente';
            } catch (Exception $e) {
                $errores[] = 'No se pudo eliminar la relación.';
                registrarErrorInterno('ADMIN.RELACIONES.ELIMINAR', $e);
            }
        }
    }
}

// Obtener todas las relaciones existentes
$stmt_rel = $pdo->query("
    SELECT r.*, 
           o.titulo as origen_titulo,
           d.titulo as destino_titulo,
           o.fecha_publicacion as origen_fecha,
           d.fecha_publicacion as destino_fecha
    FROM noticias_relacionadas r
    JOIN noticias o ON r.id_noticia_origen = o.id_noticia
    JOIN noticias d ON r.id_noticia_destino = d.id_noticia
    ORDER BY o.fecha_publicacion DESC, r.peso DESC
");
$relaciones = $stmt_rel->fetchAll();

$titulo_pagina = 'Gestión de Noticias Relacionadas';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-noticias-relacionadas-gestion.css'); ?>">


<h1 class="relacionadas-titulo">📌 Gestión de Noticias Relacionadas</h1>

<?php if ($mensaje): ?>

    <div class="relacionadas-alerta relacionadas-alerta-success"><?php echo $mensaje; ?></div>

<?php endif; ?>


<?php if (!empty($errores)): ?>

    <div class="relacionadas-alerta relacionadas-alerta-error">
        <ul>
            <?php foreach ($errores as $e): ?>

                <li><?php echo $e; ?></li>

            <?php endforeach; ?>

        </ul>
    </div>
<?php endif; ?>


<div class="relacionadas-layout">
    <!-- FORMULARIO PARA AÑADIR RELACIÓN -->
    <div class="relacionadas-card relacionadas-form-card">
        <h2 class="relacionadas-card-titulo">➕ Añadir relación manual</h2>
        
        <form method="POST" class="relacionadas-formulario">
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
            <input type="hidden" name="accion" value="añadir">
            
            <div class="relacionadas-campo-form">
                <label for="noticia_origen">Noticia origen:</label>
                <select id="noticia_origen" name="noticia_origen" required class="relacionadas-select-search">
                    <option value="">Seleccionar noticia...</option>
                    <?php foreach ($noticias as $n): ?>

                        <option value="<?php echo $n['id_noticia']; ?>">

                            <?php echo htmlspecialchars(truncarTexto($n['titulo'], 60)); ?> 

                            (<?php echo $n['nombre_categoria']; ?> - <?php echo $n['autor']; ?>)

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <div class="relacionadas-campo-form">
                <label for="noticia_destino">Noticia destino (relacionada):</label>
                <select id="noticia_destino" name="noticia_destino" required class="relacionadas-select-search">
                    <option value="">Seleccionar noticia...</option>
                    <?php foreach ($noticias as $n): ?>

                        <option value="<?php echo $n['id_noticia']; ?>">

                            <?php echo htmlspecialchars(truncarTexto($n['titulo'], 60)); ?> 

                            (<?php echo $n['nombre_categoria']; ?> - <?php echo $n['autor']; ?>)

                        </option>
                    <?php endforeach; ?>

                </select>
            </div>
            
            <div class="relacionadas-campo-form">
                <label for="peso">Peso/prioridad (1-10):</label>
                <input type="number" id="peso" name="peso" min="1" max="10" value="5" required>
                <small>Mayor número = mayor prioridad en la visualización</small>
            </div>
            
            <button style="background: none;" type="submit" class="relacionadas-btn relacionadas-btn-principal">➕&nbsp;&nbsp; Añadir relación</button>
        </form>
    </div>
    
    <!-- LISTA DE RELACIONES EXISTENTES EN TARJETAS -->
    <div class="relacionadas-card relacionadas-lista-card">
        <h2 class="relacionadas-card-titulo">📋 Relaciones existentes</h2>
        
        <?php if (empty($relaciones)): ?>

            <p class="relacionadas-sin-resultados">No hay relaciones manuales todavía.</p>
        <?php else: ?>

            <div class="relacionadas-grid">
                <?php foreach ($relaciones as $rel): ?>

                    <div class="relacionadas-item">
                        <div class="relacionadas-item-header">
                            <span class="relacionadas-badge relacionadas-badge-peso">Peso: <?php echo $rel['peso']; ?></span>

                            <span class="relacionadas-badge relacionadas-badge-tipo"><?php echo $rel['tipo']; ?></span>

                        </div>
                        
                        <div class="relacionadas-item-contenido">
                            <div class="relacionadas-noticia relacionadas-noticia-origen">
                                <div class="relacionadas-noticia-icono">📄</div>
                                <div class="relacionadas-noticia-info">
                                    <div class="relacionadas-noticia-titulo">
                                        <?php echo htmlspecialchars(truncarTexto($rel['origen_titulo'], 60)); ?>

                                    </div>
                                    <div class="relacionadas-noticia-fecha">
                                        <?php echo formatearFecha($rel['origen_fecha'], 'd/m/Y'); ?>

                                    </div>
                                </div>
                            </div>
                            
                            <div class="relacionadas-flecha">⬇️</div>
                            
                            <div class="relacionadas-noticia relacionadas-noticia-destino">
                                <div class="relacionadas-noticia-icono">🔗</div>
                                <div class="relacionadas-noticia-info">
                                    <div class="relacionadas-noticia-titulo">
                                        <?php echo htmlspecialchars(truncarTexto($rel['destino_titulo'], 60)); ?>

                                    </div>
                                    <div class="relacionadas-noticia-fecha">
                                        <?php echo formatearFecha($rel['destino_fecha'], 'd/m/Y'); ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="relacionadas-item-footer">
                            <form method="POST" class="relacionadas-form-eliminar" onsubmit="return confirm('¿Eliminar esta relación?')">
                                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_relacion" value="<?php echo $rel['id_relacion']; ?>">

                                <button type="submit" class="relacionadas-btn relacionadas-btn-eliminar" title="Eliminar relación">
                                    🗑️ Eliminar relación
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>
</div>

<!-- SELECTOR MEJORADO (opcional) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" />

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        $('.relacionadas-select-search').select2({
            placeholder: 'Buscar noticia...',
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
