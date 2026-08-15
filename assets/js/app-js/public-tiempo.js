document.addEventListener('DOMContentLoaded', () => {
    configurarSelectoresAemet();
    configurarGraficaAemet();
});

function configurarSelectoresAemet() {
    const formulario = document.querySelector('.aemet-selector');
    const provinciaSelect = document.getElementById('provincia');
    const municipioSelect = document.getElementById('municipio');
    const estadoMunicipios = document.getElementById('aemetMunicipiosEstado');
    const botonUbicacion = document.getElementById('aemetUsarUbicacion');
    const estadoUbicacion = document.getElementById('aemetUbicacionEstado');

    if (!formulario || !provinciaSelect || !municipioSelect) {
        return;
    }

    const endpointMunicipios = formulario.dataset.municipiosUrl ?? '';
    const endpointUbicacion = formulario.dataset.ubicacionUrl ?? '';
    const urlTiempo = formulario.dataset.tiempoUrl ?? '/tiempo';
    let solicitudMunicipiosActual = 0;
    let controladorMunicipios = null;

    async function cargarMunicipios(provincia) {
        solicitudMunicipiosActual += 1;
        const solicitudActual = solicitudMunicipiosActual;

        controladorMunicipios?.abort();
        controladorMunicipios = null;

        if (endpointMunicipios === '' || provincia === '') {
            return;
        }

        const controladorActual = new AbortController();
        controladorMunicipios = controladorActual;

        municipioSelect.disabled = true;

        if (estadoMunicipios) {
            estadoMunicipios.textContent = 'Cargando municipios…';
        }

        try {
            const url = new URL(endpointMunicipios, window.location.origin);
            url.searchParams.set('provincia', provincia);

            const respuesta = await fetch(url.toString(), {
                method: 'GET',
                headers: {Accept: 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controladorActual.signal,
            });

            if (!respuesta.ok) {
                throw new Error(`HTTP ${respuesta.status}`);
            }

            const datos = await respuesta.json();

            if (!datos.ok || !Array.isArray(datos.municipios)) {
                throw new Error('Respuesta no válida');
            }

            if (solicitudActual !== solicitudMunicipiosActual) {
                return;
            }

            const opciones = document.createDocumentFragment();
            let opcionesValidas = 0;

            datos.municipios.forEach((municipio) => {
                const codigo = String(municipio?.codigo ?? '').trim();
                const nombre = String(municipio?.nombre ?? '').trim();

                if (!/^\d{5}$/.test(codigo) || nombre === '') {
                    return;
                }

                const opcion = document.createElement('option');
                opcion.value = codigo;
                opcion.textContent = nombre;
                opciones.appendChild(opcion);
                opcionesValidas += 1;
            });

            if (opcionesValidas === 0) {
                throw new Error('La provincia no contiene municipios válidos');
            }

            municipioSelect.replaceChildren(opciones);

            if (estadoMunicipios) {
                estadoMunicipios.textContent = '';
            }
        } catch (error) {
            if (
                error?.name === 'AbortError'
                || solicitudActual !== solicitudMunicipiosActual
            ) {
                return;
            }

            console.error('No se pudieron cargar los municipios.', error);

            if (estadoMunicipios) {
                estadoMunicipios.textContent =
                    'No se pudieron cargar los municipios.';
            }
        } finally {
            if (solicitudActual === solicitudMunicipiosActual) {
                controladorMunicipios = null;
                municipioSelect.disabled = false;
            }
        }
    }

    function solicitarPosicion(opciones) {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                resolve,
                reject,
                opciones
            );
        });
    }

    async function obtenerPosicionNavegador() {
    return solicitarPosicion({
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
    });
}

    provinciaSelect.addEventListener('change', () => {
        cargarMunicipios(provinciaSelect.value);
    });

    if (!botonUbicacion) {
        return;
    }

    botonUbicacion.addEventListener('click', async () => {
        if (!('geolocation' in navigator)) {
            if (estadoUbicacion) {
                estadoUbicacion.textContent =
                    'Tu navegador no permite obtener la ubicación.';
            }
            return;
        }

        botonUbicacion.disabled = true;

        if (estadoUbicacion) {
            estadoUbicacion.textContent =
                'Solicitando la ubicación del navegador…';
        }

        try {
    const posicion = await obtenerPosicionNavegador();

    const latitud = posicion.coords.latitude;
    const longitud = posicion.coords.longitude;
    const precision = posicion.coords.accuracy;

    if (
        !Number.isFinite(latitud)
        || !Number.isFinite(longitud)
    ) {
        throw new Error('Coordenadas no válidas.');
    }

    /*
     * Los ordenadores suelen ofrecer una ubicación menos precisa que
     * los móviles. Se admite un margen de hasta 50 kilómetros y se avisa
     * al usuario cuando la ubicación es aproximada.
     */
    if (
        !Number.isFinite(precision)
        || precision > 50000
    ) {
        const errorPrecision = new Error(
            'La ubicación del navegador es demasiado imprecisa.'
        );

        errorPrecision.tipo = 'precision';
        errorPrecision.precision = precision;

        throw errorPrecision;
    }

    if (estadoUbicacion) {
        estadoUbicacion.textContent =
            'Buscando el municipio más cercano…';
    }

    const respuesta = await fetch(endpointUbicacion, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        cache: 'no-store',
        body: JSON.stringify({
            latitud,
            longitud,
        }),
    });

    let datos;

    try {
        datos = await respuesta.json();
    } catch {
        throw new Error(
            'El servidor devolvió una respuesta no válida.'
        );
    }

    if (
        !respuesta.ok
        || !datos.ok
        || !datos.municipio
        || typeof datos.municipio.codigo !== 'string'
        || !/^\d{5}$/.test(datos.municipio.codigo)
    ) {
        throw new Error(
            datos.mensaje
            || `HTTP ${respuesta.status}`
        );
    }

    if (estadoUbicacion) {
        estadoUbicacion.textContent = precision > 10000
            ? `Ubicación aproximada: ${datos.municipio.nombre}.`
            : `Municipio localizado: ${datos.municipio.nombre}.`;
    }

    const destino = new URL(
        urlTiempo,
        window.location.origin
    );

    destino.searchParams.set(
        'municipio',
        datos.municipio.codigo
    );

    window.location.assign(destino.toString());

} catch (error) {
    console.error(
        'No se pudo usar la ubicación.',
        error
    );

    let mensaje =
        'No se pudo obtener tu ubicación. '
        + 'Se mantiene el municipio actual.';

    if (error?.tipo === 'precision') {
        const precisionKm = Number.isFinite(error.precision)
            ? Math.round(error.precision / 1000)
            : null;

        mensaje = precisionKm !== null
            ? `La ubicación detectada tiene un margen aproximado `
                + `de ${precisionKm} km. Se mantiene el municipio actual.`
            : 'La ubicación detectada es demasiado imprecisa. '
                + 'Se mantiene el municipio actual.';
    } else if (typeof error?.code === 'number') {
        const mensajes = {
            1: 'No has autorizado el acceso a tu ubicación.',
            2: 'El navegador no pudo determinar tu ubicación.',
            3: 'La solicitud de ubicación tardó demasiado.',
        };

        mensaje =
            (mensajes[error.code]
                ?? 'No se pudo obtener tu ubicación.')
            + ' Se mantiene el municipio actual.';
    }

    if (estadoUbicacion) {
        estadoUbicacion.textContent = mensaje;
    }

    botonUbicacion.disabled = false;
}
    });
}

function configurarGraficaAemet() {
    const canvas = document.getElementById('aemetGrafica');
    const datosElemento = document.getElementById('aemetDatosGrafica');
    const botones = document.querySelectorAll('[data-grafica]');

    if (!canvas || !datosElemento || botones.length === 0) {
        return;
    }

    let datos;

    try {
        datos = JSON.parse(datosElemento.textContent ?? '[]');
    } catch (error) {
        console.error('No se pudieron leer los datos de AEMET.', error);
        return;
    }

    if (!Array.isArray(datos) || datos.length === 0) {
        return;
    }

    const esNumeroValido = (valor) => {
        return valor !== null
            && valor !== ''
            && Number.isFinite(Number(valor));
    };

    const configuraciones = {
        temperatura: {
            titulo: 'Temperatura',
            unidad: '°C',
            minimo: null,
            maximo: null,
            series: [
                {
                    etiqueta: 'Máxima',
                    clave: 'temperatura_maxima',
                    color: '#e67e22',
                },
                {
                    etiqueta: 'Mínima',
                    clave: 'temperatura_minima',
                    color: '#2980b9',
                },
            ],
        },
        humedad: {
            titulo: 'Humedad relativa',
            unidad: '%',
            minimo: 0,
            maximo: 100,
            series: [
                {
                    etiqueta: 'Máxima',
                    clave: 'humedad_maxima',
                    color: '#1677a8',
                },
                {
                    etiqueta: 'Mínima',
                    clave: 'humedad_minima',
                    color: '#55a9ce',
                },
            ],
        },
        lluvia: {
            titulo: 'Probabilidad de lluvia',
            unidad: '%',
            minimo: 0,
            maximo: 100,
            series: [
                {
                    etiqueta: 'Probabilidad',
                    clave: 'probabilidad_lluvia',
                    color: '#4c6faf',
                },
            ],
        },
        viento: {
            titulo: 'Velocidad del viento',
            unidad: 'km/h',
            minimo: 0,
            maximo: null,
            series: [
                {
                    etiqueta: 'Velocidad',
                    clave: 'viento_velocidad',
                    color: '#2f855a',
                },
            ],
        },
    };

    let tipoActual = 'temperatura';

    function dibujarGrafica() {
        const contexto = canvas.getContext('2d');

        if (!contexto) {
            return;
        }

        const contenedor = canvas.parentElement;
        const anchuraCss = Math.max(320, contenedor?.clientWidth ?? 800);
        const alturaCss = Math.max(280, contenedor?.clientHeight ?? 390);
        const proporcion = window.devicePixelRatio || 1;

        canvas.width = Math.round(anchuraCss * proporcion);
        canvas.height = Math.round(alturaCss * proporcion);
        canvas.style.width = `${anchuraCss}px`;
        canvas.style.height = `${alturaCss}px`;

        contexto.setTransform(proporcion, 0, 0, proporcion, 0, 0);

        const estilos = getComputedStyle(document.documentElement);
        const colorTexto = estilos
            .getPropertyValue('--aemet-grafica-texto')
            .trim() || '#334e68';
        const colorRejilla = estilos
            .getPropertyValue('--aemet-grafica-rejilla')
            .trim() || 'rgba(100, 116, 139, 0.18)';
        const colorFondo = estilos
            .getPropertyValue('--aemet-grafica-fondo')
            .trim() || '#f8fbfd';

        contexto.clearRect(0, 0, anchuraCss, alturaCss);
        contexto.fillStyle = colorFondo;
        contexto.fillRect(0, 0, anchuraCss, alturaCss);

        const configuracion = configuraciones[tipoActual];
        const margen = {
            arriba: 55,
            derecha: 24,
            abajo: 48,
            izquierda: 58,
        };

        const anchoGrafica =
            anchuraCss - margen.izquierda - margen.derecha;
        const altoGrafica =
            alturaCss - margen.arriba - margen.abajo;

        const valores = configuracion.series
            .flatMap((serie) => datos.map((fila) => fila[serie.clave]))
            .filter(esNumeroValido)
            .map(Number);

        if (valores.length === 0) {
            contexto.fillStyle = colorTexto;
            contexto.font = '16px sans-serif';
            contexto.textAlign = 'center';
            contexto.fillText(
                'No hay datos disponibles para esta variable.',
                anchuraCss / 2,
                alturaCss / 2
            );
            return;
        }

        let minimo = configuracion.minimo;
        let maximo = configuracion.maximo;

        if (minimo === null) {
            minimo = Math.min(...valores);
        }

        if (maximo === null) {
            maximo = Math.max(...valores);
        }

        if (minimo === maximo) {
            minimo -= 1;
            maximo += 1;
        }

        const margenEscala = (maximo - minimo) * 0.1;

        if (configuracion.minimo === null) {
            minimo -= margenEscala;
        }

        if (configuracion.maximo === null) {
            maximo += margenEscala;
        }

        const x = (indice) => {
            if (datos.length === 1) {
                return margen.izquierda + anchoGrafica / 2;
            }

            return margen.izquierda
                + (indice / (datos.length - 1)) * anchoGrafica;
        };

        const y = (valor) => {
            return margen.arriba
                + ((maximo - valor) / (maximo - minimo)) * altoGrafica;
        };

        contexto.lineWidth = 1;
        contexto.strokeStyle = colorRejilla;
        contexto.fillStyle = colorTexto;
        contexto.font = '12px sans-serif';
        contexto.textAlign = 'right';
        contexto.textBaseline = 'middle';

        const divisiones = 5;

        for (let indice = 0; indice <= divisiones; indice += 1) {
            const valor = minimo
                + ((maximo - minimo) / divisiones) * indice;
            const posicionY = y(valor);

            contexto.beginPath();
            contexto.moveTo(margen.izquierda, posicionY);
            contexto.lineTo(
                margen.izquierda + anchoGrafica,
                posicionY
            );
            contexto.stroke();

            contexto.fillText(
                `${Math.round(valor)} ${configuracion.unidad}`,
                margen.izquierda - 8,
                posicionY
            );
        }

        contexto.textAlign = 'center';
        contexto.textBaseline = 'top';

        datos.forEach((fila, indice) => {
            contexto.fillText(
                String(fila.fecha ?? ''),
                x(indice),
                margen.arriba + altoGrafica + 12
            );
        });

        contexto.font = '600 14px sans-serif';
        contexto.textAlign = 'left';
        contexto.textBaseline = 'middle';

        let posicionLeyenda = margen.izquierda;

        configuracion.series.forEach((serie) => {
            contexto.fillStyle = serie.color;
            contexto.fillRect(posicionLeyenda, 18, 18, 4);

            contexto.fillStyle = colorTexto;
            contexto.fillText(
                serie.etiqueta,
                posicionLeyenda + 25,
                20
            );

            posicionLeyenda +=
                contexto.measureText(serie.etiqueta).width + 65;
        });

        configuracion.series.forEach((serie) => {
            const puntos = datos
                .map((fila, indice) => ({
                    indice,
                    valor: fila[serie.clave],
                }))
                .filter((punto) => esNumeroValido(punto.valor))
                .map((punto) => ({
                    indice: punto.indice,
                    valor: Number(punto.valor),
                }));

            if (puntos.length === 0) {
                return;
            }

            contexto.strokeStyle = serie.color;
            contexto.lineWidth = 3;
            contexto.lineJoin = 'round';
            contexto.lineCap = 'round';
            contexto.beginPath();

            puntos.forEach((punto, indicePunto) => {
                const posicionX = x(punto.indice);
                const posicionY = y(punto.valor);

                if (indicePunto === 0) {
                    contexto.moveTo(posicionX, posicionY);
                } else {
                    contexto.lineTo(posicionX, posicionY);
                }
            });

            contexto.stroke();

            puntos.forEach((punto) => {
                const posicionX = x(punto.indice);
                const posicionY = y(punto.valor);

                contexto.beginPath();
                contexto.arc(posicionX, posicionY, 5, 0, Math.PI * 2);
                contexto.fillStyle = serie.color;
                contexto.fill();

                contexto.beginPath();
                contexto.arc(posicionX, posicionY, 2, 0, Math.PI * 2);
                contexto.fillStyle = '#ffffff';
                contexto.fill();
            });
        });

        canvas.setAttribute(
            'aria-label',
            `Gráfica de ${configuracion.titulo.toLowerCase()} `
            + `durante ${datos.length} días de predicción.`
        );
    }

    function cambiarGrafica(tipo) {
        if (!configuraciones[tipo]) {
            return;
        }

        tipoActual = tipo;

        botones.forEach((boton) => {
            const activo = boton.dataset.grafica === tipo;

            boton.classList.toggle('activo', activo);
            boton.setAttribute(
                'aria-pressed',
                activo ? 'true' : 'false'
            );
        });

        dibujarGrafica();
    }

    botones.forEach((boton) => {
        boton.addEventListener('click', () => {
            cambiarGrafica(boton.dataset.grafica ?? 'temperatura');
        });
    });

    let temporizadorRedimensionado;

    window.addEventListener('resize', () => {
        window.clearTimeout(temporizadorRedimensionado);

        temporizadorRedimensionado = window.setTimeout(
            dibujarGrafica,
            120
        );
    });

    dibujarGrafica();
}
