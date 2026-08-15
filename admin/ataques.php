<?php
declare(strict_types=1);


/**
 * MONITORIZACIÓN DE ATAQUES DE FUERZA BRUTA
 * CON BLOQUEO PERMANENTE DE IPs Y FUNCIONALIDADES AMPLIADAS
 * 
 * FUNCIONALIDADES INCLUIDAS:
 * ✅ 1. Búsqueda/filtro por IP o email
 * ✅ 2. Geolocalización de IPs (top países)
 * ✅ 3. Tasa de ataques por minuto (detección de picos)
 * ✅ 4. Log de acciones (quién bloqueó/desbloqueó)
 * ✅ 5. Sugerencia de auto-limpieza de registros antiguos
 * ✅ 6. Bloqueo automático por tasa de intentos
 * ✅ 7. Protección CSRF en acciones
 * ✅ 8. Paginación en últimos intentos
 * ✅ 9. Exportación a CSV
 * ✅ 10. Limpieza automática periódica
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

// Solo administradores pueden acceder
Permisos::requerirAdmin();

$pdo = db();
$mensaje = '';
$error = '';

// ============================================
// FUNCIONES AUXILIARES
// ============================================
function registrarAccion(PDO $pdo, string $accion, ?string $ip = null, ?string $email = null, string $detalles = ''): void {
    $stmt = $pdo->prepare("INSERT INTO log_acciones (accion, ip_afectada, email_afectado, detalles, realizado_por) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$accion, $ip, $email, $detalles, $_SESSION['usuario_nombre'] ?? 'Admin']);
}

function normalizarIPAtaques(string $ip): ?string {
    $ip = trim($ip);
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
}

function geolocalizarIP(string $ip): string {
    $ip = normalizarIPAtaques($ip);
    if ($ip === null) {
        return '🌍 Desconocido';
    }

    $ip_publica = filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
    if ($ip_publica === false) {
        return '🏠 Red local';
    }
    
    // Caché acotada en sesión para no consultar el proveedor en cada carga.
    $ahora = time();
    $cache = $_SESSION['cache_geolocalizacion_ips'] ?? [];
    if (
        isset($cache[$ip]['pais'], $cache[$ip]['caduca'])
        && (int) $cache[$ip]['caduca'] >= $ahora
    ) {
        return (string) $cache[$ip]['pais'];
    }

    $pais = '🌍 Desconocido';

    $contexto = stream_context_create([
        'http' => [
            'timeout' => 3,
            'ignore_errors' => false,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    
    // Geolocalización HTTPS sin credenciales mediante ipwho.is
    $response = @file_get_contents(
        'https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,message',
        false,
        $contexto
    );
    if ($response !== false) {
        $data = json_decode($response, true);
        if (is_array($data) && ($data['success'] ?? false) === true && !empty($data['country'])) {
            $pais = (string) $data['country'];
        }
    }

    $cache[$ip] = [
        'pais' => $pais,
        'caduca' => $ahora + 86400,
    ];
    if (count($cache) > 200) {
        uasort($cache, static fn(array $a, array $b): int => $a['caduca'] <=> $b['caduca']);
        $cache = array_slice($cache, -200, null, true);
    }
    $_SESSION['cache_geolocalizacion_ips'] = $cache;

    return $pais;
}

// ============================================
// GENERAR TOKEN CSRF
// ============================================
if (empty($_SESSION['csrf_token_ataques'])) {
    $_SESSION['csrf_token_ataques'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_ataques'];

// ============================================
// PROCESAR ACCIONES POST (CSRF protegidas)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion_post = isset($_POST['accion']) ? $_POST['accion'] : '';
    $token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!is_string($token_post) || !hash_equals($csrf_token, $token_post)) {
        $error = "❌ Error de validación CSRF";
    } else {
        try {
            if ($accion_post === 'auto_limpiar') {
                $dias = isset($_POST['dias']) ? (int)$_POST['dias'] : 30;
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$dias]);
                $afectados = $stmt->rowCount();
                $mensaje = "✅ Se eliminaron $afectados registros con más de $dias días de antigüedad";
                registrarAccion($pdo, 'auto_limpiar', null, null, "Días: $dias, Eliminados: $afectados");
            }
            
            if ($accion_post === 'limpiar_todo') {
                $pdo->exec("DELETE FROM login_attempts");
                $mensaje = "✅ Todos los registros han sido eliminados";
                registrarAccion($pdo, 'limpiar_todo');
            }
            
            if ($accion_post === 'bloquear_ip' && isset($_POST['ip'])) {
                $ip = normalizarIPAtaques((string)$_POST['ip']);
                $motivo = $_POST['motivo'] ?? 'Bloqueado por administrador';

                if ($ip === null) {
                    $error = '❌ Dirección IP no válida';
                } else {
                    $stmt = $pdo->prepare("SELECT id_bloqueo FROM ips_bloqueadas WHERE ip = ?");
                    $stmt->execute([$ip]);

                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO ips_bloqueadas (ip, motivo, bloqueado_por) VALUES (?, ?, ?)");
                        $stmt->execute([$ip, $motivo, $_SESSION['usuario_nombre'] ?? 'Admin']);
                        $mensaje = "✅ IP $ip bloqueada permanentemente";
                        registrarAccion($pdo, 'bloquear_ip', $ip, null, $motivo);
                    } else {
                        $mensaje = "ℹ️ La IP $ip ya estaba bloqueada";
                    }
                }
            }

            if ($accion_post === 'desbloquear_ip' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("SELECT ip FROM ips_bloqueadas WHERE id_bloqueo = ?");
                $stmt->execute([$id]);
                $ip = $stmt->fetchColumn();

                if ($ip) {
                    $stmt = $pdo->prepare("DELETE FROM ips_bloqueadas WHERE id_bloqueo = ?");
                    $stmt->execute([$id]);
                    $mensaje = "✅ IP $ip desbloqueada";
                    registrarAccion($pdo, 'desbloquear_ip', $ip);
                }
            }

            if ($accion_post === 'desbloquear_intento' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE login_attempts SET bloqueado_hasta = NULL WHERE id_attempt = ?");
                $stmt->execute([$id]);
                $mensaje = '✅ Registro desbloqueado correctamente';
                registrarAccion($pdo, 'desbloquear_intento', null, null, "ID: $id");
            }

            if ($accion_post === 'bloquear_desde_intento' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("SELECT ip FROM login_attempts WHERE id_attempt = ?");
                $stmt->execute([$id]);
                $ip = normalizarIPAtaques((string)$stmt->fetchColumn());

                if ($ip !== null) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO ips_bloqueadas (ip, motivo, bloqueado_por) VALUES (?, ?, ?)");
                    $stmt->execute([$ip, 'Bloqueado por intentos fallidos', $_SESSION['usuario_nombre'] ?? 'Admin']);
                    $mensaje = "✅ IP $ip bloqueada permanentemente";
                    registrarAccion($pdo, 'bloquear_ip', $ip);
                } else {
                    $error = '❌ Dirección IP no válida';
                }
            }

            if ($accion_post === 'limpiar_ip' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("SELECT ip FROM login_attempts WHERE id_attempt = ?");
                $stmt->execute([$id]);
                $ip = normalizarIPAtaques((string)$stmt->fetchColumn());

                if ($ip !== null) {
                    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?");
                    $stmt->execute([$ip]);
                    $mensaje = "✅ Todos los intentos de la IP $ip han sido eliminados";
                    registrarAccion($pdo, 'limpiar_ip', $ip);
                } else {
                    $error = '❌ Dirección IP no válida';
                }
            }

            if ($accion_post === 'limpiar_email' && isset($_POST['email'])) {
                $email = $_POST['email'];
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
                $stmt->execute([$email]);
                $mensaje = "✅ Todos los intentos del email $email han sido eliminados";
                registrarAccion($pdo, 'limpiar_email', null, $email);
            }
            
            if ($accion_post === 'exportar_csv') {
                // Exportar CSV
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="ataques_' . date('Y-m-d') . '.csv"');
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'IP', 'Email', 'Intentos', 'Último intento', 'Bloqueado hasta']);
                
                $stmt = $pdo->query("SELECT id_attempt, ip, email, intentos, ultimo_intento, bloqueado_hasta FROM login_attempts ORDER BY ultimo_intento DESC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit;
            }
            
        } catch (Throwable $e) {
            $error = '❌ No se pudo procesar la acción.';
            registrarErrorInterno('ADMIN.ATAQUES.ACCION', $e);
        }
    }
    
    if (empty($error) && $mensaje) {
        header('Location: ' . route('ataques', ['msg' => $mensaje]));
        exit;
    }
}

// ============================================
// OBTENER DATOS
// ============================================

$busqueda = isset($_GET['busqueda']) ? limpiarDatos($_GET['busqueda']) : '';
$filtro_tipo = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 20;
$offset = ($pagina_actual - 1) * $por_pagina;

// IPs bloqueadas permanentemente
$ips_bloqueadas = $pdo->query("
    SELECT * FROM ips_bloqueadas ORDER BY fecha_bloqueo DESC
")->fetchAll();

// Estadísticas generales
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
$stats['bloqueados'] = $pdo->query("SELECT COUNT(*) FROM login_attempts 
                                    WHERE bloqueado_hasta IS NOT NULL 
                                    AND bloqueado_hasta > NOW()")->fetchColumn();
$stats['hoy'] = $pdo->query("SELECT COUNT(*) FROM login_attempts 
                             WHERE DATE(ultimo_intento) = CURDATE()")->fetchColumn();
$stats['ips_bloqueadas'] = count($ips_bloqueadas);
$stats['antiguos'] = $pdo->query("SELECT COUNT(*) FROM login_attempts 
                                  WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$stats['tasa_5min'] = $pdo->query("SELECT COUNT(*) FROM login_attempts 
                                   WHERE ultimo_intento > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();

// IPs públicas bloqueadas dinámicamente por intentar acceder a varias cuentas.
$ventana_ataque_distribuido = (int)LOGIN_VENTANA_IP_MINUTOS;
$max_emails_por_ip = (int)LOGIN_MAX_EMAILS_POR_IP;
$intentos_minimos_ataque = (int)LOGIN_MAX_INTENTOS;
$candidatos_ataque_distribuido = $pdo->query("
    SELECT ip, COUNT(DISTINCT email) AS emails_distintos, MAX(ultimo_intento) AS ultimo_intento
    FROM login_attempts
    WHERE ultimo_intento >= DATE_SUB(NOW(), INTERVAL {$ventana_ataque_distribuido} MINUTE)
    GROUP BY ip
    HAVING COUNT(DISTINCT email) >= {$max_emails_por_ip}
    ORDER BY emails_distintos DESC, ultimo_intento DESC
")->fetchAll();

$ataques_distribuidos = [];
$ips_ataque_distribuido = [];
foreach ($candidatos_ataque_distribuido as $candidato) {
    $ip_publica = filter_var(
        $candidato['ip'],
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
    if ($ip_publica !== false && $candidato['ip'] !== '0.0.0.0') {
        $ataques_distribuidos[] = $candidato;
        $ips_ataque_distribuido[$candidato['ip']] = true;
    }
}

// Ataques probables: umbral de fuerza bruta alcanzado o IP bloqueada.
$top_ips = $pdo->query("
    SELECT la.ip, SUM(la.intentos) AS total,
           COUNT(DISTINCT la.email) AS emails_distintos,
           SUM(CASE WHEN la.bloqueado_hasta > NOW() THEN 1 ELSE 0 END) AS bloqueadas
    FROM login_attempts la
    LEFT JOIN ips_bloqueadas ib ON ib.ip = la.ip
    GROUP BY la.ip
    HAVING MAX(la.intentos) >= {$intentos_minimos_ataque}
        OR SUM(CASE WHEN la.bloqueado_hasta > NOW() THEN 1 ELSE 0 END) > 0
        OR COUNT(ib.id_bloqueo) > 0
        OR COUNT(DISTINCT CASE
            WHEN la.ultimo_intento >= DATE_SUB(NOW(), INTERVAL {$ventana_ataque_distribuido} MINUTE)
            THEN la.email END) >= {$max_emails_por_ip}
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

// Correos afectados exclusivamente por actividad que alcanzó un umbral real.
$top_emails = $pdo->query("
    SELECT la.email, SUM(la.intentos) AS total,
           SUM(CASE WHEN la.bloqueado_hasta > NOW() THEN 1 ELSE 0 END) AS bloqueados
    FROM login_attempts la
    LEFT JOIN ips_bloqueadas ib ON ib.ip = la.ip
    WHERE la.intentos >= {$intentos_minimos_ataque}
       OR la.bloqueado_hasta > NOW()
       OR ib.id_bloqueo IS NOT NULL
       OR la.ip IN (
            SELECT recientes.ip
            FROM login_attempts recientes
            WHERE recientes.ultimo_intento >= DATE_SUB(NOW(), INTERVAL {$ventana_ataque_distribuido} MINUTE)
            GROUP BY recientes.ip
            HAVING COUNT(DISTINCT recientes.email) >= {$max_emails_por_ip}
       )
    GROUP BY la.email
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

$stats['ataques_probables'] = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT la.ip
        FROM login_attempts la
        LEFT JOIN ips_bloqueadas ib ON ib.ip = la.ip
        GROUP BY la.ip
        HAVING MAX(la.intentos) >= {$intentos_minimos_ataque}
            OR SUM(CASE WHEN la.bloqueado_hasta > NOW() THEN 1 ELSE 0 END) > 0
            OR COUNT(ib.id_bloqueo) > 0
            OR COUNT(DISTINCT CASE
                WHEN la.ultimo_intento >= DATE_SUB(NOW(), INTERVAL {$ventana_ataque_distribuido} MINUTE)
                THEN la.email END) >= {$max_emails_por_ip}
    ) ataques
")->fetchColumn();

// Top países
$paises = [];
foreach ($top_ips as $ip_data) {
    $pais = geolocalizarIP($ip_data['ip']);
    if (!isset($paises[$pais])) $paises[$pais] = 0;
    $paises[$pais] += $ip_data['total'];
}
arsort($paises);
$top_paises = array_slice($paises, 0, 5);

// Total registros para paginación
$sql_total = "SELECT COUNT(*) FROM login_attempts";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute();
$total_registros = $stmt_total->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Últimos intentos (con paginación y búsqueda)
$sql_ultimos = "SELECT * FROM login_attempts";
$params_ultimos = [];
if ($busqueda) {
    if ($filtro_tipo === 'ip') {
        $sql_ultimos .= " WHERE ip LIKE :busqueda";
        $params_ultimos[':busqueda'] = "%$busqueda%";
    } elseif ($filtro_tipo === 'email') {
        $sql_ultimos .= " WHERE email LIKE :busqueda";
        $params_ultimos[':busqueda'] = "%$busqueda%";
    } else {
        $sql_ultimos .= " WHERE ip LIKE :busqueda OR email LIKE :busqueda";
        $params_ultimos[':busqueda'] = "%$busqueda%";
    }
}
$sql_ultimos .= " ORDER BY ultimo_intento DESC LIMIT $por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql_ultimos);
$stmt->execute($params_ultimos);
$ultimos = $stmt->fetchAll();

// Estadísticas por hora
$por_hora = $pdo->query("
    SELECT HOUR(ultimo_intento) as hora, COUNT(*) as total
    FROM login_attempts 
    WHERE ultimo_intento > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY HOUR(ultimo_intento)
    ORDER BY hora
")->fetchAll();

$horas = array_fill(0, 24, 0);
foreach ($por_hora as $h) {
    $horas[$h['hora']] = $h['total'];
}

// Log de acciones
$log_acciones = $pdo->query("
    SELECT * FROM log_acciones ORDER BY fecha DESC LIMIT 20
")->fetchAll();

$titulo_pagina = 'Monitor de Ataques';
require_once __DIR__ . '/../partials/header.php';

if (is_string($_GET['msg'] ?? null) && $_GET['msg'] !== '') {
    echo '<div class="atk-alerta atk-alerta-success">' . htmlspecialchars($_GET['msg']) . '</div>';
}
if ($error) {
    echo '<div class="atk-alerta atk-alerta-error">' . $error . '</div>';
}
?>
<link rel="stylesheet" href="<?php echo css_url('admin-ataques.css'); ?>">




<div class="atk-container">
    <h1 class="atk-titulo">🛡️ Monitor de Ataques de Fuerza Bruta</h1>
    <p class="atk-descripcion">Panel de monitoreo, bloqueo, geolocalización y auditoría de intentos de acceso no autorizados.</p>
    
    <!-- SECCIÓN 1: ESTADÍSTICAS GENERALES -->
    <div class="atk-seccion">
        <div class="atk-seccion-header">
            <h2>📊 Estadísticas Generales</h2>
        </div>
        <div class="atk-grid-4">
            <div class="atk-stat-card"><div class="atk-stat-icono">📊</div><div class="atk-stat-datos"><span class="atk-stat-valor"><?php echo $stats['total']; ?></span><span class="atk-stat-etiqueta">Registros observados</span></div></div>

            <div class="atk-stat-card"><div class="atk-stat-icono">🔴</div><div class="atk-stat-datos"><span class="atk-stat-valor"><?php echo $stats['bloqueados']; ?></span><span class="atk-stat-etiqueta">Bloqueados ahora</span></div></div>

            <div class="atk-stat-card"><div class="atk-stat-icono">🚨</div><div class="atk-stat-datos"><span class="atk-stat-valor"><?php echo $stats['ataques_probables']; ?></span><span class="atk-stat-etiqueta">Ataques probables</span></div></div>

            <div class="atk-stat-card"><div class="atk-stat-icono">🚫</div><div class="atk-stat-datos"><span class="atk-stat-valor"><?php echo $stats['ips_bloqueadas']; ?></span><span class="atk-stat-etiqueta">IPs bloqueadas</span></div></div>

        </div>
        <?php if ($stats['tasa_5min'] > 10): ?>

            <div class="atk-alerta atk-alerta-warning">⚠️ <strong>Pico de actividad:</strong> <?php echo $stats['tasa_5min']; ?> intentos en los últimos 5 minutos.</div>

        <?php endif; ?>

        <?php if (!empty($ataques_distribuidos)): ?>

            <div class="atk-alerta atk-alerta-error">
                🚨 <strong>Ataque distribuido detectado:</strong>
                bloqueo automático activo durante la ventana de <?php echo $ventana_ataque_distribuido; ?> minutos.
                <?php foreach ($ataques_distribuidos as $ataque): ?>
                    <div>
                        IP <code><?php echo htmlspecialchars($ataque['ip']); ?></code> ·
                        <?php echo (int)$ataque['emails_distintos']; ?> cuentas distintas ·
                        último intento <?php echo date('d/m/Y H:i', strtotime($ataque['ultimo_intento'])); ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <?php if ($stats['antiguos'] > 100): ?>

            <div class="atk-alerta atk-alerta-info">💡 <strong>Sugerencia:</strong> Hay <?php echo $stats['antiguos']; ?> registros con más de 30 días. 

                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <input type="hidden" name="accion" value="auto_limpiar">
                    <input type="hidden" name="dias" value="30">
                    <button type="submit" class="atk-btn atk-btn-small" onclick="return confirm('¿Eliminar registros antiguos?')">🧹 Limpiar ahora</button>
                </form>
            </div>
        <?php endif; ?>

    </div>
    
    <!-- SECCIÓN 2: TOP PAÍSES Y LOG -->
    <div class="atk-grid-2">
        <div class="atk-seccion">
            <div class="atk-seccion-header"><h2>🌍 Países con ataques probables</h2></div>
            <div class="atk-card"><div class="atk-tabla-responsive"><table class="atk-tabla"><thead><tr><th>País</th><th>Intentos</th></tr></thead><tbody>
            <?php foreach ($top_paises as $pais => $total): ?><tr><td><?php echo htmlspecialchars($pais); ?></td><td class="atk-centrar"><?php echo $total; ?></td></tr><?php endforeach; ?>

            </tbody></table></div></div>
        </div>
        <div class="atk-seccion">
            <div class="atk-seccion-header"><h2>📝 Últimas Acciones</h2></div>
            <div class="atk-card"><div class="atk-tabla-responsive" style="max-height: 300px; overflow-y: auto;"><table class="atk-tabla"><thead><tr><th>Acción</th><th>Detalle</th><th>Fecha</th></tr></thead><tbody>
            <?php foreach ($log_acciones as $log): ?><tr><td><?php echo htmlspecialchars($log['accion']); ?></td><td style="font-size: 0.75rem;"><?php echo $log['ip_afectada'] ? "IP: " . htmlspecialchars($log['ip_afectada']) : ''; echo $log['email_afectado'] ? " Email: " . htmlspecialchars($log['email_afectado']) : ''; echo $log['detalles'] ? " - " . htmlspecialchars($log['detalles']) : ''; ?></td><td style="font-size: 0.7rem;"><?php echo date('d/m H:i', strtotime($log['fecha'])); ?></td></tr><?php endforeach; ?>

            </tbody></table></div></div>
        </div>
    </div>
    
    <!-- SECCIÓN 3: ACTIVIDAD ÚLTIMAS 24H -->
    <div class="atk-seccion">
        <div class="atk-seccion-header"><h2>📈 Actividad en las últimas 24 horas</h2></div>
        <div class="atk-card atk-grafico-card"><div class="atk-barras-actividad">
        <?php for ($h = 0; $h < 24; $h++): $altura = min(120, 10 + $horas[$h] * 2); ?>

            <div class="atk-barra-dia"><div class="atk-barra" style="height: <?php echo $altura; ?>px;"></div><span class="atk-dia"><?php echo str_pad((string) $h, 2, '0', STR_PAD_LEFT); ?></span><span class="atk-valor"><?php echo $horas[$h]; ?></span></div>

        <?php endfor; ?>

        </div></div>
    </div>
    
    <!-- SECCIÓN 4: IPs BLOQUEADAS PERMANENTEMENTE -->
    <div class="atk-seccion">
        <div class="atk-seccion-header"><h2>🚫 IPs Bloqueadas Permanentemente</h2></div>
        <?php if (empty($ips_bloqueadas)): ?>

            <div class="atk-card"><p class="atk-sin-datos">✅ No hay IPs bloqueadas permanentemente</p></div>
        <?php else: ?>

            <div class="atk-grid-ips">
                <?php foreach ($ips_bloqueadas as $ip): ?>

                <div class="atk-ip-card">
                    <div class="atk-ip-card-header"><code class="atk-ip"><?php echo htmlspecialchars($ip['ip']); ?></code></div>

                    <div class="atk-ip-card-body"><p><strong>Motivo:</strong> <?php echo htmlspecialchars($ip['motivo']); ?></p><p><strong>Bloqueado por:</strong> <?php echo htmlspecialchars($ip['bloqueado_por']); ?></p><p><strong>Fecha:</strong> <?php echo formatearFecha($ip['fecha_bloqueo']); ?></p></div>

                    <div class="atk-ip-card-footer"><form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="desbloquear_ip"><input type="hidden" name="id" value="<?php echo $ip['id_bloqueo']; ?>"><button type="submit" class="atk-btn atk-btn-success atk-btn-small" onclick="return confirm('¿Desbloquear esta IP?')">🔓 Desbloquear</button></form></div>

                </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>
    
    <!-- SECCIÓN 5: TOP IPs y EMAILs -->
    <div class="atk-grid-2">
        <div class="atk-seccion">
            <div class="atk-seccion-header"><h2>🚨 IPs con ataques probables</h2></div>
            <div class="atk-card"><div class="atk-tabla-responsive"><table class="atk-tabla"><thead><tr><th>IP</th><th>Intentos</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($top_ips as $ip): ?><tr><td><code><?php echo htmlspecialchars($ip['ip']); ?></code></td><td class="atk-centrar"><?php echo $ip['total']; ?></td><td><form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="bloquear_ip"><input type="hidden" name="ip" value="<?php echo htmlspecialchars($ip['ip']); ?>"><input type="hidden" name="motivo" value="Ataque probable"><button type="submit" class="atk-btn atk-btn-warning atk-btn-small" onclick="return confirm('¿Bloquear permanentemente esta IP?')">🚫 Bloquear</button></form></td></tr><?php endforeach; ?>

            <?php if (empty($top_ips)): ?><tr><td colspan="3" class="atk-centrar">✅ No hay ataques probables detectados</td></tr><?php endif; ?>

            </tbody></table></div></div>
        </div>
        <div class="atk-seccion">
            <div class="atk-seccion-header"><h2>📧 Correos afectados por ataques probables</h2></div>
            <div class="atk-card"><div class="atk-tabla-responsive"><table class="atk-tabla"><thead><tr><th>Email</th><th>Intentos</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($top_emails as $email): ?><tr><td><?php echo htmlspecialchars($email['email']); ?></td><td class="atk-centrar"><?php echo $email['total']; ?></td><td><form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="limpiar_email"><input type="hidden" name="email" value="<?php echo htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="atk-btn atk-btn-secondary atk-btn-small" onclick="return confirm('¿Eliminar todos los intentos para este email?')">🧹 Limpiar</button></form></td></tr><?php endforeach; ?>

            <?php if (empty($top_emails)): ?><tr><td colspan="3" class="atk-centrar">✅ Ningún correo alcanzó el umbral de ataque</td></tr><?php endif; ?>

            </tbody></table></div></div>
        </div>
    </div>
    
    <!-- SECCIÓN 6: BÚSQUEDA -->
    <div class="atk-seccion">
        <div class="atk-seccion-header"><h2>🔍 Buscar Intentos</h2></div>
        <div class="atk-card">
            <form method="GET" class="atk-busqueda-form">
                <div class="atk-busqueda-grid">
                    <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por IP o email..." class="atk-input">

                    <select name="filtro" class="atk-select"><option value="todos" <?php echo $filtro_tipo === 'todos' ? 'selected' : ''; ?>>Todo</option><option value="ip" <?php echo $filtro_tipo === 'ip' ? 'selected' : ''; ?>>Solo IP</option><option value="email" <?php echo $filtro_tipo === 'email' ? 'selected' : ''; ?>>Solo Email</option></select>

                    <button type="submit" class="atk-btn atk-btn-primary">🔍 Buscar</button>
                    <?php if ($busqueda): ?><a href="<?php echo htmlspecialchars(route('ataques'), ENT_QUOTES, 'UTF-8'); ?>" class="atk-btn atk-btn-secondary">✕ Limpiar</a><?php endif; ?>

                </div>
            </form>
            <?php if ($busqueda): ?><p style="margin-top: 0.5rem; font-size: 0.8rem;">Mostrando resultados para: <strong><?php echo htmlspecialchars($busqueda); ?></strong> (<?php echo count($ultimos); ?> resultados)</p><?php endif; ?>

        </div>
    </div>
    
    <!-- SECCIÓN 7: ÚLTIMOS INTENTOS -->
    <div class="atk-seccion">
        <div class="atk-seccion-header"><h2>📋 Historial de intentos fallidos <?php echo $busqueda ? '(filtrados)' : ''; ?></h2></div>

        <div class="atk-alerta atk-alerta-info">
            <strong style="text-align: center;display: block;">ℹ️ Funcionamiento de las acciones:</strong><br>
            🚫 bloquea permanentemente la IP hasta que un administrador la desbloquee;<br>
            🧹 elimina del historial todos los intentos de esa IP, pero no la bloquea ni la desbloquea;<br>
            🔓 retira el bloqueo temporal del registro.
            Los intentos aislados se conservan aquí para diagnóstico, pero no se contabilizan como ataques probables.<br>
            Se considera ataque probable al alcanzar <?php echo $intentos_minimos_ataque; ?> intentos sobre una cuenta,
            existir un bloqueo o probar <?php echo $max_emails_por_ip; ?> cuentas distintas en
            <?php echo $ventana_ataque_distribuido; ?> minutos.
            El aviso <br>🚨 indica un bloqueo automático cuando una IP pública intenta acceder a
            <?php echo $max_emails_por_ip; ?> cuentas distintas durante <?php echo $ventana_ataque_distribuido; ?> minutos.
            Limpiar sus intentos puede hacer que deje de cumplirse esa condición automática.
        </div>

        <?php if (empty($ultimos)): ?>

            <div class="atk-card"><p class="atk-sin-datos"><?php echo $busqueda ? '🔍 No se encontraron resultados' : '🎉 No hay intentos de ataque registrados'; ?></p></div>

        <?php else: ?>

            <div class="atk-ultimos-grid">
                <?php foreach ($ultimos as $intento): $bloqueado = $intento['bloqueado_hasta'] && strtotime($intento['bloqueado_hasta']) > time(); $ataque_distribuido = isset($ips_ataque_distribuido[$intento['ip']]); $ataque_probable = $ataque_distribuido || $bloqueado || (int)$intento['intentos'] >= $intentos_minimos_ataque; $pais = geolocalizarIP($intento['ip']); ?>

                    <div class="atk-ultimo-card <?php echo $ataque_probable ? 'atk-card-bloqueada' : ''; ?>">

                        <div class="atk-ultimo-header"><div class="atk-ultimo-ip"><span>🌐</span> <code><?php echo htmlspecialchars($intento['ip']); ?></code> <span style="font-size:0.65rem;"><?php echo $pais; ?></span></div><div class="atk-ultimo-id">#<?php echo $intento['id_attempt']; ?></div></div>

                        <div class="atk-ultimo-email">📧 <?php echo htmlspecialchars($intento['email']); ?></div>

                        <div class="atk-ultimo-stats"><div class="atk-ultimo-stat"><span class="atk-stat-numero"><?php echo $intento['intentos']; ?></span><span class="atk-stat-texto">intentos</span></div><div class="atk-ultimo-stat"><span class="atk-stat-numero"><?php echo date('H:i', strtotime($intento['ultimo_intento'])); ?></span><span class="atk-stat-texto">último</span></div></div>

                        <div class="atk-ultimo-fecha">📅 <?php echo date('d/m/Y H:i', strtotime($intento['ultimo_intento'])); ?></div>

                        <div class="atk-ultimo-estado"><?php if ($ataque_distribuido): ?><span class="atk-badge atk-badge-pendiente">🚨 Bloqueo automático activo</span><?php elseif ($bloqueado): ?><span class="atk-badge atk-badge-pendiente">🔒 Hasta <?php echo date('H:i d/m', strtotime($intento['bloqueado_hasta'])); ?></span><?php elseif ($ataque_probable): ?><span class="atk-badge atk-badge-pendiente">⚠️ Ataque probable</span><?php else: ?><span class="atk-badge atk-badge-activo">ℹ️ Intento aislado</span><?php endif; ?></div>

                        <div class="atk-ultimo-acciones">
                            <?php if ($bloqueado): ?><form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="desbloquear_intento"><input type="hidden" name="id" value="<?php echo $intento['id_attempt']; ?>"><button type="submit" class="atk-btn atk-btn-success atk-btn-small">🔓</button></form><?php endif; ?>

                            <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="bloquear_desde_intento"><input type="hidden" name="id" value="<?php echo $intento['id_attempt']; ?>"><button type="submit" class="atk-btn atk-btn-warning atk-btn-small" onclick="return confirm('¿Bloquear permanentemente esta IP?')">🚫</button></form>

                            <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="limpiar_ip"><input type="hidden" name="id" value="<?php echo $intento['id_attempt']; ?>"><button type="submit" class="atk-btn atk-btn-secondary atk-btn-small" onclick="return confirm('¿Eliminar todos los intentos de esta IP?')">🧹</button></form>

                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>

            <div class="paginacion">
                <?php if ($pagina_actual > 1): ?><a href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo $busqueda ? '&busqueda=' . urlencode($busqueda) . '&filtro=' . $filtro_tipo : ''; ?>" class="btn-pagina">« Anterior</a><?php endif; ?>

                <?php for ($i = 1; $i <= min(10, $total_paginas); $i++): ?>

                    <?php if ($i == $pagina_actual): ?><span class="btn-pagina active"><?php echo $i; ?></span><?php else: ?><a href="?pagina=<?php echo $i; ?><?php echo $busqueda ? '&busqueda=' . urlencode($busqueda) . '&filtro=' . $filtro_tipo : ''; ?>" class="btn-pagina"><?php echo $i; ?></a><?php endif; ?>

                <?php endfor; ?>

                <?php if ($pagina_actual < $total_paginas): ?><a href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo $busqueda ? '&busqueda=' . urlencode($busqueda) . '&filtro=' . $filtro_tipo : ''; ?>" class="btn-pagina">Siguiente »</a><?php endif; ?>

            </div>
            <?php endif; ?>

            <div class="atk-accion-global">
                <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="auto_limpiar"><input type="hidden" name="dias" value="30"><button type="submit" class="atk-btn atk-btn-secondary" onclick="return confirm('¿Eliminar registros con más de 30 días?')">🧹 Limpiar antiguos (+30 días)</button></form>

                <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="limpiar_todo"><button type="submit" class="atk-btn atk-btn-danger" onclick="return confirm('¿Eliminar TODOS los registros? Esta acción no se puede deshacer.')">🗑️ Limpiar todo</button></form>

                <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="accion" value="exportar_csv"><button type="submit" class="atk-btn atk-btn-primary">📊 Exportar a CSV</button></form>

            </div>
        <?php endif; ?>

    </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
