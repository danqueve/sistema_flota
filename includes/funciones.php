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

function transicionChequeRecibidoValida(string $actual, string $nuevo): bool
{
    $transiciones = [
        'en_cartera' => ['depositado', 'vendido', 'endosado'],
        'depositado' => ['acreditado', 'rechazado'],
        'rechazado'  => ['recuperado'],
    ];

    return in_array($nuevo, $transiciones[$actual] ?? [], true);
}

function transicionChequeEmitidoValida(string $actual, string $nuevo): bool
{
    $transiciones = [
        'emitido' => ['debitado', 'rechazado'],
    ];

    return in_array($nuevo, $transiciones[$actual] ?? [], true);
}

function registrarMovimientoCheque(PDO $pdo, int $chequeId, string $anterior, string $nuevo, int $usuarioId, ?float $gastos = null, ?string $obs = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO cheques_movimientos (cheque_id, estado_anterior, estado_nuevo, usuario_id, gastos_asociados, observaciones)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$chequeId, $anterior, $nuevo, $usuarioId, $gastos, $obs]);
}

function obtenerCategoriaGastoPorNombre(PDO $pdo, string $nombre): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM categorias_gasto WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}
