(function () {
    'use strict';

    function inicializarFormulario(formulario) {
        var motivo = formulario.querySelector('[name="motivo"]');
        var descripcion = formulario.querySelector('[name="descripcion"]');
        var etiqueta = formulario.querySelector('[data-etiqueta-descripcion]');
        var contador = formulario.querySelector('[data-contador-descripcion]');
        var boton = formulario.querySelector('button[type="submit"]');
        var mensaje = formulario.parentElement.querySelector('[data-mensaje-reporte]');
        var enlaceRetorno = formulario.querySelector('.usuario-reportar-btn-cancelar');

        if (!motivo || !descripcion || !etiqueta || !contador || !boton || !mensaje) {
            return;
        }

        function actualizarDescripcion() {
            var requiereDescripcion = motivo.value === 'otro';
            descripcion.required = requiereDescripcion;
            etiqueta.textContent = requiereDescripcion
                ? 'Especifica el motivo:'
                : 'Descripción opcional:';
        }

        descripcion.addEventListener('input', function () {
            contador.textContent = String(descripcion.value.length);
        });
        motivo.addEventListener('change', actualizarDescripcion);
        actualizarDescripcion();

        formulario.addEventListener('submit', async function (event) {
            event.preventDefault();
            var redirigir = formulario.dataset.redirigirExito === '1';
            var reporteEnviado = false;
            boton.disabled = true;

            try {
                var respuesta = await fetch(formulario.action, {
                    method: 'POST',
                    body: new FormData(formulario),
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                var datos = await respuesta.json();
                mensaje.textContent = datos.mensaje || 'No se pudo procesar el reporte.';
                mensaje.className = 'usuario-reportar-mensaje activo ' + (datos.success ? 'exito' : 'error');

                if (datos.success) {
                    reporteEnviado = redirigir;
                    formulario.reset();
                    contador.textContent = '0';
                    actualizarDescripcion();

                    if (redirigir && enlaceRetorno) {
                        boton.textContent = 'Reporte enviado';
                        window.setTimeout(function () {
                            window.location.assign(enlaceRetorno.href);
                        }, 1200);
                    }
                }
            } catch (error) {
                mensaje.textContent = 'No se pudo enviar el reporte. Inténtalo de nuevo.';
                mensaje.className = 'usuario-reportar-mensaje activo error';
            } finally {
                boton.disabled = reporteEnviado;
            }
        });
    }

    document.querySelectorAll('.js-form-reporte').forEach(inicializarFormulario);
})();
