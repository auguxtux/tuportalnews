<?php
declare(strict_types=1);


/**
 * EDITAR PERIODISTA
 * Con modal para manejar noticias al desactivar/bloquear
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    mensajeFlash('error', 'ID de periodista no válido');
    redireccionar(route('admin_periodistas'));
}

$pdo = db();
$errores = [];
$mensaje = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !verificarTokenCSRF($_POST['csrf_token'] ?? '')
) {
    mensajeFlash('error', 'Error de seguridad. Recarga la página e inténtalo de nuevo.');
    redireccionar(route('admin_editar_periodista', ['id' => $id]));
    exit;
}

// Obtener datos del periodista
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id AND rol = 'periodista'");
$stmt->execute([':id' => $id]);
$periodista = $stmt->fetch();

if (!$periodista) {
    mensajeFlash('error', 'Articulista no encontrado');
    redireccionar(route('admin_periodistas'));
}

// Contar noticias del periodista
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_noticias,
        SUM(CASE WHEN privada = 1 THEN 1 ELSE 0 END) as noticias_privadas
    FROM noticias 
    WHERE id_autor = ?
");
$stmt->execute([$id]);
$stats_noticias = $stmt->fetch();

// ============================================
// PROCESAR CAMBIO DE ESTADO (con modal)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    $accion_noticias = $_POST['accion_noticias'] ?? 'nada';
    $confirmar = isset($_POST['confirmar']) ? (int)$_POST['confirmar'] : 0;
    
    if ($confirmar && $nuevo_estado && in_array($nuevo_estado, ['inactivo', 'bloqueado', 'activo'])) {
        try {
            $pdo->beginTransaction();
            
            $noticias_afectadas = 0;
            
            // Aplicar acción a las noticias si se está desactivando o bloqueando
            if (in_array($nuevo_estado, ['inactivo', 'bloqueado']) && $accion_noticias !== 'nada') {
                switch ($accion_noticias) {
                    case 'archivar':
                        $stmt = $pdo->prepare("UPDATE noticias SET estado = 'archivada' WHERE id_autor = ?");
                        $stmt->execute([$id]);
                        $noticias_afectadas = $stmt->rowCount();
                        break;
                    case 'publicar_privadas':
                        $stmt = $pdo->prepare("UPDATE noticias SET privada = 0 WHERE id_autor = ? AND privada = 1");
                        $stmt->execute([$id]);
                        $noticias_afectadas = $stmt->rowCount();
                        break;
                    case 'ocultar':
                        $stmt = $pdo->prepare("UPDATE noticias SET estado = 'archivada', privada = 1 WHERE id_autor = ?");
                        $stmt->execute([$id]);
                        $noticias_afectadas = $stmt->rowCount();
                        break;
                }
            }
            
            // Cambiar estado del usuario
            $stmt = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
            $stmt->execute([$nuevo_estado, $id]);
            
            $estado_texto = [
                'activo' => 'activado',
                'inactivo' => 'desactivado',
                'bloqueado' => 'bloqueado'
            ][$nuevo_estado] ?? $nuevo_estado;
            
            $mensaje = "✅ Articulista {$estado_texto} correctamente.";
            if ($noticias_afectadas > 0) {
                $accion_texto = [
                    'archivar' => 'archivadas',
                    'publicar_privadas' => 'convertidas a públicas',
                    'ocultar' => 'ocultas'
                ][$accion_noticias] ?? 'procesadas';
                $mensaje .= " Se {$accion_texto} {$noticias_afectadas} noticias.";
            }
            
            $pdo->commit();
            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => $mensaje];
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['mensaje_flash'] = ['tipo' => 'error', 'mensaje' => 'No se pudo cambiar el estado del periodista.'];
        }
        
        redireccionar(route('admin_periodistas'));
        exit;
    }
}

// ============================================
// PROCESAR FORMULARIO DE EDICIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['cambiar_estado'])) {
    $nombre = limpiarDatos(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : '');
    $email = limpiarDatos(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $telefono = limpiarDatos(is_string($_POST['telefono'] ?? null) ? $_POST['telefono'] : '');
    $ciudad = limpiarDatos(is_string($_POST['ciudad'] ?? null) ? $_POST['ciudad'] : '');
    $biografia = limpiarDatos(is_string($_POST['biografia'] ?? null) ? $_POST['biografia'] : '');
    $datosColaboracion = limpiarDatos(is_string($_POST['datos_colaboracion'] ?? null) ? $_POST['datos_colaboracion'] : '');
    $estado_solicitado = is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '';
    $estado = in_array($estado_solicitado, ['activo', 'inactivo', 'bloqueado'], true)
        ? $estado_solicitado
        : $periodista['estado'];
    
    // Validaciones
    if (empty($nombre)) $errores[] = 'El nombre es obligatorio';
    if (!validarEmail($email)) $errores[] = 'Email no válido';
    if (!validarTelefono($telefono)) $errores[] = 'Teléfono no válido';
    if (empty($ciudad)) $errores[] = 'La ciudad es obligatoria';
    if (mb_strlen($nombre) > 150) $errores[] = 'El nombre no puede superar 150 caracteres';
    if (mb_strlen($email) > 255) $errores[] = 'El email no puede superar 255 caracteres';
    if (mb_strlen($ciudad) > 120) $errores[] = 'La ciudad no puede superar 120 caracteres';
    if (mb_strlen($biografia) > 500) $errores[] = 'La biografía no puede superar 500 caracteres';
    if ($datosColaboracion === '') $errores[] = 'Los datos de interés para colaborar son obligatorios';
    if (mb_strlen($datosColaboracion) > 2000) $errores[] = 'Los datos de interés para colaborar no pueden superar 2000 caracteres';

    // Verificar email único (excepto el actual)
    if ($email !== $periodista['email']) {
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id");
        $stmt->execute([':email' => $email, ':id' => $id]);
        if ($stmt->fetch()) {
            $errores[] = 'El email ya está registrado por otro usuario';
        }
    }
    
    // Cambiar avatar si se subió
    $avatar = $periodista['avatar'];
    $nuevo_avatar_subido = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload = new UploadHandler($_FILES['avatar'], 'perfil');
        $nuevo_avatar = $upload->subir();
        
        if ($nuevo_avatar) {
            $avatar = $nuevo_avatar;
            $nuevo_avatar_subido = $nuevo_avatar;
        } else {
            $errores_upload = $upload->getErrores();
            $errores = array_merge($errores, $errores_upload);
        }
    }
    
    // Cambiar contraseña si se proporciona
    $password_sql = "";
    $params = [
        ':nombre' => $nombre,
        ':email' => $email,
        ':telefono' => $telefono,
        ':ciudad' => $ciudad,
        ':biografia' => $biografia,
        ':datos_colaboracion' => $datosColaboracion,
        ':estado' => $estado,
        ':avatar' => $avatar,
        ':id' => $id
    ];
    
    $password_nueva = (string) ($_POST['password_nueva'] ?? '');
    $password_confirmar = (string) ($_POST['password_confirmar'] ?? '');

    if ($password_nueva !== '') {
        if (strlen($password_nueva) > 4096) {
            $errores[] = 'La contraseña supera la longitud permitida';
        } elseif (strlen($password_nueva) < 10) {
            $errores[] = 'La contraseña debe tener al menos 10 caracteres';
        } elseif ($password_nueva !== $password_confirmar) {
            $errores[] = 'Las contraseñas no coinciden';
        } else {
            $password_sql = ", password = :password";
            $params[':password'] = password_hash($password_nueva, PASSWORD_DEFAULT);
        }
    }
    
    if (empty($errores)) {
        $sql = "UPDATE usuarios SET 
                nombre = :nombre,
                email = :email,
                telefono = :telefono,
                ciudad = :ciudad,
                biografia = :biografia,
                datos_colaboracion = :datos_colaboracion,
                estado = :estado,
                avatar = :avatar
                $password_sql
                WHERE id_usuario = :id";
        
        $stmt = $pdo->prepare($sql);

        try {
            $actualizado = $stmt->execute($params);
        } catch (Throwable $e) {
            $actualizado = false;
            registrarErrorInterno('ADMIN.PERIODISTA.ACTUALIZAR', $e);
        }

        if ($actualizado) {
            if (
                $nuevo_avatar_subido !== null
                && !empty($periodista['avatar'])
                && !in_array($periodista['avatar'], ['default-avatar.png', 'default.jpg'], true)
            ) {
                $ruta_anterior = UPLOAD_PERFILES . $periodista['avatar'];
                if (is_file($ruta_anterior)) {
                    unlink($ruta_anterior);
                }
            }

            $_SESSION['mensaje_flash'] = ['tipo' => 'success', 'mensaje' => 'Articulista actualizado correctamente'];
            redireccionar(route('admin_periodistas'));
        } else {
            $errores[] = 'Error al actualizar';
        }
    }

    if (!empty($errores) && $nuevo_avatar_subido !== null) {
        $ruta_nueva = UPLOAD_PERFILES . $nuevo_avatar_subido;
        if (is_file($ruta_nueva)) {
            unlink($ruta_nueva);
        }
    }
}

// Recuperar mensaje flash
$mensaje_flash = isset($_SESSION['mensaje_flash']) ? $_SESSION['mensaje_flash'] : null;
unset($_SESSION['mensaje_flash']);

$titulo_pagina = 'Editar Articulista';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('admin-editar-periodista.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('admin-confirm-modal.css'); ?>">



<div class="admin-editar-periodista-container">
    
    <?php if ($mensaje_flash): ?>

        <div class="admin-editar-periodista-alerta admin-editar-periodista-alerta-<?php echo $mensaje_flash['tipo']; ?>">

            <?php echo htmlspecialchars($mensaje_flash['mensaje'], ENT_QUOTES, 'UTF-8'); ?>

        </div>
    <?php endif; ?>

    
    <h1 class="admin-editar-periodista-titulo">✏️ Editar Articulista: <?php echo htmlspecialchars($periodista['nombre']); ?></h1>

    
    <!-- Estadísticas de noticias -->
    <div class="admin-editar-periodista-card" style="margin-bottom: 1rem; background: #f8fafc;">
        <div style="display: flex; gap: 2rem; padding: 0.75rem; justify-content: space-around;">
            <div style="text-align: center;">
                <div style="font-size: 1.5rem; font-weight: bold; color: #2563eb;"><?php echo $stats_noticias['total_noticias']; ?></div>

                <div style="font-size: 0.7rem; color: #6b7280;">Total noticias</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.5rem; font-weight: bold; color: #f59e0b;"><?php echo $stats_noticias['noticias_privadas']; ?></div>

                <div style="font-size: 0.7rem; color: #6b7280;">Noticias privadas</div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($errores)): ?>

        <div class="admin-editar-periodista-alerta admin-editar-periodista-alerta-error">
            <ul class="admin-editar-periodista-error-list">
                <?php foreach ($errores as $e): ?>

                    <li><?php echo $e; ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <div class="admin-editar-periodista-card">
        <form method="POST" enctype="multipart/form-data" class="admin-editar-periodista-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="admin-editar-periodista-grid-2">
                
                <!-- COLUMNA IZQUIERDA -->
                <div class="admin-editar-periodista-columna">
                    <div class="admin-editar-periodista-campo">
                        <label for="nombre">👤 Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" required maxlength="150"
                               value="<?php echo htmlspecialchars(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : $periodista['nombre']); ?>">

                    </div>
                    
                    <div class="admin-editar-periodista-campo">
                        <label for="email">📧 Email *</label>
                        <input type="email" id="email" name="email" required maxlength="255"
                               value="<?php echo htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : $periodista['email']); ?>">

                    </div>
                    
                    <div class="admin-editar-periodista-campo">
                        <label for="telefono">📞 Teléfono *</label>
                        <input type="tel" id="telefono" name="telefono" required 
                               value="<?php echo htmlspecialchars(is_string($_POST['telefono'] ?? null) ? $_POST['telefono'] : $periodista['telefono']); ?>">

                    </div>
                    
                    <div class="admin-editar-periodista-campo">
                        <label for="ciudad">🏙️ Ciudad *</label>
                        <input type="text" id="ciudad" name="ciudad" required maxlength="120"
                               value="<?php echo htmlspecialchars(is_string($_POST['ciudad'] ?? null) ? $_POST['ciudad'] : $periodista['ciudad']); ?>">

                    </div>
                </div>
                
                <!-- COLUMNA DERECHA -->
                <div class="admin-editar-periodista-columna">
                    <div class="admin-editar-periodista-campo">
                        <label for="estado">📌 Estado</label>
                        <select id="estado" name="estado" onchange="mostrarModalSiCambiaAInactivo(this)">
                            <option value="activo" <?php echo ($_POST['estado'] ?? $periodista['estado']) == 'activo' ? 'selected' : ''; ?>>✅ Activo</option>

                            <option value="inactivo" <?php echo ($_POST['estado'] ?? $periodista['estado']) == 'inactivo' ? 'selected' : ''; ?>>⭕ Inactivo</option>

                            <option value="bloqueado" <?php echo ($_POST['estado'] ?? $periodista['estado']) == 'bloqueado' ? 'selected' : ''; ?>>🔒 Bloqueado</option>

                        </select>
                    </div>
                    
                    <div class="admin-editar-periodista-campo">
                        <label for="biografia">📝 Biografía</label>
                        <textarea id="biografia" name="biografia" rows="4" maxlength="500"><?php echo htmlspecialchars(is_string($_POST['biografia'] ?? null) ? $_POST['biografia'] : ($periodista['biografia'] ?? '')); ?></textarea>

                    </div>

                    <div class="admin-editar-periodista-campo">
                        <label for="datos_colaboracion">🤝 Datos privados de interés para colaborar *</label>
                        <textarea id="datos_colaboracion" name="datos_colaboracion" rows="6" maxlength="2000" required><?php echo htmlspecialchars(is_string($_POST['datos_colaboracion'] ?? null) ? $_POST['datos_colaboracion'] : ($periodista['datos_colaboracion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small>Solo visible para el usuario y los administradores.</small>
                    </div>
                </div>
            </div>
            
            <!-- SECCIÓN AVATAR -->
            <div class="admin-editar-periodista-seccion">
                <h2 class="admin-editar-periodista-seccion-titulo">🖼️ Avatar</h2>
                <div class="admin-editar-periodista-avatar-actual">
                    <p class="admin-editar-periodista-avatar-label">Avatar actual:</p>
                    <img src="<?php echo base_url('uploads/perfiles/' . ($periodista['avatar'] ?? 'default-avatar.png')); ?>" 

                         alt="Avatar" class="admin-editar-periodista-avatar-imagen">
                </div>
                
                <div class="admin-editar-periodista-campo">
                    <label for="avatar">📁 Cambiar avatar</label>
                    <input type="file" id="avatar" name="avatar" accept="image/*">
                    <small>Formatos: JPG, PNG, GIF, WEBP (máx. 5MB)</small>
                    <img id="preview-avatar" class="admin-editar-periodista-avatar-preview" style="display: none;">
                </div>
            </div>
            
            <!-- SECCIÓN CONTRASEÑA -->
            <div class="admin-editar-periodista-seccion">
                <h2 class="admin-editar-periodista-seccion-titulo">🔒 Cambiar contraseña</h2>
                <p class="admin-editar-periodista-seccion-nota">Dejar en blanco para no modificar la contraseña actual</p>
                
                <div class="admin-editar-periodista-grid-2">
                    <div class="admin-editar-periodista-campo">
                        <label for="password_nueva">🔑 Nueva contraseña</label>
                        <input type="password" id="password_nueva" name="password_nueva" minlength="10" maxlength="4096">
                    </div>
                    
                    <div class="admin-editar-periodista-campo">
                        <label for="password_confirmar">✅ Confirmar contraseña</label>
                        <input type="password" id="password_confirmar" name="password_confirmar" minlength="10" maxlength="4096">
                    </div>
                </div>
            </div>
            
            <!-- ACCIONES -->
            <div class="admin-editar-periodista-acciones">
                <button type="submit" class="admin-editar-periodista-btn admin-editar-periodista-btn-guardar">
                    💾 Guardar cambios
                </button>
                <a href="<?php echo route('admin_periodistas'); ?>" class="admin-editar-periodista-btn admin-editar-periodista-btn-cancelar">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
    
</div>

<!-- MODAL PARA CAMBIO DE ESTADO A INACTIVO/BLOQUEADO -->
<div id="modalEstado" class="modal-overlay" style="display: none;">
    <div class="modal-contenido">
        <div class="modal-header">
            <h3 id="modalTitulo">⚠️ Cambiar estado del periodista</h3>
            <button type="button" class="modal-cerrar" onclick="cerrarModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <p id="modalTexto"></p>
            
            <div class="modal-alerta" id="modalAlerta">
                <strong>📰 <?php echo $stats_noticias['total_noticias']; ?> noticias</strong> 

                (<?php echo $stats_noticias['noticias_privadas']; ?> privadas) están asociadas a este periodista.

            </div>
            
            <p>¿Qué deseas hacer con sus noticias?</p>
            
            <form method="POST" action="" id="modalForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="cambiar_estado" value="1">
                <input type="hidden" name="nuevo_estado" id="nuevoEstado" value="">
                <input type="hidden" name="confirmar" value="1">
                
                <div class="modal-opciones">
                    <label class="modal-opcion">
                        <input type="radio" name="accion_noticias" value="nada" checked>
                        <div>
                            <strong>⏸️ No hacer nada</strong>
                            <small>Las noticias quedan como están (seguirán visibles)</small>
                        </div>
                    </label>
                    
                    <label class="modal-opcion">
                        <input type="radio" name="accion_noticias" value="archivar">
                        <div>
                            <strong>📦 Archivar noticias</strong>
                            <small>Las noticias se archivan (ocultas, se pueden recuperar)</small>
                        </div>
                    </label>
                    
                    <label class="modal-opcion">
                        <input type="radio" name="accion_noticias" value="publicar_privadas">
                        <div>
                            <strong>🌍 Convertir privadas a públicas</strong>
                            <small>Solo sus noticias privadas se vuelven visibles para todos</small>
                        </div>
                    </label>
                    
                    <label class="modal-opcion">
                        <input type="radio" name="accion_noticias" value="ocultar">
                        <div>
                            <strong>🔒 Ocultar todas</strong>
                            <small>Todas sus noticias se archivan y marcan como privadas</small>
                        </div>
                    </label>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-warning" id="modalBoton">Confirmar cambio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables para el estado original
var estadoOriginal = '<?php echo $periodista['estado']; ?>';

var tieneNoticias = <?php echo $stats_noticias['total_noticias'] > 0 ? 'true' : 'false'; ?>;


function mostrarModalSiCambiaAInactivo(select) {
    var nuevoEstado = select.value;
    
    // Si el estado es activo o no hay cambio, enviar formulario normalmente
    if (nuevoEstado === 'activo' || nuevoEstado === estadoOriginal) {
        // No hay necesidad de modal, enviar formulario
        select.form.submit();
        return;
    }
    
    // Si se cambia a inactivo o bloqueado, mostrar modal
    var modal = document.getElementById('modalEstado');
    var titulo = document.getElementById('modalTitulo');
    var texto = document.getElementById('modalTexto');
    var boton = document.getElementById('modalBoton');
    var nuevoEstadoHidden = document.getElementById('nuevoEstado');
    
    if (nuevoEstado === 'inactivo') {
        titulo.innerHTML = '⚠️ Desactivar periodista';
        texto.innerHTML = 'El periodista <strong><?php echo htmlspecialchars($periodista['nombre']); ?></strong> será desactivado.';

        boton.innerHTML = 'Desactivar periodista';
        boton.className = 'btn-warning';
    } else {
        titulo.innerHTML = '⚠️ Bloquear periodista';
        texto.innerHTML = 'El periodista <strong><?php echo htmlspecialchars($periodista['nombre']); ?></strong> será bloqueado.';

        boton.innerHTML = 'Bloquear periodista';
        boton.className = 'btn-danger';
    }
    
    nuevoEstadoHidden.value = nuevoEstado;
    
    // Si no tiene noticias, ocultar opciones
    if (!tieneNoticias) {
        var opciones = document.querySelectorAll('.modal-opcion');
        for (var i = 0; i < opciones.length; i++) {
            if (opciones[i].querySelector('input').value !== 'nada') {
                opciones[i].style.display = 'none';
            }
        }
    }
    
    modal.style.display = 'flex';
    return false;
}

function cerrarModal() {
    // Restaurar el select al estado original
    var selectEstado = document.getElementById('estado');
    selectEstado.value = estadoOriginal;
    document.getElementById('modalEstado').style.display = 'none';
}

// Vista previa de avatar
document.addEventListener('DOMContentLoaded', function() {
    const inputAvatar = document.getElementById('avatar');
    const preview = document.getElementById('preview-avatar');
    
    if (inputAvatar && preview) {
        inputAvatar.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
