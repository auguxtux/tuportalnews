<?php
declare(strict_types=1);


/**
 * FUNCIONES DE MENSAJES FLASH
 */

function mensajeFlash($tipo, $texto) {
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'texto' => $texto,
        'tiempo' => time()
    ];
}

function obtenerMensajeFlash() {
    if (!isset($_SESSION['flash'])) return null;
    
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    
    return (isset($flash['tiempo']) && (time() - $flash['tiempo'] > 300)) ? null : $flash;
}
