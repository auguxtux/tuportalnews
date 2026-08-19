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
