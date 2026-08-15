<?php
declare(strict_types=1);


/**
 * AJAX: Subir imagen desde el editor TinyMCE
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';

header('Content-Type: application/json');

// Solo periodistas y administradores pueden subir imágenes desde el editor
if (!Permisos::puedeAccederPeriodista()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar método y archivo
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Solicitud incorrecta']);
    exit;
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Error de seguridad. Recarga la página.']);
    exit;
}

try {
    $upload = new UploadHandler($_FILES['file'], 'editor', 'imagen', (int)($_SESSION['usuario_id'] ?? 0));
    $nombreArchivo = $upload->subir();

    if ($nombreArchivo === false || $nombreArchivo === null) {
        $errores = $upload->getErrores();
        http_response_code(422);
        echo json_encode(['error' => $errores[0] ?? 'No se pudo procesar la imagen.']);
        exit;
    }

    echo json_encode(['location' => base_url('uploads/editor/' . $nombreArchivo)]);
} catch (Throwable $e) {
    registrarErrorInterno('AJAX.EDITOR.IMAGEN_SUBIR', $e);
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo procesar la imagen.']);
}
