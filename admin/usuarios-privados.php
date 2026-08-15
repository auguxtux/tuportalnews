<?php

declare(strict_types=1);

/**
 * ADMIN - USUARIOS CON ACCESO PRIVADO
 * Solo administradores
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/privado.php';

Permisos::requerirAdmin();

$pdo = db();

// ============================================
// PROCESAR ACCIONES POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', '❌ Error de seguridad.');
    } else {
        $accion_post = $_POST['accion'] ?? '';
        $id_post = (int) ($_POST['id'] ?? 0);
        $confirmar = (int) ($_POST['confirmar'] ?? 0);
        if ($accion_post === 'guardar_correo' && $id_post > 0) {
            $correoCorporativo = strtolower(trim((string) ($_POST['correo_corporativo'] ?? '')));
            $dominioCorporativo = $correoCorporativo !== ''
                ? strtolower((string) substr(strrchr($correoCorporativo, '@') ?: '', 1))
                : '';

            if (
                $correoCorporativo !== ''
                && (
                    strlen($correoCorporativo) > 255
                    || filter_var($correoCorporativo, FILTER_VALIDATE_EMAIL) === false
                    || $dominioCorporativo !== 'erun.es'
                )
            ) {
                mensajeFlash('error', 'Introduce un correo corporativo válido del dominio erun.es.');
            } else {
                try {
                    if ($correoCorporativo !== '') {
                        $stmt = $pdo->prepare(
                            'SELECT 1
                             FROM usuarios_privados
                             WHERE correo_corporativo = ? AND id_usuario <> ?'
                        );
                        $stmt->execute([$correoCorporativo, $id_post]);

                        if ($stmt->fetchColumn()) {
                            throw new DomainException('El correo corporativo ya está asignado a otro colaborador.');
                        }
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE usuarios_privados
                         SET correo_corporativo = ?
                         WHERE id_usuario = ?'
                    );
                    $stmt->execute([
                        $correoCorporativo !== '' ? $correoCorporativo : null,
                        $id_post,
                    ]);

                    mensajeFlash('success', '📧 Correo corporativo actualizado correctamente.');
                } catch (DomainException $e) {
                    mensajeFlash('error', $e->getMessage());
                } catch (Throwable $e) {
                    mensajeFlash('error', 'No se pudo actualizar el correo corporativo.');
                    registrarErrorInterno('ADMIN.COLABORADORES.CORREO', $e);
                }
            }
        } elseif ($confirmar && $accion_post !== '' && $id_post > 0) {
            try {
                $mensaje = '';

                // Acción sobre el usuario
                if ($accion_post === 'activar_privado') {
                    $stmt = $pdo->prepare("UPDATE usuarios_privados SET activo = 1 WHERE id_usuario = ?");
                    $stmt->execute([$id_post]);
                    $mensaje = '🔒 Acceso privado activado correctamente';
                } elseif (in_array($accion_post, ['desactivar_privado', 'eliminar_privado'], true)) {
                    $resultado = reasignarColaboradorAArticulista($pdo, $id_post);
                    if (!$resultado['success']) {
                        throw new RuntimeException($resultado['message']);
                    }
                    $mensaje = '🔓 ' . $resultado['message'];
                }

                mensajeFlash('success', $mensaje);
            } catch (Throwable $e) {
                mensajeFlash('error', 'Error al procesar');
                registrarErrorInterno('ADMIN.COLABORADORES.ACCION', $e);
            }
        }
    }

    redireccionar(route('admin_usuarios_privados'));
}

// ============================================
// FILTROS Y PAGINACIÓN
// ============================================
$busqueda = $_GET['q'] ?? '';
$filtro_activo = $_GET['activo'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;
$error = null;

try {
    $sql_count = "SELECT COUNT(*) FROM usuarios u INNER JOIN usuarios_privados up ON u.id_usuario = up.id_usuario WHERE u.rol = 'periodista'";
    $sql = "SELECT u.id_usuario, u.nombre, u.email, u.avatar, u.estado as usuario_estado, up.fecha_alta, up.activo as privado_activo,
                   up.correo_corporativo,
                   COUNT(DISTINCT n.id_noticia) as total_noticias_privadas,
                   COALESCE(SUM(n.visitas), 0) as total_visitas,
                   COALESCE(SUM(ep.visitas_privadas), 0) as visitas_priv,
                   COALESCE(SUM(ep.megusta_privados), 0) as likes_priv
            FROM usuarios u
            INNER JOIN usuarios_privados up ON u.id_usuario = up.id_usuario
            LEFT JOIN noticias n ON u.id_usuario = n.id_autor AND n.privada = 1
            LEFT JOIN estadisticas_privadas ep ON n.id_noticia = ep.id_noticia
            WHERE u.rol = 'periodista'";
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND (u.nombre LIKE :q OR u.email LIKE :q)";
        $sql_count .= " AND (u.nombre LIKE :q OR u.email LIKE :q)";
        $params[':q'] = "%{$busqueda}%";
    }

    if ($filtro_activo !== '') {
        $sql .= " AND up.activo = :activo";
        $sql_count .= " AND up.activo = :activo";
        $params[':activo'] = (int) $filtro_activo;
    }

    $sql .= " GROUP BY u.id_usuario ORDER BY up.activo DESC, u.nombre ASC LIMIT :limit OFFSET :offset";

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_usuarios = (int) $stmt_count->fetchColumn();
    $total_paginas = (int) ceil($total_usuarios / $por_pagina);

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Error al cargar usuarios privados';
    registrarErrorInterno('ADMIN.COLABORADORES.CARGA', $e);
}

// Modal
$modal_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$modal_tipo = $_GET['modal'] ?? '';
$modal_usuario = null;

if ($modal_tipo !== '' && $modal_id > 0 && in_array($modal_tipo, ['desactivar', 'eliminar'], true)) {
    $stmt = $pdo->prepare("SELECT u.nombre, u.email, COUNT(n.id_noticia) as total_privadas FROM usuarios u LEFT JOIN noticias n ON u.id_usuario = n.id_autor AND n.privada = 1 WHERE u.id_usuario = ? GROUP BY u.id_usuario");
    $stmt->execute([$modal_id]);
    $modal_usuario = $stmt->fetch();
}

$titulo_pagina = 'Gestión de Colaboradores';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('admin-confirm-modal.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('usuarios-privados.css'), ENT_QUOTES, 'UTF-8'); ?>">

<header class="privados-encabezado">
    <h1 class="privados-titulo">🔒 Colaboradores con acceso privado</h1>
    <p>Administra el acceso al área privada y consulta la actividad de cada colaborador.</p>
</header>

<div class="privados-barra-gestion">
    <form method="GET" class="privados-filtros-form">
        <div class="privados-filtro-grupo">
            <label for="privados-busqueda">Nombre o correo</label>
            <input id="privados-busqueda" class="privados-campo-busqueda" type="search" name="q" placeholder="Buscar colaborador..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="privados-filtro-grupo privados-filtro-estado-grupo">
            <label for="privados-estado">Acceso privado</label>
            <select id="privados-estado" class="privados-filtro-estado" name="activo">
                <option value="">Todos</option>
                <option value="1" <?= $filtro_activo === '1' ? 'selected' : ''; ?>>Activo</option>
                <option value="0" <?= $filtro_activo === '0' ? 'selected' : ''; ?>>Inactivo</option>
            </select>
        </div>
        <div class="privados-filtro-acciones">
            <button type="submit" class="privados-btn privados-btn-filtrar">🔍 Filtrar</button>
            <a href="<?= htmlspecialchars(route('admin_usuarios_privados'), ENT_QUOTES, 'UTF-8'); ?>" class="privados-btn privados-btn-limpiar">Limpiar</a>
        </div>
    </form>
</div>

<?php if ($error !== null): ?>
    <div class="privados-alerta privados-alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (empty($usuarios)): ?>
    <div class="privados-vacio">No hay colaboradores que coincidan con los filtros.</div>
<?php else: ?>
    <p class="privados-resultados-info">Mostrando <strong><?= count($usuarios); ?></strong> de <strong><?= $total_usuarios; ?></strong> colaboradores</p>

    <div class="privados-grid">
        <?php foreach ($usuarios as $usr): ?>
            <div class="privados-card">
                <div class="privados-card-header">
                    <div class="privados-card-avatar">
                        <img src="<?= htmlspecialchars(base_url('uploads/perfiles/' . ($usr['avatar'] ?? 'default-avatar.png')), ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar de <?= htmlspecialchars((string) $usr['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <span class="privados-card-id">#<?= (int) $usr['id_usuario']; ?></span>
                </div>
                <div class="privados-card-body">
                    <h3 class="privados-card-nombre"><a href="<?= htmlspecialchars(route('admin_editar_periodista', ['id' => (int) $usr['id_usuario']]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $usr['nombre'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <div class="privados-card-email">📧 <?= htmlspecialchars((string) $usr['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <form method="POST" class="privados-correo-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="accion" value="guardar_correo">
                        <input type="hidden" name="id" value="<?= (int) $usr['id_usuario']; ?>">
                        <label for="correo-corporativo-<?= (int) $usr['id_usuario']; ?>">
                            Correo corporativo
                        </label>
                        <div class="privados-correo-controles">
                            <input
                                id="correo-corporativo-<?= (int) $usr['id_usuario']; ?>"
                                type="email"
                                name="correo_corporativo"
                                maxlength="255"
                                placeholder="nombre@erun.es"
                                value="<?= htmlspecialchars((string) ($usr['correo_corporativo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <button type="submit" class="privados-btn privados-btn-correo">
                                💾 Guardar
                            </button>
                        </div>
                    </form>
                    <div class="privados-card-fecha">📅 Acceso desde <?= date('d/m/Y', strtotime((string) $usr['fecha_alta'])); ?></div>
                    <div class="privados-card-badges">
                        <span class="privados-badge <?= $usr['usuario_estado'] === 'activo' ? 'privados-badge-activo' : 'privados-badge-inactivo'; ?>"><?= htmlspecialchars(ucfirst((string) $usr['usuario_estado']), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="privados-badge <?= $usr['privado_activo'] ? 'privados-badge-privado-activo' : 'privados-badge-privado-inactivo'; ?>"><?= $usr['privado_activo'] ? '🔒 Acceso activo' : '⏸️ Acceso inactivo'; ?></span>
                    </div>
                    <div class="privados-card-stats">
                        <div class="privados-stat-item"><span class="privados-stat-valor"><?= (int) $usr['total_noticias_privadas']; ?></span><span class="privados-stat-etiqueta">Noticias</span></div>
                        <div class="privados-stat-item"><span class="privados-stat-valor"><?= number_format((int) $usr['visitas_priv']); ?></span><span class="privados-stat-etiqueta">Visitas</span></div>
                        <div class="privados-stat-item"><span class="privados-stat-valor"><?= number_format((int) $usr['likes_priv']); ?></span><span class="privados-stat-etiqueta">Me gusta</span></div>
                    </div>
                </div>
                <div class="privados-card-footer">
                    <div class="privados-acciones-botones">
                        <?php if ($usr['privado_activo']): ?>
                            <a href="?modal=desactivar&amp;id=<?= (int) $usr['id_usuario']; ?>" class="privados-btn privados-btn-desactivar">🔓 Desactivar</a>
                        <?php else: ?>
                            <form method="POST" class="privados-accion-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="activar_privado">
                                <input type="hidden" name="id" value="<?= (int) $usr['id_usuario']; ?>">
                                <input type="hidden" name="confirmar" value="1">
                                <button type="submit" class="privados-btn privados-btn-activar">🔒 Activar</button>
                            </form>
                        <?php endif; ?>
                        <a href="?modal=eliminar&amp;id=<?= (int) $usr['id_usuario']; ?>" class="privados-btn privados-btn-eliminar" aria-label="Eliminar acceso privado de <?= htmlspecialchars((string) $usr['nombre'], ENT_QUOTES, 'UTF-8'); ?>">🗑️ Eliminar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="privados-paginacion">
            <?php if ($pagina > 1): ?>
                <a class="privados-pagina-btn" href="?pagina=<?= $pagina - 1; ?>&amp;q=<?= urlencode($busqueda); ?>&amp;activo=<?= urlencode($filtro_activo); ?>">« Anterior</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="privados-pagina-numero privados-pagina-activo"><?= $i; ?></span>
                <?php else: ?>
                    <a class="privados-pagina-numero" href="?pagina=<?= $i; ?>&amp;q=<?= urlencode($busqueda); ?>&amp;activo=<?= urlencode($filtro_activo); ?>"><?= $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagina < $total_paginas): ?>
                <a class="privados-pagina-btn" href="?pagina=<?= $pagina + 1; ?>&amp;q=<?= urlencode($busqueda); ?>&amp;activo=<?= urlencode($filtro_activo); ?>">Siguiente »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- MODAL -->
<?php if ($modal_usuario): ?>
<div class="privados-modal">
    <div class="privados-modal-contenido">
        <div class="privados-modal-header">
            <h3>⚠️ <?= $modal_tipo === 'desactivar' ? 'Desactivar acceso' : 'Eliminar del listado'; ?></h3>
            <a href="<?= htmlspecialchars(route('admin_usuarios_privados'), ENT_QUOTES, 'UTF-8'); ?>" class="privados-modal-cerrar">&times;</a>
        </div>
        <div class="privados-modal-body">
            <p>Usuario: <strong><?= htmlspecialchars((string) $modal_usuario['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong> (<?= htmlspecialchars((string) $modal_usuario['email'], ENT_QUOTES, 'UTF-8'); ?>)</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="<?= $modal_tipo === 'desactivar' ? 'desactivar_privado' : 'eliminar_privado'; ?>">
                <input type="hidden" name="id" value="<?= $modal_id; ?>">
                <input type="hidden" name="confirmar" value="1">
                <div class="privados-modal-alerta">
                    <strong>📰 <?= (int) $modal_usuario['total_privadas']; ?> noticias privadas</strong>
                    <p>Se eliminarán definitivamente estas noticias, sus dependencias y todos los comentarios escritos por el colaborador. Sus noticias públicas se conservarán.</p>
                </div>
                <div class="privados-modal-buttons">
                    <a href="<?= htmlspecialchars(route('admin_usuarios_privados'), ENT_QUOTES, 'UTF-8'); ?>" class="privados-btn-secondary">Cancelar</a>
                    <button type="submit" class="privados-btn-<?= $modal_tipo === 'eliminar' ? 'danger' : 'warning'; ?>">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
