<?php
declare(strict_types=1);


/**
 * ADMIN - VISUALIZACIÓN DE LOGS DE ACTIVIDAD
 * Solo accesible para administradores
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/logs.php';

Permisos::requerirAdmin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpiar_antiguos'])) {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        mensajeFlash('error', 'Error de seguridad. Recarga la página.');
    } else {
        try {
            $eliminados = aplicarRetencionLogsActividad($pdo);
            $eliminados += limpiarLogsActividadAntiguos(
                $pdo,
                LOG_RETENTION_DAYS,
                5000 - $eliminados
            );

            registrarLog(
                'limpiar_logs_actividad',
                null,
                null,
                "Registros antiguos eliminados: {$eliminados}"
            );

            $mensaje = $eliminados > 0
                ? "Se eliminaron {$eliminados} logs con más de " . LOG_RETENTION_DAYS . ' días.'
                : 'No había logs que superasen el periodo de conservación.';

            if ($eliminados >= 5000) {
                $mensaje .= ' Se alcanzó el límite seguro; puedes repetir la limpieza.';
            }

            mensajeFlash('success', $mensaje);
        } catch (Throwable $e) {
            error_log('[LOGS] Error en la limpieza manual de actividad.');
            mensajeFlash('error', 'No se pudo completar la limpieza de logs.');
        }
    }

    redireccionar(route('admin_logs_activity'));
}

// Filtros
$filtro_accion = isset($_GET['accion']) ? $_GET['accion'] : '';
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta
$where = [];
$params = [];

if ($filtro_accion) {
    $where[] = "accion LIKE :accion";
    $params[':accion'] = "%$filtro_accion%";
}

if ($filtro_usuario) {
    $where[] = "(realizado_por LIKE :usuario OR email_afectado LIKE :usuario)";
    $params[':usuario'] = "%$filtro_usuario%";
}

if ($filtro_fecha_desde) {
    $where[] = "DATE(fecha) >= :fecha_desde";
    $params[':fecha_desde'] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta) {
    $where[] = "DATE(fecha) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}

$sql_where = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM log_acciones $sql_where");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Datos
$sql = "SELECT * FROM log_acciones $sql_where ORDER BY fecha DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Obtener acciones disponibles para el filtro
$stmt_acciones = $pdo->query("SELECT DISTINCT accion FROM log_acciones ORDER BY accion");
$acciones_disponibles = $stmt_acciones->fetchAll(PDO::FETCH_COLUMN);

$titulo_pagina = 'Logs de Actividad';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-logs-activity.css'); ?>">


<div class="logs-container">
    <header class="logs-header">
        <h1>📋 Logs de actividad</h1>
        <p>Filtra y revisa las acciones registradas por el portal.</p>
    </header>

    <div class="logs-retencion">
        <div>
            <strong>🗓️ Conservación: <?php echo LOG_RETENTION_DAYS; ?> días</strong>
            <span>La limpieza afecta solo al historial de actividad, no a intentos de acceso ni IP bloqueadas.</span>
        </div>
        <form method="POST" onsubmit="return confirm('¿Eliminar los logs de actividad con más de <?php echo LOG_RETENTION_DAYS; ?> días?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" name="limpiar_antiguos" value="1" class="btn-limpiar-antiguos">🧹 Eliminar logs antiguos</button>
        </form>
    </div>
    
    <!-- Filtros -->
    <form method="GET" class="logs-filtros">
        <div class="logs-filtro-grupo logs-filtro-acciones">
            <label>🔍 Acción</label>
            <select name="accion">
                <option value="">Todas</option>
                <?php foreach ($acciones_disponibles as $acc): ?>

                    <option value="<?php echo htmlspecialchars($acc); ?>" <?php echo $filtro_accion === $acc ? 'selected' : ''; ?>>

                        <?php echo htmlspecialchars($acc); ?>

                    </option>
                <?php endforeach; ?>

            </select>
        </div>
        
        <div class="logs-filtro-grupo">
            <label>👤 Usuario</label>
            <input type="text" name="usuario" value="<?php echo htmlspecialchars($filtro_usuario); ?>" placeholder="Email o nombre...">

        </div>
        
        <div class="logs-filtro-grupo">
            <label>📅 Desde</label>
            <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde, ENT_QUOTES, 'UTF-8'); ?>">

        </div>
        
        <div class="logs-filtro-grupo">
            <label>📅 Hasta</label>
            <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta, ENT_QUOTES, 'UTF-8'); ?>">

        </div>
        
        <div class="logs-filtro-grupo">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="<?php echo htmlspecialchars(route('admin_logs_activity'), ENT_QUOTES, 'UTF-8'); ?>" class="btn-limpiar">↺ Quitar filtros</a>
        </div>
    </form>
    
    <!-- Tabla de logs -->
    <div class="logs-tabla-contenedor">
        <table class="logs-tabla">
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Acción</th>
                    <th>Realizado por</th>
                    <th>IP Afectada</th>
                    <th>Email Afectado</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>

                    <tr><td colspan="6" class="logs-vacio">No hay registros</td></tr>
                <?php else: ?>

                    <?php foreach ($logs as $log): ?>

                        <tr>
                            <td class="logs-fecha"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>

                            <td>
                                <?php

                                $badge_class = 'logs-badge';
                                if (strpos($log['accion'], 'login') !== false) $badge_class .= ' logs-badge-login';
                                elseif (strpos($log['accion'], 'noticia') !== false) $badge_class .= ' logs-badge-noticia';
                                elseif (strpos($log['accion'], 'usuario') !== false) $badge_class .= ' logs-badge-usuario';
                                elseif (strpos($log['accion'], 'ip') !== false) $badge_class .= ' logs-badge-ip';
                                ?>
                                <span class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['accion']); ?></span>

                            </td>
                            <td><?php echo htmlspecialchars($log['realizado_por']); ?></td>

                            <td><?php echo htmlspecialchars($log['ip_afectada'] ?? '-'); ?></td>

                            <td><?php echo htmlspecialchars($log['email_afectado'] ?? '-'); ?></td>

                            <td class="logs-detalles"><?php echo htmlspecialchars($log['detalles'] ?? '-'); ?></td>

                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>

        <div class="logs-paginacion">
            <?php if ($pagina > 1): ?>

                <a href="<?php echo htmlspecialchars(route('admin_logs_activity', [
                    'pagina' => $pagina - 1,
                    'accion' => $filtro_accion,
                    'usuario' => $filtro_usuario,
                    'fecha_desde' => $filtro_fecha_desde,
                    'fecha_hasta' => $filtro_fecha_hasta,
                ]), ENT_QUOTES, 'UTF-8'); ?>">« Anterior</a>

            <?php endif; ?>

            
            <span class="active"><?php echo $pagina; ?> / <?php echo $total_paginas; ?></span>

            
            <?php if ($pagina < $total_paginas): ?>

                <a href="<?php echo htmlspecialchars(route('admin_logs_activity', [
                    'pagina' => $pagina + 1,
                    'accion' => $filtro_accion,
                    'usuario' => $filtro_usuario,
                    'fecha_desde' => $filtro_fecha_desde,
                    'fecha_hasta' => $filtro_fecha_hasta,
                ]), ENT_QUOTES, 'UTF-8'); ?>">Siguiente »</a>

            <?php endif; ?>

        </div>
    <?php endif; ?>

    
    <div class="logs-total">
        📊 Total de registros: <?php echo number_format($total, 0, ',', '.'); ?>

    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
