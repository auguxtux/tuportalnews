<?php

declare(strict_types=1);

/** @return array{total:int,motivos:array<int,string>} */
function obtenerReportesConfirmadosNoticia(PDO $pdo, int $idNoticia, bool $privada): array
{
    $stmt = $pdo->prepare(
        "SELECT r.motivo, COUNT(*) AS total
         FROM reportes_noticias r
         INNER JOIN noticias n ON n.id_noticia = r.noticia_id
         WHERE r.noticia_id = ? AND r.estado = 'confirmado'
           AND n.privada = ? AND n.estado = 'publicada'
         GROUP BY r.motivo ORDER BY total DESC, r.motivo ASC"
    );
    $stmt->execute([$idNoticia, $privada ? 1 : 0]);

    $resumen = ['total' => 0, 'motivos' => []];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $resumen['total'] += (int) $fila['total'];
        $resumen['motivos'][] = etiquetaMotivoReporte((string) $fila['motivo']);
    }

    return $resumen;
}

/** @param int[] $idsComentarios @return array<int,array{total:int,motivos:array<int,string>}> */
function obtenerReportesConfirmadosComentarios(PDO $pdo, array $idsComentarios, bool $privada): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $idsComentarios))));
    if ($ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT r.comentario_id, r.motivo, COUNT(*) AS total
         FROM reportes_comentarios r
         INNER JOIN comentarios c ON c.id_comentario = r.comentario_id
         INNER JOIN noticias n ON n.id_noticia = c.id_noticia
         WHERE r.comentario_id IN ({$marcadores}) AND r.estado = 'confirmado'
           AND c.estado = 'aprobado' AND n.estado = 'publicada' AND n.privada = ?
         GROUP BY r.comentario_id, r.motivo
         ORDER BY r.comentario_id, total DESC"
    );
    $stmt->execute([...$ids, $privada ? 1 : 0]);

    $resumenes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $idComentario = (int) $fila['comentario_id'];
        $resumenes[$idComentario] ??= ['total' => 0, 'motivos' => []];
        $resumenes[$idComentario]['total'] += (int) $fila['total'];
        $resumenes[$idComentario]['motivos'][] = etiquetaMotivoReporte((string) $fila['motivo']);
    }

    return $resumenes;
}
