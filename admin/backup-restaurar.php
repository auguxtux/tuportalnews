<?php
declare(strict_types=1);


/**
 * RESTAURAR BACKUP
 * Solo administradores
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirAdmin();
set_time_limit(300);

$mensaje = '';
$error = '';
$zip_validado = null;
$entradas_zip = ['sql' => [], 'archivos' => []];
$directorio_temporal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = '❌ Error de seguridad';
    } else {
        $file = basename($_POST['backup_file'] ?? '');
        $tipo = $_POST['tipo_restauracion'] ?? '';
        
        if (empty($file)) {
            $error = '❌ Selecciona un archivo de backup';
        } elseif (!in_array($tipo, ['bd', 'archivos', 'completo'], true)) {
            $error = '❌ Tipo de restauración no válido';
        } else {
            $filepath = __DIR__ . '/../backups/database/' . $file;
            
            if (!file_exists($filepath)) {
                $error = '❌ Archivo no encontrado';
            } else {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                try {
                    if (!in_array($ext, ['sql', 'zip'], true)) {
                        throw new RuntimeException('Tipo de backup no permitido');
                    }

                    if ($ext === 'zip') {
                        [$zip_validado, $entradas_zip] = abrirYValidarBackupZip($filepath);
                    }

                    if (
                        in_array($tipo, ['archivos', 'completo'], true)
                        && $ext === 'zip'
                    ) {
                        $directorio_temporal = prepararArchivosBackup(
                            $zip_validado,
                            $entradas_zip['archivos']
                        );
                    }

                    // RESTAURAR BASE DE DATOS
                    if ($tipo === 'bd' || $tipo === 'completo') {
                        if ($ext === 'zip') {
                            $entrada_sql = $entradas_zip['sql'][0] ?? null;
                            if ($entrada_sql === null) {
                                $error = '❌ No se encontró archivo SQL en el ZIP';
                            } else {
                                $sql_content = $zip_validado->getFromName($entrada_sql);
                                if ($sql_content === false) {
                                    throw new RuntimeException('No se pudo leer el SQL del backup');
                                }
                                ejecutarSQL($sql_content);
                                $mensaje = '✅ Base de datos restaurada correctamente';
                            }
                        } elseif ($ext === 'sql') {
                            $sql_content = file_get_contents($filepath);
                            ejecutarSQL($sql_content);
                            $mensaje = '✅ Base de datos restaurada correctamente';
                        }
                    }
                    
                    // RESTAURAR ARCHIVOS
                    if ($tipo === 'archivos' || $tipo === 'completo') {
                        if ($ext === 'zip') {
                            $root_dir = __DIR__ . '/../';
                            aplicarArchivosBackup(
                                $directorio_temporal,
                                $root_dir,
                                $entradas_zip['archivos']
                            );
                            $mensaje .= ' ✅ Archivos restaurados correctamente';
                        } else {
                            $error = '❌ Para restaurar archivos se necesita un backup ZIP';
                        }
                    }

                    if ($zip_validado instanceof ZipArchive) {
                        $zip_validado->close();
                        $zip_validado = null;
                    }
                    
                } catch (Exception $e) {
                    if ($zip_validado instanceof ZipArchive) {
                        $zip_validado->close();
                        $zip_validado = null;
                    }
                    $error = '❌ No se pudo restaurar el backup';
                    registrarErrorInterno('BACKUP.RESTORE', $e);
                } finally {
                    if (is_string($directorio_temporal)) {
                        eliminarDirectorioTemporal($directorio_temporal);
                        $directorio_temporal = null;
                    }
                }
            }
        }
    }
}

// Listar backups disponibles
$backup_dir = __DIR__ . '/../backups/database/';
$backups = [];
$archivos = glob($backup_dir . 'backup_*.{sql,zip}', GLOB_BRACE);
if ($archivos) {
    rsort($archivos);
    foreach ($archivos as $archivo) {
        $backups[] = [
            'nombre' => basename($archivo),
            'tamano' => round(filesize($archivo) / 1024 / 1024, 1),
            'fecha' => date('d/m/Y H:i', filemtime($archivo)),
            'tipo' => strtoupper(pathinfo($archivo, PATHINFO_EXTENSION))
        ];
    }
}

$titulo_pagina = 'Restaurar Backup';
require_once __DIR__ . '/../partials/header.php';
?>

<style>
.restore-container { max-width: 800px; margin: 0 auto; padding: 1rem; }
.restore-alerta { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
.restore-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
.restore-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
.restore-card { background: white; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.restore-card h2 { margin: 0 0 1rem 0; font-size: 1.1rem; }
.restore-select { width: 100%; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.9rem; margin-bottom: 1rem; }
.restore-radio-group { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.restore-radio-group label { display: flex; align-items: center; gap: 0.3rem; cursor: pointer; font-size: 0.9rem; }
.restore-btn { padding: 0.6rem 1.5rem; border-radius: 5px; font-size: 0.9rem; cursor: pointer; border: none; }
.restore-btn-primary { background: #2563eb; color: white; }
.restore-btn-danger { background: #dc2626; color: white; }
.restore-btn-back {
  background: #0040ff;
  color: white;
  text-decoration: none;
  margin-top: 1rem;
  padding: 5px 14px;
  border-radius: 5px;
  display: block;
  text-align: center;
}
.restore-warning { background: #fef3c7; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; border-left: 4px solid #f59e0b; font-size: 0.85rem; }
</style>

<div class="restore-container">
    <h1>🔄 Restaurar Backup</h1>
    
    <?php if ($mensaje): ?>

        <div class="restore-alerta restore-success"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="restore-alerta restore-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>

    <?php endif; ?>

    
    <div class="restore-warning">
        <strong>⚠️ ADVERTENCIA:</strong> Restaurar un backup sobrescribirá los datos actuales. 
        Asegúrate de tener un backup reciente antes de continuar.
    </div>
    
    <div class="restore-card">
        <h2>📂 Seleccionar Backup</h2>
        
        <?php if (empty($backups)): ?>

            <p>📭 No hay backups disponibles</p>
        <?php else: ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                
                <select name="backup_file" class="restore-select" required>
                    <option value="">-- Selecciona un backup --</option>
                    <?php foreach ($backups as $b): ?>

                        <option value="<?php echo htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8'); ?>">

                            <?php echo htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $b['tamano']; ?> MB - <?php echo $b['fecha']; ?>)

                        </option>
                    <?php endforeach; ?>

                </select>
                
                <div class="restore-radio-group">
                    <label><input type="radio" name="tipo_restauracion" value="bd" checked> 💾 Solo Base de Datos</label>
                    <label><input type="radio" name="tipo_restauracion" value="archivos"> 📁 Solo Archivos</label>
                    <label><input type="radio" name="tipo_restauracion" value="completo"> 🔄 Completo (BD + Archivos)</label>
                </div>
                
                <button type="submit" class="restore-btn restore-btn-danger" 
                        onclick="return confirm('¿Estás SEGURO? Esta acción sobrescribirá los datos actuales.')">
                    🔄 Restaurar Ahora
                </button>
            </form>
        <?php endif; ?>

    </div>
    
    <a href="<?php echo htmlspecialchars(route('admin_backups'), ENT_QUOTES, 'UTF-8'); ?>" class="restore-btn-back">← Volver a Backups</a>
</div>

<?php

function abrirYValidarBackupZip(string $filepath): array {
    $max_archivos = 50000;
    $max_tamano_total = 10 * 1024 * 1024 * 1024;
    $zip = new ZipArchive();

    if ($zip->open($filepath) !== true) {
        throw new RuntimeException('No se pudo abrir el ZIP');
    }

    if ($zip->numFiles > $max_archivos) {
        $zip->close();
        throw new RuntimeException('El ZIP contiene demasiados archivos');
    }

    $entradas = ['sql' => [], 'archivos' => []];
    $tamano_total = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $stat = $zip->statIndex($i);

        if ($name === false || $stat === false || strpos($name, "\0") !== false) {
            $zip->close();
            throw new RuntimeException('El ZIP contiene una entrada no válida');
        }

        $normalizado = str_replace('\\', '/', $name);
        if (
            str_starts_with($normalizado, '/')
            || preg_match('/^[A-Za-z]:\//', $normalizado)
            || in_array('..', explode('/', $normalizado), true)
        ) {
            $zip->close();
            throw new RuntimeException('El ZIP contiene una ruta no permitida');
        }

        $sistema = 0;
        $atributos = 0;
        if ($zip->getExternalAttributesIndex($i, $sistema, $atributos)) {
            $tipo_archivo = ($atributos >> 16) & 0xF000;
            if ($tipo_archivo === 0xA000) {
                $zip->close();
                throw new RuntimeException('El ZIP contiene enlaces simbólicos');
            }
        }

        $tamano_total += (int) ($stat['size'] ?? 0);
        if ($tamano_total > $max_tamano_total) {
            $zip->close();
            throw new RuntimeException('El contenido descomprimido supera el límite permitido');
        }

        while (str_starts_with($normalizado, './')) {
            $normalizado = substr($normalizado, 2);
        }
        $ruta_minusculas = strtolower($normalizado);

        if (
            $ruta_minusculas === '.env'
            || $ruta_minusculas === 'includes/mail-config.php'
            || $ruta_minusculas === 'includes/aemet-config.php'
            || str_starts_with($ruta_minusculas, '.git/')
            || str_starts_with($ruta_minusculas, '.git-antiguo/')
            || str_starts_with($ruta_minusculas, 'backups/')
        ) {
            continue;
        }

        if (
            str_starts_with($ruta_minusculas, 'database/')
            && str_ends_with($ruta_minusculas, '.sql')
        ) {
            $entradas['sql'][] = $name;
            continue;
        }

        if (!str_ends_with($normalizado, '/')) {
            $entradas['archivos'][] = $name;
        }
    }

    return [$zip, $entradas];
}

/**
 * Extrae y verifica los archivos en un directorio aislado antes de modificar
 * la aplicación o restaurar la base de datos.
 */
function prepararArchivosBackup(ZipArchive $zip, array $entradas): string {
    $baseTemporal = ROOT_PATH . 'tmp' . DIRECTORY_SEPARATOR;
    if (!is_dir($baseTemporal) && !mkdir($baseTemporal, 0750, true)) {
        throw new RuntimeException('No se pudo preparar el directorio temporal');
    }

    $directorio = $baseTemporal . 'restore-' . bin2hex(random_bytes(8));
    if (!mkdir($directorio, 0700, true)) {
        throw new RuntimeException('No se pudo preparar la restauración');
    }

    try {
        foreach ($entradas as $entrada) {
            if (!$zip->extractTo($directorio, $entrada)) {
                throw new RuntimeException('No se pudo preparar un archivo del backup');
            }

            $ruta = $directorio . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, (string) $entrada);
            if (!is_file($ruta) || !is_readable($ruta)) {
                throw new RuntimeException('El backup contiene un archivo no verificable');
            }
        }

        return $directorio;
    } catch (Throwable $e) {
        eliminarDirectorioTemporal($directorio);
        throw $e;
    }
}

/**
 * Copia cada archivo preparado mediante sustitución atómica en el mismo
 * sistema de archivos.
 */
function aplicarArchivosBackup(string $temporal, string $destino, array $entradas): void {
    foreach ($entradas as $entrada) {
        $relativa = str_replace('/', DIRECTORY_SEPARATOR, (string) $entrada);
        $origen = $temporal . DIRECTORY_SEPARATOR . $relativa;
        $final = rtrim($destino, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativa;
        $directorioFinal = dirname($final);

        if (!is_dir($directorioFinal) && !mkdir($directorioFinal, 0755, true)) {
            throw new RuntimeException('No se pudo preparar un directorio de destino');
        }

        $copia = $final . '.restore-' . bin2hex(random_bytes(4));
        if (!copy($origen, $copia) || !chmod($copia, 0644) || !rename($copia, $final)) {
            if (is_file($copia)) {
                unlink($copia);
            }
            throw new RuntimeException('No se pudo aplicar un archivo del backup');
        }
    }
}

function eliminarDirectorioTemporal(string $directorio): void {
    if (!is_dir($directorio)) {
        return;
    }

    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterador as $entrada) {
        if ($entrada->isDir()) {
            rmdir($entrada->getPathname());
        } else {
            unlink($entrada->getPathname());
        }
    }
    rmdir($directorio);
}

function contieneSeparadorSqlNoPermitido(string $sql): bool {
    $en_cadena = false;
    $longitud = strlen($sql);

    for ($i = 0; $i < $longitud; $i++) {
        $caracter = $sql[$i];

        if ($en_cadena) {
            if ($caracter === '\\') {
                $i++;
                continue;
            }

            if ($caracter === "'") {
                if ($i + 1 < $longitud && $sql[$i + 1] === "'") {
                    $i++;
                    continue;
                }
                $en_cadena = false;
            }
            continue;
        }

        if ($caracter === "'") {
            $en_cadena = true;
        } elseif ($caracter === ';') {
            return true;
        }
    }

    return false;
}

function ejecutarSQL($sql) {
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('El backup SQL está vacío');
    }

    $statements = [];
    foreach (explode(";\n", $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '' && !str_starts_with($statement, '--')) {
            $statements[] = $statement;
        }
    }

    if ($statements === []) {
        throw new RuntimeException('El backup SQL no contiene sentencias ejecutables');
    }

    $inserts = [];
    foreach ($statements as $statement) {
        if (contieneSeparadorSqlNoPermitido($statement)) {
            throw new RuntimeException('El backup SQL contiene múltiples sentencias');
        }

        if (preg_match('/\AINSERT\s+INTO\s+`[A-Za-z0-9_]+`\s+VALUES\s*\(/is', $statement)) {
            $inserts[] = $statement;
            continue;
        }

        if (preg_match('/\ASET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]\s*\z/i', $statement)) {
            continue;
        }

        throw new RuntimeException('El backup SQL contiene una sentencia no permitida');
    }

    if ($inserts === []) {
        throw new RuntimeException('El backup SQL no contiene datos restaurables');
    }

    $pdo = db();
    $tablas_info = $pdo->query(
        "SELECT TABLE_NAME, ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($tablas_info === []) {
        throw new RuntimeException('La base de datos no contiene tablas restaurables');
    }

    $tablas = [];
    foreach ($tablas_info as $tabla_info) {
        if (strcasecmp((string) ($tabla_info['ENGINE'] ?? ''), 'InnoDB') !== 0) {
            throw new RuntimeException('La base de datos contiene tablas no transaccionales');
        }

        $tablas[] = (string) $tabla_info['TABLE_NAME'];
    }

    $foreign_key_checks = (int) $pdo->query("SELECT @@FOREIGN_KEY_CHECKS")->fetchColumn();
    $error_ejecucion = null;

    try {
        // Desactivar restricciones
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        $pdo->beginTransaction();

        // Vaciar todas las tablas dentro de la transacción
        foreach ($tablas as $tabla) {
            $tabla_escapada = str_replace('`', '``', $tabla);
            $pdo->exec("DELETE FROM `$tabla_escapada`");
        }

        // Ejecutar únicamente las inserciones previamente validadas
        foreach ($inserts as $statement) {
            $pdo->exec($statement);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_ejecucion = $e;
    } finally {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=" . ($foreign_key_checks === 1 ? '1' : '0'));
        } catch (Exception $e) {
            registrarErrorInterno('BACKUP.FOREIGN_KEYS', $e);
            if ($error_ejecucion === null) {
                $error_ejecucion = $e;
            }
        }
    }

    if ($error_ejecucion !== null) {
        throw $error_ejecucion;
    }
}

require_once __DIR__ . '/../partials/footer.php';
