<?php
declare(strict_types=1);


/**
 * FUNCIONES DE TEXTO
 */

function truncarTexto($texto, $longitud = 100, $final = '...') {
    if (strlen($texto) <= $longitud) return $texto;
    $texto = wordwrap($texto, $longitud, '|||', true);
    return explode('|||', $texto)[0] . $final;
}

function obtenerPrimerParrafo($contenido, $limite = 300) {
    $texto = html_entity_decode($contenido, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = strip_tags($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = trim($texto);
    
    if (strlen($texto) <= $limite) return $texto;
    
    $texto = substr($texto, 0, $limite);
    $ultimo_espacio = strrpos($texto, ' ');
    if ($ultimo_espacio !== false) {
        $texto = substr($texto, 0, $ultimo_espacio);
    }
    
    return $texto . '...';
}

/**
 * Extrae el dominio legible de una URL (sin www ni extensión).
 * Ejemplos:
 *   https://www.elpais.com/noticia → elpais
 *   https://feeds.efe.com/rss/efe → efe
 *   elpais.com → elpais
 *   Reuters → Reuters (si no es URL)
 */
function extraerDominioFuente(string $url): string {
    $url = trim($url);
    if ($url === '') return $url;

    if (preg_match('/^https?:\/\//i', $url) === 0) {
        return $url;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return $url;

    $host = preg_replace('/^(www|feeds|rss)\./i', '', $host);
    $partes = explode('.', $host);
    $primero = $partes[0] ?? '';

    if (preg_match('/^e00-([a-z]+)/i', $primero, $m)) {
        return strtolower($m[1]);
    }

    $cdn = ['uecdn', 'cdn', 'static', 'media', 'img'];
    if (in_array(strtolower($primero), $cdn) && isset($partes[1])) {
        return strtolower($partes[1]);
    }

    // En hosts de agregadores (p. ej. eldiario.opennemas.com), la marca
    // sigue siendo el primer segmento, igual que en un dominio directo.
    return strtolower($primero);
}
