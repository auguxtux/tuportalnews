<?php
declare(strict_types=1);


/**
 * AJAX: Procesar valoraciones - VERSIÓN DEFINITIVA
 */

// Headers correctos para JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/valoraciones.php';
require_once __DIR__ . '/../includes/privado.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$valoracionPrivada = defined('VALORACION_PRIVADA') && VALORACION_PRIVADA === true;

if ($valoracionPrivada && !usuarioEsPrivado()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Contenido no disponible']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Error de seguridad. Recarga la página.']);
    exit;
}

$id_noticia = isset($_POST['id_noticia']) ? (int) $_POST['id_noticia'] : 0;
$valoracion = isset($_POST['valoracion']) ? (int) $_POST['valoracion'] : 0;

// Validación básica
if (!$id_noticia || $valoracion < 1 || $valoracion > 3) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM noticias
         WHERE id_noticia = ?
           AND estado = 'publicada'
           AND privada = ?
         LIMIT 1"
    );
    $stmt->execute([$id_noticia, $valoracionPrivada ? 1 : 0]);

    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Contenido no disponible']);
        exit;
    }

    $resultado = false;
    $mensaje = '';
    
    if (isset($_SESSION['usuario_id'])) {
        // Usuario registrado
        $puede = Valoraciones::puedeVotarUsuario($id_noticia, $_SESSION['usuario_id']);
        
        if ($puede['puede']) {
            $resultado = Valoraciones::registrarVotoUsuario($id_noticia, $_SESSION['usuario_id'], $valoracion);
            $mensaje = 'Voto registrado correctamente';
        } else {
            $mensaje = $puede['mensaje'];
        }
    } else {
        // Visitante
        $session_id = Valoraciones::getVisitorIdentifier();
        
        $puede = Valoraciones::puedeVotarVisitante($id_noticia, $session_id);
        
        if ($puede['puede']) {
            $resultado = Valoraciones::registrarVotoVisitante($id_noticia, $valoracion, $session_id);
            $mensaje = 'Voto registrado correctamente';
        } else {
            $mensaje = $puede['mensaje'];
        }
    }
    
    if ($resultado) {
        // Obtener estadísticas actualizadas
        $stats = Valoraciones::getEstadisticas($id_noticia);
        
        // Asegurar que stats es un array válido
        if (!is_array($stats)) {
            $stats = [];
        }
        
        $response = [
            'success' => true,
            'message' => $mensaje,
            'stats' => $stats
        ];
    } else {
        $response = [
            'success' => false,
            'error' => $mensaje ?: 'No puedes votar en este momento'
        ];
    }
    
    // Limpiar cualquier salida previa (por si acaso)
    if (ob_get_length()) ob_clean();
    
    // Enviar respuesta JSON
    echo json_encode($response);
    
} catch (Exception $e) {
    registrarErrorInterno('AJAX.VALORACION.PROCESAR', $e);
    
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => false, 
        'error' => 'No se pudo procesar la valoración'
    ]);
}
