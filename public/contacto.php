<?php
declare(strict_types=1);


/**
 * PÁGINA DE CONTACTO
 * Diseño responsive con tarjetas
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$errores = $_SESSION['contacto_errores'] ?? [];
$datos = $_SESSION['contacto_datos'] ?? [];

// Limpiar sesión
unset($_SESSION['contacto_errores']);
unset($_SESSION['contacto_datos']);

$titulo_pagina = 'Contacto';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="<?php echo css_url('public-contacto.css'); ?>">
<link rel="stylesheet" href="<?php echo css_url('public-form-focus.css'); ?>">


<div class="public-contacto-container">
<h1>📬 Contacto</h1>
    <p class="public-contacto-descripcion">¿Tienes alguna duda o sugerencia? Escríbenos y te responderemos lo antes posible.</p>
    
    <?php if (!empty($errores)): ?>

        <div class="public-contacto-alerta public-contacto-alerta-error">
            <p><strong>⚠️ Por favor, corrige los siguientes errores:</strong></p>
            <ul class="public-contacto-error-list">
                <?php foreach ($errores as $error): ?>

                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>

                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    
    <!-- GRID DE TARJETAS (2 columnas → 1 en móvil) -->
    <div class="public-contacto-grid">
        
        <!-- TARJETA DE INFORMACIÓN DE CONTACTO -->
        <div class="public-contacto-card public-contacto-info-card">
            <h2 class="public-contacto-card-titulo">📋 Información de contacto</h2>
            
            <div class="public-contacto-info-item">
                <div class="public-contacto-info-icono">📍</div>
                <div class="public-contacto-info-contenido">
                    <h3 class="public-contacto-info-subtitulo">Dirección</h3>
                    <p>Calle Gambuesa, 126<br>35600 Puerto del Rosario, Canarias</p>
                </div>
            </div>
            
            <div class="public-contacto-info-item">
                <div class="public-contacto-info-icono">📧</div>
                <div class="public-contacto-info-contenido">
                    <h3 class="public-contacto-info-subtitulo">Email</h3>
                    <p>
                        General: <a href="mailto:auguxtux@gmail.com" class="public-contacto-enlace">auguxtux@gmail.com</a><br>
                        Soporte: <a href="mailto:gux.odyx@gmail.com" class="public-contacto-enlace">gux.odyx@gmail.com</a><br>
                        Publicidad: <a href="mailto:tindaya@gmail.com" class="public-contacto-enlace">tindaya@gmail.com</a>
                    </p>
                </div>
            </div>
            
            <div class="public-contacto-info-item">
                <div class="public-contacto-info-icono">📞</div>
                <div class="public-contacto-info-contenido">
                    <h3 class="public-contacto-info-subtitulo">Teléfono</h3>
                    <p>
                        Atención al usuario: <strong>613 262735</strong><br>
                        Redacción: <strong>644 000919</strong>
                    </p>
                </div>
            </div>
            
            <div class="public-contacto-info-item">
                <div class="public-contacto-info-icono">🕐</div>
                <div class="public-contacto-info-contenido">
                    <h3 class="public-contacto-info-subtitulo">Horario</h3>
                    <p>
                        Lunes a viernes: 9:00 - 20:00<br>
                        Sábados: 10:00 - 14:00<br>
                        Domingos: Cerrado
                    </p>
                </div>
            </div>
            
        </div>
        
        <!-- TARJETA DEL FORMULARIO DE CONTACTO -->
        <div class="public-contacto-card public-contacto-form-card">
            <h2 class="public-contacto-card-titulo">✉️ Envíanos un mensaje</h2>
            
            <form action="<?php echo htmlspecialchars(route('procesar_contacto'), ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="public-contacto-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generarTokenCSRF(), ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="public-contacto-campo">
                    <label for="nombre" class="public-contacto-label">👤 Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           class="public-contacto-input"
                           maxlength="100"
                           value="<?php echo htmlspecialchars($datos['nombre'] ?? ''); ?>"

                           placeholder="Tu nombre completo">
                </div>
                
                <div class="public-contacto-campo">
                    <label for="email" class="public-contacto-label">📧 Email *</label>
                    <input type="email" id="email" name="email" required 
                           class="public-contacto-input"
                           maxlength="255"
                           value="<?php echo htmlspecialchars($datos['email'] ?? ''); ?>"

                           placeholder="tu@email.com">
                </div>
                
                <div class="public-contacto-campo">
                    <label for="asunto" class="public-contacto-label">📝 Asunto *</label>
                    <input type="text" id="asunto" name="asunto" required 
                           class="public-contacto-input"
                           maxlength="150"
                           value="<?php echo htmlspecialchars($datos['asunto'] ?? ''); ?>"

                           minlength="5"
                           placeholder="Motivo de tu mensaje">
                </div>
                
                <div class="public-contacto-campo">
                    <label for="mensaje" class="public-contacto-label">💬 Mensaje *</label>
                    <textarea id="mensaje" name="mensaje" rows="5" required 
                              class="public-contacto-textarea"
                              minlength="10"
                              maxlength="500"
                              placeholder="Escribe aquí tu mensaje..."><?php echo htmlspecialchars($datos['mensaje'] ?? ''); ?></textarea>

                    <div class="public-contacto-contador">
                        <span id="contador" class="public-contacto-contador-numero">0</span>
                        <span class="public-contacto-contador-texto">/500 caracteres</span>
                    </div>
                </div>
                
                <div class="public-contacto-campo public-contacto-checkbox">
                    <label class="public-contacto-checkbox-label">
                        <input type="checkbox" name="privacidad" required>
                        <span>He leído y acepto la <a href="<?php echo htmlspecialchars(route('privacidad'), ENT_QUOTES, 'UTF-8'); ?>" class="public-contacto-enlace">política de privacidad</a></span>
                    </label>
                </div>
                
                <div class="public-contacto-acciones">
                    <button type="submit" class="public-contacto-btn public-contacto-btn-enviar">
                        📤 Enviar mensaje
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MAPA - OCUPA TODO EL ANCHO -->
    <div class="public-contacto-mapa">
        <h2 class="public-contacto-mapa-titulo">🗺️ Encuéntranos</h2>
        <div class="public-contacto-mapa-contenedor">
            <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d28049.266789323425!2d-13.8575872!3d28.5048832!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1ses!2ses!4v1776601761727!5m2!1ses!2ses" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
    
</div>

<!-- Script para contador de caracteres -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mensaje = document.getElementById('mensaje');
    const contador = document.getElementById('contador');
    
    if (mensaje && contador) {
        function actualizarContador() {
            const longitud = mensaje.value.length;
            contador.textContent = longitud;
            
            if (longitud > 500) {
                contador.style.color = '#ef4444';
                mensaje.value = mensaje.value.substring(0, 500);
            } else if (longitud > 450) {
                contador.style.color = '#f59e0b';
            } else {
                contador.style.color = '#6b7280';
            }
        }
        
        mensaje.addEventListener('input', actualizarContador);
        actualizarContador();
    }
});
</script>


<?php require_once __DIR__ . '/../partials/footer.php'; ?>
