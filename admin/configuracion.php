<?php
declare(strict_types=1);



ob_start(); // Buffer de salida

/**
 * CONFIGURACIÓN DEL SITIO - VERSIÓN CORREGIDA
 * 
 * ✅ Mantenimiento: Modal con confirmación visual + verificación
 * ✅ Minificación: Botones con confirmación y campo confirmado
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$pdo = db();
$errores = [];

$reglas_configuracion = [
    'site_name' => ['tipo' => 'texto', 'max' => 150],
    'site_description' => ['tipo' => 'texto', 'max' => 500],
    'items_por_pagina' => ['tipo' => 'entero', 'min' => 1, 'max' => 100],
    'comentarios_aprobacion' => ['tipo' => 'booleano'],
    'permitir_registro' => ['tipo' => 'booleano'],
    'permitir_registro_periodistas' => ['tipo' => 'booleano'],
    'max_tamano_imagen' => ['tipo' => 'entero', 'min' => 0, 'max' => 104857600],
    'limite_admin_mb' => ['tipo' => 'entero', 'min' => 0, 'max' => 100000],
    'limite_periodista_mb' => ['tipo' => 'entero', 'min' => 0, 'max' => 100000],
    'limite_usuario_mb' => ['tipo' => 'entero', 'min' => 0, 'max' => 100000],
    'limite_imagen_mb' => ['tipo' => 'entero', 'min' => 1, 'max' => 100],
    'limite_video_mb' => ['tipo' => 'entero', 'min' => 1, 'max' => 2048],
    'notificar_uso_almacenamiento' => ['tipo' => 'booleano'],
    'rss_dias_limpieza' => ['tipo' => 'entero', 'min' => 0, 'max' => 3650],
];

$escalas_almacenamiento = [
    'limite_admin_mb' => ['min' => 0, 'max' => 100000, 'step' => 100],
    'limite_periodista_mb' => ['min' => 0, 'max' => 100000, 'step' => 100],
    'limite_usuario_mb' => ['min' => 0, 'max' => 100000, 'step' => 50],
    'limite_imagen_mb' => ['min' => 1, 'max' => 100, 'step' => 1],
    'limite_video_mb' => ['min' => 1, 'max' => 2048, 'step' => 10],
];

// Definir rutas
define('CSS_ORIGINAL_DIR', ROOT_PATH . 'assets/css/app-css/');
define('JS_ORIGINAL_DIR', ROOT_PATH . 'assets/js/app-js/');

// ============================================
// PROCESAR GUARDADO DE CONFIGURACIÓN GENERAL
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error de seguridad.'];
    } else {
        $config_items = $_POST['config'] ?? [];
        $default_checkboxes = [
            'comentarios_aprobacion' => '0',
            'permitir_registro' => '0',
            'permitir_registro_periodistas' => '0',
            'notificar_uso_almacenamiento' => '0',
        ];
        $valores_validados = [];

        if (!is_array($config_items)) {
            $errores[] = 'Configuración no válida.';
        } else {
            $config_items = array_merge($default_checkboxes, $config_items);

            foreach ($config_items as $clave => $valor) {
                if (!isset($reglas_configuracion[$clave]) || is_array($valor)) {
                    $errores[] = 'Se ha recibido una opción de configuración no válida.';
                    continue;
                }

                $regla = $reglas_configuracion[$clave];
                $valor = trim((string) $valor);

                if ($regla['tipo'] === 'booleano') {
                    if (!in_array($valor, ['0', '1'], true)) {
                        $errores[] = 'Valor booleano no válido.';
                        continue;
                    }
                } elseif ($regla['tipo'] === 'entero') {
                    if (!preg_match('/^-?\d+$/', $valor)) {
                        $errores[] = 'Valor numérico no válido.';
                        continue;
                    }

                    $numero = (int) $valor;
                    if ($numero < $regla['min'] || $numero > $regla['max']) {
                        $errores[] = 'Valor fuera del rango permitido.';
                        continue;
                    }
                    $valor = (string) $numero;
                } else {
                    $valor = trim(strip_tags($valor));
                    if (mb_strlen($valor) > $regla['max']) {
                        $errores[] = 'Un texto supera la longitud permitida.';
                        continue;
                    }
                }

                $valores_validados[$clave] = $valor;
            }
        }

        if ($errores === []) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE configuracion SET valor = :valor WHERE clave = :clave");

                foreach ($valores_validados as $clave => $valor) {
                    $stmt->execute([':valor' => $valor, ':clave' => $clave]);
                }

                $pdo->commit();
                $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Configuración guardada'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                registrarErrorInterno('ADMIN.CONFIG', $e);
                $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ No se pudo guardar la configuración'];
            }
        } else {
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Revisa los valores introducidos'];
        }
    }
    
    header('Location: ' . route('admin_config'));
    exit;
}

// ============================================
// PROCESAR MODO MANTENIMIENTO (CORREGIDO)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_mantenimiento'])) {
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error de seguridad'];
        header('Location: ' . route('admin_config'));
        exit;
    }
    
    // Verificar confirmación explícita
    if (!isset($_POST['confirmado']) || $_POST['confirmado'] !== '1') {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Acción no confirmada'];
        header('Location: ' . route('admin_config'));
        exit;
    }
    
    $archivo_mantenimiento = ROOT_PATH . '.maintenance';
    $accion = isset($_POST['mantenimiento']) ? (int)$_POST['mantenimiento'] : -1;
    
    if ($accion === 1) {
        // Activar mantenimiento
        if (touch($archivo_mantenimiento)) {
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '🔒 Modo mantenimiento ACTIVADO'];
            error_log('Modo mantenimiento activado desde el panel administrativo.');
        } else {
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error al activar mantenimiento'];
        }
    } elseif ($accion === 0) {
        if (file_exists($archivo_mantenimiento)) {
            if (@unlink($archivo_mantenimiento)) {
                $_SESSION["mensaje_flash"] = ["tipo" => "success", "mensaje" => "🔓 Modo mantenimiento DESACTIVADO"];
                error_log('Modo mantenimiento desactivado desde el panel administrativo.');
            } else {
                $_SESSION["mensaje_flash"] = ["tipo" => "error", "mensaje" => "❌ Error al desactivar mantenimiento. Verifica permisos."];
            }
        } else {
            $_SESSION["mensaje_flash"] = ["tipo" => "warning", "mensaje" => "⚠️ El modo mantenimiento ya estaba desactivado."];
        }
    }
    
    header('Location: ' . route('admin_config'));
    exit;
}
// ============================================
// PROCESAR ACCIONES DE MINIFICACIÓN (CORREGIDO)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['minify_accion'])) {
    set_time_limit(300);

    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error de seguridad'];
        header('Location: ' . route('admin_config'));
        exit;
    }
    
    // Verificar confirmación explícita
    if (!isset($_POST['confirmado']) || $_POST['confirmado'] !== '1') {
        $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Acción no confirmada'];
        header('Location: ' . route('admin_config'));
        exit;
    }
    
    $accion = $_POST['minify_accion'];
    
    if ($accion === 'modo_desarrollo') {
        if (setMinifyMode('development')) {
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Modo desarrollo activado'];
        } else {
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error al activar modo desarrollo'];
        }
        
    } elseif ($accion === 'modo_produccion') {
        if (setMinifyMode('production')) {
            $result = regenerateMinifiedFiles();
            if ($result['success']) {
                $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Modo producción activado'];
                $_SESSION['minify_output'] = $result['last_lines'];
            } else {
                $_SESSION['mensaje_flash'] = ['tipo' => 'warning', 'mensaje' => '⚠️ Modo producción activado, con errores'];
            }
        } else {
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error al activar modo producción'];
        }
        
    } elseif ($accion === 'regenerar') {
        $result = regenerateMinifiedFiles();
        if ($result['success']) {
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Archivos regenerados'];
            $_SESSION['minify_output'] = $result['last_lines'];
        } else {
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => '❌ Error al regenerar'];
        }
    }
    
    header('Location: ' . route('admin_config'));
    exit;
}

// ============================================
// OBTENER DATOS
// ============================================
$config = $pdo->query("SELECT * FROM configuracion ORDER BY id_config")->fetchAll();
$modo_actual = getMinifyMode();
list($css_stats, $total_css_orig, $total_css_min) = getMinifyFileStats(CSS_ORIGINAL_DIR, '*.css');
list($js_stats, $total_js_orig) = getMinifyFileStats(JS_ORIGINAL_DIR, '*.js');

$css_reduction = ($total_css_orig > 0) ? round((1 - $total_css_min / $total_css_orig) * 100) : 0;

$mensaje_flash = isset($_SESSION['mensaje_flash']) ? $_SESSION['mensaje_flash'] : null;
unset($_SESSION['mensaje_flash']);

$titulo_pagina = 'Configuración';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-configuracion.css'); ?>">


<!-- Claves que NO deben mostrarse en Configuración General -->
<?php 

$claves_almacenamiento = [
    'limite_admin_mb', 'limite_periodista_mb', 'limite_usuario_mb', 
    'limite_imagen_mb', 'limite_video_mb', 'total_noticias_imagenes_mb', 
    'total_avatares_mb', 'notificar_uso_almacenamiento',
    'ultima_actualizacion_rss', 'rss_dias_limpieza'
];

$almacenamiento_keys = ['limite_admin_mb', 'limite_periodista_mb', 'limite_usuario_mb', 'limite_imagen_mb', 'limite_video_mb', 'notificar_uso_almacenamiento'];
?>

<div class="config-container">
    <header class="config-cabecera">
        <h1 class="config-titulo">⚙️ Panel de Configuración</h1>
        <p>Administra el funcionamiento general del portal. Revisa cada bloque antes de guardar o ejecutar una acción.</p>
    </header>

    <nav class="config-navegacion" aria-label="Secciones de configuración">
        <a href="#config-general">📝 General</a>
        <a href="#config-destacada">⭐ Portada</a>
        <a href="#minificacion">📦 Minificación</a>
    </nav>
    
    <?php if ($mensaje_flash): ?>

        <div class="alert alert-<?php echo $mensaje_flash['tipo']; ?>"><?php echo htmlspecialchars($mensaje_flash['mensaje'], ENT_QUOTES, 'UTF-8'); ?></div>

    <?php endif; ?>

    
    <!-- SECCIÓN: CONFIGURACIÓN DEL SITIO Y LÍMITES (TODO EN UN FORMULARIO) -->
    <div class="config-section" id="config-general">
        
        <div class="config-section-body">
            <div class="config-grid-2">
                
                <!-- Modo Mantenimiento -->
                <div class="mantenimiento-box">
                    <h3 style="margin-top: 0;">🔧 Modo Mantenimiento</h3>
                    <p class="config-explicacion">Impide el acceso público mientras realizas tareas técnicas. Los administradores conservan el acceso.</p>
                    <?php $mantenimiento_activo = file_exists(ROOT_PATH . '.maintenance'); ?>

                    <p>
                        <strong>Estado:</strong> 
                        <?php if ($mantenimiento_activo): ?>

                            <span class="badge-warning">🔒 ACTIVADO</span>
                        <?php else: ?>

                            <span class="badge-success">🌐 NORMAL</span>
                        <?php endif; ?>

                    </p>
                    
                    <?php if ($mantenimiento_activo): ?>

                        <button type="button" onclick="abrirModalDesactivar()" class="btn btn-secondary">
                            🔓 Desactivar mantenimiento
                        </button>
                    <?php else: ?>

                        <button type="button" onclick="abrirModalActivar()" class="btn btn-warning">
                            🔒 Activar mantenimiento
                        </button>
                    <?php endif; ?>

                </div>
                
                <!-- Configuración General + Límites de Almacenamiento (TODO EN UN FORM) -->
                <div class="config-general-box">
                    <h3 style="margin-top: 0;">📝 Configuración General</h3>
                    <p class="config-explicacion">Define el nombre del portal, registros, moderación y otros ajustes generales.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                        <input type="hidden" name="guardar_config" value="1">
                        
                        <!-- Campos de Configuración General (excluyendo almacenamiento) -->
                        <?php foreach ($config as $item): 

                            if (in_array($item['clave'], $claves_almacenamiento)) continue;
                        ?>
                            <div class="campo-form">
                                <label><?php echo htmlspecialchars($item['descripcion'] ?: $item['clave']); ?>:</label>

                                
                                <?php if ($item['clave'] === 'site_description'): ?>

                                    <textarea name="config[<?php echo $item['clave']; ?>]" rows="2"><?php echo htmlspecialchars($item['valor']); ?></textarea>

                                    
                                <?php elseif (in_array($item['clave'], ['comentarios_aprobacion', 'permitir_registro', 'permitir_registro_periodistas'])): ?>

                                    <label class="checkbox-label">
                                        <input type="checkbox" name="config[<?php echo $item['clave']; ?>]" value="1" <?php echo ($item['valor'] == '1') ? 'checked' : ''; ?>>

                                        <span>✔️ Habilitado</span>
                                    </label>
                                    
                                <?php else: ?>

                                    <input type="text" name="config[<?php echo $item['clave']; ?>]" value="<?php echo htmlspecialchars($item['valor']); ?>">

                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>

                        
                        <!-- SECCIÓN: LÍMITES DE ALMACENAMIENTO (dentro del mismo form) -->
                        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #e5e7eb;">
                            <h3 style="margin-bottom: 1rem;">💾 Límites de Almacenamiento</h3>
                            <p class="config-explicacion">Controla el espacio disponible por perfil y el tamaño máximo de imágenes y vídeos. Un valor 0 desactiva el límite cuando se indique.</p>
                            <div class="config-grid-2">
                                <?php foreach ($config as $item):

                                    if (!in_array($item['clave'], $almacenamiento_keys)) continue;
                                ?>
                                    <div class="campo-form">
                                        <label><?php echo htmlspecialchars($item['descripcion'] ?: $item['clave']); ?>:</label>

                                        
                                        <?php if ($item['clave'] === 'notificar_uso_almacenamiento'): ?>

                                            <label class="checkbox-label">
                                                <input type="checkbox" name="config[<?php echo $item['clave']; ?>]" value="1" <?php echo ($item['valor'] == '1') ? 'checked' : ''; ?>>

                                                <span>✔️ Activar notificaciones</span>
                                            </label>
                                            <small>Los usuarios recibirán un email al alcanzar el 80% de su límite</small>
                                        <?php else: ?>

                                            <?php $escala = $escalas_almacenamiento[$item['clave']]; ?>
                                            <input type="number" name="config[<?php echo $item['clave']; ?>]" value="<?php echo htmlspecialchars($item['valor']); ?>" min="<?php echo $escala['min']; ?>" max="<?php echo $escala['max']; ?>" step="<?php echo $escala['step']; ?>">

                                            <small>
                                                <?php echo $escala['min'] === 0 ? '0 = sin límite. ' : ''; ?>
                                                Rango: <?php echo $escala['min']; ?>-<?php echo $escala['max']; ?> MB
                                            </small>
                                        <?php endif; ?>

                                    </div>
                                <?php endforeach; ?>

                            </div>
                            
                            <!-- Estadísticas de uso (solo lectura) -->
                            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                <h3 style="margin-bottom: 1rem;">📊 Uso Actual de Almacenamiento</h3>
                                <p class="config-explicacion">Valores informativos calculados por la aplicación; no pueden editarse.</p>
                                <div class="config-grid-2">
                                    <?php foreach ($config as $item):

                                        if (!in_array($item['clave'], ['total_noticias_imagenes_mb', 'total_avatares_mb'])) continue;
                                    ?>
                                        <div class="campo-form">
                                            <label><?php echo htmlspecialchars($item['descripcion'] ?: $item['clave']); ?>:</label>

                                            <input type="text" value="<?php echo round((float) $item['valor'], 2); ?> MB" readonly disabled style="background: #f3f4f6;">

                                            <small>Total acumulado (solo lectura)</small>
                                        </div>
                                    <?php endforeach; ?>

                                </div>
                            </div>
                        </div>
                        <!-- SECCIÓN: CONFIGURACIÓN RSS -->
<div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #e5e7eb;">
    <h3 style="margin-bottom: 1rem;">📡 Configuración RSS</h3>
    <p class="config-explicacion">Determina durante cuánto tiempo se conservan automáticamente las noticias importadas desde RSS.</p>
    <div class="config-grid-2">
        <?php

        // Obtener valor actual de días
        $stmt_dias = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'rss_dias_limpieza'");
        $stmt_dias->execute();
        $dias_guardados = $stmt_dias->fetchColumn();
        $dias_actual = $dias_guardados === false
            ? 30
            : max(0, min(3650, (int) $dias_guardados));
        ?>
        <div class="campo-form">
            <label>📅 Días que se mantienen noticias RSS:</label>
            <input type="number" name="config[rss_dias_limpieza]" 
                   value="<?php echo $dias_actual; ?>" 

                   min="0" max="3650" step="1">
            <small>0 = sin límite (no se borran automáticamente). Las noticias RSS se eliminarán después de X días.</small>
        </div>
    </div>
</div>
                        <div class="config-acciones-principales"><button type="submit" class="btn btn-primary">💾 Guardar configuración</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN: NOTICIAS DESTACADAS -->
    <div class="config-section" id="config-destacada">
        <div class="config-section-header">
            <span style="font-size: 1.5rem;">⭐</span>
            <h2>Noticias Destacadas</h2>
        </div>
        <div class="config-section-body">
            <p class="config-section-descripcion">Selecciona una única noticia publicada para darle prioridad visual en la portada, o desactiva esta función.</p>
            <div class="mantenimiento-box">
                <?php

                // Obtener noticia destacada actual
                $stmt = $pdo->query("SELECT id_noticia, titulo FROM noticias WHERE destacada = 1 LIMIT 1");
                $destacada_actual = $stmt->fetch();
                
                // Procesar selección de destacada
                if (isset($_POST['guardar_destacada'])) {
                    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
                        $errores[] = '❌ Error de seguridad.';
                    } else {
                        $id_noticia = (int) ($_POST['noticia_destacada'] ?? 0);
                        $activar = isset($_POST['activar_destacada']) ? 1 : 0;
                        
                        if ($activar && $id_noticia > 0) {
                            // Quitar destacada de todas las noticias
                            $pdo->exec("UPDATE noticias SET destacada = 0");
                            // Marcar la nueva como destacada
                            $stmt = $pdo->prepare("UPDATE noticias SET destacada = 1 WHERE id_noticia = ?");
                            $stmt->execute([$id_noticia]);
                            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Noticia destacada activada'];
                        } else {
                            // Desactivar destacada
                            $pdo->exec("UPDATE noticias SET destacada = 0");
                            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => '✅ Noticia destacada desactivada'];
                        }
                        header('Location: ' . route('admin_config'));
                        exit;
                    }
                }
                
                // Obtener noticias para el selector
                $stmt = $pdo->query("
                    SELECT id_noticia, titulo, fecha_publicacion 
                    FROM noticias 
                    WHERE estado = 'publicada' 
                    ORDER BY fecha_publicacion DESC 
                    LIMIT 50
                ");
                $noticias_lista = $stmt->fetchAll();
                ?>
                
                <form method="POST" onsubmit="return confirm('¿Actualizar noticia destacada?')">
                    <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                    <input type="hidden" name="guardar_destacada" value="1">
                    <?php if ($destacada_actual): ?>

                        <div class="destacada-actual">
                            ⭐ <strong>Actual:</strong> <?php echo htmlspecialchars($destacada_actual['titulo']); ?>

                        </div>
                    <?php else: ?>

                        <div class="destacada-actual">
                            ⚠️ <strong>Noticia destacada desactivada</strong> - No se mostrará ninguna destacada en portada
                        </div>
                    <?php endif; ?>

                    <div class="campo-form">
                        <label class="checkbox-label">
                            <input type="checkbox" name="activar_destacada" value="1" <?php echo ($destacada_actual) ? 'checked' : ''; ?> onchange="toggleNoticiaSelector(this)">

                            ⭐ Activar noticia destacada en portada
                        </label>
                    </div>
                    
                    <div class="campo-form" id="selector_noticia_div">
                        <label>📰 Seleccionar noticia destacada:</label>
                        <select name="noticia_destacada" class="privados-filtro-estado" style="width: 100%; padding: 0.5rem;" <?php echo (!$destacada_actual) ? 'disabled' : ''; ?>>

                            <option value="0">-- Seleccionar noticia --</option>
                            <?php foreach ($noticias_lista as $n): ?>

                                <option value="<?php echo $n['id_noticia']; ?>" <?php echo ($destacada_actual && $destacada_actual['id_noticia'] == $n['id_noticia']) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars(mb_substr($n['titulo'], 0, 80)); ?> 

                                    (<?php echo date('d/m/Y', strtotime($n['fecha_publicacion'])); ?>)

                                </option>
                            <?php endforeach; ?>

                        </select>
                        <small class="ayuda">La noticia seleccionada aparecerá destacada en la portada</small>
                    </div>
                    
                    
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">💾 Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleNoticiaSelector(checkbox) {
        var selector = document.querySelector('select[name="noticia_destacada"]');
        if (checkbox.checked) {
            selector.disabled = false;
        } else {
            selector.disabled = true;
        }
    }
    </script>
    
    <!-- SECCIÓN: MINIFICACIÓN -->
    <div class="config-section" id="minificacion">
        <div class="config-section-header">
            <span style="font-size: 1.5rem;">📦</span>
            <h2>Gestión de Minificación</h2>
        </div>
        <div class="config-section-body">
            <p class="config-section-descripcion">El modo desarrollo carga archivos legibles. El modo producción regenera y utiliza CSS reducido para disminuir la transferencia.</p>
            
            <div class="minify-stats">
                <div class="minify-stat-card">
                    <strong>Modo actual:</strong><br>
                    <?php if ($modo_actual === 'production'): ?>

                        <span class="badge-success">🚀 PRODUCCIÓN</span>
                    <?php else: ?>

                        <span class="badge-warning">🔧 DESARROLLO</span>
                    <?php endif; ?>

                </div>
                <div class="minify-stat-card">
                    <strong>Archivos:</strong><br>
                    🎨 CSS: <?php echo count($css_stats); ?><br>

                    📜 JS: <?php echo count($js_stats); ?>

                </div>
                <div class="minify-stat-card">
                    <strong>Reducción:</strong><br>
                    🎨 CSS: -<?php echo $css_reduction; ?>%<br>

                    📜 JS: fuente única

                </div>
            </div>
            
            <div class="minify-buttons">
                <button type="button" onclick="confirmarModoDesarrollo()" class="btn btn-primary">🔧 Modo Desarrollo</button>
                <button type="button" onclick="confirmarModoProduccion()" class="btn btn-secondary">🚀 Modo Producción</button>
                <button type="button" onclick="minificarCompleto()" class="btn btn-warning">🔄 Regenerar CSS</button>
                <button type="button" onclick="verEstadisticasDetalladas()" class="btn btn-primary">📊 Estadísticas</button>
            </div>
            
            <div id="progress-panel" class="progress-panel" style="display: none;">
                <div><strong>📦 Minificando...</strong> <button onclick="cerrarProgress()" style="float:right; background:none; border:none; color:white;">✕</button></div>
                <div id="progress-messages" style="max-height:100px; overflow-y:auto; margin:0.5rem 0;"></div>
                <div class="progress-bar-container"><div id="progress-bar" class="progress-bar">0%</div></div>
            </div>
            
            <?php if (isset($_SESSION['minify_output'])): ?>

                <pre style="background:#1e1e1e; color:#d4d4d4; padding:1rem; border-radius:4px; font-size:0.7rem; overflow:auto; margin-top:1rem;"><?php echo htmlspecialchars($_SESSION['minify_output']); ?></pre>

                <?php unset($_SESSION['minify_output']); ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<!-- MODAL PARA ACTIVAR MANTENIMIENTO -->
<div id="modalActivarMantenimiento" class="modal-mantenimiento">
    <div class="modal-mantenimiento-content">
        <div class="modal-mantenimiento-header">
            <h3>🔒 Activar Modo Mantenimiento</h3>
        </div>
        <div class="modal-mantenimiento-body">
            <p>¿Estás seguro de que quieres activar el modo mantenimiento?</p>
            <div class="modal-mantenimiento-alerta">
                <strong>⚠️ Consecuencias:</strong><br>
                • El sitio quedará OFFLINE para todos los usuarios<br>
                • Solo los administradores podrán acceder<br>
                • Los usuarios normales verán un mensaje de mantenimiento
            </div>
            <p style="font-size: 0.85rem; color: #6b7280;">Esta acción se puede deshacer más tarde.</p>
        </div>
        <div class="modal-mantenimiento-footer">
            <button type="button" class="btn btn-secondary" onclick="cerrarModalActivar()">Cancelar</button>
            <form method="POST" id="formActivarMantenimiento" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                <input type="hidden" name="accion_mantenimiento" value="toggle">
                <input type="hidden" name="mantenimiento" value="1">
                <input type="hidden" name="confirmado" value="1">
                <button type="submit" class="btn btn-warning">🔒 Activar mantenimiento</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL PARA DESACTIVAR MANTENIMIENTO -->
<div id="modalDesactivarMantenimiento" class="modal-mantenimiento">
    <div class="modal-mantenimiento-content">
        <div class="modal-mantenimiento-header">
            <h3>🔓 Desactivar Modo Mantenimiento</h3>
        </div>
        <div class="modal-mantenimiento-body">
            <p>¿Estás seguro de que quieres desactivar el modo mantenimiento?</p>
            <div class="modal-mantenimiento-alerta">
                <strong>✅ Consecuencias:</strong><br>
                • El sitio volverá a ser visible para TODOS los usuarios<br>
                • Los usuarios normales podrán acceder normalmente
            </div>
        </div>
        <div class="modal-mantenimiento-footer">
            <button type="button" class="btn btn-secondary" onclick="cerrarModalDesactivar()">Cancelar</button>
            <form method="POST" id="formDesactivarMantenimiento" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

                <input type="hidden" name="accion_mantenimiento" value="toggle">
                <input type="hidden" name="mantenimiento" value="0">
                <input type="hidden" name="confirmado" value="1">
                <button type="submit" class="btn btn-secondary">🔓 Desactivar mantenimiento</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ESTADÍSTICAS -->
<div id="statsModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>📊 Estadísticas Detalladas</h3>
            <button class="modal-close" onclick="cerrarModal()">✕</button>
        </div>
        <div class="modal-body">
            <h4>🎨 CSS</h4>
            <table style="width:100%">
                <thead><tr><th>Archivo</th><th>Original</th><th>Minificado</th><th>Reducción</th></tr></thead>
                <tbody>
                    <?php foreach ($css_stats as $s): ?>

                    <tr>
                        <td><?php echo $s['name']; ?></td>

                        <td><?php echo round($s['size']/1024,2); ?> KB</td>

                        <td><?php echo round($s['min_size']/1024,2); ?> KB</td>

                        <td><?php echo ($s['reduction']>0) ? '-'.$s['reduction'].'%' : '-'; ?></td>

                    </tr>
                    <?php endforeach; ?>

                </tbody>
                <tfoot><tr style="background:#f0f0f0;"><td><strong>TOTAL</strong></td><td><?php echo round($total_css_orig/1024,2); ?> KB</td><td><?php echo round($total_css_min/1024,2); ?> KB</td><td><?php echo $total_css_orig > 0 ? '-' . round((1-$total_css_min/$total_css_orig)*100) . '%' : '-'; ?></td></tr></tfoot>

            </table>
            <h4 style="margin-top:1rem;">📜 JavaScript (fuente única versionada)</h4>
            <table style="width:100%">
                <thead><tr><th>Archivo</th><th>Tamaño</th></tr></thead>
                <tbody>
                    <?php foreach ($js_stats as $s): ?>

                    <tr>
                        <td><?php echo $s['name']; ?></td>

                        <td><?php echo round($s['size']/1024,2); ?> KB</td>

                    </tr>
                    <?php endforeach; ?>

                </tbody>
                <tfoot><tr style="background:#f0f0f0;"><td><strong>TOTAL</strong></td><td><?php echo round($total_js_orig/1024,2); ?> KB</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<script>
// ============================================
// MODAL MANTENIMIENTO
// ============================================
function abrirModalActivar() {
    document.getElementById('modalActivarMantenimiento').style.display = 'flex';
}

function cerrarModalActivar() {
    document.getElementById('modalActivarMantenimiento').style.display = 'none';
}

function abrirModalDesactivar() {
    document.getElementById('modalDesactivarMantenimiento').style.display = 'flex';
}

function cerrarModalDesactivar() {
    document.getElementById('modalDesactivarMantenimiento').style.display = 'none';
}

window.onclick = function(event) {
    var modalActivar = document.getElementById('modalActivarMantenimiento');
    var modalDesactivar = document.getElementById('modalDesactivarMantenimiento');
    var modalStats = document.getElementById('statsModal');
    
    if (event.target === modalActivar) cerrarModalActivar();
    if (event.target === modalDesactivar) cerrarModalDesactivar();
    if (event.target === modalStats) cerrarModal();
}

// ============================================
// MINIFICACIÓN
// ============================================
function confirmarModoDesarrollo() {
    if (!confirm('🔧 ¿Cambiar a modo DESARROLLO?\n\nLos archivos se servirán SIN minificar.')) return;
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">' +

                     '<input type="hidden" name="minify_accion" value="modo_desarrollo">' +
                     '<input type="hidden" name="confirmado" value="1">';
    document.body.appendChild(form);
    form.submit();
}

function confirmarModoProduccion() {
    if (!confirm('🚀 ¿Cambiar a modo PRODUCCIÓN?\n\nSe regenerará el CSS de producción.')) return;
    if (!confirm('⚠️ CONFIRMACIÓN: Se regenerarán las copias CSS. ¿Continuar?')) return;
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">' +

                     '<input type="hidden" name="minify_accion" value="modo_produccion">' +
                     '<input type="hidden" name="confirmado" value="1">';
    document.body.appendChild(form);
    form.submit();
}

function minificarCompleto() {
    if (!confirm('🔄 ¿Regenerar ahora el CSS de producción?')) return;
    
    var panel = document.getElementById('progress-panel');
    var messages = document.getElementById('progress-messages');
    var bar = document.getElementById('progress-bar');
    
    panel.style.display = 'block';
    messages.innerHTML = '';
    bar.style.width = '0%';
    bar.textContent = '0%';
    
    var percent = 0;
    var interval = setInterval(function() {
        percent += 10;
        bar.style.width = percent + '%';
        bar.textContent = percent + '%';
        if (percent >= 100) clearInterval(interval);
    }, 300);
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">' +

                     '<input type="hidden" name="minify_accion" value="regenerar">' +
                     '<input type="hidden" name="confirmado" value="1">';
    document.body.appendChild(form);
    form.submit();
}

function cerrarProgress() {
    document.getElementById('progress-panel').style.display = 'none';
}

function verEstadisticasDetalladas() {
    document.getElementById('statsModal').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('statsModal').style.display = 'none';
}
</script>

<?php
ob_end_flush();
require_once __DIR__ . '/../partials/footer.php';
?>
