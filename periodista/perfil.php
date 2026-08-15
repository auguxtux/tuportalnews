<?php
declare(strict_types=1);


/**
 * PERFIL DE PERIODISTA
 * (Similar al de usuario pero con campos específicos)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';

Permisos::requerirPeriodista();

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

// Procesar formulario (misma lógica que usuario/perfil.php pero para periodistas)
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
                $mensaje = ['tipo' => 'success', 'texto' => 'Perfil actualizado'];
                
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
                $mensaje = ['tipo' => 'success', 'texto' => 'Contraseña actualizada'];
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
                registrarErrorInterno('PERIODISTA.PERFIL.AVATAR_ACTUALIZAR', $e);
            }

            if ($avatar_actualizado) {
                if ($usuario['avatar'] && !in_array($usuario['avatar'], ['default-avatar.png', 'default.jpg'], true)) {
                    $ruta_anterior = UPLOAD_PERFILES . $usuario['avatar'];
                    if (is_file($ruta_anterior)) {
                        unlink($ruta_anterior);
                    }
                }

                $_SESSION['usuario_avatar'] = $nombre_archivo;
                $mensaje = ['tipo' => 'success', 'texto' => 'Avatar actualizado'];
                
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
            registrarErrorInterno('PERIODISTA.PERFIL.AVATAR_ELIMINAR', $e);
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


<h1 class="perfiles-titulo">Mi perfil de Articulista</h1>

<?php if ($mensaje): ?>

    <div class="perfiles-alerta perfiles-alerta-<?php echo $mensaje['tipo']; ?>">

        <?php echo $mensaje['texto']; ?>

    </div>
<?php endif; ?>


<?php if (!empty($errores)): ?>

    <div class="perfiles-alerta perfiles-alerta-error">
        <ul><?php foreach ($errores as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul>

    </div>
<?php endif; ?>


<!-- Sistema de pestañas -->
<div class="perfiles-pestanas">
    <button class="perfiles-pestana active" onclick="periodistaPerfilMostrarPestana('datos')">📋 Datos personales</button>
    <button class="perfiles-pestana" onclick="periodistaPerfilMostrarPestana('avatar')">🖼️ Avatar</button>
    <button class="perfiles-pestana" onclick="periodistaPerfilMostrarPestana('password')">🔒 Cambiar contraseña</button>
</div>

<!-- PESTAÑA DATOS -->
<div id="perfiles-pestana-datos" class="perfiles-pestana-contenido active">
    <div class="perfiles-formulario">
        <h2 class="perfiles-subtitulo">Datos personales</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="accion" value="actualizar_perfil">
            
            <div class="perfiles-campo-form">
                <label>Email</label>
                <input type="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled class="perfiles-campo-deshabilitado">

            </div>
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_nombre">Nombre completo *</label>
                <input type="text" id="periodista_perfil_nombre" name="nombre" required maxlength="150"
                       value="<?php echo htmlspecialchars($usuario['nombre']); ?>">

            </div>
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_telefono">Teléfono *</label>
                <input type="tel" id="periodista_perfil_telefono" name="telefono" required 
                       value="<?php echo htmlspecialchars($usuario['telefono']); ?>">

            </div>
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_ciudad">Ciudad *</label>
                <input type="text" id="periodista_perfil_ciudad" name="ciudad" required maxlength="120"
                       value="<?php echo htmlspecialchars($usuario['ciudad']); ?>">

            </div>
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_biografia">Biografía profesional</label>
                <textarea id="periodista_perfil_biografia" name="biografia" rows="5" maxlength="500"
                          placeholder="Cuéntanos tu trayectoria, especialización..."><?php echo htmlspecialchars($usuario['biografia'] ?? ''); ?></textarea>

            </div>
            
            <button type="submit" class="perfiles-btn">Guardar cambios</button>
        </form>
    </div>
</div>

<!-- PESTAÑA AVATAR -->
<div id="perfiles-pestana-avatar" class="perfiles-pestana-contenido">
    <div class="perfiles-formulario">
        <h2 class="perfiles-subtitulo">Mi avatar</h2>
        <div style="text-align: center; margin: 2rem 0;">
            <img src="<?php echo base_url('uploads/perfiles/' . ($usuario['avatar'] ?? 'default-avatar.png')); ?>" 

                 alt="Avatar" class="perfiles-avatar-imagen">
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="accion" value="cambiar_avatar">
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_avatar">Nueva imagen</label>
                <input type="file" id="periodista_perfil_avatar" name="avatar" accept="image/*">
                <img id="periodista_perfil_preview_imagen" src="#" alt="Vista previa" style="display: none; max-width: 100%; margin-top: 1rem;">
            </div>
            <button type="submit" class="perfiles-btn">Subir avatar</button>
        </form>
        
        <?php if ($usuario['avatar'] && !in_array($usuario['avatar'], ['default-avatar.png', 'default.jpg'], true)): ?>

            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="accion" value="eliminar_avatar">
                <button type="submit" class="perfiles-btn perfiles-btn-secondary" onclick="return confirm('¿Eliminar avatar?')">
                    Eliminar avatar
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>

<!-- PESTAÑA CONTRASEÑA -->
<div id="perfiles-pestana-password" class="perfiles-pestana-contenido">
    <div class="perfiles-formulario">
        <h2 class="perfiles-subtitulo">Cambiar contraseña</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="accion" value="cambiar_password">
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_password_actual">Contraseña actual *</label>
                <input type="password" id="periodista_perfil_password_actual" name="password_actual" required maxlength="4096">
            </div>
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_password_nueva">Nueva contraseña *</label>
                <input type="password" id="periodista_perfil_password_nueva" name="password_nueva" required minlength="10" maxlength="4096">
            </div>
            
            <div class="perfiles-campo-form">
                <label for="periodista_perfil_password_confirmar">Confirmar contraseña *</label>
                <input type="password" id="periodista_perfil_password_confirmar" name="password_confirmar" required minlength="10" maxlength="4096">
            </div>
            
            <button type="submit" class="perfiles-btn">Cambiar contraseña</button>
        </form>
    </div>
</div>

<!-- Script para pestañas y preview -->
<script>
function periodistaPerfilMostrarPestana(nombre) {
    document.querySelectorAll('.perfiles-pestana-contenido').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.perfiles-pestana').forEach(el => el.classList.remove('active'));
    document.getElementById('perfiles-pestana-' + nombre).classList.add('active');
    event.target.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const inputAvatar = document.querySelector('input[name="avatar"]');
    const preview = document.getElementById('periodista_perfil_preview_imagen');
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
