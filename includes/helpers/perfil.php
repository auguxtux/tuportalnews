<?php
declare(strict_types=1);

/**
 * Normaliza los datos personales compartidos por los perfiles.
 *
 * @return array{nombre:string,telefono:string,ciudad:string,biografia:string,datos_colaboracion:string}
 */
function normalizarDatosPerfil(array $datos): array
{
    return [
        'nombre' => limpiarDatos($datos['nombre'] ?? ''),
        'telefono' => limpiarDatos($datos['telefono'] ?? ''),
        'ciudad' => limpiarDatos($datos['ciudad'] ?? ''),
        'biografia' => limpiarDatos($datos['biografia'] ?? ''),
        'datos_colaboracion' => limpiarDatos($datos['datos_colaboracion'] ?? ''),
    ];
}

/**
 * Aplica las reglas comunes de los datos personales.
 *
 * @param array{nombre:string,telefono:string,ciudad:string,biografia:string,datos_colaboracion?:string} $datos
 * @return list<string>
 */
function validarDatosPerfil(array $datos): array
{
    $errores = [];

    if ($datos['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio';
    }
    if (!validarTelefono($datos['telefono'])) {
        $errores[] = 'Teléfono no válido';
    }
    if ($datos['ciudad'] === '') {
        $errores[] = 'La ciudad es obligatoria';
    }
    if (mb_strlen($datos['nombre']) > 150) {
        $errores[] = 'El nombre no puede superar 150 caracteres';
    }
    if (mb_strlen($datos['ciudad']) > 120) {
        $errores[] = 'La ciudad no puede superar 120 caracteres';
    }
    if (mb_strlen($datos['biografia']) > 500) {
        $errores[] = 'La biografía no puede superar 500 caracteres';
    }

    return $errores;
}

/**
 * Valida un cambio de contraseña sin modificar datos.
 *
 * @return list<string>
 */
function validarCambioPasswordPerfil(
    string $passwordActual,
    string $passwordNueva,
    string $passwordConfirmar,
    string $hashActual
): array {
    $errores = [];

    if (strlen($passwordActual) > 4096) {
        $errores[] = 'La contraseña actual supera la longitud permitida';
    } elseif (!password_verify($passwordActual, $hashActual)) {
        $errores[] = 'La contraseña actual no es correcta';
    }

    if (strlen($passwordNueva) > 4096) {
        $errores[] = 'La nueva contraseña supera la longitud permitida';
    } elseif (strlen($passwordNueva) < 10) {
        $errores[] = 'La nueva contraseña debe tener al menos 10 caracteres';
    }

    if ($passwordNueva !== $passwordConfirmar) {
        $errores[] = 'Las contraseñas no coinciden';
    }

    return $errores;
}
