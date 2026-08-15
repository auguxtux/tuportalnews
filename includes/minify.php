<?php
declare(strict_types=1);


/**
 * SISTEMA UNIFICADO DE MINIFICACIÓN
 * Todos los CSS se minifican y guardan en /min/
 */

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/config.php';
}

// ============================================
// CONFIGURACIÓN
// ============================================
define('MINIFY_CACHE_FILE', ROOT_PATH . 'cache/minify_mode.cache');
define('MINIFY_CSS_DIR', ROOT_PATH . 'assets/css/app-css/');
define('MINIFY_JS_DIR', ROOT_PATH . 'assets/js/app-js/');
define('MINIFY_CSS_MIN_DIR', MINIFY_CSS_DIR . 'min/');

// ============================================
// FUNCIONES DE MODO
// ============================================

function getMinifyMode() {
    if (file_exists(MINIFY_CACHE_FILE)) {
        $mode = trim(file_get_contents(MINIFY_CACHE_FILE));
        if ($mode === 'production') return 'production';
    }
    return 'development';
}

function setMinifyMode($mode) {
    $cache_dir = dirname(MINIFY_CACHE_FILE);
    if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);
    
    $mode_value = ($mode === 'production') ? 'production' : 'development';
    
    $result = file_put_contents(MINIFY_CACHE_FILE, $mode_value);
    
    return $result !== false;
}

// ============================================
// MINIFICACIÓN CSS (PHP Puro)
// ============================================

function minifyCss($css) {
    if ($css === null) {
        return '';
    }

    // Sin un parser CSS no es seguro alterar comentarios o espacios:
    // pueden formar parte de URLs, cadenas, calc() o custom properties.
    return $css;
}

/**
 * Escribe el contenido completo sin exponer al servidor un destino parcial.
 */
function writeMinifiedFile(string $destination, string $content): bool {
    if ($content === '') {
        return false;
    }

    $temporary = tempnam(dirname($destination), '.minify-');
    if ($temporary === false) {
        return false;
    }

    $written = file_put_contents($temporary, $content, LOCK_EX);
    if ($written !== strlen($content)) {
        @unlink($temporary);
        return false;
    }

    if (!chmod($temporary, 0644)) {
        @unlink($temporary);
        return false;
    }

    if (!rename($temporary, $destination)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

// ============================================
// REGENERAR ARCHIVOS MINIFICADOS
// ============================================

function regenerateMinifiedFiles() {
    $output = [];
    $success = true;
    $output[] = "🚀 INICIANDO MINIFICACIÓN";
    $output[] = "==========================";
    
    // Crear directorio min si no existe
    if (!is_dir(MINIFY_CSS_MIN_DIR)) {
        mkdir(MINIFY_CSS_MIN_DIR, 0755, true);
        $output[] = "📁 Creado: " . str_replace(ROOT_PATH, '', MINIFY_CSS_MIN_DIR);
    }
    
    // ========================================
    // MINIFICAR TODOS LOS CSS (incluyendo main.css)
    // ========================================
    $output[] = "";
    $output[] = "📁 Minificando CSS...";
    
    $css_files = glob(MINIFY_CSS_DIR . '*.css');
    $css_count = 0;
    
    foreach ($css_files as $file) {
        $filename = basename($file);
        
        // Saltar archivos ya minificados
        if (strpos($filename, '.min.css') !== false) {
            continue;
        }
        
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $content = file_get_contents($file);
        
        if ($content === false) {
            $output[] = "   ❌ Error leyendo: {$filename}";
            continue;
        }

        if ($content === '') {
            $output[] = "   ℹ️ Omitido (archivo vacío): {$filename}";
            continue;
        }
        
        $original_size = strlen($content);
        $minified = minifyCss($content);
        $minified_size = strlen($minified);
        $reduction = ($original_size > 0) ? round((1 - $minified_size / $original_size) * 100) : 0;
        
        // Guardar en la carpeta min/
        $min_file = MINIFY_CSS_MIN_DIR . "{$name}.min.css";
        
        if (writeMinifiedFile($min_file, $minified)) {
            $output[] = sprintf("   ✅ %-35s → %5.2fKB → %5.2fKB (-%3d%%)", 
                $filename, $original_size / 1024, $minified_size / 1024, $reduction);
            $css_count++;
        } else {
            $output[] = "   ❌ Error escribiendo: {$name}.min.css";
            $success = false;
        }
    }
    
    $output[] = "   📊 Total CSS minificados: {$css_count}";
    
    $output[] = "";
    $output[] = "✅ REGENERACIÓN CSS COMPLETADA";
    
    return [
        'success' => $success,
        'output' => implode("\n", $output),
        'last_lines' => implode("\n", array_slice($output, -20))
    ];
}

// ============================================
// OBTENER ARCHIVOS CSS SEGÚN MODO
// ============================================

function getCssFilesToLoad() {
    $mode = getMinifyMode();
    $files = [];
    
    if ($mode === 'production') {
        // PRODUCCIÓN: Solo archivos de la carpeta /min/
        if (is_dir(MINIFY_CSS_MIN_DIR)) {
            foreach (glob(MINIFY_CSS_MIN_DIR . '*.min.css') as $file) {
                $files[] = 'assets/css/app-css/min/' . basename($file);
            }
        }
    } else {
        // DESARROLLO: Solo archivos originales (excluyendo .min.css)
        foreach (glob(MINIFY_CSS_DIR . '*.css') as $file) {
            $filename = basename($file);
            if (strpos($filename, '.min.css') === false) {
                $files[] = 'assets/css/app-css/' . $filename;
            }
        }
    }
    
    return $files;
}

// ============================================
// ESTADÍSTICAS
// ============================================

function getMinifyFileStats($dir, $pattern) {
    $stats = [];
    $total_orig = 0;
    $total_min = 0;
    
    if (!is_dir($dir)) return [$stats, 0, 0];
    
    foreach (glob($dir . $pattern) as $file) {
        $filename = basename($file);
        if (strpos($filename, '.min.') !== false) continue;
        
        $size = filesize($file);
        $min_file = $dir . 'min/' . preg_replace('/\.css$|\.js$/', '.min$0', $filename);
        $min_size = file_exists($min_file) ? filesize($min_file) : 0;
        
        $reduction = ($min_size > 0 && $size > 0) ? round((1 - $min_size / $size) * 100) : 0;
        
        $stats[] = [
            'name' => $filename,
            'size' => $size,
            'min_size' => $min_size,
            'reduction' => $reduction
        ];
        
        $total_orig += $size;
        $total_min += $min_size;
    }
    
    return [$stats, $total_orig, $total_min];
}

function isCacheWritable() {
    $cache_dir = dirname(MINIFY_CACHE_FILE);
    if (!is_dir($cache_dir)) return @mkdir($cache_dir, 0755, true);
    return is_writable($cache_dir);
}

/**
 * Cargar archivo CSS (original o minificado según modo)
 * @param string $css_file Nombre del archivo (ej: 'admin-configuracion.css')
 * @return string Ruta completa del archivo
 */
function css_url($css_file) {
    $mode = getMinifyMode();
    $base_url = SITE_URL . '/assets/css/app-css/';
    
    // Eliminar .min si existe en el nombre
    $css_file = str_replace('.min.css', '.css', $css_file);
    $name = pathinfo($css_file, PATHINFO_FILENAME);
    
    if ($mode === 'production') {
        // Modo producción: usar minificado
        if (file_exists(MINIFY_CSS_MIN_DIR . $name . '.min.css')) {
            return versionarUrlRecurso(
                $base_url . 'min/' . $name . '.min.css',
                MINIFY_CSS_MIN_DIR . $name . '.min.css'
            );
        }
        return versionarUrlRecurso(
            $base_url . $name . '.css',
            MINIFY_CSS_DIR . $name . '.css'
        );
    } else {
        // Modo desarrollo: usar original
        return versionarUrlRecurso(
            $base_url . $name . '.css',
            MINIFY_CSS_DIR . $name . '.css'
        );
    }
}

/**
 * Cargar archivo JS (original o minificado según modo)
 * @param string $js_file Nombre del archivo (ej: 'admin.js')
 * @return string Ruta completa del archivo
 */
function js_url($js_file) {
    $base_url = SITE_URL . '/assets/js/app-js/';

    // JavaScript se sirve desde una única fuente versionada. Sin un parser
    // específico, crear copias .min idénticas solo duplica archivos.
    $js_file = str_replace('.min.js', '.js', $js_file);
    $name = pathinfo($js_file, PATHINFO_FILENAME);

    return versionarUrlRecurso(
        $base_url . $name . '.js',
        MINIFY_JS_DIR . $name . '.js'
    );
}
