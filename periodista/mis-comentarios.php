<?php
declare(strict_types=1);

/**
 * Compatibilidad con la antigua ruta de comentarios del periodista.
 * La gestión está unificada en el módulo de usuario.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (!estaLogueado()) {
    mensajeFlash('warning', 'Debes iniciar sesión');
    redireccionar(route('login'));
}

redireccionar(route('mis_comentarios'));
