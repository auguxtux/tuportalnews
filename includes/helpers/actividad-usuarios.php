<?php

declare(strict_types=1);

function formatearTiempoActividad(int $segundos): string
{
    if ($segundos < 60) {
        return $segundos . ' s';
    }

    $dias = intdiv($segundos, 86400);
    $horas = intdiv($segundos % 86400, 3600);
    $minutos = intdiv($segundos % 3600, 60);

    if ($dias > 0) {
        return $dias . ' d ' . $horas . ' h';
    }

    if ($horas > 0) {
        return $horas . ' h ' . $minutos . ' min';
    }

    return $minutos . ' min';
}

function usuarioEstaEnLinea(?string $ultimaActividad): bool
{
    if ($ultimaActividad === null || $ultimaActividad === '') {
        return false;
    }

    $marcaTiempo = strtotime($ultimaActividad);

    return $marcaTiempo !== false && $marcaTiempo >= time() - 300;
}
