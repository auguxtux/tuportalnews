<?php
declare(strict_types=1);


/**
 * FUNCIONES DE FECHAS
 */

function formatearFecha($fecha, $formato = 'd/m/Y H:i') {
    if (!$fecha || $fecha === '0000-00-00 00:00:00') {
        return 'Fecha no disponible';
    }
    try {
        return (new DateTime($fecha))->format($formato);
    } catch (Exception $e) {
        return 'Fecha inválida';
    }
}

function tiempoTranscurrido($fecha) {
    if (!$fecha) return 'Fecha desconocida';
    
    $diferencia = (new DateTime())->diff(new DateTime($fecha));
    
    if ($diferencia->y > 0) return 'hace ' . $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
    if ($diferencia->m > 0) return 'hace ' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
    if ($diferencia->d > 0) return 'hace ' . $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
    if ($diferencia->h > 0) return 'hace ' . $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
    if ($diferencia->i > 0) return 'hace ' . $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
    
    return 'hace unos segundos';
}
