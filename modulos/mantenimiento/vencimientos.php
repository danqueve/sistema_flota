<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$filas = calcularVencimientosMantenimiento($pdo);

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
