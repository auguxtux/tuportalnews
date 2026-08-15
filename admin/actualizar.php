<?php
declare(strict_types=1);


/**
 * SCRIPT DE DIAGNÓSTICO Y ACTUALIZACIÓN
 * Analiza versiones de PHP, MySQL y compatibilidad
 * Solo accesible para administradores
 * 
 * CORRECCIONES APLICADAS:
 * ✅ 1. Añadido require_once 'conexion.php'
 * ✅ 2. Ocultados nombres reales en comandos
 * ✅ 3. Añadida nota sobre Plesk
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

// Solo administradores
Permisos::requerirAdmin();

// Funciones auxiliares
function versionCompare($v1, $v2) {
    return version_compare($v1, $v2);
}

function getPhpVersion() {
    return PHP_VERSION;
}

function getMysqlVersion() {
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        return $result['version'];
    } catch (Exception $e) {
        return "No disponible";
    }
}

function checkPhpExtension($ext) {
    return extension_loaded($ext) ? '✅' : '❌';
}

function checkFunction($func) {
    return function_exists($func) ? '✅' : '❌';
}

function getPhpIniValue($key) {
    return ini_get($key) ?: 'No configurado';
}

function getRecommendedPhpVersion() {
    $current = getPhpVersion();
    
    if (versionCompare($current, '8.3') >= 0) {
        return '8.3 o superior (última estable)';
    } elseif (versionCompare($current, '8.2') >= 0) {
        return '8.2 (versión LTS recomendada)';
    } elseif (versionCompare($current, '8.1') >= 0) {
        return '8.2 (actualizar recomendado)';
    } else {
        return '8.2 (actualización URGENTE)';
    }
}

function getRecommendedMysqlVersion() {
    $current = getMysqlVersion();
    if (strpos($current, 'MariaDB') !== false) {
        return '10.11 (LTS)';
    } else {
        return '8.0 (LTS)';
    }
}

function getUpgradeStatus() {
    $php = getPhpVersion();
    $mysql = getMysqlVersion();
    
    $status = [];
    
    // PHP
    if (versionCompare($php, '8.2') < 0) {
        $status['php'] = ['level' => 'danger', 'message' => '⚠️ Versión PHP obsoleta (anterior a 8.2)'];
    } elseif (versionCompare($php, '8.3') >= 0) {
        $status['php'] = ['level' => 'success', 'message' => '✅ Versión PHP actualizada (última)'];
    } else {
        $status['php'] = ['level' => 'info', 'message' => 'ℹ️ Versión PHP estable (8.2)'];
    }
    
    // MySQL/MariaDB
    if (strpos($mysql, '5.') === 0) {
        $status['mysql'] = ['level' => 'danger', 'message' => '⚠️ MySQL 5.x obsoleto - Migrar a 8.0'];
    } elseif (strpos($mysql, '10.') === 0) {
        $parts = explode('.', $mysql);
        if (isset($parts[1]) && (int)$parts[1] < 11) {
            $status['mysql'] = ['level' => 'warning', 'message' => '⚠️ MariaDB anterior a 10.11 - Actualizar'];
        } else {
            $status['mysql'] = ['level' => 'success', 'message' => '✅ MariaDB 10.11+ (LTS)'];
        }
    } elseif (strpos($mysql, '8.0') === 0) {
        $status['mysql'] = ['level' => 'success', 'message' => '✅ MySQL 8.0 (LTS)'];
    } else {
        $status['mysql'] = ['level' => 'warning', 'message' => '⚠️ Versión MySQL no verificada'];
    }
    
    return $status;
}

$php_version = getPhpVersion();
$mysql_version = getMysqlVersion();
$status = getUpgradeStatus();
$titulo_pagina = 'Diagnóstico de Actualización';

require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-actualizar.css'); ?>">


<div class="act-container">
    <h1 class="act-titulo">🔧 Diagnóstico de Actualización</h1>
    
    <!-- ESTADO GENERAL -->
    <div class="act-card">
        <h2>📊 Estado del Sistema</h2>
        <div class="act-grid-2">
            <div class="act-version-box <?php echo $status['php']['level'] == 'success' ? 'act-badge-success' : ($status['php']['level'] == 'warning' ? 'act-badge-warning' : 'act-badge-danger'); ?>">

                <div class="act-version-label">PHP</div>
                <div class="act-version-number"><?php echo $php_version; ?></div>

                <div><?php echo $status['php']['message']; ?></div>

            </div>
            <div class="act-version-box <?php echo $status['mysql']['level'] == 'success' ? 'act-badge-success' : ($status['mysql']['level'] == 'warning' ? 'act-badge-warning' : 'act-badge-danger'); ?>">

                <div class="act-version-label">MySQL/MariaDB</div>
                <div class="act-version-number"><?php echo $mysql_version; ?></div>

                <div><?php echo $status['mysql']['message']; ?></div>

            </div>
        </div>
    </div>
    
    <!-- VERSIONES RECOMENDADAS -->
    <div class="act-card">
        <h2>🎯 Versiones Recomendadas</h2>
        <div class="act-grid-3">
            <div>
                <h3 class="act-subtitulo">PHP</h3>
                <p class="act-parrafo"><strong>Actual:</strong> <?php echo $php_version; ?></p>

                <p class="act-parrafo"><strong>Recomendada:</strong> <?php echo getRecommendedPhpVersion(); ?></p>

            </div>
            <div>
                <h3 class="act-subtitulo">MySQL/MariaDB</h3>
                <p class="act-parrafo"><strong>Actual:</strong> <?php echo $mysql_version; ?></p>

                <p class="act-parrafo"><strong>Recomendada:</strong> <?php echo getRecommendedMysqlVersion(); ?></p>

            </div>
            <div>
                <h3 class="act-subtitulo">Estado</h3>
                <?php if ($status['php']['level'] == 'success' && $status['mysql']['level'] == 'success'): ?>

                    <div class="act-badge-success" style="padding:10px;">✅ Sistema actualizado</div>
                <?php else: ?>

                    <div class="act-badge-warning" style="padding:10px;">⚠️ Recomienda actualizar</div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    
    <!-- EXTENSIONES PHP -->
    <div class="act-card">
        <h2>🧩 Extensiones PHP</h2>
        <div style="overflow-x: auto;">
            <table class="act-tabla">
                <thead>
                    <tr>
                        <th>Extensión</th>
                        <th>Estado</th>
                        <th>Requerida</th>
                        <th>Función clave</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>pdo_mysql</td>
                        <td><?php echo checkPhpExtension('pdo_mysql'); ?></td>

                        <td>✅ Obligatoria</td>
                        <td>Conexión PDO</td>
                    </tr>
                    <tr>
                        <td>mysqli</td>
                        <td><?php echo checkPhpExtension('mysqli'); ?></td>

                        <td>✅ Obligatoria</td>
                        <td>Conexiones MySQLi</td>
                    </tr>
                    <tr>
                        <td>gd</td>
                        <td><?php echo checkPhpExtension('gd'); ?></td>

                        <td>⚠️ Opcional</td>
                        <td>Procesar imágenes</td>
                    </tr>
                    <tr>
                        <td>mbstring</td>
                        <td><?php echo checkPhpExtension('mbstring'); ?></td>

                        <td>⚠️ Opcional</td>
                        <td>Cadenas UTF-8</td>
                    </tr>
                    <tr>
                        <td>curl</td>
                        <td><?php echo checkPhpExtension('curl'); ?></td>

                        <td>⚠️ Opcional</td>
                        <td>Peticiones HTTP</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- FUNCIONES OBSOLETAS -->
    <div class="act-card">
        <h2>⚠️ Funciones Obsoletas (a evitar)</h2>
        <div style="overflow-x: auto;">
            <table class="act-tabla">
                <thead>
                    <tr>
                        <th>Función</th>
                        <th>Uso en tu app</th>
                        <th>Alternativa moderna</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>mysql_*</td>
                        <td><?php echo checkFunction('mysql_connect') == '✅' ? '❌ USANDO' : '✅ No usas'; ?></td>

                        <td>mysqli_* o PDO</td>
                    </tr>
                    <tr>
                        <td>ereg</td>
                        <td><?php echo checkFunction('ereg') == '✅' ? '❌ USANDO' : '✅ No usas'; ?></td>

                        <td>preg_match</td>
                    </tr>
                    <tr>
                        <td>split</td>
                        <td><?php echo checkFunction('split') == '✅' ? '❌ USANDO' : '✅ No usas'; ?></td>

                        <td>explode, preg_split</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- CONFIGURACIÓN PHP -->
    <div class="act-card">
        <h2>⚙️ Configuración PHP</h2>
        <div style="overflow-x: auto;">
            <table class="act-tabla">
                <thead>
                    <tr>
                        <th>Directiva</th>
                        <th>Valor actual</th>
                        <th>Recomendado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>memory_limit</td>
                        <td><?php echo getPhpIniValue('memory_limit'); ?></td>

                        <td>256M</td>
                    </tr>
                    <tr>
                        <td>upload_max_filesize</td>
                        <td><?php echo getPhpIniValue('upload_max_filesize'); ?></td>

                        <td>5M</td>
                    </tr>
                    <tr>
                        <td>post_max_size</td>
                        <td><?php echo getPhpIniValue('post_max_size'); ?></td>

                        <td>6M</td>
                    </tr>
                    <tr>
                        <td>max_execution_time</td>
                        <td><?php echo getPhpIniValue('max_execution_time'); ?></td>

                        <td>300</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- COMANDOS DE ACTUALIZACIÓN -->
    <div class="act-card">
        <h2>🛠️ Comandos de Actualización</h2>
        
        <!-- ✅ NOTA SOBRE PLESK -->
        <div class="act-alert act-alert-info">
            <strong>ℹ️ Importante:</strong> Si usas <strong>Plesk</strong> como panel de control, 
            actualiza PHP desde <em>Herramientas y Configuración &gt; Actualizaciones del Sistema</em> 
            o desde la configuración del dominio. Los comandos siguientes son para servidores 
            con Apache/Ubuntu estándar.
        </div>
        
        <?php if (versionCompare($php_version, '8.2') < 0): ?>

            <div class="act-alert act-alert-warning">
                <strong>⚠️ Actualización de PHP recomendada</strong>
                <p>Ejecuta estos comandos para actualizar a PHP 8.2:</p>
                <div class="act-command">
                    sudo apt update<br>
                    sudo apt install -y php8.2 php8.2-cli php8.2-common php8.2-mysql php8.2-fpm<br>
                    sudo a2dismod php<?php echo substr($php_version, 0, 3); ?><br>

                    sudo a2enmod php8.2<br>
                    sudo systemctl restart apache2
                </div>
            </div>
        <?php endif; ?>

        
        <?php if (strpos($mysql_version, '5.') === 0): ?>

            <div class="act-alert act-alert-danger">
                <strong>❌ Actualización URGENTE de MySQL</strong>
                <p>MySQL 5.x no tiene soporte. Migra a MySQL 8.0:</p>
                <div class="act-command">
                    # Backup primero<br>
                    mysqldump -u USUARIO_DB -p NOMBRE_DB &gt; backup.sql<br><br>
                    # Instalar MySQL 8.0<br>
                    sudo apt install -y mysql-server-8.0<br>
                    mysql_upgrade -u root -p<br>
                    mysql -u USUARIO_DB -p NOMBRE_DB &lt; backup.sql
                </div>
            </div>
        <?php endif; ?>

        
        <!-- BACKUP RECOMENDADO -->
        <div class="act-alert act-alert-info">
            <strong>💾 Antes de actualizar, haz backup:</strong>
            <div class="act-command">
                # Backup de archivos<br>
                sudo tar -czf ~/backups/news_$(date +%Y%m%d).tar.gz /ruta/proyecto/<br><br>
                # Backup de BD<br>
                mysqldump -u USUARIO_DB -p NOMBRE_DB &gt; ~/backups/db_$(date +%Y%m%d).sql
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 1.5rem; padding: 1rem;">
            <button class="act-btn act-btn-success" onclick="window.print()">🖨️ Imprimir diagnóstico</button>
            <button class="act-btn act-btn-warning" onclick="copiarComandos()">📋 Copiar comandos</button>
        </div>
    </div>
    
    <!-- LOGS RECIENTES -->
    <div class="act-card">
        <h2>📋 Logs de PHP</h2>
        <?php

        $log_file = ini_get('error_log');
        if ($log_file && file_exists($log_file)) {
            $lines = file($log_file);
            $lines = array_slice($lines, -20);
            echo '<div class="act-code">';
            foreach ($lines as $line) {
                echo htmlspecialchars($line) . '<br>';
            }
            echo '</div>';
        } else {
            echo '<div style="padding: 1rem;"><p>No hay logs de PHP disponibles</p></div>';
        }
        ?>
    </div>
</div>

<script>
function copiarComandos() {
    let comandos = document.querySelectorAll('.act-command');
    let texto = '';
    comandos.forEach(cmd => {
        texto += cmd.innerText + '\n\n';
    });
    
    navigator.clipboard.writeText(texto).then(() => {
        alert('✅ Comandos copiados al portapapeles');
    }).catch(() => {
        alert('❌ No se pudo copiar');
    });
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
