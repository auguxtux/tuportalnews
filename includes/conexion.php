<?php
declare(strict_types=1);



/**
 * ==========================================================
 * CONEXIÓN A LA BASE DE DATOS
 * ==========================================================
 *
 * Características:
 *
 * - PDO.
 * - Patrón Singleton sencillo.
 * - Codificación UTF-8 MB4.
 * - Excepciones PDO activadas.
 * - Consultas preparadas reales.
 * - Resultados asociativos por defecto.
 * - Sin conexiones persistentes.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/seguridad.php';

final class Conexion
{
    /**
     * Instancia única de la clase.
     */
    private static ?Conexion $instancia = null;

    /**
     * Conexión PDO activa.
     */
    private \PDO $pdo;

    /**
     * Último error registrado.
     *
     * Se mantiene por compatibilidad con posibles usos
     * existentes del método getError().
     */
    private ?string $error = null;

    /**
     * El constructor es privado para impedir la creación
     * directa de múltiples conexiones.
     */
    private function __construct()
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $this->pdo = new \PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_PERSISTENT => false,
                ]
            );
        } catch (\PDOException $e) {
            $this->error = 'No se pudo establecer la conexión con la base de datos.';

            registrarErrorInterno('PDO.CONEXION', $e);

            http_response_code(500);

            $paginaError = ROOT_PATH . 'public/500.php';
            if (is_file($paginaError)) {
                require $paginaError;
                exit;
            }

            exit('Error interno de conexión. Inténtelo de nuevo más tarde.');
        }
    }

    /**
     * Devuelve la instancia única de la conexión.
     */
    public static function getInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }

        return self::$instancia;
    }

    /**
     * Devuelve la conexión PDO.
     */
    public function getConexion(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Devuelve el último error registrado.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Impide la clonación de la instancia.
     */
    private function __clone(): void
    {
    }

    /**
     * Impide deserializar la instancia.
     */
    public function __wakeup(): void
    {
        throw new \LogicException(
            'No está permitido deserializar la conexión.'
        );
    }
}

/**
 * Devuelve la conexión PDO activa.
 *
 * Este helper mantiene una forma breve y uniforme de acceder
 * a la base de datos desde las páginas y helpers del proyecto.
 */
function db(): \PDO
{
    return Conexion::getInstancia()->getConexion();
}
