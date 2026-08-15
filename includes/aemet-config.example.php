<?php
declare(strict_types=1);

/**
 * Plantilla de configuración local de AEMET.
 *
 * Copiar como includes/aemet-config.php e introducir una API Key válida.
 * El archivo real está excluido del repositorio.
 */
return [
    'api_key' => 'PEGA_AQUI_TU_API_KEY',
    'municipio_id' => '35017',
    'municipio_nombre' => 'Puerto del Rosario',
    'cache_ttl' => 1800,
    'timeout' => 12,
    'alertas' => [
        'lluvia_probabilidad' => 70,
        'temperatura_maxima' => 35,
        'temperatura_minima' => 5,
        'viento_velocidad' => 50,
    ],
];
