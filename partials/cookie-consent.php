<?php
declare(strict_types=1);


/**
 * AVISO DE COOKIES - GDPR COMPLIANT
 * Muestra un banner de consentimiento de cookies
 */
?>

<link rel="stylesheet" href="<?php echo css_url('cookie-consent.css'); ?>">



<div id="cookieConsent" class="cookie-consent" role="region" aria-label="Preferencias de cookies">
    <div class="cookie-consent-content">
        <div class="cookie-consent-text">
            <i class="fas fa-cookie-bite"></i>
            <strong>🍪 Uso de cookies</strong>
            <p>Este sitio utiliza cookies técnicas, de personalización y análisis para mejorar tu experiencia.</p>
        </div>
        <div class="cookie-consent-buttons">
            <button type="button" id="cookieAcceptAll" class="cookie-btn cookie-btn-accept">
                <i class="fas fa-check"></i> Aceptar todas
            </button>
            <button type="button" id="cookieDecline" class="cookie-btn cookie-btn-decline">
                <i class="fas fa-times"></i> Rechazar
            </button>
            <button type="button" id="cookieConfig" class="cookie-btn cookie-btn-config">
                <i class="fas fa-cog"></i> Configurar
            </button>
        </div>
    </div>
</div>

<!-- Modal de configuración de cookies -->
<div
    id="cookieModal"
    class="cookie-modal"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="cookieModalTitle"
>
    <div class="cookie-modal-content" tabindex="-1">
        <div class="cookie-modal-header">
            <h3 id="cookieModalTitle"><i class="fas fa-cookie-bite"></i> Configuración de cookies</h3>
            <button type="button" class="cookie-modal-close" id="closeModal" aria-label="Cerrar configuración de cookies">&times;</button>
        </div>
        <div class="cookie-modal-body">
            <div class="cookie-option">
                <label>
                    <input type="checkbox" id="cookie_necessarias" checked disabled>
                    <strong>Cookies necesarias</strong>
                </label>
                <p>Son esenciales para el funcionamiento del sitio (sesión, seguridad). No se pueden desactivar.</p>
            </div>
            <div class="cookie-option">
                <label>
                    <input type="checkbox" id="cookie_preferencias">
                    <strong>Cookies de preferencias</strong>
                </label>
                <p>Recuerdan tus preferencias como idioma o tema oscuro.</p>
            </div>
            <div class="cookie-option">
                <label>
                    <input type="checkbox" id="cookie_analiticas">
                    <strong>Cookies de análisis</strong>
                </label>
                <p>Nos ayudan a entender cómo usas el sitio para mejorarlo.</p>
            </div>
        </div>
        <div class="cookie-modal-footer">
            <button type="button" id="saveCookiePreferences">Guardar preferencias</button>
            <button type="button" id="acceptAllModal">Aceptar todas</button>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(js_url('cookie-consent.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
