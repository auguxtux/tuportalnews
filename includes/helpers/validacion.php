<?php
declare(strict_types=1);


/**
 * FUNCIONES DE VALIDACIÓN
 */

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarTelefono($telefono) {
    return preg_match('/^[6-9][0-9]{8}$/', $telefono) === 1;
}

/**
 * Valida una URL externa y limita su esquema a HTTP o HTTPS.
 */
function validarUrlHttpHttps(string $url): string|false {
    $urlValidada = filter_var($url, FILTER_VALIDATE_URL);
    if ($urlValidada === false) {
        return false;
    }

    $esquema = strtolower((string) parse_url($urlValidada, PHP_URL_SCHEME));
    return in_array($esquema, ['http', 'https'], true) ? $urlValidada : false;
}

/**
 * Comprueba el formato de los tokens generados para recuperar contraseñas.
 */
function validarFormatoTokenRecuperacion(string $token): bool {
    return strlen($token) === 64 && ctype_xdigit($token);
}

/**
 * Genera el valor irreversible que se almacena en la base de datos.
 */
function hashTokenRecuperacion(string $token): string {
    return hash('sha256', $token);
}
