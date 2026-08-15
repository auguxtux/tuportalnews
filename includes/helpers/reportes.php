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
