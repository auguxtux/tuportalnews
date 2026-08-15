<?php
declare(strict_types=1);


/**
 * PÁGINA DE POLÍTICA DE PRIVACIDAD
 * Diseño responsive con tarjetas
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/minify.php';

$titulo_pagina = 'Política de Privacidad';
require_once __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="<?php echo css_url('public-privacidad.css'); ?>">

<div class="public-privacidad-container">
    
    <div class="public-privacidad-header">
<h1>🔒 Política de Privacidad</h1>
        <p class="public-privacidad-descripcion">En <?php echo SITE_NAME; ?> nos tomamos muy en serio la protección de tus datos personales</p>

    </div>
    
    <div class="public-privacidad-grid">
        
        <!-- Tarjeta 1 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">🏢 Responsable del tratamiento</h2>
            <div class="public-privacidad-card-contenido">
                <p><strong><?php echo SITE_NAME; ?></strong> es el responsable del tratamiento de sus datos personales.</p>

                <p>📧 Email de contacto: <a href="mailto:auguxtux@gmail.com" class="public-privacidad-enlace">auguxtux@gmail.com</a></p>
            </div>
        </div>
        
        <!-- Tarjeta 2 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">📊&nbsp;&nbsp; Datos que recopilamos</h2>
            <div class="public-privacidad-card-contenido">
                <p>Podemos recopilar la siguiente información:</p>
                <ul class="public-privacidad-lista">
                    <li><strong>📝 Datos de registro:</strong> nombre, email, teléfono, ciudad, fecha de nacimiento</li>
                    <li><strong>💬 Datos de uso:</strong> comentarios, noticias visitadas, interacciones</li>
                    <li><strong>🔌 Actividad de cuenta:</strong> número de conexiones, última actividad y tiempo aproximado de uso autenticado</li>
                    <li><strong>🖥️ Datos técnicos:</strong> dirección IP, tipo de navegador, dispositivo</li>
                </ul>
            </div>
        </div>
        
        <!-- Tarjeta 3 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">🎯&nbsp;&nbsp; Finalidad del tratamiento</h2>
            <div class="public-privacidad-card-contenido">
                <p>Sus datos se utilizan para:</p>
                <ul class="public-privacidad-lista">
                    <li>✅ Gestionar su cuenta de usuario</li>
                    <li>✅ Permitir la publicación de comentarios</li>
                    <li>✅ Mejorar nuestros servicios</li>
                    <li>✅ Administrar sesiones, seguridad y capacidad del portal</li>
                    <li>✅ Enviar comunicaciones (con su consentimiento)</li>
                </ul>
            </div>
        </div>
        
        <!-- Tarjeta 4 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">⚖️&nbsp;&nbsp; Base legal</h2>
            <div class="public-privacidad-card-contenido">
                <p>El tratamiento de sus datos se basa en:</p>
                <ul class="public-privacidad-lista">
                    <li>📋 La ejecución del contrato de servicios</li>
                    <li>✅ Su consentimiento explícito</li>
                    <li>🏛️ El cumplimiento de obligaciones legales</li>
                </ul>
            </div>
        </div>
        
        <!-- Tarjeta 5 -->
        <div class="public-privacidad-card">

            <h2 class="public-privacidad-card-titulo">⏰&nbsp;&nbsp; Conservación de datos</h2>
            <div class="public-privacidad-card-contenido">
                <p>Conservamos sus datos mientras mantenga una cuenta activa. Si elimina su cuenta, conservaremos solo los datos necesarios para cumplir con obligaciones legales.</p>
            </div>
        </div>
        
        <!-- Tarjeta 6 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">🔑&nbsp;&nbsp; Sus derechos</h2>
            <div class="public-privacidad-card-contenido">
                <p>Usted tiene derecho a:</p>
                <ul class="public-privacidad-lista">
                    <li>👁️ Acceder a sus datos personales</li>
                    <li>✏️ Rectificar datos inexactos</li>
                    <li>🗑️ Solicitar la supresión de sus datos</li>
                    <li>🚫 Oponerse al tratamiento</li>
                    <li>⏸️ Solicitar la limitación del tratamiento</li>
                    <li>📦 Portar sus datos</li>
                </ul>
                <p class="public-privacidad-contacto">Para ejercer sus derechos, contacte a: <a href="mailto:auguxtux@gmail.com" class="public-privacidad-enlace">auguxtux@gmail.com</a></p>
            </div>
        </div>
        
        <!-- Tarjeta 7 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">🛡️&nbsp;&nbsp; Seguridad</h2>
            <div class="public-privacidad-card-contenido">
                <p>Implementamos medidas técnicas y organizativas para proteger sus datos contra accesos no autorizados.</p>
            </div>
        </div>
        
        <!-- Tarjeta 8 -->
        <div class="public-privacidad-card">
            <h2 class="public-privacidad-card-titulo">🔄&nbsp;&nbsp; Cambios en la política</h2>
            <div class="public-privacidad-card-contenido">
                <p>Podemos actualizar esta política periódicamente. Le notificaremos cualquier cambio significativo.</p>
            </div>
        </div>
        
    </div>
    
    <div class="public-privacidad-footer">
        <p class="public-privacidad-fecha">📅 Última actualización: <strong><?php echo date('d/m/Y'); ?></strong></p>

        <a href="<?php echo route('home'); ?>" class="public-privacidad-btn">🏠 Volver al inicio</a>

    </div>
    
</div>



<?php require_once __DIR__ . '/../partials/footer.php'; ?>
