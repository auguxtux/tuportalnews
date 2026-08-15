<?php

declare(strict_types=1);

/**
 * PANEL PRIVADO
 *
 * Dashboard para usuarios con acceso a noticias privadas.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/minify.php';

/*
|--------------------------------------------------------------------------
| Control de acceso
|--------------------------------------------------------------------------
*/

Permisos::requerirLogin();
if (!usuarioEsPrivado() && !Permisos::esAdmin()) {
    mensajeFlash('error', 'No tienes permiso para acceder al área privada');
    redireccionar(route('home'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Datos básicos
|--------------------------------------------------------------------------
*/

$pdo = db();

$id_usuario = isset($_SESSION['usuario_id'])
    ? (int) $_SESSION['usuario_id']
    : 0;

$nombre_usuario = isset($_SESSION['usuario_nombre'])
    ? (string) $_SESSION['usuario_nombre']
    : 'Usuario';

$es_admin = Permisos::esAdmin();
$correo_corporativo = '';

$error = null;
$stats_generales = [
    'total_privadas'   => 0,
    'total_autores'    => 0,
    'total_categorias' => 0,
    'total_visitas'    => 0,
    'total_likes'      => 0,
];

$stats_personales = [
    'mis_privadas' => 0,
    'mis_visitas'  => 0,
    'mis_likes'    => 0,
];

$ultimas = [];

/*
|--------------------------------------------------------------------------
| Estadísticas y noticias
|--------------------------------------------------------------------------
*/

try {
    if ($es_admin) {
        /*
         * Estadísticas generales para administradores.
         */
        $consultaStats = $pdo->query(
            "
            SELECT
                COUNT(DISTINCT n.id_noticia) AS total_privadas,
                COUNT(DISTINCT n.id_autor) AS total_autores,
                COUNT(DISTINCT n.id_categoria) AS total_categorias,
                COALESCE(SUM(ep.visitas_privadas), 0) AS total_visitas,
                COALESCE(SUM(ep.megusta_privados), 0) AS total_likes
            FROM noticias n
            LEFT JOIN estadisticas_privadas ep
                ON ep.id_noticia = n.id_noticia
            WHERE n.privada = 1
            "
        );

        $resultadoStats = $consultaStats->fetch(PDO::FETCH_ASSOC);

        if (is_array($resultadoStats)) {
            $stats_generales = array_merge(
                $stats_generales,
                $resultadoStats
            );
        }

        /*
         * Últimas noticias privadas del sistema.
         */
        $consultaUltimas = $pdo->query(
            "
            SELECT
                n.*,
                u.nombre AS autor_nombre,
                c.nombre_categoria
            FROM noticias n
            INNER JOIN usuarios u
                ON u.id_usuario = n.id_autor
            INNER JOIN categorias c
                ON c.id_categoria = n.id_categoria
            WHERE n.privada = 1
            ORDER BY n.fecha_publicacion DESC
            LIMIT 5
            "
        );

        $ultimas = $consultaUltimas->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $consultaCorreo = $pdo->prepare(
            'SELECT correo_corporativo
             FROM usuarios_privados
             WHERE id_usuario = ? AND activo = 1'
        );
        $consultaCorreo->execute([$id_usuario]);
        $correo_corporativo = (string) ($consultaCorreo->fetchColumn() ?: '');

        /*
         * Estadísticas personales.
         */
        $consultaPersonales = $pdo->prepare(
            "
            SELECT
                COUNT(DISTINCT n.id_noticia) AS mis_privadas,
                COALESCE(SUM(ep.visitas_privadas), 0) AS mis_visitas,
                COALESCE(SUM(ep.megusta_privados), 0) AS mis_likes
            FROM noticias n
            LEFT JOIN estadisticas_privadas ep
                ON ep.id_noticia = n.id_noticia
            WHERE n.privada = 1
              AND n.id_autor = ?
            "
        );

        $consultaPersonales->execute([$id_usuario]);

        $resultadoPersonales = $consultaPersonales->fetch(PDO::FETCH_ASSOC);

        if (is_array($resultadoPersonales)) {
            $stats_personales = array_merge(
                $stats_personales,
                $resultadoPersonales
            );
        }

        /*
         * Número total de noticias privadas del sistema.
         */
        $consultaGenerales = $pdo->query(
            "
            SELECT COUNT(*) AS total_privadas
            FROM noticias
            WHERE privada = 1
            "
        );

        $resultadoGenerales = $consultaGenerales->fetch(PDO::FETCH_ASSOC);

        if (is_array($resultadoGenerales)) {
            $stats_generales['total_privadas'] =
                $resultadoGenerales['total_privadas'] ?? 0;
        }

        /*
         * Últimas noticias privadas del usuario.
         */
        $consultaUltimas = $pdo->prepare(
            "
            SELECT
                n.*,
                c.nombre_categoria
            FROM noticias n
            INNER JOIN categorias c
                ON c.id_categoria = n.id_categoria
            WHERE n.privada = 1
              AND n.id_autor = ?
            ORDER BY n.fecha_publicacion DESC
            LIMIT 5
            "
        );

        $consultaUltimas->execute([$id_usuario]);

        $ultimas = $consultaUltimas->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $error = 'No se han podido cargar los datos del panel privado.';
    registrarErrorInterno('PRIVADO.DASHBOARD.CARGA', $e);
}

/*
|--------------------------------------------------------------------------
| Cabecera
|--------------------------------------------------------------------------
*/

$titulo_pagina = 'Panel de Colaborador';

require_once __DIR__ . '/../partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        css_url('privado-panel.css'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('news-cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('dashboard-roles.css'), ENT_QUOTES, 'UTF-8'); ?>">

<main class="panel-privado-pagina">

    <header class="role-panel-hero role-panel-hero--privado">
        <h1 class="role-panel-hero__title">🔒 Panel privado de Colaborador</h1>
        <p class="role-panel-hero__description">Gestiona noticias privadas y consulta el contenido compartido únicamente con colaboradores autorizados.</p>
    </header>

    <nav class="role-panel-nav role-panel-hero--privado" aria-label="Funciones del colaborador">
        <a href="<?= htmlspecialchars(route('privado_nueva_noticia'), ENT_QUOTES, 'UTF-8'); ?>">➕ Nueva privada</a>
        <a href="<?= htmlspecialchars(route('privado_mis_noticias'), ENT_QUOTES, 'UTF-8'); ?>">📰 Mis privadas</a>
        <a href="<?= htmlspecialchars(route('privado_buscar'), ENT_QUOTES, 'UTF-8'); ?>">🔍 Buscar noticias</a>
        <a href="<?= htmlspecialchars(route('privado_buscar_comentarios'), ENT_QUOTES, 'UTF-8'); ?>">💬 Comentarios</a>
        <a href="<?= htmlspecialchars($es_admin ? route('admin') : route('periodista_dashboard'), ENT_QUOTES, 'UTF-8'); ?>"><?= $es_admin ? '👑 Panel Admin' : '🌐 Panel público'; ?></a>
    </nav>

    <?php require_once __DIR__ . '/../partials/instrucciones.php'; ?>

    <?php
    // Mostrar información de almacenamiento después del título.
    include __DIR__ . '/../partials/almacenamiento-info.php';
    ?>

    <div class="panel-privado-container">

        <?php if ($error !== null): ?>
            <div
                class="panel-privado-alerta panel-privado-alerta-error"
                role="alert"
            >
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Bienvenida -->
        <section class="panel-privado-bienvenida">
            <h2 class="panel-privado-bienvenida-titulo">
                Bienvenido,
                <?= htmlspecialchars(
                    $nombre_usuario,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </h2>

            <?php if ($es_admin): ?>
                <p
                    class="
                        panel-privado-rol-badge
                        panel-privado-rol-badge-admin
                    "
                >
                    👑 Admin
                </p>
            <?php else: ?>
                <p
                    class="
                        panel-privado-rol-badge
                        panel-privado-rol-badge-privado
                    "
                >
                    🔒 Colaborador
                </p>
            <?php endif; ?>
        </section>

        <?php if (!$es_admin && $correo_corporativo !== ''): ?>
            <section class="panel-privado-correo" aria-labelledby="panel-correo-titulo">
                <div>
                    <h2 id="panel-correo-titulo">📧 Correo corporativo</h2>
                    <p class="panel-privado-correo-direccion">
                        <?= htmlspecialchars($correo_corporativo, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p class="panel-privado-correo-ayuda">
                        La contraseña es la que te facilitó el Admin al crear tu cuenta corporativa.
                    </p>
                </div>
                <a
                    href="https://webmail.erun.es"
                    class="panel-privado-btn panel-privado-btn-correo"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    📬 Abrir webmail
                </a>
            </section>
        <?php endif; ?>

        <!-- Estadísticas -->
        <section
            class="panel-privado-stats-grid"
            aria-label="Estadísticas privadas"
        >
            <?php if ($es_admin): ?>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_generales['total_privadas'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Total noticias privadas
                    </span>
                </article>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_generales['total_autores'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Autores
                    </span>
                </article>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_generales['total_visitas'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Visitas privadas
                    </span>
                </article>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_generales['total_likes'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Likes privados
                    </span>
                </article>

            <?php else: ?>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_personales['mis_privadas'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Mis noticias privadas
                    </span>
                </article>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_personales['mis_visitas'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Visitas a mis privadas
                    </span>
                </article>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_personales['mis_likes'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Likes en mis privadas
                    </span>
                </article>

                <article class="panel-privado-stat-card">
                    <span class="panel-privado-stat-valor">
                        <?= number_format(
                            (int) $stats_generales['total_privadas'],
                            0,
                            ',',
                            '.'
                        ); ?>
                    </span>

                    <span class="panel-privado-stat-etiqueta">
                        Total en el sistema
                    </span>
                </article>

            <?php endif; ?>
        </section>

        <!-- Acciones rápidas -->
        <section class="panel-privado-acciones-rapidas">
            <h2 class="panel-privado-acciones-titulo">
                📋 Acciones rápidas
            </h2>

            <div class="panel-privado-botones-accion">

                <a
                    href="<?= htmlspecialchars(
                        $es_admin ? route('admin') : route('periodista_dashboard'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    class="panel-privado-btn panel-privado-btn-publico"
                >
                    <?= $es_admin ? '📊 Panel administrador' : '🌐 Panel público'; ?>
                </a>

                <a
                    href="<?= htmlspecialchars(
                        route('privado_nueva_noticia'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    class="
                        panel-privado-btn
                        panel-privado-btn-primario
                    "
                >
                    ➕ Nueva noticia privada
                </a>

                <a
                    href="<?= htmlspecialchars(route('privado_mis_noticias'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="
                        panel-privado-btn
                        panel-privado-btn-secundario
                    "
                >
                    📰 Mis noticias privadas
                </a>

                <a
                    href="<?= htmlspecialchars(route('privado_buscar'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="
                        panel-privado-btn
                        panel-privado-btn-secundario
                    "
                >
                    🔍 Buscar noticias
                </a>

                <a
                    href="<?= htmlspecialchars(route('privado_buscar_comentarios'), ENT_QUOTES, 'UTF-8'); ?>"
                    class="
                        panel-privado-btn
                        panel-privado-btn-secundario
                    "
                >
                    💬 Buscar comentarios
                </a>

                <?php if ($es_admin): ?>
                    <a
                        href="<?= htmlspecialchars(route('admin_usuarios_privados'), ENT_QUOTES, 'UTF-8'); ?>"
                        class="
                            panel-privado-btn
                            panel-privado-btn-admin
                        "
                    >
                        👥 Gestionar Colaboradores
                    </a>
                <?php endif; ?>

            </div>
        </section>

        <!-- Últimas noticias privadas -->
        <section class="panel-privado-ultimas-noticias">
            <h2 class="panel-privado-ultimas-titulo">
                📰 <?= $es_admin ? 'Últimas noticias privadas' : 'Mis últimas noticias privadas'; ?>
            </h2>

            <?php if (!empty($ultimas)): ?>

                <div class="panel-privado-lista-noticias">

                    <?php foreach ($ultimas as $noticia): ?>
                        <?php
                        $id_noticia = isset($noticia['id_noticia'])
                            ? (int) $noticia['id_noticia']
                            : 0;

                        $titulo = isset($noticia['titulo'])
                            ? (string) $noticia['titulo']
                            : 'Sin título';

                        $categoria = isset($noticia['nombre_categoria'])
                            ? (string) $noticia['nombre_categoria']
                            : 'Sin categoría';

                        $autor = isset($noticia['autor_nombre'])
                            ? (string) $noticia['autor_nombre']
                            : '';

                        $estado = isset($noticia['estado'])
                            ? (string) $noticia['estado']
                            : 'pendiente';

                        $estadoClase = preg_replace(
                            '/[^a-z0-9_-]/i',
                            '',
                            strtolower($estado)
                        );

                        $fechaPublicacion = isset(
                            $noticia['fecha_publicacion']
                        )
                            ? strtotime(
                                (string) $noticia['fecha_publicacion']
                            )
                            : false;

                        $fechaVisible = $fechaPublicacion !== false
                            ? date('d/m/Y', $fechaPublicacion)
                            : 'Sin fecha';
                        ?>

                        <?php
                        $claseEstadoTarjeta = match ($estado) {
                            'borrador' => ' news-card--draft',
                            'pendiente' => ' news-card--pending',
                            'archivada' => ' news-card--archived',
                            default => '',
                        };
                        ?>
                        <article class="panel-privado-noticia-item news-card news-card--compact news-card--private<?= $claseEstadoTarjeta; ?>">

                            <h3 class="panel-privado-noticia-titulo news-card__title">
                                <a
                                    href="<?= htmlspecialchars(route('privado_noticia', ['id' => $id_noticia]), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <?= htmlspecialchars(
                                        $titulo,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </a>
                            </h3>

                            <div class="panel-privado-noticia-meta news-card__meta news-card__meta--standard">

                                <?php if ($es_admin && $autor !== ''): ?>
                                    <span class="panel-privado-meta-autor">
                                        ✍️
                                        <a href="<?= htmlspecialchars(route('privado_buscar', ['usuario' => (int) ($noticia['id_autor'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($autor, ENT_QUOTES, 'UTF-8'); ?></a>
                                    </span>
                                <?php endif; ?>

                                <span class="panel-privado-meta-categoria">
                                    📁
                                    <a href="<?= htmlspecialchars(route('privado_buscar', ['categoria' => (int) ($noticia['id_categoria'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'); ?></a>
                                </span>

                                <span class="panel-privado-meta-fecha">
                                    📅
                                    <?= htmlspecialchars(
                                        $fechaVisible,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                                <span
                                    class="
                                        panel-privado-meta-estado
                                        panel-privado-meta-estado-<?=
                                            htmlspecialchars(
                                                $estadoClase,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                        ?>
                                    "
                                >
                                    <?= htmlspecialchars(
                                        ucfirst($estado),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </div>
                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="panel-privado-alerta">
                    No hay noticias privadas disponibles.
                </div>

            <?php endif; ?>
        </section>

        <?php if (!$es_admin): ?>
            <section class="panel-privado-zona-peligro" aria-labelledby="panel-privado-zona-peligro-titulo">
                <h2 id="panel-privado-zona-peligro-titulo">Gestión de la cuenta</h2>
                <p>Esta acción elimina la cuenta y afecta al contenido público y privado.</p>
                <a href="<?= htmlspecialchars(route('periodista_eliminar_cuenta'), ENT_QUOTES, 'UTF-8'); ?>" class="panel-privado-btn panel-privado-btn-danger">
                    🗑️ Eliminar mi cuenta
                </a>
            </section>
        <?php endif; ?>

        <!-- Limpieza para evitar que elementos flotantes afecten al footer -->
        <div
            class="panel-privado-clean"
            aria-hidden="true"
        ></div>

    </div>

</main>

<div
    class="panel-privado-clean"
    aria-hidden="true"
></div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
