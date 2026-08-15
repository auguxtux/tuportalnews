<?php
declare(strict_types=1);


/**
 * SISTEMA DE LOGS - REGISTRO DE ACTIVIDADES
 * Solo accesible para administradores
 * 
 * Muestra:
 * - Intentos de login fallidos
 * - Acciones administrativas (aprobaciones, cambios de rol, etc.)
 * - Actividad de usuarios
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/logs.php';

Permisos::requerirAdmin();

$pdo = db();

// Obtener filtros
$tipo_log = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta según tipo
$sql_base = "FROM login_attempts ";
$where = "WHERE 1=1";

if ($tipo_log === 'login') {
    $sql_base = "FROM login_attempts ";
    $where = "WHERE 1=1";
} elseif ($tipo_log === 'acciones') {
    $sql_base = "FROM log_acciones ";
} elseif ($tipo_log === 'bloqueos') {
    $sql_base = "FROM ips_bloqueadas ";
}

// Consulta de conteo
$sql_count = "SELECT COUNT(*) " . $sql_base . $where;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute();
$total = $stmt_count->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Consulta de datos
if ($tipo_log === 'login') {
    $sql = "SELECT id_attempt, email, ip, intentos, ultimo_intento, bloqueado_hasta 
            FROM login_attempts 
            ORDER BY ultimo_intento DESC 
            LIMIT :limit OFFSET :offset";
} elseif ($tipo_log === 'acciones') {
    $sql = "SELECT id_log, accion, ip_afectada, email_afectado, detalles, realizado_por, fecha 
            FROM log_acciones 
            ORDER BY fecha DESC 
            LIMIT :limit OFFSET :offset";
} elseif ($tipo_log === 'bloqueos') {
    $sql = "SELECT id_bloqueo, ip, motivo, bloqueado_por, fecha_bloqueo 
            FROM ips_bloqueadas 
            ORDER BY fecha_bloqueo DESC 
            LIMIT :limit OFFSET :offset";
} else {
    // Todos - unión de tablas
    $sql = "(SELECT 'login' as origen, id_attempt as id, email as usuario, ip, NULL as accion, NULL as detalles, NULL as realizado_por, ultimo_intento as fecha 
             FROM login_attempts)
            UNION ALL
            (SELECT 'accion' as origen, id_log as id, email_afectado as usuario, NULL as ip, accion, detalles, realizado_por, fecha 
             FROM log_acciones)
            UNION ALL
            (SELECT 'bloqueo' as origen, id_bloqueo as id, NULL as usuario, ip, motivo as accion, NULL as detalles, bloqueado_por as realizado_por, fecha_bloqueo as fecha 
             FROM ips_bloqueadas)
            ORDER BY fecha DESC 
            LIMIT :limit OFFSET :offset";
}

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

if ($tipo_log === 'todos') {
    $logs = $stmt->fetchAll();
} else {
    $logs = $stmt->fetchAll();
}

$titulo_pagina = 'Registro de Actividad';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-logs.css'); ?>">


<div class="logs-container">
    <div class="logs-header">
        <div class="logs-titulo-grupo">
            <h1>📋 Registro general</h1>
            <p>Consulta en un solo lugar la actividad, los accesos y los bloqueos.</p>
        </div>
        <nav class="logs-tabs" aria-label="Tipos de registro">
            <a href="?tipo=todos" class="logs-tab <?php echo $tipo_log === 'todos' ? 'active' : ''; ?>">📊 Todos</a>

            <a href="?tipo=login" class="logs-tab <?php echo $tipo_log === 'login' ? 'active' : ''; ?>">🔐 Intentos de Login</a>

            <a href="?tipo=acciones" class="logs-tab <?php echo $tipo_log === 'acciones' ? 'active' : ''; ?>">✏️ Acciones Admin</a>

            <a href="?tipo=bloqueos" class="logs-tab <?php echo $tipo_log === 'bloqueos' ? 'active' : ''; ?> logs-tab danger">🚫 IPs Bloqueadas</a>

        </nav>
    </div>

    <section class="logs-gestion" aria-labelledby="logs-gestion-titulo">
        <div>
            <h2 id="logs-gestion-titulo">🛡️ Conservación y limpieza</h2>
            <p>Las acciones se conservan durante <?php echo LOG_RETENTION_DAYS; ?> días. Los intentos de acceso se limpian desde Ataques y las IP bloqueadas permanentemente no se eliminan de forma automática.</p>
        </div>
        <div class="logs-gestion-acciones">
            <a href="<?php echo htmlspecialchars(route('admin_logs_activity'), ENT_QUOTES, 'UTF-8'); ?>" class="logs-gestion-btn">📋 Gestionar actividad</a>
            <a href="<?php echo htmlspecialchars(route('ataques'), ENT_QUOTES, 'UTF-8'); ?>" class="logs-gestion-btn logs-gestion-btn-alerta">🛡️ Gestionar ataques</a>
        </div>
    </section>

    <div class="logs-card">
        <?php if (empty($logs)): ?>

            <div class="logs-vacio">
                📭 No hay registros para mostrar
            </div>
        <?php else: ?>

            <div class="logs-tabla-contenedor">
                <table class="logs-tabla">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <?php if ($tipo_log === 'login'): ?>

                                <th>Email/Usuario</th>
                                <th>IP</th>
                                <th>Intentos</th>
                                <th>Estado</th>
                            <?php elseif ($tipo_log === 'todos'): ?>

                                <th>Usuario/IP</th>
                                <th>Tipo</th>
                                <th>Detalles</th>
                                <th>Responsable</th>
                            <?php else: ?>

                                <th>IP/Usuario</th>
                                <th>Acción</th>
                                <th>Detalles</th>
                                <th>Realizado por</th>
                            <?php endif; ?>

                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tipo_log === 'login'): ?>

                            <?php foreach ($logs as $log): ?>

                            <tr>
                                <td class="logs-fecha"><?php echo date('d/m/Y H:i:s', strtotime($log['ultimo_intento'])); ?></td>

                                <td><?php echo htmlspecialchars($log['email']); ?></td>

                                <td><code class="logs-ip"><?php echo htmlspecialchars($log['ip']); ?></code></td>

                                <td><?php echo $log['intentos']; ?></td>

                                <td>
                                    <?php if ($log['bloqueado_hasta'] && strtotime($log['bloqueado_hasta']) > time()): ?>

                                        <span class="logs-badge logs-badge-bloqueo">🔒 Bloqueado hasta <?php echo date('H:i:s', strtotime($log['bloqueado_hasta'])); ?></span>

                                    <?php elseif ($log['intentos'] >= 5): ?>

                                        <span class="logs-badge logs-badge-bloqueo">⚠️ Excedió intentos</span>
                                    <?php else: ?>

                                        <span class="logs-badge logs-badge-login">📝 Activo</span>
                                    <?php endif; ?>

                                </td>
                            </tr>
                            <?php endforeach; ?>

                        
                        <?php elseif ($tipo_log === 'acciones'): ?>

                            <?php foreach ($logs as $log): ?>

                            <tr>
                                <td class="logs-fecha"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>

                                <td><?php echo htmlspecialchars($log['email_afectado'] ?? $log['ip_afectada'] ?? '-'); ?></td>

                                <td><span class="logs-badge logs-badge-accion"><?php echo htmlspecialchars($log['accion']); ?></span></td>

                                <td><?php echo htmlspecialchars($log['detalles'] ?? '-'); ?></td>

                                <td><?php echo htmlspecialchars($log['realizado_por'] ?? 'Sistema'); ?></td>

                            </tr>
                            <?php endforeach; ?>

                        
                        <?php elseif ($tipo_log === 'bloqueos'): ?>

                            <?php foreach ($logs as $log): ?>

                            <tr>
                                <td class="logs-fecha"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha_bloqueo'])); ?></td>

                                <td><code class="logs-ip"><?php echo htmlspecialchars($log['ip']); ?></code></td>

                                <td><?php echo htmlspecialchars($log['motivo'] ?? 'Múltiples intentos fallidos'); ?></td>

                                <td><?php echo htmlspecialchars($log['detalles'] ?? '-'); ?></td>

                                <td><?php echo htmlspecialchars($log['bloqueado_por'] ?? 'Automático'); ?></td>

                            </tr>
                            <?php endforeach; ?>

                        
                        <?php else: // todos ?>

                            <?php foreach ($logs as $log): ?>

                            <tr>
                                <td class="logs-fecha"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>

                                <td>
                                    <?php if ($log['origen'] === 'login'): ?>

                                        <?php echo htmlspecialchars($log['usuario']); ?>

                                    <?php elseif ($log['origen'] === 'accion'): ?>

                                        <?php echo htmlspecialchars($log['usuario'] ?? '-'); ?>

                                    <?php else: ?>

                                        <code class="logs-ip"><?php echo htmlspecialchars($log['ip']); ?></code>

                                    <?php endif; ?>

                                </td>
                                <td>
                                    <?php if ($log['origen'] === 'login'): ?>

                                        <span class="logs-badge logs-badge-login">🔐 Intento de login</span>
                                    <?php elseif ($log['origen'] === 'accion'): ?>

                                        <span class="logs-badge logs-badge-accion">✏️ <?php echo htmlspecialchars($log['accion']); ?></span>

                                    <?php else: ?>

                                        <span class="logs-badge logs-badge-bloqueo">🚫 IP Bloqueada</span>
                                    <?php endif; ?>

                                </td>
                                <td>
                                    <?php if ($log['origen'] === 'login'): ?>

                                        Intentos: <?php echo $log['id']; ?> | IP: <?php echo htmlspecialchars($log['ip']); ?>

                                    <?php elseif ($log['origen'] === 'accion'): ?>

                                        <?php echo htmlspecialchars($log['detalles'] ?? '-'); ?>

                                    <?php else: ?>

                                        <?php echo htmlspecialchars($log['accion'] ?? 'Sin motivo'); ?>

                                    <?php endif; ?>

                                </td>
                                <td>
                                    <?php if ($log['origen'] === 'accion'): ?>

                                        <?php echo htmlspecialchars($log['realizado_por'] ?? 'Sistema'); ?>

                                    <?php else: ?>

                                        -
                                    <?php endif; ?>

                                </td>
                            </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>

    <div class="logs-pagination">
        <?php if ($pagina > 1): ?>

            <a href="?tipo=<?php echo $tipo_log; ?>&pagina=<?php echo $pagina - 1; ?>">← Anterior</a>

        <?php else: ?>

            <span class="disabled">← Anterior</span>
        <?php endif; ?>

        
        <span class="active">Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>

        
        <?php if ($pagina < $total_paginas): ?>

            <a href="?tipo=<?php echo $tipo_log; ?>&pagina=<?php echo $pagina + 1; ?>">Siguiente →</a>

        <?php else: ?>

            <span class="disabled">Siguiente →</span>
        <?php endif; ?>

    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
