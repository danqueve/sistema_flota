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
