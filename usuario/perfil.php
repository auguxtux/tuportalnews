<?php
declare(strict_types=1);


/**
 * PERFIL DE USUARIO
 * Editar datos personales y avatar
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirLogin();

if (!esUsuario()) {
    mensajeFlash('error', 'Acceso no autorizado');
    redireccionar(route('home'));
}

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
        $datosPerfil = normalizarDatosPerfil($_POST);
        $nombre = $datosPerfil['nombre'];
        $telefono = $datosPerfil['telefono'];
        $ciudad = $datosPerfil['ciudad'];
        $biografia = $datosPerfil['biografia'];
        $datosColaboracion = $datosPerfil['datos_colaboracion'];
        $errores = array_merge($errores, validarDatosPerfil($datosPerfil));
        if ($datosColaboracion === '') $errores[] = 'Los datos de interés para colaborar son obligatorios';
        if (mb_strlen($datosColaboracion) > 2000) $errores[] = 'Los datos de interés para colaborar no pueden superar 2000 caracteres';
        
        if (empty($errores)) {
            $sql = "UPDATE usuarios SET 
                    nombre = :nombre,
                    telefono = :telefono,
                    ciudad = :ciudad,
                    biografia = :biografia,
                    datos_colaboracion = :datos_colaboracion
                    WHERE id_usuario = :id";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                ':nombre' => $nombre,
                ':telefono' => $telefono,
                ':ciudad' => $ciudad,
                ':biografia' => $biografia,
                ':datos_colaboracion' => $datosColaboracion,
                ':id' => $id_usuario
            ])) {
                // Actualizar nombre en sesión
                $_SESSION['usuario_nombre'] = $nombre;
                $mensaje = ['tipo' => 'success', 'texto' => 'Perfil actualizado correctamente'];
                
                // Recargar datos
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
                $stmt->execute([':id' => $id_usuario]);
                $usuario = $stmt->fetch();
            } else {
                $errores[] = 'Error al actualizar el perfil';
            }
        }
    }
    
    // CAMBIAR CONTRASEÑA
    if ($accion === 'cambiar_password') {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        $errores = array_merge($errores, validarCambioPasswordPerfil(
            $password_actual,
            $password_nueva,
            $password_confirmar,
            (string)$usuario['password']
        ));
        
        if (empty($errores)) {
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET password = :password WHERE id_usuario = :id";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([
                ':password' => $password_hash,
                ':id' => $id_usuario
            ])) {
                $mensaje = ['tipo' => 'success', 'texto' => 'Contraseña actualizada correctamente'];
            } else {
                $errores[] = 'Error al cambiar la contraseña';
            }
        }
    }
    
    // CAMBIAR AVATAR
if ($accion === 'cambiar_avatar' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    // ============================================
    // ✅ VALIDAR LÍMITE DE ALMACENAMIENTO DEL USUARIO
    // ============================================
    $verificacion = verificarLimiteAlmacenamiento($_SESSION['usuario_id'], $_FILES['avatar']['size']);
    
    if (!$verificacion['permitido']) {
        $errores[] = $verificacion['mensaje'];
    } else {
        $upload = new UploadHandler($_FILES['avatar'], 'perfil', 'imagen', $id_usuario);
        $nombre_archivo = $upload->subir();
        
        if ($nombre_archivo) {
            $stmt = $pdo->prepare("UPDATE usuarios SET avatar = :avatar WHERE id_usuario = :id");
            try {
                $avatar_actualizado = $stmt->execute([':avatar' => $nombre_archivo, ':id' => $id_usuario]);
            } catch (Throwable $e) {
                $avatar_actualizado = false;
                registrarErrorInterno('USUARIO.PERFIL.AVATAR_ACTUALIZAR', $e);
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
}
    
    // ELIMINAR AVATAR
    if ($accion === 'eliminar_avatar') {
        $avatar_anterior = $usuario['avatar'] ?? '';
        $stmt = $pdo->prepare("UPDATE usuarios SET avatar = 'default-avatar.png' WHERE id_usuario = :id");
        try {
            $avatar_eliminado = $stmt->execute([':id' => $id_usuario]);
        } catch (Throwable $e) {
            $avatar_eliminado = false;
            registrarErrorInterno('USUARIO.PERFIL.AVATAR_ELIMINAR', $e);
        }

        if ($avatar_eliminado) {
            if ($avatar_anterior && !in_array($avatar_anterior, ['default-avatar.png', 'default.jpg'], true)) {
                $ruta_avatar = UPLOAD_PERFILES . $avatar_anterior;
                if (is_file($ruta_avatar)) {
                    unlink($ruta_avatar);
                }
            }

            $_SESSION['usuario_avatar'] = 'default-avatar.png';
            $mensaje = ['tipo' => 'success', 'texto' => 'Avatar eliminado'];
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
            $stmt->execute([':id' => $id_usuario]);
            $usuario = $stmt->fetch();
        } else {
            $errores[] = 'Error al eliminar el avatar';
        }
    }
}

$titulo_pagina = 'Mi Perfil';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('perfiles.css'); ?>">

<div class="perfiles-container">
    
    <h1 class="perfiles-titulo">👤 Mi Perfil</h1>
    
    <?php if ($mensaje): ?>

        <div class="perfiles-alerta perfiles-alerta-<?php echo $mensaje['tipo']; ?>">

            <?php echo htmlspecialchars($mensaje['texto'], ENT_QUOTES, 'UTF-8'); ?>

        </div>
    <?php endif; ?>

    
    <?php if (!empty($errores)): ?>

        <div class="perfiles-alerta perfiles-alerta-error">
            <ul class="perfiles-error-list">
                <?php foreach ($errores as $error): ?>

                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <!-- PESTAÑAS DE NAVEGACIÓN -->
    <div class="perfiles-pestanas">
        <button class="perfiles-pestana active" onclick="usuarioPerfilMostrarPestana('datos')">📋 Datos personales</button>
        <button class="perfiles-pestana" onclick="usuarioPerfilMostrarPestana('avatar')">🖼️ Avatar</button>
        <button class="perfiles-pestana" onclick="usuarioPerfilMostrarPestana('password')">🔒 Cambiar contraseña</button>
    </div>
    
    <!-- PESTAÑA 1: DATOS PERSONALES -->
    <div id="perfiles-pestana-datos" class="perfiles-pestana-contenido active">
        <div class="perfiles-formulario">
            <h2 class="perfiles-subtitulo">📋 Datos personales</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="actualizar_perfil">
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_email">📧 Email (no editable)</label>
                    <input type="email" id="usuario_perfil_email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled class="perfiles-campo-deshabilitado">

                    <small>El email no se puede cambiar por seguridad</small>
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_nombre">👤 Nombre completo *</label>
                    <input type="text" id="usuario_perfil_nombre" name="nombre" required maxlength="150"
                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>">

                </div>
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_telefono">📞 Teléfono *</label>
                    <input type="tel" id="usuario_perfil_telefono" name="telefono" required 
                           value="<?php echo htmlspecialchars($usuario['telefono']); ?>"

                           pattern="[6-9][0-9]{8}"
                           title="Teléfono español de 9 dígitos">
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_ciudad">🏙️ Ciudad *</label>
                    <input type="text" id="usuario_perfil_ciudad" name="ciudad" required maxlength="120"
                           value="<?php echo htmlspecialchars($usuario['ciudad']); ?>">

                </div>
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_biografia">📝 Biografía / Sobre mí</label>
                    <textarea id="usuario_perfil_biografia" name="biografia" rows="5" maxlength="500"
                              placeholder="Cuéntanos algo sobre ti..."><?php echo htmlspecialchars($usuario['biografia'] ?? ''); ?></textarea>

                    <small>Máximo 500 caracteres</small>
                </div>

                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_datos_colaboracion">🤝 Datos de interés para colaborar *</label>
                    <textarea id="usuario_perfil_datos_colaboracion" name="datos_colaboracion" rows="6" maxlength="2000" required><?php echo htmlspecialchars($usuario['datos_colaboracion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <small>Información privada, visible únicamente para ti y los administradores.</small>
                </div>
                
                <div class="perfiles-acciones-form">
                    <button type="submit" class="perfiles-btn perfiles-btn-principal">💾 Guardar cambios</button>
                    <a href="<?php echo htmlspecialchars(route('usuario_dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="perfiles-btn perfiles-btn-secundario">❌ Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- PESTAÑA 2: AVATAR -->
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
                    <label for="usuario_perfil_avatar">📁 Seleccionar nueva imagen</label>
                    <input type="file" id="usuario_perfil_avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB</small>
                    <img id="usuario_perfil_preview_imagen" src="#" alt="Vista previa" style="display: none; max-width: 100%; margin-top: 1rem;">
                </div>
                
                <button type="submit" class="perfiles-btn perfiles-btn-principal">⬆️ Subir avatar</button>
            </form>
            
            <?php if ($usuario['avatar'] && !in_array($usuario['avatar'], ['default-avatar.png', 'default.jpg'], true)): ?>

                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="accion" value="eliminar_avatar">
                    <button type="submit" class="perfiles-btn perfiles-btn-secundario" onclick="return confirm('¿Eliminar avatar actual?')">
                        🗑️ Eliminar avatar
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- PESTAÑA 3: CAMBIAR CONTRASEÑA -->
    <div id="perfiles-pestana-password" class="perfiles-pestana-contenido">
        <div class="perfiles-formulario">
            <h2 class="perfiles-subtitulo">🔒 Cambiar contraseña</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="cambiar_password">
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_password_actual">🔑 Contraseña actual *</label>
                    <input type="password" id="usuario_perfil_password_actual" name="password_actual" required maxlength="4096">
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_password_nueva">🔒 Nueva contraseña *</label>
                    <input type="password" id="usuario_perfil_password_nueva" name="password_nueva" required minlength="10" maxlength="4096">
                    <small>Mínimo 10 caracteres</small>
                </div>
                
                <div class="perfiles-campo-form">
                    <label for="usuario_perfil_password_confirmar">✅ Confirmar nueva contraseña *</label>
                    <input type="password" id="usuario_perfil_password_confirmar" name="password_confirmar" required minlength="10" maxlength="4096">
                </div>
                
                <button type="submit" class="perfiles-btn perfiles-btn-principal">🔄 Cambiar contraseña</button>
            </form>
        </div>
    </div>
    
</div>

<script>
function usuarioPerfilMostrarPestana(nombre) {
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
    const inputAvatar = document.getElementById('usuario_perfil_avatar');
    const preview = document.getElementById('usuario_perfil_preview_imagen');
    
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
