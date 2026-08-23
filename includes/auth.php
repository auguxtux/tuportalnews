<?php
declare(strict_types=1);


/**
 * ==========================================================
 * SISTEMA DE AUTENTICACIÓN
 * ==========================================================
 *
 * Mejoras incluidas:
 * - Consultas preparadas mediante PDO.
 * - Regeneración del ID de sesión al iniciar sesión.
 * - Separación entre hora de login y última actividad.
 * - Cierre completo y seguro de sesión.
 * - Comparación temporal mediante hash ficticio si el usuario no existe.
 * - Actualización automática del hash de contraseña.
 * - Roles de registro restringidos a usuario y periodista.
 * - Contraseña mínima de 10 caracteres.
 * - Tokens de recuperación seguros y de un solo uso.
 * - Invalidación de sesiones tras restablecer contraseña.
 * - Menor exposición de datos personales en los logs.
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/helpers/login-attempts.php';

final class Auth
{
    private PDO $pdo;

    /**
     * @var string[]
     */
    private array $errores = [];

    /**
     * Hash válido utilizado para reducir diferencias temporales
     * cuando el email no existe.
     */
    private const DUMMY_PASSWORD_HASH =
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    /**
     * Roles que pueden seleccionarse desde el registro público.
     */
    private const ROLES_REGISTRO = [
        'usuario',
        'periodista',
    ];

    public function __construct()
    {
        $this->pdo = db();
    }

    /**
     * Iniciar sesión.
     *
     * Solo permite acceder a usuarios con estado activo.
     */
    public function login(string $email, string $password): bool
    {
        $this->errores = [];

        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            $this->errores[] = 'Email y contraseña son obligatorios.';
            return false;
        }

        if (strlen($password) > 4096) {
            $this->errores[] = 'Email o contraseña incorrectos.';
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errores[] = 'Email o contraseña incorrectos.';
            return false;
        }

        if (estaBloqueado($email)) {
            $this->errores[] = 'Demasiados intentos fallidos. Espera unos minutos e inténtalo de nuevo.';
            return false;
        }

        try {
            $sql = "
                SELECT
                    id_usuario,
                    email,
                    password,
                    nombre,
                    rol,
                    estado,
                    avatar
                FROM usuarios
                WHERE email = :email
                  AND estado IN ('activo', 'pendiente')
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':email' => $email,
            ]);

            $usuario = $stmt->fetch();

            /*
             * Se ejecuta password_verify incluso cuando el usuario no existe,
             * reduciendo diferencias temporales que podrían facilitar la
             * enumeración de cuentas.
             */
            $hashComprobacion = $usuario['password']
                ?? self::DUMMY_PASSWORD_HASH;

            $passwordValido = password_verify(
                $password,
                $hashComprobacion
            );

            if (!$usuario) {
                registrarIntentoFallido($email);
                $this->errores[] = 'Email o contraseña incorrectos.';
                return false;
            }

            if (!$passwordValido) {
                registrarIntentoFallido($email);
                $this->errores[] = 'Email o contraseña incorrectos.';
                return false;
            }

            if ($usuario['estado'] === 'pendiente') {
                $this->errores[] =
                    '⏳ Tu cuenta está pendiente de aprobación. '
                    . 'Recibirás un email cuando sea activada.';

                return false;
            }

            if (
                $usuario['estado'] !== 'activo'
            ) {
                $this->errores[] = 'Email o contraseña incorrectos.';
                return false;
            }

            if (
                password_needs_rehash(
                    $usuario['password'],
                    PASSWORD_DEFAULT
                )
            ) {
                $hashActualizado = $this->actualizarHashPassword(
                    (int) $usuario['id_usuario'],
                    $password
                );

                if ($hashActualizado !== false) {
                    $usuario['password'] = $hashActualizado;
                }
            }

            limpiarIntentosFallidos($email);
            $this->iniciarSesion($usuario);

            return true;
        } catch (PDOException $e) {
            registrarErrorInterno('AUTH.LOGIN', $e);

            $this->errores[] =
                'No se pudo completar la autenticación. Inténtalo más tarde.';

            return false;
        }
    }

    /**
     * Cerrar completamente la sesión.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?: '/',
                    'domain'   => $params['domain'],
                    'secure'   => (bool) $params['secure'],
                    'httponly' => (bool) $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Verificar si existe una sesión autenticada y vigente.
     */
    public function checkAuth(): bool
    {
        if (
            !isset(
                $_SESSION['usuario_id'],
                $_SESSION['usuario_rol'],
                $_SESSION['login_time'],
                $_SESSION['last_activity']
            )
        ) {
            return false;
        }

        $ahora = time();

        $ultimaActividad = (int) $_SESSION['last_activity'];
        $tiempoInactivo = $ahora - $ultimaActividad;

        if ($tiempoInactivo > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        /*
         * Renovar periódicamente el ID durante sesiones largas.
         * No se hace en cada petición para evitar trabajo innecesario.
         */
        $ultimaRegeneracion =
            (int) ($_SESSION['last_regeneration'] ?? 0);

        if (($ahora - $ultimaRegeneracion) > 900) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = $ahora;
        }

        $this->registrarActividadAutenticada($ahora);

        $_SESSION['last_activity'] = $ahora;

        return true;
    }

    /**
     * Obtener los datos del usuario autenticado.
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->checkAuth()) {
            return null;
        }

        try {
            $sql = "
                SELECT
                    id_usuario,
                    email,
                    nombre,
                    telefono,
                    ciudad,
                    avatar,
                    password,
                    rol,
                    estado,
                    fecha_registro,
                    ultimo_acceso
                FROM usuarios
                WHERE id_usuario = :id
                  AND estado = 'activo'
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => (int) $_SESSION['usuario_id'],
            ]);

            $usuario = $stmt->fetch();

            if (!$usuario) {
                $this->logout();
                return null;
            }

            $huellaEsperada = hash(
                'sha256',
                (string) $usuario['password']
            );
            $huellaSesion = (string) (
                $_SESSION['auth_password_fingerprint'] ?? ''
            );

            if (
                $huellaSesion === ''
                || !hash_equals($huellaEsperada, $huellaSesion)
            ) {
                $this->logout();
                return null;
            }

            /*
             * Sincronizar información sensible de autorización con la BD.
             */
            $_SESSION['usuario_rol'] = $usuario['rol'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_avatar'] = $usuario['avatar'];

            unset($usuario['password']);

            return $usuario;
        } catch (PDOException $e) {
            registrarErrorInterno('AUTH.CURRENT_USER', $e);

            return null;
        }
    }

    /**
     * Solicitar recuperación de contraseña.
     *
     * Devuelve el enlace para conservar la compatibilidad con
     * el código actual de envío de correo.
     */
    public function solicitarRecuperacion(string $email): string|false
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $sql = "
                SELECT
                    id_usuario,
                    nombre,
                    email,
                    token_recuperacion,
                    token_expiracion
                FROM usuarios
                WHERE email = :email
                  AND estado = 'activo'
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':email' => $email,
            ]);

            $usuario = $stmt->fetch();

            if (!$usuario) {
                return false;
            }

            $solicitudReciente = !empty($usuario['token_recuperacion'])
                && !empty($usuario['token_expiracion'])
                && strtotime((string) $usuario['token_expiracion'])
                    > time() + 55 * 60;

            if ($solicitudReciente) {
                return '';
            }

            /*
             * Un nuevo token sustituye e invalida automáticamente
             * cualquier token anterior del usuario.
             */
            $token = bin2hex(random_bytes(32));
            $tokenHash = hashTokenRecuperacion($token);
            $expiracion = date(
                'Y-m-d H:i:s',
                time() + 3600
            );

            $sql = "
                UPDATE usuarios
                SET
                    token_recuperacion = :token,
                    token_expiracion = :expiracion
                WHERE id_usuario = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $resultado = $stmt->execute([
                ':token'      => $tokenHash,
                ':expiracion' => $expiracion,
                ':id'         => (int) $usuario['id_usuario'],
            ]);

            if (!$resultado) {
                return false;
            }

            $enlace = SITE_URL
                . '/resetear_password?token='
                . rawurlencode($token);

            if (!enviarEmailRecuperacion(
                (string) $usuario['email'],
                (string) $usuario['nombre'],
                $token
            )) {
                $stmt = $this->pdo->prepare("
                    UPDATE usuarios
                    SET token_recuperacion = NULL,
                        token_expiracion = NULL
                    WHERE id_usuario = :id
                      AND token_recuperacion = :token
                ");
                $stmt->execute([
                    ':id' => (int) $usuario['id_usuario'],
                    ':token' => $tokenHash,
                ]);

                return false;
            }

            return $enlace;
        } catch (Throwable $e) {
            registrarErrorInterno('AUTH.PASSWORD_REQUEST', $e);

            return false;
        }
    }

    /**
     * Comprobar si un token de recuperación es válido.
     */
    public function verificarToken(string $token): array|false
    {
        $token = trim($token);

        /*
         * bin2hex(random_bytes(32)) produce exactamente
         * 64 caracteres hexadecimales.
         */
        if (!validarFormatoTokenRecuperacion($token)) {
            return false;
        }

        try {
            $sql = "
                SELECT id_usuario, nombre, email
                FROM usuarios
                WHERE token_recuperacion = :token
                  AND token_expiracion > NOW()
                  AND estado = 'activo'
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':token' => hashTokenRecuperacion($token),
            ]);

            return $stmt->fetch();
        } catch (PDOException $e) {
            registrarErrorInterno('AUTH.TOKEN_VERIFY', $e);

            return false;
        }
    }

    /**
     * Restablecer la contraseña mediante un token válido.
     */
    public function restablecerPassword(
        string $token,
        string $nuevaPassword
    ): bool {
        $this->errores = [];
        $token = trim($token);

        if (
            !validarFormatoTokenRecuperacion($token)
            || !$this->validarPassword($nuevaPassword)
        ) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            /*
             * Bloquear la fila para impedir que el mismo token
             * sea utilizado simultáneamente en dos peticiones.
             */
            $sql = "
                SELECT id_usuario
                FROM usuarios
                WHERE token_recuperacion = :token
                  AND token_expiracion > NOW()
                  AND estado = 'activo'
                LIMIT 1
                FOR UPDATE
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':token' => hashTokenRecuperacion($token),
            ]);

            $usuario = $stmt->fetch();

            if (!$usuario) {
                $this->pdo->rollBack();
                $this->errores[] =
                    'El enlace de recuperación no es válido o ha caducado.';

                return false;
            }

            $passwordHash = password_hash(
                $nuevaPassword,
                PASSWORD_DEFAULT
            );

            $sql = "
                UPDATE usuarios
                SET
                    password = :password,
                    token_recuperacion = NULL,
                    token_expiracion = NULL
                WHERE id_usuario = :id
            ";

            $stmt = $this->pdo->prepare($sql);

            $resultado = $stmt->execute([
                ':password' => $passwordHash,
                ':id'       => (int) $usuario['id_usuario'],
            ]);

            $this->pdo->commit();

            /*
             * Si quien restablece la contraseña tenía una sesión
             * abierta en este navegador, se cierra.
             */
            if (
                isset($_SESSION['usuario_id'])
                && (int) $_SESSION['usuario_id']
                    === (int) $usuario['id_usuario']
            ) {
                $this->logout();
            }

            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            registrarErrorInterno('AUTH.PASSWORD_RESET', $e);

            $this->errores[] =
                'No se pudo restablecer la contraseña.';

            return false;
        }
    }

    /**
     * Registrar un nuevo usuario.
     *
     * Periodista: estado pendiente.
     * Usuario: estado activo.
     */
    public function registrar(array $datos): bool
    {
        $this->errores = [];

        try {
            if (!$this->configuracionActiva('permitir_registro')) {
                $this->errores[] =
                    'El registro de usuarios está deshabilitado.';

                return false;
            }

            /*
             * Nunca aceptar un rol arbitrario procedente del formulario.
             * Esto impide registrar directamente una cuenta como admin.
             */
            $rolSolicitado = strtolower(
                trim((string) ($datos['rol'] ?? 'usuario'))
            );

            $rol = in_array(
                $rolSolicitado,
                self::ROLES_REGISTRO,
                true
            )
                ? $rolSolicitado
                : 'usuario';

            if (
                $rol === 'periodista'
                && !$this->configuracionActiva(
                    'permitir_registro_periodistas'
                )
            ) {
                $this->errores[] =
                    'El registro de periodistas está deshabilitado.';

                return false;
            }

            $requeridos = [
                'email',
                'password',
                'nombre',
                'telefono',
                'ciudad',
            ];

            foreach ($requeridos as $campo) {
                if (
                    !isset($datos[$campo])
                    || trim((string) $datos[$campo]) === ''
                ) {
                    $this->errores[] =
                        "El campo {$campo} es obligatorio.";

                    return false;
                }
            }

            $email = strtolower(trim((string) $datos['email']));
            $nombre = trim((string) $datos['nombre']);
            $telefono = trim((string) $datos['telefono']);
            $ciudad = trim((string) $datos['ciudad']);
            $password = (string) $datos['password'];

            if (!validarEmail($email)) {
                $this->errores[] = 'Email no válido.';
                return false;
            }

            if (!validarTelefono($telefono)) {
                $this->errores[] = 'Teléfono no válido.';
                return false;
            }

            if (!$this->validarPassword($password)) {
                return false;
            }

            if (mb_strlen($nombre) > 150) {
                $this->errores[] = 'El nombre es demasiado largo.';
                return false;
            }

            if (mb_strlen($ciudad) > 120) {
                $this->errores[] = 'La ciudad es demasiado larga.';
                return false;
            }

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $sql = "
                SELECT id_usuario
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':email' => $email,
            ]);

            $requiereAprobacion = $rol === 'periodista'
                || (
                    $rol === 'usuario'
                    && $this->configuracionActiva('registro_comentaristas_aprobacion')
                );
            $estado = $requiereAprobacion ? 'pendiente' : 'activo';

            $sql = "
                INSERT INTO usuarios (
                    email,
                    password,
                    nombre,
                    telefono,
                    ciudad,
                    avatar,
                    rol,
                    estado,
                    fecha_registro
                ) VALUES (
                    :email,
                    :password,
                    :nombre,
                    :telefono,
                    :ciudad,
                    'default-avatar.png',
                    :rol,
                    :estado,
                    NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);

            $resultado = $stmt->execute([
                ':email'            => $email,
                ':password'         => $passwordHash,
                ':nombre'           => $nombre,
                ':telefono'         => $telefono,
                ':ciudad'           => $ciudad,
                ':rol'              => $rol,
                ':estado'           => $estado,
            ]);

            if ($resultado) {
                error_log(
                    sprintf(
                        '[AUTH][REGISTER] Cuenta creada. Rol: %s; estado: %s',
                        $rol,
                        $estado
                    )
                );
            }

            return $resultado;
        } catch (PDOException $e) {
            registrarErrorInterno('AUTH.REGISTER', $e);

            if ((string) $e->getCode() === '23000') {
                $this->errores[] =
                    'No se pudo completar el registro.';
                return false;
            } else {
                $this->errores[] =
                    'No se pudo completar el registro.';
            }

            return false;
        }
    }

    /**
     * Obtener los errores acumulados.
     *
     * @return string[]
     */
    public function getErrores(): array
    {
        return $this->errores;
    }

    /**
     * Crear la sesión autenticada.
     */
    private function iniciarSesion(array $usuario): void
    {
        /*
         * Protección contra fijación de sesión.
         */
        session_regenerate_id(true);

        $ahora = time();

        $_SESSION['usuario_id'] =
            (int) $usuario['id_usuario'];

        $_SESSION['usuario_nombre'] =
            (string) $usuario['nombre'];

        $_SESSION['usuario_email'] =
            (string) $usuario['email'];

        $_SESSION['usuario_rol'] =
            (string) $usuario['rol'];

        $_SESSION['usuario_avatar'] =
            (string) ($usuario['avatar'] ?? 'default-avatar.png');

        $_SESSION['login_time'] = $ahora;
        $_SESSION['last_activity'] = $ahora;
        $_SESSION['last_regeneration'] = $ahora;
        $_SESSION['activity_stats_updated'] = $ahora;

        $_SESSION['auth_password_fingerprint'] = hash(
            'sha256',
            (string) $usuario['password']
        );

        /*
         * Datos informativos para auditoría.
         * No deben utilizarse como único mecanismo de bloqueo.
         */
        $_SESSION['login_ip'] =
            $this->obtenerIpCliente();

        $_SESSION['login_user_agent_hash'] =
            hash(
                'sha256',
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );

        try {
            $sql = "
                UPDATE usuarios
                SET ultimo_acceso = NOW(),
                    ultima_actividad = NOW(),
                    total_conexiones = total_conexiones + 1
                WHERE id_usuario = :id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => (int) $usuario['id_usuario'],
            ]);
        } catch (PDOException $e) {
            /*
             * Un fallo al actualizar ultimo_acceso no debe anular
             * un login que ya ha sido autenticado correctamente.
             */
            registrarErrorInterno('AUTH.LAST_ACCESS', $e);
        }
    }

    /**
     * Registra actividad como máximo una vez por minuto.
     *
     * El tiempo añadido se limita a cinco minutos para no contabilizar como
     * actividad una pestaña abandonada. Un fallo nunca invalida la sesión.
     */
    private function registrarActividadAutenticada(int $ahora): void
    {
        if (!isset($_SESSION['activity_stats_updated'])) {
            $_SESSION['activity_stats_updated'] = $ahora;
            return;
        }

        $ultimaMedicion = (int) $_SESSION['activity_stats_updated'];
        $intervalo = $ahora - $ultimaMedicion;

        if ($intervalo < 60) {
            return;
        }

        $segundosActividad = min($intervalo, 300);
        $_SESSION['activity_stats_updated'] = $ahora;

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET ultima_actividad = NOW(),
                     tiempo_conectado_segundos = tiempo_conectado_segundos + :segundos
                 WHERE id_usuario = :id AND estado = \'activo\''
            );
            $stmt->execute([
                ':segundos' => $segundosActividad,
                ':id' => (int) $_SESSION['usuario_id'],
            ]);
        } catch (Throwable $e) {
            registrarErrorInterno('AUTH.ACTIVITY_STATS', $e);
        }
    }

    /**
     * Actualizar el hash de una contraseña antigua.
     */
    private function actualizarHashPassword(
        int $idUsuario,
        string $password
    ): string|false {
        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "
            UPDATE usuarios
            SET password = :hash
            WHERE id_usuario = :id
        ";

        $stmt = $this->pdo->prepare($sql);

        $actualizado = $stmt->execute([
            ':hash' => $hash,
            ':id'   => $idUsuario,
        ]);

        return $actualizado ? $hash : false;
    }

    /**
     * Comprobar una configuración booleana almacenada en la BD.
     */
    private function configuracionActiva(string $clave): bool
    {
        $sql = "
            SELECT valor
            FROM configuracion
            WHERE clave = :clave
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clave' => $clave,
        ]);

        return $stmt->fetchColumn() === '1';
    }

    /**
     * Validar la contraseña conforme a la política de registro.
     */
    private function validarPassword(string $password): bool
    {
        if (strlen($password) < 10) {
            $this->errores[] =
                'La contraseña debe tener al menos 10 caracteres.';

            return false;
        }

        /*
         * Se permite cualquier contraseña larga, incluidas frases.
         * No se obliga a utilizar símbolos arbitrarios.
         */
        if (strlen($password) > 4096) {
            $this->errores[] =
                'La contraseña supera la longitud permitida.';

            return false;
        }

        return true;
    }

    /**
     * Obtener la IP de la conexión directa.
     *
     * No se confía automáticamente en X-Forwarded-For porque puede
     * ser falsificado si no existe un proxy de confianza configurado.
     */
    private function obtenerIpCliente(): string
    {
        return substr(
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'),
            0,
            45
        );
    }
}

/**
 * Helper para obtener una instancia del sistema de autenticación.
 */
function auth(): Auth
{
    return new Auth();
}
