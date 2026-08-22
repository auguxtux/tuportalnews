<?php
declare(strict_types=1);



/**
 * Consultas reutilizables relacionadas con noticias.
 *
 * Este helper centraliza las consultas utilizadas por la portada para evitar
 * duplicar SQL y mantener public/portada.php centrado en la presentación.
 */

/**
 * Devuelve la condición SQL que limita el acceso a noticias privadas.
 */
function condicionNoticiasPrivadas(
    bool $puedeVerPrivadas,
    string $alias = 'n'
): string {
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias) !== 1) {
        throw new InvalidArgumentException('Alias SQL no válido.');
    }

    return $puedeVerPrivadas
        ? ''
        : sprintf(' AND %s.privada = 0', $alias);
}

/**
 * Normaliza un límite para consultas de portada.
 */
function normalizarLimiteNoticias(
    int $limite,
    int $maximo = 100
): int {
    return min($maximo, max(1, $limite));
}

/**
 * Obtiene las noticias mostradas en el slider principal.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerNoticiasSlider(
    PDO $pdo,
    bool $puedeVerPrivadas,
    int $limite = 5
): array {
    $limite = normalizarLimiteNoticias($limite, 20);
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            u.avatar AS autor_avatar,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        WHERE n.estado = 'publicada'
            {$condicionPrivadas}
            AND (
                (n.imagen_principal IS NOT NULL AND n.imagen_principal != '')
                OR (n.imagen_externa IS NOT NULL AND n.imagen_externa != '')
            )
        ORDER BY RAND()
        LIMIT {$limite}
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las noticias publicadas más recientes.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerUltimasNoticias(
    PDO $pdo,
    bool $puedeVerPrivadas,
    int $limite = 6
): array {
    $limite = normalizarLimiteNoticias($limite, 50);
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        WHERE n.estado = 'publicada'
            {$condicionPrivadas}
        ORDER BY n.fecha_publicacion DESC
        LIMIT {$limite}
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las noticias con mayor número de visitas.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerNoticiasPopulares(
    PDO $pdo,
    bool $puedeVerPrivadas,
    int $limite = 5
): array {
    $limite = normalizarLimiteNoticias($limite, 50);
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        WHERE n.estado = 'publicada'
            {$condicionPrivadas}
        ORDER BY n.visitas DESC
        LIMIT {$limite}
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene una noticia aleatoria por cada categoría seleccionada.
 *
 * La estructura devuelta conserva el formato utilizado actualmente por
 * public/portada.php.
 *
 * @param array<string, array{color: string, icono: string}> $categorias
 *
 * @return array<string, array{
 *     noticia: array<string, mixed>|false,
 *     color: string,
 *     icono: string
 * }>
 */
function obtenerNoticiasPorCategorias(
    PDO $pdo,
    array $categorias,
    bool $puedeVerPrivadas
): array {
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        WHERE c.nombre_categoria = :categoria
            AND n.estado = 'publicada'
            {$condicionPrivadas}
        ORDER BY RAND()
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $resultado = [];

    foreach ($categorias as $nombreCategoria => $configuracion) {
        $stmt->execute([
            ':categoria' => $nombreCategoria,
        ]);

        $resultado[$nombreCategoria] = [
            'noticia' => $stmt->fetch(PDO::FETCH_ASSOC),
            'color' => (string) ($configuracion['color'] ?? '#2a5298'),
            'icono' => (string) ($configuracion['icono'] ?? '📰'),
        ];

        $stmt->closeCursor();
    }

    return $resultado;
}

/**
 * Obtiene noticias aleatorias con imagen para el bloque de listado.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerNoticiasDestacadasListado(
    PDO $pdo,
    bool $puedeVerPrivadas,
    int $limite = 4
): array {
    $limite = normalizarLimiteNoticias($limite, 20);
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        WHERE n.estado = 'publicada'
            {$condicionPrivadas}
            AND n.imagen_principal IS NOT NULL
            AND n.imagen_principal != ''
        ORDER BY RAND()
        LIMIT {$limite}
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene la noticia marcada manualmente como destacada.
 *
 * @return array<string, mixed>|false
 */
function obtenerNoticiaDestacadaPrincipal(
    PDO $pdo,
    bool $puedeVerPrivadas
): array|false {
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            u.avatar AS autor_avatar,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        WHERE n.destacada = 1
            AND n.estado = 'publicada'
            {$condicionPrivadas}
        ORDER BY n.fecha_publicacion DESC
        LIMIT 1
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Cuenta todas las noticias publicadas visibles para el usuario actual.
 */
function contarNoticiasPublicadas(
    PDO $pdo,
    bool $puedeVerPrivadas
): int {
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT COUNT(*)
        FROM noticias n
        WHERE n.estado = 'publicada'
            {$condicionPrivadas}
    ";

    return (int) $pdo->query($sql)->fetchColumn();
}

/**
 * Obtiene noticias publicadas paginadas, ordenadas de más reciente a más antigua.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerNoticiasPublicadasPaginadas(
    PDO $pdo,
    bool $puedeVerPrivadas,
    int $limite,
    int $offset
): array {
    $limite = normalizarLimiteNoticias($limite, 100);
    $offset = max(0, $offset);
    $condicionPrivadas = condicionNoticiasPrivadas($puedeVerPrivadas);

    $sql = "
        SELECT
            n.*,
            u.nombre AS autor_nombre,
            u.avatar AS autor_avatar,
            c.nombre_categoria,
            c.slug_categoria,
            r.nombre AS nombre_region,
            r.slug AS slug_region,
            COALESCE(co.total_comentarios, 0) AS total_comentarios
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
        LEFT JOIN regiones r
            ON n.id_region = r.id_region
        LEFT JOIN (
            SELECT
                id_noticia,
                COUNT(*) AS total_comentarios
            FROM comentarios
            WHERE estado = 'aprobado'
            GROUP BY id_noticia
        ) co
            ON co.id_noticia = n.id_noticia
        WHERE n.estado = 'publicada'
            {$condicionPrivadas}
        ORDER BY n.fecha_publicacion DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Construye los filtros opcionales del listado público.
 *
 * @return array{sql:string,parametros:array<string, int>}
 */
function filtrosListadoNoticiasPublicas(
    int $idCategoria,
    int $idRegion
): array {
    $condiciones = [];
    $parametros = [];

    if ($idCategoria > 0) {
        $condiciones[] = 'c.id_categoria = :filtro_categoria';
        $parametros[':filtro_categoria'] = $idCategoria;
    }

    if ($idRegion > 0) {
        $condiciones[] = 'n.id_region = :filtro_region';
        $parametros[':filtro_region'] = $idRegion;
    }

    return [
        'sql' => $condiciones === []
            ? ''
            : ' AND ' . implode(' AND ', $condiciones),
        'parametros' => $parametros,
    ];
}

/** Cuenta las noticias del listado público con sus filtros opcionales. */
function contarListadoNoticiasPublicas(
    PDO $pdo,
    int $idCategoria = 0,
    int $idRegion = 0
): int {
    $filtros = filtrosListadoNoticiasPublicas($idCategoria, $idRegion);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM noticias n
         INNER JOIN categorias c ON n.id_categoria = c.id_categoria
         WHERE n.estado = 'publicada'
           AND n.privada = 0{$filtros['sql']}"
    );
    $stmt->execute($filtros['parametros']);

    return (int) $stmt->fetchColumn();
}

/**
 * Devuelve las noticias del listado público con filtros y paginación.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerListadoNoticiasPublicas(
    PDO $pdo,
    int $idCategoria,
    int $idRegion,
    int $limite,
    int $offset
): array {
    $limite = normalizarLimiteNoticias($limite, 100);
    $offset = max(0, $offset);
    $filtros = filtrosListadoNoticiasPublicas($idCategoria, $idRegion);

    $stmt = $pdo->prepare(
        "SELECT n.*,
                u.nombre AS autor_nombre,
                u.avatar AS autor_avatar,
                c.nombre_categoria,
                c.slug_categoria,
                r.nombre AS nombre_region,
                r.slug AS slug_region,
                f.nombre AS fuente_normal_nombre,
                fr.nombre AS fuente_rss_nombre,
                (
                    SELECT COUNT(*)
                    FROM comentarios co
                    WHERE co.id_noticia = n.id_noticia
                      AND co.estado = 'aprobado'
                ) AS total_comentarios
         FROM noticias n
         INNER JOIN usuarios u ON n.id_autor = u.id_usuario
         INNER JOIN categorias c ON n.id_categoria = c.id_categoria
         LEFT JOIN regiones r ON n.id_region = r.id_region
         LEFT JOIN fuentes f ON f.id_fuente = n.id_fuente
         LEFT JOIN fuentes_rss fr ON fr.id_fuente = n.id_fuente_rss
         WHERE n.estado = 'publicada'
           AND n.privada = 0{$filtros['sql']}
         ORDER BY n.fecha_publicacion DESC, n.id_noticia DESC
         LIMIT :limite OFFSET :offset"
    );

    foreach ($filtros['parametros'] as $nombre => $valor) {
        $stmt->bindValue($nombre, $valor, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Normaliza el periodo permitido para las noticias populares. */
function normalizarPeriodoNoticiasPopulares(string $periodo): string
{
    return in_array($periodo, ['semana', 'mes', 'ano'], true)
        ? $periodo
        : 'todo';
}

/** Devuelve una condición SQL fija para el periodo validado. */
function condicionPeriodoNoticiasPopulares(string $periodo): string
{
    return match (normalizarPeriodoNoticiasPopulares($periodo)) {
        'semana' => ' AND n.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'mes' => ' AND n.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        'ano' => ' AND n.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        default => '',
    };
}

/** Cuenta las noticias públicas incluidas en el periodo solicitado. */
function contarNoticiasPopulares(PDO $pdo, string $periodo): int
{
    $condicionPeriodo = condicionPeriodoNoticiasPopulares($periodo);

    return (int) $pdo->query(
        "SELECT COUNT(*)
         FROM noticias n
         WHERE n.estado = 'publicada'
           AND n.privada = 0{$condicionPeriodo}"
    )->fetchColumn();
}

/**
 * Devuelve las noticias públicas más vistas del periodo solicitado.
 *
 * @return array<int, array<string, mixed>>
 */
function obtenerNoticiasPopularesPaginadas(
    PDO $pdo,
    string $periodo,
    int $limite,
    int $offset
): array {
    $limite = normalizarLimiteNoticias($limite, 100);
    $offset = max(0, $offset);
    $condicionPeriodo = condicionPeriodoNoticiasPopulares($periodo);

    $stmt = $pdo->prepare(
        "SELECT n.*,
                u.nombre AS autor_nombre,
                u.avatar AS autor_avatar,
                c.nombre_categoria,
                c.slug_categoria,
                (
                    SELECT COUNT(*)
                    FROM comentarios
                    WHERE id_noticia = n.id_noticia
                      AND estado = 'aprobado'
                ) AS total_comentarios
         FROM noticias n
         INNER JOIN usuarios u ON n.id_autor = u.id_usuario
         INNER JOIN categorias c ON n.id_categoria = c.id_categoria
         WHERE n.estado = 'publicada'
           AND n.privada = 0{$condicionPeriodo}
         ORDER BY n.visitas DESC, n.fecha_publicacion DESC
         LIMIT :limite OFFSET :offset"
    );
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Devuelve la noticia pública con más visitas históricas.
 *
 * @return array<string, mixed>|false
 */
function obtenerNoticiaPublicaMasVista(PDO $pdo): array|false
{
    $stmt = $pdo->query(
        "SELECT n.titulo, n.visitas, n.id_noticia
         FROM noticias n
         WHERE n.estado = 'publicada'
           AND n.privada = 0
         ORDER BY n.visitas DESC
         LIMIT 1"
    );

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
