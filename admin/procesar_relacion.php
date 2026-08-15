<?php
declare(strict_types=1);


/**
 * PROCESAR RELACIONES ENTRE NOTICIAS
 * Añade o elimina relaciones manuales
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

// Solo administradores pueden gestionar relaciones
Permisos::requerirAdmin();

$pdo = db();

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar(route('admin_noticias'));
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    mensajeFlash('error', 'Error de seguridad');
    redireccionar(route('admin_noticias'));
}

// Obtener datos del formulario
$id_origen = isset($_POST['id_origen']) ? (int)$_POST['id_origen'] : 0;
$id_destino = isset($_POST['id_destino']) ? (int)$_POST['id_destino'] : 0;
$peso = isset($_POST['peso']) ? (int)$_POST['peso'] : 5;

// Validaciones básicas
if (!$id_origen || !$id_destino) {
    mensajeFlash('error', 'Datos incompletos');
    redireccionar(route('admin_editar_noticia', ['id' => $id_origen]));
}

// No permitir relacionar una noticia consigo misma
if ($id_origen == $id_destino) {
    mensajeFlash('error', 'No puedes relacionar una noticia consigo misma');
    redireccionar(route('admin_editar_noticia', ['id' => $id_origen]));
}

try {
    // Verificar que ambas noticias existen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE id_noticia IN (?, ?)");
    $stmt->execute([$id_origen, $id_destino]);
    if ($stmt->fetchColumn() != 2) {
        mensajeFlash('error', 'Una de las noticias no existe');
        redireccionar(route('admin_editar_noticia', ['id' => $id_origen]));
    }
    
    // Insertar o actualizar relación
    $sql = "INSERT INTO noticias_relacionadas (id_noticia_origen, id_noticia_destino, peso, tipo) 
            VALUES (?, ?, ?, 'manual')
            ON DUPLICATE KEY UPDATE peso = ?, tipo = 'manual'";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([$id_origen, $id_destino, $peso, $peso]);
    
    if ($resultado) {
        mensajeFlash('success', 'Relación añadida correctamente');
        
        // Opcional: añadir también la relación inversa (bidireccional)
        if (isset($_POST['bidireccional']) && $_POST['bidireccional'] === '1') {
            $stmt_inv = $pdo->prepare("
                INSERT INTO noticias_relacionadas (id_noticia_origen, id_noticia_destino, peso, tipo) 
                VALUES (?, ?, ?, 'manual')
                ON DUPLICATE KEY UPDATE peso = ?, tipo = 'manual'
            ");
            $stmt_inv->execute([$id_destino, $id_origen, $peso, $peso]);
        }
        
    } else {
        mensajeFlash('error', 'Error al añadir la relación');
    }
    
} catch (PDOException $e) {
    registrarErrorInterno('ADMIN.RELACION.PROCESAR', $e);
    mensajeFlash('error', 'Error de base de datos');
}

// Redirigir de vuelta a la página de edición
redireccionar(route('admin_editar_noticia', ['id' => $id_origen]));
?>
