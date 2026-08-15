<?php
declare(strict_types=1);


// Zona horaria oficial de Canarias

/**
 * PÁGINA DE NOTICIA INDIVIDUAL
 *
 * Muestra una noticia publicada con:
 * - Imagen principal y galería.
 * - Vídeo relacionado.
 * - Acciones, favoritos y comentarios.
 * - Compartición en redes sociales.
 * - Modal para reportar comentarios.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/privado.php';
require_once __DIR__ . '/../includes/helpers/reportes-confirmados.php';


/**
 * Muestra la página 404 y finaliza la ejecución.
 */
function mostrarPaginaNoEncontrada(): never
{
    http_response_code(404);

    $error404 = __DIR__ . '/404.php';

    if (is_file($error404)) {
        require $error404;
    } else {
        echo '<h1>404 - Página no encontrada</h1>';
    }

    exit;
}

/**
 * Registra una visita una sola vez por sesión para cada noticia.
 */
function registrarVisitaNoticia(PDO $pdo, int $idNoticia, bool $esPrivada = false): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (!isset($_SESSION['noticias_visitadas'])) {
        $_SESSION['noticias_visitadas'] = [];
    }

    if (!is_array($_SESSION['noticias_visitadas'])) {
        $_SESSION['noticias_visitadas'] = [];
    }

    if (isset($_SESSION['noticias_visitadas'][$idNoticia])) {
        return;
    }

    if ($esPrivada) {
        $stmt = $pdo->prepare(
            'INSERT INTO estadisticas_privadas
                (id_noticia, visitas_privadas, megusta_privados, ultima_actualizacion)
             VALUES (?, 1, 0, NOW())
             ON DUPLICATE KEY UPDATE
                visitas_privadas = visitas_privadas + 1,
                ultima_actualizacion = NOW()'
        );
        $stmt->execute([$idNoticia]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE noticias
             SET visitas = visitas + 1
             WHERE id_noticia = ?'
        );
        $stmt->execute([$idNoticia]);
    }

    $_SESSION['noticias_visitadas'][$idNoticia] = time();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$vistaPrivada = defined('VISTA_NOTICIA_PRIVADA') && VISTA_NOTICIA_PRIVADA === true;

if ($id <= 0) {
    mostrarPaginaNoEncontrada();
}

if ($vistaPrivada && !usuarioEsPrivado()) {
    mostrarPaginaNoEncontrada();
}

// Variables utilizadas por la vista.
$error = null;
$noticia = null;
$comentarios = [];
$relacionadas = [];
$imagenesGaleria = [];
$esFavorito = false;
$reportesConfirmadosNoticia = ['total' => 0, 'motivos' => []];
$csrfToken = generarTokenCSRF();

try {
    $pdo = db();

    /*
     * Obtener únicamente noticias publicadas.
     *
     * Se conserva n.* porque mostrarVideoNoticia() puede utilizar campos de
     * vídeo adicionales definidos en la tabla. Reducir las columnas sin conocer
     * el esquema completo podría ocultar el vídeo o provocar incompatibilidades.
     */
    $stmt = $pdo->prepare(
        "SELECT
            n.*,
            u.nombre AS autor_nombre,
            c.nombre_categoria
         FROM noticias n
         INNER JOIN usuarios u
             ON n.id_autor = u.id_usuario
         INNER JOIN categorias c
             ON n.id_categoria = c.id_categoria
         WHERE n.id_noticia = ?
           AND n.estado = 'publicada'
           AND n.privada = ?
         LIMIT 1"
    );
    $stmt->execute([$id, $vistaPrivada ? 1 : 0]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($noticia === false) {
        mostrarPaginaNoEncontrada();
    }

    // Incrementar una sola visita por sesión y noticia.
    registrarVisitaNoticia($pdo, $id, $vistaPrivada);

    // Comprobar si la noticia está guardada como favorita.
    if (estaLogueado()) {
        $stmt = $pdo->prepare(
            'SELECT id_favorito
             FROM favoritos
             WHERE id_usuario = ?
               AND id_noticia = ?
             LIMIT 1'
        );
        $stmt->execute([
            (int) $_SESSION['usuario_id'],
            $id,
        ]);

        $esFavorito = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    $titulo_pagina = (string) $noticia['titulo'];

    // Obtener comentarios aprobados.
    $stmt = $pdo->prepare(
        "SELECT
            c.*,
            u.nombre,
            u.avatar,
            u.rol
         FROM comentarios c
         INNER JOIN usuarios u
             ON c.id_usuario = u.id_usuario
         WHERE c.id_noticia = ?
           AND c.estado = 'aprobado'
         ORDER BY c.fecha_comentario DESC"
    );
    $stmt->execute([$id]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reportesConfirmadosNoticia = obtenerReportesConfirmadosNoticia(
        $pdo,
        $id,
        $vistaPrivada
    );

    // Obtener noticias relacionadas cuando exista el helper.
    if (function_exists('getNoticiasRelacionadas')) {
        $relacionadas = getNoticiasRelacionadas($id, 4, $vistaPrivada ? 1 : 0);

        if (!is_array($relacionadas)) {
            $relacionadas = [];
        }
    }

    // Decodificar una sola vez los textos asociados a las imágenes.
    $textosImagenes = json_decode(
        (string) ($noticia['textos_imagenes'] ?? '{}'),
        true
    );

    if (!is_array($textosImagenes)) {
        $textosImagenes = [];
    }

    // Imagen principal: prioriza el archivo local sobre la URL externa.
    $imagenPrincipalLocal = trim((string) ($noticia['imagen_principal'] ?? ''));
    if (
        $imagenPrincipalLocal !== ''
        && basename($imagenPrincipalLocal) === $imagenPrincipalLocal
        && is_file(UPLOAD_NOTICIAS . $imagenPrincipalLocal)
    ) {
        $imagenesGaleria[] = [
            'src' => base_url(
                'uploads/noticias/' . $imagenPrincipalLocal
            ),
            'texto' => (string) ($noticia['texto_imagen_principal'] ?? ''),
        ];
    } elseif (!empty($noticia['imagen_externa'])) {
        $imagenesGaleria[] = [
            'src' => (string) $noticia['imagen_externa'],
            'texto' => (string) ($noticia['texto_imagen_principal'] ?? ''),
        ];
    }

    // Imágenes adicionales, desde imagen_2 hasta imagen_6.
    for ($i = 2; $i <= 6; $i++) {
        $campo = 'imagen_' . $i;
        $imagen = trim((string) ($noticia[$campo] ?? ''));

        if ($imagen === '') {
            continue;
        }

        if (filter_var($imagen, FILTER_VALIDATE_URL) !== false) {
            $src = $imagen;
        } elseif (basename($imagen) === $imagen && is_file(UPLOAD_NOTICIAS . $imagen)) {
            $src = base_url('uploads/noticias/' . $imagen);
        } else {
            continue;
        }

        $imagenesGaleria[] = [
            'src' => $src,
            'texto' => (string) ($textosImagenes['img' . $i] ?? ''),
        ];
    }
} catch (Throwable $e) {
    registrarErrorInterno('PUBLIC.NOTICIA.CARGA', $e);

    $error = 'No se ha podido cargar la noticia. Inténtalo de nuevo más tarde.';
}

$totalImagenes = count($imagenesGaleria);
$totalComentarios = count($comentarios);
$totalRelacionadas = count($relacionadas);

$imagenesGaleriaJson = json_encode(
    $imagenesGaleria,
    JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
);

if ($imagenesGaleriaJson === false) {
    $imagenesGaleriaJson = '[]';
}

$urlReporte = route('procesar_reporte_comentario');
$urlFavorito = base_url('ajax/toggle-favorito.php');

if ($noticia !== null) {
    $tituloNoticia = trim((string) $noticia['titulo']);
    $textoDescripcion = trim((string) ($noticia['subtitulo'] ?? ''));

    if ($textoDescripcion === '') {
        $textoDescripcion = trim(strip_tags((string) $noticia['contenido']));
    }

    $textoDescripcion = preg_replace('/\s+/u', ' ', $textoDescripcion) ?? $textoDescripcion;
    $meta_descripcion = mb_substr($textoDescripcion, 0, 160, 'UTF-8');
    $url_canonica = route('noticia', ['id' => $id]);
    $meta_tipo = 'article';
    $meta_autor = (string) $noticia['autor_nombre'];
    $meta_seccion = (string) $noticia['nombre_categoria'];
    $meta_imagen = $imagenesGaleria[0]['src'] ?? base_url('assets/img/logo.png');

    $fechaPublicacion = strtotime((string) $noticia['fecha_publicacion']);
    $fechaModificacion = strtotime(
        (string) ($noticia['fecha_actualizacion'] ?: $noticia['fecha_publicacion'])
    );
    $meta_fecha_publicacion = $fechaPublicacion !== false
        ? date(DATE_ATOM, $fechaPublicacion)
        : null;
    $meta_fecha_modificacion = $fechaModificacion !== false
        ? date(DATE_ATOM, $fechaModificacion)
        : $meta_fecha_publicacion;

    $esquemaNoticia = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $tituloNoticia,
        'description' => $meta_descripcion,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $url_canonica,
        ],
        'image' => [$meta_imagen],
        'datePublished' => $meta_fecha_publicacion,
        'dateModified' => $meta_fecha_modificacion,
        'articleSection' => $meta_seccion,
        'author' => [
            '@type' => 'Person',
            'name' => $meta_autor,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => SITE_NAME,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => base_url('assets/img/logo.png'),
            ],
        ],
    ];

    $datos_estructurados = json_encode(
        $esquemaNoticia,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
}

require_once __DIR__ . '/../partials/header.php';
$videoHtml = mostrarVideoNoticia($noticia);
$videoEsPrincipal = ($noticia['medio_principal'] ?? 'imagen') === 'video'
    && trim((string) $videoHtml) !== '';
$mostrarGaleriaCompleta = $videoEsPrincipal ? $totalImagenes > 0 : $totalImagenes > 1;
?>

<link rel="stylesheet" href="<?= htmlspecialchars(css_url('public-noticias.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(css_url('reportes-confirmados.css'), ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($error !== null): ?>
    <div class="new-alerta new-alerta-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php else: ?>

<article class="new-noticia-completa">

    <h1 class="new-titulo-principal">
        <?= htmlspecialchars((string) $noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <?php if (!empty($noticia['subtitulo'])): ?>
        <h2 class="new-subtitulo">
            <?= htmlspecialchars((string) $noticia['subtitulo'], ENT_QUOTES, 'UTF-8'); ?>
        </h2>
    <?php endif; ?>

    <?php if ($reportesConfirmadosNoticia['total'] > 0): ?>
        <aside class="reporte-confirmado-aviso" aria-label="Reportes confirmados de esta noticia">
            <strong>🚩 <?= (int) $reportesConfirmadosNoticia['total']; ?> reporte<?= $reportesConfirmadosNoticia['total'] === 1 ? '' : 's'; ?> confirmado<?= $reportesConfirmadosNoticia['total'] === 1 ? '' : 's'; ?></strong>
            <span>Motivos: <?= htmlspecialchars(implode(', ', $reportesConfirmadosNoticia['motivos']), ENT_QUOTES, 'UTF-8'); ?>.</span>
            <small>La identidad y la descripción de quienes reportaron permanecen privadas.</small>
        </aside>
    <?php endif; ?>

    <div class="new-contenido-principal">
        <?php if ($videoEsPrincipal): ?>
            <div class="new-imagen-flotante new-video-principal">
                <?= $videoHtml; ?>
            </div>
        <?php else: ?>
        <div
            class="new-imagen-flotante"
            role="button"
            tabindex="0"
            aria-label="Abrir galería de imágenes"
            onclick="abrirModalGaleria(0)"
            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); abrirModalGaleria(0); }"
        >
            <?php if (isset($imagenesGaleria[0]['src'])): ?>
                <img
                    src="<?= htmlspecialchars((string) $imagenesGaleria[0]['src'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="new-img-flotante"
                    alt="<?= htmlspecialchars((string) $noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                >
            <?php else: ?>
                <div class="new-img-flotante sin-imagen">📷 Sin imagen</div>
            <?php endif; ?>

            <?php if ($totalImagenes > 1): ?>
                <div
                    class="new-badge-galeria"
                    onclick="event.stopPropagation(); abrirModalGaleria(0)"
                >
                    📸 +<?= $totalImagenes - 1; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="new-texto-noticia new-contenido-html">
            <?php
            /*
             * El contenido se imprime como HTML porque procede del editor
             * de noticias. Debe sanearse al guardar desde el panel editorial.
             */
            echo sanitizarHtmlNoticia((string) $noticia['contenido']);
            ?>
        </div>

        <div class="new-clean"></div>
    </div>

    <?php if ($mostrarGaleriaCompleta): ?>
        <div class="new-galeria-completa">
            <h3 class="new-galeria-titulo">📸 Imágenes</h3>

            <div class="new-grid-galeria">
                <?php foreach ($imagenesGaleria as $index => $imagen): ?>
                    <div class="new-item-galeria">
                        <div
                            class="new-miniatura"
                            role="button"
                            tabindex="0"
                            aria-label="Abrir imagen <?= $index + 1; ?>"
                            onclick="abrirModalGaleria(<?= $index; ?>)"
                            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); abrirModalGaleria(<?= $index; ?>); }"
                        >
                            <img
                                src="<?= htmlspecialchars((string) $imagen['src'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="img-galeria"
                                alt="Imagen <?= $index + 1; ?> de la noticia"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>

                        <?php if (trim((string) $imagen['texto']) !== ''): ?>
                            <p class="new-texto-imagen">
                                <?= nl2br(
                                    htmlspecialchars(
                                        (string) $imagen['texto'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div
        id="modalGaleria"
        class="modal-galeria"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        aria-label="Galería de imágenes"
        aria-hidden="true"
    >
        <div class="modal-galeria-contenido">
            <button
                type="button"
                class="modal-galeria-cerrar"
                onclick="cerrarModalGaleria()"
                aria-label="Cerrar galería"
            >&times;</button>

            <button
                type="button"
                class="modal-galeria-nav modal-galeria-prev"
                onclick="cambiarImagenGaleria(-1)"
                aria-label="Imagen anterior"
            >❮</button>

            <button
                type="button"
                class="modal-galeria-nav modal-galeria-next"
                onclick="cambiarImagenGaleria(1)"
                aria-label="Imagen siguiente"
            >❯</button>

            <img id="modalGaleriaImagen" src="" alt="Imagen ampliada">
            <p id="modalGaleriaTexto" class="modal-galeria-texto"></p>
            <div class="modal-galeria-contador" id="modalGaleriaContador"></div>
        </div>
    </div>

    <?php if (!$videoEsPrincipal && trim((string) $videoHtml) !== ''): ?>
        <div class="imgs-videos-relacionados">
            <div class="new-video-container">
                <h3 class="new-video-titulo">🎬 Vídeo</h3>
                <?= $videoHtml; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="clean"></div>

    <div class="new-acciones-noticia">
        <button
            type="button"
            class="btn-new btn-new-large"
            onclick="window.location.href='<?= htmlspecialchars(route($vistaPrivada ? 'privado_valoraciones' : 'valoraciones', ['id' => $id]), ENT_QUOTES, 'UTF-8'); ?>'"
        >
            Detalles
        </button>

        <button
            type="button"
            class="btn-new btn-new-large btn-new-secondary"
            onclick="window.location.href='<?= htmlspecialchars(route($vistaPrivada ? 'privado_relacionadas' : 'ver-relacionadas', ['id' => $id]), ENT_QUOTES, 'UTF-8'); ?>'"
        >
            <?= $totalRelacionadas > 0 ? $totalRelacionadas : ''; ?>&nbsp;Relacionadas
        </button>

        <?php if (!$vistaPrivada): ?>
        <button
            type="button"
            id="btn-favorito"
            class="btn-new btn-new-favorito"
            onclick="toggleFavorito(<?= $id; ?>, this)"
            data-favorito="<?= $esFavorito ? '1' : '0'; ?>"
        >
            <?= $esFavorito ? '❤️ Favorita' : '🤍 Mis Favoritas'; ?>
        </button>
        <?php endif; ?>

        <a
            href="<?= htmlspecialchars(route($vistaPrivada ? 'privado_comentarios' : 'comentarios_noticia', ['id' => $id]), ENT_QUOTES, 'UTF-8'); ?>"
            class="btn-new btn-new-comentarios"
        >
            <?= $totalComentarios; ?>&nbsp;&nbsp;Comentarios
        </a>

        <?php if (estaLogueado() && (int)($_SESSION['usuario_id'] ?? 0) !== (int)$noticia['id_autor']): ?>
            <a
                href="<?= htmlspecialchars(route($vistaPrivada ? 'privado_reportar_noticia' : 'reportar_noticia', ['id' => $id]), ENT_QUOTES, 'UTF-8'); ?>"
                class="btn-new btn-new-secondary"
            >🚩 Reportar</a>
        <?php endif; ?>
    </div>

    <?php if (!$vistaPrivada): ?>
    <div class="new-compartir">
        <h3 class="new-compartir-titulo">Compartir:</h3>

        <div class="new-redes-sociales">
            <?php
            $urlActual = rawurlencode(route('noticia', ['id' => $id]));
            $tituloEncoded = rawurlencode((string) $noticia['titulo']);
            ?>

            <a
                href="https://twitter.com/intent/tweet?text=<?= $tituloEncoded; ?>&amp;url=<?= $urlActual; ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-new btn-new-small"
            >X</a>

            <a
                href="https://www.facebook.com/sharer/sharer.php?u=<?= $urlActual; ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-new btn-new-small"
            >Facebook</a>

            <a
                href="https://wa.me/?text=<?= $tituloEncoded; ?>%20<?= $urlActual; ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-new btn-new-small"
            >WhatsApp</a>

            <a
                href="https://t.me/share/url?url=<?= $urlActual; ?>&amp;text=<?= $tituloEncoded; ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-new btn-new-small"
            >Telegram</a>
        </div>

        <div class="clean"></div>
    </div>
    <?php endif; ?>
</article>

<div
    id="modalReporte"
    class="modal-reporte"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modalReporteTitulo"
    aria-hidden="true"
>
    <div class="modal-reporte-contenido">
        <div class="modal-reporte-header">
            <h3 id="modalReporteTitulo">🚩 Reportar comentario</h3>

            <button
                type="button"
                class="modal-reporte-cierre"
                onclick="cerrarModalReporte()"
                aria-label="Cerrar formulario de reporte"
            >&times;</button>
        </div>

        <form id="formReporte">
            <input
                type="hidden"
                id="reporte_comentario_id"
                name="comentario_id"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <div class="campo-form">
                <label for="reporte_motivo">Motivo del reporte:</label>

                <select name="motivo" id="reporte_motivo" required>
                    <option value="">-- Selecciona un motivo --</option>
                    <option value="spam">📢 Spam o publicidad</option>
                    <option value="ofensivo">🤬 Contenido ofensivo</option>
                    <option value="acoso">⚠️ Acoso o insultos</option>
                    <option value="incorrecto">❌ Información incorrecta</option>
                    <option value="otro">📝 Otro motivo</option>
                </select>
            </div>

            <div class="campo-form">
                <label for="reporte_descripcion">Descripción (opcional):</label>

                <textarea
                    id="reporte_descripcion"
                    name="descripcion"
                    rows="3"
                    placeholder="Explica brevemente el motivo..."
                ></textarea>
            </div>

            <div class="modal-reporte-footer">
                <button type="button" onclick="cerrarModalReporte()">
                    Cancelar
                </button>

                <button type="submit" class="btn-enviar">
                    Enviar reporte
                </button>
            </div>
        </form>
    </div>
</div>

<script>
'use strict';

window.imagenesGaleria = <?= $imagenesGaleriaJson; ?>;

const URL_PROCESAR_REPORTE = <?= json_encode(
    $urlReporte,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
); ?>;

const URL_TOGGLE_FAVORITO = <?= json_encode(
    $urlFavorito,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
); ?>;

const CSRF_TOKEN = <?= json_encode(
    $csrfToken,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
); ?>;

let indiceActual = 0;
let modalReporte = null;
let formReporte = null;
let ultimoFocoGaleria = null;
let ultimoFocoReporte = null;

function contenerFocoModal(event, modal) {
    if (event.key !== 'Tab') {
        return;
    }

    const enfocables = Array.from(
        modal.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    ).filter(function (elemento) {
        return elemento.offsetParent !== null;
    });

    if (enfocables.length === 0) {
        event.preventDefault();
        return;
    }

    const primero = enfocables[0];
    const ultimo = enfocables[enfocables.length - 1];

    if (event.shiftKey && document.activeElement === primero) {
        event.preventDefault();
        ultimo.focus();
    } else if (!event.shiftKey && document.activeElement === ultimo) {
        event.preventDefault();
        primero.focus();
    }
}

function abrirModalGaleria(indice) {
    const imagenes = window.imagenesGaleria;

    if (!Array.isArray(imagenes) || imagenes.length === 0) {
        return;
    }

    indiceActual = Number.isInteger(indice) ? indice : 0;

    if (indiceActual < 0 || indiceActual >= imagenes.length) {
        indiceActual = 0;
    }

    actualizarModalGaleria();

    const modalGaleria = document.getElementById('modalGaleria');
    if (modalGaleria) {
        ultimoFocoGaleria = document.activeElement;
        modalGaleria.style.display = 'flex';
        modalGaleria.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const botonCerrar = modalGaleria.querySelector('.modal-galeria-cerrar');
        if (botonCerrar) {
            botonCerrar.focus();
        }
    }
}

function actualizarModalGaleria() {
    const imagenes = window.imagenesGaleria;
    const imagen = imagenes[indiceActual];

    if (!imagen) {
        return;
    }

    const elementoImagen = document.getElementById('modalGaleriaImagen');
    const elementoTexto = document.getElementById('modalGaleriaTexto');
    const elementoContador = document.getElementById('modalGaleriaContador');

    if (elementoImagen) {
        elementoImagen.src = imagen.src || '';
    }

    if (elementoTexto) {
        elementoTexto.textContent = imagen.texto || '';
    }

    if (elementoContador) {
        elementoContador.textContent =
            `${indiceActual + 1} / ${imagenes.length}`;
    }
}

function cambiarImagenGaleria(delta) {
    const imagenes = window.imagenesGaleria;

    if (!Array.isArray(imagenes) || imagenes.length === 0) {
        return;
    }

    indiceActual += delta;

    if (indiceActual < 0) {
        indiceActual = imagenes.length - 1;
    } else if (indiceActual >= imagenes.length) {
        indiceActual = 0;
    }

    actualizarModalGaleria();
}

function cerrarModalGaleria() {
    const modalGaleria = document.getElementById('modalGaleria');

    if (modalGaleria && modalGaleria.style.display === 'flex') {
        modalGaleria.style.display = 'none';
        modalGaleria.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (ultimoFocoGaleria && typeof ultimoFocoGaleria.focus === 'function') {
            ultimoFocoGaleria.focus();
        }
    }
}

function abrirModalReporte(comentarioId) {
    if (!modalReporte) {
        return;
    }

    ultimoFocoReporte = document.activeElement;

    const inputComentario = document.getElementById('reporte_comentario_id');
    const selectMotivo = document.getElementById('reporte_motivo');
    const descripcion = document.getElementById('reporte_descripcion');

    if (inputComentario) {
        inputComentario.value = String(comentarioId);
    }

    if (selectMotivo) {
        selectMotivo.value = '';
    }

    if (descripcion) {
        descripcion.value = '';
    }

    modalReporte.style.display = 'flex';
    modalReporte.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    if (selectMotivo) {
        selectMotivo.focus();
    }
}

function cerrarModalReporte() {
    if (modalReporte && modalReporte.style.display === 'flex') {
        modalReporte.style.display = 'none';
        modalReporte.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (ultimoFocoReporte && typeof ultimoFocoReporte.focus === 'function') {
            ultimoFocoReporte.focus();
        }
    }
}

async function enviarReporte(event) {
    event.preventDefault();

    if (!formReporte) {
        return;
    }

    const botonEnviar = formReporte.querySelector('button[type="submit"]');

    if (botonEnviar) {
        botonEnviar.disabled = true;
    }

    try {
        const response = await fetch(URL_PROCESAR_REPORTE, {
            method: 'POST',
            body: new FormData(formReporte),
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        const mensaje = data.mensaje || data.message || 'Solicitud procesada.';

        alert(mensaje);

        if (data.success) {
            cerrarModalReporte();
        }
    } catch (error) {
        console.error('Error enviando reporte:', error);
        alert('❌ Error de conexión. Inténtalo de nuevo.');
    } finally {
        if (botonEnviar) {
            botonEnviar.disabled = false;
        }
    }
}

async function toggleFavorito(id, boton) {
    const cuerpo = new URLSearchParams({
        id_noticia: String(id),
        csrf_token: CSRF_TOKEN
    });

    boton.disabled = true;

    try {
        const response = await fetch(URL_TOGGLE_FAVORITO, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: cuerpo.toString()
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            alert(data.message || data.mensaje || 'No se pudo procesar la solicitud.');
            return;
        }

        if (data.favorito) {
            boton.textContent = '❤️ Favorita';
            boton.dataset.favorito = '1';
        } else {
            boton.textContent = '🤍 Mis Favoritas';
            boton.dataset.favorito = '0';
        }
    } catch (error) {
        console.error('Error al modificar favorito:', error);
        alert('Error al procesar la solicitud.');
    } finally {
        boton.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    modalReporte = document.getElementById('modalReporte');
    formReporte = document.getElementById('formReporte');

    if (formReporte) {
        formReporte.addEventListener('submit', enviarReporte);
    }

    const modalGaleria = document.getElementById('modalGaleria');

    if (modalGaleria) {
        modalGaleria.addEventListener('click', function (event) {
            if (event.target === modalGaleria) {
                cerrarModalGaleria();
            }
        });
    }

    if (modalReporte) {
        modalReporte.addEventListener('click', function (event) {
            if (event.target === modalReporte) {
                cerrarModalReporte();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        const modalGaleriaAbierto =
            modalGaleria && modalGaleria.style.display === 'flex';

        if (modalGaleriaAbierto && event.key === 'Escape') {
            event.preventDefault();
            cerrarModalGaleria();
            return;
        }

        if (!modalGaleriaAbierto) {
            const modalReporteAbierto =
                modalReporte && modalReporte.style.display === 'flex';

            if (!modalReporteAbierto) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                cerrarModalReporte();
                return;
            }

            contenerFocoModal(event, modalReporte);
            return;
        }

        contenerFocoModal(event, modalGaleria);

        if (event.key === 'ArrowLeft') {
            cambiarImagenGaleria(-1);
        } else if (event.key === 'ArrowRight') {
            cambiarImagenGaleria(1);
        }
    });
});
</script>

<?php endif; ?><?php require_once __DIR__ . '/../partials/footer.php'; ?>
