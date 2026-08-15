<?php
declare(strict_types=1);


/**
 * FUNCIONES DE SLUGS
 */

function generarSlug($texto) {
    $mapa = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
        ' ' => '-', '.' => '', ',' => '', '¿' => '', '?' => '',
        '¡' => '', '!' => '', ':' => '', ';' => ''
    ];
    $texto = strtolower(strtr($texto, $mapa));
    $texto = preg_replace('/[^a-z0-9-]+/', '', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    return trim($texto, '-');
}
