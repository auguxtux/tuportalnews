<?php
declare(strict_types=1);


require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
iniciarSesion();

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!Permisos::esPeriodista() && !Permisos::esAdmin()) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos']);
    exit;
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Error de seguridad']);
    exit;
}

$nombre = limpiarDatos($_POST['nombre'] ?? '');
if (empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'Nombre obligatorio']);
    exit;
}

$pdo = db();
$slug = generarSlug($nombre);

// Verificar duplicado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fuentes WHERE nombre = ?");
$stmt->execute([$nombre]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'Ya existe esa fuente']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO fuentes (nombre, slug, activa) VALUES (?, ?, 1)");
$stmt->execute([$nombre, $slug]);

echo json_encode(['success' => true, 'nombre' => $nombre, 'id' => $pdo->lastInsertId()]);
