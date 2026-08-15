<?php
declare(strict_types=1);


/**
 * DESCARGAR BACKUP (SQL o ZIP)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirAdmin();

$file = basename((string) ($_GET['file'] ?? ''));
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (
    $file === ''
    || !preg_match('/\Abackup_[A-Za-z0-9._-]+\.(sql|zip)\z/i', $file)
    || !in_array($ext, ['sql', 'zip'], true)
) {
    http_response_code(404);
    exit('Archivo no disponible');
}

$filepath = __DIR__ . '/../backups/database/' . $file;

if (!is_file($filepath) || !is_readable($filepath)) {
    http_response_code(404);
    exit('Archivo no disponible');
}

if ($ext === 'zip') {
    header('Content-Type: application/zip');
} else {
    header('Content-Type: application/sql');
}

header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($filepath);
exit;
