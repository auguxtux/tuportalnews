<?php
declare(strict_types=1);


/**
 * AJAX - Buscar comentarios
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

// Cabecera JSON
header('Content-Type: application/json');

// Solo aceptar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'error' => 'Método no permitido',
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

try {
    // Obtener filtros
    $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : 0;
    $id_noticia = isset($_POST['id_noticia']) ? (int)$_POST['id_noticia'] : 0;
    $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;
    $palabras = isset($_POST['palabras']) ? limpiarDatos($_POST['palabras']) : '';
    $estado = isset($_POST['estado']) ? $_POST['estado'] : 'todos';
    $fecha_desde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
    $fecha_hasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';
    
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $por_pagina = 10;
    $offset = ($pagina - 1) * $por_pagina;
    
    $pdo = db();
    
    // Construir consulta
    $sql_count = "SELECT COUNT(*) 
                  FROM comentarios c
                  JOIN usuarios u ON c.id_usuario = u.id_usuario
                  JOIN noticias n ON c.id_noticia = n.id_noticia
                  WHERE 1=1";
    
    $sql = "SELECT c.*, 
                   u.nombre as usuario_nombre, 
                   u.avatar as usuario_avatar,
                   u.rol as usuario_rol,
                   n.titulo as noticia_titulo,
                   n.id_noticia
            FROM comentarios c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            JOIN noticias n ON c.id_noticia = n.id_noticia
            WHERE 1=1";
    
    $where = [];
    $params = [];

    // Este endpoint pertenece exclusivamente al buscador público.
    $where[] = "n.privada = 0";
    
    if ($id_categoria > 0) {
        $where[] = "n.id_categoria = :id_categoria";
        $params[':id_categoria'] = $id_categoria;
    }
    
    if ($id_noticia > 0) {
        $where[] = "c.id_noticia = :id_noticia";
        $params[':id_noticia'] = $id_noticia;
    }
    
    if ($id_usuario > 0) {
        $where[] = "c.id_usuario = :id_usuario";
        $params[':id_usuario'] = $id_usuario;
    }
    
    if ($palabras) {
        $where[] = "c.contenido LIKE :palabras";
        $params[':palabras'] = "%$palabras%";
    }
    
    if ($estado !== 'todos' && esAdmin()) {
        $where[] = "c.estado = :estado";
        $params[':estado'] = $estado;
    } elseif (!esAdmin()) {
        $where[] = "c.estado = 'aprobado'";
    }
    
    if ($fecha_desde) {
        $where[] = "DATE(c.fecha_comentario) >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }
    
    if ($fecha_hasta) {
        $where[] = "DATE(c.fecha_comentario) <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }
    
    if (!empty($where)) {
        $sql_count .= " AND " . implode(" AND ", $where);
        $sql .= " AND " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY c.fecha_comentario DESC";
    
    // Total de resultados
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_resultados = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_resultados / $por_pagina);
    
    // Resultados paginados
    $sql .= " LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $resultados = $stmt->fetchAll();
    
    // Formatear resultados para JSON
    $json_resultados = [];
    $csrfTokenAcciones = htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8');
    foreach ($resultados as $comentario) {
        // Construir badge de estado
        $estado_badge = '';
        if (esAdmin()) {
            $estado_class = match($comentario['estado']) {
                'aprobado' => 'badge-aprobado',
                'pendiente' => 'badge-pendiente',
                'rechazado' => 'badge-rechazado',
                default => ''
            };
            $estado_badge = "<span class='badge $estado_class'>" . ucfirst($comentario['estado']) . "</span>";
        }
        
        // Construir acciones
        $acciones = '';
        if (estaLogueado() && ($_SESSION['usuario_id'] == $comentario['id_usuario'] || esAdmin())) {
            $acciones = '<div class="comentario-acciones">';
            if ($_SESSION['usuario_id'] == $comentario['id_usuario']) {
                $acciones .= "<a href='" . route('editar_comentario', ['id' => $comentario['id_comentario']]) . "' class='btn btn-small'>Editar</a>";
                $acciones .= "<form method='POST' action='" . route('eliminar_comentario') . "' style='display:inline' onsubmit='return confirm(\"¿Eliminar?\")'>";
                $acciones .= "<input type='hidden' name='csrf_token' value='" . $csrfTokenAcciones . "'>";
                $acciones .= "<input type='hidden' name='id_comentario' value='" . (int) $comentario['id_comentario'] . "'>";
                $acciones .= "<button type='submit' class='btn btn-small btn-eliminar'>Eliminar</button></form>";
            }
            if (esAdmin()) {
                if ($comentario['estado'] !== 'aprobado') {
                    $acciones .= "<form method='POST' action='" . route('admin_comentarios') . "' style='display:inline'>";
                    $acciones .= "<input type='hidden' name='csrf_token' value='" . $csrfTokenAcciones . "'>";
                    $acciones .= "<input type='hidden' name='accion' value='aprobar'>";
                    $acciones .= "<input type='hidden' name='id' value='" . (int) $comentario['id_comentario'] . "'>";
                    $acciones .= "<button type='submit' class='btn btn-small btn-success'>Aprobar</button></form>";
                }
                if ($comentario['estado'] !== 'rechazado') {
                    $acciones .= "<form method='POST' action='" . route('admin_comentarios') . "' style='display:inline'>";
                    $acciones .= "<input type='hidden' name='csrf_token' value='" . $csrfTokenAcciones . "'>";
                    $acciones .= "<input type='hidden' name='accion' value='rechazar'>";
                    $acciones .= "<input type='hidden' name='id' value='" . (int) $comentario['id_comentario'] . "'>";
                    $acciones .= "<button type='submit' class='btn btn-small btn-warning'>Rechazar</button></form>";
                }
            }
            $acciones .= '</div>';
        }
        
        $json_resultados[] = [
    'id' => $comentario['id_comentario'],
    'usuario_id' => $comentario['id_usuario'],  // ← AÑADIR ESTA LÍNEA
    'usuario_nombre' => htmlspecialchars($comentario['usuario_nombre']),
    'usuario_rol' => $comentario['usuario_rol'],
    'avatar' => base_url('uploads/perfiles/' . ($comentario['usuario_avatar'] ?? 'default-avatar.png')),
    'contenido' => nl2br(htmlspecialchars($comentario['contenido'], ENT_QUOTES, 'UTF-8')),
    'fecha' => formatearFecha($comentario['fecha_comentario']),
    'tiempo' => tiempoTranscurrido($comentario['fecha_comentario']),
    'noticia_titulo' => htmlspecialchars($comentario['noticia_titulo']),
    'noticia_url' => route('noticia', ['id' => $comentario['id_noticia']]),
    'estado' => $comentario['estado'],
    'estado_badge' => $estado_badge,
    'acciones' => $acciones
];
    }
    
    echo json_encode([
        'success' => true,
        'resultados' => $json_resultados,
        'total_resultados' => $total_resultados,
        'total_paginas' => $total_paginas,
        'pagina' => $pagina
    ]);
    
} catch (Exception $e) {
    registrarErrorInterno('AJAX.COMENTARIOS.BUSCAR', $e);
    echo json_encode([
        'error' => 'No se pudo realizar la búsqueda'
    ]);
}
?>
