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
            c.slug_categoria
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
            c.slug_categoria
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
            c.slug_categoria
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
            c.slug_categoria
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
            c.slug_categoria
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
            c.slug_categoria
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
            COALESCE(co.total_comentarios, 0) AS total_comentarios
        FROM noticias n
        INNER JOIN usuarios u
            ON n.id_autor = u.id_usuario
        INNER JOIN categorias c
            ON n.id_categoria = c.id_categoria
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
