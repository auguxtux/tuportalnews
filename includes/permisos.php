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
     * Verificar si la sesión pertenece al administrador root configurado.
     */
    public static function esRoot(): bool
    {
        return self::esAdmin()
            && self::esEmailRoot((string) ($_SESSION['usuario_email'] ?? ''));
    }

    /**
     * Identificar la cuenta root sin depender de su identificador interno.
     */
    public static function esEmailRoot(string $email): bool
    {
        return defined('ROOT_ADMIN_EMAIL')
            && ROOT_ADMIN_EMAIL !== ''
            && hash_equals(ROOT_ADMIN_EMAIL, strtolower(trim($email)));
    }

    /**
     * @param array<string, mixed> $usuario
     */
    public static function esUsuarioRoot(array $usuario): bool
    {
        return self::esEmailRoot((string) ($usuario['email'] ?? ''));
    }

    /**
     * Decide en servidor si el admin actual puede gestionar otra cuenta.
     * El root es inmutable y solo el root administra otros administradores.
     *
     * @param array<string, mixed> $usuario
     */
    public static function puedeGestionarUsuario(
        array $usuario,
        string $accion = '',
        string $nuevoRol = ''
    ): bool {
        if (!self::esAdmin() || self::esUsuarioRoot($usuario)) {
            return false;
        }

        $idObjetivo = (int) ($usuario['id_usuario'] ?? 0);
        $idSesion = (int) ($_SESSION['usuario_id'] ?? 0);
        if (
            $idObjetivo > 0
            && $idObjetivo === $idSesion
            && in_array($accion, ['cambiar_rol', 'desactivar', 'eliminar'], true)
        ) {
            return false;
        }

        if (self::esRoot()) {
            return true;
        }

        if ((string) ($usuario['rol'] ?? '') === 'admin' || $nuevoRol === 'admin') {
            return false;
        }

        if ($accion === 'toggle_privado') {
            return (string) ($usuario['rol'] ?? '') === 'periodista';
        }

        if (in_array($accion, ['activar', 'desactivar', 'eliminar'], true)) {
            return (int) ($usuario['creado_por_admin'] ?? 0) === $idSesion;
        }

        return false;
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
     * Requerir la cuenta Root para gestionar datos personales ajenos.
     */
    public static function requerirRoot(): void
    {
        self::requerirAdmin();

        if (self::esRoot()) {
            return;
        }

        mensajeFlash('error', 'Esta acción está reservada al administrador Root.');
        redireccionar(route('admin_dashboard'));
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
