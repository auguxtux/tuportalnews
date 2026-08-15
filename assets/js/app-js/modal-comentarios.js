(function () {
    'use strict';

    var datosElemento = document.getElementById('datosComentariosRelacionados');
    var modal = document.getElementById('modalComentarios');
    var modalBody = document.getElementById('modalComentariosBody');
    var modalFooter = document.getElementById('modalFooter');
    var modalTitulo = document.getElementById('modalTitulo');
    var enlaceNoticia = document.getElementById('modalVerNoticia');
    var botonCerrar = modal ? modal.querySelector('.modal-comentarios-cerrar') : null;
    var comentariosPorNoticia = {};
    var ultimoFoco = null;

    if (!datosElemento || !modal || !modalBody || !modalFooter || !modalTitulo || !enlaceNoticia) {
        return;
    }

    try {
        comentariosPorNoticia = JSON.parse(datosElemento.textContent || '{}');
    } catch (error) {
        comentariosPorNoticia = {};
    }

    function tiempoTranscurrido(fecha) {
        if (!fecha) {
            return 'Fecha desconocida';
        }
        var diferencia = new Date() - new Date(fecha);
        var minutos = Math.floor(diferencia / 60000);
        var horas = Math.floor(minutos / 60);
        var dias = Math.floor(horas / 24);

        if (dias > 0) {
            return 'hace ' + dias + ' día' + (dias > 1 ? 's' : '');
        }
        if (horas > 0) {
            return 'hace ' + horas + ' hora' + (horas > 1 ? 's' : '');
        }
        if (minutos > 0) {
            return 'hace ' + minutos + ' minuto' + (minutos > 1 ? 's' : '');
        }
        return 'hace unos segundos';
    }

    function crearComentario(comentario) {
        var item = document.createElement('div');
        var cabecera = document.createElement('div');
        var autor = document.createElement('span');
        var fecha = document.createElement('span');
        var contenido = document.createElement('div');

        item.className = 'modal-comentario-item';
        autor.className = 'modal-comentario-autor';
        autor.textContent = comentario.usuario_nombre || '';
        fecha.className = 'modal-comentario-fecha';
        fecha.textContent = comentario.fecha_comentario
            ? tiempoTranscurrido(comentario.fecha_comentario)
            : '';
        contenido.className = 'modal-comentario-contenido';
        contenido.innerHTML = comentario.contenido || '';

        cabecera.append(autor, fecha);
        item.append(cabecera, contenido);
        return item;
    }

    function cerrarModal() {
        if (modal.style.display !== 'flex') {
            return;
        }
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (ultimoFoco && typeof ultimoFoco.focus === 'function') {
            ultimoFoco.focus();
        }
    }

    function abrirModal(noticiaId) {
        var datos = comentariosPorNoticia[noticiaId];
        var titulo = datos && typeof datos.titulo === 'string' ? datos.titulo : '';
        ultimoFoco = document.activeElement;
        modalTitulo.textContent = '💬 Comentarios - ' + titulo.substring(0, 50) +
            (titulo.length > 50 ? '...' : '');
        modalBody.replaceChildren();

        if (datos && Array.isArray(datos.comentarios) && datos.comentarios.length > 0) {
            datos.comentarios.forEach(function (comentario) {
                modalBody.appendChild(crearComentario(comentario));
            });
            modalFooter.style.display = 'block';
            enlaceNoticia.href = datos.url;
            enlaceNoticia.target = '_blank';
            enlaceNoticia.rel = 'noopener noreferrer';
        } else {
            var vacio = document.createElement('div');
            vacio.className = 'sin-comentarios';
            vacio.textContent = '📭 No hay comentarios en esta noticia.';
            modalBody.appendChild(vacio);
            modalFooter.style.display = 'none';
        }

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (botonCerrar) {
            botonCerrar.focus();
        }
    }

    function contenerFoco(evento) {
        if (evento.key !== 'Tab') {
            return;
        }
        var enfocables = Array.from(modal.querySelectorAll(
            'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (elemento) {
            return elemento.offsetParent !== null;
        });
        if (enfocables.length === 0) {
            evento.preventDefault();
            modal.querySelector('.modal-comentarios-contenido').focus();
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
    }

    document.querySelectorAll('.btn-ver-comentarios[data-noticia-id]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrirModal(boton.dataset.noticiaId);
        });
    });
    if (botonCerrar) {
        botonCerrar.addEventListener('click', cerrarModal);
    }
    modal.addEventListener('click', function (evento) {
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
        contenerFoco(evento);
    });
})();
