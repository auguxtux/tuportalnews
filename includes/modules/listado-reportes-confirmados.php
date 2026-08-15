<?php

declare(strict_types=1);

/**
 * Listado anónimo de contenidos con reportes confirmados.
 *
 * @return array<int,array<string,mixed>>
 */
function listarReportesConfirmados(PDO $pdo, bool $privado, string $tipo): array
{
    $tipo = in_array($tipo, ['todos', 'noticia', 'comentario'], true)
        ? $tipo
        : 'todos';
    $ambito = $privado ? 1 : 0;
    $resultados = [];

    if ($tipo !== 'comentario') {
        $stmt = $pdo->prepare(
            "SELECT 'noticia' AS tipo, n.id_noticia, NULL AS id_comentario,
                    n.titulo, NULL AS contenido,
                    COUNT(*) AS total_reportes,
                    GROUP_CONCAT(DISTINCT r.motivo ORDER BY r.motivo SEPARATOR ',') AS motivos,
                    MAX(r.fecha) AS ultima_fecha
             FROM reportes_noticias r
             INNER JOIN noticias n ON n.id_noticia = r.noticia_id
             WHERE r.estado = 'confirmado'
               AND n.estado = 'publicada' AND n.privada = ?
             GROUP BY n.id_noticia, n.titulo"
        );
        $stmt->execute([$ambito]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($tipo !== 'noticia') {
        $stmt = $pdo->prepare(
            "SELECT 'comentario' AS tipo, n.id_noticia,
                    c.id_comentario, n.titulo, c.contenido,
                    COUNT(*) AS total_reportes,
                    GROUP_CONCAT(DISTINCT r.motivo ORDER BY r.motivo SEPARATOR ',') AS motivos,
                    MAX(r.fecha) AS ultima_fecha
             FROM reportes_comentarios r
             INNER JOIN comentarios c ON c.id_comentario = r.comentario_id
             INNER JOIN noticias n ON n.id_noticia = c.id_noticia
             WHERE r.estado = 'confirmado' AND c.estado = 'aprobado'
               AND n.estado = 'publicada' AND n.privada = ?
             GROUP BY c.id_comentario, n.id_noticia, n.titulo, c.contenido"
        );
        $stmt->execute([$ambito]);
        $resultados = array_merge($resultados, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    usort(
        $resultados,
        static fn(array $a, array $b): int =>
            strtotime((string) $b['ultima_fecha']) <=> strtotime((string) $a['ultima_fecha'])
    );

    foreach ($resultados as &$resultado) {
        $motivos = array_filter(explode(',', (string) ($resultado['motivos'] ?? '')));
        $resultado['motivos_etiquetas'] = array_map('etiquetaMotivoReporte', $motivos);
    }
    unset($resultado);

    return $resultados;
}
