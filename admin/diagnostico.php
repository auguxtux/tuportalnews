<?php
declare(strict_types=1);


/**
 * DIAGNÓSTICO COMPLETO DEL SISTEMA
 * Verifica rutas, configuraciones, archivos, BD, extensiones
 * Solo administradores
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirAdmin();

$resultados = [];
$errores = 0;
$warnings = 0;
$ok = 0;

function check($nombre, $condicion, $tipo = 'auto') {
    global $resultados, $errores, $warnings, $ok;
    
    if ($tipo === 'auto') {
        $status = $condicion ? 'OK' : 'ERROR';
    } else {
        $status = $tipo;
    }
    
    if ($status === 'OK') $ok++;
    elseif ($status === 'ERROR') $errores++;
    else $warnings++;
    
    $resultados[] = ['check' => $nombre, 'status' => $status, 'info' => is_string($condicion) ? $condicion : ($condicion ? 'Correcto' : 'Falló')];
}

// ============================================
// 1. VERSIONES
// ============================================
check('PHP Version', 'PHP ' . PHP_VERSION, PHP_VERSION_ID >= 80200 ? 'OK' : 'WARN');
check('MariaDB/MySQL', (db()->query("SELECT VERSION()")->fetchColumn()), 'OK');
check('Sistema Operativo', php_uname('s') . ' ' . php_uname('r'), 'OK');

// ============================================
// 2. EXTENSIONES REQUERIDAS
// ============================================
$ext_requeridas = ['pdo', 'pdo_mysql', 'mysqli', 'gd', 'mbstring', 'json', 'zip', 'curl', 'fileinfo'];
foreach ($ext_requeridas as $ext) {
    check("Extensión: $ext", extension_loaded($ext));
}

// ============================================
// 3. CONFIGURACIÓN PHP
// ============================================
$config_checks = [
    'memory_limit' => ['min' => '128M', 'recomendado' => '256M'],
    'upload_max_filesize' => ['min' => '5M', 'recomendado' => '16M'],
    'post_max_size' => ['min' => '6M', 'recomendado' => '16M'],
    'max_execution_time' => ['min' => '60', 'recomendado' => '300'],
    'max_input_vars' => ['min' => '1000', 'recomendado' => '3000'],
];
foreach ($config_checks as $key => $val) {
    $current = ini_get($key);
    $min = (int)$val['min'];
    $current_int = (int)$current;
    check("PHP: $key", "$current (mín: {$val['min']})", $current_int >= $min ? 'OK' : 'WARN');
}

// ============================================
// 4. ARCHIVOS ESENCIALES
// ============================================
$archivos_esenciales = [
    'index.php',
    '.htaccess',
    'includes/config.php',
    'includes/routes.php',
    'includes/conexion.php',
    'includes/auth.php',
    'includes/permisos.php',
    'includes/funciones.php',
    'includes/upload-handler.php',
    'includes/minify.php',
    'partials/header.php',
    'partials/footer.php',
    'partials/menu-unificado.php',
    'public/portada.php',
    'public/noticia.php',
    'public/login.php',
    'public/registro.php',
    'admin/dashboard.php',
    'admin/periodistas.php',
    'admin/backups.php',
];

$root = __DIR__ . '/../';
foreach ($archivos_esenciales as $archivo) {
    $fullpath = $root . $archivo;
    if (file_exists($fullpath)) {
        check("Archivo: $archivo", filesize($fullpath) . ' bytes');
    } else {
        check("Archivo: $archivo", false);
    }
}

// ============================================
// 5. RUTAS DEL SISTEMA
// ============================================
$rutas_test = ['home', 'login', 'registro', 'noticia', 'admin', 'ataques', 'admin_backups'];
foreach ($rutas_test as $ruta) {
    $url = route($ruta);
    check("Ruta: $ruta", $url, $url !== SITE_URL ? 'OK' : 'ERROR');
}

// ============================================
// 6. CARPETAS Y PERMISOS
// ============================================
$carpetas = [
    'uploads/' => 0755,
    'uploads/noticias/' => 0755,
    'uploads/perfiles/' => 0755,
    'backups/database/' => 0755,
    'cache/' => 0755,
    'logs/' => 0755,
];
foreach ($carpetas as $carpeta => $perm) {
    $fullpath = $root . $carpeta;
    if (is_dir($fullpath)) {
        check("Carpeta: $carpeta", is_writable($fullpath) ? 'Escribible' : 'No escribible', is_writable($fullpath) ? 'OK' : 'ERROR');
    } else {
        check("Carpeta: $carpeta", 'NO EXISTE', 'ERROR');
    }
}

// ============================================
// 7. BASE DE DATOS - CONEXIÓN Y TABLAS
// ============================================
try {
    $pdo = db();
    check('Conexión BD', 'Conectado');
    
    $tablas_esperadas = ['usuarios', 'noticias', 'categorias', 'comentarios', 'configuracion', 
                         'login_attempts', 'megusta_noticias', 'provincias', 'fuentes'];
    $tablas_reales = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tablas_esperadas as $tabla) {
        check("Tabla BD: $tabla", in_array($tabla, $tablas_reales));
    }
    
    // Configuraciones mínimas
    $configs = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    $configs_esperadas = ['site_name', 'items_por_pagina', 'comentarios_aprobacion', 'permitir_registro'];
    foreach ($configs_esperadas as $cfg) {
        check("Config BD: $cfg", $configs[$cfg] ?? 'NO EXISTE', isset($configs[$cfg]) ? 'OK' : 'ERROR');
    }
    
    // Usuarios admin
    $admin_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND estado = 'activo'")->fetchColumn();
    check('Admins activos', (int)$admin_count . ' admin(s)', $admin_count > 0 ? 'OK' : 'ERROR');
    
} catch (Exception $e) {
    check('Conexión BD', 'No disponible', 'ERROR');
    registrarErrorInterno('ADMIN.DIAGNOSTICO', $e);
}

// ============================================
// 8. HEADERS DE SEGURIDAD
// ============================================
$headers_test = @get_headers(SITE_URL, true);
if ($headers_test) {
    check('HTTPS activo', strpos($headers_test[0], '200') !== false);
    check('X-Content-Type-Options', $headers_test['X-Content-Type-Options'] ?? 'NO PRESENTE', isset($headers_test['X-Content-Type-Options']) ? 'OK' : 'WARN');
    check('X-Frame-Options', $headers_test['X-Frame-Options'] ?? 'NO PRESENTE', isset($headers_test['X-Frame-Options']) ? 'OK' : 'WARN');
    check('X-XSS-Protection', $headers_test['X-XSS-Protection'] ?? 'NO PRESENTE', isset($headers_test['X-XSS-Protection']) ? 'OK' : 'WARN');
} else {
    check('Headers HTTP', 'No se pudieron verificar', 'WARN');
}

// ============================================
// 9. LOGS RECIENTES
// ============================================
$log_file = __DIR__ . '/../logs/error.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $ultimos_errores = array_slice($lines, -5);
    $tiene_fatales = false;
    foreach ($ultimos_errores as $line) {
        if (strpos($line, 'Fatal') !== false || strpos($line, 'Parse') !== false) {
            $tiene_fatales = true;
            break;
        }
    }
    check('Errores fatales recientes', $tiene_fatales ? 'SÍ - Revisar logs' : 'No', $tiene_fatales ? 'ERROR' : 'OK');
} else {
    check('Archivo de logs', 'No existe', 'WARN');
}

$titulo_pagina = 'Diagnóstico del Sistema';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-diagnostico.css'); ?>">


<div class="diag-container">
    <div class="diag-header">
        <h1>🔍 Diagnóstico Completo del Sistema</h1>
        <p>Fecha: <?php echo date('d/m/Y H:i:s'); ?></p>

        <div class="diag-summary">
            <span class="diag-badge diag-ok">✅ <?php echo $ok; ?> OK</span>

            <span class="diag-badge diag-warn">⚠️ <?php echo $warnings; ?> Warnings</span>

            <span class="diag-badge diag-error">❌ <?php echo $errores; ?> Errores</span>

        </div>
    </div>
    
    <table class="diag-tabla">
        <thead>
            <tr><th>Verificación</th><th>Estado</th><th>Información</th></tr>
        </thead>
        <tbody>
            <?php foreach ($resultados as $r): ?>

            <tr>
                <td><?php echo $r['check']; ?></td>

                <td>
                    <span class="diag-status diag-status-<?php echo strtolower($r['status']); ?>">

                        <?php echo $r['status']; ?>

                    </span>
                </td>
                <td><?php echo $r['info']; ?></td>

            </tr>
            <?php endforeach; ?>

        </tbody>
    </table>
    
    <p style="text-align: center; margin-top: 1.5rem;">
        <button onclick="window.print()" style="padding: 0.5rem 1rem; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">🖨️ Imprimir</button>
    </p>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
