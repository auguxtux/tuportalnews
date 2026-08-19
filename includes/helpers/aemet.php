<?php
declare(strict_types=1);

/**
 * Funciones auxiliares para consultar AEMET OpenData.
 *
 * Medidas incluidas:
 * - validación estricta de URLs y hosts;
 * - bloqueo de IP privadas y reservadas;
 * - límite de descarga durante la recepción;
 * - caché con escritura atómica;
 * - bloqueo contra estampidas de caché;
 * - uso controlado de caché anterior si AEMET falla;
 * - validación estructural antes de guardar respuestas.
 */

function cargarConfiguracionAemet(): array
{
    $apiKey = trim((string) (defined('AEMET_API_KEY') ? AEMET_API_KEY : ''));

    $ruta = dirname(__DIR__) . '/aemet-config.php';
    $config = is_file($ruta) ? (require $ruta) : [];
    if (!is_array($config)) {
        $config = [];
    }

    if ($apiKey !== '' && $apiKey !== 'PEGA_AQUI_TU_API_KEY') {
        $config['api_key'] = $apiKey;
    }

    $finalKey = trim((string) ($config['api_key'] ?? ''));

    if ($finalKey === '' || $finalKey === 'PEGA_AQUI_TU_API_KEY') {
        throw new RuntimeException('La API Key de AEMET no está configurada.');
    }

    return $config;
}

/**
 * @return array<string,string>
 */
function obtenerProvinciasEspanaAemet(): array
{
    return [
        '01' => 'Álava',
        '02' => 'Albacete',
        '03' => 'Alicante',
        '04' => 'Almería',
        '05' => 'Ávila',
        '06' => 'Badajoz',
        '07' => 'Illes Balears',
        '08' => 'Barcelona',
        '09' => 'Burgos',
        '10' => 'Cáceres',
        '11' => 'Cádiz',
        '12' => 'Castellón',
        '13' => 'Ciudad Real',
        '14' => 'Córdoba',
        '15' => 'A Coruña',
        '16' => 'Cuenca',
        '17' => 'Girona',
        '18' => 'Granada',
        '19' => 'Guadalajara',
        '20' => 'Gipuzkoa',
        '21' => 'Huelva',
        '22' => 'Huesca',
        '23' => 'Jaén',
        '24' => 'León',
        '25' => 'Lleida',
        '26' => 'La Rioja',
        '27' => 'Lugo',
        '28' => 'Madrid',
        '29' => 'Málaga',
        '30' => 'Murcia',
        '31' => 'Navarra',
        '32' => 'Ourense',
        '33' => 'Asturias',
        '34' => 'Palencia',
        '35' => 'Las Palmas',
        '36' => 'Pontevedra',
        '37' => 'Salamanca',
        '38' => 'Santa Cruz de Tenerife',
        '39' => 'Cantabria',
        '40' => 'Segovia',
        '41' => 'Sevilla',
        '42' => 'Soria',
        '43' => 'Tarragona',
        '44' => 'Teruel',
        '45' => 'Toledo',
        '46' => 'Valencia',
        '47' => 'Valladolid',
        '48' => 'Bizkaia',
        '49' => 'Zamora',
        '50' => 'Zaragoza',
        '51' => 'Ceuta',
        '52' => 'Melilla',
    ];
}

function obtenerProvinciaPorCodigoAemet(string $codigoMunicipio): string
{
    if (!preg_match('/^\d{5}$/', $codigoMunicipio)) {
        return '';
    }

    $provincias = obtenerProvinciasEspanaAemet();

    return $provincias[substr($codigoMunicipio, 0, 2)] ?? '';
}

function esIpPublicaAemet(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

/**
 * Resuelve una dirección IPv4 pública y la fija posteriormente en cURL.
 */
function resolverIpPublicaAemet(string $host): string
{
    $registros = dns_get_record($host, DNS_A);

    if (!is_array($registros)) {
        throw new RuntimeException('No se pudo resolver el host de AEMET.');
    }

    foreach ($registros as $registro) {
        $ip = isset($registro['ip']) ? (string) $registro['ip'] : '';

        if ($ip !== '' && esIpPublicaAemet($ip)) {
            return $ip;
        }
    }

    throw new RuntimeException(
        'El host de AEMET no resolvió a una dirección pública válida.'
    );
}

/**
 * @return array{host:string,ip:string}
 */
function validarUrlAemet(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('La URL de AEMET no es válida.');
    }

    $partes = parse_url($url);

    if (!is_array($partes)) {
        throw new RuntimeException('No se pudo analizar la URL de AEMET.');
    }

    if (($partes['scheme'] ?? '') !== 'https') {
        throw new RuntimeException('Solo se permiten conexiones HTTPS con AEMET.');
    }

    if (isset($partes['user']) || isset($partes['pass'])) {
        throw new RuntimeException(
            'No se permiten credenciales incorporadas en la URL.'
        );
    }

    $host = strtolower((string) ($partes['host'] ?? ''));
    $hostsPermitidos = [
        'opendata.aemet.es',
    ];

    if (!in_array($host, $hostsPermitidos, true)) {
        throw new RuntimeException('El host remoto no está autorizado.');
    }

    $puerto = isset($partes['port']) ? (int) $partes['port'] : 443;

    if ($puerto !== 443) {
        throw new RuntimeException('El puerto remoto no está autorizado.');
    }

    return [
        'host' => $host,
        'ip' => resolverIpPublicaAemet($host),
    ];
}

function obtenerBloqueoTemporalAemet(): int
{
    $archivo = obtenerDirectorioCacheAemet() . '/servicio_bloqueado.json';
    $datos = leerCacheJsonAemet($archivo);
    $bloqueadoHasta = max(0, (int) ($datos['hasta'] ?? 0));

    if ($bloqueadoHasta === 0 || !is_file($archivo)) {
        return $bloqueadoHasta;
    }

    /*
     * Compatibilidad con bloqueos antiguos excesivamente largos.
     * Ningún 429 debe inutilizar el módulo durante más de cinco minutos.
     */
    return min($bloqueadoHasta, (int) filemtime($archivo) + 300);
}

function registrarBloqueoTemporalAemet(int $segundos = 60): void
{
    $archivo = obtenerDirectorioCacheAemet() . '/servicio_bloqueado.json';
    $estadoAnterior = leerCacheJsonAemet($archivo) ?? [];
    $bloqueoAnterior = max(0, (int) ($estadoAnterior['hasta'] ?? 0));
    $intentos = min(5, max(0, (int) ($estadoAnterior['intentos'] ?? 0)) + 1);
    $espera = min(300, max(60, $segundos) * (2 ** ($intentos - 1)));

    $guardado = escribirCacheJsonAemet(
        $archivo,
        [
            'hasta' => time() + $espera,
            'intentos' => $intentos,
        ]
    );

    if ($guardado && $bloqueoAnterior <= time()) {
        error_log(
            '[AEMET] Consultas pausadas temporalmente por límite del proveedor.'
        );
    }
}

function registrarRecuperacionAemetSiProcede(): void
{
    $archivo = obtenerDirectorioCacheAemet() . '/servicio_bloqueado.json';
    $bloqueadoHasta = obtenerBloqueoTemporalAemet();

    if ($bloqueadoHasta <= 0 || $bloqueadoHasta > time()) {
        return;
    }

    if (escribirCacheJsonAemet($archivo, ['hasta' => 0, 'intentos' => 0])) {
        error_log('[AEMET] Consultas externas reanudadas.');
    }
}

function esLimitacionTemporalAemet(Throwable $error): bool
{
    $mensaje = $error->getMessage();

    return str_contains($mensaje, 'limitado temporalmente')
        || str_contains($mensaje, 'pausadas temporalmente')
        || str_contains($mensaje, 'actualización meteorológica en curso');
}

/**
 * Impide que varios procesos PHP consulten simultáneamente AEMET para
 * municipios diferentes. El bloqueo nunca hace esperar al proceso llamante.
 *
 * @return resource|null
 */
function adquirirBloqueoGlobalAemet(
    string $nombreArchivo = 'consulta_global.lock'
)
{
    if (!in_array(
        $nombreArchivo,
        ['consulta_global.lock', 'consulta_openmeteo_global.lock'],
        true
    )) {
        return null;
    }

    $archivo = obtenerDirectorioCacheAemet() . '/' . $nombreArchivo;
    $bloqueo = fopen($archivo, 'c');

    if ($bloqueo === false) {
        return null;
    }

    if (!flock($bloqueo, LOCK_EX | LOCK_NB)) {
        fclose($bloqueo);

        return null;
    }

    return $bloqueo;
}

/**
 * @param resource|null $bloqueo
 */
function liberarBloqueoGlobalAemet($bloqueo): void
{
    if (!is_resource($bloqueo)) {
        return;
    }

    flock($bloqueo, LOCK_UN);
    fclose($bloqueo);
}

function permitirAccionAemetPorSesion(
    string $clave,
    int $limite = 12,
    int $ventana = 600
): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }

    $ahora = time();
    $desde = $ahora - max(60, $ventana);
    $claveSesion = 'aemet_' . $clave;
    $consultas = $_SESSION[$claveSesion] ?? [];

    if (!is_array($consultas)) {
        $consultas = [];
    }

    $consultas = array_values(
        array_filter(
            $consultas,
            static fn(mixed $momento): bool =>
                is_int($momento) && $momento >= $desde
        )
    );

    if (count($consultas) >= max(1, $limite)) {
        $_SESSION[$claveSesion] = $consultas;

        return false;
    }

    $consultas[] = $ahora;
    $_SESSION[$claveSesion] = $consultas;

    return true;
}

function permitirConsultaRemotaAemetPorSesion(
    int $limite = 12,
    int $ventana = 600
): bool {
    return permitirAccionAemetPorSesion(
        'consultas_remotas',
        $limite,
        $ventana
    );
}

function permitirCalculoUbicacionAemetPorSesion(
    int $limite = 30,
    int $ventana = 600
): bool {
    return permitirAccionAemetPorSesion(
        'calculos_ubicacion',
        $limite,
        $ventana
    );
}

function permitirAccionAemetPorIp(
    string $archivoNombre,
    string $contexto,
    int $limite,
    int $ventana
): bool {
    if (!preg_match('/^[a-z0-9_-]+\.json$/', $archivoNombre)) {
        return false;
    }

    $ip = filter_var(
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        FILTER_VALIDATE_IP
    );

    if (!is_string($ip)) {
        return false;
    }

    $config = cargarConfiguracionAemet();
    $identificador = hash_hmac(
        'sha256',
        $contexto === '' ? $ip : $contexto . '|' . $ip,
        (string) $config['api_key']
    );
    $directorio = obtenerDirectorioCacheAemet();
    $archivo = $directorio . '/' . $archivoNombre;
    $archivoBloqueo = $archivo . '.lock';
    $bloqueo = fopen($archivoBloqueo, 'c');

    if ($bloqueo === false) {
        return false;
    }

    if (!flock($bloqueo, LOCK_EX)) {
        fclose($bloqueo);

        return false;
    }

    try {
        $ahora = time();
        $desde = $ahora - max(60, $ventana);
        $limites = leerCacheJsonAemet($archivo) ?? [];

        foreach ($limites as $clave => $consultas) {
            if (!is_array($consultas)) {
                unset($limites[$clave]);
                continue;
            }

            $consultas = array_values(
                array_filter(
                    $consultas,
                    static fn(mixed $momento): bool =>
                        is_int($momento) && $momento >= $desde
                )
            );

            if ($consultas === []) {
                unset($limites[$clave]);
            } else {
                $limites[$clave] = $consultas;
            }
        }

        $consultasIp = $limites[$identificador] ?? [];

        if (count($consultasIp) >= max(1, $limite)) {
            escribirCacheJsonAemet($archivo, $limites);

            return false;
        }

        $consultasIp[] = $ahora;
        $limites[$identificador] = $consultasIp;

        return escribirCacheJsonAemet($archivo, $limites);
    } finally {
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
    }
}

function permitirConsultaRemotaAemetPorIp(
    int $limite = 30,
    int $ventana = 3600
): bool {
    return permitirAccionAemetPorIp(
        'limites_consultas.json',
        '',
        $limite,
        $ventana
    );
}

function permitirCalculoUbicacionAemetPorIp(
    int $limite = 120,
    int $ventana = 3600
): bool {
    return permitirAccionAemetPorIp(
        'limites_ubicacion.json',
        'ubicacion',
        $limite,
        $ventana
    );
}

/**
 * Descarga una respuesta de AEMET limitando el tamaño durante la recepción.
 *
 * @return array{body:string,content_type:string,http_code:int}
 */
function descargarAemet(
    string $url,
    int $timeout = 12,
    int $maxBytes = 5_000_000
): array {
    $bloqueadoHasta = obtenerBloqueoTemporalAemet();

    if ($bloqueadoHasta > time()) {
        throw new RuntimeException(
            'Las consultas a AEMET están pausadas temporalmente.'
        );
    }

    $destino = validarUrlAemet($url);
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar cURL.');
    }

    $respuesta = '';
    $excesoTamano = false;

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => max(5, $timeout),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain;q=0.9',
            'User-Agent: TuPortalNews-AEMET/2.0',
        ],
        CURLOPT_RESOLVE => [
            $destino['host'] . ':443:' . $destino['ip'],
        ],
        CURLOPT_WRITEFUNCTION => static function (
            $curlHandle,
            string $fragmento
        ) use (&$respuesta, &$excesoTamano, $maxBytes): int {
            if (strlen($respuesta) + strlen($fragmento) > $maxBytes) {
                $excesoTamano = true;

                return 0;
            }

            $respuesta .= $fragmento;

            return strlen($fragmento);
        },
    ]);

    $resultado = curl_exec($curl);
    $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $tipoContenido = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);

    curl_close($curl);

    if ($excesoTamano) {
        throw new RuntimeException(
            'La respuesta de AEMET supera el tamaño permitido.'
        );
    }

    if ($resultado === false) {
        throw new RuntimeException(
            'No se pudo conectar con AEMET'
            . ($error !== '' ? ': ' . $error : '.')
        );
    }

    if ($codigoHttp === 429) {
        registrarBloqueoTemporalAemet();

        throw new RuntimeException(
            'AEMET ha limitado temporalmente las consultas.'
        );
    }

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        throw new RuntimeException(
            'AEMET respondió con HTTP ' . $codigoHttp . '.'
        );
    }

    return [
        'body' => $respuesta,
        'content_type' => $tipoContenido,
        'http_code' => $codigoHttp,
    ];
}

function convertirRespuestaAemetAUtf8(
    string $contenido,
    string $tipoContenido = ''
): string {
    $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
    $charset = '';

    if (
        $tipoContenido !== ''
        && preg_match(
            '/charset\s*=\s*["\']?([^;"\s]+)/i',
            $tipoContenido,
            $coincidencias
        )
    ) {
        $charset = strtoupper(trim((string) ($coincidencias[1] ?? '')));
    }

    $charset = match ($charset) {
        'ISO-8859-15', 'ISO8859-15', 'LATIN-9', 'LATIN9' => 'ISO-8859-15',
        'ISO-8859-1', 'ISO8859-1', 'LATIN-1', 'LATIN1' => 'ISO-8859-1',
        'UTF8', 'UTF-8' => 'UTF-8',
        default => '',
    };

    if ($charset === '') {
        if (
            function_exists('mb_check_encoding')
            && mb_check_encoding($contenido, 'UTF-8')
        ) {
            return $contenido;
        }

        $charset = 'ISO-8859-15';
    }

    if ($charset === 'UTF-8') {
        return $contenido;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($contenido, 'UTF-8', $charset);
    }

    if (function_exists('iconv')) {
        $convertido = iconv($charset, 'UTF-8//TRANSLIT', $contenido);

        if (is_string($convertido)) {
            return $convertido;
        }
    }

    throw new RuntimeException(
        'No se pudo convertir la respuesta de AEMET a UTF-8.'
    );
}


function repararTextoMojibakeAemet(string $texto): string
{
    if ($texto === '' || !preg_match('/(?:Ã.|Â.|â.|ðŸ)/u', $texto)) {
        return $texto;
    }

    if (function_exists('mb_convert_encoding')) {
        $reparado = @mb_convert_encoding(
            $texto,
            'ISO-8859-1',
            'UTF-8'
        );

        if (
            is_string($reparado)
            && $reparado !== ''
            && mb_check_encoding($reparado, 'UTF-8')
        ) {
            return $reparado;
        }
    }

    return $texto;
}

function repararDatosMojibakeAemet(mixed $valor): mixed
{
    if (is_string($valor)) {
        return repararTextoMojibakeAemet($valor);
    }

    if (is_array($valor)) {
        foreach ($valor as $clave => $elemento) {
            $valor[$clave] = repararDatosMojibakeAemet($elemento);
        }
    }

    return $valor;
}

function decodificarJsonAemet(
    string $json,
    string $tipoContenido = ''
): array {
    $jsonUtf8 = convertirRespuestaAemetAUtf8($json, $tipoContenido);

    try {
        $datos = json_decode($jsonUtf8, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException(
            'AEMET devolvió un JSON no válido: ' . $e->getMessage(),
            0,
            $e
        );
    }

    if (!is_array($datos)) {
        throw new RuntimeException(
            'La respuesta de AEMET no contiene datos válidos.'
        );
    }

    return repararDatosMojibakeAemet($datos);
}

function obtenerDirectorioCacheAemet(): string
{
    $cacheDir = defined('ROOT_PATH')
        ? ROOT_PATH . 'storage/cache/aemet'
        : dirname(__DIR__, 2) . '/storage/cache/aemet';

    if (
        !is_dir($cacheDir)
        && !mkdir($cacheDir, 0775, true)
        && !is_dir($cacheDir)
    ) {
        throw new RuntimeException('No se pudo crear la caché de AEMET.');
    }

    return rtrim($cacheDir, '/');
}

function leerCacheJsonAemet(string $archivo): ?array
{
    if (!is_file($archivo) || !is_readable($archivo)) {
        return null;
    }

    $contenido = file_get_contents($archivo);

    if (!is_string($contenido) || $contenido === '') {
        return null;
    }

    try {
        return decodificarJsonAemet(
            $contenido,
            'application/json; charset=UTF-8'
        );
    } catch (Throwable $e) {
        registrarErrorInterno('AEMET.CACHE.LEER', $e);

        return null;
    }
}

function escribirCacheJsonAemet(string $archivo, array $datos): bool
{
    try {
        $json = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        error_log('[AEMET] No se pudo codificar la caché.');

        return false;
    }

    $temporal = $archivo . '.tmp.' . getmypid();

    if (file_put_contents($temporal, $json, LOCK_EX) === false) {
        @unlink($temporal);

        return false;
    }

    if (!rename($temporal, $archivo)) {
        @unlink($temporal);

        return false;
    }

    return true;
}

/**
 * Elimina de forma acotada predicciones que ya superan el periodo máximo de
 * respaldo y temporales abandonados. No toca catálogos ni archivos de lock.
 *
 * @return array{predicciones:int,temporales:int}
 */
function limpiarCacheAemetLimitada(int $maximoArchivos = 100): array
{
    $maximoArchivos = min(500, max(1, $maximoArchivos));
    $config = cargarConfiguracionAemet();
    $cacheTtl = max(300, (int) ($config['cache_ttl'] ?? 1800));
    $maxStale = max($cacheTtl, (int) (
        $config['cache_max_stale'] ?? 172800
    ));
    $directorio = obtenerDirectorioCacheAemet();
    $ahora = time();
    $eliminados = [
        'predicciones' => 0,
        'temporales' => 0,
    ];

    $predicciones = array_merge(
        glob($directorio . '/municipio_*.json') ?: [],
        glob($directorio . '/openmeteo_*.json') ?: []
    );
    usort(
        $predicciones,
        static fn(string $a, string $b): int =>
            ((int) filemtime($a)) <=> ((int) filemtime($b))
    );

    foreach ($predicciones as $archivo) {
        if (array_sum($eliminados) >= $maximoArchivos) {
            break;
        }

        if (
            !preg_match(
                '/^(?:municipio|openmeteo)_\d{5}\.json$/',
                basename($archivo)
            )
            || $ahora - (int) filemtime($archivo) <= $maxStale
        ) {
            continue;
        }

        if (@unlink($archivo)) {
            $eliminados['predicciones']++;
        }
    }

    foreach (glob($directorio . '/*.tmp.*') ?: [] as $archivo) {
        if (array_sum($eliminados) >= $maximoArchivos) {
            break;
        }

        if ($ahora - (int) filemtime($archivo) <= 3600) {
            continue;
        }

        if (@unlink($archivo)) {
            $eliminados['temporales']++;
        }
    }

    return $eliminados;
}

function limpiarCacheAemetSiProcede(
    int $intervalo = 3600,
    int $maximoArchivos = 100
): void {
    $directorio = obtenerDirectorioCacheAemet();
    $archivoControl = $directorio . '/limpieza_cache.lock';
    $intervalo = max(900, $intervalo);

    if (
        is_file($archivoControl)
        && time() - (int) filemtime($archivoControl) < $intervalo
    ) {
        return;
    }

    $bloqueo = fopen($archivoControl, 'c');

    if ($bloqueo === false || !flock($bloqueo, LOCK_EX | LOCK_NB)) {
        if (is_resource($bloqueo)) {
            fclose($bloqueo);
        }

        return;
    }

    try {
        clearstatcache(true, $archivoControl);

        if (
            filesize($archivoControl) > 0
            && time() - (int) filemtime($archivoControl) < $intervalo
        ) {
            return;
        }

        limpiarCacheAemetLimitada($maximoArchivos);
        ftruncate($bloqueo, 0);
        rewind($bloqueo);
        fwrite($bloqueo, (string) time());
        fflush($bloqueo);
        touch($archivoControl);
    } catch (Throwable $e) {
        error_log('[AEMET] No se pudo limpiar la caché meteorológica.');
    } finally {
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
    }
}

/**
 * Resuelve una respuesta AEMET directa o con URL en el campo "datos".
 */
function resolverRespuestaAemet(
    string $endpoint,
    int $timeout = 12
): array {
    $primera = descargarAemet($endpoint, $timeout);
    $datos = decodificarJsonAemet(
        $primera['body'],
        $primera['content_type']
    );

    $urlDatos = isset($datos['datos']) && is_string($datos['datos'])
        ? trim($datos['datos'])
        : '';

    if ($urlDatos === '') {
        registrarRecuperacionAemetSiProcede();

        return $datos;
    }

    $segunda = descargarAemet($urlDatos, $timeout);
    $resultado = decodificarJsonAemet(
        $segunda['body'],
        $segunda['content_type']
    );

    registrarRecuperacionAemetSiProcede();

    return $resultado;
}

function validarCatalogoMunicipiosAemet(array $catalogo): void
{
    if ($catalogo === [] || !isset($catalogo[0]) || !is_array($catalogo[0])) {
        throw new RuntimeException(
            'El catálogo de municipios de AEMET no tiene la estructura esperada.'
        );
    }

    $primero = $catalogo[0];

    if (
        !array_key_exists('nombre', $primero)
        || (
            !array_key_exists('id', $primero)
            && !array_key_exists('id_old', $primero)
        )
    ) {
        throw new RuntimeException(
            'El catálogo de municipios de AEMET está incompleto.'
        );
    }
}

function validarPrediccionMunicipioAemet(array $prediccion): void
{
    if (
        !isset($prediccion[0])
        || !is_array($prediccion[0])
        || !isset($prediccion[0]['prediccion']['dia'])
        || !is_array($prediccion[0]['prediccion']['dia'])
        || $prediccion[0]['prediccion']['dia'] === []
    ) {
        throw new RuntimeException(
            'La predicción de AEMET no tiene la estructura esperada.'
        );
    }
}

/**
 * @return array<string,array<int,array{codigo:string,nombre:string,latitud:?float,longitud:?float}>>
 */
function obtenerMunicipiosEspanaAemet(
    bool $forzarActualizacion = false
): array {
    $config = cargarConfiguracionAemet();
    $timeout = max(5, (int) ($config['timeout'] ?? 12));
    $cacheTtl = max(3600, (int) (
        $config['municipios_cache_ttl'] ?? 86400
    ));
    $maxStale = max($cacheTtl, (int) (
        $config['municipios_cache_max_stale'] ?? 2592000
    ));

    $cacheDir = obtenerDirectorioCacheAemet();
    $cacheFile = $cacheDir . '/municipios_espana.json';
    $lockFile = $cacheDir . '/municipios_espana.lock';

    $cacheAnterior = leerCacheJsonAemet($cacheFile);
    $edad = is_file($cacheFile)
        ? max(0, time() - (int) filemtime($cacheFile))
        : null;

    if (
        $cacheAnterior !== null
        && !$forzarActualizacion
        && $edad !== null
        && $edad < $cacheTtl
    ) {
        return $cacheAnterior;
    }

    $bloqueo = fopen($lockFile, 'c');

    if ($bloqueo === false) {
        if (
            $cacheAnterior !== null
            && $edad !== null
            && $edad <= $maxStale
        ) {
            return $cacheAnterior;
        }

        throw new RuntimeException(
            'No se pudo bloquear la actualización del catálogo.'
        );
    }

    if (!flock($bloqueo, LOCK_EX | LOCK_NB)) {
        fclose($bloqueo);

        if (
            $cacheAnterior !== null
            && $edad !== null
            && $edad <= $maxStale
        ) {
            return $cacheAnterior;
        }

        throw new RuntimeException(
            'El catálogo de municipios se está actualizando.'
        );
    }

    try {
        clearstatcache(true, $cacheFile);

        $cacheActualizada = leerCacheJsonAemet($cacheFile);
        $edadActualizada = is_file($cacheFile)
            ? max(0, time() - (int) filemtime($cacheFile))
            : null;

        if (
            $cacheActualizada !== null
            && !$forzarActualizacion
            && $edadActualizada !== null
            && $edadActualizada < $cacheTtl
        ) {
            return $cacheActualizada;
        }

        $endpoint = sprintf(
            'https://opendata.aemet.es/opendata/api/'
            . 'maestro/municipios/?api_key=%s',
            rawurlencode((string) $config['api_key'])
        );

        try {
            $bloqueoGlobal = adquirirBloqueoGlobalAemet();

            if ($bloqueoGlobal === null) {
                throw new RuntimeException(
                    'Hay una actualización meteorológica en curso.'
                );
            }

            try {
                $catalogo = resolverRespuestaAemet($endpoint, $timeout);
            } finally {
                liberarBloqueoGlobalAemet($bloqueoGlobal);
            }

            validarCatalogoMunicipiosAemet($catalogo);

            $provincias = obtenerProvinciasEspanaAemet();
            $resultado = [];

            foreach ($provincias as $nombreProvincia) {
                $resultado[$nombreProvincia] = [];
            }

            foreach ($catalogo as $municipio) {
                if (!is_array($municipio)) {
                    continue;
                }

                $nombre = trim((string) ($municipio['nombre'] ?? ''));
                $codigoOriginal = trim((string) (
                    $municipio['id'] ?? $municipio['id_old'] ?? ''
                ));

                if (
                    !preg_match(
                        '/(\d{5})$/',
                        $codigoOriginal,
                        $coincidencias
                    )
                ) {
                    continue;
                }

                $codigo = (string) $coincidencias[1];
                $provincia = obtenerProvinciaPorCodigoAemet($codigo);

                if ($provincia === '' || $nombre === '') {
                    continue;
                }

                $latitud = isset($municipio['latitud_dec'])
                    && is_numeric($municipio['latitud_dec'])
                        ? (float) $municipio['latitud_dec']
                        : null;

                $longitud = isset($municipio['longitud_dec'])
                    && is_numeric($municipio['longitud_dec'])
                        ? (float) $municipio['longitud_dec']
                        : null;

                $resultado[$provincia][] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                ];
            }

            foreach ($resultado as &$municipios) {
                usort(
                    $municipios,
                    static fn(array $a, array $b): int =>
                        strcasecmp($a['nombre'], $b['nombre'])
                );
            }
            unset($municipios);

            $resultado = array_filter(
                $resultado,
                static fn(array $municipios): bool => $municipios !== []
            );

            if ($resultado === []) {
                throw new RuntimeException(
                    'AEMET no devolvió municipios reconocibles.'
                );
            }

            if (!escribirCacheJsonAemet($cacheFile, $resultado)) {
                error_log(
                    '[AEMET] No se pudo escribir la caché nacional.'
                );
            } else {
                limpiarCacheAemetSiProcede();
            }

            return $resultado;
        } catch (Throwable $e) {
            if (
                $cacheAnterior !== null
                && $edad !== null
                && $edad <= $maxStale
            ) {
                if (!esLimitacionTemporalAemet($e)) {
                    registrarErrorInterno('AEMET.CATALOGO.CACHE_ANTERIOR', $e);
                }

                return $cacheAnterior;
            }

            throw $e;
        }
    } finally {
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
    }
}

function buscarMunicipioEspanaAemet(
    array $provincias,
    string $codigo
): ?array {
    if (!preg_match('/^\d{5}$/', $codigo)) {
        return null;
    }

    foreach ($provincias as $provincia => $municipios) {
        foreach ($municipios as $municipio) {
            if (($municipio['codigo'] ?? '') === $codigo) {
                return [
                    'codigo' => $codigo,
                    'nombre' => (string) ($municipio['nombre'] ?? ''),
                    'provincia' => (string) $provincia,
                    'latitud' => isset($municipio['latitud'])
                        && is_numeric($municipio['latitud'])
                            ? (float) $municipio['latitud']
                            : null,
                    'longitud' => isset($municipio['longitud'])
                        && is_numeric($municipio['longitud'])
                            ? (float) $municipio['longitud']
                            : null,
                ];
            }
        }
    }

    return null;
}

function obtenerPrediccionMunicipioAemet(
    string $municipioId,
    bool $forzarActualizacion = false
): array {
    if (!preg_match('/^\d{5}$/', $municipioId)) {
        throw new InvalidArgumentException(
            'El código de municipio debe tener cinco cifras.'
        );
    }

    $config = cargarConfiguracionAemet();
    $cacheTtl = max(300, (int) ($config['cache_ttl'] ?? 1800));
    $maxStale = max($cacheTtl, (int) (
        $config['cache_max_stale'] ?? 172800
    ));
    $timeout = max(5, (int) ($config['timeout'] ?? 12));
    $actualizacionMinima = max(300, (int) (
        $config['actualizacion_minima'] ?? 300
    ));

    $cacheDir = obtenerDirectorioCacheAemet();
    $cacheFile = $cacheDir . '/municipio_' . $municipioId . '.json';
    $lockFile = $cacheDir . '/municipio_' . $municipioId . '.lock';

    $cacheAnterior = leerCacheJsonAemet($cacheFile);
    $edadCache = is_file($cacheFile)
        ? max(0, time() - (int) filemtime($cacheFile))
        : null;

    if (
        $cacheAnterior !== null
        && !$forzarActualizacion
        && $edadCache !== null
        && $edadCache < $cacheTtl
    ) {
        return $cacheAnterior;
    }

    if (
        $cacheAnterior !== null
        && $forzarActualizacion
        && $edadCache !== null
        && $edadCache < $actualizacionMinima
    ) {
        return $cacheAnterior;
    }

    if (obtenerBloqueoTemporalAemet() > time()) {
        if (
            $cacheAnterior !== null
            && $edadCache !== null
            && $edadCache <= $maxStale
        ) {
            return $cacheAnterior;
        }

        throw new RuntimeException(
            'Las consultas a AEMET están pausadas temporalmente.'
        );
    }

    if (
        !permitirConsultaRemotaAemetPorSesion()
        || !permitirConsultaRemotaAemetPorIp()
    ) {
        if (
            $cacheAnterior !== null
            && $edadCache !== null
            && $edadCache <= $maxStale
        ) {
            return $cacheAnterior;
        }

        throw new RuntimeException(
            'Se ha alcanzado el límite temporal de consultas meteorológicas.'
        );
    }

    $bloqueo = fopen($lockFile, 'c');

    if ($bloqueo === false) {
        if (
            $cacheAnterior !== null
            && $edadCache !== null
            && $edadCache <= $maxStale
        ) {
            return $cacheAnterior;
        }

        throw new RuntimeException(
            'No se pudo bloquear la actualización de AEMET.'
        );
    }

    if (!flock($bloqueo, LOCK_EX | LOCK_NB)) {
        fclose($bloqueo);

        if (
            $cacheAnterior !== null
            && $edadCache !== null
            && $edadCache <= $maxStale
        ) {
            return $cacheAnterior;
        }

        throw new RuntimeException(
            'La predicción de este municipio se está actualizando.'
        );
    }

    try {
        clearstatcache(true, $cacheFile);

        $cacheActualizada = leerCacheJsonAemet($cacheFile);
        $edadActualizada = is_file($cacheFile)
            ? max(0, time() - (int) filemtime($cacheFile))
            : null;

        $limiteRecomprobacion = $forzarActualizacion
            ? $actualizacionMinima
            : $cacheTtl;

        if (
            $cacheActualizada !== null
            && $edadActualizada !== null
            && $edadActualizada < $limiteRecomprobacion
        ) {
            return $cacheActualizada;
        }

        $endpoint = sprintf(
            'https://opendata.aemet.es/opendata/api/'
            . 'prediccion/especifica/municipio/diaria/%s/'
            . '?api_key=%s',
            rawurlencode($municipioId),
            rawurlencode((string) $config['api_key'])
        );

        try {
            $bloqueoGlobal = adquirirBloqueoGlobalAemet();

            if ($bloqueoGlobal === null) {
                throw new RuntimeException(
                    'Hay una actualización meteorológica en curso.'
                );
            }

            try {
                $prediccion = resolverRespuestaAemet($endpoint, $timeout);
            } finally {
                liberarBloqueoGlobalAemet($bloqueoGlobal);
            }

            validarPrediccionMunicipioAemet($prediccion);
        } catch (Throwable $e) {
            if (
                $cacheAnterior !== null
                && $edadCache !== null
                && $edadCache <= $maxStale
            ) {
                if (!esLimitacionTemporalAemet($e)) {
                    registrarErrorInterno('AEMET.PREDICCION.CACHE_ANTERIOR', $e);
                }

                return $cacheAnterior;
            }

            throw $e;
        }

        if (!escribirCacheJsonAemet($cacheFile, $prediccion)) {
            error_log('[AEMET] No se pudo escribir la caché de predicción.');
        } else {
            limpiarCacheAemetSiProcede();
        }

        return $prediccion;
    } finally {
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
    }
}

/**
 * @return array{
 *   actualizado_en:?int,
 *   antiguedad:?int,
 *   caducada:bool,
 *   demasiado_antigua:bool
 * }
 */
function obtenerEstadoCachePrediccionAemet(string $municipioId): array
{
    if (!preg_match('/^\d{5}$/', $municipioId)) {
        return [
            'actualizado_en' => null,
            'antiguedad' => null,
            'caducada' => false,
            'demasiado_antigua' => false,
        ];
    }

    $config = cargarConfiguracionAemet();
    $cacheTtl = max(300, (int) ($config['cache_ttl'] ?? 1800));
    $maxStale = max($cacheTtl, (int) (
        $config['cache_max_stale'] ?? 172800
    ));

    $archivo = obtenerDirectorioCacheAemet()
        . '/municipio_' . $municipioId . '.json';

    if (!is_file($archivo)) {
        return [
            'actualizado_en' => null,
            'antiguedad' => null,
            'caducada' => false,
            'demasiado_antigua' => false,
        ];
    }

    $actualizadoEn = (int) filemtime($archivo);
    $antiguedad = max(0, time() - $actualizadoEn);

    return [
        'actualizado_en' => $actualizadoEn,
        'antiguedad' => $antiguedad,
        'caducada' => $antiguedad >= $cacheTtl,
        'demasiado_antigua' => $antiguedad > $maxStale,
    ];
}

/**
 * Descarga una predicción de Open-Meteo con las mismas restricciones de red
 * aplicadas al proveedor principal.
 */
function descargarOpenMeteo(string $url, int $timeout = 10): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('La URL de Open-Meteo no es válida.');
    }

    $partes = parse_url($url);

    if (
        !is_array($partes)
        || ($partes['scheme'] ?? '') !== 'https'
        || strtolower((string) ($partes['host'] ?? ''))
            !== 'api.open-meteo.com'
        || isset($partes['user'])
        || isset($partes['pass'])
        || (isset($partes['port']) && (int) $partes['port'] !== 443)
    ) {
        throw new RuntimeException('El destino de Open-Meteo no está autorizado.');
    }

    $host = 'api.open-meteo.com';
    $ip = resolverIpPublicaAemet($host);
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la consulta de respaldo.');
    }

    $respuesta = '';
    $excesoTamano = false;
    $maxBytes = 1_000_000;

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => max(5, $timeout),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: TuPortalNews-Weather/2.0',
        ],
        CURLOPT_RESOLVE => [$host . ':443:' . $ip],
        CURLOPT_WRITEFUNCTION => static function (
            $curlHandle,
            string $fragmento
        ) use (&$respuesta, &$excesoTamano, $maxBytes): int {
            if (strlen($respuesta) + strlen($fragmento) > $maxBytes) {
                $excesoTamano = true;

                return 0;
            }

            $respuesta .= $fragmento;

            return strlen($fragmento);
        },
    ]);

    $resultado = curl_exec($curl);
    $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $tipoContenido = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($excesoTamano) {
        throw new RuntimeException('La respuesta de respaldo es demasiado grande.');
    }

    if ($resultado === false) {
        throw new RuntimeException(
            'No se pudo conectar con el proveedor de respaldo'
            . ($error !== '' ? ': ' . $error : '.')
        );
    }

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        throw new RuntimeException(
            'El proveedor de respaldo respondió con HTTP '
            . $codigoHttp . '.'
        );
    }

    return decodificarJsonAemet($respuesta, $tipoContenido);
}

function describirCodigoMeteorologicoOpenMeteo(int $codigo): string
{
    return match (true) {
        $codigo === 0 => 'Despejado',
        in_array($codigo, [1, 2], true) => 'Poco nuboso',
        $codigo === 3 => 'Cubierto',
        in_array($codigo, [45, 48], true) => 'Niebla',
        in_array($codigo, [51, 53, 55, 56, 57], true) => 'Llovizna',
        in_array($codigo, [61, 63, 65, 66, 67], true) => 'Lluvia',
        in_array($codigo, [71, 73, 75, 77, 85, 86], true) => 'Nieve',
        in_array($codigo, [80, 81, 82], true) => 'Chubascos',
        in_array($codigo, [95, 96, 99], true) => 'Tormenta',
        default => 'Estado variable',
    };
}

function obtenerDireccionVientoOpenMeteo(float $grados): string
{
    $direcciones = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
    $indice = (int) floor((fmod($grados + 360.0, 360.0) + 22.5) / 45.0);

    return $direcciones[$indice % 8];
}

/**
 * @return array<int,array<string,mixed>>
 */
function normalizarPrediccionOpenMeteo(array $respuesta): array
{
    $daily = $respuesta['daily'] ?? null;

    if (!is_array($daily) || !is_array($daily['time'] ?? null)) {
        throw new RuntimeException(
            'La predicción de respaldo no tiene la estructura esperada.'
        );
    }

    $resultado = [];

    foreach ($daily['time'] as $indice => $fecha) {
        $codigo = (int) ($daily['weather_code'][$indice] ?? -1);
        $direccion = $daily['wind_direction_10m_dominant'][$indice] ?? null;

        $resultado[] = [
            'fecha' => (string) $fecha,
            'temperatura_maxima' => isset($daily['temperature_2m_max'][$indice])
                ? (int) round((float) $daily['temperature_2m_max'][$indice])
                : null,
            'temperatura_minima' => isset($daily['temperature_2m_min'][$indice])
                ? (int) round((float) $daily['temperature_2m_min'][$indice])
                : null,
            'humedad_maxima' => isset($daily['relative_humidity_2m_max'][$indice])
                ? (int) round((float) $daily['relative_humidity_2m_max'][$indice])
                : null,
            'humedad_minima' => isset($daily['relative_humidity_2m_min'][$indice])
                ? (int) round((float) $daily['relative_humidity_2m_min'][$indice])
                : null,
            'probabilidad_lluvia' => (int) round((float) (
                $daily['precipitation_probability_max'][$indice] ?? 0
            )),
            'estado_cielo' => describirCodigoMeteorologicoOpenMeteo($codigo),
            'viento_velocidad' => (int) round((float) (
                $daily['wind_speed_10m_max'][$indice] ?? 0
            )),
            'viento_direccion' => is_numeric($direccion)
                ? obtenerDireccionVientoOpenMeteo((float) $direccion)
                : '',
        ];
    }

    if ($resultado === []) {
        throw new RuntimeException('El proveedor de respaldo no devolvió días.');
    }

    return $resultado;
}

/**
 * Obtiene y almacena una predicción alternativa cuando AEMET no responde.
 *
 * @return array<int,array<string,mixed>>
 */
function obtenerPrediccionOpenMeteo(
    string $municipioId,
    float $latitud,
    float $longitud
): array {
    if (
        !preg_match('/^\d{5}$/', $municipioId)
        || $latitud < -90
        || $latitud > 90
        || $longitud < -180
        || $longitud > 180
    ) {
        throw new InvalidArgumentException(
            'Los datos del municipio para el respaldo no son válidos.'
        );
    }

    $config = cargarConfiguracionAemet();
    $cacheTtl = max(300, (int) ($config['cache_ttl'] ?? 1800));
    $maxStale = max($cacheTtl, (int) (
        $config['cache_max_stale'] ?? 172800
    ));
    $cacheDir = obtenerDirectorioCacheAemet();
    $cacheFile = $cacheDir . '/openmeteo_' . $municipioId . '.json';
    $lockFile = $cacheDir . '/openmeteo_' . $municipioId . '.lock';
    $cacheAnterior = leerCacheJsonAemet($cacheFile);
    $edad = is_file($cacheFile)
        ? max(0, time() - (int) filemtime($cacheFile))
        : null;

    if ($cacheAnterior !== null && $edad !== null && $edad < $cacheTtl) {
        return $cacheAnterior;
    }

    $bloqueo = fopen($lockFile, 'c');

    if ($bloqueo === false || !flock($bloqueo, LOCK_EX | LOCK_NB)) {
        if (is_resource($bloqueo)) {
            fclose($bloqueo);
        }

        if ($cacheAnterior !== null && $edad !== null && $edad <= $maxStale) {
            return $cacheAnterior;
        }

        throw new RuntimeException('La predicción de respaldo se está actualizando.');
    }

    try {
        $consulta = http_build_query([
            'latitude' => $latitud,
            'longitude' => $longitud,
            'daily' => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'precipitation_probability_max',
                'relative_humidity_2m_max',
                'relative_humidity_2m_min',
                'wind_speed_10m_max',
                'wind_direction_10m_dominant',
            ]),
            'timezone' => 'auto',
            'forecast_days' => 7,
            'wind_speed_unit' => 'kmh',
        ], '', '&', PHP_QUERY_RFC3986);

        try {
            $bloqueoGlobal = adquirirBloqueoGlobalAemet(
                'consulta_openmeteo_global.lock'
            );

            if ($bloqueoGlobal === null) {
                throw new RuntimeException(
                    'Hay una consulta meteorológica de respaldo en curso.'
                );
            }

            try {
                $respuesta = descargarOpenMeteo(
                    'https://api.open-meteo.com/v1/forecast?' . $consulta,
                    max(5, (int) ($config['timeout'] ?? 12))
                );
            } finally {
                liberarBloqueoGlobalAemet($bloqueoGlobal);
            }

            $prediccion = normalizarPrediccionOpenMeteo($respuesta);
            escribirCacheJsonAemet($cacheFile, $prediccion);

            return $prediccion;
        } catch (Throwable $e) {
            if ($cacheAnterior !== null && $edad !== null && $edad <= $maxStale) {
                return $cacheAnterior;
            }

            throw $e;
        }
    } finally {
        flock($bloqueo, LOCK_UN);
        fclose($bloqueo);
    }
}

function obtenerEstadoCacheOpenMeteo(string $municipioId): array
{
    if (!preg_match('/^\d{5}$/', $municipioId)) {
        return [
            'actualizado_en' => null,
            'antiguedad' => null,
            'caducada' => false,
            'demasiado_antigua' => false,
        ];
    }

    $archivo = obtenerDirectorioCacheAemet()
        . '/openmeteo_' . $municipioId . '.json';
    $config = cargarConfiguracionAemet();
    $cacheTtl = max(300, (int) ($config['cache_ttl'] ?? 1800));
    $maxStale = max($cacheTtl, (int) (
        $config['cache_max_stale'] ?? 172800
    ));

    if (!is_file($archivo)) {
        return [
            'actualizado_en' => null,
            'antiguedad' => null,
            'caducada' => false,
            'demasiado_antigua' => false,
        ];
    }

    $actualizadoEn = (int) filemtime($archivo);
    $antiguedad = max(0, time() - $actualizadoEn);

    return [
        'actualizado_en' => $actualizadoEn,
        'antiguedad' => $antiguedad,
        'caducada' => $antiguedad >= $cacheTtl,
        'demasiado_antigua' => $antiguedad > $maxStale,
    ];
}


function calcularDistanciaHaversineAemet(
    float $latitudOrigen,
    float $longitudOrigen,
    float $latitudDestino,
    float $longitudDestino
): float {
    $radioTierraKm = 6371.0088;

    $lat1 = deg2rad($latitudOrigen);
    $lat2 = deg2rad($latitudDestino);
    $deltaLat = deg2rad($latitudDestino - $latitudOrigen);
    $deltaLon = deg2rad($longitudDestino - $longitudOrigen);

    $a = sin($deltaLat / 2) ** 2
        + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $radioTierraKm * $c;
}

/**
 * Devuelve el municipio AEMET más cercano a unas coordenadas.
 *
 * @return array{
 *   codigo:string,
 *   nombre:string,
 *   provincia:string,
 *   distancia_km:float
 * }|null
 */
function obtenerMunicipioCercanoAemet(
    float $latitud,
    float $longitud
): ?array {
    if (
        $latitud < -90
        || $latitud > 90
        || $longitud < -180
        || $longitud > 180
    ) {
        throw new InvalidArgumentException(
            'Las coordenadas recibidas no son válidas.'
        );
    }

    $provincias = obtenerMunicipiosEspanaAemet();
    $masCercano = null;
    $distanciaMinima = INF;

    foreach ($provincias as $provincia => $municipios) {
        foreach ($municipios as $municipio) {
            $latitudMunicipio = $municipio['latitud'] ?? null;
            $longitudMunicipio = $municipio['longitud'] ?? null;

            if (
                !is_numeric($latitudMunicipio)
                || !is_numeric($longitudMunicipio)
            ) {
                continue;
            }

            $distancia = calcularDistanciaHaversineAemet(
                $latitud,
                $longitud,
                (float) $latitudMunicipio,
                (float) $longitudMunicipio
            );

            if ($distancia < $distanciaMinima) {
                $distanciaMinima = $distancia;
                $masCercano = [
                    'codigo' => (string) ($municipio['codigo'] ?? ''),
                    'nombre' => (string) ($municipio['nombre'] ?? ''),
                    'provincia' => (string) $provincia,
                    'distancia_km' => round($distancia, 2),
                ];
            }
        }
    }

    return $masCercano;
}


function normalizarPrediccionAemet(array $respuesta): array
{
    validarPrediccionMunicipioAemet($respuesta);

    $dias = $respuesta[0]['prediccion']['dia'];
    $resultado = [];

    foreach ($dias as $dia) {
        if (!is_array($dia)) {
            continue;
        }

        $temperatura = is_array($dia['temperatura'] ?? null)
            ? $dia['temperatura']
            : [];
        $precipitacion = is_array($dia['probPrecipitacion'] ?? null)
            ? $dia['probPrecipitacion']
            : [];
        $humedad = is_array($dia['humedadRelativa'] ?? null)
            ? $dia['humedadRelativa']
            : [];
        $cielo = is_array($dia['estadoCielo'] ?? null)
            ? $dia['estadoCielo']
            : [];
        $vientos = is_array($dia['viento'] ?? null)
            ? $dia['viento']
            : [];

        $vientoMaximo = 0;
        $direccionViento = '';

        foreach ($vientos as $viento) {
            if (!is_array($viento)) {
                continue;
            }

            $velocidad = (int) ($viento['velocidad'] ?? 0);

            if ($velocidad > $vientoMaximo) {
                $vientoMaximo = $velocidad;
                $direccionViento = trim(
                    (string) ($viento['direccion'] ?? '')
                );
            }
        }

        $descripcionCielo = '';

        foreach ($cielo as $estadoCielo) {
            if (!is_array($estadoCielo)) {
                continue;
            }

            $descripcion = trim(
                (string) ($estadoCielo['descripcion'] ?? '')
            );

            if ($descripcion !== '') {
                $descripcionCielo = $descripcion;
                break;
            }
        }

        $probabilidadLluvia = 0;

        foreach ($precipitacion as $periodo) {
            if (!is_array($periodo)) {
                continue;
            }

            $probabilidadLluvia = max(
                $probabilidadLluvia,
                (int) ($periodo['value'] ?? 0)
            );
        }

        $resultado[] = [
            'fecha' => (string) ($dia['fecha'] ?? ''),
            'temperatura_maxima' => isset($temperatura['maxima'])
                ? (int) $temperatura['maxima']
                : null,
            'temperatura_minima' => isset($temperatura['minima'])
                ? (int) $temperatura['minima']
                : null,
            'humedad_maxima' => isset($humedad['maxima'])
                ? (int) $humedad['maxima']
                : null,
            'humedad_minima' => isset($humedad['minima'])
                ? (int) $humedad['minima']
                : null,
            'probabilidad_lluvia' => $probabilidadLluvia,
            'estado_cielo' => $descripcionCielo,
            'viento_velocidad' => $vientoMaximo,
            'viento_direccion' => $direccionViento,
        ];
    }

    return $resultado;
}

function generarAlertasAemet(array $dias, array $umbrales): array
{
    $alertas = [];

    foreach ($dias as $dia) {
        $fecha = (string) ($dia['fecha'] ?? '');
        $lluvia = (int) ($dia['probabilidad_lluvia'] ?? 0);
        $maxima = $dia['temperatura_maxima'] ?? null;
        $minima = $dia['temperatura_minima'] ?? null;
        $viento = (int) ($dia['viento_velocidad'] ?? 0);

        if ($lluvia >= (int) ($umbrales['lluvia_probabilidad'] ?? 70)) {
            $alertas[] = [
                'tipo' => 'lluvia',
                'fecha' => $fecha,
                'mensaje' => 'Probabilidad alta de lluvia: '
                    . $lluvia . ' %.',
            ];
        }

        if (
            $maxima !== null
            && $maxima >= (int) ($umbrales['temperatura_maxima'] ?? 35)
        ) {
            $alertas[] = [
                'tipo' => 'calor',
                'fecha' => $fecha,
                'mensaje' => 'Temperatura máxima elevada: '
                    . $maxima . ' °C.',
            ];
        }

        if (
            $minima !== null
            && $minima <= (int) ($umbrales['temperatura_minima'] ?? 5)
        ) {
            $alertas[] = [
                'tipo' => 'frio',
                'fecha' => $fecha,
                'mensaje' => 'Temperatura mínima baja: '
                    . $minima . ' °C.',
            ];
        }

        if ($viento >= (int) ($umbrales['viento_velocidad'] ?? 50)) {
            $alertas[] = [
                'tipo' => 'viento',
                'fecha' => $fecha,
                'mensaje' => 'Viento previsto: hasta '
                    . $viento . ' km/h.',
            ];
        }
    }

    return $alertas;
}
