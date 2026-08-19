<?php
declare(strict_types=1);


/**
 * FUNCIONES DE SEGURIDAD
 */

function limpiarDatos($dato) {
    if (is_array($dato)) {
        return '';
    }

    if (!is_scalar($dato) && $dato !== null) {
        return '';
    }

    return trim((string) $dato);
}

/**
 * Registra un fallo sin copiar mensajes de excepciones que puedan contener
 * consultas, rutas, credenciales o respuestas de proveedores.
 */
function registrarErrorInterno(string $contexto, Throwable $error): void {
    $contexto = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $contexto) ?? 'ERROR';
    error_log(sprintf(
        '[%s][%s:%s]',
        $contexto,
        $error::class,
        (string) $error->getCode()
    ));
}

/**
 * Conserva el formato básico permitido en comentarios y elimina contenido
 * ejecutable, atributos no autorizados y enlaces con esquemas peligrosos.
 */
function sanitizarHtmlComentario(string $html): string {
    if (trim($html) === '') {
        return '';
    }

    $documento = new DOMDocument('1.0', 'UTF-8');
    $estadoErrores = libxml_use_internal_errors(true);
    $contenedorId = 'comentario-contenido-seguro';

    $cargado = $documento->loadHTML(
        '<?xml encoding="UTF-8"><div id="' . $contenedorId . '">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors($estadoErrores);

    if (!$cargado) {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $contenedor = $documento->getElementById($contenedorId);

    if (!$contenedor) {
        return '';
    }

    $etiquetasPermitidas = [
        'p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a', 'b', 'i',
        'span', 's', 'blockquote', 'hr',
    ];
    $etiquetasEliminadasCompletamente = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
        'form', 'input', 'button', 'textarea', 'select', 'option',
    ];

    $limpiarNodo = function (DOMNode $nodo) use (
        &$limpiarNodo,
        $etiquetasPermitidas,
        $etiquetasEliminadasCompletamente
    ): void {
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if ($hijo instanceof DOMElement) {
                $etiqueta = strtolower($hijo->tagName);

                if (in_array($etiqueta, $etiquetasEliminadasCompletamente, true)) {
                    $nodo->removeChild($hijo);
                    continue;
                }

                if (!in_array($etiqueta, $etiquetasPermitidas, true)) {
                    $limpiarNodo($hijo);

                    while ($hijo->firstChild) {
                        $nodo->insertBefore($hijo->firstChild, $hijo);
                    }

                    $nodo->removeChild($hijo);
                    continue;
                }

                $href = $etiqueta === 'a' ? trim($hijo->getAttribute('href')) : '';
                $style = trim($hijo->getAttribute('style'));

                foreach (iterator_to_array($hijo->attributes) as $atributo) {
                    $hijo->removeAttribute($atributo->name);
                }

                if ($etiqueta === 'a' && $href !== '') {
                    $hrefDecodificado = html_entity_decode(
                        $href,
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    );
                    $hrefComprobacion = preg_replace('/[\x00-\x20\x7F]+/u', '', $hrefDecodificado) ?? '';
                    $esRelativo = !preg_match('/^[a-z][a-z0-9+.-]*:/i', $hrefComprobacion)
                        && !str_starts_with($hrefComprobacion, '//');
                    $esquema = strtolower((string) parse_url($hrefComprobacion, PHP_URL_SCHEME));
                    $esPermitido = $esRelativo || in_array($esquema, ['http', 'https', 'mailto'], true);

                    if ($esPermitido) {
                        $hijo->setAttribute('href', $href);

                        if (in_array($esquema, ['http', 'https'], true)) {
                            $hijo->setAttribute('rel', 'noopener noreferrer');
                        }
                    }
                }

                if ($style !== '' && in_array($etiqueta, ['p', 'span', 'blockquote'], true)) {
                    $estilosSeguros = [];
                    $coloresNombrados = [
                        'black', 'white', 'red', 'green', 'blue', 'yellow',
                        'orange', 'purple', 'gray', 'grey', 'transparent',
                    ];

                    foreach (explode(';', $style) as $declaracion) {
                        if (!str_contains($declaracion, ':')) {
                            continue;
                        }

                        [$propiedad, $valor] = array_map('trim', explode(':', $declaracion, 2));
                        $propiedad = strtolower($propiedad);
                        $valor = strtolower($valor);

                        if (
                            $propiedad === 'text-align'
                            && $etiqueta !== 'span'
                            && in_array($valor, ['left', 'center', 'right', 'justify'], true)
                        ) {
                            $estilosSeguros[] = 'text-align: ' . $valor;
                            continue;
                        }

                        if (
                            in_array($propiedad, ['color', 'background-color'], true)
                            && (
                                preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $valor)
                                || preg_match('/^rgba?\(\s*\d{1,3}%?(\s*,\s*\d{1,3}%?){2}(\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $valor)
                                || in_array($valor, $coloresNombrados, true)
                            )
                        ) {
                            $estilosSeguros[] = $propiedad . ': ' . $valor;
                            continue;
                        }

                        if (
                            in_array($propiedad, ['padding-left', 'margin-left'], true)
                            && $etiqueta !== 'span'
                            && preg_match('/^(\d{1,3})px$/', $valor, $coincidencia)
                            && (int) $coincidencia[1] <= 200
                        ) {
                            $estilosSeguros[] = $propiedad . ': ' . $valor;
                        }
                    }

                    if ($estilosSeguros !== []) {
                        $hijo->setAttribute('style', implode('; ', $estilosSeguros));
                    }
                }

                $limpiarNodo($hijo);
            } elseif (!($hijo instanceof DOMText)) {
                $nodo->removeChild($hijo);
            }
        }
    };

    $limpiarNodo($contenedor);
    $resultado = '';

    foreach ($contenedor->childNodes as $hijo) {
        $resultado .= $documento->saveHTML($hijo);
    }

    return trim($resultado);
}

/**
 * Conserva el formato editorial de TinyMCE y elimina contenido ejecutable.
 */
function sanitizarHtmlNoticia(string $html): string {
    if (trim($html) === '') {
        return '';
    }

    /*
     * Compatibilidad con noticias antiguas cuyo HTML completo se guardó como
     * entidades. Solo se decodifica cuando existe una etiqueta editorial de
     * apertura y otra de cierre; después se aplica siempre el saneamiento DOM.
     */
    if (
        preg_match('/&lt;(?:p|div|h[1-6]|ul|ol|blockquote|table)\b/i', $html)
        && preg_match('/&lt;\/(?:p|div|h[1-6]|ul|ol|blockquote|table)&gt;/i', $html)
    ) {
        $html = html_entity_decode(
            $html,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    $documento = new DOMDocument('1.0', 'UTF-8');
    $estadoErrores = libxml_use_internal_errors(true);
    $contenedorId = 'noticia-contenido-seguro';
    $cargado = $documento->loadHTML(
        '<?xml encoding="UTF-8"><div id="' . $contenedorId . '">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($estadoErrores);

    if (!$cargado) {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $contenedor = $documento->getElementById($contenedorId);
    if (!$contenedor) {
        return '';
    }

    $etiquetasPermitidas = [
        'p', 'br', 'strong', 'em', 'u', 's', 'b', 'i', 'ul', 'ol', 'li',
        'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'table', 'thead',
        'tbody', 'tfoot', 'tr', 'td', 'th', 'blockquote', 'pre', 'code',
        'span', 'div', 'hr', 'iframe', 'video', 'source',
    ];
    $etiquetasEliminadasCompletamente = [
        'script', 'style', 'object', 'embed', 'svg', 'math', 'form', 'input',
        'button', 'textarea', 'select', 'option', 'link', 'meta', 'base',
    ];
    $clasesRssPermitidas = [
        'noticia-rss-importada', 'rss-extracto', 'rss-video',
        'rss-boton-externo', 'btn-rss-externo', 'rss-fuente',
    ];

    $urlSegura = static function (string $url, array $esquemas): bool {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $comprobacion = preg_replace('/[\x00-\x20\x7F]+/u', '', $url) ?? '';
        if ($comprobacion === '') {
            return false;
        }
        if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $comprobacion)) {
            return !str_starts_with($comprobacion, '//');
        }
        return in_array(strtolower((string) parse_url($comprobacion, PHP_URL_SCHEME)), $esquemas, true);
    };

    $limpiarNodo = function (DOMNode $nodo) use (
        &$limpiarNodo,
        $etiquetasPermitidas,
        $etiquetasEliminadasCompletamente,
        $clasesRssPermitidas,
        $urlSegura
    ): void {
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if (!($hijo instanceof DOMElement)) {
                if (!($hijo instanceof DOMText)) {
                    $nodo->removeChild($hijo);
                }
                continue;
            }

            $etiqueta = strtolower($hijo->tagName);
            if (in_array($etiqueta, $etiquetasEliminadasCompletamente, true)) {
                $nodo->removeChild($hijo);
                continue;
            }
            if (!in_array($etiqueta, $etiquetasPermitidas, true)) {
                $limpiarNodo($hijo);
                while ($hijo->firstChild) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }
                $nodo->removeChild($hijo);
                continue;
            }

            $atributos = [];
            foreach (iterator_to_array($hijo->attributes) as $atributo) {
                $atributos[strtolower($atributo->name)] = $atributo->value;
                $hijo->removeAttribute($atributo->name);
            }

            if (isset($atributos['class'])) {
                $clases = preg_split('/\s+/', trim($atributos['class'])) ?: [];
                $clases = array_values(array_intersect($clases, $clasesRssPermitidas));
                if ($clases !== []) {
                    $hijo->setAttribute('class', implode(' ', $clases));
                }
            }

            if ($etiqueta === 'a' && isset($atributos['href']) && $urlSegura($atributos['href'], ['http', 'https', 'mailto'])) {
                $hijo->setAttribute('href', $atributos['href']);
                $hijo->setAttribute('rel', 'noopener noreferrer');
                if (($atributos['target'] ?? '') === '_blank') {
                    $hijo->setAttribute('target', '_blank');
                }
            }

            if ($etiqueta === 'img' && isset($atributos['src']) && $urlSegura($atributos['src'], ['http', 'https'])) {
                $hijo->setAttribute('src', $atributos['src']);
                $hijo->setAttribute('alt', substr((string) ($atributos['alt'] ?? ''), 0, 300));
                $hijo->setAttribute('loading', 'lazy');
            } elseif ($etiqueta === 'img') {
                $nodo->removeChild($hijo);
                continue;
            }

            if ($etiqueta === 'iframe') {
                $src = (string) ($atributos['src'] ?? '');
                $host = strtolower((string) parse_url($src, PHP_URL_HOST));
                if (!$urlSegura($src, ['https']) || !in_array($host, ['www.youtube.com', 'youtube.com', 'player.vimeo.com'], true)) {
                    $nodo->removeChild($hijo);
                    continue;
                }
                $hijo->setAttribute('src', $src);
                $hijo->setAttribute('allowfullscreen', 'allowfullscreen');
                $hijo->setAttribute('loading', 'lazy');
            }

            if (in_array($etiqueta, ['video', 'source'], true) && isset($atributos['src']) && $urlSegura($atributos['src'], ['http', 'https'])) {
                $hijo->setAttribute('src', $atributos['src']);
                if ($etiqueta === 'video') {
                    $hijo->setAttribute('controls', 'controls');
                }
                if ($etiqueta === 'source' && isset($atributos['type']) && preg_match('#^video/(mp4|webm|ogg)$#i', $atributos['type'])) {
                    $hijo->setAttribute('type', strtolower($atributos['type']));
                }
            } elseif (in_array($etiqueta, ['video', 'source'], true) && isset($atributos['src'])) {
                $nodo->removeChild($hijo);
                continue;
            }

            foreach (['width', 'height', 'colspan', 'rowspan'] as $nombre) {
                if (isset($atributos[$nombre]) && preg_match('/^\d{1,4}$/', $atributos[$nombre])) {
                    $hijo->setAttribute($nombre, $atributos[$nombre]);
                }
            }

            $style = (string) ($atributos['style'] ?? '');
            if ($style !== '') {
                $estilos = [];
                foreach (explode(';', $style) as $declaracion) {
                    if (!str_contains($declaracion, ':')) {
                        continue;
                    }
                    [$propiedad, $valor] = array_map('trim', explode(':', $declaracion, 2));
                    $propiedad = strtolower($propiedad);
                    $valor = strtolower($valor);
                    if ($propiedad === 'text-align' && in_array($valor, ['left', 'center', 'right', 'justify'], true)) {
                        $estilos[] = $propiedad . ': ' . $valor;
                    } elseif (in_array($propiedad, ['color', 'background-color'], true) && preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9.,% ]+\)|[a-z]{3,20})$/i', $valor)) {
                        $estilos[] = $propiedad . ': ' . $valor;
                    } elseif (in_array($propiedad, ['width', 'height', 'max-width'], true) && preg_match('/^(auto|\d{1,4}(px|%))$/', $valor)) {
                        $estilos[] = $propiedad . ': ' . $valor;
                    } elseif (in_array($propiedad, ['margin-left', 'margin-right'], true) && preg_match('/^(auto|\d{1,3}px)$/', $valor)) {
                        $estilos[] = $propiedad . ': ' . $valor;
                    } elseif ($propiedad === 'display' && in_array($valor, ['block', 'inline', 'inline-block'], true)) {
                        $estilos[] = $propiedad . ': ' . $valor;
                    } elseif ($propiedad === 'float' && in_array($valor, ['left', 'right', 'none'], true)) {
                        $estilos[] = $propiedad . ': ' . $valor;
                    }
                }
                if ($estilos !== []) {
                    $hijo->setAttribute('style', implode('; ', $estilos));
                }
            }

            $limpiarNodo($hijo);
        }
    };

    $limpiarNodo($contenedor);
    $resultado = '';
    foreach ($contenedor->childNodes as $hijo) {
        $resultado .= $documento->saveHTML($hijo);
    }
    return trim($resultado);
}

function obtenerIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
}
