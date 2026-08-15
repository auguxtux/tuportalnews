<?php
declare(strict_types=1);


/**
 * POLÍTICA DE COOKIES - Versión mejorada
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$titulo_pagina = 'Política de Cookies';
require_once __DIR__ . '/../partials/header.php';
?>

       <link rel="stylesheet" href="<?php echo css_url('public-cookies.css'); ?>">


<div class="cookies-policy">
    <div class="cookies-header">
        <h1><i class="fas fa-cookie-bite"></i>Política de Cookies</h1>
        <p>Última actualización: <?php echo date('d/m/Y'); ?></p>

        <p>Esta política explica qué son las cookies, cómo las usamos y cómo puedes gestionarlas.</p>
    </div>

    <!-- ¿Qué son las cookies? -->
    <div class="cookies-section" style="border-left-color: #2563eb;">
        <h2>
            <i class="fas fa-question-circle" style="color: #2563eb;"></i>
            ¿Qué son las cookies?
        </h2>
        <p>Las cookies son pequeños archivos de texto que los sitios web colocan en tu dispositivo (ordenador, tablet o móvil) cuando los visitas. Contienen información que permite al sitio web recordar tus acciones y preferencias durante un período de tiempo.</p>
        <p>Las cookies no contienen virus ni pueden ejecutar programas en tu dispositivo. Son seguras y se utilizan en prácticamente todas las páginas web.</p>
    </div>

    <!-- Tipos de cookies -->
    <div class="cookies-section" style="border-left-color: #10b981;">
        <h2>
            <i class="fas fa-tag" style="color: #10b981;"></i>
            Tipos de cookies que utilizamos
        </h2>
        
        <h3><span class="badge-cookie badge-necessary">🍪 Necesarias</span></h3>
        <p>Son esenciales para el funcionamiento básico del sitio web. Sin ellas, algunas funcionalidades no estarían disponibles.</p>
        <ul>
            <li><strong>Autenticación:</strong> Te mantienen identificado mientras navegas.</li>
            <li><strong>Seguridad:</strong> Protegen tu sesión y previenen ataques.</li>
            <li><strong>Sesión:</strong> Permiten recordar tu carrito o formularios en proceso.</li>
        </ul>
        
        <h3><span class="badge-cookie badge-preferences">⚙️ Preferencias</span></h3>
        <p>Recuerdan tus preferencias para ofrecerte una experiencia personalizada.</p>
        <ul>
            <li><strong>Idioma:</strong> Guardan tu idioma preferido.</li>
            <li><strong>Apariencia:</strong> Recuerdan si prefieres tema oscuro o claro.</li>
            <li><strong>Configuración:</strong> Guardan ajustes personalizados del sitio.</li>
        </ul>
        
        <h3><span class="badge-cookie badge-analytics">📊 Análisis</span></h3>
        <p>Nos ayudan a entender cómo interactúan los usuarios con nuestro sitio para mejorarlo.</p>
        <ul>
            <li><strong>Google Analytics:</strong> Analiza el tráfico, páginas visitadas, tiempo de navegación.</li>
            <li><strong>Comportamiento:</strong> Nos permite saber qué contenido es más relevante.</li>
            <li><strong>Rendimiento:</strong> Detecta errores y problemas de navegación.</li>
        </ul>
    </div>

    <!-- Tabla de cookies específicas -->
    <div class="cookies-section" style="border-left-color: #f59e0b;">
        <h2>
            <i class="fas fa-table-list" style="color: #f59e0b;"></i>
            Cookies que utilizamos actualmente
        </h2>
        <table class="cookies-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Duración</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>news_session</code></td>
                    <td><span class="badge-cookie badge-necessary">Necesaria</span></td>
                    <td>Sesión</td>
                    <td>Mantiene tu sesión iniciada mientras navegas.</td>
                </tr>
                <tr>
                    <td><code>csrf_token</code></td>
                    <td><span class="badge-cookie badge-necessary">Necesaria</span></td>
                    <td>Sesión</td>
                    <td>Protege los formularios contra ataques CSRF.</td>
                </tr>
                <tr>
                    <td><code>visitor_id</code></td>
                    <td><span class="badge-cookie badge-preferences">Preferencia</span></td>
                    <td>1 año</td>
                    <td>Identifica visitantes únicos para valoraciones.</td>
                </tr>
                <tr>
                    <td><code>cookie_consent</code></td>
                    <td><span class="badge-cookie badge-necessary">Necesaria</span></td>
                    <td>1 año</td>
                    <td>Guarda tu preferencia de cookies.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Cómo gestionar -->
    <div class="cookies-section" style="border-left-color: #8b5cf6;">
        <h2>
            <i class="fas fa-sliders-h" style="color: #8b5cf6;"></i>
            ¿Cómo gestionar las cookies?
        </h2>
        <p>Puedes gestionar tus preferencias de cookies de varias formas:</p>
        <ul>
            <li><strong>Desde nuestro banner:</strong> Al entrar por primera vez, puedes aceptar, rechazar o configurar las cookies.</li>
            <li><strong>Configuración del navegador:</strong> Puedes bloquear o eliminar cookies desde la configuración de tu navegador.</li>
        </ul>
        <p><strong>Enlaces de ayuda por navegador:</strong></p>
        <ul>
            <li><i class="fab fa-chrome"></i> <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener noreferrer">Google Chrome</a></li>
            <li><i class="fab fa-firefox"></i> <a href="https://support.mozilla.org/es/kb/Borrar%20cookies" target="_blank" rel="noopener noreferrer">Mozilla Firefox</a></li>
            <li><i class="fab fa-safari"></i> <a href="https://support.apple.com/es-es/guide/safari/sfri11471/mac" target="_blank" rel="noopener noreferrer">Safari</a></li>
            <li><i class="fab fa-edge"></i> <a href="https://support.microsoft.com/es-es/microsoft-edge/eliminar-las-cookies-en-microsoft-edge" target="_blank" rel="noopener noreferrer">Microsoft Edge</a></li>
        </ul>
    </div>

    <!-- Más información -->
    <div class="cookies-section" style="border-left-color: #ef4444;">
        <h2>
            <i class="fas fa-info-circle" style="color: #ef4444;"></i>
            Más información
        </h2>
        <p>Si tienes alguna duda sobre nuestra política de cookies, puedes contactarnos a través de:</p>
        <ul>
            <li><i class="fas fa-envelope"></i> Email: <a href="mailto:auguxtux@gmail.com">auguxtux@gmail.com</a></li>
            <li><i class="fas fa-phone"></i> Teléfono: <a href="tel:+34613262735">613 262 735</a></li>
            <li><i class="fas fa-envelope-open-text"></i> <a href="<?php echo route('contacto'); ?>">Formulario de contacto</a></li>
        </ul>
    </div>

    <!-- Botones de acción -->
    <div class="cookies-actions">
        <a href="<?php echo base_url(''); ?>" class="btn-cookie btn-cookie-outline">

            <i class="fas fa-arrow-left"></i> Inicio
        </a>
        <a href="javascript:void(0);" onclick="resetCookiesAndReload()" class="btn-cookie btn-cookie-primary">
            <i class="fas fa-cookie-bite"></i> Preferencias
        </a>
        <a href="<?php echo route('contacto'); ?>" class="btn-cookie btn-cookie-secondary">
            <i class="fas fa-question-circle"></i> Contactar
        </a>
    </div>
</div>

<script>
function resetCookiesAndReload() {
    localStorage.removeItem('cookie_consent');
    localStorage.removeItem('cookie_preferencias');
    localStorage.removeItem('cookie_analiticas');
    window.location.reload();
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
