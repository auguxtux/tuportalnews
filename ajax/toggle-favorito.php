<?php
declare(strict_types=1);


/**
 * TOGGLE FAVORITO - Guardar o quitar noticia de favoritos
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Solo usuarios logueados
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

// Verificar CSRF
if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Error de seguridad']);
    exit;
}

$id_noticia = isset($_POST['id_noticia']) ? (int)$_POST['id_noticia'] : 0;
$id_usuario = $_SESSION['usuario_id'];

if (!$id_noticia) {
    echo json_encode(['success' => false, 'message' => 'ID de noticia no válido']);
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
        echo json_encode(['success' => false, 'message' => 'Noticia no disponible']);
        exit;
    }
    
    // Verificar si ya está en favoritos
    $stmt = $pdo->prepare("SELECT id_favorito FROM favoritos WHERE id_usuario = ? AND id_noticia = ?");
    $stmt->execute([$id_usuario, $id_noticia]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        // Quitar de favoritos
        $stmt = $pdo->prepare("DELETE FROM favoritos WHERE id_usuario = ? AND id_noticia = ?");
        $stmt->execute([$id_usuario, $id_noticia]);
        echo json_encode(['success' => true, 'favorito' => false, 'message' => 'Noticia eliminada de favoritos']);
    } else {
        // Añadir a favoritos
        $stmt = $pdo->prepare("INSERT INTO favoritos (id_usuario, id_noticia) VALUES (?, ?)");
        $stmt->execute([$id_usuario, $id_noticia]);
        echo json_encode(['success' => true, 'favorito' => true, 'message' => 'Noticia guardada en favoritos']);
    }
    
} catch (Exception $e) {
    registrarErrorInterno('AJAX.FAVORITO.ALTERNAR', $e);
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
?>
