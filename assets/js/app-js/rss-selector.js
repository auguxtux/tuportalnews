(function () {
    'use strict';
    const origen = window.location.origin;

    document.querySelectorAll('[data-rss-seleccionar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const destino = window.parent !== window ? window.parent : window.opener;
            if (!destino) return;
            destino.postMessage({canal:'tuportalnews-rss',idFuente:boton.dataset.idFuente,hash:boton.dataset.hash,titulo:boton.dataset.titulo,contenido:boton.dataset.contenido,enlace:boton.dataset.enlace,imagen:boton.dataset.imagen,fuenteNombre:boton.dataset.fuenteNombre}, origen);
            const estado = document.getElementById('rssSelectorEstado');
            if (estado) { estado.hidden = false; estado.textContent = 'Noticia enviada al formulario. Ya puedes cerrar esta ventana.'; }
        });
    });

    document.querySelectorAll('[data-abrir-selector-rss]').forEach((boton) => {
        boton.addEventListener('click', () => {
            let modal = document.getElementById('selectorRssModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'selectorRssModal'; modal.className = 'selector-rss-modal'; modal.hidden = true;
                modal.innerHTML = '<div class="selector-rss-dialogo" role="dialog" aria-modal="true" aria-label="Seleccionar noticia RSS"><button type="button" class="selector-rss-cerrar">✕ Cerrar</button><iframe class="selector-rss-marco" title="Selector de noticias RSS" src="/periodista/importar-rss?seleccionar=noticia"></iframe></div>';
                document.body.appendChild(modal);
                modal.querySelector('.selector-rss-cerrar').addEventListener('click', () => { modal.hidden = true; document.body.classList.remove('selector-rss-abierto'); boton.focus(); });
                modal.addEventListener('click', (evento) => { if (evento.target === modal) modal.querySelector('.selector-rss-cerrar').click(); });
            }
            modal.hidden = false; document.body.classList.add('selector-rss-abierto'); modal.querySelector('.selector-rss-cerrar').focus();
        });
    });

    window.addEventListener('message', (evento) => {
        if (evento.origin !== origen || !evento.data || evento.data.canal !== 'tuportalnews-rss') return;
        const datos = evento.data;
        if (![datos.idFuente,datos.hash,datos.titulo,datos.contenido,datos.enlace].every((valor) => typeof valor === 'string')) return;
        if (document.querySelector('.editar-noticia-formulario') && !window.confirm('Esta acción reemplazará el título, el contenido, la fuente y la imagen principal actuales. ¿Continuar?')) return;
        const titulo=document.querySelector('input[name="titulo"]'), contenido=document.querySelector('textarea[name="contenido"]'), idFuente=document.querySelector('input[name="rss_id_fuente"]'), hash=document.querySelector('input[name="rss_item_hash"]'), enlace=document.querySelector('input[name="rss_enlace"]');
        if (!titulo || !contenido || !idFuente || !hash || !enlace) return;
        titulo.value=datos.titulo; contenido.value=datos.contenido; idFuente.value=datos.idFuente; hash.value=datos.hash; enlace.value=datos.enlace;
        const reemplazar=document.querySelector('input[name="rss_reemplazar"]'); if(reemplazar) reemplazar.value='1';
        if (window.tinymce?.get('contenido')) window.tinymce.get('contenido').setContent(datos.contenido);
        const imagenUrl=document.querySelector('input[name="imagen_url"]');
        if (imagenUrl && typeof datos.imagen === 'string' && datos.imagen !== '') {
            const tipoUrl=document.querySelector('input[name="tipo_imagen"][value="url"]'), selectorUrl=document.getElementById('chkImagenUrl');
            if (tipoUrl) { tipoUrl.checked=true; tipoUrl.dispatchEvent(new Event('change',{bubbles:true})); }
            if (selectorUrl && !selectorUrl.checked) { selectorUrl.checked=true; selectorUrl.dispatchEvent(new Event('change',{bubbles:true})); }
            imagenUrl.value=datos.imagen; imagenUrl.dispatchEvent(new Event('input',{bubbles:true}));
        }
        const fuenteSelect=document.querySelector('select[name="fuente"]');
        if (fuenteSelect) {
            let opcion=fuenteSelect.querySelector('[data-rss-temporal]');
            if (!opcion) { opcion=document.createElement('option'); opcion.dataset.rssTemporal='1'; fuenteSelect.appendChild(opcion); }
            opcion.value=datos.enlace; opcion.textContent='RSS · '+(datos.fuenteNombre||'Fuente externa'); opcion.selected=true;
        }
        const fuenteSoloLectura=document.querySelector('input#id_fuente[readonly]'); if(fuenteSoloLectura) fuenteSoloLectura.value='RSS · '+(datos.fuenteNombre||'Fuente externa');
        const fuenteIdSelect=document.querySelector('select[name="id_fuente"]');
        if(fuenteIdSelect){let opcion=fuenteIdSelect.querySelector('[data-rss-temporal]');if(!opcion){opcion=document.createElement('option');opcion.dataset.rssTemporal='1';fuenteIdSelect.appendChild(opcion);}opcion.value='rss';opcion.textContent='RSS · '+(datos.fuenteNombre||'Fuente externa');opcion.selected=true;}
        const estado=document.querySelector('[data-rss-seleccion-estado]');
        if (estado) { estado.hidden=false; estado.textContent='✓ Noticia RSS cargada. Selecciona categoría y ubicación, revisa el contenido y guarda.'; }
        document.getElementById('selectorRssModal')?.querySelector('.selector-rss-cerrar')?.click(); titulo.focus();
    });

    document.addEventListener('keydown',(evento)=>{const modal=document.getElementById('selectorRssModal');if(evento.key==='Escape'&&modal&&!modal.hidden)modal.querySelector('.selector-rss-cerrar').click();});
}());
