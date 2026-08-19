<?php
declare(strict_types=1);


/**
 * PERFIL DE ADMINISTRADOR
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';

// Requerir acceso de administrador
Permisos::requerirAdmin();

$pdo = db();
$id_usuario = $_SESSION['usuario_id'];
$mensaje = '';
$errores = [];

// Obtener datos actuales
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
$stmt->execute([':id' => $id_usuario]);
$usuario = $stmt->fetch();

if (!$usuario) {
    mensajeFlash('error', 'Usuario no encontrado');
    redireccionar(route('logout'));
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
    $errores[] = 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // ACTUALIZAR DATOS PERSONALES
    if ($accion === 'actualizar_perfil') {
        $nombre = limpiarDatos($_POST['nombre'] ?? '');
        $telefono = limpiarDatos($_POST['telefono'] ?? '');
        $ciudad = limpiarDatos($_POST['ciudad'] ?? '');
        $biografia = limpiarDatos($_POST['biografia'] ?? '');
        
        if (empty($nombre)) $errores[] = 'El nombre es obligatorio';
        if (!validarTelefono($telefono)) $errores[] = 'Teléfono no válido';
        if (empty($ciudad)) $errores[] = 'La ciudad es obligatoria';
        if (mb_strlen($nombre) > 150) $errores[] = 'El nombre no puede superar 150 caracteres';
        if (mb_strlen($ciudad) > 120) $errores[] = 'La ciudad no puede superar 120 caracteres';
        if (mb_strlen($biografia) > 500) $errores[] = 'La biografía no puede superar 500 caracteres';

        if (empty($errores)) {
            $sql = "UPDATE usuarios SET 
                    nombre = :nombre,
                    telefono = :telefono,
                    ciudad = :ciudad,
                    biografia = :biografia
                    WHERE id_usuario = :id";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                ':nombre' => $nombre,
                ':telefono' => $telefono,
                ':ciudad' => $ciudad,
                ':biografia' => $biografia,
                ':id' => $id_usuario
            ])) {
                $_SESSION['usuario_nombre'] = $nombre;
                $mensaje = ['tipo' => 'success', 'texto' => 'Perfil actualizado correctamente'];
                
                // Recargar datos
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
                $stmt->execute([':id' => $id_usuario]);
                $usuario = $stmt->fetch();
            }
        }
    }
    
    // CAMBIAR CONTRASEÑA
    if ($accion === 'cambiar_password') {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        if (strlen($password_actual) > 4096) {
            $errores[] = 'La contraseña actual supera la longitud permitida';
        } elseif (!password_verify($password_actual, $usuario['password'])) {
            $errores[] = 'La contraseña actual no es correcta';
        }
        
        if (strlen($password_nueva) > 4096) {
            $errores[] = 'La nueva contraseña supera la longitud permitida';
        } elseif (strlen($password_nueva) < 10) {
            $errores[] = 'La nueva contraseña debe tener al menos 10 caracteres';
        }
        
        if ($password_nueva !== $password_confirmar) {
            $errores[] = 'Las contraseñas no coinciden';
        }
        
        if (empty($errores)) {
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET password = :password WHERE id_usuario = :id";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([':password' => $password_hash, ':id' => $id_usuario])) {
                $mensaje = ['tipo' => 'success', 'texto' => 'Contraseña actualizada correctamente'];
            }
        }
    }
    
    // CAMBIAR AVATAR
    if ($accion === 'cambiar_avatar' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload = new UploadHandler($_FILES['avatar'], 'perfil');
        $nombre_archivo = $upload->subir();
        
        if ($nombre_archivo) {
            $stmt = $pdo->prepare("UPDATE usuarios SET avatar = :avatar WHERE id_usuario = :id");
            try {
                $avatar_actualizado = $stmt->execute([':avatar' => $nombre_archivo, ':id' => $id_usuario]);
            } catch (Throwable $e) {
                $avatar_actualizado = false;
                registrarErrorInterno('ADMIN.PERFIL.AVATAR_ACTUALIZAR', $e);
            }

            if ($avatar_actualizado) {
                if ($usuario['avatar'] && !in_array($usuario['avatar'], ['default-avatar.png', 'default.jpg'], true)) {
                    $ruta_anterior = UPLOAD_PERFILES . $usuario['avatar'];
                    if (is_file($ruta_anterior)) {
                        unlink($ruta_anterior);
                    }
                }

                $_SESSION['usuario_avatar'] = $nombre_archivo;
                $mensaje = ['tipo' => 'success', 'texto' => 'Avatar actualizado correctamente'];
                
                // Recargar datos
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
                $stmt->execute([':id' => $id_usuario]);
                $usuario = $stmt->fetch();
            } else {
                $ruta_nueva = UPLOAD_PERFILES . $nombre_archivo;
                if (is_file($ruta_nueva)) {
                    unlink($ruta_nueva);
                }
                $errores[] = 'Error al actualizar el avatar';
            }
        } else {
            $errores_upload = $upload->getErrores();
            $errores = array_merge($errores, $errores_upload);
        }
    }
    
    // ELIMINAR AVATAR
    if ($accion === 'eliminar_avatar') {
        $avatar_anterior = $usuario['avatar'] ?? '';
        $stmt = $pdo->prepare("UPDATE usuarios SET avatar = 'default-avatar.png' WHERE id_usuario = :id");
        try {
            $avatar_eliminado = $stmt->execute([':id' => $id_usuario]);
        } catch (Throwable $e) {
            $avatar_eliminado = false;
            registrarErrorInterno('ADMIN.PERFIL.AVATAR_ELIMINAR', $e);
        }

        if ($avatar_eliminado) {
            if ($avatar_anterior && !in_array($avatar_anterior, ['default-avatar.png', 'default.jpg'], true)) {
                $ruta_avatar = UPLOAD_PERFILES . $avatar_anterior;
                if (is_file($ruta_avatar)) {
                    unlink($ruta_avatar);
                }
            }

            $_SESSION['usuario_avatar'] = 'default-avatar.png';
            $mensaje = ['tipo' => 'success', 'texto' => 'Avatar eliminado correctamente'];
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
            $stmt->execute([':id' => $id_usuario]);
            $usuario = $stmt->fetch();
        } else {
            $errores[] = 'Error al eliminar el avatar';
        }
    }
}

$titulo_pagina = 'Mi perfil de Admin';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('perfiles.css'); ?>">


<div class="perfiles-container">
    
    <h1 class="perfiles-titulo">👑 Mi perfil de Admin</h1>
    
    <?php if ($mensaje): ?>

        <div class="perfiles-alerta perfiles-alerta-<?php echo $mensaje['tipo']; ?>">

            <?php echo htmlspecialchars($mensaje['texto'], ENT_QUOTES, 'UTF-8'); ?>

        </div>
    <?php endif; ?>

    
    <?php if (!empty($errores)): ?>

        <div class="perfiles-alerta perfiles-alerta-error">
            <ul class="perfiles-error-list">
                <?php foreach ($errores as $e): ?>

                    <li><?php echo $e; ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <!-- Sistema de pestañas -->
    <div class="perfiles-pestanas">
        <button class="perfiles-pestana active" onclick="adminPerfilMostrarPestana('datos')">📋 Datos personales</button>
        <button class="perfiles-pestana" onclick="adminPerfilMostrarPestana('avatar')">🖼️ Avatar</button>
        <button class="perfiles-pestana" onclick="adminPerfilMostrarPestana('password')">🔒 Cambiar contraseña</button>
        <div class="perfiles-clean"></div>
        <?php if ($usuario['rol'] === 'admin'): ?>

            <button class="perfiles-pestana" onclick="adminPerfilMostrarPestana('seguridad')">🛡️ Seguridad</button>
        <?php endif; ?>

    </div>
    
    <!-- PESTAÑA DATOS PERSONALES -->
    <div id="perfiles-pestana-datos" class="perfiles-pestana-contenido active">
        <div class="perfiles-formulario">
            <h2 class="perfiles-subtitulo">📋 Datos personales</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="actualizar_perfil">
                
                <div class="perfiles-campo-form">
                    <label>📧 Email (no editable por seguridad)</label>
                    <input type="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled class="perfiles-campo-deshabilitado">

                    <small>El email no se puede cambiar directamente</small>
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_nombre">👤 Nombre completo *</label>
                    <input type="text" id="admin_perfil_nombre" name="nombre" required maxlength="150"
                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>">

                </div>
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_telefono">📞 Teléfono *</label>
                    <input type="tel" id="admin_perfil_telefono" name="telefono" required 
                           value="<?php echo htmlspecialchars($usuario['telefono']); ?>"

                           pattern="[6-9][0-9]{8}"
                           title="Teléfono español de 9 dígitos">
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_ciudad">🏙️ Ciudad *</label>
                    <input type="text" id="admin_perfil_ciudad" name="ciudad" required maxlength="120"
                           value="<?php echo htmlspecialchars($usuario['ciudad']); ?>">

                </div>
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_biografia">📝 Biografía</label>
                    <textarea id="admin_perfil_biografia" name="biografia" rows="5" maxlength="500"
                              placeholder="Información adicional sobre ti..."><?php echo htmlspecialchars($usuario['biografia'] ?? ''); ?></textarea>

                </div>
                
                <div class="perfiles-campo-form">
                    <label>⭐ Rol</label>
                    <input type="text" value="Admin" disabled class="perfiles-campo-deshabilitado perfiles-campo-rol">
                </div>
                
                <div class="perfiles-acciones">
                    <button type="submit" class="perfiles-btn perfiles-btn-principal">💾 Guardar cambios</button>
                    <div class="perfiles-clean"></div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- PESTAÑA AVATAR -->
    <div id="perfiles-pestana-avatar" class="perfiles-pestana-contenido">
        <div class="perfiles-formulario">
            <h2 class="perfiles-subtitulo">🖼️ Mi avatar</h2>
            
            <div class="perfiles-avatar-actual">
                <h3 class="perfiles-avatar-titulo">Avatar actual</h3>
                <div class="perfiles-avatar-imagen-container">
                    <img src="<?php echo base_url('uploads/perfiles/' . ($usuario['avatar'] ?? 'default-avatar.png')); ?>" 

                         alt="Avatar" 
                         class="perfiles-avatar-imagen">
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="cambiar_avatar">
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_avatar">📁 Seleccionar nueva imagen</label>
                    <input type="file" id="admin_perfil_avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB</small>
                    <img id="admin_perfil_preview_imagen" src="#" alt="Vista previa" style="display: none; max-width: 100%; margin-top: 1rem; border-radius: 0.375rem;">
                </div>
                
                <button type="submit" class="perfiles-btn perfiles-btn-principal">⬆️ Subir avatar</button>
            </form>
            
            <?php if ($usuario['avatar'] && !in_array($usuario['avatar'], ['default-avatar.png', 'default.jpg'], true)): ?>

                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="accion" value="eliminar_avatar">
                    <button type="submit" class="perfiles-btn perfiles-btn-secondary" onclick="return confirm('¿Eliminar avatar actual?')">
                        🗑️ Eliminar avatar
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- PESTAÑA CAMBIAR CONTRASEÑA -->
    <div id="perfiles-pestana-password" class="perfiles-pestana-contenido">
        <div class="perfiles-formulario">
            <h2 class="perfiles-subtitulo">🔒 Cambiar contraseña</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="cambiar_password">
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_password_actual">🔑 Contraseña actual *</label>
                    <input type="password" id="admin_perfil_password_actual" name="password_actual" required maxlength="4096">
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_password_nueva">🔒 Nueva contraseña *</label>
                    <input type="password" id="admin_perfil_password_nueva" name="password_nueva" required minlength="10" maxlength="4096">
                    <small>Mínimo 10 caracteres</small>
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="admin_perfil_password_confirmar">✅ Confirmar nueva contraseña *</label>
                    <input type="password" id="admin_perfil_password_confirmar" name="password_confirmar" required minlength="10" maxlength="4096">
                </div>
                
                <button type="submit" class="perfiles-btn perfiles-btn-principal">🔄 Cambiar contraseña</button>
                <div class="perfiles-clean"></div>
            </form>
        </div>
    </div>
    
    <!-- PESTAÑA SEGURIDAD (solo para admins) -->
    <?php if ($usuario['rol'] === 'admin'): ?>

    <div id="perfiles-pestana-seguridad" class="perfiles-pestana-contenido">
        <div class="perfiles-formulario">
            <h2 class="perfiles-subtitulo">🛡️ Configuración de seguridad</h2>
            
            <div class="perfiles-seguridad-info">
                <h3 class="perfiles-seguridad-titulo">📋 Información de la cuenta</h3>
                <div class="perfiles-info-grid">
                    <p><strong>ID de usuario:</strong> <?php echo $usuario['id_usuario']; ?></p>

                    <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>

                    <p><strong>Perfil:</strong> Admin</p>
                    <p><strong>Miembro desde:</strong> <?php echo formatearFecha($usuario['fecha_registro']); ?></p>

                    <p><strong>Último acceso:</strong> <?php echo $usuario['ultimo_acceso'] ? formatearFecha($usuario['ultimo_acceso']) : 'Primera vez'; ?></p>

                </div>
            </div>
            
            <div class="perfiles-seguridad-acciones">
                <h3 class="perfiles-seguridad-titulo">⚡ Acciones de seguridad</h3>
                
                <div class="perfiles-accion-seguridad">
                    <h4>📧 Verificar email</h4>
                    <p>Estado: <span class="perfiles-badge <?php echo $usuario['email_verificado'] ? 'perfiles-badge-success' : 'perfiles-badge-warning'; ?>">

                        <?php echo $usuario['email_verificado'] ? '✅ Verificado' : '⚠️ No verificado'; ?>

                    </span></p>
                    <?php if (!$usuario['email_verificado']): ?>
                        <p>La verificación mediante enlace todavía no está habilitada.</p>
                    <?php endif; ?>

                </div>
                
                <div class="perfiles-accion-seguridad">
                    <h4>🔐 Sesiones activas</h4>
                    <p>La gestión individual de sesiones todavía no está habilitada. Cierra sesión al terminar en un dispositivo compartido.</p>
                </div>
                
                <div class="perfiles-accion-seguridad">
                    <h4>📱 Autenticación de dos factores</h4>
                    <p>El segundo factor todavía no está habilitado. La cuenta continúa protegida mediante contraseña y controles de sesión.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    
</div>

<script>
function adminPerfilMostrarPestana(nombre) {
    // Ocultar todos los contenidos
    document.querySelectorAll('.perfiles-pestana-contenido').forEach(el => {
        el.classList.remove('active');
    });
    
    // Desactivar todas las pestañas
    document.querySelectorAll('.perfiles-pestana').forEach(el => {
        el.classList.remove('active');
    });
    
    // Mostrar la pestaña seleccionada
    document.getElementById('perfiles-pestana-' + nombre).classList.add('active');
    
    // Activar el botón clickeado
    event.target.classList.add('active');
}

// Vista previa de imagen
document.addEventListener('DOMContentLoaded', function() {
    const inputAvatar = document.getElementById('admin_perfil_avatar');
    const preview = document.getElementById('admin_perfil_preview_imagen');
    
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
