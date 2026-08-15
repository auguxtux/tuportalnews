<?php
declare(strict_types=1);

/**
 * Vista de una noticia privada.
 *
 * La presentación se comparte con la noticia pública, pero el modo privado
 * obliga a consultar únicamente contenido privado y a comprobar el permiso.
 */

define('VISTA_NOTICIA_PRIVADA', true);

require __DIR__ . '/../public/noticia.php';
