<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

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

$conteo = ['rojo' => 0, 'amarillo' => 0, 'verde' => 0];
foreach ($filas as $fila) {
    if ($fila['color']) {
        $conteo[$fila['color']]++;
    }
}

$activo       = 'vencimientos';
$tituloPagina = 'Vencimientos de mantenimiento';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Vencimientos de mantenimiento</h1>

<div class="consumo seccion">
  <div><small>Vencidos</small><b class="venc"><?= $conteo['rojo'] ?></b></div>
  <div><small>Por vencer</small><b><?= $conteo['amarillo'] ?></b></div>
  <div><small>Al día</small><b><?= $conteo['verde'] ?></b></div>
</div>

<?php if (!$filas): ?>
  <p class="nota">Todavía no hay planes de mantenimiento cargados. Armalos en <a href="planes.php">Planes</a>.</p>
<?php endif; ?>

<?php foreach ($filas as $fila):
    $plan = $fila['plan'];
    $chipClase = ['rojo' => 'rech', 'amarillo' => 'cartera', 'verde' => 'ok'][$fila['color']] ?? '';
    $chipTexto = ['rojo' => 'vencido', 'amarillo' => 'por vencer', 'verde' => 'al día'][$fila['color']] ?? 'sin historial';
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
          (a los <?= number_format($fila['detalles']['km']['vencimiento'], 0, ',', '.') ?>)
        </span>
      <?php endif; ?>
      <?php if (isset($fila['detalles']['fecha'])): ?>
        <span>
          <?= $fila['detalles']['fecha']['restante_dias'] >= 0
                ? 'Faltan ' . $fila['detalles']['fecha']['restante_dias'] . ' días'
                : 'Vencido hace ' . abs($fila['detalles']['fecha']['restante_dias']) . ' días' ?>
          (<?= formatearFecha($fila['detalles']['fecha']['vencimiento']) ?>)
        </span>
      <?php endif; ?>
    </div>
    <?php if (!$fila['ultimo']): ?>
      <div class="l2">
        <span>Sin service registrado para calcular el vencimiento.</span>
      </div>
    <?php endif; ?>
    <div class="acciones">
      <a href="nuevo.php?camion_id=<?= $plan['camion_id'] ?>&tipo_service_id=<?= $plan['tipo_service_id'] ?>">Registrar service</a>
    </div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
