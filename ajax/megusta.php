<?php
declare(strict_types=1);


/**
 * AJAX - Procesar "me gusta" en noticias
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['error' => 'Debes iniciar sesión']);
    exit;
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Error de seguridad. Recarga la página.']);
    exit;
}

$id_noticia = isset($_POST['id_noticia']) ? (int)$_POST['id_noticia'] : 0;

if (!$id_noticia) {
    echo json_encode(['error' => 'Noticia no válida']);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM noticias
         WHERE id_noticia = ?
           AND estado = 'publicada'
           AND privada = 0
         LIMIT 1"
    );
    $stmt->execute([$id_noticia]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Noticia no disponible']);
        exit;
    }

    $accion = toggleMegusta($_SESSION['usuario_id'], $id_noticia);
    
    $stmt = $pdo->prepare("SELECT megusta FROM noticias WHERE id_noticia = ?");
    $stmt->execute([$id_noticia]);
    $total = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'accion' => $accion,
        'total' => $total,
        'activo' => ($accion === 'agregado')
    ]);
    
} catch (Exception $e) {
    registrarErrorInterno('AJAX.MEGUSTA.PROCESAR', $e);
    echo json_encode(['error' => 'No se pudo procesar la acción']);
}
?>
