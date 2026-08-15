<?php
declare(strict_types=1);

/**
 * GESTIÓN DE FUENTES RSS DEL PERIODISTA
 *
 * En esta fase se administran fuentes propias. La selección e importación
 * manual de noticias se incorporará de forma separada.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/rss.php';
require_once __DIR__ . '/../includes/logs.php';

Permisos::requerirPeriodista();

$pdo = db();
$idPropietario = (int) ($_SESSION['usuario_id'] ?? 0);
$errores = [];
$mensaje = '';
$fuenteEditar = null;
$importacionCompletada = false;
$modoSelector = ($_GET['seleccionar'] ?? '') === 'noticia';

if ($idPropietario <= 0) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $errores[] = 'Error de seguridad. Recarga la página e inténtalo de nuevo.';
    } else {
        $accion = (string) ($_POST['accion'] ?? '');
        $idFuente = (int) ($_POST['id_fuente'] ?? 0);

        try {
            if ($accion === 'importar_noticias') {
                $idCategoria = (int) ($_POST['categoria_destino'] ?? 0);
                $tipoUbicacion = (string) ($_POST['tipo_ubicacion'] ?? '');
                $idProvincia = (int) ($_POST['id_provincia'] ?? 0);
                $lugarInternacional = limpiarDatos($_POST['lugar_internacional'] ?? '');
                $otraUbicacion = limpiarDatos($_POST['otras_ubicacion'] ?? '');
                $seleccionadas = array_values(array_unique(array_filter(
                    (array) ($_POST['noticias'] ?? []),
                    static fn ($hash): bool => is_string($hash)
                        && preg_match('/^[a-f0-9]{64}$/', $hash) === 1
                )));

                if ($idFuente <= 0) {
                    $errores[] = 'Debes seleccionar una fuente RSS activa';
                }
                if ($idCategoria <= 0) {
                    $errores[] = 'Debes seleccionar una categoría de destino';
                }
                if (!in_array($tipoUbicacion, ['espana', 'internacional', 'otras'], true)) {
                    $errores[] = 'Debes seleccionar una ubicación';
                } elseif ($tipoUbicacion === 'espana' && $idProvincia <= 0) {
                    $errores[] = 'Debes seleccionar una provincia';
                } elseif ($tipoUbicacion === 'internacional' && $lugarInternacional === '') {
                    $errores[] = 'Debes indicar el lugar internacional';
                } elseif ($tipoUbicacion === 'otras' && $otraUbicacion === '') {
                    $errores[] = 'Debes indicar el nombre del lugar';
                }
                if ($seleccionadas === []) {
                    $errores[] = 'Debes seleccionar al menos una noticia';
                } elseif (count($seleccionadas) > 20) {
                    $errores[] = 'Puedes importar un máximo de 20 noticias cada vez';
                }

                $fuenteImportar = null;
                if ($errores === []) {
                    $stmt = $pdo->prepare(
                        'SELECT id_fuente, nombre, url FROM fuentes_rss '
                        . 'WHERE id_fuente = ? AND activa = 1'
                    );
                    $stmt->execute([$idFuente]);
                    $fuenteImportar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                    $stmt = $pdo->prepare(
                        'SELECT COUNT(*) FROM categorias '
                        . 'WHERE id_categoria = ? AND activa = 1'
                    );
                    $stmt->execute([$idCategoria]);

                    if ($fuenteImportar === null) {
                        $errores[] = 'La fuente RSS ya no está disponible';
                    }
                    if ((int) $stmt->fetchColumn() === 0) {
                        $errores[] = 'La categoría seleccionada no está disponible';
                    }

                    if ($tipoUbicacion === 'espana') {
                        $stmt = $pdo->prepare(
                            'SELECT COUNT(*) FROM provincias WHERE id_provincia = ?'
                        );
                        $stmt->execute([$idProvincia]);
                        if ((int) $stmt->fetchColumn() === 0) {
                            $errores[] = 'La provincia seleccionada no está disponible';
                        }
                    }
                }

                if ($errores === [] && $fuenteImportar !== null) {
                    $feed = obtenerFeed((string) $fuenteImportar['url'], 5);
                    $itemsFeed = obtenerItemsSeleccionablesRss($feed, 50);
                    $itemsImportar = [];

                    foreach ($seleccionadas as $hash) {
                        if (isset($itemsFeed[$hash])) {
                            $itemsImportar[$hash] = $itemsFeed[$hash];
                        }
                    }

                    if (count($itemsImportar) !== count($seleccionadas)) {
                        $errores[] = 'Una o más noticias ya no están disponibles en el RSS';
                    } else {
                        $importadas = 0;
                        $duplicadas = 0;
                        $pdo->beginTransaction();

                        foreach ($itemsImportar as $hash => $item) {
                            $stmt = $pdo->prepare(
                                'SELECT id_noticia FROM noticias '
                                . 'WHERE rss_item_hash = ? LIMIT 1'
                            );
                            $stmt->execute([$hash]);
                            if ($stmt->fetchColumn()) {
                                $duplicadas++;
                                continue;
                            }

                            $slugBase = generarSlugSeguro((string) $item['titulo']);
                            $slug = $slugBase;
                            $contador = 1;
                            while (true) {
                                $stmt = $pdo->prepare(
                                    'SELECT id_noticia FROM noticias WHERE slug = ? LIMIT 1'
                                );
                                $stmt->execute([$slug]);
                                if (!$stmt->fetchColumn()) {
                                    break;
                                }
                                $slug = $slugBase . '-' . $contador;
                                $contador++;
                            }

                            $stmt = $pdo->prepare("
                                INSERT INTO noticias (
                                    titulo, slug, contenido, fuente,
                                    id_fuente_rss, rss_item_hash, imagen_externa,
                                    id_autor, id_categoria, privada,
                                    permitir_comentarios, tipo_ubicacion, id_provincia,
                                    lugar_internacional, otras_ubicacion,
                                    estado, fecha_publicacion
                                ) VALUES (
                                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1,
                                    ?, ?, ?, ?, 'publicada', ?
                                )
                            ");

                            try {
                                $stmt->execute([
                                    $item['titulo'],
                                    $slug,
                                    $item['contenido'],
                                    $item['enlace'],
                                    $idFuente,
                                    $hash,
                                    $item['imagen'],
                                    $idPropietario,
                                    $idCategoria,
                                    $tipoUbicacion,
                                    $tipoUbicacion === 'espana' ? $idProvincia : null,
                                    $tipoUbicacion === 'internacional' ? $lugarInternacional : null,
                                    $tipoUbicacion === 'otras' ? $otraUbicacion : null,
                                    $item['fecha'],
                                ]);
                                $importadas++;
                            } catch (PDOException $e) {
                                if ((string) $e->getCode() === '23000') {
                                    $duplicadas++;
                                    continue;
                                }
                                throw $e;
                            }
                        }

                        $pdo->commit();

                        if ($importadas > 0) {
                            $importacionCompletada = true;
                            $mensaje = "✅ Se importaron {$importadas} noticias correctamente";
                            if ($duplicadas > 0) {
                                $mensaje .= ". {$duplicadas} ya estaban seleccionadas";
                            }
                            registrarLog(
                                'periodista_rss_importar',
                                null,
                                null,
                                "Fuente RSS ID {$idFuente}; importadas: {$importadas}; duplicadas: {$duplicadas}"
                            );
                        } else {
                            $mensaje = 'ℹ️ Las noticias seleccionadas ya habían sido importadas';
                        }
                    }
                }
            } elseif ($accion === 'crear' || $accion === 'editar') {
                $validacion = validarConfiguracionFuenteRss(
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['url'] ?? '')
                );
                $errores = array_merge($errores, $validacion['errores']);
                $datos = $validacion['datos'];

                if ($accion === 'editar' && $idFuente <= 0) {
                    $errores[] = 'La fuente indicada no es válida';
                }

                if ($datos !== null && $errores === []) {
                    $sqlDuplicado = 'SELECT id_fuente FROM fuentes_rss WHERE LOWER(url) = LOWER(?)';
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
                        $stmt = $pdo->prepare(
                            'INSERT INTO fuentes_rss '
                            . '(id_propietario, nombre, url, activa) '
                            . 'VALUES (?, ?, ?, 1)'
                        );
                        $stmt->execute([
                            $idPropietario,
                            $datos['nombre'],
                            $datos['url'],
                        ]);
                        $idCreado = (int) $pdo->lastInsertId();
                        $mensaje = '✅ Fuente RSS añadida y activada correctamente';
                        registrarLog(
                            'periodista_rss_agregar',
                            null,
                            null,
                            "Fuente RSS creada: ID {$idCreado}"
                        );
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE fuentes_rss '
                            . 'SET nombre = ?, url = ? '
                            . 'WHERE id_fuente = ? AND id_propietario = ?'
                        );
                        $stmt->execute([
                            $datos['nombre'],
                            $datos['url'],
                            $idFuente,
                            $idPropietario,
                        ]);

                        $comprobar = $pdo->prepare(
                            'SELECT COUNT(*) FROM fuentes_rss '
                            . 'WHERE id_fuente = ? AND id_propietario = ?'
                        );
                        $comprobar->execute([$idFuente, $idPropietario]);

                        if ((int) $comprobar->fetchColumn() === 0) {
                            $errores[] = 'No puedes editar esta fuente RSS';
                        } else {
                            $mensaje = '✅ Fuente RSS actualizada correctamente';
                            registrarLog(
                                'periodista_rss_editar',
                                null,
                                null,
                                "Fuente RSS editada: ID {$idFuente}"
                            );
                        }
                    }
                }
            } elseif ($accion === 'activar' || $accion === 'desactivar') {
                if ($idFuente <= 0) {
                    $errores[] = 'La fuente indicada no es válida';
                } else {
                    $nuevoEstado = $accion === 'activar' ? 1 : 0;
                    $stmt = $pdo->prepare(
                        'UPDATE fuentes_rss SET activa = ? '
                        . 'WHERE id_fuente = ? AND id_propietario = ?'
                    );
                    $stmt->execute([$nuevoEstado, $idFuente, $idPropietario]);

                    $comprobar = $pdo->prepare(
                        'SELECT COUNT(*) FROM fuentes_rss '
                        . 'WHERE id_fuente = ? AND id_propietario = ?'
                    );
                    $comprobar->execute([$idFuente, $idPropietario]);

                    if ((int) $comprobar->fetchColumn() === 0) {
                        $errores[] = 'No puedes modificar esta fuente RSS';
                    } else {
                        $mensaje = $nuevoEstado === 1
                            ? '✅ Fuente RSS activada'
                            : '✅ Fuente RSS desactivada';
                        registrarLog(
                            $nuevoEstado === 1
                                ? 'periodista_rss_activar'
                                : 'periodista_rss_desactivar',
                            null,
                            null,
                            "Estado de fuente RSS actualizado: ID {$idFuente}"
                        );
                    }
                }
            } elseif ($accion === 'eliminar') {
                if ($idFuente <= 0) {
                    $errores[] = 'La fuente indicada no es válida';
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'SELECT id_fuente FROM fuentes_rss '
                        . 'WHERE id_fuente = ? AND id_propietario = ? FOR UPDATE'
                    );
                    $stmt->execute([$idFuente, $idPropietario]);

                    if (!$stmt->fetchColumn()) {
                        $pdo->rollBack();
                        $errores[] = 'No puedes eliminar esta fuente RSS';
                    } else {
                        $stmt = $pdo->prepare(
                            'DELETE FROM fuentes_rss '
                            . 'WHERE id_fuente = ? AND id_propietario = ?'
                        );
                        $stmt->execute([$idFuente, $idPropietario]);
                        $pdo->commit();
                        $mensaje = '✅ Fuente RSS eliminada. Sus noticias publicadas se conservan.';
                        registrarLog(
                            'periodista_rss_eliminar',
                            null,
                            null,
                            "Fuente RSS eliminada: ID {$idFuente}"
                        );
                    }
                }
            } else {
                $errores[] = 'Acción no válida';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ((string) $e->getCode() === '23000') {
                $errores[] = 'Esta fuente RSS ya está configurada';
            } else {
                registrarErrorInterno('PERIODISTA.RSS.GESTION_PDO', $e);
                $errores[] = 'No se pudo completar la operación';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            registrarErrorInterno('PERIODISTA.RSS.GESTION', $e);
            $errores[] = 'No se pudo completar la operación';
        }
    }
}

if (isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    if ($idEditar > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM fuentes_rss '
            . 'WHERE id_fuente = ? AND id_propietario = ?'
        );
        $stmt->execute([$idEditar, $idPropietario]);
        $fuenteEditar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($fuenteEditar === null) {
            $errores[] = 'No puedes editar esta fuente RSS';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT fr.*, COUNT(n.id_noticia) AS total_noticias
    FROM fuentes_rss fr
    LEFT JOIN noticias n ON n.id_fuente_rss = fr.id_fuente
    WHERE fr.id_propietario = ?
    GROUP BY fr.id_fuente
    ORDER BY fr.activa DESC, fr.nombre
");
$stmt->execute([$idPropietario]);
$fuentesPropias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT fr.id_fuente, fr.nombre, fr.url, u.nombre AS propietario_nombre
    FROM fuentes_rss fr
    INNER JOIN usuarios u ON u.id_usuario = fr.id_propietario
    WHERE fr.activa = 1 AND fr.id_propietario != ?
    ORDER BY fr.nombre
");
$stmt->execute([$idPropietario]);
$fuentesDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fuentesSeleccionables = $pdo->query("
    SELECT fr.id_fuente, fr.nombre, u.nombre AS propietario_nombre
    FROM fuentes_rss fr
    INNER JOIN usuarios u ON u.id_usuario = fr.id_propietario
    WHERE fr.activa = 1
    ORDER BY fr.nombre
")->fetchAll(PDO::FETCH_ASSOC);

$categorias = $pdo->query("
    SELECT id_categoria, nombre_categoria
    FROM categorias
    WHERE activa = 1
    ORDER BY nombre_categoria
")->fetchAll(PDO::FETCH_ASSOC);

$provincias = $pdo->query("
    SELECT id_provincia, nombre
    FROM provincias
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$idFuenteVista = (int) ($_GET['fuente'] ?? $_POST['id_fuente'] ?? 0);
$fuenteVista = null;
$itemsVista = [];
$hashesImportados = [];

if ($idFuenteVista > 0) {
    $stmt = $pdo->prepare(
        'SELECT id_fuente, nombre, url FROM fuentes_rss '
        . 'WHERE id_fuente = ? AND activa = 1'
    );
    $stmt->execute([$idFuenteVista]);
    $fuenteVista = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($fuenteVista === null) {
        $errores[] = 'La fuente seleccionada no está disponible';
    } else {
        $feedVista = obtenerFeed((string) $fuenteVista['url'], 5);
        $itemsVista = obtenerItemsSeleccionablesRss($feedVista, 50);

        if ($itemsVista === []) {
            $errores[] = 'No se encontraron noticias multimedia disponibles en esta fuente';
        } else {
            $hashes = array_keys($itemsVista);
            $marcas = implode(',', array_fill(0, count($hashes), '?'));
            $stmt = $pdo->prepare(
                "SELECT rss_item_hash FROM noticias WHERE rss_item_hash IN ({$marcas})"
            );
            $stmt->execute($hashes);
            $hashesImportados = array_fill_keys(
                $stmt->fetchAll(PDO::FETCH_COLUMN),
                true
            );
        }
    }
}

$titulo_pagina = 'Fuentes RSS';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('periodista-importar-rss.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('news-cards.css'); ?>">

<?php if ($modoSelector): ?>
<script defer src="<?php echo htmlspecialchars(js_url('rss-selector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<div class="importar-rss-container" data-rss-selector>
    <h1>📡 Seleccionar noticia RSS</h1>
    <p class="descripcion">Selecciona una fuente y después una noticia. Podrás revisar todos sus datos antes de guardar.</p>
    <?php if ($errores !== []): ?>
        <div class="alert alert-error"><?php foreach ($errores as $error): ?><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="fuentes-panel" id="noticias-rss">
        <form method="GET" action="<?php echo route('importar_rss'); ?>#noticias-rss">
            <input type="hidden" name="seleccionar" value="noticia">
            <div class="campo">
                <label for="fuente_selector">📡 Fuente RSS:</label>
                <select name="fuente" id="fuente_selector" required>
                    <option value="">-- Seleccionar fuente --</option>
                    <?php foreach ($fuentesSeleccionables as $fuente): ?>
                        <option value="<?php echo (int) $fuente['id_fuente']; ?>" <?php echo $idFuenteVista === (int) $fuente['id_fuente'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $fuente['nombre'] . ' — ' . (string) $fuente['propietario_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-importar">🔍 Cargar noticias</button>
        </form>
    </div>
    <?php if ($fuenteVista !== null && $itemsVista !== []): ?>
        <div class="fuentes-panel">
            <h2>Noticias disponibles de <?php echo htmlspecialchars((string) $fuenteVista['nombre'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="fuentes-lista">
                <?php foreach ($itemsVista as $hash => $item): ?>
                    <?php $yaImportada = isset($hashesImportados[$hash]); ?>
                    <article class="fuente-item news-card news-card--vertical news-card--external">
                        <?php if ($item['imagen'] !== null): ?><img src="<?php echo htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" style="width:100%;max-height:220px;object-fit:cover"><?php endif; ?>
                        <h2 class="news-card__title"><?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p><?php echo htmlspecialchars($item['extracto'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ($yaImportada): ?>
                            <div class="alert alert-error">Noticia ya seleccionada</div>
                        <?php else: ?>
                            <button type="button" class="btn-importar" data-rss-seleccionar
                                data-id-fuente="<?php echo (int) $fuenteVista['id_fuente']; ?>"
                                data-hash="<?php echo htmlspecialchars($hash, ENT_QUOTES, 'UTF-8'); ?>"
                                data-titulo="<?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-contenido="<?php echo htmlspecialchars($item['contenido'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-enlace="<?php echo htmlspecialchars($item['enlace'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-imagen="<?php echo htmlspecialchars((string) ($item['imagen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-fuente-nombre="<?php echo htmlspecialchars((string) $fuenteVista['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                            >📥 Usar esta noticia</button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div id="rssSelectorEstado" class="alert" hidden role="status"></div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; exit; ?>
<?php endif; ?>

<div class="importar-rss-container">
    <h1>📡 Fuentes RSS</h1>
    <p class="descripcion">
        Administra tus fuentes RSS y consulta las fuentes activas compartidas.
    </p>

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

    <div class="importar-grid">
        <div class="fuentes-panel">
            <h2><?php echo $fuenteEditar ? '✏️ Editar mi fuente' : '➕ Añadir mi fuente'; ?></h2>

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>"
                >
                <input
                    type="hidden"
                    name="accion"
                    value="<?php echo $fuenteEditar ? 'editar' : 'crear'; ?>"
                >

                <?php if ($fuenteEditar): ?>
                    <input
                        type="hidden"
                        name="id_fuente"
                        value="<?php echo (int) $fuenteEditar['id_fuente']; ?>"
                    >
                <?php endif; ?>

                <div class="campo">
                    <label for="nombre">📝 Nombre:</label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        maxlength="100"
                        required
                        value="<?php echo htmlspecialchars((string) ($fuenteEditar['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="campo">
                    <label for="url">🔗 URL RSS:</label>
                    <input
                        type="url"
                        id="url"
                        name="url"
                        maxlength="500"
                        required
                        value="<?php echo htmlspecialchars((string) ($fuenteEditar['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                    <small>Debe contener noticias con imágenes o vídeos.</small>
                </div>

                <button type="submit" class="btn-importar">
                    <?php echo $fuenteEditar ? '💾 Guardar cambios' : '➕ Añadir fuente'; ?>
                </button>

                <?php if ($fuenteEditar): ?>
                    <a href="<?php echo route('importar_rss'); ?>" class="btn">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="fuentes-panel">
            <h2>📋 Mis fuentes</h2>

            <?php if ($fuentesPropias === []): ?>
                <p class="text-muted">Todavía no has añadido fuentes RSS.</p>
            <?php else: ?>
                <div class="fuentes-lista">
                    <?php foreach ($fuentesPropias as $fuente): ?>
                        <?php
                        $idFuente = (int) $fuente['id_fuente'];
                        $activa = (int) $fuente['activa'] === 1;
                        ?>
                        <div class="fuente-item">
                            <strong>
                                <?php echo htmlspecialchars((string) $fuente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                            <small>
                                <?php echo htmlspecialchars((string) $fuente['url'], ENT_QUOTES, 'UTF-8'); ?>
                            </small>
                            <small style="display:block;">
                                <?php echo $activa ? '✅ Activa' : '⏸️ Inactiva'; ?>
                                · 📰 <?php echo (int) $fuente['total_noticias']; ?> noticias
                            </small>

                            <div style="margin-top:0.5rem;">
                                <a href="?editar=<?php echo $idFuente; ?>" class="btn btn-warning btn-sm">✏️</a>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_fuente" value="<?php echo $idFuente; ?>">
                                    <input type="hidden" name="accion" value="<?php echo $activa ? 'desactivar' : 'activar'; ?>">
                                    <button type="submit" class="btn btn-sm">
                                        <?php echo $activa ? '⏸️' : '▶️'; ?>
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirm('¿Eliminar esta fuente? Las noticias ya publicadas se conservarán.')"
                                >
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_fuente" value="<?php echo $idFuente; ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="fuentes-panel">
        <h2>🌐 Otras fuentes activas disponibles</h2>

        <?php if ($fuentesDisponibles === []): ?>
            <p class="text-muted">No hay otras fuentes activas disponibles.</p>
        <?php else: ?>
            <div class="fuentes-lista">
                <?php foreach ($fuentesDisponibles as $fuente): ?>
                    <div class="fuente-item">
                        <strong>
                            <?php echo htmlspecialchars((string) $fuente['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                        <small>
                            <?php echo htmlspecialchars((string) $fuente['url'], ENT_QUOTES, 'UTF-8'); ?>
                        </small>
                        <small style="display:block;">
                            👤 <?php echo htmlspecialchars(
                                (string) $fuente['propietario_nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="fuentes-panel" id="noticias-rss">
        <h2>📰 Seleccionar noticias</h2>

        <ol style="line-height:1.7; margin:0 0 1rem 1.5rem;">
            <li>Selecciona una fuente RSS activa y carga sus noticias.</li>
            <li>Marca una o varias noticias disponibles.</li>
            <li>Elige la categoría y la ubicación común en la que se publicarán.</li>
            <li>Pulsa <strong>Importar noticias seleccionadas</strong>.</li>
        </ol>

        <p class="text-muted" style="padding:0.5rem;">
            Las noticias con el aviso <strong>Noticia ya seleccionada</strong>
            ya pertenecen al portal y no pueden importarse otra vez.
        </p>

        <?php if ($importacionCompletada): ?>
            <div class="alert alert-success">
                <strong><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></strong>
                <div style="margin-top:0.75rem; display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <a href="<?php echo route('mis_noticias'); ?>" class="btn">
                        📰 Ver en Mis Noticias
                    </a>
                    <a href="<?php echo route('listado_noticias'); ?>" class="btn">
                        🌐 Ver listado público
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($fuentesSeleccionables === []): ?>
            <p class="text-muted">No hay fuentes RSS activas disponibles.</p>
        <?php else: ?>
            <form
                method="GET"
                action="<?php echo route('importar_rss'); ?>#noticias-rss"
            >
                <div class="campo">
                    <label for="fuente">📡 Fuente RSS:</label>
                    <select name="fuente" id="fuente" required>
                        <option value="">-- Seleccionar fuente --</option>
                        <?php foreach ($fuentesSeleccionables as $fuente): ?>
                            <option
                                value="<?php echo (int) $fuente['id_fuente']; ?>"
                                <?php echo $idFuenteVista === (int) $fuente['id_fuente'] ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars(
                                    (string) $fuente['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                                — <?php echo htmlspecialchars(
                                    (string) $fuente['propietario_nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-importar">🔍 Cargar noticias</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($fuenteVista !== null && $itemsVista !== []): ?>
        <div class="fuentes-panel">
            <h2>
                📋 Noticias disponibles de
                <?php echo htmlspecialchars((string) $fuenteVista['nombre'], ENT_QUOTES, 'UTF-8'); ?>
            </h2>

            <form
                method="POST"
                action="<?php echo route('importar_rss'); ?>#noticias-rss"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>"
                >
                <input type="hidden" name="accion" value="importar_noticias">
                <input type="hidden" name="id_fuente" value="<?php echo (int) $fuenteVista['id_fuente']; ?>">

                <div class="campo">
                    <label for="categoria_destino">📂 Categoría de destino:</label>
                    <select name="categoria_destino" id="categoria_destino" required>
                        <option value="">-- Seleccionar categoría --</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option
                                value="<?php echo (int) $categoria['id_categoria']; ?>"
                                <?php echo (int) ($_POST['categoria_destino'] ?? 0) === (int) $categoria['id_categoria'] ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars(
                                    (string) $categoria['nombre_categoria'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php $tipoUbicacionFormulario = (string) ($_POST['tipo_ubicacion'] ?? ''); ?>
                <fieldset class="campo rss-ubicacion-campo">
                    <legend>📍 Ubicación común del lote:</legend>
                    <div class="rss-ubicacion-opciones">
                        <label>
                            <input type="radio" name="tipo_ubicacion" value="espana" required <?php echo $tipoUbicacionFormulario === 'espana' ? 'checked' : ''; ?>>
                            <span>🇪🇸 España</span>
                        </label>
                        <label>
                            <input type="radio" name="tipo_ubicacion" value="internacional" required <?php echo $tipoUbicacionFormulario === 'internacional' ? 'checked' : ''; ?>>
                            <span>🌍 Internacional</span>
                        </label>
                        <label>
                            <input type="radio" name="tipo_ubicacion" value="otras" required <?php echo $tipoUbicacionFormulario === 'otras' ? 'checked' : ''; ?>>
                            <span>🗺️ Otra ubicación</span>
                        </label>
                    </div>

                    <div data-ubicacion-panel="espana" <?php echo $tipoUbicacionFormulario === 'espana' ? '' : 'hidden'; ?>>
                        <label for="rss_id_provincia">🏞️ Provincia:</label>
                        <select name="id_provincia" id="rss_id_provincia" <?php echo $tipoUbicacionFormulario === 'espana' ? 'required' : ''; ?>>
                            <option value="">-- Seleccionar provincia --</option>
                            <?php foreach ($provincias as $provincia): ?>
                                <option
                                    value="<?php echo (int) $provincia['id_provincia']; ?>"
                                    <?php echo (int) ($_POST['id_provincia'] ?? 0) === (int) $provincia['id_provincia'] ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars((string) $provincia['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div data-ubicacion-panel="internacional" <?php echo $tipoUbicacionFormulario === 'internacional' ? '' : 'hidden'; ?>>
                        <label for="rss_lugar_internacional">🌍 País o lugar:</label>
                        <input type="text" name="lugar_internacional" id="rss_lugar_internacional" maxlength="150" value="<?php echo htmlspecialchars((string) ($_POST['lugar_internacional'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $tipoUbicacionFormulario === 'internacional' ? 'required' : ''; ?>>
                    </div>

                    <div data-ubicacion-panel="otras" <?php echo $tipoUbicacionFormulario === 'otras' ? '' : 'hidden'; ?>>
                        <label for="rss_otras_ubicacion">🗺️ Nombre del lugar:</label>
                        <input type="text" name="otras_ubicacion" id="rss_otras_ubicacion" maxlength="150" value="<?php echo htmlspecialchars((string) ($_POST['otras_ubicacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $tipoUbicacionFormulario === 'otras' ? 'required' : ''; ?>>
                    </div>
                    <small>Se aplicará a todas las noticias seleccionadas. Después podrás modificar cada una desde Editar noticia.</small>
                </fieldset>

                <div class="fuentes-lista rss-noticias-grid">
                    <?php foreach ($itemsVista as $hash => $item): ?>
                        <?php $yaImportada = isset($hashesImportados[$hash]); ?>
                        <?php $idSelector = 'rss-noticia-' . $hash; ?>
                        <article class="fuente-item rss-noticia-card news-card news-card--vertical news-card--external<?php echo $yaImportada ? ' rss-noticia-card--importada' : ''; ?>">
                            <input
                                class="rss-noticia-checkbox"
                                id="<?php echo htmlspecialchars($idSelector, ENT_QUOTES, 'UTF-8'); ?>"
                                type="checkbox"
                                name="noticias[]"
                                value="<?php echo htmlspecialchars($hash, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $yaImportada ? 'disabled' : ''; ?>
                            >
                            <label class="rss-noticia-titulo" for="<?php echo htmlspecialchars($idSelector, ENT_QUOTES, 'UTF-8'); ?>">
                                <strong class="news-card__title">
                                    <?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>
                                </strong>
                            </label>

                            <div class="rss-noticia-media news-card__media">
                                <?php if ($item['imagen'] !== null): ?>
                                    <img
                                        src="<?php echo htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt=""
                                        loading="lazy"
                                    >
                                <?php elseif ($item['video'] !== null): ?>
                                    <video
                                        controls
                                        preload="metadata"
                                    >
                                        <source src="<?php echo htmlspecialchars($item['video'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </video>
                                <?php endif; ?>
                            </div>

                            <p class="rss-noticia-extracto news-card__body">
                                <?php echo htmlspecialchars($item['extracto'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                            <div class="rss-noticia-meta news-card__meta news-card__meta--standard">
                                <span>📅 <?php echo htmlspecialchars($item['fecha'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span><?php echo $item['video'] !== null ? '🎥 Vídeo' : '🖼️ Imagen'; ?></span>
                                <span>📡 <a href="<?php echo htmlspecialchars(route('buscar', ['fuente' => 'rss:' . (int) $fuenteVista['id_fuente']]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $fuenteVista['nombre'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                                <span><?php echo $yaImportada ? '⛔ Ya importada' : '✅ Disponible'; ?></span>
                            </div>

                            <div class="rss-noticia-acciones news-card__actions">
                                <a
                                    href="<?php echo htmlspecialchars($item['enlace'], ENT_QUOTES, 'UTF-8'); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                >Ver original ↗</a>
                            </div>

                            <?php if ($yaImportada): ?>
                                <div class="alert alert-error rss-noticia-aviso">
                                    Noticia ya seleccionada
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-importar">
                    📥 Importar noticias seleccionadas
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('input[name="tipo_ubicacion"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('[data-ubicacion-panel]').forEach(function (panel) {
            const activo = panel.dataset.ubicacionPanel === radio.value;
            panel.hidden = !activo;
            panel.querySelectorAll('select, input').forEach(function (campo) {
                campo.required = activo;
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
