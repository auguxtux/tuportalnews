<?php
declare(strict_types=1);
/**
 * ==========================================================
 * CONFIGURACIÓN PRINCIPAL
 * Entorno: develop.erun.es
 * ==========================================================
 */

define('SITE_NAME', 'TuPortalNews');
define('SITE_VERSION', '1.1.4');
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR);
define('UPLOADS_PATH', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('ASSETS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$envFile = ROOT_PATH . '.env';
$env = is_file($envFile)
    ? parse_ini_file($envFile, false, INI_SCANNER_RAW)
    : false;

$requiredEnv = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'DB_CHARSET',
    'SMTP_USER',
    'SMTP_PASSWORD',
];

if (!is_array($env)) {
    throw new RuntimeException('No se pudo cargar la configuración local.');
}

foreach (['ENV_PRODUCTION', 'SITE_URL'] as $envName) {
    if (!array_key_exists($envName, $env) || trim((string) $env[$envName]) === '') {
        throw new RuntimeException('La configuración local está incompleta.');
    }
}

$envProduction = filter_var(
    $env['ENV_PRODUCTION'] ?? false,
    FILTER_VALIDATE_BOOL
);
$siteUrl = rtrim((string) ($env['SITE_URL'] ?? 'https://develop.erun.es'), '/');

define('ENV_PRODUCTION', $envProduction);
define('SITE_URL', $siteUrl);

foreach ($requiredEnv as $envName) {
    if (!array_key_exists($envName, $env)) {
        throw new RuntimeException('La configuración local está incompleta.');
    }

    define($envName, (string) $env[$envName]);
}

if (array_key_exists('AEMET_API_KEY', $env)) {
    define('AEMET_API_KEY', (string) $env['AEMET_API_KEY']);
}

define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'webm', 'ogg', 'mov']);
define('ALLOWED_VIDEO_MIME', ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']);
define('UPLOAD_NOTICIAS', UPLOADS_PATH . 'noticias' . DIRECTORY_SEPARATOR);
define('UPLOAD_PERFILES', UPLOADS_PATH . 'perfiles' . DIRECTORY_SEPARATOR);
define('UPLOAD_COMENTARIOS', UPLOADS_PATH . 'comentarios' . DIRECTORY_SEPARATOR);
define('SESSION_NAME', 'news_session');
define('SESSION_LIFETIME', 7200);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

define('ITEMS_PER_PAGE', 10);

date_default_timezone_set('Atlantic/Canary');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES.utf8', 'Spanish_Spain.1252');

ini_set('error_log', ROOT_PATH . 'logs' . DIRECTORY_SEPARATOR . 'error.log');

$requestPathForSession = (string) parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? ''),
    PHP_URL_PATH
);
$skipSessionStart =
    (defined('SKIP_SESSION_START') && SKIP_SESSION_START === true)
    || $requestPathForSession === '/sitemap.xml'
    || $requestPathForSession === '/public/rss-image';

if (session_status() === PHP_SESSION_NONE && !$skipSessionStart) {
    session_name(SESSION_NAME);
    session_start();
}

/*
 * Aplicar el modo mantenimiento también a los archivos PHP solicitados
 * directamente, que no pasan por el front controller.
 */
if (PHP_SAPI !== 'cli' && is_file(ROOT_PATH . '.maintenance')) {
    $requestPath = (string) parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );
    $request = trim($requestPath, '/');
    $rutasPermitidasMantenimiento = [
        'login',
        'logout',
        'logout.php',
        'public/login',
        'public/login.php',
        'admin',
        'admin/dashboard',
        'admin/dashboard.php',
    ];
    $esAdministradorMantenimiento =
        isset($_SESSION['usuario_rol'])
        && $_SESSION['usuario_rol'] === 'admin';

    if (
        !$esAdministradorMantenimiento
        && !in_array($request, $rutasPermitidasMantenimiento, true)
    ) {
        http_response_code(503);
        require ROOT_PATH . 'maintenance.php';
        exit;
    }
}

require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/mail-config.php';
require_once __DIR__ . '/noticia-utils.php';
require_once __DIR__ . '/notificaciones.php';
