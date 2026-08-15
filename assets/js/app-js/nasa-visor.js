(function () {
    'use strict';

    let ultimoFoco = null;
    const cache = new Map();

    function crearVisor() {
        const fondo = document.createElement('div');
        fondo.id = 'nasaVisor';
        fondo.className = 'nasa-visor';
        fondo.hidden = true;
        fondo.innerHTML = [
            '<div class="nasa-visor-dialogo" role="dialog" aria-modal="true" aria-labelledby="nasaVisorTitulo">',
            '<header class="nasa-visor-cabecera">',
            '<h2 id="nasaVisorTitulo"></h2>',
            '<button class="nasa-visor-cerrar" type="button" aria-label="Cerrar visor">✕ Cerrar</button>',
            '</header>',
            '<div class="nasa-visor-medio" aria-live="polite"></div>',
            '<div class="nasa-visor-informacion">',
            '<p class="nasa-visor-descripcion"></p>',
            '</div></div>'
        ].join('');
        document.body.appendChild(fondo);

        const cerrar = () => {
            const video = fondo.querySelector('video');
            if (video) video.pause();
            fondo.hidden = true;
            document.body.classList.remove('nasa-visor-abierto');
            ultimoFoco?.focus();
        };
        fondo.querySelector('.nasa-visor-cerrar').addEventListener('click', cerrar);
        fondo.addEventListener('click', (evento) => {
            if (evento.target === fondo) cerrar();
        });
        fondo.cerrar = cerrar;
        return fondo;
    }

    async function abrir(boton) {
        ultimoFoco = boton;
        const visor = document.getElementById('nasaVisor') || crearVisor();
        const medio = visor.querySelector('.nasa-visor-medio');
        const titulo = visor.querySelector('#nasaVisorTitulo');
        const descripcion = visor.querySelector('.nasa-visor-descripcion');
        const id = boton.dataset.nasaVerId || '';
        const tipo = boton.dataset.nasaVerTipo || '';
        const clave = tipo + ':' + id;

        titulo.textContent = boton.dataset.nasaVerTitulo || 'Contenido NASA';
        descripcion.textContent = boton.dataset.nasaVerDescripcion || 'Sin descripción disponible.';
        medio.classList.remove('nasa-visor-error');
        medio.textContent = 'Cargando recurso…';
        visor.hidden = false;
        document.body.classList.add('nasa-visor-abierto');
        visor.querySelector('.nasa-visor-cerrar').focus();

        try {
            let datos = cache.get(clave);
            if (!datos) {
                const parametros = new URLSearchParams({ id, tipo });
                const respuesta = await fetch('/ajax/nasa-ver?' + parametros.toString(), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                datos = await respuesta.json();
                if (!respuesta.ok || typeof datos.url !== 'string') {
                    throw new Error(typeof datos.error === 'string' ? datos.error : 'Recurso no disponible.');
                }
                cache.set(clave, datos);
            }
            medio.textContent = '';
            if (datos.tipo === 'video') {
                const video = document.createElement('video');
                video.controls = true;
                video.preload = 'metadata';
                video.playsInline = true;
                video.src = datos.url;
                medio.appendChild(video);
            } else {
                const imagen = document.createElement('img');
                imagen.src = datos.url;
                imagen.alt = titulo.textContent;
                medio.appendChild(imagen);
            }
        } catch (error) {
            medio.textContent = error.message || 'No se pudo abrir el recurso.';
            medio.classList.add('nasa-visor-error');
        }
    }

    document.querySelectorAll('.nasa-abrir-visor').forEach((boton) => {
        boton.addEventListener('click', () => abrir(boton));
    });

    document.querySelectorAll('.nasa-traducir-tarjeta').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const tarjeta = boton.closest('.nasa-tarjeta');
            if (!tarjeta) return;
            boton.disabled = true;
            boton.textContent = 'Traduciendo…';
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const cuerpo = new URLSearchParams({
                    accion: 'tarjeta',
                    id: boton.dataset.nasaTraducirId || '',
                    csrf_token: token
                });
                const respuesta = await fetch('/ajax/nasa-traducir', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: cuerpo.toString()
                });
                const datos = await respuesta.json();
                if (!respuesta.ok || typeof datos.titulo !== 'string') {
                    throw new Error(typeof datos.error === 'string' ? datos.error : 'Traducción no disponible.');
                }
                const titulo = tarjeta.querySelector('.nasa-titulo-visor');
                const descripcion = tarjeta.querySelector('.nasa-tarjeta-cuerpo > p');
                if (titulo) titulo.textContent = datos.titulo;
                if (descripcion && typeof datos.descripcion === 'string' && datos.descripcion !== '') {
                    descripcion.textContent = datos.descripcion;
                }
                tarjeta.querySelectorAll('.nasa-abrir-visor').forEach((control) => {
                    control.dataset.nasaVerTitulo = datos.titulo;
                    if (typeof datos.descripcion === 'string' && datos.descripcion !== '') {
                        control.dataset.nasaVerDescripcion = datos.descripcion;
                    }
                });
                boton.textContent = '✓ Traducida';
            } catch (error) {
                boton.disabled = false;
                boton.textContent = '🌐 Traducir';
                window.alert(error.message || 'No se pudo traducir esta tarjeta.');
            }
        });
    });

    document.addEventListener('keydown', (evento) => {
        const visor = document.getElementById('nasaVisor');
        if (evento.key === 'Escape' && visor && !visor.hidden) visor.cerrar();
    });
}());
