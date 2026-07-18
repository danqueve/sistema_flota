<?php

require_once __DIR__ . '/includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/includes/funciones.php';

$pdo = obtenerConexion();
$periodoActual = date('Y-m');

// Fletes del mes por camión.
$stmt = $pdo->prepare(
    'SELECT c.id, c.patente, COUNT(f.id) AS cantidad,
            COALESCE(SUM(f.importe_bruto),0) AS total_bruto,
            COALESCE(SUM(f.comision_chofer),0) AS total_comision
     FROM camiones c
     LEFT JOIN fletes f ON f.camion_id = c.id AND DATE_FORMAT(f.fecha, "%Y-%m") = ?
     WHERE c.activo = 1
     GROUP BY c.id, c.patente
     ORDER BY c.patente'
);
$stmt->execute([$periodoActual]);
$fletesPorCamion = $stmt->fetchAll();

// Evolución del consumo (L/100km) por camión, a partir de las cargas de combustible.
$stmt = $pdo->query(
    'SELECT camion_id, fecha,
            litros / (km - LAG(km) OVER (PARTITION BY camion_id ORDER BY km)) * 100 AS consumo_l100
     FROM cargas_combustible
     WHERE km IS NOT NULL
     ORDER BY camion_id, km'
);

$patentesPorCamion = [];
foreach ($fletesPorCamion as $fila) {
    $patentesPorCamion[(int) $fila['id']] = $fila['patente'];
}

$fechasSet = [];
$porCamion = [];
foreach ($stmt->fetchAll() as $fila) {
    if ($fila['consumo_l100'] === null) {
        continue;
    }
    $camionId = (int) $fila['camion_id'];
    $fecha    = $fila['fecha'];
    $fechasSet[$fecha] = true;
    $porCamion[$camionId][$fecha] = round((float) $fila['consumo_l100'], 1);
}

$fechas = array_keys($fechasSet);
sort($fechas);

$datasetsGrafico = [];
foreach ($porCamion as $camionId => $valoresPorFecha) {
    $datos = [];
    foreach ($fechas as $fecha) {
        $datos[] = $valoresPorFecha[$fecha] ?? null;
    }
    $datasetsGrafico[] = [
        'label' => $patentesPorCamion[$camionId] ?? ('Camión #' . $camionId),
        'data'  => $datos,
    ];
}
$labelsGrafico = array_map('formatearFecha', $fechas);

// Cheques recibidos en cartera/depositados que vencen dentro de 7 días (o ya vencidos).
$stmt = $pdo->query(
    "SELECT ch.*, cl.razon_social
     FROM cheques ch
     LEFT JOIN clientes cl ON cl.id = ch.cliente_id
     WHERE ch.tipo = 'recibido' AND ch.estado IN ('en_cartera','depositado')
       AND ch.fecha_pago <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY ch.fecha_pago ASC"
);
$chequesPorVencer = $stmt->fetchAll();

$posicion = $pdo->query('SELECT * FROM v_posicion')->fetch();

$repuestosBajoMinimo = $pdo->query(
    'SELECT * FROM repuestos WHERE activo = 1 AND stock_actual <= stock_minimo ORDER BY nombre'
)->fetchAll();

$stockPalletsTotal = $pdo->query(
    'SELECT COALESCE(SUM(sanos),0) AS sanos, COALESCE(SUM(rotos),0) AS rotos,
            COALESCE(SUM(reacondicionados),0) AS reacondicionados, COALESCE(SUM(separadores),0) AS separadores
     FROM v_pallets_stock'
)->fetch();

$ultimoRemito = $pdo->query(
    'SELECT r.*, cl.razon_social
     FROM remitos r
     JOIN clientes cl ON cl.id = r.cliente_id
     ORDER BY r.id DESC LIMIT 1'
)->fetch();

$vencimientosMantenimiento = array_values(array_filter(
    calcularVencimientosMantenimiento($pdo),
    function ($fila) {
        return in_array($fila['color'], ['rojo', 'amarillo'], true);
    }
));

$scriptsPagina = [
    'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js',
    BASE_URL . '/assets/js/dashboard.js',
];
$tituloPagina = 'Inicio';
require __DIR__ . '/includes/header.php';
?>

<h1>Hola, <?= htmlspecialchars(usuarioActual()['nombre']) ?></h1>

<h1 class="seccion">Fletes de <?= htmlspecialchars(ucfirst(nombreMes((int) date('n'))) . ' ' . date('Y')) ?> por camión</h1>

<?php foreach ($fletesPorCamion as $camion): ?>
  <div class="item">
    <div class="l1"><span class="num"><?= htmlspecialchars($camion['patente']) ?></span></div>
    <div class="consumo">
      <div><small>Fletes</small><b><?= (int) $camion['cantidad'] ?></b></div>
      <div><small>Facturado</small><b><?= formatearImporte((float) $camion['total_bruto']) ?></b></div>
      <div><small>Comisión</small><b><?= formatearImporte((float) $camion['total_comision']) ?></b></div>
    </div>
  </div>
<?php endforeach; ?>

<a href="<?= BASE_URL ?>/modulos/fletes/listado.php" class="btn sec">Ver todos los fletes</a>

<h1 class="seccion">Consumo de combustible por camión</h1>

<?php if (!$datasetsGrafico): ?>
  <p class="nota">Todavía no hay suficientes cargas de combustible para graficar el consumo.</p>
<?php else: ?>
  <canvas id="graficoConsumo" height="220"></canvas>
  <script>
    window.GRAFICO_LABELS = <?= json_encode($labelsGrafico) ?>;
    window.GRAFICO_DATASETS = <?= json_encode($datasetsGrafico) ?>;
  </script>
<?php endif; ?>

<h1 class="seccion">Posición de tesorería</h1>

<div class="consumo">
  <div><small>Saldo actual</small><b><?= formatearImporte((float) $posicion['saldo_actual']) ?></b></div>
  <div><small>Por entrar</small><b><?= formatearImporte((float) $posicion['por_entrar']) ?></b></div>
  <div><small>Por salir</small><b><?= formatearImporte((float) $posicion['por_salir']) ?></b></div>
</div>
<a href="<?= BASE_URL ?>/modulos/tesoreria/listado.php" class="btn sec">Ver tesorería</a>

<h1 class="seccion">Cheques próximos a vencer</h1>

<?php foreach ($chequesPorVencer as $cheque):
    $vencido = $cheque['fecha_pago'] < date('Y-m-d');
?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($cheque['banco_librador']) ?> · <?= htmlspecialchars($cheque['numero']) ?></span>
      <span class="imp"><?= formatearImporte((float) $cheque['importe']) ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($cheque['razon_social'] ?? 'Sin cliente') ?></span>
      <span class="venc">cobro <?= formatearFecha($cheque['fecha_pago']) ?><?= $vencido ? ' · ¡vencido!' : '' ?></span>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$chequesPorVencer): ?>
  <p class="nota">No hay cheques venciendo en los próximos 7 días.</p>
<?php endif; ?>

<a href="<?= BASE_URL ?>/modulos/cheques/cartera.php" class="btn sec">Ver todos los cheques</a>

<h1 class="seccion">Repuestos bajo mínimo</h1>

<?php foreach ($repuestosBajoMinimo as $repuesto): ?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($repuesto['nombre']) ?></span>
      <span class="venc"><?= (int) $repuesto['stock_actual'] ?> / mín. <?= (int) $repuesto['stock_minimo'] ?></span>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$repuestosBajoMinimo): ?>
  <p class="nota">Todo el stock está por encima del mínimo.</p>
<?php endif; ?>

<a href="<?= BASE_URL ?>/modulos/stock/index.php" class="btn sec">Ver stock</a>

<h1 class="seccion">Tarimas (pallets)</h1>

<div class="consumo">
  <div><small>Sanas</small><b><?= (int) $stockPalletsTotal['sanos'] ?></b></div>
  <div><small>Rotas</small><b><?= (int) $stockPalletsTotal['rotos'] ?></b></div>
  <div><small>Reac.</small><b><?= (int) $stockPalletsTotal['reacondicionados'] ?></b></div>
  <div><small>Separ.</small><b><?= (int) $stockPalletsTotal['separadores'] ?></b></div>
</div>

<?php if ($ultimoRemito): ?>
  <p class="nota">
    Último remito: <a href="<?= BASE_URL ?>/modulos/pallets/detalle.php?id=<?= $ultimoRemito['id'] ?>">Nº <?= str_pad((string) $ultimoRemito['numero'], 6, '0', STR_PAD_LEFT) ?></a>
    (<?= $ultimoRemito['tipo'] === 'recepcion' ? 'recepción' : 'devolución' ?> · <?= htmlspecialchars($ultimoRemito['razon_social']) ?> · <?= formatearFecha($ultimoRemito['fecha']) ?>)
  </p>
<?php else: ?>
  <p class="nota">Todavía no hay remitos cargados.</p>
<?php endif; ?>

<a href="<?= BASE_URL ?>/modulos/pallets/nuevo.php" class="btn sec">Ver pallets</a>

<h1 class="seccion">Mantenimiento</h1>

<?php foreach ($vencimientosMantenimiento as $fila):
    $plan = $fila['plan'];
    $chipClase = $fila['color'] === 'rojo' ? 'rech' : 'cartera';
    $chipTexto = $fila['color'] === 'rojo' ? 'vencido' : 'por vencer';
?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($plan['patente']) ?> · <?= htmlspecialchars($plan['tipo_nombre']) ?></span>
      <span class="chip <?= $chipClase ?>"><?= $chipTexto ?></span>
    </div>
    <div class="l2">
      <?php if (isset($fila['detalles']['km'])): ?>
        <span>
          <?= $fila['detalles']['km']['restante'] >= 0
                ? 'Faltan ' . number_format($fila['detalles']['km']['restante'], 0, ',', '.') . ' km'
                : 'Vencido hace ' . number_format(abs($fila['detalles']['km']['restante']), 0, ',', '.') . ' km' ?>
        </span>
      <?php endif; ?>
      <?php if (isset($fila['detalles']['fecha'])): ?>
        <span>
          <?= $fila['detalles']['fecha']['restante_dias'] >= 0
                ? 'Faltan ' . $fila['detalles']['fecha']['restante_dias'] . ' días'
                : 'Vencido hace ' . abs($fila['detalles']['fecha']['restante_dias']) . ' días' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$vencimientosMantenimiento): ?>
  <p class="nota">Todo el mantenimiento está al día.</p>
<?php endif; ?>

<a href="<?= BASE_URL ?>/modulos/mantenimiento/vencimientos.php" class="btn sec">Ver vencimientos</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
