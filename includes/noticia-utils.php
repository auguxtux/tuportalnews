<?php
declare(strict_types=1);


/**
 * UTILIDADES PARA MOSTRAR NOTICIAS (imágenes y videos)
 */

/**
 * Genera el HTML para mostrar la imagen de una noticia
 */
/**
 * Muestra la imagen de una noticia (prioriza imagen_principal, luego imagen_externa)
 * 
 * @param array $noticia Array con los datos de la noticia
 * @param string $clase CSS clase para la imagen
 * @param string $placeholder Texto o emoji para cuando no hay imagen
 * @param string|null $enlace URL opcional para hacer clicable la imagen
 * @return string HTML de la imagen o placeholder
 */
function mostrarImagenNoticia($noticia, $clase = 'noticia-img', $placeholder = '📷', ?string $enlace = null) {
    $html = '';
    $imagenLocal = trim((string) ($noticia['imagen_principal'] ?? ''));
    $imagenLocalValida = $imagenLocal !== ''
        && basename($imagenLocal) === $imagenLocal
        && is_file(UPLOAD_NOTICIAS . $imagenLocal);

    // Priorizar imagen_principal (local)
    if ($imagenLocalValida) {
        $url = base_url('uploads/noticias/' . $imagenLocal);
        $html = '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="' . $clase . '" alt="' . htmlspecialchars($noticia['titulo'] ?? '') . '" loading="lazy">';
    } elseif (!empty($noticia['imagen_externa'])) {
        // Si no hay imagen_principal, usar imagen_externa (RSS u otras)
        $html = '<img src="' . htmlspecialchars($noticia['imagen_externa'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="' . $clase . '" alt="' . htmlspecialchars($noticia['titulo'] ?? '') . '" loading="lazy" onerror="this.style.display=\'none\'">';
    } else {
        $html = '<div class="' . $clase . ' sin-imagen">' . $placeholder . '</div>';
    }

    if ($enlace === null || $enlace === '') {
        return $html;
    }

    $titulo = trim((string) ($noticia['titulo'] ?? ''));
    $etiqueta = $titulo !== '' ? 'Abrir noticia: ' . $titulo : 'Abrir noticia';

    return '<a class="news-card__media-link" href="'
        . htmlspecialchars($enlace, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" aria-label="'
        . htmlspecialchars($etiqueta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . $html . '</a>';
}
/**
 * Genera el HTML para mostrar el video de una noticia
 */
function mostrarVideoNoticia($noticia, $clase = 'video-container') {
    // Video local
    $videoLocal = trim((string) ($noticia['video_nombre'] ?? ''));
    if (
        $videoLocal !== ''
        && basename($videoLocal) === $videoLocal
        && is_file(UPLOAD_NOTICIAS . $videoLocal)
        && ($noticia['video_tipo'] ?? 'local') === 'local'
    ) {
        $src = base_url('uploads/noticias/' . $videoLocal);
        return '<video controls class="video-local"><source src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></video>';
    }
    // YouTube o Vimeo con embed
    elseif (!empty($noticia['video_embed']) && in_array($noticia['video_tipo'] ?? '', ['youtube', 'vimeo'])) {
        $embed = trim((string) $noticia['video_embed']);
        $host = strtolower((string) parse_url($embed, PHP_URL_HOST));
        $hostsPermitidos = ($noticia['video_tipo'] ?? '') === 'youtube'
            ? ['www.youtube.com', 'youtube.com']
            : ['player.vimeo.com'];

        if (
            filter_var($embed, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($embed, PHP_URL_SCHEME)) === 'https'
            && in_array($host, $hostsPermitidos, true)
        ) {
            return '<div class="' . htmlspecialchars((string) $clase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><iframe src="' . htmlspecialchars($embed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" frameborder="0" loading="lazy" allowfullscreen></iframe></div>';
        }
    }
    // Vídeo externo seleccionado desde el catálogo oficial de NASA.
    elseif (!empty($noticia['video_externo']) && ($noticia['video_tipo'] ?? '') === 'nasa') {
        $url = (string) $noticia['video_externo'];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (filter_var($url, FILTER_VALIDATE_URL) && $host === 'images-assets.nasa.gov' && preg_match('/\.mp4(?:\?|$)/i', $url)) {
            $segura = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<video controls preload="metadata" class="video-local"><source src="' . $segura . '" type="video/mp4"></video><p class="video-fuente">Fuente multimedia: NASA</p>';
        }
    }
    return '';
}

/**
 * Genera el badge del tipo de video para listados
 */
function badgeVideoNoticia($noticia) {
    $tipo = $noticia['video_tipo'] ?? '';
    if ($tipo === 'youtube') {
        return '<span class="badge-video badge-youtube">▶️ YouTube</span>';
    } elseif ($tipo === 'vimeo') {
        return '<span class="badge-video badge-vimeo">🎥 Vimeo</span>';
    } elseif ($tipo === 'nasa' && !empty($noticia['video_externo'])) {
        return '<span class="badge-video badge-nasa">🚀 NASA</span>';
    } elseif (!empty($noticia['video_nombre'])) {
        return '<span class="badge-video badge-local">📹 Video</span>';
    }
    return '';
}

/**
 * Enlace a la noticia
 */
function enlaceNoticia($noticia) {
    return '/public/noticia.php?id=' . $noticia['id_noticia'];
}

/**
 * Extracto del contenido
 */
function extractoNoticia($noticia, $max_length = 150) {
    $texto = strip_tags($noticia['contenido']);
    if (strlen($texto) > $max_length) {
        $texto = substr($texto, 0, $max_length) . '...';
    }
    return htmlspecialchars($texto);
}

/**
 * Obtiene nombres seguros de imágenes locales insertadas mediante TinyMCE.
 *
 * @return string[]
 */
function obtenerArchivosEditorNoticia(string $contenido): array {
    if ($contenido === '') {
        return [];
    }

    preg_match_all(
        '~(?:https?://[^\s"\']+)?/uploads/editor/([^?&#"\'<>/]+)~i',
        html_entity_decode($contenido, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $coincidencias
    );

    $archivos = [];
    foreach ($coincidencias[1] ?? [] as $archivo) {
        $archivo = rawurldecode((string) $archivo);
        if ($archivo !== '' && basename($archivo) === $archivo) {
            $archivos[$archivo] = true;
        }
    }

    return array_keys($archivos);
}

/**
 * Calcula los archivos locales vinculados directamente a una noticia.
 *
 * @return array<int, array{ruta:string,tipo:string,nombre:string}>
 */
function obtenerArchivosLocalesNoticia(array $noticia): array {
    $archivos = [];
    $campos = [
        'imagen_principal', 'imagen_2', 'imagen_3',
        'imagen_4', 'imagen_5', 'imagen_6', 'video_nombre',
    ];

    foreach ($campos as $campo) {
        $nombre = $noticia[$campo] ?? null;
        if (
            is_string($nombre)
            && $nombre !== ''
            && !filter_var($nombre, FILTER_VALIDATE_URL)
            && basename($nombre) === $nombre
        ) {
            $archivos[] = [
                'ruta' => UPLOAD_NOTICIAS . $nombre,
                'tipo' => 'noticia',
                'nombre' => $nombre,
            ];
        }
    }

    foreach (obtenerArchivosEditorNoticia((string) ($noticia['contenido'] ?? '')) as $nombre) {
        $archivos[] = [
            'ruta' => UPLOADS_PATH . 'editor' . DIRECTORY_SEPARATOR . $nombre,
            'tipo' => 'editor',
            'nombre' => $nombre,
        ];
    }

    return $archivos;
}

/**
 * Calcula el espacio local ocupado por una noticia, sin duplicar archivos.
 */
function calcularEspacioNoticiaBytes(array $noticia): int {
    $total = 0;
    $vistos = [];

    foreach (obtenerArchivosLocalesNoticia($noticia) as $archivo) {
        $ruta = $archivo['ruta'];
        if (!isset($vistos[$ruta]) && is_file($ruta)) {
            $vistos[$ruta] = true;
            $tamano = filesize($ruta);
            if ($tamano !== false) {
                $total += $tamano;
            }
        }
    }

    return $total;
}

/**
 * Elimina integralmente hasta 10 noticias autorizadas y sus archivos locales.
 * Las dependencias de base de datos se eliminan mediante las FK en cascada.
 *
 * @return array{success:bool,message:string,eliminadas?:int,bytes?:int,archivos_no_eliminados?:int}
 */
function eliminarNoticiasCompletamente(
    PDO $pdo,
    array $ids,
    int $idAutor,
    bool $esAdmin = false,
    ?int $privada = null
): array {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn (int $id): bool => $id > 0
    )));

    if ($ids === [] || count($ids) > 10) {
        return ['success' => false, 'message' => 'Selecciona entre 1 y 10 noticias.'];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id_noticia, id_autor, privada, contenido,
                   imagen_principal, imagen_2, imagen_3, imagen_4,
                   imagen_5, imagen_6, video_nombre
            FROM noticias WHERE id_noticia IN ($marcas)";
    $parametros = $ids;

    if (!$esAdmin) {
        $sql .= ' AND id_autor = ?';
        $parametros[] = $idAutor;
    }
    if ($privada !== null) {
        $sql .= ' AND privada = ?';
        $parametros[] = $privada;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $noticias = $stmt->fetchAll();

    if (count($noticias) !== count($ids)) {
        return ['success' => false, 'message' => 'Alguna noticia no existe o no te pertenece.'];
    }

    $archivos = [];
    $bytes = 0;
    foreach ($noticias as $noticia) {
        $bytes += calcularEspacioNoticiaBytes($noticia);
        foreach (obtenerArchivosLocalesNoticia($noticia) as $archivo) {
            $archivos[$archivo['ruta']] = $archivo;
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM noticias WHERE id_noticia IN ($marcas)");
        $stmt->execute($ids);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        registrarErrorInterno('NOTICIAS.ELIMINAR_COMPLETAMENTE', $e);
        return ['success' => false, 'message' => 'No se pudieron eliminar las noticias seleccionadas.'];
    }

    $noEliminados = 0;
    foreach ($archivos as $archivo) {
        if ($archivo['tipo'] === 'editor') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM noticias WHERE contenido LIKE ?');
            $stmt->execute(['%/uploads/editor/' . $archivo['nombre'] . '%']);
            if ((int) $stmt->fetchColumn() > 0) {
                continue;
            }
        }

        if (is_file($archivo['ruta']) && !unlink($archivo['ruta'])) {
            $noEliminados++;
        }
    }

    return [
        'success' => true,
        'message' => count($ids) === 1
            ? 'Noticia eliminada correctamente.'
            : count($ids) . ' noticias eliminadas correctamente.',
        'eliminadas' => count($ids),
        'bytes' => $bytes,
        'archivos_no_eliminados' => $noEliminados,
    ];
}
