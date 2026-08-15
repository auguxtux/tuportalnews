<?php

declare(strict_types=1);

/**
 * GESTIÓN GLOBAL DE NOTICIAS (admin)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();

// Procesar acciones
$accion = (string) ($_POST['accion'] ?? '');
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', 'Error de seguridad');
        redireccionar(route('admin_noticias'));
    }

    try {
        switch ($id > 0 ? $accion : '') {
            case 'publicar':
                $stmt = $pdo->prepare("UPDATE noticias SET estado = 'publicada' WHERE id_noticia = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Noticia publicada');
                break;

            case 'archivar':
                $stmt = $pdo->prepare("UPDATE noticias SET estado = 'archivada' WHERE id_noticia = :id");
                $stmt->execute([':id' => $id]);
                mensajeFlash('success', 'Noticia archivada');
                break;

            case 'eliminar':
                $resultado = eliminarNoticiasCompletamente(
                    $pdo,
                    [$id],
                    (int) ($_SESSION['usuario_id'] ?? 0),
                    true
                );
                mensajeFlash(
                    $resultado['success'] ? 'success' : 'error',
                    $resultado['message']
                );
                break;
        }
    } catch (Throwable $e) {
        mensajeFlash('error', 'Error al procesar la acción');
        registrarErrorInterno('ADMIN.NOTICIAS.ACCION', $e);
    }

    redireccionar(route('admin_noticias'));
}

// Filtros
$filtro_fuente = $_GET['fuente'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_categoria = isset($_GET['categoria']) ? (int) $_GET['categoria'] : 0;
$filtro_autor = isset($_GET['autor']) ? (int) $_GET['autor'] : 0;
$busqueda = $_GET['q'] ?? '';

// Paginación
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;
$error = null;
$total_noticias = 0;
$total_paginas = 1;

try {
    $sql_count = "SELECT COUNT(*) FROM noticias n";
    $sql = "SELECT n.*, u.nombre as autor_nombre, c.nombre_categoria,
                   f.nombre AS fuente_normal_nombre,
                   fr.nombre AS fuente_rss_nombre,
                   (SELECT COUNT(*) FROM comentarios WHERE id_noticia = n.id_noticia AND estado = 'aprobado') as comentarios_count
            FROM noticias n
            JOIN usuarios u ON n.id_autor = u.id_usuario
            JOIN categorias c ON n.id_categoria = c.id_categoria
            LEFT JOIN fuentes f ON f.id_fuente = n.id_fuente
            LEFT JOIN fuentes_rss fr ON fr.id_fuente = n.id_fuente_rss";
    $where = [];
    $params = [];

    if ($filtro_estado !== '') {
        $where[] = "n.estado = :estado";
        $params[':estado'] = $filtro_estado;
    }

    if ($filtro_categoria > 0) {
        $where[] = "n.id_categoria = :categoria";
        $params[':categoria'] = $filtro_categoria;
    }

    if ($filtro_autor > 0) {
        $where[] = "n.id_autor = :autor";
        $params[':autor'] = $filtro_autor;
    }

    if ($filtro_fuente !== '') {
        $where[] = "n.fuente = :fuente";
        $params[':fuente'] = $filtro_fuente;
    }

    if ($busqueda !== '') {
        $where[] = "(n.titulo LIKE :q OR n.contenido LIKE :q)";
        $params[':q'] = "%{$busqueda}%";
    }

    if (!empty($where)) {
        $where_clause = " WHERE " . implode(" AND ", $where);
        $sql .= $where_clause;
        $sql_count .= $where_clause;
    }

    $sql .= " ORDER BY n.fecha_publicacion DESC";

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_noticias = (int) $stmt_count->fetchColumn();
    $total_paginas = (int) ceil($total_noticias / $por_pagina);

    $sql .= " LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $noticias = $stmt->fetchAll();

    $categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria")->fetchAll();
    $autores = $pdo->query("SELECT id_usuario, nombre FROM usuarios WHERE rol IN ('periodista', 'admin') ORDER BY nombre")->fetchAll();
    $fuentes = $pdo->query("SELECT DISTINCT fuente FROM noticias WHERE fuente IS NOT NULL AND fuente != '' ORDER BY fuente")->fetchAll();
} catch (Throwable $e) {
    $error = 'Error al cargar noticias';
    registrarErrorInterno('ADMIN.NOTICIAS.CARGA', $e);
}

$titulo_pagina = 'Gestión de Noticias';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('admin-noticias.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('news-cards.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="admin-noticias-filtros-avanzados">
    <h1>Gestión de Noticias</h1>
    <form method="GET" class="admin-noticias-filtros-form">
        <input type="text" name="q" placeholder="Buscar por título..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">

        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="publicada" <?= $filtro_estado === 'publicada' ? 'selected' : ''; ?>>Publicada</option>
            <option value="borrador" <?= $filtro_estado === 'borrador' ? 'selected' : ''; ?>>Borrador</option>
            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
            <option value="archivada" <?= $filtro_estado === 'archivada' ? 'selected' : ''; ?>>Archivada</option>
        </select>

        <select name="categoria">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int) $cat['id_categoria']; ?>" <?= $filtro_categoria === (int) $cat['id_categoria'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $cat['nombre_categoria'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="autor">
            <option value="">Todos los autores</option>
            <?php foreach ($autores as $autor): ?>
                <option value="<?= (int) $autor['id_usuario']; ?>" <?= $filtro_autor === (int) $autor['id_usuario'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $autor['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="fuente">
            <option value="">Todas las fuentes</option>
            <?php foreach ($fuentes as $f): ?>
                <option value="<?= htmlspecialchars((string) $f['fuente'], ENT_QUOTES, 'UTF-8'); ?>" <?= $filtro_fuente === $f['fuente'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $f['fuente'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="admin-noticias-btn admin-noticias-btn-filtrar">Filtrar</button>
        <a href="<?= htmlspecialchars(route('admin_noticias'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-noticias-btn admin-noticias-btn-limpiar">Limpiar</a>
    </form>
</div>

<?php if ($error !== null): ?>
    <div class="admin-noticias-alerta admin-noticias-alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (empty($noticias)): ?>
    <div class="admin-noticias-alerta admin-noticias-alerta-info"><p>No hay noticias con los criterios seleccionados.</p></div>
<?php else: ?>
    <p class="admin-noticias-resultados-info">Mostrando <?= count($noticias); ?> de <?= $total_noticias; ?> noticias</p>

    <div class="admin-noticias-grid">
        <?php foreach ($noticias as $n): ?>
            <?php
            $rutaNoticia = route(
                (int) ($n['privada'] ?? 0) === 1 ? 'privado_noticia' : 'noticia',
                ['id' => (int) $n['id_noticia']]
            );
            $claseEstado = match ($n['estado'] ?? '') {
                'borrador' => ' news-card--draft',
                'pendiente' => ' news-card--pending',
                'archivada' => ' news-card--archived',
                default => '',
            };
            $claseTipo = (int) ($n['privada'] ?? 0) === 1
                ? ' news-card--private'
                : (!empty($n['id_fuente_rss'])
                    ? ' news-card--external'
                    : ' news-card--public');
            ?>
            <div class="admin-noticias-card news-card news-card--vertical<?= $claseTipo . $claseEstado; ?>">
                <h3 class="news-card__title"><a href="<?= htmlspecialchars($rutaNoticia, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $n['titulo'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                <?php if ($n['subtitulo']): ?><p class="news-card__subtitle"><?= htmlspecialchars(truncarTexto((string) $n['subtitulo'], 80), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

                <div class="admin-noticias-card-imagen news-card__media" onclick="verImagen('<?= htmlspecialchars($n['imagen_principal'] ? base_url('uploads/noticias/' . $n['imagen_principal']) : ($n['imagen_externa'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                    <?php if ($n['imagen_principal']): ?>
                        <img src="<?= htmlspecialchars(base_url('uploads/noticias/' . $n['imagen_principal']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars((string) $n['titulo'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                    <?php elseif ($n['imagen_externa']): ?>
                        <img src="<?= htmlspecialchars((string) $n['imagen_externa'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars((string) $n['titulo'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="admin-noticias-card-sin-imagen">📷 Sin imagen</div>
                    <?php endif; ?>
                    <span class="admin-noticias-card-id">#<?= (int) $n['id_noticia']; ?></span>
                    <?php
                    $estado_icono = match ($n['estado']) {
                        'publicada' => '✅', 'borrador' => '📝',
                        'pendiente' => '⏳', 'archivada' => '📦', default => '',
                    };
                    $estado_texto = match ($n['estado']) {
                        'publicada' => 'Publicada', 'borrador' => 'Borrador',
                        'pendiente' => 'Pendiente', 'archivada' => 'Archivada', default => (string) $n['estado'],
                    };
                    ?>
                    <span class="admin-noticias-card-estado-badge admin-noticias-badge-<?= htmlspecialchars((string) $n['estado'], ENT_QUOTES, 'UTF-8'); ?>"><?= $estado_icono . ' ' . $estado_texto; ?></span>
                </div>

                <div class="admin-noticias-card-body news-card__body">
                    <div class="news-card__meta news-card__meta--standard">
                        <span>✍️ <a href="<?= htmlspecialchars(route('admin_noticias', ['autor' => (int) $n['id_autor']]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $n['autor_nombre'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                        <span>📂 <a href="<?= htmlspecialchars(route('admin_noticias', ['categoria' => (int) $n['id_categoria']]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $n['nombre_categoria'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                        <?php if (!empty($n['fuente_rss_nombre'])): ?>
                            <span>📡 <a href="<?= htmlspecialchars(route('admin_noticias', ['fuente' => (string) $n['fuente']]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $n['fuente_rss_nombre'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                        <?php elseif (!empty($n['fuente_normal_nombre'])): ?>
                            <span>📰 <a href="<?= htmlspecialchars(route('admin_noticias', ['fuente' => (string) $n['fuente']]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $n['fuente_normal_nombre'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                        <?php elseif (!empty($n['fuente'])): ?>
                            <span>🔗 <?= htmlspecialchars((string) $n['fuente'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span>📅 <?= formatearFecha((string) $n['fecha_publicacion']); ?></span>
                    </div>
                    <div>
                        <span>👁️ <?= number_format((int) $n['visitas']); ?></span>
                        <span>💬 <a href="<?= htmlspecialchars(route((int) ($n['privada'] ?? 0) === 1 ? 'privado_comentarios' : 'comentarios_noticia', ['id' => (int) $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>"><?= (int) ($n['comentarios_count'] ?? 0); ?></a></span>
                    </div>
                </div>

                <div class="admin-noticias-card-footer news-card__actions">
                    <a href="<?= htmlspecialchars($rutaNoticia, ENT_QUOTES, 'UTF-8'); ?>" class="admin-noticias-btn admin-noticias-btn-ver">👁️ Ver</a>
                    <a href="<?= htmlspecialchars(route('admin_editar_noticia', ['id' => (int) $n['id_noticia']]), ENT_QUOTES, 'UTF-8'); ?>" class="admin-noticias-btn admin-noticias-btn-editar">✏️ Editar</a>
                    <?php if ($n['estado'] !== 'publicada'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Publicar esta noticia?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="publicar">
                            <input type="hidden" name="id" value="<?= (int) $n['id_noticia']; ?>">
                            <button type="submit" class="admin-noticias-btn admin-noticias-btn-publicar">✅ Publicar</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($n['estado'] !== 'archivada'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Archivar?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="archivar">
                            <input type="hidden" name="id" value="<?= (int) $n['id_noticia']; ?>">
                            <button type="submit" class="admin-noticias-btn admin-noticias-btn-archivar">📦 Archivar</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿ELIMINAR esta noticia?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int) $n['id_noticia']; ?>">
                        <button type="submit" class="admin-noticias-btn admin-noticias-btn-eliminar">🗑️ Eliminar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="admin-noticias-paginacion">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1; ?>&estado=<?= urlencode($filtro_estado); ?>&categoria=<?= $filtro_categoria; ?>&autor=<?= $filtro_autor; ?>&q=<?= urlencode($busqueda); ?>" class="admin-noticias-pagina-btn">« Anterior</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="admin-noticias-pagina-activo"><?= $i; ?></span>
                <?php else: ?>
                    <a href="?pagina=<?= $i; ?>&estado=<?= urlencode($filtro_estado); ?>&categoria=<?= $filtro_categoria; ?>&autor=<?= $filtro_autor; ?>&q=<?= urlencode($busqueda); ?>" class="admin-noticias-pagina-link"><?= $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1; ?>&estado=<?= urlencode($filtro_estado); ?>&categoria=<?= $filtro_categoria; ?>&autor=<?= $filtro_autor; ?>&q=<?= urlencode($busqueda); ?>" class="admin-noticias-pagina-btn">Siguiente »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div id="adminNoticiasImagenModal" class="admin-noticias-modal" style="display:none;">
    <span class="admin-noticias-modal-cerrar" onclick="cerrarModalImagen()">&times;</span>
    <img class="admin-noticias-modal-contenido" id="adminNoticiasImagenAmpliada" alt="Imagen ampliada">
    <div id="adminNoticiasModalCaption"></div>
</div>

<script>
function verImagen(url) {
    var modal = document.getElementById('adminNoticiasImagenModal');
    var modalImg = document.getElementById('adminNoticiasImagenAmpliada');
    if (url && url !== '') {
        modal.style.display = "block";
        modalImg.src = url;
    }
}
function cerrarModalImagen() {
    document.getElementById('adminNoticiasImagenModal').style.display = "none";
}
document.addEventListener('keydown', function(e) { if (e.key === "Escape") cerrarModalImagen(); });
window.onclick = function(event) {
    var modal = document.getElementById('adminNoticiasImagenModal');
    if (event.target == modal) modal.style.display = "none";
};
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
