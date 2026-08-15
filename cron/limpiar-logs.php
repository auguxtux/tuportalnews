<?php
declare(strict_types=1);

/**
 * Limpia diariamente un lote acotado de logs de actividad antiguos.
 *
 * Cron sugerido:
 * 15 3 * * * /usr/bin/php /ruta/al/proyecto/cron/limpiar-logs.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/logs.php';

try {
    $eliminados = limpiarLogsActividadAntiguos(
        db(),
        LOG_RETENTION_DAYS,
        5000
    );

    fwrite(
        STDOUT,
        "Limpieza de logs completada: {$eliminados} registros eliminados." . PHP_EOL
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo completar la limpieza de logs.' . PHP_EOL);
    exit(1);
}
