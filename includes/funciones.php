<?php

function formatearImporte(float $valor): string
{
    return '$ ' . number_format($valor, 2, ',', '.');
}

function formatearFecha(string $fecha): string
{
    $timestamp = strtotime($fecha);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : $fecha;
}

function obtenerParametro(string $clave, ?string $porDefecto = null): ?string
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('SELECT valor FROM parametros WHERE clave = ?');
    $stmt->execute([$clave]);
    $valor = $stmt->fetchColumn();

    return $valor !== false ? $valor : $porDefecto;
}

function nombreMes(int $mes): string
{
    $nombres = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    return $nombres[$mes] ?? '';
}
