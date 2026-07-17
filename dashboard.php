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

<h1 class="seccion">Cheques próximos a vencer</h1>
<div class="placeholder-fase">
  <span class="chip cartera">Fase 2</span>
  <p>Las alertas de vencimiento de cheques se activan cuando se construya el módulo de Cheques y tesorería.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
