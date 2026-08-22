<?php
declare(strict_types=1);

function motivosReporte(): array
{
    return [
        'fuente_no_valida' => 'Fuente no válida',
        'contenido_falso' => 'Contenido falso',
        'pornografico' => 'Contenido pornográfico',
        'insultante' => 'Contenido insultante',
        'violento' => 'Contenido violento',
        'spam' => 'Spam o publicidad',
        'derechos' => 'Infracción de derechos',
        'otro' => 'Otro',
    ];
}

function motivoReporteValido(string $motivo): bool
{
    return array_key_exists($motivo, motivosReporte());
}

function etiquetaMotivoReporte(string $motivo): string
{
    $etiquetas = motivosReporte();
    $etiquetas['ofensivo'] = 'Contenido insultante';
    $etiquetas['acoso'] = 'Acoso o insultos';
    $etiquetas['incorrecto'] = 'Información incorrecta';

    return $etiquetas[$motivo] ?? ucfirst(str_replace('_', ' ', $motivo));
}

/**
 * Normaliza los campos comunes enviados por los formularios de reporte.
 *
 * @return array{motivo: string, descripcion: string}
 */
function normalizarDatosReporte(array $datos): array
{
    return [
        'motivo' => limpiarDatos($datos['motivo'] ?? ''),
        'descripcion' => mb_substr(trim((string)($datos['descripcion'] ?? '')), 0, 1000),
    ];
}

/**
 * Obtiene un comentario que puede reportarse en el ámbito indicado.
 */
function obtenerComentarioReportable(PDO $pdo, int $comentarioId, bool $privado): array|false
{
    $stmt = $pdo->prepare("SELECT c.id_comentario, c.id_usuario, u.nombre AS autor_nombre,
                                  n.titulo AS noticia_titulo, n.id_noticia
                           FROM comentarios c
                           JOIN usuarios u ON c.id_usuario = u.id_usuario
                           JOIN noticias n ON c.id_noticia = n.id_noticia
                           WHERE c.id_comentario = ?
                             AND c.estado = 'aprobado'
                             AND n.estado IN ('publicada','destacada')
                             AND n.privada = ?");
    $stmt->execute([$comentarioId, $privado ? 1 : 0]);

    return $stmt->fetch();
}

/**
 * Obtiene una noticia que puede reportarse en el ámbito indicado.
 */
function obtenerNoticiaReportable(PDO $pdo, int $noticiaId, bool $privado): array|false
{
    $stmt = $pdo->prepare("SELECT id_noticia, id_autor, titulo
                           FROM noticias
                           WHERE id_noticia = ?
                             AND estado IN ('publicada','destacada')
                             AND privada = ?");
    $stmt->execute([$noticiaId, $privado ? 1 : 0]);

    return $stmt->fetch();
}
