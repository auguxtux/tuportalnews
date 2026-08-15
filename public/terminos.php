<?php
declare(strict_types=1);


/**
 * PÁGINA DE TÉRMINOS Y CONDICIONES
 * Diseño responsive con tarjetas
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_pagina = 'Términos y Condiciones';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('public-terminos.css'); ?>">

<div class="public-terminos-container">
    
    <div class="public-terminos-header">

<h1>📜 Términos y Condiciones de Uso</h1>
        <p class="public-terminos-descripcion">Lee atentamente nuestras condiciones antes de utilizar este portal</p>
    </div>
    
    <div class="public-terminos-grid">
        
        <!-- Tarjeta 1 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">📋</div>
            <h2 class="public-terminos-card-titulo">1. Aceptación de los términos</h2>
            <p class="public-terminos-card-texto">Al acceder y utilizar este portal de noticias, usted acepta estar sujeto a estos términos y condiciones. Si no está de acuerdo con alguna parte de los términos, no podrá acceder al servicio.</p>
        </div>
        
        <!-- Tarjeta 2 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">🔧</div>
            <h2 class="public-terminos-card-titulo">2. Descripción del servicio</h2>
            <p class="public-terminos-card-texto">Nuestro portal proporciona noticias e información de actualidad. Nos reservamos el derecho de modificar, suspender o discontinuar cualquier aspecto del servicio en cualquier momento.</p>
        </div>
        
        <!-- Tarjeta 3 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">🔐</div>
            <h2 class="public-terminos-card-titulo">3. Registro de cuentas</h2>
            <p class="public-terminos-card-texto">Para acceder a ciertas funciones, deberá registrarse. Usted es responsable de mantener la confidencialidad de su cuenta y contraseña.</p>
        </div>
        
        <!-- Tarjeta 4 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">⚖️</div>
            <h2 class="public-terminos-card-titulo">4. Conducta del usuario</h2>
            <p class="public-terminos-card-texto">Al utilizar nuestro servicio, usted acepta no:</p>
            <ul class="public-terminos-card-lista">
                <li>Publicar contenido ilegal, obsceno o difamatorio</li>
                <li>Violar derechos de propiedad intelectual</li>
                <li>Suplantar a otras personas</li>
                <li>Interferir con el funcionamiento del servicio</li>
            </ul>
        </div>
        
        <!-- Tarjeta 5 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">📝</div>
            <h2 class="public-terminos-card-titulo">5. Contenido de los usuarios</h2>
            <p class="public-terminos-card-texto">Usted conserva todos los derechos sobre el contenido que publica. Al publicar, nos otorga una licencia para usar, modificar y mostrar dicho contenido en relación con el servicio.</p>
        </div>
        
        <!-- Tarjeta 6 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">©️</div>
            <h2 class="public-terminos-card-titulo">6. Propiedad intelectual</h2>
            <p class="public-terminos-card-texto">Todo el contenido proporcionado por el portal está protegido por derechos de autor y otras leyes de propiedad intelectual.</p>
        </div>
        
        <!-- Tarjeta 7 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">⚠️</div>
            <h2 class="public-terminos-card-titulo">7. Limitación de responsabilidad</h2>
            <p class="public-terminos-card-texto">El portal no será responsable por daños indirectos, incidentales o consecuentes que resulten del uso o la imposibilidad de usar el servicio.</p>
        </div>
        
        <!-- Tarjeta 8 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">🔄</div>
            <h2 class="public-terminos-card-titulo">8. Modificaciones</h2>
            <p class="public-terminos-card-texto">Nos reservamos el derecho de modificar estos términos en cualquier momento. Los cambios entrarán en vigor inmediatamente después de su publicación.</p>
        </div>
        
        <!-- Tarjeta 9 -->
        <div class="public-terminos-card">
            <div class="public-terminos-card-icono">📧</div>
            <h2 class="public-terminos-card-titulo">9. Contacto</h2>
            <p class="public-terminos-card-texto">Si tiene preguntas sobre estos términos, puede contactarnos en: <a href="mailto:info@news.local" class="public-terminos-enlace">auguxtux@gmail.com</a></p>
        </div>
        
    </div>
    
    <div class="public-terminos-footer">
        <p class="public-terminos-fecha"><em>📅 Última actualización: <?php echo date('d/m/Y'); ?></em></p>

        <a href="<?php echo route('home'); ?>" class="public-privacidad-btn">🏠 Volver al inicio</a>

    </div>
    
</div>


<?php require_once __DIR__ . '/../partials/footer.php'; ?>

