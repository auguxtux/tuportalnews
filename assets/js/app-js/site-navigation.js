(function () {
    'use strict';

    function inicializarCabecera() {
        var menuLateral = document.getElementById('menuLateral');
        var menuAbrir = document.getElementById('menuAbrir');
        var menuCerrar = document.getElementById('menuCerrar');
        var contenedor = document.getElementById('contenedorPrincipal');
        var btnVolver = document.getElementById('btnVolver');

        if (btnVolver) {
            var referrerMismoOrigen = false;

            try {
                referrerMismoOrigen = document.referrer !== '' &&
                    new URL(document.referrer).origin === window.location.origin;
            } catch (error) {
                referrerMismoOrigen = false;
            }

            btnVolver.style.display = 'flex';
            btnVolver.addEventListener('click', function () {
                if (referrerMismoOrigen) {
                    window.history.back();
                } else {
                    window.location.assign(btnVolver.dataset.urlInicio || '/');
                }
            });
        }

        if (!menuLateral || !menuAbrir || !menuCerrar) {
            return;
        }

        function abrirMenu(evento) {
            if (evento) {
                evento.preventDefault();
            }
            menuLateral.classList.add('abierto');
            menuLateral.setAttribute('aria-hidden', 'false');
            menuLateral.removeAttribute('inert');
            menuAbrir.setAttribute('aria-expanded', 'true');
            if (contenedor) {
                contenedor.classList.add('menu-abierto');
            }
            document.body.style.overflow = 'hidden';
            menuCerrar.focus();
        }

        function cerrarMenu(evento) {
            if (evento) {
                evento.preventDefault();
            }
            menuLateral.classList.remove('abierto');
            menuLateral.setAttribute('aria-hidden', 'true');
            menuLateral.setAttribute('inert', '');
            menuAbrir.setAttribute('aria-expanded', 'false');
            if (contenedor) {
                contenedor.classList.remove('menu-abierto');
            }
            document.body.style.overflow = '';
            menuAbrir.focus();
        }

        menuAbrir.addEventListener('click', abrirMenu);
        menuCerrar.addEventListener('click', cerrarMenu);
        document.addEventListener('keydown', function (evento) {
            if (!menuLateral.classList.contains('abierto')) {
                return;
            }
            if (evento.key === 'Escape') {
                cerrarMenu();
                return;
            }
            if (evento.key !== 'Tab') {
                return;
            }

            var elementosEnfocables = Array.from(menuLateral.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function (elemento) {
                return elemento.offsetParent !== null;
            });
            if (elementosEnfocables.length === 0) {
                evento.preventDefault();
                return;
            }

            var primero = elementosEnfocables[0];
            var ultimo = elementosEnfocables[elementosEnfocables.length - 1];
            if (evento.shiftKey && document.activeElement === primero) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primero.focus();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarCabecera);
    } else {
        inicializarCabecera();
    }
})();
