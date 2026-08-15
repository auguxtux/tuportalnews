<?php
declare(strict_types=1);


/**
 * REDIRECCIÓN AL SISTEMA DE MINIFICACIÓN UNIFICADO
 * Este archivo se mantiene por compatibilidad, pero redirige a configuracion.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirAdmin();

// Redirigir a la configuración principal, anclando a la sección de minificación
header('Location: ' . route('admin_config') . '#minificacion');
exit;
