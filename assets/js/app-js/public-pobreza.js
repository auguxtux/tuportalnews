document.addEventListener('DOMContentLoaded', () => {
    configurarModalesPobreza();
    configurarVisibilidadFiltrosPobreza();
    configurarSelectoresMultiplesPobreza();
    const canvas = document.getElementById('pobrezaGrafica');
    const datosElemento = document.getElementById('pobrezaDatos');
    const gobiernosElemento = document.getElementById('pobrezaGobiernosEspana');
    const mostrarGobierno = document.getElementById('pobrezaMostrarGobiernoEspana');
    const gobiernosAutonomicosElemento = document.getElementById('pobrezaGobiernosAutonomicos');
    const controlesRegionales = document.querySelectorAll('.pobreza-mostrar-gobierno-regional');
    const leyenda = document.getElementById('pobrezaLeyenda');

    if (!canvas || !datosElemento || !leyenda) return;

    let series;
    let gobiernos = [];
    let gobiernosAutonomicos = {};
    try {
        series = JSON.parse(datosElemento.textContent || '[]');
        gobiernos = JSON.parse(gobiernosElemento?.textContent || '[]');
        gobiernosAutonomicos = JSON.parse(gobiernosAutonomicosElemento?.textContent || '{}');
    } catch {
        return;
    }
    if (!Array.isArray(series) || series.length === 0) return;

    const colores = ['#172554', '#c2410c', '#047857', '#7e22ce', '#0369a1', '#be123c', '#4d7c0f'];
    const contexto = canvas.getContext('2d');

    series.forEach((serie, indice) => {
        const elemento = document.createElement('span');
        const muestra = document.createElement('i');
        muestra.style.backgroundColor = colores[indice % colores.length];
        elemento.append(muestra, document.createTextNode(String(serie.nombre || '')));
        leyenda.appendChild(elemento);
    });

    function dibujar() {
        const ancho = Math.max(320, canvas.parentElement?.clientWidth || 320);
        const regionalesVisibles = [...controlesRegionales]
            .filter((control) => control.checked)
            .map((control) => control.value)
            .filter((clave) => gobiernosAutonomicos[clave]);
        const lineasContexto = (mostrarGobierno?.checked ? 1 : 0) + regionalesVisibles.length;
        const altoGrafica = Math.max(280, Math.round(ancho * 0.48));
        const alto = Math.min(760, altoGrafica + lineasContexto * 48);
        const escala = window.devicePixelRatio || 1;
        canvas.width = ancho * escala;
        canvas.height = alto * escala;
        canvas.style.width = `${ancho}px`;
        canvas.style.height = `${alto}px`;
        contexto.setTransform(escala, 0, 0, escala, 0, 0);
        contexto.clearRect(0, 0, ancho, alto);

        const anyos = [...new Set(series.flatMap((serie) => Object.keys(serie.valores || {}).map(Number)))].sort();
        const valores = series.flatMap((serie) => Object.values(serie.valores || {}).map(Number)).filter(Number.isFinite);
        if (anyos.length === 0 || valores.length === 0) return;

        const contextoPoliticoVisible = mostrarGobierno?.checked && Array.isArray(gobiernos);
        const margen = {izquierda: 48, derecha: 20, arriba: lineasContexto > 0 ? lineasContexto * 48 + 14 : 18, abajo: 42};
        const areaAncho = ancho - margen.izquierda - margen.derecha;
        const areaAlto = alto - margen.arriba - margen.abajo;
        const minimo = Math.max(0, Math.floor((Math.min(...valores) - 2) / 5) * 5);
        const maximo = Math.max(minimo + 5, Math.ceil((Math.max(...valores) + 2) / 5) * 5);

        if (lineasContexto > 0) {
            const inicioGrafica = new Date(`${anyos[0]}-01-01T00:00:00Z`).getTime();
            const finGrafica = new Date(`${anyos[anyos.length - 1]}-12-31T23:59:59Z`).getTime();
            const duracion = Math.max(1, finGrafica - inicioGrafica);

            let indiceLinea = 0;
            const dibujarLineaGobierno = (periodos, etiqueta) => {
                const y = 8 + indiceLinea * 48;
                contexto.fillStyle = '#475569';
                contexto.font = '600 11px system-ui, sans-serif';
                contexto.textAlign = 'left';
                contexto.fillText(etiqueta, margen.izquierda, y + 8);

                periodos.forEach((gobierno) => {
                const desde = Math.max(inicioGrafica, Date.parse(`${gobierno.desde}T00:00:00Z`));
                const hasta = Math.min(
                    finGrafica,
                    gobierno.hasta ? Date.parse(`${gobierno.hasta}T00:00:00Z`) : finGrafica
                );
                if (!Number.isFinite(desde) || !Number.isFinite(hasta) || hasta <= desde) return;

                const x = margen.izquierda + ((desde - inicioGrafica) / duracion) * areaAncho;
                const finalX = margen.izquierda + ((hasta - inicioGrafica) / duracion) * areaAncho;
                contexto.globalAlpha = 0.18;
                contexto.fillStyle = String(gobierno.color || '#64748b');
                contexto.fillRect(x, y + 13, Math.max(1, finalX - x), 28);
                contexto.globalAlpha = 1;
                contexto.strokeStyle = String(gobierno.color || '#64748b');
                contexto.strokeRect(x, y + 13, Math.max(1, finalX - x), 28);

                if (finalX - x >= 48) {
                    contexto.save();
                    contexto.beginPath();
                    contexto.rect(x + 2, y + 14, Math.max(0, finalX - x - 4), 26);
                    contexto.clip();
                    contexto.fillStyle = '#0f172a';
                    contexto.font = '600 11px system-ui, sans-serif';
                    contexto.textAlign = 'center';
                    contexto.fillText(String(gobierno.partidos || ''), (x + finalX) / 2, y + 27);
                    contexto.restore();
                }
                });
                indiceLinea += 1;
            };

            if (contextoPoliticoVisible) {
                dibujarLineaGobierno(gobiernos, 'Gobierno de España');
            }
            regionalesVisibles.forEach((clave) => {
                const regional = gobiernosAutonomicos[clave];
                if (Array.isArray(regional?.periodos)) {
                    dibujarLineaGobierno(regional.periodos, `Presidencia: ${regional.nombre}`);
                }
            });
        }

        contexto.font = '12px system-ui, sans-serif';
        contexto.textAlign = 'right';
        contexto.textBaseline = 'middle';
        for (let valor = minimo; valor <= maximo; valor += 5) {
            const y = margen.arriba + areaAlto - ((valor - minimo) / (maximo - minimo)) * areaAlto;
            contexto.strokeStyle = '#dbe3ec';
            contexto.beginPath();
            contexto.moveTo(margen.izquierda, y);
            contexto.lineTo(ancho - margen.derecha, y);
            contexto.stroke();
            contexto.fillStyle = '#475569';
            contexto.fillText(`${valor}%`, margen.izquierda - 8, y);
        }

        contexto.textAlign = 'center';
        const salto = anyos.length > 12 ? 2 : 1;
        anyos.forEach((anyo, indice) => {
            if (indice % salto !== 0 && indice !== anyos.length - 1) return;
            const x = margen.izquierda + (indice / Math.max(1, anyos.length - 1)) * areaAncho;
            contexto.fillStyle = '#475569';
            contexto.fillText(String(anyo), x, alto - 18);
        });

        series.forEach((serie, indiceSerie) => {
            contexto.strokeStyle = colores[indiceSerie % colores.length];
            contexto.lineWidth = serie.nacional ? 3 : 2;
            contexto.beginPath();
            let iniciada = false;
            anyos.forEach((anyo, indice) => {
                const valor = Number(serie.valores?.[anyo]);
                if (!Number.isFinite(valor)) {
                    iniciada = false;
                    return;
                }
                const x = margen.izquierda + (indice / Math.max(1, anyos.length - 1)) * areaAncho;
                const y = margen.arriba + areaAlto - ((valor - minimo) / (maximo - minimo)) * areaAlto;
                if (!iniciada) contexto.moveTo(x, y); else contexto.lineTo(x, y);
                iniciada = true;
            });
            contexto.stroke();
        });
    }

    dibujar();
    mostrarGobierno?.addEventListener('change', dibujar);
    controlesRegionales.forEach((control) => control.addEventListener('change', dibujar));
    new ResizeObserver(dibujar).observe(canvas.parentElement);
});

function configurarModalesPobreza() {
    if (typeof HTMLDialogElement === 'undefined') return;

    const pagina = document.querySelector('.pobreza-pagina');
    const formulario = document.getElementById('pobrezaFormularioFiltros');
    const botonFiltros = document.getElementById('pobrezaAlternarFiltros');
    if (!pagina || !formulario || !botonFiltros) return;

    const prepararDialogo = (dialogo) => {
        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'pobreza-modal-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar ventana');
        cerrar.textContent = '×';
        cerrar.addEventListener('click', () => dialogo.close());
        dialogo.prepend(cerrar);
        dialogo.addEventListener('click', (evento) => {
            if (evento.target === dialogo) dialogo.close();
        });
    };

    const dialogoFiltros = document.createElement('dialog');
    dialogoFiltros.className = 'pobreza-modal pobreza-modal-filtros';
    dialogoFiltros.id = 'pobrezaDialogoFiltros';
    dialogoFiltros.setAttribute('aria-label', 'Configurar comparación');
    formulario.classList.remove('pobreza-filtros-cerrados');
    botonFiltros.insertAdjacentElement('afterend', dialogoFiltros);
    dialogoFiltros.appendChild(formulario);
    prepararDialogo(dialogoFiltros);
    botonFiltros.setAttribute('aria-controls', dialogoFiltros.id);

    const paneles = [...pagina.querySelectorAll('.pobreza-panel')];
    if (paneles.length === 0) return;
    const resumen = document.createElement('section');
    resumen.className = 'pobreza-resumen-indicadores';
    resumen.setAttribute('aria-label', 'Indicadores disponibles');
    paneles[0].insertAdjacentElement('beforebegin', resumen);

    const dialogosPorTitulo = new Map();
    paneles.forEach((panel, indice) => {
        const titulo = panel.querySelector('h2');
        if (!titulo) return;
        const descripcion = panel.querySelector('.pobreza-panel-cabecera p:not(.pobreza-etiqueta)');
        const proveedor = panel.querySelector('.pobreza-panel-cabecera > span');
        const etiqueta = panel.querySelector('.pobreza-etiqueta');

        const tarjeta = document.createElement('article');
        tarjeta.className = 'pobreza-resumen-tarjeta';
        const encabezado = document.createElement('p');
        encabezado.className = 'pobreza-resumen-etiqueta';
        encabezado.textContent = etiqueta?.textContent?.trim() || proveedor?.textContent?.trim() || 'Indicador oficial';
        const nombre = document.createElement('h2');
        nombre.textContent = titulo.textContent;
        const texto = document.createElement('p');
        texto.textContent = descripcion?.textContent?.trim() || 'Consulta la gráfica, los valores y su metodología.';
        const abrir = document.createElement('button');
        abrir.type = 'button';
        abrir.textContent = 'Ver gráfica y datos';

        const dialogo = document.createElement('dialog');
        dialogo.className = 'pobreza-modal pobreza-modal-indicador';
        dialogo.id = `pobrezaDialogoIndicador${indice + 1}`;
        dialogo.setAttribute('aria-labelledby', titulo.id);
        prepararDialogo(dialogo);
        dialogo.appendChild(panel);

        if (indice === 0) {
            const tablaIne = pagina.querySelector('.pobreza-tabla-seccion');
            if (tablaIne) dialogo.appendChild(tablaIne);
        }

        abrir.addEventListener('click', () => dialogo.showModal());
        tarjeta.append(encabezado, nombre, texto, abrir);
        resumen.appendChild(tarjeta);
        document.body.appendChild(dialogo);
        dialogosPorTitulo.set(`#${titulo.id}`, dialogo);
    });

    document.querySelectorAll('.pobreza-navegacion a').forEach((enlace) => {
        const dialogo = dialogosPorTitulo.get(enlace.getAttribute('href'));
        if (!dialogo) return;
        enlace.addEventListener('click', (evento) => {
            evento.preventDefault();
            dialogo.showModal();
        });
    });

    pagina.classList.add('pobreza-modales-activos');
}

document.addEventListener('DOMContentLoaded', () => {
    const colores = ['#172554', '#be123c', '#1d4ed8', '#047857', '#7e22ce', '#c2410c'];

    document.querySelectorAll('[data-pobreza-grafica-simple]').forEach((canvas) => {
        const datosElemento = document.getElementById(canvas.dataset.datos || '');
        const leyenda = document.getElementById(canvas.dataset.leyenda || '');
        if (!datosElemento || !leyenda) return;

        let series;
        try {
            series = JSON.parse(datosElemento.textContent || '[]');
        } catch {
            return;
        }
        if (!Array.isArray(series) || series.length === 0) return;

        const contexto = canvas.getContext('2d');
        series.forEach((serie, indice) => {
            const elemento = document.createElement('span');
            const muestra = document.createElement('i');
            muestra.style.backgroundColor = colores[indice % colores.length];
            elemento.append(muestra, document.createTextNode(String(serie.nombre || '')));
            leyenda.appendChild(elemento);
        });

        const dibujar = () => {
            const ancho = Math.max(320, canvas.parentElement?.clientWidth || 320);
            const alto = Math.max(280, Math.min(520, Math.round(ancho * 0.48)));
            const escala = window.devicePixelRatio || 1;
            canvas.width = ancho * escala;
            canvas.height = alto * escala;
            canvas.style.width = `${ancho}px`;
            canvas.style.height = `${alto}px`;
            contexto.setTransform(escala, 0, 0, escala, 0, 0);
            contexto.clearRect(0, 0, ancho, alto);

            const anyos = [...new Set(series.flatMap((serie) => Object.keys(serie.valores || {}).map(Number)))].sort();
            const valores = series.flatMap((serie) => Object.values(serie.valores || {}).map(Number)).filter(Number.isFinite);
            if (anyos.length === 0 || valores.length === 0) return;

            const margen = {izquierda: 48, derecha: 20, arriba: 18, abajo: 42};
            const areaAncho = ancho - margen.izquierda - margen.derecha;
            const areaAlto = alto - margen.arriba - margen.abajo;
            const minimo = Math.max(0, Math.floor((Math.min(...valores) - 2) / 5) * 5);
            const maximo = Math.max(minimo + 5, Math.ceil((Math.max(...valores) + 2) / 5) * 5);

            contexto.font = '12px system-ui, sans-serif';
            contexto.textBaseline = 'middle';
            contexto.textAlign = 'right';
            for (let valor = minimo; valor <= maximo; valor += 5) {
                const y = margen.arriba + areaAlto - ((valor - minimo) / (maximo - minimo)) * areaAlto;
                contexto.strokeStyle = '#dbe3ec';
                contexto.beginPath();
                contexto.moveTo(margen.izquierda, y);
                contexto.lineTo(ancho - margen.derecha, y);
                contexto.stroke();
                contexto.fillStyle = '#475569';
                contexto.fillText(`${valor}%`, margen.izquierda - 8, y);
            }

            contexto.textAlign = 'center';
            anyos.forEach((anyo, indice) => {
                const x = margen.izquierda + (indice / Math.max(1, anyos.length - 1)) * areaAncho;
                contexto.fillStyle = '#475569';
                contexto.fillText(String(anyo), x, alto - 18);
            });

            series.forEach((serie, indiceSerie) => {
                contexto.strokeStyle = colores[indiceSerie % colores.length];
                contexto.lineWidth = 2.5;
                contexto.beginPath();
                let iniciada = false;
                anyos.forEach((anyo, indice) => {
                    const valor = Number(serie.valores?.[anyo]);
                    if (!Number.isFinite(valor)) {
                        return;
                    }
                    const x = margen.izquierda + (indice / Math.max(1, anyos.length - 1)) * areaAncho;
                    const y = margen.arriba + areaAlto - ((valor - minimo) / (maximo - minimo)) * areaAlto;
                    if (!iniciada) contexto.moveTo(x, y); else contexto.lineTo(x, y);
                    contexto.fillStyle = colores[indiceSerie % colores.length];
                    contexto.fillRect(x - 2, y - 2, 4, 4);
                    iniciada = true;
                });
                contexto.stroke();
            });
        };

        dibujar();
        new ResizeObserver(dibujar).observe(canvas.parentElement);
    });
});

function configurarVisibilidadFiltrosPobreza() {
    const boton = document.getElementById('pobrezaAlternarFiltros');
    const formulario = document.getElementById('pobrezaFormularioFiltros');
    const texto = boton?.querySelector('[data-texto-filtros]');
    if (!boton || !formulario) return;

    const dialogo = formulario.closest('dialog');
    if (dialogo) {
        boton.setAttribute('aria-expanded', 'false');
        if (texto) texto.textContent = 'Configurar comparación';
        boton.addEventListener('click', () => {
            dialogo.showModal();
            boton.setAttribute('aria-expanded', 'true');
        });
        dialogo.addEventListener('close', () => boton.setAttribute('aria-expanded', 'false'));
        return;
    }

    boton.addEventListener('click', () => {
        const cerrado = formulario.classList.toggle('pobreza-filtros-cerrados');
        boton.setAttribute('aria-expanded', String(!cerrado));
        if (texto) texto.textContent = cerrado ? 'Cambiar selección y periodo' : 'Ocultar selección';
        if (!cerrado) formulario.querySelector('select, summary, input')?.focus();
    });
}

function configurarSelectoresMultiplesPobreza() {
    const desplegables = [...document.querySelectorAll('[data-selector-multiple]')];
    desplegables.forEach((selector) => {
        selector.addEventListener('toggle', () => {
            if (!selector.open) return;
            desplegables.forEach((otro) => {
                if (otro !== selector) otro.removeAttribute('open');
            });
        });
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            desplegables.forEach((selector) => selector.removeAttribute('open'));
        }
    });

    document.querySelectorAll('[data-selector-multiple]').forEach((selector) => {
        const buscador = selector.querySelector('[data-buscar-opciones]');
        const lista = selector.querySelector('[data-lista-opciones]');
        const resumen = selector.querySelector('[data-resumen-seleccion]');
        const limpiar = selector.querySelector('[data-limpiar-seleccion]');
        const opciones = [...(lista?.querySelectorAll('label') || [])];
        const casillas = opciones.map((opcion) => opcion.querySelector('input[type="checkbox"]')).filter(Boolean);
        const singular = selector.dataset.singular || 'seleccionado';
        const plural = selector.dataset.plural || 'seleccionados';
        if (!lista || !resumen || casillas.length === 0) return;

        const actualizarResumen = () => {
            const cantidad = casillas.filter((casilla) => casilla.checked).length;
            resumen.textContent = `${cantidad} ${cantidad === 1 ? singular : plural}`;
        };

        casillas.forEach((casilla) => {
            casilla.addEventListener('change', () => {
                const marcadas = casillas.filter((elemento) => elemento.checked);
                if (marcadas.length > 6) {
                    casilla.checked = false;
                    resumen.textContent = 'Máximo 6 selecciones';
                    return;
                }
                actualizarResumen();
            });
        });

        buscador?.addEventListener('input', () => {
            const consulta = buscador.value.trim().toLocaleLowerCase('es');
            let visibles = 0;
            opciones.forEach((opcion) => {
                opcion.hidden = consulta !== ''
                    && !opcion.textContent.toLocaleLowerCase('es').includes(consulta);
                if (!opcion.hidden) visibles += 1;
            });
            buscador.setAttribute('aria-description', visibles === 1 ? '1 resultado' : `${visibles} resultados`);
        });

        limpiar?.addEventListener('click', () => {
            casillas.forEach((casilla) => {
                casilla.checked = false;
            });
            actualizarResumen();
            buscador?.focus();
        });

        actualizarResumen();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('pobrezaGraficaInfantil');
    const datosElemento = document.getElementById('pobrezaDatosInfantiles');
    const leyenda = document.getElementById('pobrezaLeyendaInfantil');
    const gobiernosElemento = document.getElementById('pobrezaGobiernosEuropeos');
    const controlesGobierno = document.querySelectorAll('.pobreza-mostrar-gobierno-europeo');
    if (!canvas || !datosElemento || !leyenda) return;

    let series;
    let gobiernos = {};
    try {
        series = JSON.parse(datosElemento.textContent || '[]');
        gobiernos = JSON.parse(gobiernosElemento?.textContent || '{}');
    } catch {
        return;
    }
    if (!Array.isArray(series) || series.length === 0) return;

    const colores = ['#172554', '#be123c', '#1d4ed8', '#047857', '#7e22ce', '#c2410c', '#4d7c0f'];
    const contexto = canvas.getContext('2d');
    series.forEach((serie, indice) => {
        const elemento = document.createElement('span');
        const muestra = document.createElement('i');
        muestra.style.backgroundColor = colores[indice % colores.length];
        elemento.append(muestra, document.createTextNode(String(serie.nombre || '')));
        leyenda.appendChild(elemento);
    });

    function dibujar() {
        const ancho = Math.max(320, canvas.parentElement?.clientWidth || 320);
        const gobiernosVisibles = [...controlesGobierno]
            .filter((control) => control.checked)
            .map((control) => control.value)
            .filter((clave) => gobiernos[clave]);
        const alto = Math.min(760, Math.max(280, Math.round(ancho * 0.46)) + gobiernosVisibles.length * 48);
        const escala = window.devicePixelRatio || 1;
        canvas.width = ancho * escala;
        canvas.height = alto * escala;
        canvas.style.width = `${ancho}px`;
        canvas.style.height = `${alto}px`;
        contexto.setTransform(escala, 0, 0, escala, 0, 0);
        contexto.clearRect(0, 0, ancho, alto);

        const anyos = [...new Set(series.flatMap((serie) => Object.keys(serie.valores || {}).map(Number)))].sort();
        const valores = series.flatMap((serie) => Object.values(serie.valores || {}).map(Number)).filter(Number.isFinite);
        if (anyos.length === 0 || valores.length === 0) return;

        const margen = {izquierda: 48, derecha: 20, arriba: gobiernosVisibles.length > 0 ? gobiernosVisibles.length * 48 + 14 : 18, abajo: 42};
        const areaAncho = ancho - margen.izquierda - margen.derecha;
        const areaAlto = alto - margen.arriba - margen.abajo;
        const minimo = Math.max(0, Math.floor((Math.min(...valores) - 2) / 5) * 5);
        const maximo = Math.max(minimo + 5, Math.ceil((Math.max(...valores) + 2) / 5) * 5);
        contexto.font = '12px system-ui, sans-serif';
        contexto.textBaseline = 'middle';

        if (gobiernosVisibles.length > 0) {
            const inicioGrafica = new Date(`${anyos[0]}-01-01T00:00:00Z`).getTime();
            const finGrafica = new Date(`${anyos[anyos.length - 1]}-12-31T23:59:59Z`).getTime();
            const duracion = Math.max(1, finGrafica - inicioGrafica);

            gobiernosVisibles.forEach((clave, indiceLinea) => {
                const gobiernoPais = gobiernos[clave];
                const y = 8 + indiceLinea * 48;
                contexto.fillStyle = '#475569';
                contexto.font = '600 11px system-ui, sans-serif';
                contexto.textAlign = 'left';
                contexto.fillText(`Gobierno: ${gobiernoPais.nombre}`, margen.izquierda, y + 8);

                (gobiernoPais.periodos || []).forEach((gobierno) => {
                    const desde = Math.max(inicioGrafica, Date.parse(`${gobierno.desde}T00:00:00Z`));
                    const hasta = Math.min(finGrafica, gobierno.hasta ? Date.parse(`${gobierno.hasta}T00:00:00Z`) : finGrafica);
                    if (!Number.isFinite(desde) || !Number.isFinite(hasta) || hasta <= desde) return;
                    const x = margen.izquierda + ((desde - inicioGrafica) / duracion) * areaAncho;
                    const finalX = margen.izquierda + ((hasta - inicioGrafica) / duracion) * areaAncho;
                    const color = String(gobierno.color || '#64748b');
                    contexto.globalAlpha = 0.18;
                    contexto.fillStyle = color;
                    contexto.fillRect(x, y + 13, Math.max(1, finalX - x), 28);
                    contexto.globalAlpha = 1;
                    contexto.strokeStyle = color;
                    contexto.strokeRect(x, y + 13, Math.max(1, finalX - x), 28);
                    if (finalX - x >= 54) {
                        contexto.save();
                        contexto.beginPath();
                        contexto.rect(x + 2, y + 14, Math.max(0, finalX - x - 4), 26);
                        contexto.clip();
                        contexto.fillStyle = '#0f172a';
                        contexto.font = '600 10px system-ui, sans-serif';
                        contexto.textAlign = 'center';
                        contexto.fillText(String(gobierno.partidos || ''), (x + finalX) / 2, y + 27);
                        contexto.restore();
                    }
                });
            });
        }

        for (let valor = minimo; valor <= maximo; valor += 5) {
            const y = margen.arriba + areaAlto - ((valor - minimo) / (maximo - minimo)) * areaAlto;
            contexto.strokeStyle = '#dbe3ec';
            contexto.beginPath();
            contexto.moveTo(margen.izquierda, y);
            contexto.lineTo(ancho - margen.derecha, y);
            contexto.stroke();
            contexto.fillStyle = '#475569';
            contexto.textAlign = 'right';
            contexto.fillText(`${valor}%`, margen.izquierda - 8, y);
        }

        contexto.textAlign = 'center';
        anyos.forEach((anyo, indice) => {
            const x = margen.izquierda + (indice / Math.max(1, anyos.length - 1)) * areaAncho;
            contexto.fillStyle = '#475569';
            contexto.fillText(String(anyo), x, alto - 18);
        });

        series.forEach((serie, indiceSerie) => {
            contexto.strokeStyle = colores[indiceSerie % colores.length];
            contexto.lineWidth = 3;
            contexto.beginPath();
            let iniciada = false;
            anyos.forEach((anyo, indice) => {
                const valor = Number(serie.valores?.[anyo]);
                if (!Number.isFinite(valor)) return;
                const x = margen.izquierda + (indice / Math.max(1, anyos.length - 1)) * areaAncho;
                const y = margen.arriba + areaAlto - ((valor - minimo) / (maximo - minimo)) * areaAlto;
                if (!iniciada) contexto.moveTo(x, y); else contexto.lineTo(x, y);
                iniciada = true;
            });
            contexto.stroke();
        });
    }

    dibujar();
    controlesGobierno.forEach((control) => control.addEventListener('change', dibujar));
    new ResizeObserver(dibujar).observe(canvas.parentElement);
});
