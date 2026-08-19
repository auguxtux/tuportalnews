<?php
declare(strict_types=1);

/**
 * CONFIGURACIÓN DE FUENTES RSS EXTERNAS
 * Solo para administradores.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/rss.php';
require_once __DIR__ . '/../includes/logs.php';

Permisos::requerirAdmin();

$pdo = db();
$errores = [];
$mensaje = '';
$fuente_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $errores[] = 'Error de seguridad. Recarga la página e inténtalo de nuevo.';
    } else {
        $accion = (string) ($_POST['accion'] ?? '');

        try {
            if ($accion === 'crear' || $accion === 'editar') {
                $validacion = validarConfiguracionFuenteRss(
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['url'] ?? '')
                );
                $errores = array_merge($errores, $validacion['errores']);
                $datos = $validacion['datos'];
                $idFuente = (int) ($_POST['id_fuente'] ?? 0);

                if ($accion === 'editar' && $idFuente <= 0) {
                    $errores[] = 'La fuente indicada no es válida';
                }

                if ($datos !== null && $errores === []) {
                    $sqlDuplicado = "SELECT id_fuente FROM fuentes_rss WHERE LOWER(url) = LOWER(?)";
                    $parametrosDuplicado = [$datos['url']];

                    if ($accion === 'editar') {
                        $sqlDuplicado .= ' AND id_fuente != ?';
                        $parametrosDuplicado[] = $idFuente;
                    }

                    $stmt = $pdo->prepare($sqlDuplicado . ' LIMIT 1');
                    $stmt->execute($parametrosDuplicado);

                    if ($stmt->fetchColumn()) {
                        $errores[] = 'Esta fuente RSS ya está configurada';
                    } elseif ($accion === 'crear') {
                        $idPropietario = (int) ($_SESSION['usuario_id'] ?? 0);
                        if ($idPropietario <= 0) {
                            throw new RuntimeException('Usuario autenticado sin identificador válido');
                        }

                        $idRegion = (int) ($_POST['id_region'] ?? 0) ?: null;

                        $stmt = $pdo->prepare(
                            'INSERT INTO fuentes_rss '
                            . '(id_propietario, nombre, url, id_region, activa) '
                            . 'VALUES (?, ?, ?, ?, 1)'
                        );
                        $stmt->execute([
                            $idPropietario,
                            $datos['nombre'],
                            $datos['url'],
                            $idRegion,
                        ]);
                        $idCreado = (int) $pdo->lastInsertId();
                        $mensaje = '✅ Fuente RSS añadida y activada correctamente';
                        registrarLog(
                            'admin_rss_agregar',
                            null,
                            null,
                            "Fuente RSS creada: ID {$idCreado}"
                        );
                    } else {
                        $idRegion = (int) ($_POST['id_region'] ?? 0) ?: null;

                        $stmt = $pdo->prepare(
                            'UPDATE fuentes_rss SET nombre = ?, url = ?, id_region = ? WHERE id_fuente = ?'
                        );
                        $stmt->execute([$datos['nombre'], $datos['url'], $idRegion, $idFuente]);

                        if ($stmt->rowCount() === 0) {
                            $comprobar = $pdo->prepare(
                                'SELECT COUNT(*) FROM fuentes_rss WHERE id_fuente = ?'
                            );
                            $comprobar->execute([$idFuente]);
                            if ((int) $comprobar->fetchColumn() === 0) {
                                $errores[] = 'La fuente indicada no existe';
                            }
                        }

                        if ($errores === []) {
                            $mensaje = '✅ Fuente RSS actualizada correctamente';
                            registrarLog(
                                'admin_rss_editar',
                                null,
                                null,
                                "Fuente RSS editada: ID {$idFuente}"
                            );
                        }
                    }
                }
            } elseif ($accion === 'activar' || $accion === 'desactivar') {
                $idFuente = (int) ($_POST['id_fuente'] ?? 0);
                $nuevoEstado = $accion === 'activar' ? 1 : 0;

                if ($idFuente <= 0) {
                    $errores[] = 'La fuente indicada no es válida';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE fuentes_rss SET activa = ? WHERE id_fuente = ?'
                    );
                    $stmt->execute([$nuevoEstado, $idFuente]);

                    $comprobar = $pdo->prepare(
                        'SELECT COUNT(*) FROM fuentes_rss WHERE id_fuente = ?'
                    );
                    $comprobar->execute([$idFuente]);

                    if ((int) $comprobar->fetchColumn() === 0) {
                        $errores[] = 'La fuente indicada no existe';
                    } else {
                        $mensaje = $nuevoEstado === 1
                            ? '✅ Fuente RSS activada'
                            : '✅ Fuente RSS desactivada';
                        registrarLog(
                            $nuevoEstado === 1 ? 'admin_rss_activar' : 'admin_rss_desactivar',
                            null,
                            null,
                            "Estado de fuente RSS actualizado: ID {$idFuente}"
                        );
                    }
                }
            } elseif ($accion === 'eliminar') {
                $idFuente = (int) ($_POST['id_fuente'] ?? 0);

                if ($idFuente <= 0) {
                    $errores[] = 'La fuente indicada no es válida';
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'SELECT nombre FROM fuentes_rss WHERE id_fuente = ? FOR UPDATE'
                    );
                    $stmt->execute([$idFuente]);
                    $nombreFuente = $stmt->fetchColumn();

                    if ($nombreFuente === false) {
                        $pdo->rollBack();
                        $errores[] = 'La fuente indicada no existe';
                    } else {
                        $stmt = $pdo->prepare(
                            'SELECT COUNT(*) FROM noticias WHERE id_fuente_rss = ?'
                        );
                        $stmt->execute([$idFuente]);
                        $totalNoticias = (int) $stmt->fetchColumn();

                        $stmt = $pdo->prepare(
                            'DELETE FROM fuentes_rss WHERE id_fuente = ?'
                        );
                        $stmt->execute([$idFuente]);
                        $pdo->commit();

                        $mensaje = "✅ Fuente RSS eliminada. Sus {$totalNoticias} noticias asociadas se conservan.";
                        registrarLog(
                            'admin_rss_eliminar',
                            null,
                            null,
                            "Fuente RSS eliminada: ID {$idFuente}; noticias: {$totalNoticias}"
                        );
                    }
                }
            } else {
                $errores[] = 'Acción no válida';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            registrarErrorInterno('ADMIN.RSS.GESTION', $e);
            $errores[] = 'No se pudo completar la operación';
        }
    }
}

if (isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    if ($idEditar > 0) {
        $stmt = $pdo->prepare('SELECT * FROM fuentes_rss WHERE id_fuente = ?');
        $stmt->execute([$idEditar]);
        $fuente_editar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($fuente_editar === null) {
            $errores[] = 'La fuente indicada no existe';
        }
    }
}

$fuentes = $pdo->query("
    SELECT
        fr.*,
        u.nombre AS propietario_nombre,
        u.rol AS propietario_rol,
        r.nombre AS region_nombre,
        COUNT(n.id_noticia) AS total_noticias
    FROM fuentes_rss fr
    INNER JOIN usuarios u ON u.id_usuario = fr.id_propietario
    LEFT JOIN noticias n ON n.id_fuente_rss = fr.id_fuente
    LEFT JOIN regiones r ON r.id_region = fr.id_region
    GROUP BY fr.id_fuente
    ORDER BY fr.activa DESC, fr.nombre
")->fetchAll(PDO::FETCH_ASSOC);

$regiones = $pdo->query(
    "SELECT id_region, nombre FROM regiones WHERE activa = 1 ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Gestión RSS Externo';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-rss-config.css'); ?>">

<div class="rss-config-container">
    <h1>📡 Configuración de Fuentes RSS</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($errores !== []): ?>
        <div class="alert alert-error">
            <?php foreach ($errores as $error): ?>
                <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rss-config-grid">
        <div class="rss-config-card">
            <h2><?php echo $fuente_editar ? '✏️ Editar fuente RSS' : '➕ Añadir fuente RSS'; ?></h2>

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>"
                >
                <input
                    type="hidden"
                    name="accion"
                    value="<?php echo $fuente_editar ? 'editar' : 'crear'; ?>"
                >

                <?php if ($fuente_editar): ?>
                    <input
                        type="hidden"
                        name="id_fuente"
                        value="<?php echo (int) $fuente_editar['id_fuente']; ?>"
                    >
                <?php endif; ?>

                <div class="campo">
                    <label for="nombre">📝 Nombre de la fuente:</label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        maxlength="100"
                        required
                        value="<?php echo htmlspecialchars((string) ($fuente_editar['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Ej: Medio de noticias"
                    >
                </div>

                <div class="campo">
                    <label for="url">🔗 URL del feed RSS:</label>
                    <input
                        type="url"
                        id="url"
                        name="url"
                        maxlength="500"
                        required
                        value="<?php echo htmlspecialchars((string) ($fuente_editar['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="https://ejemplo.com/feed.xml"
                    >
                    <small>La URL se comprobará antes de guardarla.</small>
                </div>

                <div class="campo">
                    <label for="id_region">📍 Región:</label>
                    <select id="id_region" name="id_region">
                        <option value="">-- Sin región (internacional) --</option>
                        <?php foreach ($regiones as $region): ?>
                            <option
                                value="<?php echo (int) $region['id_region']; ?>"
                                <?php echo ((int) ($fuente_editar['id_region'] ?? 0)) === (int) $region['id_region'] ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars((string) $region['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Las noticias importadas de esta fuente se clasificarán automáticamente en esta región.</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $fuente_editar ? '💾 Guardar cambios' : '➕ Añadir fuente'; ?>
                </button>

                <?php if ($fuente_editar): ?>
                    <a href="<?php echo route('admin_rss'); ?>" class="btn">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="rss-config-card">
            <h2>📋 Fuentes configuradas</h2>

            <?php if ($fuentes === []): ?>
                <p style="color: #6b7280; text-align: center; padding: 2rem;">
                    No hay fuentes RSS configuradas.
                </p>
            <?php else: ?>
                <?php foreach ($fuentes as $fuente): ?>
                    <?php
                    $idFuente = (int) $fuente['id_fuente'];
                    $activa = (int) $fuente['activa'] === 1;
                    $totalNoticias = (int) $fuente['total_noticias'];
                    ?>
                    <div class="fuente-item">
                        <div class="fuente-info">
                            <strong>
                                <?php echo htmlspecialchars((string) $fuente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                            <small>
                                <?php echo htmlspecialchars((string) $fuente['url'], ENT_QUOTES, 'UTF-8'); ?>
                            </small>
                            <div class="limite">
                                <?php echo $activa ? '✅ Activa' : '⏸️ Inactiva'; ?>
                                · 📰 <?php echo $totalNoticias; ?> noticias
                                <?php if (!empty($fuente['region_nombre'])): ?>
                                    · 📍 <?php echo htmlspecialchars((string) $fuente['region_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="limite">
                                👤 <?php echo htmlspecialchars(
                                    (string) $fuente['propietario_nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                                (<?php echo htmlspecialchars(
                                    (string) $fuente['propietario_rol'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>)
                            </div>
                        </div>

                        <div class="fuente-acciones">
                            <a
                                href="?editar=<?php echo $idFuente; ?>"
                                class="btn btn-warning btn-sm"
                                title="Editar"
                            >✏️</a>

                            <form method="POST" style="display:inline;">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <input type="hidden" name="id_fuente" value="<?php echo $idFuente; ?>">
                                <input
                                    type="hidden"
                                    name="accion"
                                    value="<?php echo $activa ? 'desactivar' : 'activar'; ?>"
                                >
                                <button type="submit" class="btn btn-sm">
                                    <?php echo $activa ? '⏸️' : '▶️'; ?>
                                </button>
                            </form>

                            <form
                                method="POST"
                                style="display:inline;"
                                onsubmit="return confirm('¿Eliminar esta fuente? Sus <?php echo $totalNoticias; ?> noticias asociadas se conservarán.')"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <input type="hidden" name="id_fuente" value="<?php echo $idFuente; ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="rss-config-card">
        <h2>ℹ️ Funcionamiento</h2>
        <ul style="margin-left: 1.5rem; line-height: 1.6;">
            <li>Solo las fuentes activas estarán disponibles para los periodistas.</li>
            <li>El periodista elegirá las noticias y su categoría al importarlas.</li>
            <li>Una noticia RSS ya seleccionada no podrá volver a importarse.</li>
            <li>Al eliminar una fuente se conservarán las noticias asociadas.</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
