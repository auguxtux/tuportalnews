document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var modal = document.getElementById('cookieModal');
    var banner = document.getElementById('cookieConsent');
    if (!modal || !banner) {
        return;
    }

    var contenidoModal = modal.querySelector('.cookie-modal-content');
    var preferenciasCampo = document.getElementById('cookie_preferencias');
    var analiticasCampo = document.getElementById('cookie_analiticas');
    var activadorModal = null;

    if (!localStorage.getItem('cookie_consent')) {
        window.setTimeout(function () {
            banner.classList.add('show');
        }, 500);
    }

    function cerrarModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (activadorModal && typeof activadorModal.focus === 'function') {
            activadorModal.focus();
        }
    }

    function aceptarTodas() {
        localStorage.setItem('cookie_consent', 'all');
        localStorage.setItem('cookie_preferencias', 'true');
        localStorage.setItem('cookie_analiticas', 'true');
        banner.classList.remove('show');
        if (modal.style.display === 'flex') {
            cerrarModal();
        }
    }

    function rechazar() {
        localStorage.setItem('cookie_consent', 'necessary');
        localStorage.setItem('cookie_preferencias', 'false');
        localStorage.setItem('cookie_analiticas', 'false');
        banner.classList.remove('show');
    }

    function guardarPreferencias() {
        localStorage.setItem('cookie_consent', 'custom');
        localStorage.setItem('cookie_preferencias', String(preferenciasCampo.checked));
        localStorage.setItem('cookie_analiticas', String(analiticasCampo.checked));
        banner.classList.remove('show');
        cerrarModal();
    }

    function abrirModal() {
        activadorModal = document.activeElement;
        preferenciasCampo.checked = localStorage.getItem('cookie_preferencias') === 'true';
        analiticasCampo.checked = localStorage.getItem('cookie_analiticas') === 'true';
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        contenidoModal.focus();
    }

    document.getElementById('cookieAcceptAll').addEventListener('click', aceptarTodas);
    document.getElementById('cookieDecline').addEventListener('click', rechazar);
    document.getElementById('cookieConfig').addEventListener('click', abrirModal);
    document.getElementById('saveCookiePreferences').addEventListener('click', guardarPreferencias);
    document.getElementById('acceptAllModal').addEventListener('click', aceptarTodas);
    document.getElementById('closeModal').addEventListener('click', cerrarModal);

    window.addEventListener('click', function (evento) {
        if (evento.target === modal) {
            cerrarModal();
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (modal.style.display !== 'flex') {
            return;
        }
        if (evento.key === 'Escape') {
            evento.preventDefault();
            cerrarModal();
            return;
        }
        if (evento.key !== 'Tab') {
            return;
        }

        var enfocables = Array.from(modal.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
        )).filter(function (elemento) {
            return elemento.offsetParent !== null;
        });
        if (enfocables.length === 0) {
            evento.preventDefault();
            contenidoModal.focus();
            return;
        }

        var primero = enfocables[0];
        var ultimo = enfocables[enfocables.length - 1];
        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });
});
