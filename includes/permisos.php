<?php
declare(strict_types=1);



/**
 * ==========================================================
 * CONTROL DE PERMISOS Y ROLES
 * ==========================================================
 *
 * Gestiona:
 * - Comprobación de roles.
 * - Acceso a áreas restringidas.
 * - Edición de noticias y comentarios.
 * - Propiedad de recursos.
 *
 * Las redirecciones utilizan route() para evitar rutas
 * escritas directamente en el código.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/funciones.php';

final class Permisos
{
    /**
     * Verificar si el usuario tiene un rol específico.
     */
    public static function tieneRol(string $rol): bool
    {
        return estaLogueado()
            && isset($_SESSION['usuario_rol'])
            && $_SESSION['usuario_rol'] === $rol;
    }

    /**
     * Verificar si es administrador.
     */
    public static function esAdmin(): bool
    {
        return self::tieneRol('admin');
    }

    /**
     * Verificar si es periodista.
     */
    public static function esPeriodista(): bool
    {
        return self::tieneRol('periodista');
    }

    /**
     * Verificar si es usuario normal.
     */
    public static function esUsuario(): bool
    {
        return self::tieneRol('usuario');
    }

    /**
     * Verificar si puede acceder a la zona de periodistas.
     */
    public static function puedeAccederPeriodista(): bool
    {
        return estaLogueado()
            && (self::esPeriodista() || self::esAdmin());
    }

    /**
     * Verificar si puede acceder a la zona de administración.
     */
    public static function puedeAccederAdmin(): bool
    {
        return estaLogueado() && self::esAdmin();
    }

    /**
     * Verificar si puede editar una noticia específica.
     */
    public static function puedeEditarNoticia(int $idAutor): bool
    {
        if (!estaLogueado()) {
            return false;
        }

        if (self::esAdmin()) {
            return true;
        }

        return self::esPeriodista()
            && isset($_SESSION['usuario_id'])
            && (int) $_SESSION['usuario_id'] === $idAutor;
    }

    /**
     * Verificar si puede editar un comentario específico.
     */
    public static function puedeEditarComentario(int $idUsuario): bool
    {
        if (!estaLogueado()) {
            return false;
        }

        if (self::esAdmin()) {
            return true;
        }

        return isset($_SESSION['usuario_id'])
            && (int) $_SESSION['usuario_id'] === $idUsuario;
    }

    /**
     * Requerir inicio de sesión.
     */
    public static function requerirLogin(): void
    {
        if (estaLogueado()) {
            return;
        }

        mensajeFlash(
            'warning',
            'Debes iniciar sesión para acceder a esta página'
        );

        redireccionar(route('login'));
        exit;
    }

    /**
     * Requerir rol de administrador.
     */
    public static function requerirAdmin(): void
    {
        self::requerirLogin();

        if (self::esAdmin()) {
            return;
        }

        mensajeFlash(
            'error',
            'No tienes permisos para acceder a esta página'
        );

        redireccionar(route('home'));
        exit;
    }

    /**
     * Requerir rol de periodista o administrador.
     */
    public static function requerirPeriodista(): void
    {
        self::requerirLogin();

        if (self::puedeAccederPeriodista()) {
            return;
        }

        mensajeFlash(
            'error',
            'Área restringida a periodistas y administradores'
        );

        redireccionar(route('home'));
        exit;
    }

    /**
     * Verificar la propiedad de un recurso.
     */
    public static function verificarPropiedad(
        int $idPropietario,
        string $mensajeError = 'No tienes permiso para modificar este recurso'
    ): bool {
        if (!estaLogueado()) {
            return false;
        }

        if (self::esAdmin()) {
            return true;
        }

        if (
            !isset($_SESSION['usuario_id'])
            || (int) $_SESSION['usuario_id'] !== $idPropietario
        ) {
            mensajeFlash('error', $mensajeError);
            return false;
        }

        return true;
    }
}

/**
 * ==========================================================
 * HELPERS GLOBALES PARA USO RÁPIDO EN VISTAS
 * ==========================================================
 *
 * estaLogueado() ya está definida en funciones.php.
 */

function esAdmin(): bool
{
    return Permisos::esAdmin();
}

function esPeriodista(): bool
{
    return Permisos::esPeriodista();
}

function esUsuario(): bool
{
    return Permisos::esUsuario();
}
