<?php
declare(strict_types=1);


/**
 * NUEVO PERIODISTA
 * Formulario para crear un nuevo periodista (solo admin)
 * Con soporte para marcarlo como usuario privado
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../includes/upload-handler.php';
require_once __DIR__ . '/../includes/minify.php';

// Requerir autenticación de admin
Permisos::requerirAdmin();

$pdo = db();

$errores = [];
$datos = [
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'ciudad' => '',
    'biografia' => '',
    'es_privado' => 0,
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar token CSRF
    if (!verificarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $errores[] = 'Error de seguridad. Inténtalo de nuevo.';
    } else {
        
        $datos = [
            'nombre' => limpiarDatos($_POST['nombre'] ?? ''),
            'email' => limpiarDatos($_POST['email'] ?? ''),
            'telefono' => limpiarDatos($_POST['telefono'] ?? ''),
            'ciudad' => limpiarDatos($_POST['ciudad'] ?? ''),
            'biografia' => limpiarDatos($_POST['biografia'] ?? ''),
            'es_privado' => isset($_POST['es_privado']) ? 1 : 0,
        ];
        
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        // Validaciones
        if (empty($datos['nombre'])) {
            $errores[] = 'El nombre es obligatorio';
        }
        
        if (!validarEmail($datos['email'])) {
            $errores[] = 'Email no válido';
        }
        
        if (!validarTelefono($datos['telefono'])) {
            $errores[] = 'Teléfono no válido (debe tener 9 dígitos y empezar por 6, 7, 8 o 9)';
        }
        
        if (empty($datos['ciudad'])) {
            $errores[] = 'La ciudad es obligatoria';
        }

        if (mb_strlen($datos['nombre']) > 150) {
            $errores[] = 'El nombre no puede superar 150 caracteres';
        }

        if (mb_strlen($datos['email']) > 255) {
            $errores[] = 'El email no puede superar 255 caracteres';
        }

        if (mb_strlen($datos['ciudad']) > 120) {
            $errores[] = 'La ciudad no puede superar 120 caracteres';
        }

        if (mb_strlen($datos['biografia']) > 500) {
            $errores[] = 'La biografía no puede superar 500 caracteres';
        }
        
        if (strlen($password) > 4096) {
            $errores[] = 'La contraseña supera la longitud permitida';
        } elseif (strlen($password) < 10) {
            $errores[] = 'La contraseña debe tener al menos 10 caracteres';
        }
        
        if ($password !== $password2) {
            $errores[] = 'Las contraseñas no coinciden';
        }
        
        // Verificar si el email ya existe
        if (empty($errores)) {
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->execute([$datos['email']]);
            if ($stmt->fetch()) {
                $errores[] = 'Este email ya está registrado';
            }
        }
        
        // Si no hay errores, guardar
        if (empty($errores)) {
            $avatar_nuevo_subido = null;
            $limpiar_avatar_nuevo = static function (?string $nombre_archivo): void {
                if ($nombre_archivo === null) {
                    return;
                }

                $ruta_avatar = UPLOAD_PERFILES . $nombre_archivo;
                if (is_file($ruta_avatar)) {
                    unlink($ruta_avatar);
                }
            };

            try {
                $pdo->beginTransaction();
                
                // Procesar avatar si se subió
                $avatar = 'default.jpg';
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $upload = new UploadHandler($_FILES['avatar'], 'perfil', 'imagen');
                    $avatar = $upload->subir();
                    
                    if (!$avatar) {
                        $errores_upload = $upload->getErrores();
                        $errores = array_merge($errores, $errores_upload);
                        $avatar = 'default.jpg';
                    } else {
                        $avatar_nuevo_subido = $avatar;
                    }
                }
                
                if (empty($errores)) {
                    // Hash de contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar usuario como periodista
                    $sql = "INSERT INTO usuarios (
                        nombre, email, password, telefono, ciudad, 
                        biografia, avatar, rol, estado,
                        fecha_registro, email_verificado
                    ) VALUES (
                        :nombre, :email, :password, :telefono, :ciudad,
                        :biografia, :avatar, 'periodista', 'activo',
                        NOW(), 1
                    )";
                    
                    $stmt = $pdo->prepare($sql);
                    $resultado = $stmt->execute([
                        ':nombre' => $datos['nombre'],
                        ':email' => $datos['email'],
                        ':password' => $password_hash,
                        ':telefono' => $datos['telefono'],
                        ':ciudad' => $datos['ciudad'],
                        ':biografia' => $datos['biografia'] ?: null,
                        ':avatar' => $avatar
                    ]);
                    
                    if ($resultado) {
                        $id_periodista = $pdo->lastInsertId();
                        
                        // Si se marcó como usuario privado, insertar en tabla usuarios_privados
                        if ($datos['es_privado']) {
                            $stmt_priv = $pdo->prepare("INSERT IGNORE INTO usuarios_privados (id_usuario) VALUES (?)");
                            $stmt_priv->execute([$id_periodista]);
                        }
                        
                        $pdo->commit();
                        
                        mensajeFlash('success', 'Articulista creado correctamente' . ($datos['es_privado'] ? ' como Colaborador' : ''));
                        redireccionar(route('admin_periodistas'));
                    } else {
                        $pdo->rollBack();
                        $limpiar_avatar_nuevo($avatar_nuevo_subido);
                        $errores[] = 'Error al guardar el periodista';
                    }
                } elseif ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $limpiar_avatar_nuevo($avatar_nuevo_subido);
                $errores[] = 'No se pudo crear el periodista.';
                registrarErrorInterno('ADMIN.PERIODISTA.CREAR', $e);
            }
        }
    }
    
    limpiarTokenCSRF();
}

$titulo_pagina = 'Nuevo Articulista';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('admin-periodistas-gestion.css'); ?>">


<div class="new-container">
    
    <h1 class="new-titulo">✍️ Crear nuevo Articulista</h1>
    
    <?php if (!empty($errores)): ?>

        <div class="new-alerta new-alerta-error">
            <ul>
                <?php foreach ($errores as $error): ?>

                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <div class="new-card">
        <form method="POST" enctype="multipart/form-data" class="new-form">
            
            <input type="hidden" name="csrf_token" value="<?php echo generarTokenCSRF(); ?>">

            
            <div class="new-campo">
                <label for="nombre">👤 Nombre completo *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="150"
                       value="<?php echo htmlspecialchars($datos['nombre']); ?>"

                       placeholder="Ej: Juan Pérez">
            </div>
            
            <div class="new-campo">
                <label for="email">📧 Email *</label>
                <input type="email" id="email" name="email" required maxlength="255"
                       value="<?php echo htmlspecialchars($datos['email']); ?>"

                       placeholder="periodista@email.com">
            </div>
            
            <div class="new-campo">
                <label for="password">🔒 Contraseña *</label>
                <input type="password" id="password" name="password" required
                       placeholder="Mínimo 10 caracteres"
                       minlength="10" maxlength="4096">
            </div>
            
            <div class="new-campo">
                <label for="password2">🔒 Repetir contraseña *</label>
                <input type="password" id="password2" name="password2" required
                       placeholder="Repite la contraseña"
                       minlength="10" maxlength="4096">
            </div>
            
            <div class="new-campo">
                <label for="telefono">📞 Teléfono *</label>
                <input type="tel" id="telefono" name="telefono" required 
                       value="<?php echo htmlspecialchars($datos['telefono']); ?>"

                       placeholder="612345678"
                       pattern="[6-9][0-9]{8}"
                       title="Teléfono español de 9 dígitos empezando por 6,7,8 o 9">
            </div>
            
            <div class="new-campo">
                <label for="ciudad">🏙️ Ciudad *</label>
                <input type="text" id="ciudad" name="ciudad" required maxlength="120"
                       value="<?php echo htmlspecialchars($datos['ciudad']); ?>"

                       placeholder="Ej: Madrid">
            </div>
            
            <div class="new-campo">
                <label for="biografia">📝 Biografía (opcional)</label>
                <textarea id="biografia" name="biografia" rows="5" maxlength="500"
                          placeholder="Breve descripción del periodista..."><?php echo htmlspecialchars($datos['biografia']); ?></textarea>

            </div>
            
            <div class="new-campo new-checkbox">
                <label>
                    <input type="checkbox" name="es_privado" value="1" <?php echo $datos['es_privado'] ? 'checked' : ''; ?>>

                    🔒 Usuario con acceso a noticias privadas
                </label>
                <small>Los Colaboradores pueden ver noticias marcadas como privadas</small>
            </div>
            
            <div class="new-campo">
                <label for="avatar">🖼️ Foto de perfil (opcional)</label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                <small>Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB</small>
                <img id="preview-avatar" class="new-avatar-preview" src="#" alt="Vista previa" style="display: none;">
            </div>
            
            <div class="new-acciones">
                <button type="submit" class="new-btn new-btn-guardar">
                    💾 Crear Articulista
                </button>
                <a href="<?php echo route('admin_periodistas'); ?>" class="new-btn new-btn-cancelar">

                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Vista previa del avatar
    const inputAvatar = document.getElementById('avatar');
    const previewAvatar = document.getElementById('preview-avatar');
    
    if (inputAvatar && previewAvatar) {
        inputAvatar.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewAvatar.src = e.target.result;
                    previewAvatar.style.display = 'block';
                    previewAvatar.classList.add('new-avatar-preview');
                }
                reader.readAsDataURL(file);
            } else {
                previewAvatar.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
