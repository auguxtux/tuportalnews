<?php
declare(strict_types=1);


/**
 * ==========================================================
 * SEGURIDAD DE LOGIN
 * ==========================================================
 * Protección frente a ataques de fuerza bruta.
 *
 * Funciones públicas:
 *   - registrarIntentoFallido()
 *   - estaBloqueado()
 *   - limpiarIntentosFallidos()
 *
 * Mejoras:
 *  - Constantes configurables.
 *  - Limpieza automática de bloqueos caducados.
 *  - Validación del email.
 *  - Manejo de excepciones.
 *  - Código tipado.
 *  - Compatible con la implementación actual.
 * ==========================================================
 */

if (!defined('LOGIN_MAX_INTENTOS')) {
    define('LOGIN_MAX_INTENTOS', 5);
}

if (!defined('LOGIN_BLOQUEO_MINUTOS')) {
    define('LOGIN_BLOQUEO_MINUTOS', 15);
}

if (!defined('LOGIN_MAX_EMAILS_POR_IP')) {
    define('LOGIN_MAX_EMAILS_POR_IP', 5);
}

if (!defined('LOGIN_VENTANA_IP_MINUTOS')) {
    define('LOGIN_VENTANA_IP_MINUTOS', 5);
}

/**
 * Registrar un intento fallido.
 */
function registrarIntentoFallido(string $email): void
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    try {

        $pdo = db();
        $ip = obtenerIP();

        limpiarBloqueosCaducados();

        $stmt = $pdo->prepare("
            SELECT
                id_attempt,
                intentos
            FROM login_attempts
            WHERE email = ?
              AND ip = ?
            LIMIT 1
        ");

        $stmt->execute([
            $email,
            $ip
        ]);

        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($registro) {

            $intentos = (int)$registro['intentos'] + 1;

            if ($intentos >= LOGIN_MAX_INTENTOS) {

                $bloqueadoHasta = date(
                    'Y-m-d H:i:s',
                    strtotime('+' . LOGIN_BLOQUEO_MINUTOS . ' minutes')
                );

                $stmt = $pdo->prepare("
                    UPDATE login_attempts
                    SET
                        intentos = ?,
                        ultimo_intento = NOW(),
                        bloqueado_hasta = ?
                    WHERE id_attempt = ?
                ");

                $stmt->execute([
                    $intentos,
                    $bloqueadoHasta,
                    $registro['id_attempt']
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE login_attempts
                    SET
                        intentos = ?,
                        ultimo_intento = NOW()
                    WHERE id_attempt = ?
                ");

                $stmt->execute([
                    $intentos,
                    $registro['id_attempt']
                ]);
            }

            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO login_attempts
            (
                email,
                ip,
                intentos,
                ultimo_intento
            )
            VALUES
            (
                ?,
                ?,
                1,
                NOW()
            )
        ");

        $stmt->execute([
            $email,
            $ip
        ]);

    } catch (PDOException $e) {

        registrarErrorInterno('LOGIN_ATTEMPTS.REGISTER', $e);
    }
}

/**
 * ¿La IP está bloqueada permanentemente por un administrador?
 */
function estaIPBloqueadaPermanentemente(?string $ip = null): bool
{
    $ip = $ip ?? obtenerIP();

    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 1
            FROM ips_bloqueadas
            WHERE ip = ?
            LIMIT 1
        ");
        $stmt->execute([$ip]);

        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        registrarErrorInterno('LOGIN_ATTEMPTS.PERMANENT_BLOCK', $e);

        return false;
    }
}

/**
 * ¿La IP está intentando acceder a demasiadas cuentas diferentes?
 */
function estaIPConAtaqueDistribuido(?string $ip = null): bool
{
    $ip = $ip ?? obtenerIP();

    $ipPublica = filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
    if ($ipPublica === false || $ip === '0.0.0.0') {
        return false;
    }

    try {
        $pdo = db();
        $ventana = (int)LOGIN_VENTANA_IP_MINUTOS;
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT email)
            FROM login_attempts
            WHERE ip = ?
              AND ultimo_intento >= DATE_SUB(NOW(), INTERVAL {$ventana} MINUTE)
        ");
        $stmt->execute([$ip]);

        return (int)$stmt->fetchColumn() >= (int)LOGIN_MAX_EMAILS_POR_IP;
    } catch (PDOException $e) {
        registrarErrorInterno('LOGIN_ATTEMPTS.DISTRIBUTED_ATTACK', $e);

        return false;
    }
}

/**
 * ¿Está bloqueado?
 */
function estaBloqueado(string $email): bool
{
    $email = strtolower(trim($email));

    if (estaIPBloqueadaPermanentemente()) {
        return true;
    }

    if (estaIPConAtaqueDistribuido()) {
        return true;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {

        $pdo = db();

        limpiarBloqueosCaducados();

        $stmt = $pdo->prepare("
            SELECT 1
            FROM login_attempts
            WHERE email = ?
              AND ip = ?
              AND bloqueado_hasta IS NOT NULL
              AND bloqueado_hasta > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            $email,
            obtenerIP()
        ]);

        return (bool)$stmt->fetchColumn();

    } catch (PDOException $e) {

        registrarErrorInterno('LOGIN_ATTEMPTS.CHECK', $e);

        return false;
    }
}

/**
 * Limpiar intentos tras un login correcto.
 */
function limpiarIntentosFallidos(string $email): void
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    try {

        $pdo = db();

        $stmt = $pdo->prepare("
            DELETE
            FROM login_attempts
            WHERE email = ?
              AND ip = ?
        ");

        $stmt->execute([
            $email,
            obtenerIP()
        ]);

    } catch (PDOException $e) {

        registrarErrorInterno('LOGIN_ATTEMPTS.CLEAR', $e);
    }
}

/**
 * Elimina automáticamente bloqueos expirados.
 */
function limpiarBloqueosCaducados(): void
{
    static $ejecutado = false;

    if ($ejecutado) {
        return;
    }

    $ejecutado = true;

    try {

        $pdo = db();

        $pdo->exec("
            DELETE
            FROM login_attempts
            WHERE bloqueado_hasta IS NOT NULL
              AND bloqueado_hasta <= NOW()
        ");

    } catch (PDOException $e) {

        registrarErrorInterno('LOGIN_ATTEMPTS.CLEAN', $e);
    }
}
