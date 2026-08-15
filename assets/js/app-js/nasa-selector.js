(function () {
    'use strict';

    const origen = window.location.origin;
    const estado = document.getElementById('nasaSelectorEstado');

    function avisar(texto, error) {
        if (!estado) return;
        estado.hidden = false;
        estado.textContent = texto;
        estado.classList.toggle('nasa-error', Boolean(error));
        estado.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async function seleccionar(boton) {
        const destino = window.opener && !window.opener.closed
            ? window.opener
            : (window.parent !== window ? window.parent : null);
        if (!destino) {
            avisar('Abre el catálogo desde el formulario de una noticia.', true);
            return;
        }
        const datos = {
            canal: 'tuportalnews-nasa',
            tipo: boton.dataset.tipo,
            id: boton.dataset.id,
            url: boton.dataset.url,
            titulo: boton.dataset.titulo,
            detalle: boton.dataset.detalle
        };
        datos.miniatura = datos.url;

        const selectorParrafos = document.getElementById('nasaParrafos');
        const parrafos = Number.parseInt(selectorParrafos?.value || '0', 10);
        if (parrafos > 0) {
            boton.disabled = true;
            boton.textContent = 'Traduciendo descripción…';
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const cuerpo = new URLSearchParams({
                    id: datos.id,
                    parrafos: String(parrafos),
                    csrf_token: token
                });
                const respuesta = await fetch('/ajax/nasa-traducir', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: cuerpo.toString()
                });
                const json = await respuesta.json();
                if (!respuesta.ok || typeof json.texto !== 'string') {
                    throw new Error(typeof json.error === 'string' ? json.error : 'Traducción no disponible.');
                }
                datos.descripcion = json.texto;
            } catch (error) {
                avisar(error.message || 'No se pudo traducir la descripción.', true);
                boton.disabled = false;
                boton.textContent = datos.tipo === 'video' ? '🎬 Usar este vídeo' : '🖼️ Usar esta imagen';
            }
        }

        if (datos.tipo === 'video') {
            boton.disabled = true;
            boton.textContent = 'Preparando vídeo…';
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const cuerpo = new URLSearchParams({ id: datos.id, csrf_token: token });
                const respuesta = await fetch('/ajax/nasa-asset', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: cuerpo.toString()
                });
                const json = await respuesta.json();
                if (!respuesta.ok || typeof json.url !== 'string') {
                    throw new Error(typeof json.error === 'string' ? json.error : 'Vídeo no disponible.');
                }
                datos.url = json.url;
            } catch (error) {
                avisar(error.message || 'No se pudo preparar el vídeo.', true);
                boton.disabled = false;
                boton.textContent = '🎬 Usar este vídeo';
                return;
            }
        }

        destino.postMessage(datos, origen);
        avisar('Contenido enviado al formulario. Puedes seleccionar otro elemento o cerrar esta ventana.', false);
    }

    document.querySelectorAll('[data-nasa-seleccionar]').forEach((boton) => {
        boton.addEventListener('click', () => seleccionar(boton));
    });

    document.querySelectorAll('[data-abrir-selector-nasa]').forEach((boton) => {
        boton.addEventListener('click', () => {
            let modal = document.getElementById('selectorNasaModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'selectorNasaModal';
                modal.className = 'selector-nasa-modal';
                modal.hidden = true;

                const dialogo = document.createElement('div');
                dialogo.className = 'selector-nasa-dialogo';
                dialogo.setAttribute('role', 'dialog');
                dialogo.setAttribute('aria-modal', 'true');
                dialogo.setAttribute('aria-label', 'Seleccionar contenido de NASA');

                const cerrar = document.createElement('button');
                cerrar.type = 'button';
                cerrar.className = 'selector-nasa-cerrar';
                cerrar.setAttribute('aria-label', 'Cerrar catálogo NASA');
                cerrar.textContent = '✕ Cerrar';

                const marco = document.createElement('iframe');
                marco.className = 'selector-nasa-marco';
                marco.title = 'Catálogo multimedia NASA';
                marco.loading = 'eager';
                marco.src = '/nasa?seleccionar=noticia';

                cerrar.addEventListener('click', () => {
                    modal.hidden = true;
                    document.body.classList.remove('selector-nasa-abierto');
                    boton.focus();
                });
                modal.addEventListener('click', (evento) => {
                    if (evento.target === modal) cerrar.click();
                });
                dialogo.append(cerrar, marco);
                modal.appendChild(dialogo);
                document.body.appendChild(modal);
            }
            modal.hidden = false;
            document.body.classList.add('selector-nasa-abierto');
            modal.querySelector('.selector-nasa-cerrar')?.focus();
        });
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Escape') return;
        const modal = document.getElementById('selectorNasaModal');
        if (modal && !modal.hidden) {
            modal.querySelector('.selector-nasa-cerrar')?.click();
        }
    });

    function rellenarImagen(datos) {
        const principal = document.querySelector('input[name="imagen_url"]');
        if (principal && principal.value.trim() === '') {
            const selector = document.getElementById('chkImagenUrl');
            if (selector && !selector.checked) {
                selector.checked = true;
                selector.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (!selector) {
                const tipoUrl = document.querySelector('input[name="tipo_imagen"][value="url"]');
                if (tipoUrl) {
                    tipoUrl.checked = true;
                    tipoUrl.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            principal.value = datos.url;
            principal.dispatchEvent(new Event('input', { bubbles: true }));
            document.querySelectorAll('textarea[name="texto_imagen_principal"]').forEach((campo) => {
                campo.value = 'NASA · ' + datos.titulo;
            });
            return 'Imagen NASA asignada como imagen principal.';
        }

        for (let numero = 2; numero <= 6; numero += 1) {
            const campo = document.querySelector('input[name="imagen_galeria_url_' + numero + '"]');
            if (!campo || campo.value.trim() !== '') continue;
            const selector = document.querySelector('.chkGaleriaUrl[data-img="' + numero + '"]');
            if (selector && !selector.checked) {
                selector.checked = true;
                selector.dispatchEvent(new Event('change', { bubbles: true }));
            }
            campo.value = datos.url;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            const texto = document.querySelector('textarea[name="texto_imagen_' + numero + '"]');
            if (texto) texto.value = 'NASA · ' + datos.titulo;
            return 'Imagen NASA añadida a la galería.';
        }
        return 'La imagen principal y las cinco posiciones de la galería ya están ocupadas.';
    }

    function rellenarVideo(datos) {
        const radio = document.querySelector('input[name="tipo_video"][value="nasa"]')
            || document.querySelector('input[name="tipo_video"][value="externo"]');
        const campo = document.querySelector('input[name="video_url"]');
        if (!radio || !campo) return 'Este formulario no admite vídeos NASA.';
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
        campo.value = datos.url;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        const medioPrincipal = document.querySelector('input[name="medio_principal"][value="video"]');
        if (medioPrincipal) medioPrincipal.checked = true;
        const imagenPrincipal = document.querySelector('input[name="imagen_url"]');
        if (imagenPrincipal && imagenPrincipal.value.trim() === '' && typeof datos.miniatura === 'string') {
            rellenarImagen({ url: datos.miniatura, titulo: datos.titulo });
        }
        if (window.tinymce) {
            const editor = window.tinymce.get('contenido');
            if (editor) {
                const enlace = document.createElement('a');
                enlace.href = datos.detalle;
                enlace.target = '_blank';
                enlace.rel = 'noopener noreferrer';
                enlace.textContent = 'NASA';
                editor.insertContent('<p>🎬 Fuente multimedia: ' + enlace.outerHTML + '</p>');
            }
        }
        return 'Vídeo NASA asignado a la noticia.';
    }

    window.addEventListener('message', (evento) => {
        if (evento.origin !== origen || !evento.data || evento.data.canal !== 'tuportalnews-nasa') return;
        const datos = evento.data;
        if (typeof datos.url !== 'string' || typeof datos.titulo !== 'string') return;
        const mensaje = datos.tipo === 'video' ? rellenarVideo(datos) : rellenarImagen(datos);
        let mensajeFinal = mensaje;
        if (typeof datos.descripcion === 'string' && datos.descripcion.trim() !== '') {
            const parrafos = datos.descripcion
                .split(/\n\s*\n/)
                .map((texto) => texto.trim())
                .filter(Boolean)
                .map((texto) => '<p>' + texto.replace(/[&<>"']/g, (caracter) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                })[caracter]) + '</p>')
                .join('');
            if (window.tinymce) {
                const editor = window.tinymce.get('contenido');
                if (editor) {
                    editor.insertContent(parrafos);
                    mensajeFinal += ' Descripción NASA traducida añadida al editor.';
                }
            }
        }
        window.alert(mensajeFinal);
    });
}());
