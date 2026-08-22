<?php
declare(strict_types=1);

/**
 * Carga común mínima de la aplicación.
 *
 * Mantiene disponibles la configuración, las funciones compartidas
 * y la conexión a la base de datos sin añadir dependencias específicas.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/mail-config.php';
require_once __DIR__ . '/noticia-utils.php';
require_once __DIR__ . '/notificaciones.php';
