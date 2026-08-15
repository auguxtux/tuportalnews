<?php
declare(strict_types=1);


/**
 * EJECUTAR BACKUP COMPLETO (BD + Archivos)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';

Permisos::requerirAdmin();
set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido');
}

if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $_SESSION['backup_msg'] = '❌ Error de seguridad';
    header('Location: ' . route('admin_backups'));
    exit;
}

$filepath = null;
$filepath_sql = null;
$sql_handle = null;
$backup_completado = false;

try {
    $backup_dir = __DIR__ . '/../backups/database/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $fecha = date('Y-m-d_H-i-s');
    $filename = 'backup_completo_' . $fecha . '.zip';
    $filepath = $backup_dir . $filename;
    $filepath_sql = $backup_dir . 'backup_' . $fecha . '.sql';
    
    // PASO 1: Crear backup de BD
    $pdo = db();
    $sql_handle = fopen($filepath_sql, 'wb');
    if ($sql_handle === false) {
        throw new RuntimeException('No se pudo crear el SQL temporal');
    }
    if (!chmod($filepath_sql, 0600)) {
        throw new RuntimeException('No se pudo proteger el SQL temporal');
    }

    $escribir_sql = static function (string $contenido) use ($sql_handle): void {
        $longitud = strlen($contenido);
        $escritos = 0;

        while ($escritos < $longitud) {
            $resultado = fwrite($sql_handle, substr($contenido, $escritos));
            if ($resultado === false || $resultado === 0) {
                throw new RuntimeException('No se pudo escribir el SQL temporal');
            }
            $escritos += $resultado;
        }
    };

    $escribir_sql("-- Backup " . DB_NAME . " - " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    
    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tablas as $tabla) {
        $stmt = $pdo->query("SHOW CREATE TABLE `$tabla`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        $escribir_sql("\n-- Tabla: $tabla\n" . $row[1] . ";\n\n");
        
        $filas = $pdo->query("SELECT * FROM `$tabla`");
        while ($fila = $filas->fetch(PDO::FETCH_ASSOC)) {
            $valores = array_map(function($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, $fila);
            $escribir_sql("INSERT INTO `$tabla` VALUES (" . implode(', ', $valores) . ");\n");
        }
        $filas->closeCursor();
    }
    $escribir_sql("\nSET FOREIGN_KEY_CHECKS=1;\n");

    if (!fclose($sql_handle)) {
        throw new RuntimeException('No se pudo finalizar el SQL temporal');
    }
    $sql_handle = null;
    
    // PASO 2: Crear ZIP con archivos + BD
    $root_dir = __DIR__ . '/../';
    
    $command = sprintf(
        'cd %s && zip -r %s . -x "backups/database/*.zip" "backups/database/*.sql" "cache/*" "tmp/*" ".git/*" ".git-antiguo/*" "vendor/*" "node_modules/*" ".env" "includes/mail-config.php" "includes/aemet-config.php" 2>&1',
        escapeshellarg($root_dir),
        escapeshellarg($filepath)
    );
    
    exec($command, $output, $return_var);
    
    // PASO 3: Añadir SQL al ZIP (se añade al final)
    if ($return_var !== 0 || !is_file($filepath)) {
        throw new RuntimeException('El comando ZIP no pudo crear el backup');
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new RuntimeException('No se pudo abrir el ZIP generado');
    }

    if (!$zip->addFile($filepath_sql, 'database/backup_' . $fecha . '.sql')) {
        $zip->close();
        throw new RuntimeException('No se pudo añadir el SQL al ZIP');
    }

    if (!$zip->close()) {
        throw new RuntimeException('No se pudo finalizar el ZIP');
    }

    if (!chmod($filepath, 0600)) {
        throw new RuntimeException('No se pudo proteger el backup generado');
    }

    $backup_completado = true;
    $tamano = round(filesize($filepath) / 1024 / 1024, 1);
    $backups_eliminados = 0;
    $backups_existentes = glob($backup_dir . 'backup_completo_*.zip') ?: [];
    usort(
        $backups_existentes,
        static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a)
    );

    foreach (array_slice($backups_existentes, 5) as $backup_antiguo) {
        if (is_file($backup_antiguo) && unlink($backup_antiguo)) {
            $backups_eliminados++;
        } else {
            error_log('No se pudo aplicar la retención a un backup antiguo');
        }
    }

    $_SESSION['backup_msg'] = '✅ Backup creado: ' . $filename . ' (' . $tamano . ' MB)';
    if ($backups_eliminados > 0) {
        $_SESSION['backup_msg'] .= '. Backups antiguos eliminados: ' . $backups_eliminados;
    }
    
} catch (Exception $e) {
    registrarErrorInterno('BACKUP.CREATE', $e);
    $_SESSION['backup_msg'] = '❌ No se pudo crear el backup';
} finally {
    if (is_resource($sql_handle)) {
        fclose($sql_handle);
    }
    if (is_string($filepath_sql) && is_file($filepath_sql) && !unlink($filepath_sql)) {
        error_log('No se pudo eliminar el SQL temporal del backup');
    }
    if (!$backup_completado && is_string($filepath) && is_file($filepath) && !unlink($filepath)) {
        error_log('No se pudo eliminar el ZIP incompleto del backup');
    }
}

header('Location: ' . route('admin_backups'));
exit;
