<?php

/**
 * Genera datos transaccionales de demo: un mes de operación creíble que
 * conecta los 4 módulos (fletes que cobran con cheques, resúmenes de
 * combustible, repuestos consumidos en services, tarimas coherentes con
 * sus remitos). Pensado para correr en local antes de mostrarle el
 * sistema a Alejandro/al cliente, NO para producción.
 *
 * Uso: php database/generar_datos_demo.php
 *
 * Las fechas son relativas a "hoy" (no hardcodeadas), así el guion sigue
 * siendo válido corriéndolo en cualquier momento. Los maestros (camiones,
 * choferes, clientes, estaciones, cuentas, financieras, repuestos, tipos
 * de service, categorías, parámetros, usuarios) NO se tocan: solo se
 * regeneran las tablas transaccionales.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/funciones.php';

$pdo = obtenerConexion();

function fecha(int $offsetDias): string
{
    return date('Y-m-d', strtotime($offsetDias . ' days'));
}

function idPor(PDO $pdo, string $tabla, string $columna, string $valor): int
{
    $stmt = $pdo->prepare("SELECT id FROM $tabla WHERE $columna = ? LIMIT 1");
    $stmt->execute([$valor]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("No encontré $tabla.$columna = $valor");
    }
    return (int) $id;
}

echo "Limpiando datos transaccionales...\n";
$pdo->exec('DELETE FROM movimientos_stock WHERE service_id IS NOT NULL');
$pdo->exec('DELETE FROM services');
$pdo->exec('DELETE FROM planes_mantenimiento');
$pdo->exec('DELETE FROM remitos'); // cascada a pallets_movimientos
$pdo->exec('DELETE FROM cheques_movimientos');
$pdo->exec('DELETE FROM cheques');
$pdo->exec('UPDATE liquidaciones SET movimiento_id = NULL');
$pdo->exec('UPDATE resumenes_estacion SET movimiento_id = NULL');
$pdo->exec('DELETE FROM movimientos_tesoreria');
$pdo->exec('DELETE FROM fletes'); // cascada a gastos_viaje
$pdo->exec('DELETE FROM liquidaciones');
$pdo->exec('DELETE FROM resumenes_estacion');
$pdo->exec('DELETE FROM cargas_combustible');

$usuarioId = (int) $pdo->query("SELECT id FROM usuarios WHERE usuario = 'alejandro' LIMIT 1")->fetchColumn();

$camion1 = idPor($pdo, 'camiones', 'patente', 'AB 123 CD');
$camion2 = idPor($pdo, 'camiones', 'patente', 'AC 456 EF');
$camion3 = idPor($pdo, 'camiones', 'patente', 'AD 789 GH');

$chofer1 = idPor($pdo, 'choferes', 'nombre', 'Juan Pérez');
$chofer2 = idPor($pdo, 'choferes', 'nombre', 'Ramón Gómez');
$chofer3 = idPor($pdo, 'choferes', 'nombre', 'Sergio Ledesma');

$clienteMolinos    = idPor($pdo, 'clientes', 'razon_social', 'Molinos Tucumán S.A.');
$clienteCitricola  = idPor($pdo, 'clientes', 'razon_social', 'Citrícola San Miguel S.R.L.');
$clienteFrigorifico = idPor($pdo, 'clientes', 'razon_social', 'Frigorífico del Norte S.A.');
$clienteEnvasadora = idPor($pdo, 'clientes', 'razon_social', 'Envasadora del Litoral S.A.');

$estacionYpf   = idPor($pdo, 'estaciones', 'nombre', 'YPF Ruta 9');
$estacionAxion = idPor($pdo, 'estaciones', 'nombre', 'Axion Ruta 38');

$cuentaGalicia = idPor($pdo, 'cuentas', 'nombre', 'Banco Galicia CC $');
$cuentaMacro   = idPor($pdo, 'cuentas', 'nombre', 'Banco Macro CC $');
$cuentaCaja    = idPor($pdo, 'cuentas', 'nombre', 'Caja efectivo');

$financiera1 = idPor($pdo, 'financieras', 'nombre', 'Financiera del Norte');

$tipoAceite    = idPor($pdo, 'tipos_service', 'nombre', 'Cambio de aceite y filtros');
$tipoFrenos    = idPor($pdo, 'tipos_service', 'nombre', 'Frenos');
$tipoCubiertas = idPor($pdo, 'tipos_service', 'nombre', 'Cubiertas');
$tipoEmbrague  = idPor($pdo, 'tipos_service', 'nombre', 'Embrague');
$tipoTren      = idPor($pdo, 'tipos_service', 'nombre', 'Tren delantero');
$tipoRevision  = idPor($pdo, 'tipos_service', 'nombre', 'Revisión general');

$repF100 = idPor($pdo, 'repuestos', 'codigo', 'F-100'); // Filtro de aceite
$repF101 = idPor($pdo, 'repuestos', 'codigo', 'F-101'); // Filtro de aire
$repA500 = idPor($pdo, 'repuestos', 'codigo', 'A-500'); // Aceite de motor
$repC400 = idPor($pdo, 'repuestos', 'codigo', 'C-400'); // Cubierta 295/80 R22.5
$repB200 = idPor($pdo, 'repuestos', 'codigo', 'B-200'); // Pastillas de freno
$repB202 = idPor($pdo, 'repuestos', 'codigo', 'B-202'); // Amortiguador delantero

$offsetMesAnterior = (int) round((strtotime(date('Y-m-01', strtotime('first day of last month'))) - time()) / 86400);
$offsetMesActual   = (int) round((strtotime(date('Y-m-01')) - time()) / 86400);

echo "Cargando fletes...\n";

function crearFlete(PDO $pdo, array $datos): int
{
    $pctComision = (float) obtenerParametro('pct_comision_chofer', '15');
    $comision = round($datos['bruto'] * $pctComision / 100, 2);
    $stmt = $pdo->prepare(
        'INSERT INTO fletes (fecha, camion_id, chofer_id, cliente_id, destino, importe_bruto, pct_comision, comision_chofer, viatico_adelanto)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $datos['fecha'], $datos['camion_id'], $datos['chofer_id'], $datos['cliente_id'],
        $datos['destino'], $datos['bruto'], $pctComision, $comision, $datos['viatico'],
    ]);
    return (int) $pdo->lastInsertId();
}

function agregarGasto(PDO $pdo, int $fleteId, string $categoriaNombre, float $importe, string $descripcion): void
{
    $categoriaId = obtenerCategoriaGastoPorNombre($pdo, $categoriaNombre);
    $pdo->prepare('INSERT INTO gastos_viaje (flete_id, categoria_id, importe, descripcion) VALUES (?, ?, ?, ?)')
        ->execute([$fleteId, $categoriaId, $importe, $descripcion]);
}

// --- Mes anterior (se liquida y se paga) ---
$f1 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 2), 'camion_id' => $camion1, 'chofer_id' => $chofer1, 'cliente_id' => $clienteMolinos, 'destino' => 'Buenos Aires', 'bruto' => 480000, 'viatico' => 25000]);
agregarGasto($pdo, $f1, 'Peaje', 8000, 'Autopista');
agregarGasto($pdo, $f1, 'Playa', 12000, 'Estacionamiento destino');

$f2 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 10), 'camion_id' => $camion1, 'chofer_id' => $chofer1, 'cliente_id' => $clienteCitricola, 'destino' => 'Córdoba', 'bruto' => 390000, 'viatico' => 20000]);
agregarGasto($pdo, $f2, 'Peaje', 7000, 'Peajes ruta');
agregarGasto($pdo, $f2, 'Playa', 15000, 'Playa de camiones');

$f3 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 18), 'camion_id' => $camion1, 'chofer_id' => $chofer1, 'cliente_id' => $clienteFrigorifico, 'destino' => 'Rosario', 'bruto' => 420000, 'viatico' => 22000]);
agregarGasto($pdo, $f3, 'Peaje', 9000, 'Peajes ruta');
agregarGasto($pdo, $f3, 'Playa', 18000, 'Playa de camiones');

$f4 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 3), 'camion_id' => $camion2, 'chofer_id' => $chofer2, 'cliente_id' => $clienteMolinos, 'destino' => 'Mendoza', 'bruto' => 610000, 'viatico' => 30000]);
agregarGasto($pdo, $f4, 'Peaje', 10000, 'Peajes ruta');
agregarGasto($pdo, $f4, 'Playa', 15000, 'Playa de camiones');

$f5 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 14), 'camion_id' => $camion2, 'chofer_id' => $chofer2, 'cliente_id' => $clienteFrigorifico, 'destino' => 'Salta', 'bruto' => 350000, 'viatico' => 18000]);
agregarGasto($pdo, $f5, 'Peaje', 6000, 'Peajes ruta');
agregarGasto($pdo, $f5, 'Playa', 10000, 'Playa de camiones');

$f6 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 6), 'camion_id' => $camion3, 'chofer_id' => $chofer3, 'cliente_id' => $clienteCitricola, 'destino' => 'San Juan', 'bruto' => 440000, 'viatico' => 24000]);
agregarGasto($pdo, $f6, 'Peaje', 8000, 'Peajes ruta');
agregarGasto($pdo, $f6, 'Playa', 14000, 'Playa de camiones');

$f7 = crearFlete($pdo, ['fecha' => fecha($offsetMesAnterior + 20), 'camion_id' => $camion3, 'chofer_id' => $chofer3, 'cliente_id' => $clienteMolinos, 'destino' => 'Jujuy', 'bruto' => 395000, 'viatico' => 20000]);
agregarGasto($pdo, $f7, 'Peaje', 7000, 'Peajes ruta');
agregarGasto($pdo, $f7, 'Playa', 16000, 'Playa de camiones');

// --- Mes actual, hasta hoy (todavía no liquidado) ---
$f8  = crearFlete($pdo, ['fecha' => fecha($offsetMesActual + 2), 'camion_id' => $camion1, 'chofer_id' => $chofer1, 'cliente_id' => $clienteCitricola, 'destino' => 'Catamarca', 'bruto' => 410000, 'viatico' => 21000]);
$f9  = crearFlete($pdo, ['fecha' => fecha(-3), 'camion_id' => $camion1, 'chofer_id' => $chofer1, 'cliente_id' => $clienteFrigorifico, 'destino' => 'La Rioja', 'bruto' => 385000, 'viatico' => 19000]);
$f10 = crearFlete($pdo, ['fecha' => fecha($offsetMesActual + 5), 'camion_id' => $camion2, 'chofer_id' => $chofer2, 'cliente_id' => $clienteMolinos, 'destino' => 'Santiago del Estero', 'bruto' => 560000, 'viatico' => 28000]);
$f11 = crearFlete($pdo, ['fecha' => fecha(-1), 'camion_id' => $camion3, 'chofer_id' => $chofer3, 'cliente_id' => $clienteFrigorifico, 'destino' => 'Chaco', 'bruto' => 430000, 'viatico' => 22000]);

echo "Cerrando y pagando liquidaciones del mes anterior...\n";

function cerrarYPagarLiquidacion(PDO $pdo, int $choferId, string $periodo, bool $pagar, ?int $cuentaId, int $usuarioId): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cantidad, COALESCE(SUM(comision_chofer),0) AS total_comisiones, COALESCE(SUM(viatico_adelanto),0) AS viaticos_adelantados
         FROM fletes WHERE chofer_id = ? AND DATE_FORMAT(fecha, "%Y-%m") = ? AND liquidacion_id IS NULL'
    );
    $stmt->execute([$choferId, $periodo]);
    $resumenFletes = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(gv.importe),0) AS gastos_reales
         FROM gastos_viaje gv JOIN fletes f ON f.id = gv.flete_id
         WHERE f.chofer_id = ? AND DATE_FORMAT(f.fecha, "%Y-%m") = ? AND f.liquidacion_id IS NULL'
    );
    $stmt->execute([$choferId, $periodo]);
    $gastosReales = (float) $stmt->fetchColumn();

    $totalComisiones = (float) $resumenFletes['total_comisiones'];
    $viaticos        = (float) $resumenFletes['viaticos_adelantados'];
    $ajuste          = max($gastosReales - $viaticos, 0);
    $totalPagar      = $totalComisiones + $ajuste;

    $pdo->prepare(
        'INSERT INTO liquidaciones (chofer_id, periodo, total_comisiones, viaticos_adelantados, gastos_reales, ajuste_viaticos, total_pagar, estado, fecha_cierre)
         VALUES (?, ?, ?, ?, ?, ?, ?, "cerrada", ?)'
    )->execute([$choferId, $periodo, $totalComisiones, $viaticos, $gastosReales, $ajuste, $totalPagar, fecha(-25)]);
    $liquidacionId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE fletes SET liquidacion_id = ? WHERE chofer_id = ? AND DATE_FORMAT(fecha, "%Y-%m") = ? AND liquidacion_id IS NULL')
        ->execute([$liquidacionId, $choferId, $periodo]);

    if ($pagar) {
        $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Sueldos');
        $pdo->prepare(
            'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
             VALUES (?, ?, "egreso", ?, ?, "liquidacion", ?, ?, ?)'
        )->execute([fecha(-24), $cuentaId, $categoriaId, $totalPagar, $liquidacionId, 'Pago de liquidación ' . $periodo, $usuarioId]);
        $movimientoId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE liquidaciones SET estado="pagada", movimiento_id=? WHERE id=?')->execute([$movimientoId, $liquidacionId]);
    }
}

$periodoAnterior = date('Y-m', strtotime('first day of last month'));
cerrarYPagarLiquidacion($pdo, $chofer1, $periodoAnterior, true, $cuentaGalicia, $usuarioId);
cerrarYPagarLiquidacion($pdo, $chofer2, $periodoAnterior, false, null, $usuarioId);
cerrarYPagarLiquidacion($pdo, $chofer3, $periodoAnterior, true, $cuentaMacro, $usuarioId);

echo "Cargando cheques recibidos...\n";

function crearChequeRecibido(PDO $pdo, array $d): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO cheques (tipo, formato, numero, banco_librador, cliente_id, flete_id, importe, fecha_emision, fecha_pago, estado)
         VALUES ("recibido", "fisico", ?, ?, ?, ?, ?, ?, ?, "en_cartera")'
    );
    $stmt->execute([$d['numero'], $d['banco'], $d['cliente_id'], $d['flete_id'], $d['importe'], $d['fecha_emision'], $d['fecha_pago']]);
    return (int) $pdo->lastInsertId();
}

function moverChequeRecibido(PDO $pdo, int $chequeId, string $estadoAnterior, string $nuevo, int $usuarioId, array $extra = []): void
{
    switch ($nuevo) {
        case 'depositado':
            $pdo->prepare('UPDATE cheques SET estado="depositado", cuenta_deposito_id=? WHERE id=?')->execute([$extra['cuenta_id'], $chequeId]);
            registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'depositado', $usuarioId);
            break;
        case 'acreditado':
            $stmt = $pdo->prepare('SELECT * FROM cheques WHERE id=?');
            $stmt->execute([$chequeId]);
            $cheque = $stmt->fetch();
            $pdo->prepare('UPDATE cheques SET estado="acreditado" WHERE id=?')->execute([$chequeId]);
            registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'acreditado', $usuarioId);
            $pdo->prepare(
                'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                 VALUES (?, ?, "ingreso", ?, "cheque", ?, ?, ?)'
            )->execute([$extra['fecha'], $cheque['cuenta_deposito_id'], $cheque['importe'], $chequeId, 'Cheque acreditado ' . $cheque['numero'], $usuarioId]);
            if ($cheque['flete_id']) {
                $pdo->prepare('UPDATE fletes SET estado_cobro="cobrado" WHERE id=?')->execute([$cheque['flete_id']]);
            }
            break;
        case 'vendido':
            $stmt = $pdo->prepare('SELECT * FROM cheques WHERE id=?');
            $stmt->execute([$chequeId]);
            $cheque = $stmt->fetch();
            $pdo->prepare('UPDATE cheques SET estado="vendido", financiera_id=?, monto_neto_venta=? WHERE id=?')
                ->execute([$extra['financiera_id'], $extra['monto_neto'], $chequeId]);
            registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'vendido', $usuarioId);
            $pdo->prepare(
                'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                 VALUES (?, ?, "ingreso", ?, "cheque", ?, ?, ?)'
            )->execute([$extra['fecha'], $extra['cuenta_id'], $extra['monto_neto'], $chequeId, 'Venta a financiera del cheque ' . $cheque['numero'], $usuarioId]);
            if ($cheque['flete_id']) {
                $pdo->prepare('UPDATE fletes SET estado_cobro="cobrado" WHERE id=?')->execute([$cheque['flete_id']]);
            }
            break;
        case 'endosado':
            $stmt = $pdo->prepare('SELECT * FROM cheques WHERE id=?');
            $stmt->execute([$chequeId]);
            $cheque = $stmt->fetch();
            $pdo->prepare('UPDATE cheques SET estado="endosado", destinatario=? WHERE id=?')->execute([$extra['destinatario'], $chequeId]);
            registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'endosado', $usuarioId);
            if ($cheque['flete_id']) {
                $pdo->prepare('UPDATE fletes SET estado_cobro="cobrado" WHERE id=?')->execute([$cheque['flete_id']]);
            }
            break;
        case 'rechazado':
            $stmt = $pdo->prepare('SELECT * FROM cheques WHERE id=?');
            $stmt->execute([$chequeId]);
            $cheque = $stmt->fetch();
            $pdo->prepare('UPDATE cheques SET estado="rechazado" WHERE id=?')->execute([$chequeId]);
            registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'rechazado', $usuarioId, $extra['gastos'] ?? null);
            if (!empty($extra['gastos'])) {
                $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Gastos bancarios');
                $pdo->prepare(
                    'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                     VALUES (?, ?, "egreso", ?, ?, "cheque", ?, ?, ?)'
                )->execute([$extra['fecha'], $cheque['cuenta_deposito_id'], $categoriaId, $extra['gastos'], $chequeId, 'Gastos por rechazo del cheque ' . $cheque['numero'], $usuarioId]);
            }
            break;
        case 'recuperado':
            $pdo->prepare('UPDATE cheques SET estado="recuperado" WHERE id=?')->execute([$chequeId]);
            registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'recuperado', $usuarioId);
            break;
    }
}

$ch1 = crearChequeRecibido($pdo, ['numero' => '00446001', 'banco' => 'Banco Nación', 'cliente_id' => $clienteMolinos, 'flete_id' => $f1, 'importe' => 480000, 'fecha_emision' => fecha($offsetMesAnterior + 2), 'fecha_pago' => fecha($offsetMesAnterior + 32)]);
moverChequeRecibido($pdo, $ch1, 'en_cartera', 'depositado', $usuarioId, ['cuenta_id' => $cuentaGalicia]);
moverChequeRecibido($pdo, $ch1, 'depositado', 'acreditado', $usuarioId, ['fecha' => fecha($offsetMesAnterior + 33)]);

$ch2 = crearChequeRecibido($pdo, ['numero' => '00446002', 'banco' => 'Banco Macro', 'cliente_id' => $clienteCitricola, 'flete_id' => $f2, 'importe' => 390000, 'fecha_emision' => fecha($offsetMesAnterior + 10), 'fecha_pago' => fecha($offsetMesAnterior + 40)]);
moverChequeRecibido($pdo, $ch2, 'en_cartera', 'vendido', $usuarioId, ['financiera_id' => $financiera1, 'monto_neto' => 362700, 'cuenta_id' => $cuentaCaja, 'fecha' => fecha($offsetMesAnterior + 12)]);

$ch3 = crearChequeRecibido($pdo, ['numero' => '00446003', 'banco' => 'Banco Nación', 'cliente_id' => $clienteMolinos, 'flete_id' => $f4, 'importe' => 610000, 'fecha_emision' => fecha($offsetMesAnterior + 3), 'fecha_pago' => fecha($offsetMesAnterior + 33)]);
moverChequeRecibido($pdo, $ch3, 'en_cartera', 'depositado', $usuarioId, ['cuenta_id' => $cuentaMacro]);
moverChequeRecibido($pdo, $ch3, 'depositado', 'acreditado', $usuarioId, ['fecha' => fecha($offsetMesAnterior + 34)]);

$ch4 = crearChequeRecibido($pdo, ['numero' => '00446004', 'banco' => 'Banco Galicia', 'cliente_id' => $clienteFrigorifico, 'flete_id' => $f5, 'importe' => 350000, 'fecha_emision' => fecha($offsetMesAnterior + 14), 'fecha_pago' => fecha($offsetMesAnterior + 20)]);
moverChequeRecibido($pdo, $ch4, 'en_cartera', 'depositado', $usuarioId, ['cuenta_id' => $cuentaGalicia]);
moverChequeRecibido($pdo, $ch4, 'depositado', 'rechazado', $usuarioId, ['fecha' => fecha($offsetMesAnterior + 21), 'gastos' => 8500]);
moverChequeRecibido($pdo, $ch4, 'rechazado', 'recuperado', $usuarioId);

$ch5 = crearChequeRecibido($pdo, ['numero' => '00446005', 'banco' => 'Banco Nación', 'cliente_id' => $clienteCitricola, 'flete_id' => $f6, 'importe' => 440000, 'fecha_emision' => fecha($offsetMesAnterior + 6), 'fecha_pago' => fecha($offsetMesAnterior + 36)]);
moverChequeRecibido($pdo, $ch5, 'en_cartera', 'endosado', $usuarioId, ['destinatario' => 'Repuestos del Norte SRL']);

crearChequeRecibido($pdo, ['numero' => '00446006', 'banco' => 'Banco Macro', 'cliente_id' => $clienteMolinos, 'flete_id' => $f8, 'importe' => 410000, 'fecha_emision' => fecha($offsetMesActual + 2), 'fecha_pago' => fecha(15)]);
crearChequeRecibido($pdo, ['numero' => '00446007', 'banco' => 'Banco Galicia', 'cliente_id' => $clienteFrigorifico, 'flete_id' => null, 'importe' => 150000, 'fecha_emision' => fecha(-5), 'fecha_pago' => fecha(5)]);

echo "Cargando cheques emitidos...\n";

$stmt = $pdo->prepare(
    'INSERT INTO cheques (tipo, formato, numero, cuenta_id, destinatario, importe, fecha_emision, fecha_pago, estado)
     VALUES ("emitido", "fisico", ?, ?, ?, ?, ?, ?, "emitido")'
);
$stmt->execute(['00512001', $cuentaGalicia, 'Repuestos del Norte SRL', 95000, fecha($offsetMesAnterior + 25), fecha($offsetMesAnterior + 25)]);
$emit1Id = (int) $pdo->lastInsertId();
$pdo->prepare('UPDATE cheques SET estado="debitado" WHERE id=?')->execute([$emit1Id]);
registrarMovimientoCheque($pdo, $emit1Id, 'emitido', 'debitado', $usuarioId);
$pdo->prepare(
    'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
     VALUES (?, ?, "egreso", ?, "cheque", ?, ?, ?)'
)->execute([fecha($offsetMesAnterior + 25), $cuentaGalicia, 95000, $emit1Id, 'Cheque propio debitado 00512001', $usuarioId]);

$stmt->execute(['00512002', $cuentaMacro, 'Gomería Central', 220000, fecha(0), fecha(10)]);

echo "Cargando combustible...\n";

function cargarCombustible(PDO $pdo, int $camionId, ?int $choferId, int $estacionId, string $modalidad, string $fecha, int $km, float $litros): void
{
    $importe = round($litros * 960, 2);
    $pdo->prepare(
        'INSERT INTO cargas_combustible (fecha, camion_id, chofer_id, estacion_id, litros, importe, km, modalidad)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$fecha, $camionId, $choferId, $estacionId, $litros, $importe, $km, $modalidad]);
    $pdo->prepare('UPDATE camiones SET km_actual = ? WHERE id = ?')->execute([$km, $camionId]);
}

cargarCombustible($pdo, $camion1, $chofer1, $estacionYpf, 'cta_cte', fecha($offsetMesAnterior + 1), 379500, 380);
cargarCombustible($pdo, $camion1, $chofer1, $estacionYpf, 'cta_cte', fecha($offsetMesAnterior + 12), 382000, 390);
cargarCombustible($pdo, $camion1, $chofer1, $estacionAxion, 'contado', fecha($offsetMesActual + 3), 384500, 385);
cargarCombustible($pdo, $camion1, $chofer1, $estacionYpf, 'cta_cte', fecha(-2), 386800, 370);

cargarCombustible($pdo, $camion2, $chofer2, $estacionYpf, 'cta_cte', fecha($offsetMesAnterior + 2), 408500, 400);
cargarCombustible($pdo, $camion2, $chofer2, $estacionYpf, 'cta_cte', fecha($offsetMesAnterior + 15), 410800, 395);
cargarCombustible($pdo, $camion2, $chofer2, $estacionAxion, 'contado', fecha($offsetMesActual + 4), 412600, 390);
cargarCombustible($pdo, $camion2, $chofer2, $estacionYpf, 'cta_cte', fecha(-1), 414500, 380);

cargarCombustible($pdo, $camion3, $chofer3, $estacionYpf, 'cta_cte', fecha($offsetMesAnterior + 4), 207500, 360);
cargarCombustible($pdo, $camion3, $chofer3, $estacionYpf, 'cta_cte', fecha($offsetMesAnterior + 16), 209200, 355);
cargarCombustible($pdo, $camion3, $chofer3, $estacionAxion, 'contado', fecha($offsetMesActual + 2), 210700, 350);
cargarCombustible($pdo, $camion3, $chofer3, $estacionYpf, 'cta_cte', fecha(-4), 212100, 345);

echo "Cargando resumen de estación...\n";

$pdo->prepare('INSERT INTO resumenes_estacion (estacion_id, periodo, importe_total) VALUES (?, ?, ?)')
    ->execute([$estacionYpf, $periodoAnterior, 610000]);
$resumenId = (int) $pdo->lastInsertId();
$categoriaCombustible = obtenerCategoriaGastoPorNombre($pdo, 'Combustible');
$pdo->prepare(
    'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
     VALUES (?, ?, "egreso", ?, ?, "resumen_estacion", ?, ?, ?)'
)->execute([fecha($offsetMesAnterior + 28), $cuentaGalicia, $categoriaCombustible, 610000, $resumenId, 'Pago resumen de estación ' . $periodoAnterior, $usuarioId]);
$pdo->prepare('UPDATE resumenes_estacion SET pagado=1, movimiento_id=? WHERE id=?')->execute([(int) $pdo->lastInsertId(), $resumenId]);

echo "Cargando remitos de pallets...\n";

function crearRemito(PDO $pdo, int $numero, string $tipo, string $fecha, int $clienteId, int $usuarioId, array $cantidades): void
{
    $pdo->prepare('INSERT INTO remitos (numero, tipo, fecha, cliente_id, usuario_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$numero, $tipo, $fecha, $clienteId, $usuarioId]);
    $remitoId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO pallets_movimientos (remito_id, sanos, rotos, reacondicionados, separadores) VALUES (?, ?, ?, ?, ?)'
    )->execute([$remitoId, $cantidades['sanos'] ?? 0, $cantidades['rotos'] ?? 0, $cantidades['reacondicionados'] ?? 0, $cantidades['separadores'] ?? 0]);
}

crearRemito($pdo, 1, 'recepcion', fecha($offsetMesAnterior + 3), $clienteEnvasadora, $usuarioId, ['sanos' => 200, 'rotos' => 10, 'reacondicionados' => 5, 'separadores' => 50]);
crearRemito($pdo, 2, 'recepcion', fecha($offsetMesAnterior + 16), $clienteEnvasadora, $usuarioId, ['sanos' => 150]);
crearRemito($pdo, 3, 'devolucion', fecha($offsetMesAnterior + 25), $clienteEnvasadora, $usuarioId, ['sanos' => 100, 'separadores' => 20]);
crearRemito($pdo, 4, 'recepcion', fecha($offsetMesActual + 3), $clienteEnvasadora, $usuarioId, ['sanos' => 80, 'rotos' => 5, 'separadores' => 10]);
crearRemito($pdo, 5, 'devolucion', fecha(-2), $clienteEnvasadora, $usuarioId, ['sanos' => 50, 'rotos' => 5]);

echo "Cargando mantenimiento (planes + services)...\n";

function crearService(PDO $pdo, int $camionId, int $tipoId, string $fecha, int $km, ?string $taller, ?float $costo, ?string $obs): int
{
    $pdo->prepare('INSERT INTO services (camion_id, tipo_service_id, fecha, km, costo, taller, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$camionId, $tipoId, $fecha, $km, $costo, $taller, $obs]);
    return (int) $pdo->lastInsertId();
}

function usarRepuesto(PDO $pdo, int $repuestoId, int $cantidad, int $camionId, int $serviceId, int $usuarioId): void
{
    $pdo->prepare('INSERT INTO movimientos_stock (repuesto_id, tipo, cantidad, camion_id, service_id, usuario_id) VALUES (?, "egreso", ?, ?, ?, ?)')
        ->execute([$repuestoId, $cantidad, $camionId, $serviceId, $usuarioId]);
    $pdo->prepare('UPDATE repuestos SET stock_actual = stock_actual - ? WHERE id = ?')->execute([$cantidad, $repuestoId]);
}

function generarEgresoMantenimiento(PDO $pdo, string $fecha, int $cuentaId, float $importe, string $descripcion, int $usuarioId): void
{
    $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Mantenimiento');
    $pdo->prepare(
        'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, descripcion, usuario_id)
         VALUES (?, ?, "egreso", ?, ?, "otro", ?, ?)'
    )->execute([$fecha, $cuentaId, $categoriaId, $importe, $descripcion, $usuarioId]);
}

// Historial previo (no es el "último", solo da profundidad al historial.php)
crearService($pdo, $camion1, $tipoAceite, fecha(-345), 353000, 'Taller Norte', 39000, null);
crearService($pdo, $camion2, $tipoFrenos, fecha(-280), 380000, 'Taller Sur', 32000, null);

// Plan 1: camión 1, aceite y filtros — vencido (rojo)
$s1 = crearService($pdo, $camion1, $tipoAceite, fecha(-165), 368000, 'Taller Norte', 42000, 'Cambio habitual');
usarRepuesto($pdo, $repF100, 1, $camion1, $s1, $usuarioId);
usarRepuesto($pdo, $repF101, 1, $camion1, $s1, $usuarioId);
usarRepuesto($pdo, $repA500, 1, $camion1, $s1, $usuarioId);
generarEgresoMantenimiento($pdo, fecha(-165), $cuentaCaja, 42000, 'Service Cambio de aceite y filtros — AB 123 CD', $usuarioId);
$pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
    ->execute([$camion1, $tipoAceite, 15000, 6]);

// Plan 2: camión 1, cubiertas — por vencer (amarillo)
$s2 = crearService($pdo, $camion1, $tipoCubiertas, fecha(-140), 350000, 'Gomería Central', 280000, 'Juego delantero');
usarRepuesto($pdo, $repC400, 2, $camion1, $s2, $usuarioId);
$pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
    ->execute([$camion1, $tipoCubiertas, 40000, null]);

// Plan 3: camión 2, frenos — al día (verde)
$s3 = crearService($pdo, $camion2, $tipoFrenos, fecha(-100), 402000, 'Taller Sur', 38000, null);
usarRepuesto($pdo, $repB200, 1, $camion2, $s3, $usuarioId);
$pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
    ->execute([$camion2, $tipoFrenos, 20000, null]);

// Plan 4: camión 2, revisión general — por vencer (amarillo)
crearService($pdo, $camion2, $tipoRevision, fecha(-325), 395000, 'Taller Sur', 65000, 'Revisión completa');
generarEgresoMantenimiento($pdo, fecha(-325), $cuentaMacro, 65000, 'Service Revisión general — AC 456 EF', $usuarioId);
$pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
    ->execute([$camion2, $tipoRevision, null, 12]);

// Plan 5: camión 3, embrague — al día (verde)
$s5 = crearService($pdo, $camion3, $tipoEmbrague, fecha(-400), 170000, 'Taller Norte', 180000, 'Kit de embrague completo');
generarEgresoMantenimiento($pdo, fecha(-400), $cuentaGalicia, 180000, 'Service Embrague — AD 789 GH', $usuarioId);
$pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
    ->execute([$camion3, $tipoEmbrague, 60000, 24]);

// Plan 6: camión 3, tren delantero — al día (verde)
$s6 = crearService($pdo, $camion3, $tipoTren, fecha(-250), 185000, 'Taller Sur', 52000, null);
usarRepuesto($pdo, $repB202, 2, $camion3, $s6, $usuarioId);
$pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
    ->execute([$camion3, $tipoTren, 50000, null]);

echo "Listo. Datos de demo generados.\n";
