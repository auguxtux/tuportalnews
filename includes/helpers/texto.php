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

    // Si no parece URL, devolver tal cual (nombre de fuente manual)
    if (preg_match('/^https?:\/\//i', $url) === 0) {
        return $url;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return $url;

    // Quitar www.
    $host = preg_replace('/^www\./i', '', $host);

    // Quitar extensión (.com, .es, .org, etc.)
    $host = preg_replace('/\.[a-z]{2,}$/', '', $host);

    return strtolower(trim($host));
}
