<?php
declare(strict_types=1);

/**
 * Estadísticas resumidas para la documentación administrativa.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!estaLogueado() || !esAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'Acceso no autorizado']);
    exit;
}

try {
    $pdo = db();

    $respuesta = [
        'success' => true,
        'usuarios' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'")->fetchColumn(),
        'noticias' => (int) $pdo->query("SELECT COUNT(*) FROM noticias WHERE estado = 'publicada'")->fetchColumn(),
        'comentarios' => (int) $pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn(),
        'categorias' => (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
        'valoraciones' => (int) $pdo->query('SELECT COUNT(*) FROM megusta_noticias WHERE valoracion IS NOT NULL')->fetchColumn(),
        'tablas' => (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn(),
    ];

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    registrarErrorInterno('AJAX.ADMIN.ESTADISTICAS', $e);
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'No se pudieron obtener las estadísticas']);
}
