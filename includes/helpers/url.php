<?php
declare(strict_types=1);


/**
 * FUNCIONES DE URLs
 */

function base_url($path = '') {
    static $base = null;
    if ($base === null) {
        $base = rtrim(SITE_URL, '/');
    }

    if (!$path) {
        return $base;
    }

    $normalizedPath = ltrim((string) $path, '/');
    $url = $base . '/' . $normalizedPath;

    if (str_starts_with($normalizedPath, 'assets/')) {
        return versionarUrlRecurso($url, ROOT_PATH . $normalizedPath);
    }

    return $url;
}

/**
 * Añade una versión estable basada en la fecha de modificación del archivo.
 */
function versionarUrlRecurso(string $url, string $rutaLocal): string
{
    if (!is_file($rutaLocal)) {
        return $url;
    }

    $version = filemtime($rutaLocal);
    if ($version === false) {
        return $url;
    }

    $separador = str_contains($url, '?') ? '&' : '?';
    return $url . $separador . 'v=' . $version;
}

function redireccionar($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    echo '<script>window.location.href=' . json_encode(
        (string) $url,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) . ';</script>';
    exit;
}

function current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}
