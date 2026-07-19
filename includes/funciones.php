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

function formatearFechaHora(string $fecha): string
{
    $timestamp = strtotime($fecha);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : $fecha;
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

/**
 * Semáforo de vencimientos de mantenimiento: por cada plan, calcula qué
 * porcentaje del intervalo queda (por km y/o por fecha, lo que ocurra
 * primero) contra el último service registrado. Ordenado por urgencia.
 *
 * @return array<int, array{plan: array, ultimo: ?array, km_actual: ?int, detalles: array, pct: ?float, color: ?string}>
 */
function calcularVencimientosMantenimiento(PDO $pdo): array
{
    $umbral = ((float) obtenerParametro('pct_alerta_service', '20')) / 100;

    $camiones = $pdo->query('SELECT id, patente, km_actual FROM camiones WHERE activo=1 ORDER BY patente')->fetchAll();
    $kmActualPorCamion = [];
    foreach ($camiones as $camion) {
        $kmActualPorCamion[(int) $camion['id']] = $camion['km_actual'] !== null ? (int) $camion['km_actual'] : null;
    }

    $ultimoServicio = [];
    $stmt = $pdo->query('SELECT camion_id, tipo_service_id, fecha, km FROM services ORDER BY fecha ASC, id ASC');
    foreach ($stmt->fetchAll() as $s) {
        $ultimoServicio[$s['camion_id'] . '_' . $s['tipo_service_id']] = ['fecha' => $s['fecha'], 'km' => $s['km']];
    }

    $planes = $pdo->query(
        "SELECT pm.*, c.patente, ts.nombre AS tipo_nombre
         FROM planes_mantenimiento pm
         JOIN camiones c ON c.id = pm.camion_id
         JOIN tipos_service ts ON ts.id = pm.tipo_service_id
         WHERE c.activo = 1
         ORDER BY c.patente, ts.nombre"
    )->fetchAll();

    $hoy = strtotime(date('Y-m-d'));
    $filas = [];

    foreach ($planes as $plan) {
        $camionId = (int) $plan['camion_id'];
        $tipoId   = (int) $plan['tipo_service_id'];
        $ultimo   = $ultimoServicio[$camionId . '_' . $tipoId] ?? null;
        $kmActual = $kmActualPorCamion[$camionId] ?? null;

        $fila = [
            'plan'      => $plan,
            'ultimo'    => $ultimo,
            'km_actual' => $kmActual,
            'detalles'  => [],
            'pct'       => null,
            'color'     => null,
        ];

        if ($ultimo) {
            $pcts = [];

            if ($plan['intervalo_km'] && $ultimo['km'] !== null && $kmActual !== null) {
                $kmVencimiento = (int) $ultimo['km'] + (int) $plan['intervalo_km'];
                $kmRestante    = $kmVencimiento - $kmActual;
                $pctKm         = $kmRestante / (int) $plan['intervalo_km'];
                $pcts[] = $pctKm;
                $fila['detalles']['km'] = ['restante' => $kmRestante, 'vencimiento' => $kmVencimiento];
            }

            if ($plan['intervalo_meses'] && $ultimo['fecha']) {
                $fechaVencimientoTs = strtotime($ultimo['fecha'] . ' +' . (int) $plan['intervalo_meses'] . ' months');
                $diasTotal      = max(1, (int) round(($fechaVencimientoTs - strtotime($ultimo['fecha'])) / 86400));
                $diasRestantes  = (int) round(($fechaVencimientoTs - $hoy) / 86400);
                $pctFecha       = $diasRestantes / $diasTotal;
                $pcts[] = $pctFecha;
                $fila['detalles']['fecha'] = ['restante_dias' => $diasRestantes, 'vencimiento' => date('Y-m-d', $fechaVencimientoTs)];
            }

            if ($pcts) {
                $fila['pct'] = min($pcts);
                if ($fila['pct'] <= 0) {
                    $fila['color'] = 'rojo';
                } elseif ($fila['pct'] <= $umbral) {
                    $fila['color'] = 'amarillo';
                } else {
                    $fila['color'] = 'verde';
                }
            }
        }

        $filas[] = $fila;
    }

    usort($filas, function ($a, $b) {
        if ($a['pct'] === null && $b['pct'] === null) {
            return 0;
        }
        if ($a['pct'] === null) {
            return 1;
        }
        if ($b['pct'] === null) {
            return -1;
        }

        return $a['pct'] <=> $b['pct'];
    });

    return $filas;
}
